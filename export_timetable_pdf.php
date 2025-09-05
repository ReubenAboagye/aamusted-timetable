<?php
// Standalone endpoint to export timetable as branded PDF (no layout includes)
include 'connect.php';

// Buffer to prevent accidental output before headers
if (!ob_get_level()) { ob_start(); }

// Fallback autoloader for Dompdf in case Composer didn't register it
spl_autoload_register(function($class){
    if (strpos($class, 'Dompdf\\') === 0) {
        $relative = substr($class, strlen('Dompdf\\'));
        $path = __DIR__ . '/vendor/dompdf/dompdf/src/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

$selected_stream = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$role = isset($_GET['role']) ? $_GET['role'] : '';
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$lecturer_id = isset($_GET['lecturer_id']) ? intval($_GET['lecturer_id']) : 0;

if (!($selected_stream > 0 && $selected_semester > 0 && in_array($role, ['class','department','lecturer']))) {
    http_response_code(400);
    echo 'Missing required parameters';
    exit;
}

// Detect schema variants
$has_class_course = false;
$has_lecturer_course = false;
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
if ($col && $col->num_rows > 0) { $has_class_course = true; }
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'lecturer_course_id'");
if ($col && $col->num_rows > 0) { $has_lecturer_course = true; }

$select_parts = [
    "d.name AS day_name",
    "ts.start_time",
    "ts.end_time",
    "c.name AS class_name",
    "co.code AS course_code",
    "co.name AS course_name",
    "IFNULL(l.name, '') AS lecturer_name",
    "r.name AS room_name",
    "r.capacity AS room_capacity"
];

$joins = [];
if ($has_class_course) {
    $joins[] = "JOIN class_courses cc ON t.class_course_id = cc.id";
    $joins[] = "JOIN classes c ON cc.class_id = c.id";
    $joins[] = "JOIN courses co ON cc.course_id = co.id";
} else {
    $joins[] = "JOIN classes c ON t.class_id = c.id";
    $joins[] = "JOIN courses co ON t.course_id = co.id";
}

if ($has_lecturer_course) {
    $joins[] = "LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id";
    $joins[] = "LEFT JOIN lecturers l ON lc.lecturer_id = l.id";
} else {
    $joins[] = "LEFT JOIN lecturers l ON t.lecturer_id = l.id";
}

$joins[] = "JOIN days d ON t.day_id = d.id";
$joins[] = "JOIN time_slots ts ON t.time_slot_id = ts.id";
$joins[] = "JOIN rooms r ON t.room_id = r.id";

$where = ["c.stream_id = ?", "t.semester = ?"];
$params = [$selected_stream, $selected_semester];
$types = 'ii';

if ($role === 'class' && $class_id > 0) {
    $where[] = "c.id = ?";
    $params[] = $class_id;
    $types .= 'i';
}
if ($role === 'department' && $department_id > 0) {
    $joins[] = "JOIN programs p ON c.program_id = p.id";
    $where[] = "p.department_id = ?";
    $params[] = $department_id;
    $types .= 'i';
}
if ($role === 'lecturer' && $lecturer_id > 0) {
    $where[] = "l.id = ?";
    $params[] = $lecturer_id;
    $types .= 'i';
}

$sql = "SELECT " . implode(",\n    ", $select_parts) . "\nFROM timetable t\n    " . implode("\n    ", $joins) . "\nWHERE " . implode(" AND ", $where) . "\nORDER BY d.id, ts.start_time, c.name, co.code";

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
}

// Resolve labels
$stream_name = '';
if ($selected_stream > 0) {
    $ss = $conn->prepare("SELECT name, code FROM streams WHERE id = ?");
    if ($ss) { $ss->bind_param('i', $selected_stream); $ss->execute(); $sr = $ss->get_result(); if ($sr && ($row = $sr->fetch_assoc())) { $stream_name = $row['name'] . (!empty($row['code']) ? (' (' . $row['code'] . ')') : ''); } $ss->close(); }
}
$role_title = ($role === 'class' ? 'Class' : ($role === 'department' ? 'Department' : 'Lecturer'));
$filter_value = '';
if ($role === 'class' && $class_id) { $qr = $conn->prepare("SELECT name FROM classes WHERE id = ?"); if ($qr) { $qr->bind_param('i', $class_id); $qr->execute(); $rs = $qr->get_result(); if ($rs && ($r = $rs->fetch_assoc())) { $filter_value = $r['name']; } $qr->close(); } }
if ($role === 'department' && $department_id) { $qr = $conn->prepare("SELECT name FROM departments WHERE id = ?"); if ($qr) { $qr->bind_param('i', $department_id); $qr->execute(); $rs = $qr->get_result(); if ($rs && ($r = $rs->fetch_assoc())) { $filter_value = $r['name']; } $qr->close(); } }
if ($role === 'lecturer' && $lecturer_id) { $qr = $conn->prepare("SELECT name FROM lecturers WHERE id = ?"); if ($qr) { $qr->bind_param('i', $lecturer_id); $qr->execute(); $rs = $qr->get_result(); if ($rs && ($r = $rs->fetch_assoc())) { $filter_value = $r['name']; } $qr->close(); } }

// Build HTML for PDF
$logoPathPreferred = __DIR__ . '/images/aamusted-logo.png';
$logoPathFallback  = __DIR__ . '/images/aamustedLog.png';
$logoDataUri = '';
if (file_exists($logoPathPreferred)) { $bin = @file_get_contents($logoPathPreferred); if ($bin !== false) { $logoDataUri = 'data:image/png;base64,' . base64_encode($bin); } }
elseif (file_exists($logoPathFallback)) { $bin = @file_get_contents($logoPathFallback); if ($bin !== false) { $logoDataUri = 'data:image/png;base64,' . base64_encode($bin); } }

$safeTitle = htmlspecialchars($stream_name !== '' ? $stream_name : 'Stream', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeRole = htmlspecialchars($role_title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeFilter = htmlspecialchars($filter_value !== '' ? $filter_value : 'All', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeSemester = htmlspecialchars((string)$selected_semester, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$rowsHtml = '';
// Group by day to calculate rowspans
$dayToRows = [];
foreach ($rows as $r) {
    $dayToRows[$r['day_name']][] = $r;
}

foreach ($dayToRows as $dayName => $dayRows) {
    $rowspan = count($dayRows);
    $first = true;
    foreach ($dayRows as $r) {
        $rowsHtml .= '<tr>';
        if ($first) {
            $rowsHtml .= '<td rowspan="' . intval($rowspan) . '">' . htmlspecialchars($dayName) . '</td>';
            $first = false;
        }
        $rowsHtml
            .= '<td>' . htmlspecialchars($r['start_time']) . '</td>'
            . '<td>' . htmlspecialchars($r['end_time']) . '</td>'
            . '<td><strong>' . htmlspecialchars($r['class_name']) . '</strong></td>'
            . '<td>' . htmlspecialchars(($r['course_code'] ? ($r['course_code'] . ' - ') : '') . $r['course_name']) . '</td>'
            . '<td>' . htmlspecialchars($r['lecturer_name']) . '</td>'
            . '<td>' . htmlspecialchars($r['room_name']) . '</td>'
            . '</tr>';
    }
}

$html = '<html><head><meta charset="UTF-8"><style>
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; }
    .header { text-align:center; border-bottom:2px solid #800020; padding-bottom:10px; margin-bottom:10px; }
    .header img { height:58px; display:block; margin:0 auto 6px; }
    .title { color:#800020; margin:0; font-size:20px; font-weight:700; text-align:center; }
    .subtitle { margin:4px 0 0; color:#444; font-size:12px; text-align:center; }
    .meta { margin:10px 0 14px; font-size:12px; text-align:center; }
    .meta span { display:inline-block; margin:0 10px; }
    table { width:100%; border-collapse:collapse; }
    th, td { border:1px solid #ddd; padding:6px 8px; }
    thead th { background:#f2f2f2; font-weight:600; }
    </style></head><body>'
    . '<div class="header">'
    . ($logoDataUri !== '' ? ('<img src="' . htmlspecialchars($logoDataUri) . '" />') : '')
    . '<h1 class="title">AKENTEN APPIAH-MENKA UNIVERSITY</h1>'
    . '<div class="subtitle">Timetable - ' . $safeTitle . ' | Semester ' . $safeSemester . '</div>'
    . '</div>'
    . '<div class="meta"><span><strong>Role:</strong> ' . $safeRole . '</span><span><strong>Filter:</strong> ' . $safeFilter . '</span><span><strong>Generated:</strong> ' . htmlspecialchars(date('Y-m-d H:i')) . '</span></div>'
    . '<table><thead><tr><th>Day</th><th>Start</th><th>End</th><th>Class</th><th>Course</th><th>Lecturer</th><th>Room</th></tr></thead><tbody>'
    . $rowsHtml
    . '</tbody></table></body></html>';

// Clean buffers to avoid mixing output
while (ob_get_level() > 0) { @ob_end_clean(); }

$options = new Dompdf\Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('timetable.pdf', ['Attachment' => true]);
exit;

