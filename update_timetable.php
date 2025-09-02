<?php
include 'connect.php';

// Page title and layout includes
$pageTitle = 'Update Timetable Entry';
include 'includes/header.php';

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
                $stmt->close();
                redirect_with_flash('update_timetable.php', 'success', 'Timetable entry deleted successfully!');
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
        $lecturer_course_id = (int)($_POST['lecturer_course_id'] ?? 0);
        
        if ($id > 0 && $day_id > 0 && $time_slot_id > 0 && $room_id > 0 && $class_course_id > 0) {
            // Check for conflicts (same day, time, room but different entry)
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
                $stmt = $conn->prepare("UPDATE timetable SET day_id = ?, time_slot_id = ?, room_id = ?, class_course_id = ?, lecturer_course_id = ? WHERE id = ?");
                $stmt->bind_param('iiiiii', $day_id, $time_slot_id, $room_id, $class_course_id, $lecturer_course_id, $id);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    redirect_with_flash('update_timetable.php?id=' . $id, 'success', 'Timetable entry updated successfully!');
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
        $lecturer_course_id = (int)($_POST['lecturer_course_id'] ?? 0);
        
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
                $stmt = $conn->prepare("INSERT INTO timetable (day_id, time_slot_id, room_id, class_course_id, lecturer_course_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('iiiii', $day_id, $time_slot_id, $room_id, $class_course_id, $lecturer_course_id);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    redirect_with_flash('update_timetable.php', 'success', 'Timetable entry created successfully!');
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

    // Detect whether timetable stores class_course_id and lecturer_course_id (newer schema)
    $tcol_check_class_course = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
    $has_t_class_course = ($tcol_check_class_course && $tcol_check_class_course->num_rows > 0);
    if ($tcol_check_class_course) $tcol_check_class_course->close();

    $tcol_check_lecturer_course = $conn->query("SHOW COLUMNS FROM timetable LIKE 'lecturer_course_id'");
    $has_t_lecturer_course = ($tcol_check_lecturer_course && $tcol_check_lecturer_course->num_rows > 0);
    if ($tcol_check_lecturer_course) $tcol_check_lecturer_course->close();

    // Build SELECT depending on detected schema to avoid referencing missing columns in ON clauses
    $select_fields = "t.*, c.name as class_name, co.code as course_code, co.name as course_name, d.name as day_name, ts.start_time, ts.end_time, r.name as room_name, l.name as lecturer_name";
    $from_clause = "FROM timetable t";

    if ($has_t_class_course) {
        $join_clause = "JOIN class_courses cc ON t.class_course_id = cc.id\n        JOIN classes c ON cc.class_id = c.id\n        JOIN courses co ON cc.course_id = co.id";
    } else {
        // older schema: timetable stores class_id and course_id directly
        $join_clause = "JOIN classes c ON t.class_id = c.id\n        JOIN courses co ON t.course_id = co.id";
    }

    $join_clause .= "\n        JOIN days d ON t.day_id = d.id\n        JOIN time_slots ts ON t.time_slot_id = ts.id\n        JOIN rooms r ON t.room_id = r.id";

    if ($has_t_lecturer_course) {
        $join_clause .= "\n        LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id\n        LEFT JOIN lecturers l ON lc.lecturer_id = l.id";
    } else {
        $join_clause .= "\n        LEFT JOIN lecturers l ON t.lecturer_id = l.id";
    }

    $select_sql = "SELECT " . $select_fields . "\n        " . $from_clause . "\n        " . $join_clause . "\n        WHERE t.id = ?";

    $stmt = $conn->prepare($select_sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_entry = $result->fetch_assoc();
    $stmt->close();

    // Ensure form compatibility: if older schema, provide null lecturer_course_id
    if (!$has_t_lecturer_course && $edit_entry) {
        $edit_entry['lecturer_course_id'] = $edit_entry['lecturer_course_id'] ?? null;
    }
}

// Get filter options
$days_result = $conn->query("SELECT id, name FROM days ORDER BY id");
$time_slots_result = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
$rooms_result = $conn->query("SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY name");
$class_courses_result = $conn->query("
    SELECT cc.id, c.name as class_name, co.code as course_code, co.name as course_name
    FROM class_courses cc
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    WHERE cc.is_active = 1
    ORDER BY c.name, co.code
");
$lecturer_courses_result = $conn->query("
    SELECT lc.id, l.name as lecturer_name, co.code as course_code, co.name as course_name
    FROM lecturer_courses lc
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN courses co ON lc.course_id = co.id
    WHERE lc.is_active = 1
    ORDER BY l.name, co.code
");
?>

<!-- Bootstrap CSS and JS are included globally in includes/header.php -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }
    .form-section {
        background-color: #f8f9fa;
        border-radius: 0.375rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .form-section h6 {
        color: #495057;
        margin-bottom: 1rem;
    }
    .current-info {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border: 1px solid #2196f3;
        border-radius: 0.375rem;
        padding: 1rem;
    }
    .time-slot-display {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 0.375rem;
        padding: 0.75rem;
        text-align: center;
        font-weight: bold;
        color: #856404;
    }
    .btn-action {
        min-width: 120px;
    }
</style>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
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

                    <?php if ($edit_entry): ?>
                        <!-- Current Entry Information -->
                        <div class="current-info mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-info-circle me-2"></i>Current Entry Details
                            </h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Day:</strong><br>
                                    <span class="text-primary"><?php echo htmlspecialchars($edit_entry['day_name']); ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Time:</strong><br>
                                    <span class="text-primary">
                                        <?php echo htmlspecialchars(substr($edit_entry['start_time'], 0, 5) . ' - ' . substr($edit_entry['end_time'], 0, 5)); ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Room:</strong><br>
                                    <span class="text-primary"><?php echo htmlspecialchars($edit_entry['room_name']); ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Class-Course:</strong><br>
                                    <span class="text-primary">
                                        <?php echo htmlspecialchars($edit_entry['class_name'] . ' - ' . $edit_entry['course_code']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="timetableForm">
                        <input type="hidden" name="action" value="<?php echo $edit_entry ? 'update' : 'create'; ?>">
                        <?php if ($edit_entry): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_entry['id']; ?>">
                        <?php endif; ?>
                        
                        <!-- Time and Day Section -->
                        <div class="form-section">
                            <h6><i class="fas fa-clock me-2"></i>Schedule Settings</h6>
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
                                    <label for="time_slot_id" class="form-label">Time Period *</label>
                                    <select name="time_slot_id" id="time_slot_id" class="form-select" required>
                                        <option value="">Select time period...</option>
                                        <?php while ($slot = $time_slots_result->fetch_assoc()): ?>
                                            <option value="<?php echo $slot['id']; ?>" 
                                                    <?php echo ($edit_entry && $edit_entry['time_slot_id'] == $slot['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(substr($slot['start_time'], 0, 5) . ' - ' . substr($slot['end_time'], 0, 5)); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Location and Assignment Section -->
                        <div class="form-section">
                            <h6><i class="fas fa-map-marker-alt me-2"></i>Location & Assignment</h6>
                            <div class="row g-3">
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
                        </div>

                        <!-- Lecturer Assignment Section -->
                        <div class="form-section">
                            <h6><i class="fas fa-user-tie me-2"></i>Lecturer Assignment (Optional)</h6>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="lecturer_course_id" class="form-label">Lecturer-Course Assignment</label>
                                    <select name="lecturer_course_id" id="lecturer_course_id" class="form-select">
                                        <option value="">Select lecturer-course assignment...</option>
                                        <?php while ($lc = $lecturer_courses_result->fetch_assoc()): ?>
                                            <option value="<?php echo $lc['id']; ?>" 
                                                    <?php echo ($edit_entry && $edit_entry['lecturer_course_id'] == $lc['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lc['lecturer_name'] . ' - ' . $lc['course_code'] . ' (' . $lc['course_name'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <div class="form-text">Leave empty if no lecturer is assigned to this course</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-3 justify-content-center mt-4">
                            <button type="submit" class="btn btn-primary btn-action">
                                <i class="fas fa-save me-2"></i>
                                <?php echo $edit_entry ? 'Update' : 'Create'; ?> Entry
                            </button>
                            
                            <?php if ($edit_entry): ?>
                                <button type="button" class="btn btn-danger btn-action" onclick="deleteEntry(<?php echo $edit_entry['id']; ?>)">
                                    <i class="fas fa-trash me-2"></i>Delete Entry
                                </button>
                            <?php endif; ?>
                            
                            <a href="view_timetable.php" class="btn btn-outline-secondary btn-action">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Form validation
    document.getElementById('timetableForm').addEventListener('submit', function(e) {
        const dayId = document.getElementById('day_id').value;
        const timeSlotId = document.getElementById('time_slot_id').value;
        const roomId = document.getElementById('room_id').value;
        const classCourseId = document.getElementById('class_course_id').value;
        
        if (!dayId || !timeSlotId || !roomId || !classCourseId) {
            e.preventDefault();
            alert('Please fill in all required fields marked with *');
            return false;
        }
    });

    // Auto-update time slot display when changed
    document.getElementById('time_slot_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const timeText = selectedOption.text;
            // You can add additional logic here to show the selected time period
        }
    });

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
