<?php
include 'connect.php'; // Include database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize form inputs
    $departmentId = $_POST['departmentId'];
    $departmentName = $_POST['departmentName'];
    $departmentShortName = $_POST['departmentShortName'];

    // Prepare SQL query to insert data into the department table
    $sql = "INSERT INTO department (department_id, department_name, department_short_name) 
            VALUES (?, ?, ?)";
    
    // Prepare the statement
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind the parameters to the SQL query
        $stmt->bind_param("sss", $departmentId, $departmentName, $departmentShortName);

        // Execute the query
        if ($stmt->execute()) {
            echo "<script>alert('Department added successfully!'); window.location.href='department.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }

    // Close the database connection
    $conn->close();
}
?>
