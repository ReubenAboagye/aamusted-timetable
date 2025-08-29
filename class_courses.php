<?php
include 'connect.php';

// Handle single assignment
if ($_POST['action'] === 'assign_single') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    
    if ($class_id > 0 && $course_id > 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $class_id, $course_id);
        
        if ($stmt->execute()) {
            $success_message = "Course assigned to class successfully!";
        } else {
            $error_message = "Error assigning course to class.";
        }
        $stmt->close();
    } else {
        $error_message = "Please select both class and course.";
    }
}

// Handle bulk assignment
if ($_POST['action'] === 'assign_bulk') {
    $class_ids = $_POST['class_ids'] ?? [];
    $course_ids = $_POST['course_ids'] ?? [];
    
    if (!empty($class_ids) && !empty($course_ids)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id) VALUES (?, ?)");
        
        foreach ($class_ids as $cid) {
            foreach ($course_ids as $coid) {
                $stmt->bind_param('ii', $cid, $coid);
                $stmt->execute();
            }
        }
        $stmt->close();
        $success_message = "Bulk assignment completed successfully!";
    } else {
        $error_message = "Please select both classes and courses.";
    }
}

// Handle deletion
if ($_POST['action'] === 'delete' && isset($_POST['class_course_id'])) {
    $class_course_id = (int)$_POST['class_course_id'];
    $stmt = $conn->prepare("DELETE FROM class_courses WHERE id = ?");
    $stmt->bind_param('i', $class_course_id);
    
    if ($stmt->execute()) {
        $success_message = "Assignment deleted successfully!";
    } else {
        $error_message = "Error deleting assignment.";
    }
    $stmt->close();
}

// Get all classes
$classes_sql = "SELECT id, name, level FROM classes WHERE is_active = 1 ORDER BY name";
$classes_result = $conn->query($classes_sql);

// Get all courses
$courses_sql = "SELECT id, course_code, course_name FROM courses WHERE is_active = 1 ORDER BY course_code";
$courses_result = $conn->query($courses_sql);

// Get existing assignments
$assignments_sql = "SELECT cc.id, c.name as class_name, c.level, co.course_code, co.course_name
                    FROM class_courses cc
                    JOIN classes c ON cc.class_id = c.id
                    JOIN courses co ON cc.course_id = co.id
                    WHERE cc.is_active = 1
                    ORDER BY c.name, co.course_code";
$assignments_result = $conn->query($assignments_sql);

// Get existing assignments for bulk operations
$existing_assignments_sql = "SELECT cc.class_id, cc.course_id
                            FROM class_courses cc
                            WHERE cc.is_active = 1";
$existing_assignments_result = $conn->query($existing_assignments_sql);

$existing_assignments = [];
if ($existing_assignments_result) {
    while ($row = $existing_assignments_result->fetch_assoc()) {
        $existing_assignments[] = $row['class_id'] . '_' . $row['course_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Course Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        .select2-container {
            width: 100% !important;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>Class Course Management
                        </h5>
                        <div>
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#bulkAssignmentModal">
                                <i class="fas fa-layer-group me-1"></i>Bulk Assignment
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#singleAssignmentModal">
                                <i class="fas fa-plus me-1"></i>Single Assignment
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="assignmentsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Class</th>
                                        <th>Level</th>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($assignments_result && $assignments_result->num_rows > 0): ?>
                                        <?php while ($row = $assignments_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['level']); ?></td>
                                                <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="class_course_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to delete this assignment?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                <i class="fas fa-info-circle me-2"></i>No assignments found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Assignment Modal -->
    <div class="modal fade" id="bulkAssignmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-layer-group me-2"></i>Bulk Assignment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_bulk">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="bulk_classes" class="form-label">Classes *</label>
                                <select class="form-select" id="bulk_classes" name="class_ids[]" multiple required>
                                    <?php if ($classes_result): ?>
                                        <?php while ($class = $classes_result->fetch_assoc()): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo htmlspecialchars($class['name'] . ' (' . $class['level'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">Hold Ctrl/Cmd to select multiple classes</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="bulk_courses" class="form-label">Courses *</label>
                                <select class="form-select" id="bulk_courses" name="course_ids[]" multiple required>
                                    <?php if ($courses_result): ?>
                                        <?php while ($course = $courses_result->fetch_assoc()): ?>
                                            <option value="<?php echo $course['id']; ?>">
                                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">Hold Ctrl/Cmd to select multiple courses</div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This will create assignments for all selected class-course combinations.
                            Existing assignments will be skipped.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Create Assignments
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Single Assignment Modal -->
    <div class="modal fade" id="singleAssignmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Single Assignment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_single">
                        
                        <div class="mb-3">
                            <label for="class_id" class="form-label">Class *</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php 
                                // Reset the classes result set for reuse
                                if ($classes_result) {
                                    $classes_result->data_seek(0);
                                    while ($class = $classes_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['name'] . ' (' . $class['level'] . ')'); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course *</label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php 
                                // Reset the courses result set for reuse
                                if ($courses_result) {
                                    $courses_result->data_seek(0);
                                    while ($course = $courses_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for better dropdown experience
            $('#bulk_classes, #bulk_courses').select2({
                placeholder: "Select options...",
                allowClear: true
            });
            
            // Initialize DataTable for better table experience
            $('#assignmentsTable').DataTable({
                pageLength: 25,
                order: [[0, 'asc'], [2, 'asc']],
                language: {
                    search: "Search assignments:",
                    lengthMenu: "Show _MENU_ assignments per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ assignments"
                }
            });
        });
    </script>
</body>
</html>

