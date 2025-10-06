<?php
// Start output buffering to catch any unexpected output
ob_start();

// Disable custom error handler for AJAX requests to prevent HTML output
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // This is an AJAX request, don't use custom error handler
} else {
    // Include custom error handler for better error display
    include_once 'includes/custom_error_handler.php';
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log the request for debugging
error_log("AJAX Request: " . print_r($_POST, true));

// Clear any output buffer content
ob_clean();

header('Content-Type: application/json');
include 'connect.php';

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';
    
    // Debug: Log the action
    error_log("AJAX Action: " . $action);

    if ($action === 'add') {
        // Validate required fields
        $required_fields = ['name', 'department_id', 'duration'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $name = trim($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $code = trim($_POST['code']);
        $duration = (int)$_POST['duration'];
        $is_active = isset($_POST['is_active']) ? 1 : 1; // Default to active for new programs

        // Validate department exists
        $dept_check = $conn->prepare("SELECT id FROM departments WHERE id = ? AND is_active = 1");
        if (!$dept_check) {
            throw new Exception("Database error: " . $conn->error);
        }
        $dept_check->bind_param("i", $department_id);
        $dept_check->execute();
        $dept_res = $dept_check->get_result();
        if ($dept_res->num_rows === 0) {
            throw new Exception("Selected department does not exist");
        }
        $dept_check->close();

        // Check for duplicate name in same department
        $check_sql = "SELECT id FROM programs WHERE name = ? AND department_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $check_stmt->bind_param("si", $name, $department_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            throw new Exception("Program with this name already exists in the selected department");
        }
        $check_stmt->close();

        // Check for duplicate code
        $check_code_sql = "SELECT id FROM programs WHERE code = ?";
        $check_code_stmt = $conn->prepare($check_code_sql);
        if (!$check_code_stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $check_code_stmt->bind_param("s", $code);
        $check_code_stmt->execute();
        $check_code_result = $check_code_stmt->get_result();

        if ($check_code_result->num_rows > 0) {
            throw new Exception("Program code already exists");
        }
        $check_code_stmt->close();

        // Insert the program
        $sql = "INSERT INTO programs (name, department_id, code, duration_years, is_active, stream_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        // Get current stream_id from session or default to 1
        $current_stream_id = $_SESSION['current_stream_id'] ?? 1;
        $stmt->bind_param("sisiii", $name, $department_id, $code, $duration, $is_active, $current_stream_id);
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();
            
            // Get the newly created program with department name
            $get_program_sql = "SELECT p.*, d.name as department_name 
                               FROM programs p 
                               LEFT JOIN departments d ON p.department_id = d.id 
                               WHERE p.id = ?";
            $get_stmt = $conn->prepare($get_program_sql);
            $get_stmt->bind_param("i", $new_id);
            $get_stmt->execute();
            $program_data = $get_stmt->get_result()->fetch_assoc();
            $get_stmt->close();
            
            $response['success'] = true;
            $response['message'] = 'Program added successfully!';
            $response['data'] = $program_data;
            error_log("Program added successfully: ID=$new_id, Name=$name");
        } else {
            throw new Exception("Error adding program: " . $stmt->error);
        }

    } elseif ($action === 'edit') {
        // Validate required fields
        $required_fields = ['id', 'name', 'department_id', 'duration'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $department_id = (int)$_POST['department_id'];
        $code = trim($_POST['code'] ?? '');
        $duration = (int)$_POST['duration'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Simple update without complex validation for now
        // Check if stream_id column exists in the database
        $check_stream_column = $conn->query("SHOW COLUMNS FROM programs LIKE 'stream_id'");
        $has_stream_column = $check_stream_column && $check_stream_column->num_rows > 0;
        
        // For edit operations, we don't need to filter by stream_id since we're updating a specific program by ID
        $sql = "UPDATE programs SET name = ?, department_id = ?, code = ?, duration_years = ?, is_active = ? WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        // Get current stream_id from session or default to 1
        $current_stream_id = $_SESSION['current_stream_id'] ?? 1;
        
        // Debug logging
        error_log("Edit Program Debug - ID: $id, Name: '$name', Dept: $department_id, Code: '$code', Duration: $duration, Active: $is_active");
        
        $stmt->bind_param("sisiii", $name, $department_id, $code, $duration, $is_active, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Get the updated program with department name
            $get_program_sql = "SELECT p.*, d.name as department_name 
                               FROM programs p 
                               LEFT JOIN departments d ON p.department_id = d.id 
                               WHERE p.id = ?";
            $get_stmt = $conn->prepare($get_program_sql);
            $get_stmt->bind_param("i", $id);
            $get_stmt->execute();
            $program_data = $get_stmt->get_result()->fetch_assoc();
            $get_stmt->close();
            
            $response['success'] = true;
            $response['message'] = 'Program updated successfully!';
            $response['data'] = $program_data;
            error_log("Program updated successfully: ID=$id, Name=$name");
        } else {
            error_log("Program update failed: " . $stmt->error);
            throw new Exception("Error updating program: " . $stmt->error);
        }

    } elseif ($action === 'delete') {
        if (!isset($_POST['id'])) {
            throw new Exception("Missing required field: id");
        }

        $id = (int)$_POST['id'];
        
        // Get current stream_id from session or default to 1
        $current_stream_id = $_SESSION['current_stream_id'] ?? 1;

        // Check if stream_id column exists in the database
        $check_stream_column = $conn->query("SHOW COLUMNS FROM programs LIKE 'stream_id'");
        $has_stream_column = $check_stream_column && $check_stream_column->num_rows > 0;

        // Soft delete: set is_active = 0
        $sql = "UPDATE programs SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $response['success'] = true;
            $response['message'] = 'Program deleted successfully!';
            error_log("Program deleted successfully: ID=$id");
        } else {
            throw new Exception("Error deleting program: " . $stmt->error);
        }
        $stmt->close();

    } elseif ($action === 'get_programs') {
        // Get current stream_id from session or default to 1
        $current_stream_id = $_SESSION['current_stream_id'] ?? 1;
        
        // Check if stream_id column exists in the database
        $check_stream_column = $conn->query("SHOW COLUMNS FROM programs LIKE 'stream_id'");
        $has_stream_column = $check_stream_column && $check_stream_column->num_rows > 0;
        
        if ($has_stream_column) {
            // Get all programs with department names for current stream (both active and inactive)
            $sql = "SELECT p.*, d.name as department_name 
                    FROM programs p 
                    LEFT JOIN departments d ON p.department_id = d.id 
                    WHERE p.stream_id = ?
                    ORDER BY p.is_active DESC, p.name";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            $stmt->bind_param("i", $current_stream_id);
        } else {
            // Get all programs with department names (both active and inactive) - no stream filtering
            $sql = "SELECT p.*, d.name as department_name 
                    FROM programs p 
                    LEFT JOIN departments d ON p.department_id = d.id 
                    ORDER BY p.is_active DESC, p.name";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            throw new Exception("Error fetching programs: " . $conn->error);
        }
        
        $programs = [];
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }
        
        $response['success'] = true;
        $response['data'] = $programs;

    } elseif ($action === 'get_departments') {
        // Get all active departments
        $sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Error fetching departments: " . $conn->error);
        }
        
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        
        $response['success'] = true;
        $response['data'] = $departments;

    } elseif ($action === 'toggle_status') {
        if (!isset($_POST['id']) || !isset($_POST['is_active'])) {
            throw new Exception("Missing required fields: id and is_active");
        }

        $id = (int)$_POST['id'];
        $is_active = (int)$_POST['is_active'];
        
        // Get current stream_id from session or default to 1
        $current_stream_id = $_SESSION['current_stream_id'] ?? 1;

        // Check if stream_id column exists in the database
        $check_stream_column = $conn->query("SHOW COLUMNS FROM programs LIKE 'stream_id'");
        $has_stream_column = $check_stream_column && $check_stream_column->num_rows > 0;

        // Update the program status
        $sql = "UPDATE programs SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("ii", $is_active, $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            $action_text = $is_active ? 'activated' : 'deactivated';
            $response['success'] = true;
            $response['message'] = "Program {$action_text} successfully!";
            error_log("Program status toggled successfully: ID=$id, Status=$is_active");
        } else {
            throw new Exception("Error updating program status: " . $stmt->error);
        }
        $stmt->close();

    } else {
        throw new Exception("Invalid action: $action");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("AJAX Programs Error: " . $e->getMessage());
}

// Clear any output buffer and send clean JSON
ob_clean();
echo json_encode($response);
exit;
?>
