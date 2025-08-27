<?php
// Simple CSV-only import preview (no PhpSpreadsheet required)
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

function resp($arr) { 
    while (ob_get_level()) ob_end_clean();
    echo json_encode($arr); 
    exit; 
}

// Debug information
$debug = [
    'POST' => $_POST,
    'FILES' => $_FILES,
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resp(['success'=>false, 'error'=>'Only POST requests allowed', 'debug' => $debug]);
}

// Check if files were uploaded
if (!isset($_FILES['file'])) {
    resp(['success'=>false, 'error'=>'No file field found in request', 'debug' => $debug]);
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    
    $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error: ' . $file['error'];
    resp(['success'=>false, 'error'=>$errorMsg, 'debug' => $debug]);
}

// Check if file actually exists
if (!is_uploaded_file($file['tmp_name'])) {
    resp(['success'=>false, 'error'=>'File upload validation failed', 'debug' => $debug]);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Only allow CSV for now
if ($ext !== 'csv') {
    resp(['success'=>false, 'error'=>'Only CSV files are supported. Please convert your Excel file to CSV format.', 'debug' => $debug]);
}

// Check file size (limit to 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    resp(['success'=>false, 'error'=>'File too large. Maximum size is 5MB', 'debug' => $debug]);
}

try {
    // Read CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        resp(['success'=>false, 'error'=>'Could not open uploaded file', 'debug' => $debug]);
    }

    $headers = [];
    $rows = [];
    $rowCount = 0;
    $maxPreviewRows = 100;

    // Read headers (first row)
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        resp(['success'=>false, 'error'=>'Could not read CSV headers', 'debug' => $debug]);
    }

    // Read data rows
    while (($row = fgetcsv($handle)) !== false && $rowCount < $maxPreviewRows) {
        // Skip empty rows
        if (array_filter($row)) {
            $rows[] = $row;
        }
        $rowCount++;
    }

    fclose($handle);

    // Create a token for the actual import
    $token = uniqid('csv_import_');
    
    // Store the uploaded file
    $persistDir = __DIR__ . '/uploads/imports/';
    if (!is_dir($persistDir)) mkdir($persistDir, 0755, true);
    $persistPath = $persistDir . $token . '.csv';
    
    if (!copy($file['tmp_name'], $persistPath)) {
        resp(['success'=>false, 'error'=>'Failed to store uploaded file', 'debug' => $debug]);
    }

    resp([
        'success' => true, 
        'headers' => $headers, 
        'rows' => $rows, 
        'upload_token' => $token, 
        'ext' => 'csv', 
        'total_rows' => $rowCount,
        'message' => 'CSV file parsed successfully. Make sure your columns are: Name, Code, Short Name, Head of Department, Is Active'
    ]);

} catch (Exception $e) {
    resp(['success'=>false, 'error'=>'Parse error: ' . $e->getMessage(), 'debug' => $debug]);
}
?>
