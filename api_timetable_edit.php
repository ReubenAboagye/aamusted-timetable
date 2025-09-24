<?php
header('Content-Type: application/json');
include 'connect.php';

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_timetable_entry') {
        // Validate required fields
        $required_fields = ['entry_id', 'day_id', 'time_slot_id', 'room_id'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $entry_id = intval($_POST['entry_id']);
        $day_id = intval($_POST['day_id']);
        $time_slot_id = intval($_POST['time_slot_id']);
        $room_id = intval($_POST['room_id']);
        $hours_per_week = intval($_POST['hours_per_week'] ?? 1); // Keep for reference but don't validate

        // Validate that the entry exists
        $check_entry_sql = "SELECT id FROM timetable WHERE id = ?";
        $check_entry_stmt = $conn->prepare($check_entry_sql);
        $check_entry_stmt->bind_param("i", $entry_id);
        $check_entry_stmt->execute();
        $entry_result = $check_entry_stmt->get_result();
        if ($entry_result->num_rows === 0) {
            throw new Exception('Timetable entry not found');
        }
        $check_entry_stmt->close();

        // Validate day exists
        $check_day_sql = "SELECT id FROM days WHERE id = ?";
        $check_day_stmt = $conn->prepare($check_day_sql);
        $check_day_stmt->bind_param("i", $day_id);
        $check_day_stmt->execute();
        $day_result = $check_day_stmt->get_result();
        if ($day_result->num_rows === 0) {
            throw new Exception('Invalid day ID');
        }
        $check_day_stmt->close();

        // Validate time slot exists
        $check_timeslot_sql = "SELECT id FROM time_slots WHERE id = ?";
        $check_timeslot_stmt = $conn->prepare($check_timeslot_sql);
        $check_timeslot_stmt->bind_param("i", $time_slot_id);
        $check_timeslot_stmt->execute();
        $timeslot_result = $check_timeslot_stmt->get_result();
        if ($timeslot_result->num_rows === 0) {
            throw new Exception('Invalid time slot ID');
        }
        $check_timeslot_stmt->close();

        // Validate room exists and is active
        $check_room_sql = "SELECT id FROM rooms WHERE id = ? AND is_active = 1";
        $check_room_stmt = $conn->prepare($check_room_sql);
        $check_room_stmt->bind_param("i", $room_id);
        $check_room_stmt->execute();
        $room_result = $check_room_stmt->get_result();
        if ($room_result->num_rows === 0) {
            throw new Exception('Invalid room ID or room is not active');
        }
        $check_room_stmt->close();
        // Duration is read-only, no need to validate

        // Check if the new slot is available (no conflicts)
        $check_conflict_sql = "
            SELECT COUNT(*) as count 
            FROM timetable 
            WHERE day_id = ? 
            AND time_slot_id = ? 
            AND room_id = ? 
            AND id != ?
        ";
        $check_stmt = $conn->prepare($check_conflict_sql);
        $check_stmt->bind_param("iiii", $day_id, $time_slot_id, $room_id, $entry_id);
        $check_stmt->execute();
        $conflict_result = $check_stmt->get_result();
        $conflict_count = $conflict_result->fetch_assoc()['count'];
        $check_stmt->close();

        if ($conflict_count > 0) {
            throw new Exception('The selected time slot and room combination is already occupied');
        }

        // Handle lecturer selection if provided
        $lecturer_course_id = isset($_POST['lecturer_course_id']) ? intval($_POST['lecturer_course_id']) : null;
        
        // Update the timetable entry
        $update_sql = "
            UPDATE timetable 
            SET day_id = ?, time_slot_id = ?, room_id = ?" . 
            ($lecturer_course_id ? ", lecturer_course_id = ?" : "") . "
            WHERE id = ?
        ";
        $update_stmt = $conn->prepare($update_sql);
        
        if ($lecturer_course_id) {
            $update_stmt->bind_param("iiiii", $day_id, $time_slot_id, $room_id, $lecturer_course_id, $entry_id);
        } else {
            $update_stmt->bind_param("iiii", $day_id, $time_slot_id, $room_id, $entry_id);
        }
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update timetable entry: ' . $update_stmt->error);
        }

        // Course duration is read-only, no updates needed

        $response['success'] = true;
        $response['message'] = 'Course schedule updated successfully';

    } elseif ($action === 'bulk_update_timetable_entries') {
        // Handle bulk updates from detailed editor
        $input = json_decode(file_get_contents('php://input'), true);
        $changes = $input['changes'] ?? [];
        
        if (empty($changes)) {
            throw new Exception('No changes provided');
        }
        
        $conn->begin_transaction();
        
        try {
            $update_sql = "UPDATE timetable SET day_id = ?, time_slot_id = ?, room_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($changes as $change) {
                $entry_id = intval($change['id']);
                $day_id = intval($change['day_id']);
                $time_slot_id = intval($change['time_slot_id']);
                $room_id = intval($change['room_id']);
                
                // Validate each entry
                $check_entry_sql = "SELECT id FROM timetable WHERE id = ?";
                $check_stmt = $conn->prepare($check_entry_sql);
                $check_stmt->bind_param("i", $entry_id);
                $check_stmt->execute();
                $entry_result = $check_stmt->get_result();
                
                if ($entry_result->num_rows === 0) {
                    $error_count++;
                    $check_stmt->close();
                    continue;
                }
                $check_stmt->close();
                
                // Check for conflicts
                $check_conflict_sql = "
                    SELECT COUNT(*) as count 
                    FROM timetable 
                    WHERE day_id = ? 
                    AND time_slot_id = ? 
                    AND room_id = ? 
                    AND id != ?
                ";
                $check_conflict_stmt = $conn->prepare($check_conflict_sql);
                $check_conflict_stmt->bind_param("iiii", $day_id, $time_slot_id, $room_id, $entry_id);
                $check_conflict_stmt->execute();
                $conflict_result = $check_conflict_stmt->get_result();
                $conflict_count = $conflict_result->fetch_assoc()['count'];
                $check_conflict_stmt->close();
                
                if ($conflict_count > 0) {
                    $error_count++;
                    continue;
                }
                
                // Update the entry
                $update_stmt->bind_param("iiii", $day_id, $time_slot_id, $room_id, $entry_id);
                if ($update_stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            $update_stmt->close();
            $conn->commit();
            
            if ($error_count > 0) {
                $response['success'] = true;
                $response['message'] = "Updated $success_count entries successfully. $error_count entries failed to update.";
            } else {
                $response['success'] = true;
                $response['message'] = "All $success_count entries updated successfully.";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } elseif ($action === 'add_manual_timetable_entry') {
        // Validate required fields
        $required_fields = ['class_course_id', 'day_id', 'time_slot_id', 'room_id', 'semester'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $class_course_id = intval($_POST['class_course_id']);
        $day_id = intval($_POST['day_id']);
        $time_slot_id = intval($_POST['time_slot_id']);
        $room_id = intval($_POST['room_id']);
        $semester = intval($_POST['semester']);
        $lecturer_course_id = isset($_POST['lecturer_course_id']) ? intval($_POST['lecturer_course_id']) : null;

        // Validate that the class course exists
        $check_class_course_sql = "SELECT id FROM class_courses WHERE id = ? AND is_active = 1";
        $check_class_course_stmt = $conn->prepare($check_class_course_sql);
        $check_class_course_stmt->bind_param("i", $class_course_id);
        $check_class_course_stmt->execute();
        $class_course_result = $check_class_course_stmt->get_result();
        if ($class_course_result->num_rows === 0) {
            throw new Exception('Class course not found or inactive');
        }
        $check_class_course_stmt->close();

        // Validate day exists
        $check_day_sql = "SELECT id FROM days WHERE id = ?";
        $check_day_stmt = $conn->prepare($check_day_sql);
        $check_day_stmt->bind_param("i", $day_id);
        $check_day_stmt->execute();
        $day_result = $check_day_stmt->get_result();
        if ($day_result->num_rows === 0) {
            throw new Exception('Invalid day selected');
        }
        $check_day_stmt->close();

        // Validate time slot exists
        $check_time_slot_sql = "SELECT id FROM time_slots WHERE id = ? AND is_active = 1";
        $check_time_slot_stmt = $conn->prepare($check_time_slot_sql);
        $check_time_slot_stmt->bind_param("i", $time_slot_id);
        $check_time_slot_stmt->execute();
        $time_slot_result = $check_time_slot_stmt->get_result();
        if ($time_slot_result->num_rows === 0) {
            throw new Exception('Invalid time slot selected');
        }
        $check_time_slot_stmt->close();

        // Validate room exists
        $check_room_sql = "SELECT id FROM rooms WHERE id = ? AND is_active = 1";
        $check_room_stmt = $conn->prepare($check_room_sql);
        $check_room_stmt->bind_param("i", $room_id);
        $check_room_stmt->execute();
        $room_result = $check_room_stmt->get_result();
        if ($room_result->num_rows === 0) {
            throw new Exception('Invalid room selected');
        }
        $check_room_stmt->close();

        // Check if the slot is available (no conflicts)
        $check_conflict_sql = "
            SELECT COUNT(*) as count 
            FROM timetable 
            WHERE day_id = ? 
            AND time_slot_id = ? 
            AND room_id = ?
        ";
        $check_stmt = $conn->prepare($check_conflict_sql);
        $check_stmt->bind_param("iii", $day_id, $time_slot_id, $room_id);
        $check_stmt->execute();
        $conflict_result = $check_stmt->get_result();
        $conflict_count = $conflict_result->fetch_assoc()['count'];
        $check_stmt->close();

        if ($conflict_count > 0) {
            throw new Exception('The selected time slot and room combination is already occupied');
        }

        // Insert the new timetable entry
        $insert_sql = "
            INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, semester, academic_year, timetable_type, division_label, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, YEAR(CURDATE()), 'lecture', '', NOW())
        ";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiiiii", $class_course_id, $lecturer_course_id, $day_id, $time_slot_id, $room_id, $semester);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to add timetable entry: ' . $insert_stmt->error);
        }

        $response['success'] = true;
        $response['message'] = 'Course added to timetable successfully';

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
