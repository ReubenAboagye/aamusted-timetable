<?php
$pageTitle = 'Programs Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Handle bulk import and form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Bulk import
    if ($_POST['action'] === 'bulk_import' && isset($_POST['import_data'])) {
        $import_data = json_decode($_POST['import_data'], true);
        if ($import_data) {
            $success_count = 0;
            $ignored_count = 0;
            $error_count = 0;

            // Prepare check statements to detect existing programs
            $check_name_dept_sql = "SELECT id FROM programs WHERE name = ? AND department_id = ?";
            $check_code_sql = "SELECT id FROM programs WHERE code = ?";
            $check_name_dept_stmt = $conn->prepare($check_name_dept_sql);
            $check_code_stmt = $conn->prepare($check_code_sql);

            foreach ($import_data as $row) {
                $name = isset($row['name']) ? $conn->real_escape_string($row['name']) : '';
                $department_id = isset($row['department_id']) ? (int)$row['department_id'] : 0;
                $code = isset($row['code']) ? $conn->real_escape_string($row['code']) : '';
                $duration = isset($row['duration']) ? (int)$row['duration'] : 0;
                $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;

                if ($name === '' || $department_id === 0) {
                    $error_count++;
                    continue;
                }

                // Generate a code if not provided
                if (empty($code)) {
                    $base_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 6));
                    // Ensure uniqueness by adding department prefix if needed
                    if (strlen($base_code) < 3) {
                        $base_code = 'PRG' . $base_code;
                    }
                    
                    $code = $base_code;
                    $counter = 1;
                    
                    // Check if code exists and generate a unique one
                    while (true) {
                        $check_code_stmt->bind_param("s", $code);
                        $check_code_stmt->execute();
                        $existing_code = $check_code_stmt->get_result();
                        if ($existing_code && $existing_code->num_rows > 0) {
                            $code = $base_code . $counter;
                            $counter++;
                            // Prevent infinite loop
                            if ($counter > 999) {
                                $code = $base_code . time() % 1000;
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                }

                // Skip if program with same name and department exists
                if ($check_name_dept_stmt) {
                    $check_name_dept_stmt->bind_param("si", $name, $department_id);
                    $check_name_dept_stmt->execute();
                    $existing_name_dept = $check_name_dept_stmt->get_result();
                    if ($existing_name_dept && $existing_name_dept->num_rows > 0) {
                        $ignored_count++;
                        continue;
                    }
                }

                // Skip if program with same code exists
                if ($check_code_stmt) {
                    $check_code_stmt->bind_param("s", $code);
                    $check_code_stmt->execute();
                    $existing_code = $check_code_stmt->get_result();
                    if ($existing_code && $existing_code->num_rows > 0) {
                        $ignored_count++;
                        continue;
                    }
                }

                $sql = "INSERT INTO programs (name, department_id, code, duration_years, is_active) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) { $error_count++; continue; }
                $stmt->bind_param("sisii", $name, $department_id, $code, $duration, $is_active);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }

            if ($check_name_dept_stmt) $check_name_dept_stmt->close();
            if ($check_code_stmt) $check_code_stmt->close();

            if ($success_count > 0) {
                $success_message = "Successfully imported $success_count programs!";
                if ($ignored_count > 0) $success_message .= " $ignored_count duplicates ignored.";
                if ($error_count > 0) $success_message .= " $error_count records failed to import.";
            } else {
                if ($ignored_count > 0 && $error_count === 0) {
                    $success_message = "No new programs imported. $ignored_count duplicates ignored.";
                } else {
                    $error_message = "No programs were imported. Please check your data.";
                }
            }
        }

    // Single add
    } elseif ($_POST['action'] === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $code = $conn->real_escape_string($_POST['code'] ?? '');
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0; // default inactive unless checked

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
                    $success_message = "Program added successfully!";
                } else {
                    $error_message = "Error adding program: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        }

    // Edit
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $code = $conn->real_escape_string($_POST['code'] ?? '');
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

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
                    $success_message = "Program updated successfully!";
                } else {
                    $error_message = "Error updating program: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing statement: " . $conn->error;
            }
        }

    // Delete (soft delete: set is_active = 0)
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE programs SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Program deleted.";
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
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
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
                        <th>Department</th>
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
                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
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
                            <td colspan="3" class="empty-state">
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
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php while ($dept = $dept_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
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
                        <select class="form-select" id="edit_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php
                                // Re-fetch departments for the edit modal options
                                $dept_list = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
                                while ($d = $dept_list->fetch_assoc()):
                            ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
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

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Programs</h5>
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
                        <small class="text-muted">Supported format: CSV (headers: name,department_id,code,duration,is_active)</small>
                    </div>
                    <input type="file" class="form-control d-none" id="csvFile" accept=",.csv">
                </div>

                <div class="mb-3">
                    <h6>Preview (first 10 rows)</h6>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-bordered" id="previewTable" style="min-width: 100%; margin-bottom: 0;">
                            <thead class="table-light sticky-top" style="background-color: #f8f9fa;">
                                <tr>
                                    <th style="width: 5%; min-width: 40px;">#</th>
                                    <th style="width: 20%; min-width: 120px;">Name</th>
                                    <th style="width: 15%; min-width: 80px;">Code</th>
                                    <th style="width: 15%; min-width: 80px;">Dept ID</th>
                                    <th style="width: 10%; min-width: 80px;">Duration</th>
                                    <th style="width: 10%; min-width: 80px;">Status</th>
                                    <th style="width: 25%; min-width: 150px;">Validation</th>
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

    var editModal = new bootstrap.Modal(document.getElementById('editProgramModal'));
    editModal.show();
}
</script>

<script>
let importDataPrograms = [];

function parseCSVPrograms(csvText) {
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
        console.error('Preview table body not found');
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

function processProgramsFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = parseCSVPrograms(e.target.result);
        importDataPrograms = validateProgramsData(data);
        showPreviewPrograms();
        // process button state is updated inside showPreviewPrograms
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
    uploadArea.addEventListener('drop', function(e){ e.preventDefault(); this.style.borderColor='#ccc'; this.style.background='#f8f9fa'; const files = e.dataTransfer.files; if (files.length) { fileInput.files = files; processProgramsFile(files[0]); } });
    fileInput.addEventListener('change', function(){ if (this.files.length) processProgramsFile(this.files[0]); });

    processBtn.addEventListener('click', function(){
        const validData = importDataPrograms.filter(r => r.valid);
        if (validData.length === 0) { alert('No valid records to import'); return; }
        
        // Ensure all required fields are present
        const processedData = validData.map(row => ({
            name: row.name,
            department_id: row.department_id,
            code: row.code,
            duration: row.duration,
            is_active: row.is_active
        }));
        
        const form = document.createElement('form'); form.method='POST'; form.style.display='none';
        const actionInput = document.createElement('input'); actionInput.type='hidden'; actionInput.name='action'; actionInput.value='bulk_import';
        const dataInput = document.createElement('input'); dataInput.type='hidden'; dataInput.name='import_data'; dataInput.value = JSON.stringify(processedData);
        form.appendChild(actionInput); form.appendChild(dataInput); document.body.appendChild(form); form.submit();
    });
    
    // Ensure table is properly initialized when modal is shown
    const importModal = document.getElementById('importModal');
    if (importModal) {
        importModal.addEventListener('shown.bs.modal', function() {
            console.log('Import modal shown, initializing table');
            // Clear any existing data
            importDataPrograms = [];
            const tbody = document.getElementById('previewBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Upload a CSV file to see preview</td></tr>';
            }
        });
    }
});
</script>
