<?php
header('Content-Type: application/json');
include 'connect.php';

$stream_id = isset($_GET['stream_id']) ? (int)$_GET['stream_id'] : 0;
if ($stream_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT time_slot_id FROM stream_time_slots WHERE stream_id = ? AND is_active = 1");
$stmt->bind_param('i', $stream_id);
$stmt->execute();
$res = $stmt->get_result();
$ids = [];
while ($r = $res->fetch_assoc()) { $ids[] = (int)$r['time_slot_id']; }
$stmt->close();

echo json_encode($ids);
