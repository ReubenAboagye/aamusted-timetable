<?php
$pageTitle = 'Classes Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Check whether the `departments` table has a `short_name` column (some DBs may lack it)
$dept_short_exists = false;
$col_check = $conn->query("SHOW COLUMNS FROM departments LIKE 'short_name'");
if ($col_check && $col_check->num_rows > 0) {
    $dept_short_exists = true;
}

// Fetch streams from database
$streams_sql = "SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name";
$streams_result = $conn->query($streams_sql);

// Handle form submission for adding new class
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'add') {
        // Current schema: classes has program_id and level_id
        $program_id = $conn->real_escape_string($_POST['program_code'] ?? '');
        $level_id = $conn->real_escape_string($_POST['level_id'] ?? '');
        $total_capacity = intval($_POST['total_capacity'] ?? 0);
        $stream_id = isset($_POST['stream_id']) ? $conn->real_escape_string($_POST['stream_id']) : 1;
        $is_active = 1;

        // Fetch program and level
        $prog_stmt = $conn->prepare("SELECT id, name, code FROM programs WHERE id = ?");
        $prog_stmt->bind_param("i", $program_id);
        $prog_stmt->execute();
        $prog_res = $prog_stmt->get_result();
        $program = $prog_res->fetch_assoc();
        $prog_stmt->close();

        $lvl_stmt = $conn->prepare("SELECT id, name FROM levels WHERE id = ?");
        $lvl_stmt->bind_param("i", $level_id);
        $lvl_stmt->execute();
        $lvl_res = $lvl_stmt->get_result();
        $level = $lvl_res->fetch_assoc();
        $lvl_stmt->close();

        if ($program && $level) {
            $num_classes = max(1, ceil($total_capacity / 100));
            $success_count = 0;
            $error_count = 0;

            // Extract level numeric part for code (e.g., 'Level 100' -> '100')
            preg_match('/(\d+)/', $level['name'], $m);
            $level_num = $m[1] ?? '1';
            $year_suffix = date('Y');

            for ($i = 0; $i < $num_classes; $i++) {
                $letter = chr(65 + $i);
                $class_name = $program['name'] . ' ' . $level['name'] . ($num_classes > 1 ? $letter : '');
                $class_code = ($program['code'] ?? 'PRG') . '-Y' . $level_num . '-' . $year_suffix . ($num_classes > 1 ? $letter : '');
                $academic_year = date('Y') . '/' . (date('Y') + 1);
                $semester = 'first';

                $sql = "INSERT INTO classes (program_id, level_id, name, code, academic_year, semester, stream_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iissssii", $program_id, $level_id, $class_name, $class_code, $academic_year, $semester, $stream_id, $is_active);

                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }

            if ($success_count > 0) {
                $msg = "Successfully created $success_count classes!";
                if ($error_count > 0) {
                    $msg .= " ($error_count classes failed to create)";
                }
                redirect_with_flash('classes.php', 'success', $msg);
            } else {
                $error_message = "Failed to create any classes. Please check your input.";
            }
        } else {
            $error_message = "Invalid program or level selected.";
        }
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE classes SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('classes.php', 'success', 'Class deleted successfully!');
        } else {
            $error_message = "Error deleting class: " . $conn->error;
        }
        $stmt->close();
    }
}

// Include stream manager
include 'includes/stream_manager.php';
$streamManager = getStreamManager();

// Fetch classes with related program, level and stream information
// Detect commonly used foreign keys on `classes` and join accordingly
$class_has_program = false;
$class_has_level = false;
$class_has_stream = false;
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'program_id'");
if ($col && $col->num_rows > 0) {
    $class_has_program = true;
}
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'level_id'");
if ($col && $col->num_rows > 0) {
    $class_has_level = true;
}
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
if ($col && $col->num_rows > 0) {
    $class_has_stream = true;
}

$select_extra = [];
$from_clause = "FROM classes c ";

// Join programs
if ($class_has_program) {
    $select_extra[] = "p.name as program_name";
    $select_extra[] = "p.code as program_code";
    $from_clause .= "LEFT JOIN programs p ON c.program_id = p.id ";
}

// Join levels
if ($class_has_level) {
    $select_extra[] = "l.name as level_name";
    $from_clause .= "LEFT JOIN levels l ON c.level_id = l.id ";
}

// Join streams
if ($class_has_stream) {
    $select_extra[] = "s.name as stream_name";
    $select_extra[] = "s.code as stream_code";
    $from_clause .= "LEFT JOIN streams s ON c.stream_id = s.id ";
}

// Preserve department short selection (only used when programs/departments are linked)
if ($dept_short_exists && !$class_has_program) {
    // If we don't have program join, but departments are expected elsewhere, include department placeholder
    $select_extra[] = $dept_short_exists ? "d.short_name as department_short" : "SUBSTRING(d.name,1,3) as department_short";
}

$select_clause = "c.*";
if (!empty($select_extra)) {
    $select_clause .= ", " . implode(', ', $select_extra);
}

$where_clause = "WHERE c.is_active = 1";
if ($class_has_stream) {
    $where_clause .= " AND c.stream_id = " . $streamManager->getCurrentStreamId();
}

$sql = "SELECT " . $select_clause . "\n        " . $from_clause . "\n        " . $where_clause . "\n        ORDER BY c.name";
$result = $conn->query($sql);

$dept_select_cols = $dept_short_exists ? "id, name, short_name" : "id, name";
// Departments are global; do not filter by stream here
$dept_sql = "SELECT " . $dept_select_cols . " FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);

// Fetch levels for dropdown
// Use year_number for ordering when available, otherwise fall back to id
$level_order_col = 'year_number';
$col_check = $conn->query("SHOW COLUMNS FROM levels LIKE 'year_number'");
if (!($col_check && $col_check->num_rows > 0)) {
    $level_order_col = 'id';
}
$level_sql = "SELECT id, name FROM levels ORDER BY " . $level_order_col;
$level_result = $conn->query($level_sql);


?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-users me-2"></i>Classes Management</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="fas fa-plus me-2"></i>Add New Classes
            </button>
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
            <input type="text" class="search-input" placeholder="Search classes...">
        </div>

        <div class="table-responsive">
            <table class="table" id="classesTable">
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Level</th>
                        <th>Program</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><span class="badge bg-warning"><?php echo htmlspecialchars($row['level'] ?? ($row['level_name'] ?? 'N/A')); ?></span></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($row['program_name'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editClass(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this class?')">
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
                                <i class="fas fa-users"></i>
                                <p>No classes found. Add your first classes to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Classes Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Classes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Auto-Class Generation:</strong> Select the program, level, and enter the number of students. The system will automatically create multiple classes (max 100 students per class) with proper naming convention.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="program_code" class="form-label">Program Code *</label>
                                <select class="form-select" id="program_code" name="program_code" required>
                                    <option value="">Select Program</option>
                                    <?php
                                    // Fetch programs for dropdown
                                    $programs_sql = "SELECT id, name, code FROM programs WHERE is_active = 1 ORDER BY name";
                                    $programs_result = $conn->query($programs_sql);
                                    if ($programs_result && $programs_result->num_rows > 0):
                                        while ($program = $programs_result->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $program['id']; ?>" data-code="<?php echo htmlspecialchars($program['code']); ?>">
                                            <?php echo htmlspecialchars($program['name']); ?> (<?php echo htmlspecialchars($program['code']); ?>)
                                        </option>
                                    <?php 
                                        endwhile;
                                    endif;
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="level_id" class="form-label">Level *</label>
                                <select class="form-select" id="level_id" name="level_id" required>
                                    <option value="">Select Level</option>
                                    <?php if ($level_result && $level_result->num_rows > 0): ?>
                                        <?php while ($level = $level_result->fetch_assoc()): ?>
                                            <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="total_capacity" class="form-label">Number of Students *</label>
                                <input type="number" class="form-control" id="total_capacity" name="total_capacity" min="1" max="1000" value="100" required>
                                <small class="text-muted">The system will automatically create multiple classes (max 100 students per class)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div id="classPreview" class="alert alert-secondary" style="display: none;">
                        <strong>Class Preview:</strong> <span id="previewText"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Classes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
function editClass(id) {
    // TODO: Implement edit functionality
    alert('Edit functionality will be implemented here for class ID: ' + id);
}

// Preview class names as user types
document.getElementById('total_capacity').addEventListener('input', updateClassPreview);
document.getElementById('program_code').addEventListener('change', updateClassPreview);
document.getElementById('level_id').addEventListener('change', updateClassPreview);


function updateClassPreview() {
    const capacity = parseInt(document.getElementById('total_capacity').value) || 0;
    const programSelect = document.getElementById('program_code');
    const levelSelect = document.getElementById('level_id');
    const previewDiv = document.getElementById('classPreview');
    const previewText = document.getElementById('previewText');
    
    if (capacity > 0 && programSelect.value && levelSelect.value) {
        const selectedOption = programSelect.options[programSelect.selectedIndex];
        const programCode = selectedOption.dataset.code || selectedOption.text.match(/\(([^)]+)\)/)?.[1] || 'PROG';
        const levelText = levelSelect.options[levelSelect.selectedIndex].text;
        
        // Extract just the number from level text (e.g., "Level 100" -> "100")
        const levelNumber = levelText.match(/\d+/)?.[0] || levelText.replace(/\D/g, '');
        
        const numClasses = Math.ceil(capacity / 100);
        const classes = [];
        
        for (let i = 0; i < numClasses; i++) {
            const letter = String.fromCharCode(65 + i);
            classes.push(programCode + levelNumber + letter);
        }
        
        previewText.textContent = classes.join(', ');
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
    }
}
</script>
