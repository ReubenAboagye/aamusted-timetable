<?php
require_once 'connect.php';

header('Content-Type: application/json');

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid class id']);
    exit;
}

// Fetch assigned courses for this class
$assigned = [];
$stmt = $conn->prepare("SELECT c.id, c.code AS course_code, c.name AS course_name, c.department_id FROM class_courses cc JOIN courses c ON cc.course_id = c.id WHERE cc.class_id = ? AND cc.is_active = 1 ORDER BY c.code");
if ($stmt) {
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $assigned[] = $r;
    }
    $stmt->close();
}

// Fetch available (not assigned) courses
$available = [];
$sql = "SELECT id, code AS course_code, name AS course_name, department_id FROM courses WHERE is_active = 1";
// Exclude assigned course ids
if (count($assigned) > 0) {
    $ids = array_map(function($c){ return (int)$c['id']; }, $assigned);
    $sql .= " AND id NOT IN (" . implode(',', $ids) . ")";
}
$sql .= " ORDER BY code";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $available[] = $r;
    }
}

// Try to determine the class' department (via program -> department)
$class_department_id = null;
$cstmt = $conn->prepare("SELECT p.department_id FROM classes c LEFT JOIN programs p ON c.program_id = p.id WHERE c.id = ? LIMIT 1");
if ($cstmt) {
    $cstmt->bind_param('i', $class_id);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    if ($cres && $row = $cres->fetch_assoc()) {
        $class_department_id = $row['department_id'] !== null ? (int)$row['department_id'] : null;
    }
    $cstmt->close();
}

echo json_encode(['success' => true, 'data' => ['available_courses' => $available, 'assigned_courses' => $assigned, 'class_department_id' => $class_department_id]]);


