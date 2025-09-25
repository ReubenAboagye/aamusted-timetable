<?php
require_once 'connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Get stream ID from session (always use current stream)
    $stream_id = isset($_SESSION['current_stream_id']) ? (int)$_SESSION['current_stream_id'] : 1;
    $day_id = isset($_GET['day_id']) ? (int)$_GET['day_id'] : null;
    
    
    // Base query to get time slots for the current stream with availability checks
    $query = "
        SELECT 
            ts.id, 
            ts.start_time, 
            ts.end_time, 
            ts.duration, 
            ts.is_break, 
            ts.is_mandatory,
            CASE 
                WHEN ? IS NOT NULL THEN (
                    -- Check if there are available rooms AND no lecturer conflicts
                    SELECT COUNT(*) = 0 
                    FROM timetable t 
                    JOIN class_courses cc ON t.class_course_id = cc.id
                    JOIN classes c ON cc.class_id = c.id
                    WHERE t.day_id = ? 
                    AND t.time_slot_id = ts.id 
                    AND c.stream_id = ?
                )
                ELSE 1
            END as is_available,
            CASE 
                WHEN ? IS NOT NULL THEN (
                    -- Count available rooms for this time slot
                    SELECT COUNT(*) 
                    FROM rooms r
                    WHERE r.is_active = 1
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM timetable t 
                        JOIN class_courses cc ON t.class_course_id = cc.id
                        JOIN classes c ON cc.class_id = c.id
                        WHERE t.room_id = r.id 
                        AND t.day_id = ? 
                        AND t.time_slot_id = ts.id 
                        AND c.stream_id = ?
                    )
                )
                ELSE 0
            END as available_rooms_count
        FROM time_slots ts
        LEFT JOIN stream_time_slots sts ON ts.id = sts.time_slot_id AND sts.stream_id = ? AND sts.is_active = 1
        WHERE (ts.is_mandatory = 1 OR sts.stream_id IS NOT NULL)
        AND ts.is_break = 0
        ORDER BY ts.start_time
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param('iiiiiii', $day_id, $day_id, $stream_id, $day_id, $day_id, $stream_id, $stream_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $time_slots = [];
    while ($row = $result->fetch_assoc()) {
        $time_slots[] = [
            'id' => (int)$row['id'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'duration' => (int)$row['duration'],
            'is_break' => (bool)$row['is_break'],
            'is_mandatory' => (bool)$row['is_mandatory'],
            'is_available' => (bool)$row['is_available'],
            'available_rooms_count' => (int)$row['available_rooms_count']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $time_slots
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch time slots: ' . $e->getMessage()
    ]);
}

$conn->close();
?>