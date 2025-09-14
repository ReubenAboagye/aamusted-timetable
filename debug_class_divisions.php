<?php
include 'connect.php';

$target = isset($argv[1]) ? trim($argv[1]) : '';
$where = '';
if ($target !== '') {
    $safe = $conn->real_escape_string($target);
    $where = "WHERE c.name LIKE '%$safe%'";
}

echo "Classes and divisions_count:\n";
$sql = "SELECT c.id, c.name, c.divisions_count, c.total_capacity FROM classes c $where ORDER BY c.name LIMIT 50";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . "\t" . $row['name'] . "\tdivisions=" . ($row['divisions_count'] ?? 'NULL') . "\tcapacity=" . ($row['total_capacity'] ?? 'NULL') . "\n";
}

echo "\nDivision label counts in timetable per class (NULL shown as <none>):\n";
$sql2 = "SELECT c.id, c.name, IFNULL(t.division_label,'<none>') AS label, COUNT(*) AS cnt
         FROM timetable t
         JOIN class_courses cc ON t.class_course_id = cc.id
         JOIN classes c ON cc.class_id = c.id
         " . ($where ? str_replace('c.name', 'c.name', $where) : '') .
        " GROUP BY c.id, c.name, label ORDER BY c.name, label LIMIT 200";
$res2 = $conn->query($sql2);
while ($row = $res2->fetch_assoc()) {
    echo $row['id'] . "\t" . $row['name'] . "\tlabel=" . $row['label'] . "\tcnt=" . $row['cnt'] . "\n";
}
?>


