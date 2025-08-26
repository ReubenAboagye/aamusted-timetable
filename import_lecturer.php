<?php
// import_lecturer.php

include 'connect.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if a file was uploaded without error
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        // Validate file extension
        $allowedExts = ['xls', 'xlsx'];
        $fileName = $_FILES['file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExts)) {
            die("Error: Invalid file format. Please upload an Excel file (.xls or .xlsx).");
        }

        $tmpFilePath = $_FILES['file']['tmp_name'];

        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($tmpFilePath);
            $sheet = $spreadsheet->getActiveSheet();
            // Convert the sheet into an array (each row is a numeric-indexed array)
            $rows = $sheet->toArray();

            /* 
              Expected Excel columns:
              Column 0: Lecturer ID
              Column 1: Lecturer Name
              Column 2: Department
              Column 3: Courses (comma-separated course names, optional)

              The first row is assumed to be the header and will be skipped.
            */

            // Loop through each data row (starting at index 1)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Ensure at least 3 columns exist (lecturer_id, lecturer_name, department)
                if (count($row) < 3) {
                    continue;
                }

                $lecturer_id   = trim($row[0]);
                $lecturer_name = trim($row[1]);
                $department    = trim($row[2]);
                $courses       = isset($row[3]) ? trim($row[3]) : '';

                // Insert lecturer data into the lecturer table
                $stmt = $conn->prepare("INSERT INTO lecturer (lecturer_id, lecturer_name, department) VALUES (?, ?, ?)");
                if (!$stmt) {
                    echo "Prepare failed: " . $conn->error;
                    continue;
                }
                $stmt->bind_param("sss", $lecturer_id, $lecturer_name, $department);
                if (!$stmt->execute()) {
                    echo "Error inserting lecturer: " . $stmt->error;
                    continue;
                }
                $stmt->close();

                // If courses are provided, insert them into the lecturer_course linking table
                if (!empty($courses)) {
                    // Split the courses string by comma and trim each course name
                    $course_names = explode(",", $courses);
                    foreach ($course_names as $course_name) {
                        $course_name = trim($course_name);
                        if (!empty($course_name)) {
                            // Look up course_id from the course table using the course name
                            $stmt_lookup = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
                            if (!$stmt_lookup) {
                                echo "Error preparing lookup: " . $conn->error;
                                continue;
                            }
                            $stmt_lookup->bind_param("s", $course_name);
                            $stmt_lookup->execute();
                            $result_lookup = $stmt_lookup->get_result();
                            if ($result_lookup && $result_lookup->num_rows > 0) {
                                $row_lookup = $result_lookup->fetch_assoc();
                                $course_id = $row_lookup['course_id'];
                                $stmt_lookup->close();

                                // Insert into lecturer_course using the retrieved course_id
                                $stmt_course = $conn->prepare("INSERT INTO lecturer_course (lecturer_id, course_id) VALUES (?, ?)");
                                if (!$stmt_course) {
                                    echo "Error preparing course insertion: " . $conn->error;
                                    continue;
                                }
                                // Assuming course_id is numeric
                                $stmt_course->bind_param("si", $lecturer_id, $course_id);
                                if (!$stmt_course->execute()) {
                                    echo "Error inserting lecturer course: " . $stmt_course->error;
                                }
                                $stmt_course->close();
                            } else {
                                echo "Course not found for course name: " . $course_name;
                                $stmt_lookup->close();
                            }
                        }
                    }
                }
            }

            $conn->close();
            // Redirect back to lecturer.php on successful import
            header("Location: lecturer.php?import=success");
            exit();

        } catch (Exception $e) {
            die("Error loading file: " . $e->getMessage());
        }
    } else {
        die("Error: No file uploaded or there was an upload error.");
    }
} else {
    die("Invalid request method.");
}
?>
