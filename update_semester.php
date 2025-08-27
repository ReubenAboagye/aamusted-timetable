<?php
// Handle semester updates
header('Content-Type: application/json');

// Include database connection
include 'connect.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // Get form data
    $semesterId = $_POST['semester_id'] ?? '';
    $semesterName = trim($_POST['semesterName'] ?? '');
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    
    // Validate required fields
    if (empty($semesterId) || empty($semesterName) || empty($startDate) || empty($endDate)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required.'
        ]);
        exit;
    }
    
    // Validate dates
    if (strtotime($startDate) >= strtotime($endDate)) {
        echo json_encode([
            'success' => false,
            'message' => 'End date must be after start date.'
        ]);
        exit;
    }
    
    try {
        // Prepare SQL statement
        $sql = "UPDATE semesters SET name = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        // Bind parameters
        $stmt->bind_param("sssii", $semesterName, $startDate, $endDate, $isActive, $semesterId);
        
        // Execute statement
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Semester updated successfully!'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No changes made or semester not found.'
                ]);
            }
        } else {
            throw new Exception("Database execute error: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating semester: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method or action.'
    ]);
}

$conn->close();
?>
