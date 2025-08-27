<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

include 'connect.php';

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
	die('Error: No file uploaded or upload error occurred.');
}

$file = $_FILES['file'];
$fileName = $file['name'];
$tmp = $file['tmp_name'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if (!in_array($ext, ['xls','xlsx'])) {
	die('Error: Only Excel files (.xls, .xlsx) are allowed.');
}

try {
	$spreadsheet = IOFactory::load($tmp);
	$worksheet = $spreadsheet->getActiveSheet();
	$rows = $worksheet->toArray();
	if (!$rows || count($rows) < 2) {
		die('Error: No data rows found.');
	}
	// Assume first row is header with columns: Name, Year Number
	array_shift($rows);
	$success = 0;
	$errors = 0;
	foreach ($rows as $index => $row) {
		if (!array_filter($row)) continue; // skip empty
		$name = trim((string)($row[0] ?? ''));
		$yearNumberRaw = $row[1] ?? '';
		$yearNumber = is_numeric($yearNumberRaw) ? (int)$yearNumberRaw : (int)preg_replace('/[^0-9]/','',$yearNumberRaw);
		if ($name === '' || $yearNumber <= 0) { $errors++; continue; }
		// Check duplicates by year_number or name
		$check = $conn->prepare('SELECT id FROM levels WHERE year_number = ? OR name = ?');
		$check->bind_param('is', $yearNumber, $name);
		$check->execute();
		if ($check->get_result()->num_rows > 0) { $check->close(); continue; }
		$check->close();
		$stmt = $conn->prepare('INSERT INTO levels (name, year_number) VALUES (?, ?)');
		if (!$stmt) { $errors++; continue; }
		$stmt->bind_param('si', $name, $yearNumber);
		if ($stmt->execute()) { $success++; } else { $errors++; }
		$stmt->close();
	}
	echo "<h2>Import Results</h2>";
	echo "<p><strong>Successfully imported:</strong> {$success} levels</p>";
	echo "<p><strong>Errors/Skipped:</strong> {$errors}</p>";
	echo "<p><a href='levels.php'>Back to Levels</a></p>";
} catch (Exception $e) {
	die('Error processing file: ' . $e->getMessage());
}

$conn->close();
?>

