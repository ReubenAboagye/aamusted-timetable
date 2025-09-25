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

// Placeholder functions for other modules (to be implemented)
function handleProgramActions($action, $conn) {
    sendResponse(false, 'Program actions not yet implemented');
}

function handleClassActions($action, $conn) {
    sendResponse(false, 'Class actions not yet implemented');
}

function handleRoomActions($action, $conn) {
    sendResponse(false, 'Room actions not yet implemented');
}

function handleLevelActions($action, $conn) {
    sendResponse(false, 'Level actions not yet implemented');
}

function handleStreamActions($action, $conn) {
    sendResponse(false, 'Stream actions not yet implemented');
}

function handleLecturerCourseActions($action, $conn) {
    sendResponse(false, 'Lecturer course actions not yet implemented');
}

function handleClassCourseActions($action, $conn) {
    sendResponse(false, 'Class course actions not yet implemented');
}

function handleCourseRoomTypeActions($action, $conn) {
    // This can reuse the existing course_roomtype.php logic
    include 'ajax_course_roomtype.php';
}
?>