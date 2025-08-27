<?php
// Endpoint: parse uploaded CSV/XLSX and return JSON preview
// Suppress error output to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Simple response helper
function resp($arr) { 
    // Clear any output buffers
    while (ob_get_level()) ob_end_clean();
    echo json_encode($arr); 
    exit; 
}

// Check if vendor/autoload.php exists
if (!file_exists('vendor/autoload.php')) {
    resp(['success'=>false, 'error'=>'PhpSpreadsheet not installed. Please run: composer install']);
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
    resp(['success'=>false, 'error'=>'No file uploaded or upload error: ' . ($_FILES['file']['error'] ?? 'unknown')]);
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['xls','xlsx','csv'];
if (!in_array($ext, $allowed)) {
    resp(['success'=>false, 'error'=>'Unsupported file type: ' . $ext . '. Please use .xls, .xlsx, or .csv']);
}

// Check file size (limit to 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    resp(['success'=>false, 'error'=>'File too large. Maximum size is 10MB']);
}

// Move to temp folder
$tmpDir = sys_get_temp_dir();
$tmpPath = tempnam($tmpDir, 'imp_');
if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
    resp(['success'=>false, 'error'=>'Failed to move uploaded file']);
}

try {
    // For Excel files, explicitly set the reader type
    if ($ext === 'xlsx') {
        $reader = IOFactory::createReader('Xlsx');
    } elseif ($ext === 'xls') {
        $reader = IOFactory::createReader('Xls');
    } elseif ($ext === 'csv') {
        $reader = IOFactory::createReader('Csv');
        // Try to detect delimiter automatically
        $reader->setDelimiter(',');
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);
    } else {
        // Fallback to auto-detection
        $reader = IOFactory::createReaderForFile($tmpPath);
    }
    
    // Load the spreadsheet
    $spreadsheet = $reader->load($tmpPath);
    $sheet = $spreadsheet->getActiveSheet();

    // Read header (first row) and up to first 200 data rows for preview
    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = $sheet->getHighestDataColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    // Ensure we have at least one column
    if ($highestColumnIndex < 1) {
        resp(['success'=>false, 'error'=>'No data found in file']);
    }

    $headers = [];
    for ($c = 1; $c <= $highestColumnIndex; $c++) {
        $cellValue = $sheet->getCellByColumnAndRow($c, 1)->getFormattedValue();
        $headers[] = (string) ($cellValue ?: 'Column ' . $c);
    }

    $rows = [];
    $max = min($highestRow, 201); // include header + 200 rows
    for ($r = 2; $r <= $max; $r++) {
        $row = [];
        $empty = true;
        for ($c = 1; $c <= $highestColumnIndex; $c++) {
            $val = $sheet->getCellByColumnAndRow($c, $r)->getFormattedValue();
            if ($val !== null && $val !== '') $empty = false;
            $row[] = $val;
        }
        if ($empty) continue;
        $rows[] = $row;
    }

    // Create a token by storing the uploaded file in tmp and returning filename as token
    $token = basename($tmpPath);

    // Keep the temp file for the actual import step; move to uploads/imports_{token}
    $persistDir = __DIR__ . '/uploads/imports/';
    if (!is_dir($persistDir)) mkdir($persistDir, 0755, true);
    $persistPath = $persistDir . $token . '.' . $ext;
    if (!rename($tmpPath, $persistPath)) {
        // fallback: copy
        copy($tmpPath, $persistPath);
        unlink($tmpPath);
    }

    resp(['success'=>true, 'headers'=>$headers, 'rows'=>$rows, 'upload_token'=>$token, 'ext'=>$ext, 'total_rows'=>$highestRow]);

} catch (Exception $e) {
    if (file_exists($tmpPath)) @unlink($tmpPath);
    resp(['success'=>false, 'error'=>'Parse error: ' . $e->getMessage() . ' (File: ' . $file['name'] . ', Type: ' . $ext . ')']);
}
?>
