<?php
// import_department.php

include 'connect.php';

// Include PhpSpreadsheet via Composer's autoload file
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Check if a file was uploaded without errors
if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
    $fileName    = $_FILES['file']['name'];
    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileExt     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['xls', 'xlsx'];

    if (in_array($fileExt, $allowedExts)) {
        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($fileTmpPath);
            $worksheet   = $spreadsheet->getActiveSheet();
            $highestRow  = $worksheet->getHighestDataRow();
            
            // Assuming the first row is headers, start at row 2
            for ($row = 2; $row <= $highestRow; $row++) {
                // Adjust these cell references as needed:
                $departmentId        = $worksheet->getCell("A{$row}")->getValue();
                $departmentName      = $worksheet->getCell("B{$row}")->getValue();
                $departmentShortName = $worksheet->getCell("C{$row}")->getValue();
                
                // Prepare and execute the INSERT query
                $stmt = $conn->prepare("INSERT INTO department (department_id, department_name, department_short_name) VALUES (?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sss", $departmentId, $departmentName, $departmentShortName);
                $stmt->execute();
                $stmt->close();
            }
            
            // Redirect back to the departments page with a success flag
            header("Location: department.php?import=success");
            exit;
            
        } catch (Exception $e) {
            echo "Error importing file: " . $e->getMessage();
        }
    } else {
        echo "Invalid file format. Please upload a valid Excel file (.xls or .xlsx).";
    }
} else {
    echo "No file uploaded or there was an upload error.";
}

$conn->close();
?>
