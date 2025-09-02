<?php
// Database connection and flash functionality
include 'connect.php';
include 'includes/flash.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for better security
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
}

$pageTitle = 'Courses Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Handle form submission for adding new course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($_POST['action'] === 'add') {
        $course_name = $conn->real_escape_string($_POST['course_name']);
        $course_code = $conn->real_escape_string($_POST['course_code']);
        $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $hours_per_week = isset($_POST['hours_per_week']) ? (int)$_POST['hours_per_week'] : 3;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "INSERT INTO courses (name, code, department_id, hours_per_week, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiii", $course_name, $course_code, $department_id, $hours_per_week, $is_active);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('courses.php', 'success', 'Course added successfully!');
        } else {
            $error_message = "Error adding course: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'bulk_import' && isset($_POST['import_data'])) {
        $import_data = json_decode($_POST['import_data'], true);
        if ($import_data) {
            $success_count = 0;
            $ignored_count = 0;
            $error_count = 0;

            // Prepare a check statement to detect existing (code)
            $check_sql = "SELECT id FROM courses WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);

            foreach ($import_data as $row) {
                $course_name = isset($row['name']) ? $conn->real_escape_string($row['name']) : '';
                $course_code = isset($row['code']) ? $conn->real_escape_string($row['code']) : '';
                $department_id = isset($row['department_id']) ? (int)$row['department_id'] : null;
                $hours_per_week = isset($row['hours_per_week']) ? (int)$row['hours_per_week'] : 3;
                $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;

                if ($course_name === '' || $course_code === '') {
                    $error_count++;
                    continue;
                }

                // Skip if course with same code exists
                if ($check_stmt) {
                    $check_stmt->bind_param("s", $course_code);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result();
                    if ($existing && $existing->num_rows > 0) {
                        $ignored_count++;
                        continue;
                    }
                }

                $sql = "INSERT INTO courses (name, code, department_id, hours_per_week, is_active) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) { $error_count++; continue; }
                $stmt->bind_param("ssiii", $course_name, $course_code, $department_id, $hours_per_week, $is_active);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }

            if ($check_stmt) $check_stmt->close();

            if ($success_count > 0) {
                $msg = "Successfully imported $success_count courses!";
                if ($ignored_count > 0) $msg .= " $ignored_count duplicates ignored.";
                if ($error_count > 0) $msg .= " $error_count records failed to import.";
                redirect_with_flash('courses.php', 'success', $msg);
            } else {
                if ($ignored_count > 0 && $error_count === 0) {
                    $success_message = "No new courses imported. $ignored_count duplicates ignored.";
                } else {
                    $error_message = "No courses were imported. Please check your data.";
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE courses SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('courses.php', 'success', 'Course deleted successfully!');
        } else {
            $error_message = "Error deleting course: " . $conn->error;
        }
        $stmt->close();
    }
}

// Include stream manager (use include_once to avoid redeclaration)
include_once 'includes/stream_manager.php';
$streamManager = getStreamManager();

// Fetch courses with department names
$sql = "SELECT c.*, d.name as department_name, d.code as department_code
        FROM courses c 
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE c.is_active = 1
        ORDER BY c.department_id IS NULL DESC, d.name, c.name";
$result = $conn->query($sql);

// Fetch departments for dropdown
$dept_sql = "SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);

// Build departments list for client-side validation
$departments_list = [];
if ($dept_result) {
    while ($d = $dept_result->fetch_assoc()) {
        $departments_list[] = $d;
    }
}

// Get course statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_courses,
        COUNT(department_id) as assigned_courses,
        COUNT(*) - COUNT(department_id) as unassigned_courses
    FROM courses 
    WHERE is_active = 1
";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_courses' => 0, 'assigned_courses' => 0, 'unassigned_courses' => 0];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-book me-2"></i>Courses Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="fas fa-plus me-2"></i>Add New Course
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-3 m-3">
            <div class="col-md-4">
                <div class="card theme-card bg-theme-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['total_courses']; ?></h4>
                                <p class="card-text">Total Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card theme-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['assigned_courses']; ?></h4>
                                <p class="card-text">Assigned to Departments</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card theme-card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['unassigned_courses']; ?></h4>
                                <p class="card-text">Unassigned Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-container m-3">
            <input type="text" class="search-input" placeholder="Search courses...">
        </div>

        <div class="table-responsive">
            <table class="table" id="coursesTable">
                <thead>
                    <tr>
                        <th>Course Name</th>
                        <th>Code</th>
                        <th>Department</th>
                        <th>Hours/Week</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="<?php echo $row['department_id'] ? '' : 'table-warning'; ?>">
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($row['code']); ?></span></td>
                                <td>
                                    <?php if ($row['department_id']): ?>
                                        <span class="badge bg-success">
                                            <?php echo htmlspecialchars($row['department_code'] . ' - ' . $row['department_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unassigned</span>
                                    <?php endif; ?>
                                </td>

                                <td><span class="badge bg-info"><?php echo htmlspecialchars($row['hours_per_week'] ?? 'N/A'); ?> hrs/week</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editCourse(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="fas fa-book"></i>
                                <p>No courses found. Add your first course to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="course_name" class="form-label">Course Name *</label>
                                <input type="text" class="form-control" id="course_name" name="course_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="course_code" class="form-label">Course Code *</label>
                                <input type="text" class="form-control" id="course_code" name="course_code" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department (Optional)</label>
                                <?php if ($dept_result && $dept_result->num_rows > 0): ?>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">No Department</option>
                                    <?php while ($dept = $dept_result->fetch_assoc()): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    No departments found. <a href="department.php" class="btn btn-sm btn-primary ms-2">Create Department</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hours_per_week" class="form-label">Hours Per Week *</label>
                                <select class="form-select" id="hours_per_week" name="hours_per_week" required>
                                    <option value="">Select Hours</option>
                                    <option value="1">1 Hour</option>
                                    <option value="2">2 Hours</option>
                                    <option value="3">3 Hours</option>
                                    <option value="4">4 Hours</option>
                                    <option value="5">5 Hours</option>
                                    <option value="6">6 Hours</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active Course
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Courses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-upload fa-3x text-primary mb-3"></i>
                    <h6>Upload CSV File</h6>
                    <p class="text-muted">Drop your CSV file here or click to browse</p>
                </div>

                <div class="mb-3">
                    <div class="upload-area" id="uploadArea" style="border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; background: #f8f9fa; cursor: pointer;">
                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-3"></i>
                        <p class="mb-2">Drop CSV file here or <strong>click to browse</strong></p>
                        <small class="text-muted">Supported format: CSV (headers: name,code,department_id,hours_per_week,is_active)</small>
                    </div>
                    <input type="file" class="form-control d-none" id="csvFile" accept=".csv,text/csv">
                </div>

                <div class="mb-3">
                    <h6>Preview (first 10 rows)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm" id="previewTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Department ID</th>
                                    <th>Hours/Week</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="processBtn" disabled>Process File</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Embed existing course codes and departments for client-side validation
// Courses are global; do not filter by stream
$existing_codes = [];
$codes_res = $conn->query("SELECT code FROM courses WHERE is_active = 1");
if ($codes_res) {
    while ($r = $codes_res->fetch_assoc()) {
        $existing_codes[] = $r['code'];
    }
}
?>
<script>
var existingCourseCodes = <?php echo json_encode($existing_codes); ?> || [];
var existingCourseCodesSet = {};
existingCourseCodes.forEach(function(code){ if (code) existingCourseCodesSet[code.trim().toUpperCase()] = true; });

// Departments list for matching by name or code
var departmentsList = <?php echo json_encode($departments_list); ?> || [];
var deptNameToId = {};
var deptCodeToId = {};
departmentsList.forEach(function(d){
    if (d.name) deptNameToId[d.name.trim().toUpperCase()] = d.id;
    if (d.code) deptCodeToId[d.code.trim().toUpperCase()] = d.id;
});
</script>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
function editCourse(id) {
    // TODO: Implement edit functionality
    alert('Edit functionality will be implemented here for course ID: ' + id);
}
</script>

<script>
let importDataCourses = [];

function parseCSVCourses(csvText) {
    // Accept either comma or tab separated files; normalize to tabs for header parsing
    const rawLines = csvText.split('\n').filter(l => l.trim());
    // Detect delimiter from first line (tab or comma)
    const delimiter = rawLines[0].includes('\t') ? '\t' : ',';
    const headers = rawLines[0].split(delimiter).map(h => h.trim().replace(/"/g, ''));
    const data = [];
    for (let i = 1; i < rawLines.length; i++) {
        const values = rawLines[i].split(delimiter).map(v => v.trim().replace(/"/g, ''));
        const row = {};
        headers.forEach((header, index) => {
            row[header] = values[index] || '';
        });
        data.push(row);
    }
    return data;
}

function validateCoursesData(data) {
    return data.map(row => {
        const validated = {
            name: row['name'] || row['Name'] || row.course_name || row.courseName || '',
            code: row['code'] || row['Code'] || row.course_code || row.courseCode || '',
            department_id: row['department_id'] || row['Department_ID'] || row.department || row.Department || '',
            hours_per_week: row['hours_per_week'] || row['Hours/Week'] || row.hours_per_week || row.hoursPerWeek || '3',
            is_active: (row.is_active || row.isActive || '1') === '1' ? '1' : '0'
        };
        validated.valid = true;
        validated.errors = [];
        // Required fields: name, code, hours_per_week
        if (!validated.name.trim()) { validated.valid = false; validated.errors.push('Name required'); }
        if (!validated.code.trim()) { validated.valid = false; validated.errors.push('Code required'); }
        if (!validated.hours_per_week.toString().trim()) { validated.valid = false; validated.errors.push('Hours per week required'); }

        // If department_id provided, ensure it matches one of the known departments
        const deptIdKey = validated.department_id.toString().trim();
        if (deptIdKey && deptIdKey !== 'null' && deptIdKey !== '') {
            const found = departmentsList.some(function(d){ return d.id.toString() === deptIdKey; });
            if (!found) {
                validated.valid = false;
                validated.errors.push('Department ID not found');
            }
        }

        // Check for duplicate code
        if (existingCourseCodesSet[validated.code.trim().toUpperCase()]) {
            validated.valid = false;
            validated.errors.push('Course code already exists.');
        }
        return validated;
    });
}

function showPreviewCourses() {
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = '';
    const previewRows = importDataCourses.slice(0, 10);
    let validCount = 0;

    previewRows.forEach((row, idx) => {
        const tr = document.createElement('tr');
        tr.className = row.valid ? '' : 'table-danger';

        // Determine status badge/text
        let validationHtml = '';
        if (row.valid) {
            validationHtml = '<span class="text-success">✓ Valid</span>';
            validCount++;
        } else {
            const isExisting = row.errors && row.errors.some(e => e.toLowerCase().includes('already exists'));
            if (isExisting) {
                validationHtml = '<span class="badge bg-secondary">Skipped (exists)</span>';
            } else {
                validationHtml = '<span class="text-danger">✗ ' + (row.errors ? row.errors.join(', ') : 'Invalid') + '</span>';
            }
        }

        tr.innerHTML = `
            <td>${row.name}</td>
            <td>${row.code}</td>
            <td>${row.department_id || 'N/A'}</td>
            <td>${row.hours_per_week}</td>
            <td>${validationHtml}</td>
        `;
        tbody.appendChild(tr);
    });

    // Update process button to show how many valid rows will be imported
    const processBtn = document.getElementById('processBtn');
    if (processBtn) {
        if (validCount > 0) {
            processBtn.disabled = false;
            processBtn.textContent = `Process (${validCount})`;
        } else {
            processBtn.disabled = true;
            processBtn.textContent = 'Process File';
        }
    }
}

function processCoursesFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = parseCSVCourses(e.target.result);
        importDataCourses = validateCoursesData(data);
        showPreviewCourses();
        // process button state is updated inside showPreviewCourses
    };
    reader.readAsText(file);
}

// Set up drag/drop and file input
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csvFile');
    const processBtn = document.getElementById('processBtn');

    uploadArea.addEventListener('click', () => fileInput.click());
    uploadArea.addEventListener('dragover', function(e){ e.preventDefault(); this.style.borderColor='#007bff'; this.style.background='#e3f2fd'; });
    uploadArea.addEventListener('dragleave', function(e){ e.preventDefault(); this.style.borderColor='#ccc'; this.style.background='#f8f9fa'; });
    uploadArea.addEventListener('drop', function(e){
        e.preventDefault();
        this.style.borderColor='#ccc';
        this.style.background='#f8f9fa';
        const files = e.dataTransfer.files;
        console.log('drop event files:', files);
        if (files && files.length) {
            // avoid assigning to fileInput.files (may be read-only in some browsers)
            processCoursesFile(files[0]);
        }
    });
    fileInput.addEventListener('change', function(){
        console.log('file input changed, files:', this.files);
        if (this.files && this.files.length) processCoursesFile(this.files[0]);
    });

    processBtn.addEventListener('click', function(){
        const validData = importDataCourses.filter(r => r.valid);
        if (validData.length === 0) { alert('No valid records to import'); return; }
        const form = document.createElement('form'); form.method='POST'; form.style.display='none';
        const actionInput = document.createElement('input'); actionInput.type='hidden'; actionInput.name='action'; actionInput.value='bulk_import';
        const dataInput = document.createElement('input'); dataInput.type='hidden'; dataInput.name='import_data'; dataInput.value = JSON.stringify(validData);

        // Prefer CSRF token from DOM (if present) to avoid mismatches; fall back to server token
        let csrfValue = '';
        try {
            const domTokenInput = document.querySelector('input[name="csrf_token"]');
            if (domTokenInput && domTokenInput.value) {
                csrfValue = domTokenInput.value;
            } else {
                csrfValue = '<?php echo isset($_SESSION["csrf_token"]) ? $_SESSION["csrf_token"] : ""; ?>';
            }
        } catch (err) {
            console.warn('Error reading CSRF token from DOM, falling back to server token', err);
            csrfValue = '<?php echo isset($_SESSION["csrf_token"]) ? $_SESSION["csrf_token"] : ""; ?>';
        }

        console.log('Import submit - csrf token present:', !!csrfValue, 'length:', csrfValue ? csrfValue.length : 0);
        const csrfInput = document.createElement('input'); csrfInput.type='hidden'; csrfInput.name='csrf_token'; csrfInput.value = csrfValue;
        form.appendChild(actionInput); form.appendChild(dataInput); form.appendChild(csrfInput); document.body.appendChild(form);
        try { form.submit(); } catch (err) { console.error('Form submit failed', err); }
    });
});
</script>
