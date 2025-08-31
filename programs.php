<?php
$pageTitle = 'Programs Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Handle bulk import and form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    // Bulk import removed

    // Single add
    if ($action === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $code = $conn->real_escape_string($_POST['code'] ?? '');
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0; // default inactive unless checked

        // Verify department exists before inserting
        $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ?");
        if ($dept_check) {
            $dept_check->bind_param("i", $department_id);
            $dept_check->execute();
            $dept_res = $dept_check->get_result();
            if (!$dept_res || $dept_res->num_rows === 0) {
                $error_message = "Selected department does not exist.";
                $dept_check->close();
            }
            $dept_check->close();
        }

        // Check if program with same name and department exists
        $check_sql = "SELECT id FROM programs WHERE name = ? AND department_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $name, $department_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "Program with this name already exists in the selected department.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $sql = "INSERT INTO programs (name, department_id, code, duration_years, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sisii", $name, $department_id, $code, $duration, $is_active);
                if ($stmt->execute()) {
                    $stmt->close();
                    redirect_with_flash('programs.php', 'success', 'Program added successfully!');
                } else {
                    $error_message = "Error adding program: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        }

    // Edit
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $code = $conn->real_escape_string($_POST['code'] ?? '');
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Verify department exists before updating
        $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ?");
        if ($dept_check) {
            $dept_check->bind_param("i", $department_id);
            $dept_check->execute();
            $dept_res = $dept_check->get_result();
            if (!$dept_res || $dept_res->num_rows === 0) {
                $error_message = "Selected department does not exist.";
                $dept_check->close();
            }
            $dept_check->close();
        }

        // Check duplicates for other records
        $check_sql = "SELECT id FROM programs WHERE name = ? AND department_id = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sii", $name, $department_id, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "Another program with this name exists in the selected department.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $sql = "UPDATE programs SET name = ?, department_id = ?, code = ?, duration_years = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssisii", $name, $department_id, $code, $duration, $is_active, $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    redirect_with_flash('programs.php', 'success', 'Program updated successfully!');
                } else {
                    $error_message = "Error updating program: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        }

    // Delete (soft delete: set is_active = 0)
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE programs SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('programs.php', 'success', 'Program deleted.');
        } else {
            $error_message = "Error deleting program: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch programs with department names
$sql = "SELECT p.*, d.name as department_name 
        FROM programs p 
        LEFT JOIN departments d ON p.department_id = d.id 
        WHERE p.is_active = 1 
        ORDER BY p.name";
$result = $conn->query($sql);

// Fetch departments for dropdown
$dept_sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-graduation-cap me-2"></i>Programs Management</h4>
            <div class="d-flex gap-2">
                <!-- Import removed -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="fas fa-plus me-2"></i>Add New Program
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
            <input type="text" class="search-input" placeholder="Search programs...">
        </div>

        <div class="table-responsive">
            <table class="table" id="programsTable">
                <thead>
                    <tr>
                        <th>Program Name</th>
                        <th>Code</th>
                        <th>Duration (yrs)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['duration_years'] ?? $row['duration'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editProgram(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', <?php echo (int)$row['department_id']; ?>, <?php echo $row['is_active']; ?>, '<?php echo htmlspecialchars(addslashes($row['code'] ?? '')); ?>', <?php echo (int)($row['duration_years'] ?? $row['duration'] ?? 0); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this program?')">
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
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-graduation-cap"></i>
                                <p>No programs found. Add your first program to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Program Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Program Code *</label>
                        <input type="text" class="form-control" id="code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department *</label>
                        <?php if ($dept_result && $dept_result->num_rows > 0): ?>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
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
                    <div id="addFormAlert"></div>
                    
                    <div class="mb-3">
                        <label for="duration" class="form-label">Duration (Years) *</label>
                        <select class="form-select" id="duration" name="duration" required>
                            <option value="">Select Duration</option>
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Program Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_code" class="form-label">Program Code *</label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label">Department *</label>
                        <?php
                            // Re-fetch departments for the edit modal options
                            $dept_list = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
                        ?>
                        <?php if ($dept_list && $dept_list->num_rows > 0): ?>
                        <select class="form-select" id="edit_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php while ($d = $dept_list->fetch_assoc()): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            No departments found. <a href="department.php" class="btn btn-sm btn-primary ms-2">Create Department</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="editFormAlert"></div>
                    
                    <div class="mb-3">
                        <label for="edit_duration" class="form-label">Duration (Years) *</label>
                        <select class="form-select" id="edit_duration" name="duration" required>
                            <option value="">Select Duration</option>
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import removed -->

<?php 
// Embed existing program data for client-side duplicate checks
$existing_name_dept = [];
$existing_codes = [];
$codes_res = $conn->query("SELECT name, department_id, code FROM programs WHERE is_active = 1");
if ($codes_res) {
    while ($r = $codes_res->fetch_assoc()) {
        $existing_name_dept[] = ['name' => $r['name'], 'dept' => (int)$r['department_id']];
        $existing_codes[] = $r['code'];
    }
}
?>
<script>
var existingProgramNameDept = <?php echo json_encode($existing_name_dept); ?> || [];
var existingProgramCodes = <?php echo json_encode($existing_codes); ?> || [];
var existingProgramNameDeptSet = {};
var existingProgramCodesSet = {};
existingProgramNameDept.forEach(function(item){ if (item.name && item.dept) existingProgramNameDeptSet[(item.name.trim().toUpperCase() + '|' + item.dept)] = true; });
existingProgramCodes.forEach(function(code){ if (code) existingProgramCodesSet[code.trim().toUpperCase()] = true; });
</script>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
function editProgram(id, name, departmentId, isActive, code, duration) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_department_id').value = departmentId;
    document.getElementById('edit_code').value = code || '';
    document.getElementById('edit_duration').value = duration || '';
    // Set is_active checkbox if present
    var checkbox = document.querySelector('#editProgramModal input[name="is_active"]');
    if (checkbox) checkbox.checked = !!isActive;

    var el = document.getElementById('editProgramModal');
    if (!el) return console.error('editProgramModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el).show();
}
</script>

<script>
// Client-side validation and server error display handling
document.addEventListener('DOMContentLoaded', function(){
    var addForm = document.querySelector('#addProgramModal form');
    var editForm = document.querySelector('#editProgramModal form');

    if (addForm) {
        addForm.addEventListener('submit', function(e){
            var dept = document.getElementById('department_id');
            var alertEl = document.getElementById('addFormAlert');
            if (dept && dept.value === '') {
                e.preventDefault();
                if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">Please select a department.</div>';
                return false;
            }
        });
    }

    if (editForm) {
        editForm.addEventListener('submit', function(e){
            var dept = document.getElementById('edit_department_id');
            var alertEl = document.getElementById('editFormAlert');
            if (dept && dept.value === '') {
                e.preventDefault();
                if (alertEl) alertEl.innerHTML = '<div class="alert alert-danger">Please select a department.</div>';
                return false;
            }
        });
    }

    // If server returned an error message, show it inside the modal
    <?php if (isset($error_message) && !empty($error_message)): ?>
        (function(){
            var em = <?php echo json_encode($error_message); ?>;
            // Try to reopen add or edit modal based on posted action
            var action = <?php echo json_encode($_POST['action'] ?? ''); ?>;
            if (action === 'add') {
                var addAlert = document.getElementById('addFormAlert');
                if (addAlert) addAlert.innerHTML = '<div class="alert alert-danger">'+em+'</div>';
                var el = document.getElementById('addProgramModal'); if (el && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(el).show();
            } else if (action === 'edit') {
                var editAlert = document.getElementById('editFormAlert');
                if (editAlert) editAlert.innerHTML = '<div class="alert alert-danger">'+em+'</div>';
                var el = document.getElementById('editProgramModal'); if (el && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(el).show();
            }
        })();
    <?php endif; ?>
});
</script>
// Import functionality removed - clear variables and functions
let importDataPrograms = [];

function parseCSVPrograms(csvText) {
    // Normalize newlines and trim
    csvText = csvText.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    const lines = csvText.split('\n').filter(l => l.trim());
    if (lines.length === 0) return [];

    // Robust CSV line parser that honors quoted fields and escaped quotes
    function parseCSVLine(line) {
        const result = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (inQuotes) {
                if (ch === '"') {
                    if (line[i + 1] === '"') { // escaped quote
                        cur += '"';
                        i++; // skip next
                    } else {
                        inQuotes = false;
                    }
                } else {
                    cur += ch;
                }
            } else {
                if (ch === '"') {
                    inQuotes = true;
                } else if (ch === ',') {
                    result.push(cur);
                    cur = '';
                } else {
                    cur += ch;
                }
            }
        }
        result.push(cur);
        return result.map(s => s.trim());
    }

    const headers = parseCSVLine(lines[0]).map(h => h.replace(/^"|"$/g, '').trim());
    const data = [];

    for (let i = 1; i < lines.length; i++) {
        // skip empty lines
        if (!lines[i].trim()) continue;
        const values = parseCSVLine(lines[i]);
        const row = {};
        headers.forEach((header, index) => {
            row[header] = values[index] !== undefined ? values[index] : '';
        });
        data.push(row);
    }
    return data;
}

function validateProgramsData(data) {
    return data.map(row => {
        const validated = {
            name: row.name || row.Name || '',
            department_id: row.department_id || row.departmentId || row.department_id || '',
            code: row.code || row.Code || '',
            duration: row.duration || row.duration_years || row.durationYears || '',
            is_active: (row.is_active || row.isActive || '1') === '1' ? '1' : '0'
        };
        validated.valid = true;
        validated.errors = [];
        if (!validated.name.trim()) { validated.valid = false; validated.errors.push('Name required'); }
        if (!validated.department_id.toString().trim()) { validated.valid = false; validated.errors.push('Department ID required'); }
        if (!validated.code.trim()) { validated.valid = false; validated.errors.push('Code required'); }
        if (!validated.duration.toString().trim()) { validated.valid = false; validated.errors.push('Duration required'); }

        // Check for duplicate name+department
        if (existingProgramNameDeptSet[validated.name.trim().toUpperCase() + '|' + validated.department_id]) {
            validated.valid = false;
            validated.errors.push('Program name and department combination already exists.');
        }

        // Check for duplicate code
        if (existingProgramCodesSet[validated.code.trim().toUpperCase()]) {
            validated.valid = false;
            validated.errors.push('Program code already exists.');
        }
        return validated;
    });
}

function showPreviewPrograms() {
    const tbody = document.getElementById('previewBody');
    if (!tbody) {
        // preview removed; silently return
        return;
    }
    tbody.innerHTML = '';
    const previewRows = importDataPrograms.slice(0, 10);
    let validCount = 0;
    
    console.log('Showing preview for', previewRows.length, 'rows');

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
        
        console.log('Creating row', idx, 'with data:', row);

        tr.innerHTML = `
            <td class="text-center">${idx+1}</td>
            <td>${row.name || 'N/A'}</td>
            <td class="text-center">${row.code || 'N/A'}</td>
            <td class="text-center">${row.department_id || 'N/A'}</td>
            <td class="text-center">${row.duration || 'N/A'}</td>
            <td class="text-center"><span class="badge ${row.is_active === '1' ? 'bg-success' : 'bg-secondary'}">${row.is_active === '1' ? 'Active' : 'Inactive'}</span></td>
            <td>${validationHtml}</td>
        `;
        tbody.appendChild(tr);
    });

    // Update process button to show how many valid rows will be imported
    // process button removed
}

function processProgramsFile(file) {
    // removed
}

// Set up drag/drop and file input
document.addEventListener('DOMContentLoaded', function() {
    // upload handlers removed
    
    // Import modal removed; nothing to initialize
});
</script>
