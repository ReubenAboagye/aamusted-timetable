<?php
// Handle semester import from Excel files
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Include database connection
include 'connect.php';

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    die('Error: No file uploaded or upload error occurred.');
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];

// Check file extension
$allowedExtensions = ['xls', 'xlsx'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    die('Error: Only Excel files (.xls, .xlsx) are allowed.');
}

try {
    // Load the Excel file
    $spreadsheet = IOFactory::load($fileTmpName);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    // Remove header row
    $headers = array_shift($rows);
    
    // Validate headers (expected: Name, Start Date, End Date, Is Active)
    $expectedHeaders = ['Name', 'Start Date', 'End Date', 'Is Active'];
    if (count(array_intersect($headers, $expectedHeaders)) < 3) {
        die('Error: Invalid file format. Expected columns: Name, Start Date, End Date, Is Active');
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Process each row
    foreach ($rows as $rowIndex => $row) {
        if (empty(array_filter($row))) continue; // Skip empty rows
        
        $name = trim($row[0] ?? '');
        $startDate = $row[1] ?? '';
        $endDate = $row[2] ?? '';
        $isActive = isset($row[3]) ? (strtolower(trim($row[3])) === 'yes' || $row[3] == 1 ? 1 : 0) : 1;
        
        // Validate data
        if (empty($name) || empty($startDate) || empty($endDate)) {
            $errors[] = "Row " . ($rowIndex + 2) . ": Missing required data";
            $errorCount++;
            continue;
        }
        
        // Convert Excel date to MySQL date format
        try {
            $startDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($startDate)->format('Y-m-d');
            $endDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($endDate)->format('Y-m-d');
        } catch (Exception $e) {
            // Try direct date parsing if Excel date conversion fails
            $startDate = date('Y-m-d', strtotime($startDate));
            $endDate = date('Y-m-d', strtotime($endDate));
        }
        
        // Validate dates
        if (!$startDate || !$endDate || strtotime($startDate) >= strtotime($endDate)) {
            $errors[] = "Row " . ($rowIndex + 2) . ": Invalid dates";
            $errorCount++;
            continue;
        }
        
        try {
            // Insert into database
            $sql = "INSERT INTO semesters (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $stmt->bind_param("sssi", $name, $startDate, $endDate, $isActive);
            
            if ($stmt->execute()) {
                $successCount++;
            } else {
                throw new Exception("Database execute error: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
            $errorCount++;
        }
    }
    
    // Display results
    echo "<h2>Import Results</h2>";
    echo "<p><strong>Successfully imported:</strong> $successCount semesters</p>";
    echo "<p><strong>Errors:</strong> $errorCount</p>";
    
    if (!empty($errors)) {
        echo "<h3>Error Details:</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    }
    
    echo "<p><a href='semesters.php'>Back to Semesters</a></p>";
    
} catch (Exception $e) {
    die('Error processing file: ' . $e->getMessage());
}

$conn->close();
?>
