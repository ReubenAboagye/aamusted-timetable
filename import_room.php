<?php
// Production mode: disable error display.
ini_set('display_errors', 0);
error_reporting(0);

// Include Composer's autoloader for PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Include your database connection file
include 'connect.php';

// Check if a file was uploaded
if (isset($_FILES['file']['name']) && $_FILES['file']['name'] != "") {
    // Allowed file extensions
    $allowed_ext = array("xls", "xlsx");
    $file_array  = explode(".", $_FILES["file"]["name"]);
    $file_ext    = strtolower(end($file_array));

    // Verify if the file extension is allowed
    if (in_array($file_ext, $allowed_ext)) {
        // Ensure the uploads directory exists; create it if not
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Define the target path for the uploaded file
        $targetPath = $uploadDir . basename($_FILES["file"]["name"]);
        
        // Move the uploaded file into the uploads directory
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            header("Location: rooms.php?status=error");
            exit();
        }
        
        // Load the Excel file using PhpSpreadsheet
        try {
            $spreadsheet = IOFactory::load($targetPath);
        } catch (Exception $e) {
            header("Location: rooms.php?status=error");
            exit();
        }
        
        // Get the active sheet (assuming your data is in the first sheet)
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Use a row iterator starting at row 2 to skip the header row
        $rowIterator = $worksheet->getRowIterator(2);
        while ($rowIterator->valid()) {
            $row = $rowIterator->current();
            
            // Create a cell iterator that includes empty cells
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            // Build an array of cell values for the current row
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }
            
            // If the first cell (Room Type) is empty, assume the row has no data and skip it
            if (empty($rowData[0]) && empty($rowData[1])) {
                $rowIterator->next();
                continue;
            }
            
            // Map expected columns:
            // A: room_type, B: room_name, C: capacity, D: building_id
            $room_type   = isset($rowData[0]) ? trim($rowData[0]) : '';
            $room_name   = isset($rowData[1]) ? trim($rowData[1]) : '';
            $capacity    = isset($rowData[2]) ? trim($rowData[2]) : '';
            $building_id = isset($rowData[3]) ? trim($rowData[3]) : '';
            
            // Validate required fields (for example, room_name, capacity, and building_id must not be empty)
            if (empty($room_name) || empty($capacity) || empty($building_id)) {
                $rowIterator->next();
                continue;
            }
            
            // Prepare the SQL statement for inserting the room record
            $stmt = $conn->prepare("INSERT INTO room (room_type, room_name, capacity, building_id) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $room_type, $room_name, $capacity, $building_id);
                $stmt->execute();
                $stmt->close();
            }
            
            $rowIterator->next();
        }
        
        // Remove the uploaded file after processing
        unlink($targetPath);
        
        // Redirect to the rooms management page with a success status
        header("Location: rooms.php?status=success");
        exit();
    } else {
        header("Location: rooms.php?status=error");
        exit();
    }
} else {
    header("Location: rooms.php?status=error");
    exit();
}
?>
