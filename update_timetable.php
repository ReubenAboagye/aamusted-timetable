<?php
include 'connect.php';

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM timetable WHERE id = ?");
            $stmt->bind_param('i', $id);
            
            if ($stmt->execute()) {
                $success_message = "Timetable entry deleted successfully!";
            } else {
                $error_message = "Error deleting timetable entry.";
            }
            $stmt->close();
        } else {
            $error_message = "Invalid timetable entry ID.";
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $day_id = (int)($_POST['day_id'] ?? 0);
        $time_slot_id = (int)($_POST['time_slot_id'] ?? 0);
        $room_id = (int)($_POST['room_id'] ?? 0);
        $class_course_id = (int)($_POST['class_course_id'] ?? 0);
        
        if ($id > 0 && $day_id > 0 && $time_slot_id > 0 && $room_id > 0 && $class_course_id > 0) {
            // Check for conflicts
            $check_sql = "SELECT COUNT(*) as count FROM timetable WHERE day_id = ? AND time_slot_id = ? AND room_id = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iiii", $day_id, $time_slot_id, $room_id, $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $conflict = $check_result->fetch_assoc()['count'] > 0;
            $check_stmt->close();
            
            if ($conflict) {
                $error_message = "This time slot and room is already occupied. Please choose a different time or room.";
            } else {
                $stmt = $conn->prepare("UPDATE timetable SET day_id = ?, time_slot_id = ?, room_id = ?, class_course_id = ? WHERE id = ?");
                $stmt->bind_param('iiiii', $day_id, $time_slot_id, $room_id, $class_course_id, $id);
                
                if ($stmt->execute()) {
                    $success_message = "Timetable entry updated successfully!";
                } else {
                    $error_message = "Error updating timetable entry.";
                }
                $stmt->close();
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    } elseif ($action === 'create') {
        $day_id = (int)($_POST['day_id'] ?? 0);
        $time_slot_id = (int)($_POST['time_slot_id'] ?? 0);
        $room_id = (int)($_POST['room_id'] ?? 0);
        $class_course_id = (int)($_POST['class_course_id'] ?? 0);
        
        if ($day_id > 0 && $time_slot_id > 0 && $room_id > 0 && $class_course_id > 0) {
            // Check for conflicts
            $check_sql = "SELECT COUNT(*) as count FROM timetable WHERE day_id = ? AND time_slot_id = ? AND room_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iii", $day_id, $time_slot_id, $room_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $conflict = $check_result->fetch_assoc()['count'] > 0;
            $check_stmt->close();
            
            if ($conflict) {
                $error_message = "This time slot and room is already occupied. Please choose a different time or room.";
            } else {
                $stmt = $conn->prepare("INSERT INTO timetable (day_id, time_slot_id, room_id, class_course_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iiii', $day_id, $time_slot_id, $room_id, $class_course_id);
                
                if ($stmt->execute()) {
                    $success_message = "Timetable entry created successfully!";
                } else {
                    $error_message = "Error creating timetable entry.";
                }
                $stmt->close();
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
}

// Get timetable entry for editing
$edit_entry = null;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("
        SELECT t.*, c.name as class_name, co.course_code, co.course_name, d.name as day_name, 
               ts.start_time, ts.end_time, r.name as room_name
        FROM timetable t
        JOIN class_courses cc ON t.class_course_id = cc.id
        JOIN classes c ON cc.class_id = c.id
        JOIN courses co ON cc.course_id = co.id
        JOIN working_days d ON t.day_id = d.id
        JOIN time_slots ts ON t.time_slot_id = ts.id
        JOIN rooms r ON t.room_id = r.id
        WHERE t.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_entry = $result->fetch_assoc();
    $stmt->close();
}

// Get filter options
$days_result = $conn->query("SELECT id, name FROM working_days WHERE is_active = 1 ORDER BY id");
$time_slots_result = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
$rooms_result = $conn->query("SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY name");
$class_courses_result = $conn->query("
    SELECT cc.id, c.name as class_name, co.course_code, co.course_name
    FROM class_courses cc
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    WHERE cc.is_active = 1
    ORDER BY c.name, co.course_code
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_entry ? 'Edit' : 'Create'; ?> Timetable Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>
                            <?php echo $edit_entry ? 'Edit' : 'Create'; ?> Timetable Entry
                        </h5>
                        <a href="view_timetable.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Timetable
                        </a>
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

                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_entry ? 'update' : 'create'; ?>">
                            <?php if ($edit_entry): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_entry['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="day_id" class="form-label">Day *</label>
                                    <select name="day_id" id="day_id" class="form-select" required>
                                        <option value="">Select day...</option>
                                        <?php while ($day = $days_result->fetch_assoc()): ?>
                                            <option value="<?php echo $day['id']; ?>" 
                                                    <?php echo ($edit_entry && $edit_entry['day_id'] == $day['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($day['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="time_slot_id" class="form-label">Time Slot *</label>
                                    <select name="time_slot_id" id="time_slot_id" class="form-select" required>
                                        <option value="">Select time slot...</option>
                                        <?php while ($slot = $time_slots_result->fetch_assoc()): ?>
                                            <option value="<?php echo $slot['id']; ?>" 
                                                    <?php echo ($edit_entry && $edit_entry['time_slot_id'] == $slot['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(substr($slot['start_time'], 0, 5) . ' - ' . substr($slot['end_time'], 0, 5)); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="room_id" class="form-label">Room *</label>
                                    <select name="room_id" id="room_id" class="form-select" required>
                                        <option value="">Select room...</option>
                                        <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                            <option value="<?php echo $room['id']; ?>" 
                                                    <?php echo ($edit_entry && $edit_entry['room_id'] == $room['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($room['name'] . ' (Capacity: ' . $room['capacity'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="class_course_id" class="form-label">Class-Course Assignment *</label>
                                    <select name="class_course_id" id="class_course_id" class="form-select" required>
                                        <option value="">Select class-course assignment...</option>
                                        <?php while ($cc = $class_courses_result->fetch_assoc()): ?>
                                            <option value="<?php echo $cc['id']; ?>" 
                                                    <?php echo ($edit_entry && $edit_entry['class_course_id'] == $cc['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cc['class_name'] . ' - ' . $cc['course_code'] . ' (' . $cc['course_name'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?php echo $edit_entry ? 'Update' : 'Create'; ?> Entry
                                </button>
                                
                                <?php if ($edit_entry): ?>
                                    <button type="button" class="btn btn-danger" onclick="deleteEntry(<?php echo $edit_entry['id']; ?>)">
                                        <i class="fas fa-trash me-2"></i>Delete Entry
                                    </button>
                                <?php endif; ?>
                                
                                <a href="view_timetable.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                        
                        <?php if ($edit_entry): ?>
                            <div class="mt-4">
                                <h6>Current Entry Details</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Day:</strong> <?php echo htmlspecialchars($edit_entry['day_name']); ?></p>
                                        <p><strong>Time:</strong> <?php echo htmlspecialchars(substr($edit_entry['start_time'], 0, 5) . ' - ' . substr($edit_entry['end_time'], 0, 5)); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Room:</strong> <?php echo htmlspecialchars($edit_entry['room_name']); ?></p>
                                        <p><strong>Class-Course:</strong> <?php echo htmlspecialchars($edit_entry['class_name'] . ' - ' . $edit_entry['course_code']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteEntry(id) {
            if (confirm('Are you sure you want to delete this timetable entry? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'update_timetable.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
