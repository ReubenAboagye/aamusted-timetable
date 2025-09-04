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

        // Validate values
        if ($day_id < 1 || $day_id > 5) {
            throw new Exception('Invalid day ID');
        }
        if ($time_slot_id < 1 || $time_slot_id > 8) {
            throw new Exception('Invalid time slot ID');
        }
        if ($room_id < 1 || $room_id > 4) {
            throw new Exception('Invalid room ID');
        }
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

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
