<?php
include 'connect.php'; // Include the database connection

if (isset($_GET['class_name'])) {
    $class_name = $_GET['class_name'];

    // Make sure class_name is properly sanitized to prevent SQL injection
    $class_name = $conn->real_escape_string($class_name);

    // Query to delete the class with the specific name
    $sql = "DELETE FROM class WHERE class_name = '$class_name'";

    if ($conn->query($sql) === TRUE) {
        echo"class deleted successfully";
        header("Location: classes.php"); // Redirect to the classes page after successful deletion
    } else {
        
        header("Location: classes.php"); // Redirect with an error status if something goes wrong
    }
} else {
    header("Location: classes.php?status=error"); // If class_name is not set, redirect with an error
}

$conn->close(); // Close the connection
?>
