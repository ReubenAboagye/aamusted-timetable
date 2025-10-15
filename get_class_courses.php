<?php
require_once 'connect.php';
// Stream helpers
if (file_exists(__DIR__ . '/includes/stream_validation.php')) include_once __DIR__ . '/includes/stream_validation.php';
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';

header('Content-Type: application/json');

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid class id']);
    exit;
}

// Determine the class' stream to constrain available list correctly
$class_stream_id = null;
$cs = $conn->prepare("SELECT stream_id FROM classes WHERE id = ? LIMIT 1");
if ($cs) {
    $cs->bind_param('i', $class_id);
    $cs->execute();
    $cres = $cs->get_result();
    if ($cres && $row = $cres->fetch_assoc()) {
        $class_stream_id = (int)$row['stream_id'];
    }
    $cs->close();
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
// Constrain by class stream if known
if (!empty($class_stream_id)) {
    $sql .= " AND stream_id = " . (int)$class_stream_id;
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
    $ac['academic_semester'] = null;
    $code = $ac['course_code'] ?? '';
    
    // Extract level and semester from 3-digit course code
    if (preg_match('/(\d{3})/', $code, $m)) {
        $threeDigit = $m[1];
        $firstDigit = (int)substr($threeDigit, 0, 1);
        $secondDigit = (int)substr($threeDigit, 1, 1);
        
        // Level: first digit * 100 (e.g., 356 -> level 300)
        $ac['level_band'] = $firstDigit * 100;
        
        // Semester: second digit (odd=1, even=2)
        $ac['course_semester'] = $secondDigit;
        $ac['academic_semester'] = ($secondDigit % 2 === 1) ? 1 : 2;
    }
}
unset($ac);

foreach ($assigned as &$ac) {
    $ac['level_band'] = null;
    $ac['course_semester'] = null;
    $ac['academic_semester'] = null;
    $code = $ac['course_code'] ?? '';
    
    // Extract level and semester from 3-digit course code
    if (preg_match('/(\d{3})/', $code, $m)) {
        $threeDigit = $m[1];
        $firstDigit = (int)substr($threeDigit, 0, 1);
        $secondDigit = (int)substr($threeDigit, 1, 1);
        
        // Level: first digit * 100 (e.g., 356 -> level 300)
        $ac['level_band'] = $firstDigit * 100;
        
        // Semester: second digit (odd=1, even=2)
        $ac['course_semester'] = $secondDigit;
        $ac['academic_semester'] = ($secondDigit % 2 === 1) ? 1 : 2;
    }
}
unset($ac);

echo json_encode(['success' => true, 'data' => ['available_courses' => $available, 'assigned_courses' => $assigned, 'class_department_id' => $class_department_id, 'class_level_band' => $class_level_band]]);


