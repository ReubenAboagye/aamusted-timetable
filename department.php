<?php
// Handle bulk import BEFORE any output
include 'connect.php';
include 'includes/flash.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to log errors
function logError($message, $context = []) {
    $log_file = 'logs/department_errors.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $log_entry = "[$timestamp] $message$context_str\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'bulk_import' && isset($_POST['import_data'])) {
        $import_data = json_decode($_POST['import_data'], true);
        if ($import_data) {
            $success_count = 0;
            $error_count = 0;
            foreach ($import_data as $row) {
                if (!empty($row['valid'])) {
                    $name = $conn->real_escape_string($row['name']);
                    $code = $conn->real_escape_string($row['code']);
                    $description = isset($row['description']) ? $conn->real_escape_string($row['description']) : '';
                    $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;

                    $sql = "INSERT INTO departments (name, code, description, is_active) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $name, $code, $description, $is_active);

                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    $stmt->close();
                }
            }
            if ($success_count > 0) {
                $msg = "Successfully imported $success_count departments!";
                if ($error_count > 0) {
                    $msg .= " $error_count records failed to import.";
                }
                logError("Bulk import completed", ['success_count' => $success_count, 'error_count' => $error_count]);
                redirect_with_flash('department.php', 'success', $msg);
            } else {
                $error_message = "No departments were imported. Please check your data.";
                logError("Bulk import failed", ['error_count' => $error_count]);
            }
        }
    }
}

$pageTitle = 'Departments Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Handle form submission for adding/editing department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        // Validate and sanitize input
        $name = trim(htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'));
        $code = strtoupper(trim(htmlspecialchars($_POST['code'], ENT_QUOTES, 'UTF-8')));
        $description = trim(htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'));
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        // Additional validation
        if (empty($name) || strlen($name) > 100) {
            $error_message = "Department name is required and must be less than 100 characters.";
        } elseif (empty($code) || strlen($code) > 20) {
            $error_message = "Department code is required and must be less than 20 characters.";
        } else {

        // Check if code already exists
        $check_sql = "SELECT id FROM departments WHERE code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "Department code already exists. Please choose a different code.";
            $check_stmt->close();
        } else {
            $check_stmt->close();

            $sql = "INSERT INTO departments (name, code, description, is_active) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $code, $description, $is_active);

            if ($stmt->execute()) {
                $stmt->close();
                $msg = 'Department added successfully!';
                logError("Department added successfully", ['name' => $name, 'code' => $code, 'description' => $description, 'is_active' => $is_active]);
                redirect_with_flash('department.php', 'success', $msg);
            } else {
                $error_message = "Error adding department: " . $conn->error;
                logError("Failed to add department", ['name' => $name, 'code' => $code, 'description' => $description, 'is_active' => $is_active, 'error' => $conn->error]);
            }
            $stmt->close();
        }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        // Validate and sanitize input
        $name = trim(htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'));
        $code = strtoupper(trim(htmlspecialchars($_POST['code'], ENT_QUOTES, 'UTF-8')));
        $description = trim(htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'));
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        
        // Additional validation
        if ($id < 1) {
            $error_message = "Invalid department ID.";
        } elseif (empty($name) || strlen($name) > 100) {
            $error_message = "Department name is required and must be less than 100 characters.";
        } elseif (empty($code) || strlen($code) > 20) {
            $error_message = "Department code is required and must be less than 20 characters.";
        } else {
        
        // Check if code already exists for other departments
        $check_sql = "SELECT id FROM departments WHERE code = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $code, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Department code already exists. Please choose a different code.";
        } else {
            $sql = "UPDATE departments SET name = ?, code = ?, description = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $name, $code, $description, $is_active, $id);
            
            if ($stmt->execute()) {
                $stmt->close();
                $msg = 'Department updated successfully!';
                logError("Department updated successfully", ['id' => $id, 'name' => $name, 'code' => $code, 'description' => $description, 'is_active' => $is_active]);
                redirect_with_flash('department.php', 'success', $msg);
            } else {
                $error_message = "Error updating department: " . $conn->error;
                logError("Failed to update department", ['id' => $id, 'name' => $name, 'code' => $code, 'description' => $description, 'is_active' => $is_active, 'error' => $conn->error]);
            }
            $stmt->close();
        }
        }
        $check_stmt->close();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        // Validate ID
        if ($id < 1) {
            $error_message = "Invalid department ID.";
        } else {
            // Check if department is being used by other tables before deleting
            $check_sql = "SELECT COUNT(*) as count FROM programs WHERE department_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_row = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if ($check_row['count'] > 0) {
                $error_message = "Cannot delete department. It is being used by " . $check_row['count'] . " program(s).";
            } else {
                // Soft delete by setting is_active to 0
                $sql = "UPDATE departments SET is_active = 0 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $success_message = "Department deleted successfully!";
                    logError("Department deleted successfully", ['id' => $id]);
                } else {
                    $error_message = "Error deleting department: " . $conn->error;
                    logError("Failed to delete department", ['id' => $id, 'error' => $conn->error]);
                }
                $stmt->close();
            }
        }
    }
}

// Fetch departments
$sql = "SELECT * FROM departments WHERE is_active = 1 ORDER BY name";
$result = $conn->query($sql);

// Get streams for dropdowns (if needed for future use)
$streams_result = null;

// Get department statistics
$stats_sql = "SELECT 
    COUNT(*) as total_departments,
    (SELECT COUNT(*) FROM programs WHERE department_id IN (SELECT id FROM departments)) as total_programs
FROM departments WHERE is_active = 1";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_departments' => 0, 'total_programs' => 0];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-building me-2"></i>Departments Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-2"></i>Add New Department
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
            <div class="col-md-6">
                <div class="card theme-card bg-theme-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['total_departments']; ?></h4>
                                <p class="card-text">Total Departments</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-building fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card theme-card bg-theme-accent text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['total_programs']; ?></h4>
                                <p class="card-text">Total Programs</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-graduation-cap fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-container m-3">
            <input type="text" class="search-input" id="searchInput" placeholder="Search departments...">
        </div>

        <div class="table-responsive">
            <table class="table" id="departmentsTable">
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>Code</th>
                        <th>Description</th>
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
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['description'] ?? 'No description'); ?></small></td>
                                <td><span class="badge <?php echo $row['is_active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editDepartment(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['code'])); ?>', '<?php echo htmlspecialchars(addslashes($row['description'] ?? '')); ?>', <?php echo $row['is_active']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this department?')">
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
                                <i class="fas fa-building"></i>
                                <p>No departments found. Add your first department to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g., Computer Science">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="code" class="form-label">Department Code *</label>
                                <input type="text" class="form-control" id="code" name="code" required placeholder="e.g., CS">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="is_active" class="form-label">Status</label>
                                <select class="form-control" id="is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter department description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required placeholder="e.g., Computer Science">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_code" class="form-label">Department Code *</label>
                                <input type="text" class="form-control" id="edit_code" name="code" required placeholder="e.g., CS">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_is_active" class="form-label">Status</label>
                                <select class="form-control" id="edit_is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3" placeholder="Enter department description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
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
                <h5 class="modal-title">Import Departments</h5>
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
                        <small class="text-muted">Supported format: CSV<br>
                        Headers: name, code, description, is_active</small>
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
                                    <th>Description</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="processBtn">Process File</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Confirmation Modal -->
<div class="modal fade" id="confirmImportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Import</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to import <strong id="importCount">0</strong> departments?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone. Duplicate department codes will be skipped.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="executeImport()">Import Now</button>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
let importData = [];
let currentStep = 1;

function editDepartment(id, name, code, description, isActive) {
    // Populate the edit modal with current values
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_is_active').value = isActive;
    
    // Show the edit modal
    var el = document.getElementById('editDepartmentModal');
    if (!el) return console.error('editDepartmentModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    var editModal = bootstrap.Modal.getOrCreateInstance(el);
    editModal.show();
}

function showStep(step) {
    currentStep = step;
    document.getElementById('step1').style.display = step === 1 ? 'block' : 'none';
    document.getElementById('step2').style.display = step === 2 ? 'block' : 'none';
    document.getElementById('step3').style.display = step === 3 ? 'block' : 'none';
}

function processFile() {
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a file first.');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = parseCSV(e.target.result);

            if (data.length > 0) {
                importData = validateData(data);
                showPreview();
                // Do NOT show confirmation modal here; user must click "Process File" to confirm import
            } else {
                alert('No data found in the file.');
            }
        } catch (error) {
            alert('Error processing file: ' + error.message);
        }
    };
    
    reader.readAsText(file);
}

function parseCSV(csvText) {
    const lines = csvText.split('\n');
    const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
    const data = [];
    
    for (let i = 1; i < lines.length; i++) {
        if (lines[i].trim()) {
            const values = lines[i].split(',').map(v => v.trim().replace(/"/g, ''));
            const row = {};
            headers.forEach((header, index) => {
                row[header] = values[index] || '';
            });
            data.push(row);
        }
    }
    
    return data;
}

function validateData(data) {
    return data.map((row, index) => {
        const validated = {
            name: row.name || row.Name || '',
            code: row.code || row.Code || '',
            description: row.description || row.Description || '',
            is_active: row.is_active || row['is_active'] || row.IsActive || '1'
        };
        
        // Validation
        validated.valid = true;
        validated.errors = [];
        
        if (!validated.name.trim()) {
            validated.valid = false;
            validated.errors.push('Name is required');
        }
        
        if (!validated.code.trim()) {
            validated.valid = false;
            validated.errors.push('Code is required');
        }
        
        if (validated.code.length > 20) {
            validated.valid = false;
            validated.errors.push('Code too long (max 20 chars)');
        }
        
        if (validated.name.length > 100) {
            validated.valid = false;
            validated.errors.push('Name too long (max 100 chars)');
        }
        
        // Validate is_active is numeric
        if (isNaN(validated.is_active) || (validated.is_active !== '0' && validated.is_active !== '1')) {
            validated.is_active = '1'; // Default to active
        }
        
        return validated;
    });
}

function showPreview() {
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = '';
    
    // Show only first 10 rows in the preview to give user a quick snapshot
    const previewRows = importData.slice(0, 10);
    
    previewRows.forEach((row, index) => {
        const tr = document.createElement('tr');
        tr.className = row.valid ? '' : 'table-danger';
        
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td>${row.name}</td>
            <td>${row.code}</td>
            <td>${row.description}</td>
            <td>${row.valid ? '<span class="text-success">✓ Valid</span>' : '<span class="text-danger">✗ ' + row.errors.join(', ') + '</span>'}</td>
        `;
        
        tbody.appendChild(tr);
    });
}

function confirmImport() {
    const validCount = importData.filter(row => row.valid).length;
    if (validCount === 0) {
        alert('No valid data to import. Please fix the errors first.');
        return;
    }
    
    document.getElementById('importCount').textContent = validCount;
    var el = document.getElementById('confirmImportModal');
    if (!el) return console.error('confirmImportModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el).show();
}

function executeImport() {
    const validData = importData.filter(row => row.valid);
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'bulk_import';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
    
    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'import_data';
    dataInput.value = JSON.stringify(validData);
    
    form.appendChild(actionInput);
    form.appendChild(csrfInput);
    form.appendChild(dataInput);
    document.body.appendChild(form);
    form.submit();
}

// Clear form when modals are closed
document.addEventListener('DOMContentLoaded', function() {
    // Clear add form when modal is closed
    var addModal = document.getElementById('addDepartmentModal');
    addModal.addEventListener('hidden.bs.modal', function () {
        this.querySelector('form').reset();
    });
    
    // Clear edit form when modal is closed
    var editModal = document.getElementById('editDepartmentModal');
    editModal.addEventListener('hidden.bs.modal', function () {
        this.querySelector('form').reset();
        document.getElementById('edit_id').value = '';
    });
    
    // Reset import modal when closed
    var importModal = document.getElementById('importModal');
    importModal.addEventListener('hidden.bs.modal', function () {
        importData = [];
        document.getElementById('csvFile').value = '';
        document.getElementById('previewBody').innerHTML = '';
    });
    
    // Auto-generate department code from name
    document.getElementById('name').addEventListener('input', function() {
        const name = this.value.trim();
        const codeInput = document.getElementById('code');
        if (name && !codeInput.value) {
            // Generate code from first letters of each word
            const words = name.split(' ').filter(word => word.length > 0);
            const code = words.map(word => word.charAt(0).toUpperCase()).join('');
            if (code.length <= 20) {
                codeInput.value = code;
            }
        }
    });
    
    // Setup drag and drop functionality
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csvFile');
    
    // Click to browse
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Drag and drop events
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#007bff';
        this.style.background = '#e3f2fd';
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ccc';
        this.style.background = '#f8f9fa';
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ccc';
        this.style.background = '#f8f9fa';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            // Auto-parse and show preview only; user must click Process File to confirm import
            const reader = new FileReader();
            reader.onload = function(ev) {
                try {
                    const data = parseCSV(ev.target.result);
                    if (data.length > 0) {
                        importData = validateData(data);
                        showPreview();
                    } else {
                        alert('No data found in the file.');
                    }
                } catch (error) {
                    alert('Error processing file: ' + error.message);
                }
            };
            reader.readAsText(files[0]);
        }
    });
    
    // File input change event
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            // Auto-parse and show preview only; user must click Process File to confirm import
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = parseCSV(e.target.result);
                    if (data.length > 0) {
                        importData = validateData(data);
                        showPreview();
                    } else {
                        alert('No data found in the file.');
                    }
                } catch (error) {
                    alert('Error processing file: ' + error.message);
                }
            };
            reader.readAsText(this.files[0]);
        }
    });

    // Process button should open the confirmation modal only when clicked
    const processBtn = document.getElementById('processBtn');
    processBtn.addEventListener('click', function() {
        const validCount = importData.filter(row => row.valid).length;
        if (validCount === 0) {
            alert('No valid data to import. Please fix the errors first.');
            return;
        }
        document.getElementById('importCount').textContent = validCount;
        var el = document.getElementById('confirmImportModal');
        if (!el) return console.error('confirmImportModal element missing');
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
        bootstrap.Modal.getOrCreateInstance(el).show();
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#departmentsTable tbody tr');
            
            tableRows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const code = row.cells[1].textContent.toLowerCase();
                const description = row.cells[2].textContent.toLowerCase();
                
                if (name.includes(searchTerm) || code.includes(searchTerm) || description.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
