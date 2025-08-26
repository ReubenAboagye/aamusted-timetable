<?php
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if fields exist in the POST request
    $course_code = $conn->real_escape_string($_POST['course_code']);
    $department_name = $conn->real_escape_string($_POST['department_name']);
    $course_name = $conn->real_escape_string($_POST['course_name']);
    $course_hours = $conn->real_escape_string($_POST['course_hours']);
    $level = $conn->real_escape_string($_POST['level']);
    $semester = $conn->real_escape_string($_POST['semester']);
  
    // Prepare SQL query
    $sql = "INSERT INTO course (course_code, department_name, course_name, course_hours, level, semester) 
            VALUES (?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Correct the number of parameters in the bind_param method
        $stmt->bind_param("ssssss", $course_code, $department_name, $course_name, $course_hours, $level, $semester);
        if ($stmt->execute()) {
            echo "<script>alert('Course added successfully!'); window.location.href='courses.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }

    $conn->close();
}
?>
