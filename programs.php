<?php
$pageTitle = 'Programs Management';

try {
    include 'includes/header.php';
    include 'includes/sidebar.php';
    include 'includes/flash.php';

    // Database connection (header may already include this)
    include_once 'connect.php';
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    /**
     * Enhanced bulk import function with comprehensive error handling and conflict resolution
     */
    function perform_bulk_import($conn, $import_data) {
        $result = [
            'success_count' => 0,
            'ignored_count' => 0,
            'error_count' => 0,
            'errors' => [],
            'warnings' => [],
            'details' => []
        ];

        try {
            // Start transaction for data integrity
            $conn->begin_transaction();

            // Prepare all statements for better performance
            $check_name_dept_stmt = $conn->prepare("SELECT id, name, department_id FROM programs WHERE name = ? AND department_id = ?");
            $check_code_stmt = $conn->prepare("SELECT id, code FROM programs WHERE code = ?");
            $check_dept_stmt = $conn->prepare("SELECT id, name FROM departments WHERE id = ? AND is_active = 1");
            $insert_stmt = $conn->prepare("INSERT INTO programs (name, department_id, code, duration_years, is_active) VALUES (?, ?, ?, ?, ?)");

            if (!$check_name_dept_stmt || !$check_code_stmt || !$check_dept_stmt || !$insert_stmt) {
                throw new Exception("Failed to prepare database statements");
            }

            // Preload existing codes and name+department pairs to avoid per-row DB lookups
            $existing_codes = [];
            $existing_name_dept = [];
            $codes_rs = $conn->query("SELECT code, name, department_id FROM programs");
            if ($codes_rs) {
                while ($r = $codes_rs->fetch_assoc()) {
                    $c = trim((string)$r['code']);
                    if ($c !== '') $existing_codes[strtoupper($c)] = true;
                    $n = trim((string)$r['name']);
                    $d = (int)$r['department_id'];
                    if ($n !== '' && $d > 0) $existing_name_dept[strtoupper($n) . '|' . $d] = true;
                }
                $codes_rs->close();
            }

            // Track seen codes and name-dept combos within this import to skip duplicates in-file
            $seen_codes = [];
            $seen_name_dept = [];

            $row_number = 0;
            foreach ($import_data as $row) {
                $row_number++;
                $row_result = process_import_row($conn, $row, $row_number, $check_name_dept_stmt, $check_code_stmt, $check_dept_stmt, $insert_stmt, $existing_codes, $existing_name_dept, $seen_codes, $seen_name_dept);
                
                // Aggregate results
                $result['success_count'] += $row_result['success'] ? 1 : 0;
                $result['ignored_count'] += $row_result['ignored'] ? 1 : 0;
                $result['error_count'] += $row_result['error'] ? 1 : 0;
                
                if ($row_result['error']) {
                    $result['errors'][] = "Row $row_number: " . $row_result['message'];
                }
                if ($row_result['warning']) {
                    $result['warnings'][] = "Row $row_number: " . $row_result['message'];
                }
                if ($row_result['success']) {
                    $result['details'][] = "Row $row_number: Successfully imported '{$row_result['name']}'";
                }
            }

            // Commit transaction if everything went well
            $conn->commit();

        } catch (Exception $e) {
            // Rollback on any error
            $conn->rollback();
            $result['errors'][] = "Import failed: " . $e->getMessage();
            error_log("Bulk import error: " . $e->getMessage());
        } finally {
            // Clean up statements
            if (isset($check_name_dept_stmt)) $check_name_dept_stmt->close();
            if (isset($check_code_stmt)) $check_code_stmt->close();
            if (isset($check_dept_stmt)) $check_dept_stmt->close();
            if (isset($insert_stmt)) $insert_stmt->close();
        }

        return $result;
    }

    /**
     * Process a single import row with comprehensive validation and conflict resolution
     */
    function process_import_row($conn, $row, $row_number, $check_name_dept_stmt, $check_code_stmt, $check_dept_stmt, $insert_stmt, &$existing_codes = [], &$existing_name_dept = [], &$seen_codes = [], &$seen_name_dept = []) {
        $result = [
            'success' => false,
            'ignored' => false,
            'error' => false,
            'warning' => false,
            'message' => '',
            'name' => ''
        ];

        try {
            // Extract and validate data
            $name = isset($row['name']) ? trim($row['name']) : '';
            $department_id = isset($row['department_id']) ? (int)$row['department_id'] : 0;
            $code = isset($row['code']) ? trim($row['code']) : '';
            $duration = isset($row['duration']) ? (int)$row['duration'] : 4;
            $is_active = isset($row['is_active']) ? ((int)$row['is_active'] === 0 ? 0 : 1) : 1;
            $skip = isset($row['skip']) ? (bool)$row['skip'] : false;

            $result['name'] = $name;

            // Basic validation
            if (empty($name)) {
                $result['error'] = true;
                $result['message'] = 'Program name is required';
                return $result;
            }

            if ($department_id <= 0) {
                $result['error'] = true;
                $result['message'] = 'Valid department ID is required';
                return $result;
            }

            // Skip if client-side validation flagged this as duplicate
            if ($skip) {
                $result['ignored'] = true;
                $result['message'] = 'Duplicate detected by client-side validation';
                return $result;
            }

            // Normalize for quick checks
            $norm_code = strtoupper(trim($code));
            $norm_name = strtoupper(trim($name));
            $nd_key = $norm_name . '|' . $department_id;

            // Check existing caches for duplicates (DB or earlier in this CSV)
            if ($norm_code !== '' && (isset($existing_codes[$norm_code]) || isset($seen_codes[$norm_code]))) {
                $result['ignored'] = true;
                $result['message'] = "Program code '$code' already exists";
                return $result;
            }

            if ($norm_name !== '' && (isset($existing_name_dept[$nd_key]) || isset($seen_name_dept[$nd_key]))) {
                $result['ignored'] = true;
                $result['message'] = "Program '$name' already exists in department $department_id";
                return $result;
            }

            // Validate department exists
            $check_dept_stmt->bind_param("i", $department_id);
            $check_dept_stmt->execute();
            $dept_result = $check_dept_stmt->get_result();
            if (!$dept_result || $dept_result->num_rows === 0) {
                $result['error'] = true;
                $result['message'] = "Department ID $department_id does not exist";
                return $result;
            }

            // Generate code if not provided
            if (empty($code)) {
                $code = generate_unique_code($conn, $name, $check_code_stmt);
                $result['warning'] = true;
                $result['message'] = "Generated code: $code";
            }

            // Check for name+department and code conflicts using prepared statements as a fallback
            $check_name_dept_stmt->bind_param("si", $name, $department_id);
            $check_name_dept_stmt->execute();
            $name_dept_result = $check_name_dept_stmt->get_result();
            if ($name_dept_result && $name_dept_result->num_rows > 0) {
                $result['ignored'] = true;
                $result['message'] = "Program '$name' already exists in department $department_id";
                return $result;
            }

            // Check for code conflicts
            $check_code_stmt->bind_param("s", $code);
            $check_code_stmt->execute();
            $code_result = $check_code_stmt->get_result();
            if ($code_result && $code_result->num_rows > 0) {
                $result['ignored'] = true;
                $result['message'] = "Program code '$code' already exists";
                return $result;
            }

            // Mark code and name-dept as seen to prevent duplicates later in this import
            if ($norm_code !== '') {
                $seen_codes[$norm_code] = true;
            }
            $seen_name_dept[$nd_key] = true;

            // Validate duration
            if ($duration < 1 || $duration > 10) {
                $result['warning'] = true;
                $result['message'] = "Duration $duration years seems unusual, using default 4 years";
                $duration = 4;
            }

            // Insert the program
            $insert_stmt->bind_param("sisii", $name, $department_id, $code, $duration, $is_active);
            if ($insert_stmt->execute()) {
                $result['success'] = true;
                $result['message'] = "Successfully imported program '$name'";
                error_log("Successfully imported program: $name (dept: $department_id, code: $code)");
            } else {
                $result['error'] = true;
                $result['message'] = "Database error: " . $conn->error;
            }

        } catch (Exception $e) {
            $result['error'] = true;
            $result['message'] = "Processing error: " . $e->getMessage();
            error_log("Row $row_number processing error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Generate a unique program code based on the program name
     */
    function generate_unique_code($conn, $name, $check_code_stmt) {
        $base_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 6));
        if (strlen($base_code) < 3) {
            $base_code = 'PRG' . $base_code;
        }

        $code = $base_code;
        $counter = 1;
        $max_attempts = 100;

        while ($counter <= $max_attempts) {
            $check_code_stmt->bind_param("s", $code);
            $check_code_stmt->execute();
            $result = $check_code_stmt->get_result();
            
            if (!$result || $result->num_rows === 0) {
                return $code; // Found unique code
            }
            
            $code = $base_code . $counter;
            $counter++;
        }

        // If we can't find a unique code, use timestamp
        return $base_code . '_' . time() % 1000;
    }

// Handle bulk import and form submissions
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    // Bulk import
    if ($action === 'bulk_import') {
        $raw = $_POST['import_data'] ?? '';
        if (trim($raw) === '') {
            $error_message = 'No import data provided.';
        } else {
            $import_data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_message = 'Invalid JSON import data: ' . json_last_error_msg();
            } elseif (!is_array($import_data) || empty($import_data)) {
                $error_message = 'No rows found in import data.';
            } else {
                // Enhanced import with detailed error handling and conflict resolution
                $import_result = perform_bulk_import($conn, $import_data);
                
                if ($import_result['success_count'] > 0) {
                    $msg = "Successfully imported {$import_result['success_count']} programs!";
                    if ($import_result['ignored_count'] > 0) {
                        $msg .= " {$import_result['ignored_count']} duplicates ignored.";
                    }
                    if ($import_result['error_count'] > 0) {
                        $msg .= " {$import_result['error_count']} records failed to import.";
                    }
                    if (!empty($import_result['warnings'])) {
                        $msg .= " Warnings: " . implode(', ', $import_result['warnings']);
                    }
                    redirect_with_flash('programs.php', 'success', $msg);
                } else {
                    if ($import_result['ignored_count'] > 0 && $import_result['error_count'] === 0) {
                        $success_message = "No new programs imported. {$import_result['ignored_count']} duplicates ignored.";
                    } else {
                        $error_message = "No programs were imported. Please check your data.";
                        if (!empty($import_result['errors'])) {
                            $error_message .= " Errors: " . implode(', ', array_slice($import_result['errors'], 0, 3));
                        }
                    }
                }
            }
        }
    }

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

if (!$result) {
    throw new Exception("Error fetching programs: " . $conn->error);
}

// Fetch departments for dropdown
$dept_sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);

if (!$dept_result) {
    throw new Exception("Error fetching departments: " . $conn->error);
}
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

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Programs (CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div id="uploadArea" class="upload-area p-4 text-center border rounded" style="cursor:pointer;">
                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                        <p class="mb-1">Drop CSV file here or <strong>click to browse</strong></p>
                        <small class="text-muted">Expected headers: name,department_id,code,duration,is_active</small>
                    </div>
                    <input type="file" class="form-control d-none" id="csvFile" accept=".csv,text/csv">
                </div>

                <div class="mb-3">
                    <h6>Preview (first 10 rows)</h6>
                    <div class="table-responsive" style="max-height:300px;overflow:auto">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Dept ID</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Upload a CSV file to preview</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="importSummary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="processBtn" disabled>Process File</button>
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
} catch (Exception $e) {
    // Log the exception
    error_log("Exception in programs.php: " . $e->getMessage());
    
    // Display user-friendly error message
    echo '<div class="alert alert-danger" role="alert">';
    echo '<h4>An error occurred</h4>';
    echo '<p>Please contact the administrator or try refreshing the page.</p>';
    echo '<small>Error details have been logged.</small>';
    echo '</div>';
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

include 'includes/footer.php'; 
?>

<style>
.upload-area {
    border: 2px dashed #ccc !important;
    transition: all 0.3s ease;
}

.upload-area:hover {
    border-color: #007bff !important;
    background-color: #f8f9fa;
}

.upload-area.dragover {
    border-color: #007bff !important;
    background-color: #e3f2fd !important;
}
</style>

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
 
 <script>
 // Import functionality
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
    return data.map((row, index) => {
        const validated = {
            name: row.name || row.Name || '',
            department_id: row.department_id || row.departmentId || row.department_id || '',
            code: row.code || row.Code || '',
            duration: row.duration || row.duration_years || row.durationYears || '',
            is_active: (row.is_active || row.isActive || '1').toString().trim() === '0' ? '0' : '1',
            row_number: index + 1
        };
        
        validated.valid = true;
        validated.errors = [];
        validated.warnings = [];
        validated.skip = false;

        // Enhanced validation with detailed error messages
        if (!validated.name.trim()) { 
            validated.valid = false; 
            validated.errors.push('Program name is required'); 
        }

        if (!validated.department_id.toString().trim() || validated.department_id <= 0) { 
            validated.valid = false; 
            validated.errors.push('Valid department ID is required'); 
        }

        if (!validated.code.trim()) { 
            validated.warnings.push('Code will be auto-generated'); 
        }

        if (!validated.duration.toString().trim() || validated.duration < 1 || validated.duration > 10) { 
            validated.warnings.push('Duration will default to 4 years'); 
        }

        // Check for duplicate name+department
        if (existingProgramNameDeptSet[validated.name.trim().toUpperCase() + '|' + validated.department_id]) {
            validated.valid = true; // Mark as valid so it gets sent to server
            validated.errors.push('Program name and department combination already exists');
            validated.skip = true; // Flag to skip on server side
        }

        // Check for duplicate code
        if (existingProgramCodesSet[validated.code.trim().toUpperCase()]) {
            validated.valid = true; // Mark as valid so it gets sent to server
            validated.errors.push('Program code already exists');
            validated.skip = true; // Flag to skip on server side
        }

        return validated;
    });
}

function showPreviewPrograms() {
    const tbody = document.getElementById('previewBody');
    const processBtn = document.getElementById('processBtn');
    
    if (!tbody) {
        console.error('previewBody missing');
        return;
    }
    
    tbody.innerHTML = '';
    const previewRows = importDataPrograms.slice(0, 10);
    let validCount = 0;
    
    console.log('Showing preview for', previewRows.length, 'rows');

    previewRows.forEach((row, idx) => {
        const tr = document.createElement('tr');
        tr.className = row.valid ? '' : 'table-danger';

        // Determine status badge/text with enhanced feedback
        let validationHtml = '';
        let statusClass = '';
        
        if (row.valid && !row.skip) {
            if (row.warnings && row.warnings.length > 0) {
                validationHtml = '<span class="text-warning">⚠ Valid (with warnings)</span>';
                statusClass = 'table-warning';
            } else {
                validationHtml = '<span class="text-success">✓ Valid</span>';
                statusClass = '';
            }
            validCount++;
        } else if (row.skip) {
            validationHtml = '<span class="badge bg-secondary">Skipped (exists)</span>';
            statusClass = 'table-secondary';
        } else {
            validationHtml = '<span class="text-danger">✗ ' + (row.errors ? row.errors.join(', ') : 'Invalid') + '</span>';
            statusClass = 'table-danger';
        }
        
        tr.className = statusClass;
        
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

    // Update process button and show summary
    if (processBtn) {
        const validData = importDataPrograms.filter(r => r.valid && !r.skip);
        const skippedData = importDataPrograms.filter(r => r.skip);
        const errorData = importDataPrograms.filter(r => !r.valid && !r.skip);
        const warningData = importDataPrograms.filter(r => r.valid && !r.skip && r.warnings && r.warnings.length > 0);
        
        if (validData.length > 0) {
            processBtn.disabled = false;
            let btnText = `Process ${validData.length} Records`;
            if (warningData.length > 0) {
                btnText += ` (${warningData.length} with warnings)`;
            }
            processBtn.textContent = btnText;
        } else {
            processBtn.disabled = true;
            processBtn.textContent = 'Process File';
        }
        
        // Show summary if there are multiple rows
        if (importDataPrograms.length > 1) {
            const summaryDiv = document.getElementById('importSummary');
            if (summaryDiv) {
                let summaryHtml = '<div class="alert alert-info mt-2"><strong>Import Summary:</strong> ';
                summaryHtml += `${validData.length} valid, ${skippedData.length} duplicates, ${errorData.length} errors`;
                if (warningData.length > 0) {
                    summaryHtml += `, ${warningData.length} with warnings`;
                }
                summaryHtml += '</div>';
                summaryDiv.innerHTML = summaryHtml;
            }
        }
    }
}

function processProgramsFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = parseCSVPrograms(e.target.result);
        importDataPrograms = validateProgramsData(data);
        showPreviewPrograms();
        
        // Clear any previous summary
        const summaryDiv = document.getElementById('importSummary');
        if (summaryDiv) {
            summaryDiv.innerHTML = '';
        }
    };
    reader.readAsText(file);
}

// Set up drag/drop and file input
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csvFile');
    const processBtn = document.getElementById('processBtn');

    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', function(e){
            e.preventDefault();
            try {
                fileInput.click();
            } catch(err) {
                // fallback: temporarily show input and click
                fileInput.style.display = 'block';
                fileInput.click();
                fileInput.style.display = 'none';
            }
            return false;
        });

        uploadArea.addEventListener('dragover', function(e){ 
            e.preventDefault(); 
            e.stopPropagation(); 
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e){ 
            e.preventDefault(); 
            e.stopPropagation(); 
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e){
            e.preventDefault(); 
            e.stopPropagation();
            this.classList.remove('dragover');
            const files = e.dataTransfer.files; 
            if (files && files.length) { 
                fileInput.files = files; 
                processProgramsFile(files[0]); 
            }
        });
    } else {
        console.debug('Import: uploadArea or csvFile not found in DOM');
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', function(){ 
            if (this.files.length) processProgramsFile(this.files[0]); 
        });
    }

    if (processBtn) {
        processBtn.addEventListener('click', function(){
            const validData = importDataPrograms.filter(r => r.valid && !r.skip);
            if (validData.length === 0) { 
                alert('No valid records to import'); 
                return; 
            }
            
            // Show confirmation with details
            const skippedData = importDataPrograms.filter(r => r.skip);
            const warningData = importDataPrograms.filter(r => r.valid && !r.skip && r.warnings && r.warnings.length > 0);
            
            let confirmMsg = `Import ${validData.length} programs?`;
            if (skippedData.length > 0) {
                confirmMsg += `\n${skippedData.length} duplicates will be skipped.`;
            }
            if (warningData.length > 0) {
                confirmMsg += `\n${warningData.length} programs have warnings (missing codes, unusual durations, etc.).`;
            }
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            // Disable button to prevent double submission
            processBtn.disabled = true;
            processBtn.textContent = 'Processing...';
            
            const form = document.createElement('form'); 
            form.method='POST'; 
            form.style.display='none';
            
            const actionInput = document.createElement('input'); 
            actionInput.type='hidden'; 
            actionInput.name='action'; 
            actionInput.value='bulk_import';
            
            const dataTextarea = document.createElement('textarea'); 
            dataTextarea.name='import_data'; 
            dataTextarea.style.display='none'; 
            dataTextarea.textContent = JSON.stringify(validData);
            
            form.appendChild(actionInput); 
            form.appendChild(dataTextarea); 
            document.body.appendChild(form); 
            form.submit();
        });
    }
});
</script>
