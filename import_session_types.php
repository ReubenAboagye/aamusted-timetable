<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

include 'connect.php';

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
	die('Error: No file uploaded or upload error occurred.');
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xls','xlsx'])) { die('Error: Only Excel files (.xls, .xlsx) are allowed.'); }

try {
	$spreadsheet = IOFactory::load($file['tmp_name']);
	$worksheet = $spreadsheet->getActiveSheet();
	$rows = $worksheet->toArray();
	if (!$rows || count($rows) < 2) { die('Error: No data rows found.'); }
	array_shift($rows); // headers, assume first column Name
	$success = 0; $skipped = 0;
	foreach ($rows as $row) {
		if (!array_filter($row)) continue;
		$name = trim((string)($row[0] ?? ''));
		if ($name === '') { $skipped++; continue; }
		$check = $conn->prepare('SELECT id FROM session_types WHERE name = ?');
		$check->bind_param('s', $name);
		$check->execute();
		if ($check->get_result()->num_rows > 0) { $check->close(); $skipped++; continue; }
		$check->close();
		$stmt = $conn->prepare('INSERT INTO session_types (name) VALUES (?)');
		if (!$stmt) { $skipped++; continue; }
		$stmt->bind_param('s', $name);
		if ($stmt->execute()) { $success++; } else { $skipped++; }
		$stmt->close();
	}
	echo "<h2>Import Results</h2>";
	echo "<p><strong>Successfully imported:</strong> {$success} session types</p>";
	echo "<p><strong>Errors/Skipped:</strong> {$skipped}</p>";
	echo "<p><a href='session_types.php'>Back to Session Types</a></p>";
} catch (Exception $e) {
	die('Error processing file: ' . $e->getMessage());
}

$conn->close();
?>

