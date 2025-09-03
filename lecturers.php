<?php
$pageTitle = 'Lecturers Management';

// Database connection and stream manager must be loaded before any output
include 'connect.php';
include 'includes/stream_manager.php';
$streamManager = getStreamManager();

include 'includes/header.php';
include 'includes/sidebar.php';

// Handle form submission for adding/editing/deleting lecturer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

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

        if (empty($error_message)) {
            // Lecturers are global; do not assign stream_id
            $sql = "INSERT INTO lecturers (name, department_id, is_active) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $name, $department_id, $is_active);

            if ($stmt->execute()) {
                $stmt->close();
                redirect_with_flash('lecturers.php', 'success', 'Lecturer added successfully!');
            } else {
                $error_message = "Error adding lecturer: " . $conn->error;
            }
            $stmt->close();
        }

    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
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

        if (empty($error_message)) {
            $sql = "UPDATE lecturers SET name = ?, department_id = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siii", $name, $department_id, $is_active, $id);

            if ($stmt->execute()) {
                $stmt->close();
                redirect_with_flash('lecturers.php', 'success', 'Lecturer updated successfully!');
            } else {
                $error_message = "Error updating lecturer: " . $conn->error;
            }
            $stmt->close();
        }

    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE lecturers SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_message = "Lecturer deleted successfully!";
        } else {
            $error_message = "Error deleting lecturer: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle bulk import and form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Bulk import
    if ($_POST['action'] === 'bulk_import' && isset($_POST['import_data'])) {
        $import_data = json_decode($_POST['import_data'], true);
        if ($import_data) {
            $success_count = 0;
            $ignored_count = 0;
            $error_count = 0;

            // Prepare a check statement to detect existing (name + department_id)
            $check_sql = "SELECT id FROM lecturers WHERE name = ? AND department_id = ?";
            $check_stmt = $conn->prepare($check_sql);

            foreach ($import_data as $row) {
                $name = isset($row['name']) ? $conn->real_escape_string($row['name']) : '';
                $department_id = isset($row['department_id']) ? (int)$row['department_id'] : 0;
                $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;

                if ($name === '' || $department_id === 0) {
                    $error_count++;
                    continue;
                }

                // Verify department exists before inserting
                $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ?");
                if ($dept_check) {
                    $dept_check->bind_param("i", $department_id);
                    $dept_check->execute();
                    $dept_res = $dept_check->get_result();
                    if (!$dept_res || $dept_res->num_rows === 0) {
                        $error_count++;
                        $dept_check->close();
                        continue;
                    }
                    $dept_check->close();
                }

                // Skip if lecturer with same name and department exists
                if ($check_stmt) {
                    $check_stmt->bind_param("si", $name, $department_id);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result();
                    if ($existing && $existing->num_rows > 0) {
                        $ignored_count++;
                        continue;
                    }
                }

                $sql = "INSERT INTO lecturers (name, department_id, is_active) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) { $error_count++; continue; }
                $stmt->bind_param("sii", $name, $department_id, $is_active);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }

            if ($check_stmt) $check_stmt->close();

            if ($success_count > 0) {
                $success_message = "Successfully imported $success_count lecturers!";
                if ($ignored_count > 0) $success_message .= " $ignored_count duplicates ignored.";
                if ($error_count > 0) $success_message .= " $error_count records failed to import.";
            } else {
                if ($ignored_count > 0 && $error_count === 0) {
                    $success_message = "No new lecturers imported. $ignored_count duplicates ignored.";
                } else {
                    $error_message = "No lecturers were imported. Please check your data.";
                }
            }
        }

    // Single add
    } elseif ($_POST['action'] === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $sql = "INSERT INTO lecturers (name, department_id, is_active) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $name, $department_id, $is_active);

        if ($stmt->execute()) {
            $success_message = "Lecturer added successfully!";
        } else {
            $error_message = "Error adding lecturer: " . $conn->error;
        }
        $stmt->close();

    // Edit
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $sql = "UPDATE lecturers SET name = ?, department_id = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siii", $name, $department_id, $is_active, $id);

        if ($stmt->execute()) {
            $success_message = "Lecturer updated successfully!";
        } else {
            $error_message = "Error updating lecturer: " . $conn->error;
        }
        $stmt->close();

    // Delete (soft delete: set is_active = 0)
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE lecturers SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('lecturers.php', 'success', 'Lecturer deleted.');
        } else {
            $error_message = "Error deleting lecturer: " . $conn->error;
        }
        $stmt->close();
    }
}

// Include stream manager
include 'includes/stream_manager.php';
$streamManager = getStreamManager();

// Fetch lecturers with department names
$sql = "SELECT l.id, l.name, l.department_id, d.name as department_name, l.is_active
        FROM lecturers l 
        LEFT JOIN departments d ON l.department_id = d.id 
        WHERE l.is_active = 1
        ORDER BY l.name";
$result = $conn->query($sql);

// Fetch departments for dropdown (filtered by stream if supported)
$dept_stream_filter = '';
$dept_sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers Management</h4>
            <div>
                <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#importLecturerModal">
                    <i class="fas fa-file-import me-2"></i>Import CSV
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLecturerModal">
                    <i class="fas fa-plus me-2"></i>Add New Lecturer
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
            <input type="text" class="search-input" placeholder="Search lecturers...">
        </div>

        <div class="table-responsive">
            <table class="table" id="lecturersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editLecturer(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', <?php echo (int)$row['department_id']; ?>, <?php echo $row['is_active']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this lecturer?')">
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
                                <i class="fas fa-chalkboard-teacher"></i>
                                <p>No lecturers found. Add your first lecturer to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Lecturer Modal -->
<div class="modal fade" id="addLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
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
                    
                    <!-- email, phone, rank fields removed to match DB schema -->
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active Lecturer
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Lecturer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Lecturer Modal -->
<div class="modal fade" id="editLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
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
                    
                    <!-- email, phone, rank fields removed to match DB schema -->
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active Lecturer
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Lecturer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Embed existing lecturer name+department for client-side duplicate checks
// Lecturers are global; do not filter by stream
$existing_name_dept = [];
$codes_res = $conn->query("SELECT name, department_id FROM lecturers WHERE is_active = 1");
if ($codes_res) {
    while ($r = $codes_res->fetch_assoc()) {
        $existing_name_dept[] = ['name' => $r['name'], 'dept' => (int)$r['department_id']];
    }
}
?>
<script>
var existingLecturerNameDept = <?php echo json_encode($existing_name_dept); ?> || [];
var existingLecturerNameDeptSet = {};
existingLecturerNameDept.forEach(function(item){ if (item.name && item.dept) existingLecturerNameDeptSet[(item.name.trim().toUpperCase() + '|' + item.dept)] = true; });
</script>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<!-- Import Modal -->
<div class="modal fade" id="importLecturerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Lecturers</h5>
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
                        <small class="text-muted">Supported format: CSV (headers: name,department_id,is_active)</small>
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
                                    <th>Department ID</th>
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

<script>
function editLecturer(id, name, departmentId, isActive) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_department_id').value = departmentId;
    document.getElementById('edit_is_active').checked = isActive == 1;

    var el = document.getElementById('editLecturerModal');
    if (!el) return console.error('editLecturerModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el).show();
}

// Auto-show preview modal if preview data exists
<?php if (!empty($import_preview)): ?>
var el2 = document.getElementById('importPreviewModal');
if (el2) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el2).show();
}
<?php endif; ?>

// Import functionality
let importDataLecturers = [];

function parseCSVLecturers(csvText) {
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

function validateLecturersData(data) {
    return data.map(row => {
        const validated = {
            name: row.name || row.Name || '',
            department_id: row.department_id || row.departmentId || row.department_id || '',
            is_active: (row.is_active || row.isActive || '1') === '1' ? '1' : '0'
        };
        validated.valid = true;
        validated.errors = [];
        if (!validated.name.trim()) { validated.valid = false; validated.errors.push('Name required'); }
        if (!validated.department_id.toString().trim()) { validated.valid = false; validated.errors.push('Department ID required'); }

        // Check for duplicate name+department
        if (existingLecturerNameDeptSet[validated.name.trim().toUpperCase() + '|' + validated.department_id]) {
            validated.valid = false;
            validated.errors.push('Lecturer name and department combination already exists.');
        }
        return validated;
    });
}

function showPreviewLecturers() {
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = '';
    const previewRows = importDataLecturers.slice(0, 10);
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
            <td>${row.department_id}</td>
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

function processLecturersFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = parseCSVLecturers(e.target.result);
        importDataLecturers = validateLecturersData(data);
        showPreviewLecturers();
        // process button state is updated inside showPreviewLecturers
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
    uploadArea.addEventListener('drop', function(e){ e.preventDefault(); this.style.borderColor='#ccc'; this.style.background='#f8f9fa'; const files = e.dataTransfer.files; if (files.length) { fileInput.files = files; processLecturersFile(files[0]); } });
    fileInput.addEventListener('change', function(){ if (this.files.length) processLecturersFile(this.files[0]); });

    processBtn.addEventListener('click', function(){
        const validData = importDataLecturers.filter(r => r.valid);
        if (validData.length === 0) { alert('No valid records to import'); return; }
        const form = document.createElement('form'); form.method='POST'; form.style.display='none';
        const actionInput = document.createElement('input'); actionInput.type='hidden'; actionInput.name='action'; actionInput.value='bulk_import';
        const dataInput = document.createElement('input'); dataInput.type='hidden'; dataInput.name='import_data'; dataInput.value = JSON.stringify(validData);
        form.appendChild(actionInput); form.appendChild(dataInput); document.body.appendChild(form); form.submit();
    });
});
</script>
