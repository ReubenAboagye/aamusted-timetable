<?php
require "connect.php";
$res = $conn->query("SELECT academic_year, COUNT(*) as c FROM timetable GROUP BY academic_year");
$out = array();
if ($res) { while ($r = $res->fetch_assoc()) { $out[] = $r; } }
echo json_encode($out);
?>
