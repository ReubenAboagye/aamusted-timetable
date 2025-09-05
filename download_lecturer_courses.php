<?php
// Note: This is a new file created to provide CSV exports of lecturer-course mappings.
include 'connect.php';

$selected_department = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// Build query similar to lecturer_courses.php
$mappings_query = "SELECT l.name AS lecturer_name, d.name AS department_name, GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ') AS course_codes
                  FROM lecturers l
                  LEFT JOIN departments d ON l.department_id = d.id
                  LEFT JOIN lecturer_courses lc ON l.id = lc.lecturer_id
                  LEFT JOIN courses c ON c.id = lc.course_id
                  WHERE l.is_active = 1";

$params = [];
$types = '';

if ($selected_department > 0) {
    $mappings_query .= " AND l.department_id = ?";
    $params[] = $selected_department;
    $types .= 'i';
}

if (!empty($search_name)) {
    $mappings_query .= " AND l.name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $types .= 's';
}

$mappings_query .= " GROUP BY l.id, l.name, d.name ORDER BY l.name";

// Execute
if (!empty($params)) {
    $stmt = $conn->prepare($mappings_query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        http_response_code(500);
        echo 'Query prepare failed';
        exit;
    }
} else {
    $result = $conn->query($mappings_query);
}

// Output CSV headers
$filename = 'lecturer_course_mappings_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    echo 'Unable to open output stream';
    exit;
}

// Write header row
fputcsv($out, ['Lecturer', 'Department', 'Course Codes']);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [ $row['lecturer_name'], $row['department_name'], $row['course_codes'] ]);
    }
}

fclose($out);
exit;
?>
