<?php
$pageTitle = 'Courses Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Handle form submission for adding new course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $department_id = $conn->real_escape_string($_POST['department_id']);
        $credits = $conn->real_escape_string($_POST['credits']);
        $hours_per_week = $conn->real_escape_string($_POST['hours_per_week']);
        $level = $conn->real_escape_string($_POST['level']);
        $preferred_room_type = $conn->real_escape_string($_POST['preferred_room_type']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "INSERT INTO courses (name, code, department_id, credits, hours_per_week, level, preferred_room_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiissi", $name, $code, $department_id, $credits, $hours_per_week, $level, $preferred_room_type, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Course added successfully!";
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
                $name = isset($row['name']) ? $conn->real_escape_string($row['name']) : '';
                $code = isset($row['code']) ? $conn->real_escape_string($row['code']) : '';
                $department_id = isset($row['department_id']) ? (int)$row['department_id'] : 0;
                $credits = isset($row['credits']) ? (int)$row['credits'] : 3;
                $hours_per_week = isset($row['hours_per_week']) ? (int)$row['hours_per_week'] : 3;
                $level = isset($row['level']) ? (int)$row['level'] : 100;
                $preferred_room_type = isset($row['preferred_room_type']) ? $conn->real_escape_string($row['preferred_room_type']) : 'classroom';
                $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;

                if ($name === '' || $code === '' || $department_id === 0) {
                    $error_count++;
                    continue;
                }

                // Skip if course with same code exists
                if ($check_stmt) {
                    $check_stmt->bind_param("s", $code);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result();
                    if ($existing && $existing->num_rows > 0) {
                        $ignored_count++;
                        continue;
                    }
                }

                $sql = "INSERT INTO courses (name, code, department_id, credits, hours_per_week, level, preferred_room_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) { $error_count++; continue; }
                $stmt->bind_param("sssiissi", $name, $code, $department_id, $credits, $hours_per_week, $level, $preferred_room_type, $is_active);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }

            if ($check_stmt) $check_stmt->close();

            if ($success_count > 0) {
                $success_message = "Successfully imported $success_count courses!";
                if ($ignored_count > 0) $success_message .= " $ignored_count duplicates ignored.";
                if ($error_count > 0) $success_message .= " $error_count records failed to import.";
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
            $success_message = "Course deleted successfully!";
        } else {
            $error_message = "Error deleting course: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch courses with department names
$sql = "SELECT c.*, d.name as department_name 
        FROM courses c 
        LEFT JOIN departments d ON c.department_id = d.id 
        WHERE c.is_active = 1 
        ORDER BY c.name";
$result = $conn->query($sql);

// Fetch departments for dropdown
$dept_sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);
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
                        <th>Credits</th>
                        <th>Hours/Week</th>
                        <th>Level</th>
                        <th>Room Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($row['code']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($row['credits']); ?> credits</span></td>
                                <td><span class="badge bg-warning"><?php echo htmlspecialchars($row['hours_per_week']); ?> hrs/week</span></td>
                                <td><span class="badge bg-secondary">Level <?php echo htmlspecialchars($row['level']); ?></span></td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['preferred_room_type']))); ?></span></td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editCourse(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this course?')">
                                        <input type="hidden" name="action" value="delete">
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
                            <td colspan="9" class="empty-state">
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
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Course Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="code" class="form-label">Course Code *</label>
                                <input type="text" class="form-control" id="code" name="code" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department *</label>
                                <select class="form-select" id="department_id" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php while ($dept = $dept_result->fetch_assoc()): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="level" class="form-label">Level *</label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <option value="100">100 Level</option>
                                    <option value="200">200 Level</option>
                                    <option value="300">300 Level</option>
                                    <option value="400">400 Level</option>
                                    <option value="500">500 Level</option>
                                    <option value="600">600 Level</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="credits" class="form-label">Credits *</label>
                                <select class="form-select" id="credits" name="credits" required>
                                    <option value="">Select Credits</option>
                                    <option value="1">1 Credit</option>
                                    <option value="2">2 Credits</option>
                                    <option value="3">3 Credits</option>
                                    <option value="4">4 Credits</option>
                                    <option value="6">6 Credits</option>
                                </select>
                            </div>
                        </div>
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
                        <label for="preferred_room_type" class="form-label">Preferred Room Type *</label>
                        <select class="form-select" id="preferred_room_type" name="preferred_room_type" required>
                            <option value="">Select Room Type</option>
                            <option value="classroom">Classroom</option>
                            <option value="lecture_hall">Lecture Hall</option>
                            <option value="laboratory">Laboratory</option>
                            <option value="computer_lab">Computer Lab</option>
                            <option value="seminar_room">Seminar Room</option>
                            <option value="auditorium">Auditorium</option>
                        </select>
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
                        <small class="text-muted">Supported format: CSV (headers: name,code,department_id,credits,hours_per_week,level,preferred_room_type,is_active)</small>
                    </div>
                    <input type="file" class="form-control d-none" id="csvFile" accept=",.csv">
                </div>

                <div class="mb-3">
                    <h6>Preview (first 10 rows)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm" id="previewTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Department ID</th>
                                    <th>Credits</th>
                                    <th>Hours/Week</th>
                                    <th>Level</th>
                                    <th>Room Type</th>
                                    <th>Status</th>
                                    <th>Validation</th>
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
// Embed existing course codes for client-side duplicate checks
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
    const lines = csvText.split('\n').filter(l => l.trim());
    const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
    const data = [];

    for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',').map(v => v.trim().replace(/"/g, ''));
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
            name: row.name || row.Name || '',
            code: row.code || row.Code || '',
            department_id: row.department_id || row.departmentId || row.department_id || '',
            credits: row.credits || row.Credits || '3',
            hours_per_week: row.hours_per_week || row.hoursPerWeek || row.hours_per_week || '3',
            level: row.level || row.Level || '100',
            preferred_room_type: row.preferred_room_type || row.preferredRoomType || row.preferred_room_type || 'classroom',
            is_active: (row.is_active || row.isActive || '1') === '1' ? '1' : '0'
        };
        validated.valid = true;
        validated.errors = [];
        if (!validated.name.trim()) { validated.valid = false; validated.errors.push('Name required'); }
        if (!validated.code.trim()) { validated.valid = false; validated.errors.push('Code required'); }
        if (!validated.department_id.toString().trim()) { validated.valid = false; validated.errors.push('Department ID required'); }

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
            <td>${idx+1}</td>
            <td>${row.name}</td>
            <td>${row.code}</td>
            <td>${row.department_id}</td>
            <td>${row.credits}</td>
            <td>${row.hours_per_week}</td>
            <td>${row.level}</td>
            <td>${row.preferred_room_type}</td>
            <td><span class="badge ${row.is_active === '1' ? 'bg-success' : 'bg-secondary'}">${row.is_active === '1' ? 'Active' : 'Inactive'}</span></td>
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
    uploadArea.addEventListener('drop', function(e){ e.preventDefault(); this.style.borderColor='#ccc'; this.style.background='#f8f9fa'; const files = e.dataTransfer.files; if (files.length) { fileInput.files = files; processCoursesFile(files[0]); } });
    fileInput.addEventListener('change', function(){ if (this.files.length) processCoursesFile(this.files[0]); });

    processBtn.addEventListener('click', function(){
        const validData = importDataCourses.filter(r => r.valid);
        if (validData.length === 0) { alert('No valid records to import'); return; }
        const form = document.createElement('form'); form.method='POST'; form.style.display='none';
        const actionInput = document.createElement('input'); actionInput.type='hidden'; actionInput.name='action'; actionInput.value='bulk_import';
        const dataInput = document.createElement('input'); dataInput.type='hidden'; dataInput.name='import_data'; dataInput.value = JSON.stringify(validData);
        form.appendChild(actionInput); form.appendChild(dataInput); document.body.appendChild(form); form.submit();
    });
});
</script>
