<?php
require 'connect.php';
$out = array();
$res = $conn->query('SELECT COUNT(*) as c FROM class_courses WHERE is_active=1');
$out['total_active'] = $res ? $res->fetch_assoc()['c'] : 0;
$res = $conn->query('SELECT COUNT(*) as c FROM class_courses cc JOIN classes c ON cc.class_id=c.id WHERE cc.is_active=1 AND c.stream_id=1');
$out['stream1_active'] = $res ? $res->fetch_assoc()['c'] : 0;
$res = $conn->query('SELECT cc.id,cc.class_id,cc.course_id,cc.lecturer_id,cc.semester,co.code FROM class_courses cc JOIN classes c ON cc.class_id=c.id JOIN courses co ON cc.course_id=co.id WHERE c.stream_id=1 LIMIT 50');
$rows = array();
if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
$out['sample_stream1'] = $rows;
echo json_encode($out);
?>
