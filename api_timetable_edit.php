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

        // Update the timetable entry
        $update_sql = "
            UPDATE timetable 
            SET day_id = ?, time_slot_id = ?, room_id = ? 
            WHERE id = ?
        ";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("iiii", $day_id, $time_slot_id, $room_id, $entry_id);
        
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

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
