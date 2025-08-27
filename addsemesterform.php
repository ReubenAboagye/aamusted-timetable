<?php
// Handle semester form submission
header('Content-Type: application/json');

// Include database connection
include 'connect.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $semesterName = trim($_POST['semesterName'] ?? '');
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    
    // Validate required fields
    if (empty($semesterName) || empty($startDate) || empty($endDate)) {
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
        $sql = "INSERT INTO semesters (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        // Bind parameters
        $stmt->bind_param("sssi", $semesterName, $startDate, $endDate, $isActive);
        
        // Execute statement
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Semester added successfully!'
            ]);
        } else {
            throw new Exception("Database execute error: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding semester: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

$conn->close();
?>
