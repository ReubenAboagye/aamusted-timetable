<?php
// import_courses.php

// Ensure PhpSpreadsheet is installed via Composer:
// composer require phpoffice/phpspreadsheet
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Include your database connection
include 'connect.php';

// Check if a file was uploaded without errors
if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
    $fileName = $_FILES['file']['name'];
    $fileTmp  = $_FILES['file']['tmp_name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Allowed file extensions
    $allowedExtensions = ['xls', 'xlsx'];
    if (!in_array($fileExt, $allowedExtensions)) {
        die("Error: Invalid file format. Please upload an Excel file (.xls or .xlsx).");
    }

    try {
        // Load the uploaded Excel file
        $spreadsheet = IOFactory::load($fileTmp);
        $worksheet   = $spreadsheet->getActiveSheet();
        // Use toArray with the last parameter set to false to ensure numeric keys
        $rows        = $worksheet->toArray(null, true, false, false);

        // Assuming the first row contains headers; start from the second row
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Ensure the row has the expected columns:
            // [0] => course_code, [1] => department_name, [2] => course_name,
            // [3] => course_hours, [4] => level, [5] => semester
            if (count($row) < 6) {
                continue; // Skip rows with insufficient data
            }

            // Sanitize and prepare data
            $course_code     = $conn->real_escape_string(trim($row[0]));
            $department_name = $conn->real_escape_string(trim($row[1]));
            $course_name     = $conn->real_escape_string(trim($row[2]));
            $course_hours    = intval($row[3]);
            $level           = intval($row[4]);
            $semester        = intval($row[5]);

            // Insert into the database
            $sql = "INSERT INTO course (course_code, department_name, course_name, course_hours, level, semester) 
                    VALUES ('$course_code', '$department_name', '$course_name', $course_hours, $level, $semester)";
            if (!$conn->query($sql)) {
                echo "Error inserting row $i: " . $conn->error . "<br>";
            }
        }
        // Redirect back to courses.php after successful import
        header("Location: courses.php?import=success");
        exit;
    } catch (Exception $e) {
        die("Error loading file: " . $e->getMessage());
    }
} else {
    die("No file was uploaded or there was an upload error.");
}

$conn->close();
?>
