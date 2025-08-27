<?php
include 'connect.php';

$id = $_GET['session_id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo 'missing id';
    exit;
}

$stmt = $conn->prepare("DELETE FROM sessions WHERE id = ?");
$stmt->bind_param('i', $id);
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


