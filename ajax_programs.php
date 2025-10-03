<?php
// Include custom error handler for better error display
include_once 'includes/custom_error_handler.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log the request for debugging
error_log("AJAX Request: " . print_r($_POST, true));

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
        $sql = "INSERT INTO programs (name, department_id, code, duration_years, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("sisii", $name, $department_id, $code, $duration, $is_active);
        
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
        $code = trim($_POST['code']);
        $duration = (int)$_POST['duration'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Simple update without complex validation for now
        $sql = "UPDATE programs SET name = ?, department_id = ?, code = ?, duration_years = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $stmt->bind_param("sisii", $name, $department_id, $code, $duration, $id);
        
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
            throw new Exception("Error updating program: " . $stmt->error);
        }
        $stmt->close();

    } elseif ($action === 'delete') {
        if (!isset($_POST['id'])) {
            throw new Exception("Missing required field: id");
        }

        $id = (int)$_POST['id'];

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
        // Get all active programs with department names
        $sql = "SELECT p.*, d.name as department_name 
                FROM programs p 
                LEFT JOIN departments d ON p.department_id = d.id 
                WHERE p.is_active = 1 
                ORDER BY p.name";
        $result = $conn->query($sql);
        
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

    } else {
        throw new Exception("Invalid action: $action");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("AJAX Programs Error: " . $e->getMessage());
}

echo json_encode($response);
?>
