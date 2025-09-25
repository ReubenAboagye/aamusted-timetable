<?php
// Simple AJAX test endpoint
ob_start();
header('Content-Type: application/json');

include 'connect.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function
function sendResponse($success, $message, $data = null) {
    ob_clean();
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST requests are allowed');
}

$action = $_POST['action'] ?? null;
$module = $_POST['module'] ?? null;

if (empty($action) || empty($module)) {
    sendResponse(false, 'Action and module are required');
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    sendResponse(false, 'CSRF token validation failed. Expected: ' . $_SESSION['csrf_token'] . ', Received: ' . ($_POST['csrf_token'] ?? 'none'));
}

try {
    if ($module === 'department' && $action === 'get_list') {
        $sql = "SELECT d.*, COUNT(c.id) as course_count FROM departments d LEFT JOIN courses c ON d.id = c.department_id GROUP BY d.id ORDER BY d.name";
        $result = $conn->query($sql);
        
        $departments = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $departments[] = $row;
            }
        }
        
        sendResponse(true, 'Departments retrieved successfully', $departments);
    } else {
        sendResponse(false, 'Invalid module or action');
    }
} catch (Exception $e) {
    sendResponse(false, 'An error occurred: ' . $e->getMessage());
}

sendResponse(false, 'Unknown error occurred');
?>