<?php
include 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $academicYear = $_POST['academicYear'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $semesterName = $_POST['semester_name'] ?? '';
    $startDate = $_POST['startDate'] ?? null;
    $endDate = $_POST['endDate'] ?? null;
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    
    // Validate required fields
    if (empty($academicYear) || empty($semester) || empty($semesterName)) {
        echo json_encode(['success' => false, 'message' => 'Academic Year, Semester, and Semester Name are required']);
        exit;
    }
    
    // Check if session already exists for this academic year and semester
    $checkSql = "SELECT id FROM sessions WHERE academic_year = ? AND semester_number = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ss", $academicYear, $semester);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A session for this academic year and semester already exists']);
        exit;
    }
    
    // Insert new session
    $insertSql = "INSERT INTO sessions (academic_year, semester_number, semester_name, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?)";
    
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("sssssi", $academicYear, $semester, $semesterName, $startDate, $endDate, $isActive);
    
    if ($insertStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Session added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding session: ' . $conn->error]);
    }
    
    $insertStmt->close();
    $checkStmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>


