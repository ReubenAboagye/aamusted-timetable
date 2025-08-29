<?php
// Update Timetable Entry - Handle AJAX requests for CRUD operations

header('Content-Type: application/json');
include 'connect.php';

// Get action from POST
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createTimetableEntry($conn);
            break;
        case 'update':
            updateTimetableEntry($conn);
            break;
        case 'delete':
            deleteTimetableEntry($conn);
            break;
        default:
            throw new Exception('Invalid action specified');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function createTimetableEntry($conn) {
    // Validate required fields
    $required_fields = ['session_id', 'day_id', 'time_slot', 'room_id', 'class_id', 'course_id', 'lecturer_id', 'session_type_id', 'course_hours'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $session_id = intval($_POST['session_id']);
    $day_id = intval($_POST['day_id']);
    $time_slot = intval($_POST['time_slot']);
    $room_id = intval($_POST['room_id']);
    $class_id = intval($_POST['class_id']);
    $course_id = intval($_POST['course_id']);
    $lecturer_id = intval($_POST['lecturer_id']);
    $session_type_id = intval($_POST['session_type_id']);
    $course_hours = intval($_POST['course_hours']);
    
    // Check if class_course mapping exists, create if not
    $class_course_id = getOrCreateClassCourse($conn, $class_id, $course_id, $session_id);
    
    // Check if lecturer_course mapping exists, create if not
    $lecturer_course_id = getOrCreateLecturerCourse($conn, $lecturer_id, $course_id);
    
    // Check for conflicts (same time slot, room, or lecturer)
    checkConflicts($conn, $session_id, $day_id, $time_slot, $room_id, $lecturer_id, $course_hours);
    
    // Insert new timetable entry
    $stmt = $conn->prepare("
        INSERT INTO timetable (session_id, class_course_id, lecturer_course_id, day_id, room_id, session_type_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param('iiiiii', $session_id, $class_course_id, $lecturer_course_id, $day_id, $room_id, $session_type_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database insert error: ' . $stmt->error);
    }
    
    $timetable_id = $conn->insert_id;
    
    // Update the course hours in the courses table (only if the column exists)
    try {
        $update_course_stmt = $conn->prepare("UPDATE courses SET hours = ? WHERE id = ?");
        if ($update_course_stmt) {
            $update_course_stmt->bind_param('ii', $course_hours, $course_id);
            $update_course_stmt->execute();
            $update_course_stmt->close();
        }
    } catch (Exception $e) {
        // If the hours column doesn't exist, we'll ignore this error
        // The column will need to be added manually
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timetable entry created successfully',
        'id' => $timetable_id
    ]);
}

function updateTimetableEntry($conn) {
    // Validate required fields
    $required_fields = ['timetable_id', 'session_id', 'day_id', 'time_slot', 'room_id', 'class_id', 'course_id', 'lecturer_id', 'session_type_id', 'course_hours'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $timetable_id = intval($_POST['timetable_id']);
    $session_id = intval($_POST['session_id']);
    $day_id = intval($_POST['day_id']);
    $time_slot = intval($_POST['time_slot']);
    $room_id = intval($_POST['room_id']);
    $class_id = intval($_POST['class_id']);
    $course_id = intval($_POST['course_id']);
    $lecturer_id = intval($_POST['lecturer_id']);
    $session_type_id = intval($_POST['session_type_id']);
    $course_hours = intval($_POST['course_hours']);
    
    // Check if timetable entry exists
    $stmt = $conn->prepare("SELECT id FROM timetable WHERE id = ?");
    $stmt->bind_param('i', $timetable_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Timetable entry not found');
    }
    $stmt->close();
    
    // Check if class_course mapping exists, create if not
    $class_course_id = getOrCreateClassCourse($conn, $class_id, $course_id, $session_id);
    
    // Check if lecturer_course mapping exists, create if not
    $lecturer_course_id = getOrCreateLecturerCourse($conn, $lecturer_id, $course_id);
    
    // Check for conflicts (excluding current entry)
    checkConflicts($conn, $session_id, $day_id, $time_slot, $room_id, $lecturer_id, $course_hours, $timetable_id);
    
    // Update timetable entry
    $stmt = $conn->prepare("
        UPDATE timetable 
        SET class_course_id = ?, lecturer_course_id = ?, day_id = ?, room_id = ?, session_type_id = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param('iiiiii', $class_course_id, $lecturer_course_id, $day_id, $room_id, $session_type_id, $timetable_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database update error: ' . $stmt->error);
    }
    
    $stmt->close();
    
    // Update the course hours in the courses table (only if the column exists)
    try {
        $update_course_stmt = $conn->prepare("UPDATE courses SET hours = ? WHERE id = ?");
        if ($update_course_stmt) {
            $update_course_stmt->bind_param('ii', $course_hours, $course_id);
            $update_course_stmt->execute();
            $update_course_stmt->close();
        }
    } catch (Exception $e) {
        // If the hours column doesn't exist, we'll ignore this error
        // The column will need to be added manually
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Timetable entry updated successfully'
    ]);
}

function deleteTimetableEntry($conn) {
    if (empty($_POST['timetable_id'])) {
        throw new Exception('Missing timetable ID');
    }
    
    $timetable_id = intval($_POST['timetable_id']);
    
    // Delete timetable entry
    $stmt = $conn->prepare("DELETE FROM timetable WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $timetable_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database delete error: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Timetable entry not found');
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timetable entry deleted successfully'
    ]);
}

function getOrCreateClassCourse($conn, $class_id, $course_id, $session_id) {
    // Check if mapping exists
    $stmt = $conn->prepare("SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND session_id = ?");
    $stmt->bind_param('iii', $class_id, $course_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    }
    
    $stmt->close();
    
    // Create new mapping
    $stmt = $conn->prepare("INSERT INTO class_courses (class_id, course_id, session_id) VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $class_id, $course_id, $session_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create class-course mapping: ' . $stmt->error);
    }
    
    $id = $conn->insert_id;
    $stmt->close();
    
    return $id;
}

function getOrCreateLecturerCourse($conn, $lecturer_id, $course_id) {
    // Check if mapping exists
    $stmt = $conn->prepare("SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ?");
    $stmt->bind_param('ii', $lecturer_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id'];
    }
    
    $stmt->close();
    
    // Create new mapping
    $stmt = $conn->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $lecturer_id, $course_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create lecturer-course mapping: ' . $stmt->error);
    }
    
    $id = $conn->insert_id;
    $stmt->close();
    
    return $id;
}

function checkConflicts($conn, $session_id, $day_id, $time_slot, $room_id, $lecturer_id, $course_hours = 1, $exclude_id = null) {
    // Check room conflict for all time slots that this course will occupy
    for ($i = 0; $i < $course_hours; $i++) {
        $check_time_slot = $time_slot + $i;
        
        $room_conflict_sql = "SELECT id FROM timetable WHERE session_id = ? AND day_id = ? AND room_id = ?";
        $room_conflict_params = [$session_id, $day_id, $room_id];
        $room_conflict_types = 'iii';
        
        if ($exclude_id) {
            $room_conflict_sql .= " AND id != ?";
            $room_conflict_params[] = $exclude_id;
            $room_conflict_types .= 'i';
        }
        
        $stmt = $conn->prepare($room_conflict_sql);
        $stmt->bind_param($room_conflict_types, ...$room_conflict_params);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            throw new Exception("Room is already occupied at time slot " . ($check_time_slot + 8) . ":00");
        }
        $stmt->close();
    }
    
    // Check lecturer conflict for all time slots that this course will occupy
    for ($i = 0; $i < $course_hours; $i++) {
        $check_time_slot = $time_slot + $i;
        
        $lecturer_conflict_sql = "
            SELECT t.id FROM timetable t 
            JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id 
            WHERE t.session_id = ? AND t.day_id = ? AND lc.lecturer_id = ?
        ";
        $lecturer_conflict_params = [$session_id, $day_id, $lecturer_id];
        
        if ($exclude_id) {
            $lecturer_conflict_sql .= " AND t.id != ?";
            $lecturer_conflict_params[] = $exclude_id;
        }
        
        $stmt = $conn->prepare($lecturer_conflict_sql);
        $stmt->bind_param('iii', ...$lecturer_conflict_params);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            throw new Exception("Lecturer is already teaching at time slot " . ($check_time_slot + 8) . ":00");
        }
        $stmt->close();
    }
}
?>
