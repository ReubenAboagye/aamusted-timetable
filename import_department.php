<?php
// Import departments from stored CSV file
include 'connect.php';

// Simple response helper
function resp($arr) { 
    while (ob_get_level()) ob_end_clean();
    echo json_encode($arr); 
    exit; 
}

// Check if upload token was provided
if (!isset($_POST['upload_token']) || empty($_POST['upload_token'])) {
    resp(['success'=>false, 'error'=>'No upload token provided']);
}

$token = $_POST['upload_token'];
$csvPath = __DIR__ . '/uploads/imports/' . $token . '.csv';

// Check if the CSV file exists
if (!file_exists($csvPath)) {
    resp(['success'=>false, 'error'=>'Uploaded file not found. Please try uploading again.']);
}

try {
    // Read the CSV file
    $handle = fopen($csvPath, 'r');
    if (!$handle) {
        resp(['success'=>false, 'error'=>'Could not read stored file']);
    }

    // Read headers (first row)
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        resp(['success'=>false, 'error'=>'Could not read CSV headers']);
    }

    // Map expected columns (case-insensitive)
    $columnMap = [];
    foreach ($headers as $index => $header) {
        $headerLower = strtolower(trim($header));
        if (strpos($headerLower, 'name') !== false && strpos($headerLower, 'code') === false) {
            $columnMap['name'] = $index;
        } elseif (strpos($headerLower, 'code') !== false) {
            $columnMap['code'] = $index;
        } elseif (strpos($headerLower, 'short') !== false) {
            $columnMap['short_name'] = $index;
        } elseif (strpos($headerLower, 'head') !== false) {
            $columnMap['head_of_department'] = $index;
        } elseif (strpos($headerLower, 'active') !== false || strpos($headerLower, 'status') !== false) {
            $columnMap['is_active'] = $index;
        }
    }

    // Validate required columns
    if (!isset($columnMap['name']) || !isset($columnMap['code'])) {
        fclose($handle);
        resp(['success'=>false, 'error'=>'Required columns missing. Need at least: Name, Code']);
    }

    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $rowNumber = 1; // Start from 1 since we're processing data rows

    // Process data rows
    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;
        
        // Skip empty rows
        if (!array_filter($row)) continue;

        try {
            // Extract values based on column mapping
            $name = trim($row[$columnMap['name']] ?? '');
            $code = trim($row[$columnMap['code']] ?? '');
            $shortName = trim($row[$columnMap['short_name']] ?? '');
            $headOfDepartment = trim($row[$columnMap['head_of_department']] ?? '');
            $isActive = isset($columnMap['is_active']) ? 
                (strtolower(trim($row[$columnMap['is_active']])) === '1' || 
                 strtolower(trim($row[$columnMap['is_active']])) === 'yes' || 
                 strtolower(trim($row[$columnMap['is_active']])) === 'true' ? 1 : 0) : 1;

            // Validate required fields
            if (empty($name) || empty($code)) {
                $errors[] = "Row $rowNumber: Name and Code are required";
                $errorCount++;
                continue;
            }

            // Check if department code already exists
            $checkSql = "SELECT id FROM departments WHERE code = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $code);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "Row $rowNumber: Department code '$code' already exists";
                $errorCount++;
                $checkStmt->close();
                continue;
            }
            $checkStmt->close();

            // Insert the department
            $sql = "INSERT INTO departments (name, code, short_name, head_of_department, is_active) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssi", $name, $code, $shortName, $headOfDepartment, $isActive);
                if ($stmt->execute()) {
                    $successCount++;
                } else {
                    $errors[] = "Row $rowNumber: Database error: " . $stmt->error;
                    $errorCount++;
                }
                $stmt->close();
            } else {
                $errors[] = "Row $rowNumber: Failed to prepare statement";
                $errorCount++;
            }

        } catch (Exception $e) {
            $errors[] = "Row $rowNumber: " . $e->getMessage();
            $errorCount++;
        }
    }

    fclose($handle);

    // Clean up the uploaded file
    @unlink($csvPath);

    // Prepare response
    $response = [
        'success' => true,
        'message' => "Import completed. $successCount departments imported successfully.",
        'summary' => [
            'total_processed' => $rowNumber - 1,
            'successful' => $successCount,
            'errors' => $errorCount
        ]
    ];

    if (!empty($errors)) {
        $response['errors'] = array_slice($errors, 0, 10); // Show first 10 errors
        if (count($errors) > 10) {
            $response['errors'][] = "... and " . (count($errors) - 10) . " more errors";
        }
    }

    resp($response);

} catch (Exception $e) {
    resp(['success'=>false, 'error'=>'Import failed: ' . $e->getMessage()]);
}
?>
