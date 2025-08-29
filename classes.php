<?php
$pageTitle = 'Classes Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Fetch streams from database
$streams_sql = "SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name";
$streams_result = $conn->query($streams_sql);

// Handle form submission for adding new class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $department_id = $conn->real_escape_string($_POST['department_id']);
        $level_id = $conn->real_escape_string($_POST['level_id']);
        $stream_id = $conn->real_escape_string($_POST['stream_id']);
        $total_capacity = $conn->real_escape_string($_POST['total_capacity']);
        $current_enrollment = $conn->real_escape_string($_POST['current_enrollment']);
        $max_daily_courses = $conn->real_escape_string($_POST['max_daily_courses']);
        $max_weekly_hours = $conn->real_escape_string($_POST['max_weekly_hours']);
        $preferred_start_time = $conn->real_escape_string($_POST['preferred_start_time']);
        $preferred_end_time = $conn->real_escape_string($_POST['preferred_end_time']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Get department short name and level name
        $dept_query = "SELECT short_name FROM departments WHERE id = ?";
        $dept_stmt = $conn->prepare($dept_query);
        $dept_stmt->bind_param("i", $department_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        $department = $dept_result->fetch_assoc();
        $dept_stmt->close();
        
        $level_query = "SELECT name FROM levels WHERE id = ?";
        $level_stmt = $conn->prepare($level_query);
        $level_stmt->bind_param("i", $level_id);
        $level_stmt->execute();
        $level_result = $level_stmt->get_result();
        $level = $level_result->fetch_assoc();
        $level_stmt->close();
        
        if ($department && $level) {
            $dept_short = $department['short_name'] ?: substr($department['name'], 0, 3);
            $level_name = $level['name'];
            
            // Calculate number of classes needed (max 100 students per class)
            $num_classes = ceil($total_capacity / 100);
            $students_per_class = ceil($total_capacity / $num_classes);
            
            $success_count = 0;
            $error_count = 0;
            
            // Generate multiple classes with alphabetic naming
            for ($i = 0; $i < $num_classes; $i++) {
                $class_letter = chr(65 + $i); // A, B, C, D, E...
                $class_name = $dept_short . " " . $level_name . $class_letter;
                
                // Calculate capacity for this specific class
                $remaining_students = $total_capacity - ($i * $students_per_class);
                $class_capacity = min($students_per_class, $remaining_students);
                
                $sql = "INSERT INTO classes (name, department_id, level, stream_id, capacity, current_enrollment, max_daily_courses, max_weekly_hours, preferred_start_time, preferred_end_time, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sisiiiiissi", $class_name, $department_id, $level_name, $stream_id, $class_capacity, $current_enrollment, $max_daily_courses, $max_weekly_hours, $preferred_start_time, $preferred_end_time, $is_active);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }
            
            if ($success_count > 0) {
                $success_message = "Successfully created $success_count classes!";
                if ($error_count > 0) {
                    $success_message .= " ($error_count classes failed to create)";
                }
            } else {
                $error_message = "Failed to create any classes. Please check your input.";
            }
        } else {
            $error_message = "Invalid department or level selected.";
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE classes SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success_message = "Class deleted successfully!";
        } else {
            $error_message = "Error deleting class: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch classes with department and stream information
$sql = "SELECT c.*, d.name as department_name, d.short_name as department_short, 
               s.name as stream_name, s.code as stream_code
        FROM classes c 
        LEFT JOIN departments d ON c.department_id = d.id 
        LEFT JOIN streams s ON c.stream_id = s.id
        WHERE c.is_active = 1 
        ORDER BY c.name";
$result = $conn->query($sql);

// Fetch departments for dropdown
$dept_sql = "SELECT id, name, short_name FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);

// Fetch levels for dropdown
$level_sql = "SELECT id, name FROM levels ORDER BY year_number";
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
                                <td><span class="badge bg-warning"><?php echo htmlspecialchars($row['level']); ?></span></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($row['program_name'] ?? 'N/A'); ?></span>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($row['program_code'] ?? ''); ?></small>
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
