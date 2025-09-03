<?php
header('Content-Type: application/json');
include 'connect.php';

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Stream-Id, Stream-Id');
header('Access-Control-Max-Age: 86400');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$response = ['success' => false, 'message' => '', 'data' => null];

// Bring in StreamManager to resolve stream from headers/GET/session
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';
$current_stream_id = null;
if (function_exists('getStreamManager')) {
    $sm = getStreamManager();
    $current_stream_id = $sm->getCurrentStreamId();
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_timetable_data':
            $semester = $_GET['semester'] ?? 1;
            $type = $_GET['type'] ?? 'lecture';
            
            // Get timetable data from database
            $query = "SELECT 
                        t.id,
                        t.day_id,
                        t.time_slot_id,
                        d.name as day_name,
                        ts.start_time,
                        ts.end_time,
                        r.name as room_name,
                        r.building,
                        c.name as class_name,
                        co.code as course_code,
                        co.name as course_name,
                        l.name as lecturer_name
                    FROM timetable t
                    JOIN days d ON t.day_id = d.id
                    JOIN time_slots ts ON t.time_slot_id = ts.id
                    JOIN rooms r ON t.room_id = r.id
                    JOIN classes c ON t.class_id = c.id
                    JOIN courses co ON t.course_id = co.id
                    LEFT JOIN lecturers l ON t.lecturer_id = l.id
                    WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Apply stream filter via classes if available
            if (!empty($current_stream_id)) {
                $query .= " AND c.stream_id = ?";
                $params[] = (int)$current_stream_id;
                $types .= "i";
            }
            
            $stmt = $conn->prepare($query);
            if ($stmt && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $timetable_data = [];
            while ($row = $result->fetch_assoc()) {
                $day = $row['day_name'];
                $room = $row['room_name'];
                
                if (!isset($timetable_data[$day])) {
                    $timetable_data[$day] = [];
                }
                if (!isset($timetable_data[$day][$room])) {
                    $timetable_data[$day][$room] = [];
                }
                
                $timetable_data[$day][$room][] = [
                    'course' => [
                        'id' => $row['id'],
                        'code' => $row['course_code'],
                        'name' => $row['course_name'],
                        'lecturer_name' => $row['lecturer_name'],
                        'color' => 'bg-blue-100 text-blue-800', // Default color
                        'classes' => [$row['class_name']]
                    ],
                    'start_time' => $row['time_slot_id'] - 1, // Assuming time slots start from 1
                    'spans' => 1 // Default to 1 hour
                ];
            }
            
            $response['success'] = true;
            $response['data'] = $timetable_data;
            break;
            
        case 'save_course':
            $day = $_POST['day'] ?? '';
            $room = $_POST['room'] ?? '';
            $course_code = $_POST['course_code'] ?? '';
            $start_time = $_POST['start_time'] ?? 0;
            $classes = $_POST['classes'] ?? [];
            $lecturer_name = $_POST['lecturer_name'] ?? 'Dr. Smith';
            
            if (empty($day) || empty($room) || empty($course_code) || empty($classes)) {
                throw new Exception('Missing required fields');
            }
            
            // Get day_id
            $stmt = $conn->prepare("SELECT id FROM days WHERE name = ?");
            $stmt->bind_param("s", $day);
            $stmt->execute();
            $day_result = $stmt->get_result();
            $day_id = $day_result->fetch_assoc()['id'] ?? 0;
            
            // Get room_id
            $stmt = $conn->prepare("SELECT id FROM rooms WHERE name = ?");
            $stmt->bind_param("s", $room);
            $stmt->execute();
            $room_result = $stmt->get_result();
            $room_id = $room_result->fetch_assoc()['id'] ?? 0;
            
            // Get course_id
            $stmt = $conn->prepare("SELECT id FROM courses WHERE code = ?");
            $stmt->bind_param("s", $course_code);
            $stmt->execute();
            $course_result = $stmt->get_result();
            $course_id = $course_result->fetch_assoc()['id'] ?? 0;
            
            // Get lecturer_id
            $stmt = $conn->prepare("SELECT id FROM lecturers WHERE name = ?");
            $stmt->bind_param("s", $lecturer_name);
            $stmt->execute();
            $lecturer_result = $stmt->get_result();
            $lecturer_id = $lecturer_result->fetch_assoc()['id'] ?? 0;
            
            // Get class_id (use first class)
            $stmt = $conn->prepare("SELECT id FROM classes WHERE name = ?");
            $stmt->bind_param("s", $classes[0]);
            $stmt->execute();
            $class_result = $stmt->get_result();
            $class_id = $class_result->fetch_assoc()['id'] ?? 0;
            
            if (!$day_id || !$room_id || !$course_id || !$class_id) {
                throw new Exception('Invalid day, room, course, or class');
            }
            
            // Check for conflicts
            $stmt = $conn->prepare("SELECT id FROM timetable WHERE day_id = ? AND room_id = ? AND time_slot_id = ?");
            $stmt->bind_param("iii", $day_id, $room_id, $start_time + 1);
            $stmt->execute();
            $conflict_result = $stmt->get_result();
            
            if ($conflict_result->num_rows > 0) {
                throw new Exception('Time slot already occupied');
            }
            
            // Insert new timetable entry
            $stmt = $conn->prepare("INSERT INTO timetable (day_id, room_id, time_slot_id, class_id, course_id, lecturer_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiiii", $day_id, $room_id, $start_time + 1, $class_id, $course_id, $lecturer_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Course saved successfully';
                $response['data'] = ['id' => $conn->insert_id];
            } else {
                throw new Exception('Failed to save course');
            }
            break;
            
        case 'delete_course':
            $day = $_POST['day'] ?? '';
            $room = $_POST['room'] ?? '';
            $start_time = $_POST['start_time'] ?? 0;
            
            if (empty($day) || empty($room)) {
                throw new Exception('Missing required fields');
            }
            
            // Get day_id and room_id
            $stmt = $conn->prepare("SELECT d.id as day_id, r.id as room_id FROM days d, rooms r WHERE d.name = ? AND r.name = ?");
            $stmt->bind_param("ss", $day, $room);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if (!$row) {
                throw new Exception('Invalid day or room');
            }
            
            // Delete timetable entry
            $stmt = $conn->prepare("DELETE FROM timetable WHERE day_id = ? AND room_id = ? AND time_slot_id = ?");
            $stmt->bind_param("iii", $row['day_id'], $row['room_id'], $start_time + 1);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Course deleted successfully';
            } else {
                throw new Exception('Failed to delete course');
            }
            break;
            
        case 'get_sessions':
            $stmt = $conn->prepare("SELECT id, name, academic_year FROM sessions ORDER BY academic_year DESC, name ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sessions = [];
            while ($row = $result->fetch_assoc()) {
                $sessions[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $sessions;
            break;
            
        case 'get_rooms':
            $stmt = $conn->prepare("SELECT id, name, building, capacity, room_type FROM rooms ORDER BY building, name");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $rooms = [];
            while ($row = $result->fetch_assoc()) {
                $rooms[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $rooms;
            break;
            
        case 'get_time_slots':
            $stmt = $conn->prepare("SELECT id, start_time, end_time FROM time_slots ORDER BY start_time");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $time_slots = [];
            while ($row = $result->fetch_assoc()) {
                $time_slots[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $time_slots;
            break;
            
        case 'get_days':
            $stmt = $conn->prepare("SELECT id, name FROM days ORDER BY id");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $days = [];
            while ($row = $result->fetch_assoc()) {
                $days[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $days;
            break;
            
        case 'get_courses':
            $stmt = $conn->prepare("SELECT id, code, name FROM courses WHERE is_active = 1 ORDER BY code");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $courses;
            break;
            
        case 'get_classes':
            $session_id = $_GET['session_id'] ?? 0;
            
            $query = "SELECT id, name FROM classes WHERE is_active = 1";
            $params = [];
            $types = "";
            
            if ($session_id > 0) {
                $query .= " AND session_id = ?";
                $params[] = $session_id;
                $types .= "i";
            }
            
            $query .= " ORDER BY name";
            
            $stmt = $conn->prepare($query);
            if ($stmt && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $classes = [];
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $classes;
            break;
            
        case 'get_lecturers':
            $stmt = $conn->prepare("SELECT id, name FROM lecturers WHERE is_active = 1 ORDER BY name");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $lecturers = [];
            while ($row = $result->fetch_assoc()) {
                $lecturers[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $lecturers;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} catch (Error $e) {
    $response['message'] = 'System error: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>
