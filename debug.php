<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

// Test connection
$servername = "localhost";
$username = "root";
$password = "!Won2Encript?";
$dbname = "timetable_system";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Database connection successful!<br>";
    
    // Test queries
    $dept_query = "SELECT COUNT(*) AS dept_count FROM departments WHERE is_active = 1";
    $dept_result = $conn->query($dept_query);
    
    if ($dept_result) {
        $dept_row = $dept_result->fetch_assoc();
        echo "Department count: " . $dept_row['dept_count'] . "<br>";
    } else {
        echo "Department query failed: " . $conn->error . "<br>";
    }
    
    $course_query = "SELECT COUNT(*) AS course_count FROM courses WHERE is_active = 1";
    $course_result = $conn->query($course_query);
    
    if ($course_result) {
        $course_row = $course_result->fetch_assoc();
        echo "Course count: " . $course_row['course_count'] . "<br>";
    } else {
        echo "Course query failed: " . $conn->error . "<br>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>
