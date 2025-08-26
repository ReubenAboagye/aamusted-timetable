<?php
// import_classes.php

include 'connect.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function insertCourses($conn, $class_id, $coursesString) {
    if (!empty($coursesString)) {
        $course_names = array_map('trim', explode(",", $coursesString));

        foreach ($course_names as $course_name) {
            if (!empty($course_name)) {
                // Look up the course_id based on the course name
                $stmt_lookup = $conn->prepare("SELECT course_id FROM course WHERE course_name = ?");
                if (!$stmt_lookup) {
                    error_log("Error preparing course lookup: " . $conn->error);
                    continue;
                }
                $stmt_lookup->bind_param("s", $course_name);
                $stmt_lookup->execute();
                $result_lookup = $stmt_lookup->get_result();
                
                if ($result_lookup && $result_lookup->num_rows > 0) {
                    $row_lookup = $result_lookup->fetch_assoc();
                    $course_id = $row_lookup['course_id'];
                    $stmt_lookup->close();

                    // Insert course into class_course table
                    $stmt_course = $conn->prepare("INSERT INTO class_course (class_id, course_id) VALUES (?, ?)");
                    if (!$stmt_course) {
                        error_log("Error preparing class_course insertion: " . $conn->error);
                        continue;
                    }
                    $stmt_course->bind_param("ii", $class_id, $course_id);
                    if (!$stmt_course->execute()) {
                        error_log("Error inserting course: " . $stmt_course->error);
                    }
                    $stmt_course->close();
                } else {
                    error_log("Course not found: " . $course_name);
                    $stmt_lookup->close();
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $allowedExts = ['xls', 'xlsx'];
        $fileName = $_FILES['file']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowedExts)) {
            die("Invalid file format. Please upload an Excel file (.xls or .xlsx).");
        }

        $tmpFilePath = $_FILES['file']['tmp_name'];

        try {
            $spreadsheet = IOFactory::load($tmpFilePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            for ($i = 1; $i < count($rows); $i++) {
                $row = array_map('trim', $rows[$i]);

                if (count($row) < 6 || empty($row[0])) {
                    continue;
                }

                [$class_name, $department, $level, $class_session, $anydis, $capacity] = array_slice($row, 0, 6);
                $sem1_courses = $row[6] ?? '';
                $sem2_courses = $row[7] ?? '';

                $stmt = $conn->prepare("INSERT INTO class (class_name, department, level, class_session, anydis, capacity) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    error_log("Prepare failed: " . $conn->error);
                    continue;
                }
                $stmt->bind_param("ssssss", $class_name, $department, $level, $class_session, $anydis, $capacity);
                if (!$stmt->execute()) {
                    error_log("Error inserting class: " . $stmt->error);
                    continue;
                }
                $class_id = $stmt->insert_id;
                $stmt->close();

                insertCourses($conn, $class_id, $sem1_courses);
                insertCourses($conn, $class_id, $sem2_courses);
            }

            $conn->close();
            header("Location: classes.php?import=success");
            exit();

        } catch (Exception $e) {
            error_log("Error loading file: " . $e->getMessage());
            die("Error processing the file.");
        }
    } else {
        die("No file uploaded or there was an upload error.");
    }
} else {
    die("Invalid request method.");
}
?>
