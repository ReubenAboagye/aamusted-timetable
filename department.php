<?php
// Handle bulk import BEFORE any output
include 'connect.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_import') {
    if (isset($_POST['import_data'])) {
        $import_data = json_decode($_POST['import_data'], true);
        if ($import_data) {
            $success_count = 0;
            $error_count = 0;
            foreach ($import_data as $row) {
                if (!empty($row['valid'])) {
                    $name = $conn->real_escape_string($row['name']);
                    $code = $conn->real_escape_string($row['code']);
                    $short_name = $conn->real_escape_string($row['short_name']);
                    $head_of_department = $conn->real_escape_string($row['head_of_department']);
                    $is_active = $row['is_active'];

                    $sql = "INSERT INTO departments (name, code, short_name, head_of_department, is_active) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $name, $code, $short_name, $head_of_department, $is_active);

                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    $stmt->close();
                }
            }
            if ($success_count > 0) {
                $success_message = "Successfully imported $success_count departments!";
                if ($error_count > 0) {
                    $success_message .= " $error_count records failed to import.";
                }
            } else {
                $error_message = "No departments were imported. Please check your data.";
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
        $name = trim($_POST['name']);
        $code = strtoupper(trim($_POST['code']));
        $short_name = trim($_POST['short_name']);
        $head_of_department = trim($_POST['head_of_department']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

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

            $sql = "INSERT INTO departments (name, code, short_name, head_of_department, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $name, $code, $short_name, $head_of_department, $is_active);

            if ($stmt->execute()) {
                $success_message = "Department added successfully!";
            } else {
                $error_message = "Error adding department: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $short_name = $conn->real_escape_string($_POST['short_name']);
        $head_of_department = $conn->real_escape_string($_POST['head_of_department']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check if code already exists for other departments
        $check_sql = "SELECT id FROM departments WHERE code = ? AND id != ? AND is_active = 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $code, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Department code already exists. Please choose a different code.";
        } else {
            $sql = "UPDATE departments SET name = ?, code = ?, short_name = ?, head_of_department = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssii", $name, $code, $short_name, $head_of_department, $is_active, $id);
            
            if ($stmt->execute()) {
                $success_message = "Department updated successfully!";
            } else {
                $error_message = "Error updating department: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE departments SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success_message = "Department deleted successfully!";
        } else {
            $error_message = "Error deleting department: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch departments
$sql = "SELECT * FROM departments WHERE is_active = 1 ORDER BY name";
$result = $conn->query($sql);
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

        <div class="search-container m-3">
            <input type="text" class="search-input" placeholder="Search departments...">
        </div>

        <div class="table-responsive">
            <table class="table" id="departmentsTable">
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>Code</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($row['code']); ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editDepartment(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['code'])); ?>', '<?php echo htmlspecialchars(addslashes($row['short_name'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($row['head_of_department'] ?? '')); ?>', <?php echo $row['is_active']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this department?')">
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
                            <td colspan="3" class="empty-state">
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
                                <label for="short_name" class="form-label">Short Name</label>
                                <input type="text" class="form-control" id="short_name" name="short_name" placeholder="e.g., CompSci" maxlength="10">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="head_of_department" class="form-label">Head of Department</label>
                        <input type="text" class="form-control" id="head_of_department" name="head_of_department" placeholder="e.g., Dr. John Smith">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active Department
                            </label>
                        </div>
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
                                <label for="edit_short_name" class="form-label">Short Name</label>
                                <input type="text" class="form-control" id="edit_short_name" name="short_name" placeholder="e.g., CompSci" maxlength="10">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_head_of_department" class="form-label">Head of Department</label>
                        <input type="text" class="form-control" id="edit_head_of_department" name="head_of_department" placeholder="e.g., Dr. John Smith">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active Department
                            </label>
                        </div>
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
                        <small class="text-muted">Supported format: CSV</small>
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
                                    <th>Short Name</th>
                                    <th>Head of Department</th>
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

function editDepartment(id, name, code, shortName, headOfDepartment, isActive) {
    // Populate the edit modal with current values
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_short_name').value = shortName;
    document.getElementById('edit_head_of_department').value = headOfDepartment;
    document.getElementById('edit_is_active').checked = isActive == 1;
    
    // Show the edit modal
    var editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
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
                // Show confirmation modal directly
                var confirmModal = new bootstrap.Modal(document.getElementById('confirmImportModal'));
                confirmModal.show();
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
            short_name: row.short_name || row['short_name'] || row.ShortName || '',
            head_of_department: row.head_of_department || row['head_of_department'] || row.HeadOfDepartment || '',
            is_active: (row.is_active || row['is_active'] || row.IsActive || '1') === '1' ? '1' : '0'
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
        
        if (validated.short_name.length > 10) {
            validated.valid = false;
            validated.errors.push('Short name too long');
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
            <td>${row.short_name}</td>
            <td>${row.head_of_department}</td>
            <td><span class="badge ${row.is_active === '1' ? 'bg-success' : 'bg-secondary'}">${row.is_active === '1' ? 'Active' : 'Inactive'}</span></td>
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
    var confirmModal = new bootstrap.Modal(document.getElementById('confirmImportModal'));
    confirmModal.show();
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
    
    const dataInput = document.createElement('input');
    dataInput.type = 'hidden';
    dataInput.name = 'import_data';
    dataInput.value = JSON.stringify(validData);
    
    form.appendChild(actionInput);
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
            // Auto-process the file
            processFile();
        }
    });
    
    // File input change event
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            // Auto-process the file
            processFile();
        }
    });
});
</script>
