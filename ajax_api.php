<?php
// Centralized AJAX API Handler for the entire project
// Include custom error handler for better error display
include_once 'includes/custom_error_handler.php';

// Ensure no output before JSON response is sent 
ob_start();

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include database connection and session management
include 'connect.php';
include 'includes/flash.php';
include 'includes/stream_validation.php';
include 'includes/stream_manager.php';

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

// Validate stream selection for all database operations
$stream_validation = validateStreamForAjax($conn);
$current_stream_id = $stream_validation['stream_id'];

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
        case 'room_type':
            handleRoomTypeActions($action, $conn);
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
            // Get stream manager for filtering
            $streamManager = getStreamManager();
            $current_stream_id = $streamManager->getCurrentStreamId();
            
            $sql = "SELECT d.*, COUNT(c.id) as course_count FROM departments d LEFT JOIN courses c ON d.id = c.department_id";
            
            // Check if departments table has stream_id column
            $col = $conn->query("SHOW COLUMNS FROM departments LIKE 'stream_id'");
            $has_dept_stream = ($col && $col->num_rows > 0);
            if ($col) $col->close();
            
            // Check if courses table has stream_id column
            $col = $conn->query("SHOW COLUMNS FROM courses LIKE 'stream_id'");
            $has_course_stream = ($col && $col->num_rows > 0);
            if ($col) $col->close();
            
            if ($has_dept_stream) {
                $sql .= " WHERE d.stream_id = " . intval($current_stream_id);
            }
            
            if ($has_course_stream) {
                $sql .= ($has_dept_stream ? " AND" : " WHERE") . " (c.stream_id = " . intval($current_stream_id) . " OR c.id IS NULL)";
            }
            
            $sql .= " GROUP BY d.id ORDER BY d.name";
            
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
    // Get current stream ID
    $streamManager = getStreamManager();
    $current_stream_id = $streamManager->getCurrentStreamId();
    
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
            
            // Check if course code already exists in the current stream
            $check_sql = "SELECT id FROM courses WHERE code = ? AND stream_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $current_stream_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Course code already exists in this stream');
            }
            
            $sql = "INSERT INTO courses (name, code, department_id, stream_id, hours_per_week, is_active) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiii", $name, $code, $department_id, $current_stream_id, $hours_per_week, $is_active);
            
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
            
            // Check if course code already exists in the current stream (excluding current record)
            $check_sql = "SELECT id FROM courses WHERE code = ? AND stream_id = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sii", $code, $current_stream_id, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Course code already exists in this stream');
            }
            
            $sql = "UPDATE courses SET name = ?, code = ?, department_id = ?, hours_per_week = ?, is_active = ? WHERE id = ? AND stream_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiiii", $name, $code, $department_id, $hours_per_week, $is_active, $id, $current_stream_id);
            
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
            
            $sql = "DELETE FROM courses WHERE id = ? AND stream_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $current_stream_id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Course deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting course: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            // Get stream manager for filtering
            $streamManager = getStreamManager();
            $current_stream_id = $streamManager->getCurrentStreamId();
            
            $sql = "SELECT c.*, d.name as department_name FROM courses c LEFT JOIN departments d ON c.department_id = d.id";
            
            // Check if courses table has stream_id column
            $col = $conn->query("SHOW COLUMNS FROM courses LIKE 'stream_id'");
            $has_stream_col = ($col && $col->num_rows > 0);
            if ($col) $col->close();
            
            if ($has_stream_col) {
                $sql .= " WHERE c.stream_id = " . intval($current_stream_id);
            }
            
            $sql .= " ORDER BY c.code";
            
            $result = $conn->query($sql);
            
            $courses = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
            }
            
            sendResponse(true, 'Courses retrieved successfully', $courses);
            break;
            
        case 'bulk_import':
            $import_data = $_POST['import_data'] ?? null;
            
            // Handle JSON string input
            if (is_string($import_data)) {
                $import_data = json_decode($import_data, true);
            }
            
            if (!$import_data || !is_array($import_data)) {
                sendResponse(false, 'Invalid import data');
            }
            
            $success_count = 0;
            $error_count = 0;
            $skipped_count = 0;
            $added_courses = [];
            
            // Get existing course codes for duplicate checking within current stream
            $existing_codes = [];
            $existing_sql = "SELECT code FROM courses WHERE stream_id = ?";
            $existing_stmt = $conn->prepare($existing_sql);
            $existing_stmt->bind_param("i", $current_stream_id);
            $existing_stmt->execute();
            $existing_result = $existing_stmt->get_result();
            if ($existing_result) {
                while ($row = $existing_result->fetch_assoc()) {
                    $existing_codes[strtolower($row['code'])] = true;
                }
            }
            $existing_stmt->close();
            
            foreach ($import_data as $course_data) {
                $name = sanitizeInput($course_data['name'] ?? '');
                $code = sanitizeInput($course_data['code'] ?? '');
                $department_id = (int)($course_data['department_id'] ?? 0);
                $hours_per_week = (int)($course_data['hours_per_week'] ?? 3);
                $is_active = (int)($course_data['is_active'] ?? 1);
                
                if (empty($name) || empty($code) || $department_id <= 0) {
                    $error_count++;
                    continue;
                }
                
                // Check for duplicate codes
                if (isset($existing_codes[strtolower($code)])) {
                    $skipped_count++;
                    continue;
                }
                
                // Verify department exists
                $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ?");
                $dept_check->bind_param("i", $department_id);
                $dept_check->execute();
                $dept_res = $dept_check->get_result();
                
                if ($dept_res->num_rows === 0) {
                    $error_count++;
                    continue;
                }
                
                // Validate hours per week
                if ($hours_per_week < 1 || $hours_per_week > 20) {
                    $error_count++;
                    continue;
                }
                
                $sql = "INSERT INTO courses (name, code, department_id, stream_id, hours_per_week, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiiii", $name, $code, $department_id, $current_stream_id, $hours_per_week, $is_active);
                
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $success_count++;
                    
                    // Get department name for response
                    $dept_name_sql = "SELECT name FROM departments WHERE id = ?";
                    $dept_name_stmt = $conn->prepare($dept_name_sql);
                    $dept_name_stmt->bind_param("i", $department_id);
                    $dept_name_stmt->execute();
                    $dept_name_result = $dept_name_stmt->get_result();
                    $dept_name = $dept_name_result->fetch_assoc()['name'] ?? 'Unknown';
                    
                    $added_courses[] = [
                        'id' => $new_id,
                        'name' => $name,
                        'code' => $code,
                        'department_id' => $department_id,
                        'department_name' => $dept_name,
                        'hours_per_week' => $hours_per_week,
                        'is_active' => $is_active
                    ];
                    
                    // Add to existing codes to prevent duplicates in same batch
                    $existing_codes[strtolower($code)] = true;
                } else {
                    $error_count++;
                }
            }
            
            $message = "Import completed: {$success_count} added, {$skipped_count} skipped (duplicates), {$error_count} errors";
            sendResponse(true, $message, [
                'total_processed' => count($import_data),
                'added_count' => $success_count,
                'skipped_count' => $skipped_count,
                'error_count' => $error_count,
                'added_courses' => $added_courses
            ]);
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
            // Get stream manager for filtering
            $streamManager = getStreamManager();
            $current_stream_id = $streamManager->getCurrentStreamId();
            
            $sql = "SELECT l.*, d.name as department_name FROM lecturers l LEFT JOIN departments d ON l.department_id = d.id";
            
            // Check if lecturers table has stream_id column
            $col = $conn->query("SHOW COLUMNS FROM lecturers LIKE 'stream_id'");
            $has_stream_col = ($col && $col->num_rows > 0);
            if ($col) $col->close();
            
            if ($has_stream_col) {
                $sql .= " WHERE l.stream_id = " . intval($current_stream_id);
            }
            
            $sql .= " ORDER BY l.name";
            
            $result = $conn->query($sql);
            
            $lecturers = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $lecturers[] = $row;
                }
            }
            
            sendResponse(true, 'Lecturers retrieved successfully', $lecturers);
            break;
            
        case 'bulk_import':
            $import_data = $_POST['import_data'] ?? null;
            
            // Handle JSON string input
            if (is_string($import_data)) {
                $import_data = json_decode($import_data, true);
            }
            
            if (!$import_data || !is_array($import_data)) {
                sendResponse(false, 'Invalid import data');
            }
            
            $success_count = 0;
            $error_count = 0;
            $skipped_count = 0;
            $added_lecturers = [];
            
            // Get existing lecturer names for duplicate checking
            $existing_lecturers = [];
            $existing_sql = "SELECT name, department_id FROM lecturers";
            $existing_result = $conn->query($existing_sql);
            if ($existing_result) {
                while ($row = $existing_result->fetch_assoc()) {
                    $key = strtolower($row['name']) . '|' . $row['department_id'];
                    $existing_lecturers[$key] = true;
                }
            }
            
            foreach ($import_data as $lecturer_data) {
                $name = sanitizeInput($lecturer_data['name'] ?? '');
                $department_id = (int)($lecturer_data['department_id'] ?? 0);
                $is_active = (int)($lecturer_data['is_active'] ?? 1);
                
                if (empty($name) || $department_id <= 0) {
                    $error_count++;
                    continue;
                }
                
                // Check for duplicates
                $duplicate_key = strtolower($name) . '|' . $department_id;
                if (isset($existing_lecturers[$duplicate_key])) {
                    $skipped_count++;
                    continue;
                }
                
                // Verify department exists
                $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ?");
                $dept_check->bind_param("i", $department_id);
                $dept_check->execute();
                $dept_res = $dept_check->get_result();
                
                if ($dept_res->num_rows === 0) {
                    $error_count++;
                    continue;
                }
                
                $sql = "INSERT INTO lecturers (name, department_id, is_active) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sii", $name, $department_id, $is_active);
                
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $success_count++;
                    
                    // Get department name for response
                    $dept_name_sql = "SELECT name FROM departments WHERE id = ?";
                    $dept_name_stmt = $conn->prepare($dept_name_sql);
                    $dept_name_stmt->bind_param("i", $department_id);
                    $dept_name_stmt->execute();
                    $dept_name_result = $dept_name_stmt->get_result();
                    $dept_name = $dept_name_result->fetch_assoc()['name'] ?? 'Unknown';
                    
                    $added_lecturers[] = [
                        'id' => $new_id,
                        'name' => $name,
                        'department_id' => $department_id,
                        'department_name' => $dept_name,
                        'is_active' => $is_active
                    ];
                    
                    // Add to existing lecturers to prevent duplicates in same batch
                    $existing_lecturers[$duplicate_key] = true;
                } else {
                    $error_count++;
                }
            }
            
            $message = "Import completed: {$success_count} added, {$skipped_count} skipped (duplicates), {$error_count} errors";
            sendResponse(true, $message, [
                'total_processed' => count($import_data),
                'added_count' => $success_count,
                'skipped_count' => $skipped_count,
                'error_count' => $error_count,
                'added_lecturers' => $added_lecturers
            ]);
            break;
            
        default:
            sendResponse(false, 'Invalid lecturer action');
    }
}

// Placeholder functions for other modules (to be implemented)
function handleProgramActions($action, $conn) {
    // Get current stream ID
    $streamManager = getStreamManager();
    $current_stream_id = $streamManager->getCurrentStreamId();
    
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $duration = (int)($_POST['duration'] ?? 4);
            
            if (empty($name) || empty($code) || $department_id <= 0) {
                sendResponse(false, 'Name, code, and department are required');
            }
            
            // Check if program code already exists in the current stream
            $check_sql = "SELECT id FROM programs WHERE code = ? AND stream_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $code, $current_stream_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Program code already exists in this stream');
            }
            
            $sql = "INSERT INTO programs (name, code, department_id, stream_id, duration_years) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiii", $name, $code, $department_id, $current_stream_id, $duration);
            
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
            $duration = (int)($_POST['duration'] ?? 4);
            
            if ($id <= 0 || empty($name) || empty($code) || $department_id <= 0) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if program code already exists in the current stream (excluding current record)
            $check_sql = "SELECT id FROM programs WHERE code = ? AND stream_id = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sii", $code, $current_stream_id, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Program code already exists in this stream');
            }
            
            $sql = "UPDATE programs SET name = ?, code = ?, department_id = ?, duration_years = ? WHERE id = ? AND stream_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiii", $name, $code, $department_id, $duration, $id, $current_stream_id);
            
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
            
            $sql = "DELETE FROM programs WHERE id = ? AND stream_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id, $current_stream_id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Program deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting program: ' . $stmt->error);
            }
            break;
            
        case 'get_list':
            $sql = "SELECT p.*, d.name as department_name FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.stream_id = ? ORDER BY p.code";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $current_stream_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $programs = [];
            while ($row = $result->fetch_assoc()) {
                $programs[] = $row;
            }
            
            sendResponse(true, 'Programs retrieved successfully', $programs);
            break;
            
        case 'get_departments':
            $sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
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
            sendResponse(false, 'Invalid program action');
    }
}

function handleClassActions($action, $conn) {
    global $current_stream_id;
    
    switch ($action) {
        case 'get_count':
            // Get stream manager for filtering
            include_once 'includes/stream_manager.php';
            $streamManager = getStreamManager();
            $current_stream_id = $streamManager->getCurrentStreamId();
            
            // Check if classes table has stream_id column
            $col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
            $has_stream_col = ($col && $col->num_rows > 0);
            if ($col) $col->close();
            
            // Build query based on stream support
            if ($has_stream_col) {
                $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
                    FROM classes WHERE stream_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $current_stream_id);
            } else {
                $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
                    FROM classes";
                $stmt = $conn->prepare($sql);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            sendResponse(true, 'Class count retrieved successfully', [
                'total' => intval($row['total']),
                'active' => intval($row['active']),
                'inactive' => intval($row['inactive'])
            ]);
            break;
            
        default:
            sendResponse(false, 'Invalid class action');
    }
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

// Room Type Actions
function handleRoomTypeActions($action, $conn) {
    switch ($action) {
        case 'list':
            $sql = "SELECT id, name, description, is_active, created_at FROM room_types ORDER BY name";
            $result = $conn->query($sql);
            
            if (!$result) {
                sendResponse(false, 'Error fetching room types: ' . $conn->error);
            }
            
            $room_types = [];
            while ($row = $result->fetch_assoc()) {
                $room_types[] = $row;
            }
            
            sendResponse(true, 'Room types fetched successfully', $room_types);
            break;
            
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                sendResponse(false, 'Room type name is required');
            }
            
            // Check if room type name already exists
            $check_sql = "SELECT id FROM room_types WHERE name = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $name);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Room type name already exists');
            }
            
            $sql = "INSERT INTO room_types (name, description, is_active) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $description, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Room type added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding room type: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name)) {
                sendResponse(false, 'Invalid input');
            }
            
            // Check if room type name already exists (excluding current record)
            $check_sql = "SELECT id FROM room_types WHERE name = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $name, $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Room type name already exists');
            }
            
            $sql = "UPDATE room_types SET name = ?, description = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $name, $description, $is_active, $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Room type updated successfully!');
            } else {
                sendResponse(false, 'Error updating room type: ' . $stmt->error);
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                sendResponse(false, 'Invalid room type ID');
            }
            
            // Check if room type is used in rooms table
            $check_sql = "SELECT COUNT(*) as count FROM rooms WHERE room_type = (SELECT name FROM room_types WHERE id = ?)";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                sendResponse(false, 'Cannot delete room type that is being used by rooms');
            }
            
            $sql = "DELETE FROM room_types WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                sendResponse(true, 'Room type deleted successfully!');
            } else {
                sendResponse(false, 'Error deleting room type: ' . $stmt->error);
            }
            break;
            
        case 'bulk_edit':
            $ids = $_POST['ids'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($ids)) {
                sendResponse(false, 'No room types selected');
            }
            
            $id_array = explode(',', $ids);
            $placeholders = str_repeat('?,', count($id_array) - 1) . '?';
            
            $sql = "UPDATE room_types SET is_active = ? WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            
            $params = array_merge([$is_active], $id_array);
            $stmt->bind_param(str_repeat('i', count($params)), ...$params);
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                sendResponse(true, "Successfully updated $affected_rows room type(s)!");
            } else {
                sendResponse(false, 'Error updating room types: ' . $stmt->error);
            }
            break;
            
        default:
            sendResponse(false, 'Invalid action for room type module');
    }
}
?>