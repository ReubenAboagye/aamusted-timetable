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

// Try to determine the class' department and level band (via program -> department and level)
$class_department_id = null;
$class_level_band = null;
$cstmt = $conn->prepare("SELECT p.department_id, l.name AS level_name FROM classes c LEFT JOIN programs p ON c.program_id = p.id LEFT JOIN levels l ON c.level_id = l.id WHERE c.id = ? LIMIT 1");
if ($cstmt) {
    $cstmt->bind_param('i', $class_id);
    $cstmt->execute();
    $cres = $cstmt->get_result();
    if ($cres && $row = $cres->fetch_assoc()) {
        $class_department_id = $row['department_id'] !== null ? (int)$row['department_id'] : null;
        // derive level band from level_name by extracting first number and mapping to 100s
        $level_name = $row['level_name'] ?? '';
        if (preg_match('/(\d{1,3})/', $level_name, $lm)) {
            $num = (int)$lm[1];
            if ($num > 0 && $num < 10) $num = $num * 100;
            $class_level_band = (int)(floor($num / 100) * 100);
        }
    }
    $cstmt->close();
}

// annotate available and assigned courses with their detected level band to help client-side filtering
foreach ($available as &$ac) {
    $ac['level_band'] = null;
    $ac['course_semester'] = null;
    $code = $ac['course_code'] ?? '';
    $name = $ac['course_name'] ?? '';
    if (preg_match('/(\d{3})/', $code, $m) || preg_match('/(\d{3})/', $name, $m)) {
        $num = (int)$m[1];
        $ac['level_band'] = (int)(floor($num / 100) * 100);
        // semester encoded as the middle digit of the 3-digit number, e.g., 356 -> 5
        $middle = substr($m[1], 1, 1);
        if (is_numeric($middle)) {
            $ac['course_semester'] = (int)$middle;
            // academic semester: odd => 1, even => 2
            $ac['academic_semester'] = ($ac['course_semester'] % 2 === 1) ? 1 : 2;
        }
    } elseif (preg_match('/(\d)/', $code, $m) || preg_match('/(\d)/', $name, $m)) {
        $num = (int)$m[1];
        if ($num > 0 && $num < 10) $num = $num * 100;
        $ac['level_band'] = (int)(floor($num / 100) * 100);
    }
}
unset($ac);

foreach ($assigned as &$ac) {
    $ac['level_band'] = null;
    $ac['course_semester'] = null;
    $code = $ac['course_code'] ?? '';
    $name = $ac['course_name'] ?? '';
    if (preg_match('/(\d{3})/', $code, $m) || preg_match('/(\d{3})/', $name, $m)) {
        $num = (int)$m[1];
        $ac['level_band'] = (int)(floor($num / 100) * 100);
        $middle = substr($m[1], 1, 1);
        if (is_numeric($middle)) {
            $ac['course_semester'] = (int)$middle;
            $ac['academic_semester'] = ($ac['course_semester'] % 2 === 1) ? 1 : 2;
        }
    } elseif (preg_match('/(\d)/', $code, $m) || preg_match('/(\d)/', $name, $m)) {
        $num = (int)$m[1];
        if ($num > 0 && $num < 10) $num = $num * 100;
        $ac['level_band'] = (int)(floor($num / 100) * 100);
    }
}
unset($ac);

echo json_encode(['success' => true, 'data' => ['available_courses' => $available, 'assigned_courses' => $assigned, 'class_department_id' => $class_department_id, 'class_level_band' => $class_level_band]]);


