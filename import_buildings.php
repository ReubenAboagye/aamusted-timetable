<?php
// Production mode: disable error display.
ini_set('display_errors', 0);
error_reporting(0);

// Include Composer's autoloader for PhpSpreadsheet
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Include your database connection file
include 'connect.php';

if (isset($_FILES['file']['name']) && $_FILES['file']['name'] != "") {
    // Define allowed file extensions
    $allowed_ext = array("xls", "xlsx");
    $file_array  = explode(".", $_FILES["file"]["name"]);
    $file_ext    = strtolower(end($file_array));

    // Verify if the uploaded file has an allowed extension
    if (in_array($file_ext, $allowed_ext)) {
        // Ensure the uploads directory exists; if not, create it
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Set the target path for the uploaded file
        $targetPath = $uploadDir . basename($_FILES["file"]["name"]);

        // Move the uploaded file to the uploads directory
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            header("Location: buildings.php?status=error");
            exit();
        }

        // Load the Excel file using PhpSpreadsheet
        try {
            $spreadsheet = IOFactory::load($targetPath);
        } catch (Exception $e) {
            header("Location: buildings.php?status=error");
            exit();
        }

        // Set the active sheet (assuming your data is in the first sheet)
        $worksheet = $spreadsheet->getActiveSheet();

        // Use the row iterator starting at row 2 (to skip the header row)
        $rowIterator = $worksheet->getRowIterator(2);
        while ($rowIterator->valid()) {
            $row = $rowIterator->current();

            // Create a cell iterator and include all cells (even empty ones)
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            // Build an array of cell values for this row
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // If the first cell is empty, assume the row has no data and skip it
            if (empty($rowData[0])) {
                $rowIterator->next();
                continue;
            }

            // Extract values based on the expected column order for buildings:
            // Column A: building_id, B: building_type, C: building_name, D: division
            $building_id   = isset($rowData[0]) ? trim($rowData[0]) : '';
            $building_type = isset($rowData[1]) ? trim($rowData[1]) : '';
            $building_name = isset($rowData[2]) ? trim($rowData[2]) : '';
            $division      = isset($rowData[3]) ? trim($rowData[3]) : '';

            // Prepare the SQL statement for insertion
            $sql = "INSERT INTO building (building_id, building_type, building_name, division) VALUES (?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssss", $building_id, $building_type, $building_name, $division);
                $stmt->execute();
                $stmt->close();
            }
            $rowIterator->next();
        }

        // Remove the uploaded file after processing
        unlink($targetPath);

        // Redirect to the building management page with a success status
        header("Location: buildings.php?status=success");
        exit();
    } else {
        header("Location: buildings.php?status=error");
        exit();
    }
} else {
    header("Location: buildings.php?status=error");
    exit();
}
?>
