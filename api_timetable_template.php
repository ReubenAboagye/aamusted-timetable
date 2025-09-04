<?php
header('Content-Type: application/json');
include 'connect.php';

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_timetable_data':
            $session_id = $_GET['session_id'] ?? 0;
            $semester = $_GET['semester'] ?? 1;
            $type = $_GET['type'] ?? 'lecture';
            
            // Get timetable data from database using new schema
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
                        t.division_label,
                        co.code as course_code,
                        co.name as course_name,
                        l.name as lecturer_name
                    FROM timetable t
                    JOIN class_courses cc ON t.class_course_id = cc.id
                    JOIN classes c ON cc.class_id = c.id
                    JOIN courses co ON cc.course_id = co.id
                    JOIN days d ON t.day_id = d.id
                    JOIN time_slots ts ON t.time_slot_id = ts.id
                    JOIN rooms r ON t.room_id = r.id
                    LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                    LEFT JOIN lecturers l ON lc.lecturer_id = l.id
                    WHERE 1=1";
            
            $params = [];
            $types = "";
            
            if ($session_id > 0) {
                $query .= " AND c.session_id = ?";
                $params[] = $session_id;
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
                        'classes' => [$row['class_name'] . ($row['division_label'] ? ' ' . $row['division_label'] : '')]
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
            
        case 'generate_timetable':
            $session_id = $_POST['session_id'] ?? 0;
            $semester = $_POST['semester'] ?? 1;
            $type = $_POST['type'] ?? 'lecture';
            $exclusions_json = $_POST['exclusions'] ?? '[]';
            $exclusions = json_decode($exclusions_json, true);
            
            if (!$session_id) {
                throw new Exception('Session ID is required');
            }
            
            // Get session info
            $stmt = $conn->prepare("SELECT name, academic_year FROM sessions WHERE id = ?");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            $session_result = $stmt->get_result();
            $session = $session_result->fetch_assoc();
            
            if (!$session) {
                throw new Exception('Invalid session');
            }
            
            // Get classes for this session
            $stmt = $conn->prepare("SELECT id, name FROM classes WHERE session_id = ? AND is_active = 1");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            $classes_result = $stmt->get_result();
            $classes = [];
            while ($row = $classes_result->fetch_assoc()) {
                $classes[] = $row;
            }
            
            // Get courses
            $stmt = $conn->prepare("SELECT id, code, name FROM courses WHERE is_active = 1");
            $stmt->execute();
            $courses_result = $stmt->get_result();
            $courses = [];
            while ($row = $courses_result->fetch_assoc()) {
                $courses[] = $row;
            }
            
            // Get lecturers
            $stmt = $conn->prepare("SELECT id, name FROM lecturers WHERE is_active = 1");
            $stmt->execute();
            $lecturers_result = $stmt->get_result();
            $lecturers = [];
            while ($row = $lecturers_result->fetch_assoc()) {
                $lecturers[] = $row;
            }
            
            // Get rooms
            $stmt = $conn->prepare("SELECT id, name, building FROM rooms WHERE is_active = 1");
            $stmt->execute();
            $rooms_result = $stmt->get_result();
            $rooms = [];
            while ($row = $rooms_result->fetch_assoc()) {
                $rooms[] = $row;
            }
            
            // Get days
            $stmt = $conn->prepare("SELECT id, name FROM days ORDER BY id");
            $stmt->execute();
            $days_result = $stmt->get_result();
            $days = [];
            while ($row = $days_result->fetch_assoc()) {
                $days[] = $row;
            }
            
            // Get time slots
            $stmt = $conn->prepare("SELECT id, start_time, end_time FROM time_slots ORDER BY start_time");
            $stmt->execute();
            $timeslots_result = $stmt->get_result();
            $time_slots = [];
            while ($row = $timeslots_result->fetch_assoc()) {
                $time_slots[] = $row;
            }
            
            // Generate timetable data using genetic algorithm or simple assignment
            $timetable_data = generateTimetableData($classes, $courses, $lecturers, $rooms, $days, $time_slots, $exclusions);
            
            $response['success'] = true;
            $response['data'] = $timetable_data;
            $response['message'] = "Timetable generated for {$session['name']} - Semester {$semester}";
            break;
            
        case 'save_timetable':
            $session_id = $_POST['session_id'] ?? 0;
            $semester = $_POST['semester'] ?? 1;
            $type = $_POST['type'] ?? 'lecture';
            $timetable_data_json = $_POST['timetable_data'] ?? '{}';
            $timetable_data = json_decode($timetable_data_json, true);
            
            if (!$session_id || empty($timetable_data)) {
                throw new Exception('Session ID and timetable data are required');
            }
            
            // Get session info
            $stmt = $conn->prepare("SELECT name, academic_year FROM sessions WHERE id = ?");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            $session_result = $stmt->get_result();
            $session = $session_result->fetch_assoc();
            
            if (!$session) {
                throw new Exception('Invalid session');
            }
            
            // Create saved timetable entry
            $timetable_name = "{$session['name']} - Semester {$semester} ({$type})";
            $stmt = $conn->prepare("INSERT INTO saved_timetables (name, session_id, semester, type, timetable_data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("siiss", $timetable_name, $session_id, $semester, $type, $timetable_data_json);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Timetable saved successfully';
                $response['data'] = ['id' => $conn->insert_id];
            } else {
                throw new Exception('Failed to save timetable');
            }
            break;
            
        case 'get_generated_timetable':
            $academic_year = $_POST['academic_year'] ?? '';
            $semester = $_POST['semester'] ?? '';
            
            if (!$academic_year || !$semester) {
                throw new Exception('Academic year and semester are required');
            }
            
            // Get timetable data from the main timetable table
            $query = "SELECT 
                        t.id,
                        c.name as class_name,
                        t.division_label,
                        co.code as course_code,
                        co.name as course_name,
                        l.name as lecturer_name,
                        r.name as room_name,
                        d.name as day_name,
                        ts.start_time,
                        ts.end_time
                      FROM timetable t
                      JOIN classes c ON t.class_id = c.id
                      JOIN courses co ON t.course_id = co.id
                      JOIN lecturers l ON t.lecturer_id = l.id
                      JOIN rooms r ON t.room_id = r.id
                      JOIN days d ON t.day_id = d.id
                      JOIN time_slots ts ON t.time_slot_id = ts.id
                      WHERE t.academic_year = ? AND t.semester = ?
                      ORDER BY d.id, ts.start_time";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $academic_year, $semester);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $timetable_data = [];
            while ($row = $result->fetch_assoc()) {
                // Include division label in class name if present
                if (!empty($row['division_label'])) {
                    $row['class_name'] .= ' ' . $row['division_label'];
                }
                $timetable_data[] = $row;
            }
            
            $response['success'] = true;
            $response['data'] = $timetable_data;
            break;
            
        case 'save_generated_timetable':
            $academic_year = $_POST['academic_year'] ?? '';
            $semester = $_POST['semester'] ?? '';
            $type = $_POST['type'] ?? 'lecture';
            
            if (!$academic_year || !$semester) {
                throw new Exception('Academic year and semester are required');
            }
            
            // Get timetable data from the main timetable table
            $query = "SELECT 
                        t.id,
                        c.name as class_name,
                        t.division_label,
                        co.code as course_code,
                        co.name as course_name,
                        l.name as lecturer_name,
                        r.name as room_name,
                        d.name as day_name,
                        ts.start_time,
                        ts.end_time
                      FROM timetable t
                      JOIN classes c ON t.class_id = c.id
                      JOIN courses co ON t.course_id = co.id
                      JOIN lecturers l ON t.lecturer_id = l.id
                      JOIN rooms r ON t.room_id = r.id
                      JOIN days d ON t.day_id = d.id
                      JOIN time_slots ts ON t.time_slot_id = ts.id
                      WHERE t.academic_year = ? AND t.semester = ?
                      ORDER BY d.id, ts.start_time";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $academic_year, $semester);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $timetable_data = [];
            while ($row = $result->fetch_assoc()) {
                // Include division label in class name if present
                if (!empty($row['division_label'])) {
                    $row['class_name'] .= ' ' . $row['division_label'];
                }
                $timetable_data[] = $row;
            }
            
            // Create saved timetable entry
            $timetable_name = "{$academic_year} - Semester {$semester} ({$type})";
            $timetable_data_json = json_encode($timetable_data);
            
            // Check if saved_timetables table exists, if not create it
            $table_check = $conn->query("SHOW TABLES LIKE 'saved_timetables'");
            if ($table_check && $table_check->num_rows == 0) {
                $create_table_sql = "CREATE TABLE saved_timetables (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    academic_year VARCHAR(50) NOT NULL,
                    semester VARCHAR(20) NOT NULL,
                    type VARCHAR(20) NOT NULL,
                    timetable_data JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $conn->query($create_table_sql);
            }
            
            $stmt = $conn->prepare("INSERT INTO saved_timetables (name, academic_year, semester, type, timetable_data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $timetable_name, $academic_year, $semester, $type, $timetable_data_json);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Timetable saved successfully';
                $response['data'] = ['id' => $conn->insert_id];
            } else {
                throw new Exception('Failed to save timetable');
            }
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

// Function to generate timetable data
function generateTimetableData($classes, $courses, $lecturers, $rooms, $days, $time_slots, $exclusions) {
    $timetable_data = [];
    
    // Convert days to lowercase for consistency
    $day_names = array_map(function($day) {
        return strtolower($day['name']);
    }, $days);
    
    // Convert time slots to hours for easier manipulation
    $time_hours = [];
    foreach ($time_slots as $slot) {
        $start_hour = (int)substr($slot['start_time'], 0, 2);
        $end_hour = (int)substr($slot['end_time'], 0, 2);
        $time_hours[] = [
            'id' => $slot['id'],
            'start_hour' => $start_hour,
            'end_hour' => $end_hour,
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time']
        ];
    }
    
    // Initialize timetable structure
    foreach ($day_names as $day) {
        $timetable_data[$day] = [];
        foreach ($rooms as $room) {
            $timetable_data[$day][$room['name']] = [];
        }
    }
    
    // Process exclusions first
    foreach ($exclusions as $exclusion) {
        $day = strtolower($exclusion['dayOfWeek']);
        $start_time = $exclusion['startTime'];
        $end_time = $exclusion['endTime'];
        $reason = $exclusion['reason'];
        
        // Find time slots that fall within exclusion period
        foreach ($time_hours as $slot) {
            if ($slot['start_time'] >= $start_time && $slot['start_time'] < $end_time) {
                foreach ($rooms as $room) {
                    $timetable_data[$day][$room['name']][] = [
                        'course' => [
                            'id' => 'exclusion',
                            'code' => 'EXCLUSION',
                            'name' => $reason,
                            'duration' => 1,
                            'color' => 'bg-warning text-dark',
                            'lecturerName' => 'Reserved',
                            'classes' => ['All Classes']
                        ],
                        'start_time' => $slot['start_hour'],
                        'spans' => 1
                    ];
                }
            }
        }
    }
    
    // Simple course assignment (in a real system, this would use the genetic algorithm)
    $course_index = 0;
    $lecturer_index = 0;
    
    foreach ($classes as $class) {
        // Assign 2-3 courses per class
        $courses_per_class = rand(2, 3);
        
        for ($i = 0; $i < $courses_per_class && $course_index < count($courses); $i++) {
            $course = $courses[$course_index];
            $lecturer = $lecturers[$lecturer_index % count($lecturers)];
            
            // Random day and room
            $day = $day_names[array_rand($day_names)];
            $room = $rooms[array_rand($rooms)];
            
            // Random time slot
            $time_slot = $time_hours[array_rand($time_hours)];
            
            // Check if slot is available (not excluded)
            $is_excluded = false;
            foreach ($timetable_data[$day][$room['name']] as $existing) {
                if ($existing['start_time'] == $time_slot['start_hour']) {
                    $is_excluded = true;
                    break;
                }
            }
            
            if (!$is_excluded) {
                $duration = rand(1, 3); // 1-3 hours
                $color_classes = ['bg-blue-100 text-blue-800', 'bg-green-100 text-green-800', 'bg-purple-100 text-purple-800', 'bg-yellow-100 text-yellow-800', 'bg-red-100 text-red-800', 'bg-indigo-100 text-indigo-800'];
                $color = $color_classes[array_rand($color_classes)];
                
                $timetable_data[$day][$room['name']][] = [
                    'course' => [
                        'id' => $course['id'],
                        'code' => $course['code'],
                        'name' => $course['name'],
                        'duration' => $duration,
                        'color' => $color,
                        'lecturerName' => $lecturer['name'],
                        'classes' => [$class['name']]
                    ],
                    'start_time' => $time_slot['start_hour'],
                    'spans' => $duration
                ];
            }
            
            $course_index++;
            $lecturer_index++;
        }
    }
    
    return $timetable_data;
}
?>
