<?php
// Centralized AJAX API Handler for the entire project
// Ensure no output before JSON
ob_start();

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include database connection
include 'connect.php';
include 'includes/flash.php';

// Start session for CSRF protection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = null) {
    // Clear any output buffer
    ob_clean();
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper function to validate CSRF token
function validateCSRF() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        sendResponse(false, 'CSRF token validation failed');
    }
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Only allow POST requests for AJAX operations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST requests are allowed');
}

$action = $_POST['action'] ?? null;
$module = $_POST['module'] ?? null;

if (empty($action) || empty($module)) {
    sendResponse(false, 'Action and module are required');
}

// Validate CSRF token
validateCSRF();

try {
    switch ($module) {
        case 'department':
            handleDepartmentActions($action, $conn);
            break;
        case 'course':
            handleCourseActions($action, $conn);
            break;
        case 'lecturer':
            handleLecturerActions($action, $conn);
            break;
        case 'program':
            handleProgramActions($action, $conn);
            break;
        case 'class':
            handleClassActions($action, $conn);
            break;
        case 'room':
            handleRoomActions($action, $conn);
            break;
        case 'level':
            handleLevelActions($action, $conn);
            break;
        case 'stream':
            handleStreamActions($action, $conn);
            break;
        case 'lecturer_course':
            handleLecturerCourseActions($action, $conn);
            break;
        case 'class_course':
            handleClassCourseActions($action, $conn);
            break;
        case 'course_roomtype':
            handleCourseRoomTypeActions($action, $conn);
            break;
        default:
            sendResponse(false, 'Invalid module specified');
    }

} catch (Exception $e) {
    sendResponse(false, 'An error occurred: ' . $e->getMessage());
} catch (Error $e) {
    sendResponse(false, 'A fatal error occurred: ' . $e->getMessage());
}

// If we reach here, something went wrong
sendResponse(false, 'Unknown error occurred');

// Department Actions
function handleDepartmentActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code)) {
                sendResponse(false, 'Name and code are required');
            }
            
            // Check if department code already exists
            $check_sql = "SELECT id FROM departments WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Department code already exists');
            }
            
            $sql = "INSERT INTO departments (name, code, is_active) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $code, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Department added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding department: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($code)) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if department code already exists (excluding current record)
            $check_sql = "SELECT id FROM departments WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Department code already exists');
            }
            
            $sql = "UPDATE departments SET name = ?, code = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $name, $code, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Department updated successfully!');
            } else {
                sendResponse(false, 'Error updating department: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid department ID');
            }
            
            // Check if department has associated courses
            $check_sql = "SELECT COUNT(*) as count FROM courses WHERE department_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                sendResponse(false, 'Cannot delete department with associated courses');
            }
            
            $sql = "DELETE FROM departments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Department deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting department: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT d.*, COUNT(c.id) as course_count FROM departments d LEFT JOIN courses c ON d.id = c.department_id GROUP BY d.id ORDER BY d.name";
            $result = $conn->query($sql);
            
            $departments = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $departments[] = $row;
                }
            }
            
            sendResponse(true, 'Departments retrieved successfully', $departments);
            break;
            
        default:
            sendResponse(false, 'Invalid department action');
    }
}

// Course Actions
function handleCourseActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $hours_per_week = (int)($_POST['hours_per_week'] ?? 3);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code) || $department_id <= 0) {
                sendResponse(false, 'Name, code, and department are required');
            }
            
            // Check if course code already exists
            $check_sql = "SELECT id FROM courses WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Course code already exists');
            }
            
            $sql = "INSERT INTO courses (name, code, department_id, hours_per_week, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $name, $code, $department_id, $hours_per_week, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Course added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding course: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $hours_per_week = (int)($_POST['hours_per_week'] ?? 3);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($code) || $department_id <= 0) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if course code already exists (excluding current record)
            $check_sql = "SELECT id FROM courses WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Course code already exists');
            }
            
            $sql = "UPDATE courses SET name = ?, code = ?, department_id = ?, hours_per_week = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiii", $name, $code, $department_id, $hours_per_week, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Course updated successfully!');
            } else {
                sendResponse(false, 'Error updating course: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid course ID');
            }
            
            $sql = "DELETE FROM courses WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Course deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting course: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT c.*, d.name as department_name FROM courses c LEFT JOIN departments d ON c.department_id = d.id ORDER BY c.code";
            $result = $conn->query($sql);
            
            $courses = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
            }
            
            sendResponse(true, 'Courses retrieved successfully', $courses);
            break;
            
        default:
            sendResponse(false, 'Invalid course action');
    }
}

// Lecturer Actions
function handleLecturerActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || $department_id <= 0) {
                sendResponse(false, 'Name and department are required');
            }
            
            // Verify department exists
            $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ?");
            $dept_check->bind_param("i", $department_id);
            $dept_check->execute();
            $dept_res = $dept_check->get_result();
            
            if ($dept_res->num_rows === 0) {
                sendResponse(false, 'Selected department does not exist');
            }
            
            $sql = "INSERT INTO lecturers (name, department_id, is_active) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $name, $department_id, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Lecturer added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding lecturer: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || $department_id <= 0) {
                sendResponse(false, 'Invalid input');
            }
            
            // Verify department exists
            $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ?");
            $dept_check->bind_param("i", $department_id);
            $dept_check->execute();
            $dept_res = $dept_check->get_result();
            
            if ($dept_res->num_rows === 0) {
                sendResponse(false, 'Selected department does not exist');
            }
            
            $sql = "UPDATE lecturers SET name = ?, department_id = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siii", $name, $department_id, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Lecturer updated successfully!');
            } else {
                sendResponse(false, 'Error updating lecturer: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid lecturer ID');
            }
            
            $sql = "DELETE FROM lecturers WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Lecturer deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting lecturer: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT l.*, d.name as department_name FROM lecturers l LEFT JOIN departments d ON l.department_id = d.id ORDER BY l.name";
            $result = $conn->query($sql);
            
            $lecturers = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $lecturers[] = $row;
                }
            }
            
            sendResponse(true, 'Lecturers retrieved successfully', $lecturers);
            break;
            
        default:
            sendResponse(false, 'Invalid lecturer action');
    }
}

// Program Actions
function handleProgramActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $duration_years = (int)($_POST['duration_years'] ?? 4);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code) || $department_id <= 0) {
                sendResponse(false, 'Name, code, and department are required');
            }
            
            // Check if program code already exists
            $check_sql = "SELECT id FROM programs WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Program code already exists');
            }
            
            $sql = "INSERT INTO programs (name, code, department_id, duration_years, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $name, $code, $department_id, $duration_years, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Program added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding program: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $duration_years = (int)($_POST['duration_years'] ?? 4);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($code) || $department_id <= 0) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if program code already exists (excluding current record)
            $check_sql = "SELECT id FROM programs WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Program code already exists');
            }
            
            $sql = "UPDATE programs SET name = ?, code = ?, department_id = ?, duration_years = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiii", $name, $code, $department_id, $duration_years, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Program updated successfully!');
            } else {
                sendResponse(false, 'Error updating program: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid program ID');
            }
            
            $sql = "DELETE FROM programs WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Program deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting program: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT p.*, d.name as department_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id ORDER BY p.name";
            $result = $conn->query($sql);
            
            $programs = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $programs[] = $row;
                }
            }
            
            sendResponse(true, 'Programs retrieved successfully', $programs);
            break;
            
        default:
            sendResponse(false, 'Invalid program action');
    }
}

// Class Actions
function handleClassActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $program_id = (int)($_POST['program_id'] ?? 0);
            $stream_id = (int)($_POST['stream_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code) || $program_id <= 0 || $stream_id <= 0) {
                sendResponse(false, 'Name, code, program, and stream are required');
            }
            
            // Check if class code already exists
            $check_sql = "SELECT id FROM classes WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Class code already exists');
            }
            
            $sql = "INSERT INTO classes (name, code, program_id, stream_id, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $name, $code, $program_id, $stream_id, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Class added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding class: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $program_id = (int)($_POST['program_id'] ?? 0);
            $stream_id = (int)($_POST['stream_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($code) || $program_id <= 0 || $stream_id <= 0) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if class code already exists (excluding current record)
            $check_sql = "SELECT id FROM classes WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Class code already exists');
            }
            
            $sql = "UPDATE classes SET name = ?, code = ?, program_id = ?, stream_id = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiii", $name, $code, $program_id, $stream_id, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Class updated successfully!');
            } else {
                sendResponse(false, 'Error updating class: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid class ID');
            }
            
            $sql = "DELETE FROM classes WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Class deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting class: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT c.*, p.name as program_name, s.name as stream_name FROM classes c LEFT JOIN programs p ON c.program_id = p.id LEFT JOIN streams s ON c.stream_id = s.id ORDER BY c.name";
            $result = $conn->query($sql);
            
            $classes = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            }
            
            sendResponse(true, 'Classes retrieved successfully', $classes);
            break;
            
        default:
            sendResponse(false, 'Invalid class action');
    }
}

// Room Actions
function handleRoomActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $capacity = (int)($_POST['capacity'] ?? 0);
            $room_type_id = (int)($_POST['room_type_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code) || $capacity <= 0 || $room_type_id <= 0) {
                sendResponse(false, 'Name, code, capacity, and room type are required');
            }
            
            // Check if room code already exists
            $check_sql = "SELECT id FROM rooms WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Room code already exists');
            }
            
            $sql = "INSERT INTO rooms (name, code, capacity, room_type_id, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $name, $code, $capacity, $room_type_id, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Room added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding room: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $capacity = (int)($_POST['capacity'] ?? 0);
            $room_type_id = (int)($_POST['room_type_id'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($code) || $capacity <= 0 || $room_type_id <= 0) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if room code already exists (excluding current record)
            $check_sql = "SELECT id FROM rooms WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Room code already exists');
            }
            
            $sql = "UPDATE rooms SET name = ?, code = ?, capacity = ?, room_type_id = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiii", $name, $code, $capacity, $room_type_id, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Room updated successfully!');
            } else {
                sendResponse(false, 'Error updating room: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid room ID');
            }
            
            $sql = "DELETE FROM rooms WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Room deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting room: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT r.*, rt.name as room_type_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id ORDER BY r.name";
            $result = $conn->query($sql);
            
            $rooms = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rooms[] = $row;
                }
            }
            
            sendResponse(true, 'Rooms retrieved successfully', $rooms);
            break;
            
        case 'get_room_types':
            $sql = "SELECT * FROM room_types ORDER BY name";
            $result = $conn->query($sql);
            
            $room_types = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $room_types[] = $row;
                }
            }
            
            sendResponse(true, 'Room types retrieved successfully', $room_types);
            break;
            
        default:
            sendResponse(false, 'Invalid room action');
    }
}

// Level Actions
function handleLevelActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code)) {
                sendResponse(false, 'Name and code are required');
            }
            
            // Check if level code already exists
            $check_sql = "SELECT id FROM levels WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Level code already exists');
            }
            
            $sql = "INSERT INTO levels (name, code, description, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $code, $description, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Level added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding level: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($code)) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if level code already exists (excluding current record)
            $check_sql = "SELECT id FROM levels WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Level code already exists');
            }
            
            $sql = "UPDATE levels SET name = ?, code = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $name, $code, $description, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Level updated successfully!');
            } else {
                sendResponse(false, 'Error updating level: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid level ID');
            }
            
            $sql = "DELETE FROM levels WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Level deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting level: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT * FROM levels ORDER BY name";
            $result = $conn->query($sql);
            
            $levels = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $levels[] = $row;
                }
            }
            
            sendResponse(true, 'Levels retrieved successfully', $levels);
            break;
            
        default:
            sendResponse(false, 'Invalid level action');
    }
}

// Stream Actions
function handleStreamActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code)) {
                sendResponse(false, 'Name and code are required');
            }
            
            // Check if stream code already exists
            $check_sql = "SELECT id FROM streams WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Stream code already exists');
            }
            
            $sql = "INSERT INTO streams (name, code, is_active) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $code, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Stream added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding stream: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($code)) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if stream code already exists (excluding current record)
            $check_sql = "SELECT id FROM streams WHERE code = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Stream code already exists');
            }
            
            $sql = "UPDATE streams SET name = ?, code = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $name, $code, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Stream updated successfully!');
            } else {
                sendResponse(false, 'Error updating stream: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid stream ID');
            }
            
            $sql = "DELETE FROM streams WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Stream deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting stream: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT * FROM streams ORDER BY name";
            $result = $conn->query($sql);
            
            $streams = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $streams[] = $row;
                }
            }
            
            sendResponse(true, 'Streams retrieved successfully', $streams);
            break;
            
        default:
            sendResponse(false, 'Invalid stream action');
    }
}

// Lecturer Course Actions
function handleLecturerCourseActions($action, $conn) {
    switch ($action) {
        case 'add':
            $lecturer_id = (int)($_POST['lecturer_id'] ?? 0);
            $course_id = (int)($_POST['course_id'] ?? 0);
            
            if ($lecturer_id <= 0 || $course_id <= 0) {
                sendResponse(false, 'Lecturer and course are required');
            }
            
            // Check if mapping already exists
            $check_sql = "SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $lecturer_id, $course_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'This lecturer-course mapping already exists');
            }
            
            $sql = "INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $lecturer_id, $course_id);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Lecturer-course mapping added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding lecturer-course mapping: ' . $stmt->error);
            }
            break;
            
        case 'bulk_add':
            $lecturer_id = (int)($_POST['lecturer_id'] ?? 0);
            $course_ids = isset($_POST['course_ids']) && is_array($_POST['course_ids']) ? array_map('intval', $_POST['course_ids']) : [];
            
            if ($lecturer_id <= 0 || empty($course_ids)) {
                sendResponse(false, 'Lecturer and courses are required');
            }
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($course_ids as $course_id) {
                if ($course_id <= 0) continue;
                
                // Check if mapping already exists
                $check_sql = "SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $lecturer_id, $course_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_count++;
                    continue;
                }
                
                $sql = "INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $lecturer_id, $course_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            if ($success_count > 0) {
                $message = "Successfully added $success_count lecturer-course mappings!";
                if ($error_count > 0) {
                    $message .= " $error_count mappings already existed.";
                }
                sendResponse(true, $message);
            } else {
                sendResponse(false, 'No new mappings were added. All selected mappings may already exist.');
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid mapping ID');
            }
            
            $sql = "DELETE FROM lecturer_courses WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Lecturer-course mapping deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting lecturer-course mapping: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT lc.*, l.name as lecturer_name, c.name as course_name, c.code as course_code, d.name as department_name 
                    FROM lecturer_courses lc 
                    LEFT JOIN lecturers l ON lc.lecturer_id = l.id 
                    LEFT JOIN courses c ON lc.course_id = c.id 
                    LEFT JOIN departments d ON l.department_id = d.id 
                    ORDER BY l.name, c.name";
            $result = $conn->query($sql);
            
            $mappings = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $mappings[] = $row;
                }
            }
            
            sendResponse(true, 'Lecturer-course mappings retrieved successfully', $mappings);
            break;
            
        case 'get_lecturers':
            $sql = "SELECT l.*, d.name as department_name FROM lecturers l LEFT JOIN departments d ON l.department_id = d.id WHERE l.is_active = 1 ORDER BY l.name";
            $result = $conn->query($sql);
            
            $lecturers = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $lecturers[] = $row;
                }
            }
            
            sendResponse(true, 'Lecturers retrieved successfully', $lecturers);
            break;
            
        case 'get_courses':
            $sql = "SELECT c.*, d.name as department_name FROM courses c LEFT JOIN departments d ON c.department_id = d.id WHERE c.is_active = 1 ORDER BY c.name";
            $result = $conn->query($sql);
            
            $courses = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
            }
            
            sendResponse(true, 'Courses retrieved successfully', $courses);
            break;
            
        default:
            sendResponse(false, 'Invalid lecturer course action');
    }
}

// Class Course Actions
function handleClassCourseActions($action, $conn) {
    switch ($action) {
        case 'assign_single':
            $class_id = (int)($_POST['class_id'] ?? 0);
            $course_id = (int)($_POST['course_id'] ?? 0);
            
            if ($class_id <= 0 || $course_id <= 0) {
                sendResponse(false, 'Class and course are required');
            }
            
            // Check if assignment already exists
            $check_sql = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $class_id, $course_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'This class-course assignment already exists');
            }
            
            $sql = "INSERT INTO class_courses (class_id, course_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $class_id, $course_id);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Course assigned to class successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error assigning course to class: ' . $stmt->error);
            }
            break;
            
        case 'assign_bulk':
            $class_ids = isset($_POST['class_ids']) && is_array($_POST['class_ids']) ? array_map('intval', $_POST['class_ids']) : [];
            $course_ids = isset($_POST['course_ids']) && is_array($_POST['course_ids']) ? array_map('intval', $_POST['course_ids']) : [];
            
            if (empty($class_ids) || empty($course_ids)) {
                sendResponse(false, 'Classes and courses are required');
            }
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($class_ids as $class_id) {
                if ($class_id <= 0) continue;
                
                foreach ($course_ids as $course_id) {
                    if ($course_id <= 0) continue;
                    
                    // Check if assignment already exists
                    $check_sql = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("ii", $class_id, $course_id);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error_count++;
                        continue;
                    }
                    
                    $sql = "INSERT INTO class_courses (class_id, course_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $class_id, $course_id);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                }
            }
            
            if ($success_count > 0) {
                $message = "Successfully assigned $success_count class-course assignments!";
                if ($error_count > 0) {
                    $message .= " $error_count assignments already existed.";
                }
                sendResponse(true, $message);
            } else {
                sendResponse(false, 'No new assignments were made. All selected assignments may already exist.');
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid assignment ID');
            }
            
            $sql = "DELETE FROM class_courses WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Class-course assignment deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting class-course assignment: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT cc.*, c.name as class_name, c.code as class_code, co.name as course_name, co.code as course_code, p.name as program_name, s.name as stream_name
                    FROM class_courses cc 
                    LEFT JOIN classes c ON cc.class_id = c.id 
                    LEFT JOIN courses co ON cc.course_id = co.id 
                    LEFT JOIN programs p ON c.program_id = p.id 
                    LEFT JOIN streams s ON c.stream_id = s.id 
                    ORDER BY c.name, co.name";
            $result = $conn->query($sql);
            
            $assignments = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $assignments[] = $row;
                }
            }
            
            sendResponse(true, 'Class-course assignments retrieved successfully', $assignments);
            break;
            
        case 'get_classes':
            $sql = "SELECT c.*, p.name as program_name, s.name as stream_name FROM classes c LEFT JOIN programs p ON c.program_id = p.id LEFT JOIN streams s ON c.stream_id = s.id WHERE c.is_active = 1 ORDER BY c.name";
            $result = $conn->query($sql);
            
            $classes = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
            }
            
            sendResponse(true, 'Classes retrieved successfully', $classes);
            break;
            
        case 'get_courses':
            $sql = "SELECT c.*, d.name as department_name FROM courses c LEFT JOIN departments d ON c.department_id = d.id WHERE c.is_active = 1 ORDER BY c.name";
            $result = $conn->query($sql);
            
            $courses = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
            }
            
            sendResponse(true, 'Courses retrieved successfully', $courses);
            break;
            
        default:
            sendResponse(false, 'Invalid class course action');
    }
}

function handleCourseRoomTypeActions($action, $conn) {
    // This can reuse the existing course_roomtype.php logic
    include 'ajax_course_roomtype.php';
}
?>