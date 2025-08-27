<?php
include 'connect.php';

$id = $_POST['session_id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo 'missing id';
    exit;
}

$academicYear = $_POST['academicYear'] ?? '';
$semester = $_POST['semester'] ?? '';
$startDate = $_POST['startDate'] ?? null;
$endDate = $_POST['endDate'] ?? null;

$stmt = $conn->prepare("UPDATE sessions SET academic_year=?, semester=?, start_date=?, end_date=? WHERE id=?");
$stmt->bind_param('ssssi', $academicYear, $semester, $startDate, $endDate, $id);
if ($stmt->execute()) {
    header('Location: sessions.php');
    exit;
} else {
    http_response_code(500);
    echo 'error: ' . $stmt->error;
}

$stmt->close();
$conn->close();
?>


