<?php
include 'connect.php'; // Include database connection

// Check if 'department_id' is provided in the URL query string
if (isset($_GET['department_id'])) {
    // Get the department_id from the URL
    $department_id = $_GET['department_id'];

    // Prepare SQL to check if the department exists
    $check_sql = "SELECT department_id FROM department WHERE department_id = ?";
    
    // Prepare the statement
    if ($check_stmt = $conn->prepare($check_sql)) {
        // Bind the department_id parameter to the query
        $check_stmt->bind_param("i", $department_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        // Check if the department exists
        if ($check_stmt->num_rows > 0) {
            // Prepare SQL to delete the department
            $delete_sql = "DELETE FROM department WHERE department_id = ?";
            
            // Prepare the delete statement
            if ($delete_stmt = $conn->prepare($delete_sql)) {
                // Bind the department_id to the delete query
                $delete_stmt->bind_param("i", $department_id);

                // Execute the delete query
                if ($delete_stmt->execute()) {
                    echo "<script>alert('Department deleted successfully!'); window.location.href='department.php';</script>";
                } else {
                    echo "Error deleting department: " . $delete_stmt->error;
                }

                // Close the delete statement
                $delete_stmt->close();
            } else {
                echo "Error preparing delete statement: " . $conn->error;
            }
        } else {
            echo "<script>alert('Department not found!'); window.location.href='department.php';</script>";
        }

        // Close the check statement
        $check_stmt->close();
    } else {
        echo "Error preparing check statement: " . $conn->error;
    }
} else {
    echo "<script>alert('Department ID not provided!'); window.location.href='department.php';</script>";
}

// Close the database connection
$conn->close();
?>
