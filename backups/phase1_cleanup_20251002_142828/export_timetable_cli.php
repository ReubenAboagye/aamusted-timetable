<?php
// CLI wrapper to export CSV for a given class_id using export_timetable.php logic
// Usage: php export_timetable_cli.php <class_id>
if (php_sapi_name() !== 'cli') { http_response_code(400); echo "CLI only\n"; exit(1); }

$classId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($classId <= 0) { fwrite(STDERR, "Usage: php export_timetable_cli.php <class_id>\n" ); exit(1); }

// Emulate GET params
$_GET['class_id'] = $classId;
$_GET['format'] = 'csv';
$_GET['export'] = '1';

// Run export_timetable.php and stream CSV to STDOUT
include __DIR__ . '/export_timetable.php';
?>


