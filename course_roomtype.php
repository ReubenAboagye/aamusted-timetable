<?php
include 'connect.php';

$success_message = '';
$error_message = '';

// Helper: resolve room type id from either numeric id or name
function resolveRoomTypeId($conn, $roomType) {
    if (is_numeric($roomType)) return (int)$roomType;
    $stmt = $conn->prepare("SELECT id FROM room_types WHERE name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $roomType);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }
    return null;
}

// Handle single course availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_single') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $room_type = $_POST['room_type'] ?? '';
    $room_type_id = resolveRoomTypeId($conn, $room_type);

    if ($course_id <= 0 || !$room_type_id) {
        $error_message = 'Please select a valid course and room type.';
    } else {
        // Check if course is already available
        $check_sql = "SELECT COUNT(*) as count FROM course_room_types WHERE course_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();

        if ($check_row['count'] > 0) {
            $error_message = "This course already has room type preferences set.";
        } else {
            $sql = "INSERT INTO course_room_types (course_id, room_type_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $course_id, $room_type_id);

            if ($stmt->execute()) {
                $stmt->close();
                redirect_with_flash('course_roomtype.php', 'success', 'Course room type preference added successfully!');
            } else {
                $error_message = "Error adding course room type preference.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle bulk course availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bulk') {
    $course_ids = $_POST['course_ids'] ?? [];
    $room_type = $_POST['room_type'] ?? '';
    $room_type_id = resolveRoomTypeId($conn, $room_type);

    if (empty($course_ids) || !$room_type_id) {
        $error_message = "Please select courses and specify a valid room type.";
    } else {
        $stmt = $conn->prepare("INSERT IGNORE INTO course_room_types (course_id, room_type_id) VALUES (?, ?)");

        foreach ($course_ids as $course_id) {
            $cid = (int)$course_id;
            $stmt->bind_param("ii", $cid, $room_type_id);
            $stmt->execute();
        }
        $stmt->close();
        redirect_with_flash('course_roomtype.php', 'success', 'All selected courses have been assigned room type preferences!');
    }
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['course_id'])) {
    $course_id = (int)$_POST['course_id'];
    $sql = "DELETE FROM course_room_types WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $course_id);

    if ($stmt->execute()) {
        $stmt->close();
        redirect_with_flash('course_roomtype.php', 'success', 'Course room type preference deleted successfully!');
    } else {
        $error_message = "Error deleting course room type preference.";
    }
    $stmt->close();
}

// Handle editing room type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_room_type' && isset($_POST['edit_course_id']) && isset($_POST['room_type'])) {
    $course_id = (int)$_POST['edit_course_id'];
    $room_type = $_POST['room_type'] ?? '';
    $room_type_id = resolveRoomTypeId($conn, $room_type);

    if ($course_id <= 0 || !$room_type_id) {
        $error_message = 'Invalid input for update.';
    } else {
        $sql = "UPDATE course_room_types SET room_type_id = ? WHERE course_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $room_type_id, $course_id);

        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('course_roomtype.php', 'success', 'Course room type preference updated successfully!');
        } else {
            $error_message = "Error updating course room type preference.";
        }
        $stmt->close();
    }
}

// Get existing course room type preferences
$sql = "SELECT crt.course_id, rt.id AS room_type_id, rt.name AS preferred_room_type,
        co.`code` AS course_code, co.`name` AS course_name
        FROM course_room_types crt
        LEFT JOIN room_types rt ON crt.room_type_id = rt.id
        LEFT JOIN courses co ON crt.course_id = co.id
        ORDER BY co.`code`";
$result = $conn->query($sql);

// Get all courses for dropdowns
$courses_sql = "SELECT id, `code` AS course_code, `name` AS course_name FROM courses WHERE is_active = 1 ORDER BY `code`";
$courses_result = $conn->query($courses_sql);

// Get room types
$room_types = [];
$rt_res = $conn->query("SELECT id, name FROM room_types ORDER BY name");
if ($rt_res) {
    while ($r = $rt_res->fetch_assoc()) {
        $room_types[] = $r;
    }
} else {
    // Fallback list
    $room_types = [['id'=>1,'name'=>'Lecture Hall'], ['id'=>2,'name'=>'Laboratory'], ['id'=>3,'name'=>'Computer Lab']];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Room Type Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-maroon: #7a0b1c;
            --brand-maroon-dark: #5a0713;
            --muted-border: rgba(0,0,0,0.08);
        }
        .select2-container {
            width: 100% !important;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid var(--muted-border);
        }

        /* Brand header styling to match project theme */
        .card-header.bg-primary {
            background: var(--brand-maroon) !important;
            border-bottom: 1px solid var(--brand-maroon-dark);
        }
        .card-header.bg-primary h5, .card-header.bg-primary .btn {
            color: #fff !important;
        }

        /* Buttons in header */
        .card-header .btn-outline-light {
            color: #fff;
            border-color: rgba(255,255,255,0.15);
        }
        .card-header .btn-light {
            background: #fff;
            color: var(--brand-maroon);
        }

        /* Action icons styling to match brand */
        .table .btn-danger {
            border-color: var(--brand-maroon);
            color: var(--brand-maroon);
            background: transparent;
        }
        .table .btn-danger:hover {
            background: rgba(122,11,28,0.05);
        }
        .table .btn-warning {
            border-color: var(--brand-maroon);
            color: var(--brand-maroon);
            background: transparent;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2"></i>Course Room Type Management
                        </h5>
                        <div>
                            <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#bulkCourseModal">
                                <i class="fas fa-layer-group me-1"></i>Bulk Add
                            </button>
                            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                <i class="fas fa-plus me-1"></i>Add Single
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table" id="courseRoomTypeTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Preferred Room Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['preferred_room_type']); ?></td>
                                                <td>
                                                    <button class="btn btn-warning btn-sm me-1" 
                                                            onclick="openEditModal('<?php echo $row['course_id']; ?>', '<?php echo htmlspecialchars($row['course_code']); ?>', '<?php echo htmlspecialchars($row['preferred_room_type'] ?? ''); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="course_id" value="<?php echo $row['course_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to delete this room type preference?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">
                                                <i class="fas fa-info-circle me-2"></i>No course room type preferences found
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

    <!-- Bulk Add Modal -->
    <div class="modal fade" id="bulkCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Add Course Room Type Preferences</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_bulk">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <label for="bulk_course_ids" class="form-label">Courses *</label>
                                <select class="form-select" id="bulk_course_ids" name="course_ids[]" multiple required>
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
                            
                            <div class="col-md-4">
                                <label for="bulk_room_type" class="form-label">Room Type *</label>
                                <select class="form-select" id="bulk_room_type" name="room_type" required>
                                    <option value="">Select Room Type</option>
                                    <?php foreach ($room_types as $type): ?>
                                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This will assign the selected room type preference to all selected courses.
                            Courses that already have preferences will be skipped.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Add Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Single Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Single Course Room Type Preference</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_single">
                        
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
                        
                        <div class="mb-3">
                            <label for="room_type" class="form-label">Room Type *</label>
                            <select class="form-select" id="room_type" name="room_type" required>
                                <option value="">Select Room Type</option>
                                <?php foreach ($room_types as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Add Preference
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course Room Type Preference</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_room_type">
                        <input type="hidden" name="edit_course_id" id="edit_course_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" id="edit_course_display" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_room_type" class="form-label">Room Type *</label>
                            <select class="form-select" id="edit_room_type" name="room_type" required>
                                <option value="">Select Room Type</option>
                                <?php foreach ($room_types as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i>Update Preference
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
            $('#bulk_course_ids').select2({
                placeholder: "Select courses...",
                allowClear: true
            });
            
            // Initialize DataTable for better table experience
            $('#courseRoomTypeTable').DataTable({
                pageLength: 25,
                order: [[0, 'asc']],
                language: {
                    search: "Search preferences:",
                    lengthMenu: "Show _MENU_ preferences per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ preferences"
                }
            });
        });
        
        function openEditModal(courseId, courseCode, roomType) {
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('edit_course_display').value = courseCode;
            document.getElementById('edit_room_type').value = roomType;
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>
</html>
