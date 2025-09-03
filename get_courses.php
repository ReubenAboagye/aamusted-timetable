<?php
header('Content-Type: application/json');
include 'connect.php';

// Include stream manager for proper filtering
if (file_exists(__DIR__ . '/includes/stream_manager.php')) {
    include_once __DIR__ . '/includes/stream_manager.php';
    $streamManager = getStreamManager();
    $current_stream_id = $streamManager->getCurrentStreamId();
} else {
    // Fallback if stream manager doesn't exist
    session_start();
    $current_stream_id = $_SESSION['current_stream_id'] ?? 1;
}

$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0; // still supported but optional
$level = isset($_GET['level']) ? (int)$_GET['level'] : 0;

$query = "SELECT id, code, name FROM courses WHERE is_active = 1 AND stream_id = ?";
$params = [$current_stream_id];
$types = 'i';

if ($department_id > 0) {
    $query .= " AND department_id = ?";
    $params[] = $department_id;
    $types .= 'i';
}

if ($level > 0) {
    $query .= " AND level = ?";
    $params[] = $level;
    $types .= 'i';
}

$query .= " ORDER BY code";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = [
            'id' => $row['id'],
            'code' => htmlspecialchars($row['code']),
            'name' => htmlspecialchars($row['name'])
        ];
    }
    $stmt->close();
    
    echo json_encode($courses);
} else {
    echo json_encode([]);
}
?>
