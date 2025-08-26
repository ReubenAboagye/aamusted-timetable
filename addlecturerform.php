<?php
// Include the database connection file
include 'connect.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $lecturer_id   = $_POST['lecturer_id'];
    $lecturer_name = $_POST['lecturer_name'];
    $department    = $_POST['department'];
    $courses       = isset($_POST['courses']) ? $_POST['courses'] : []; // Array of selected course IDs

    // Prepare the SQL query to insert data into the `lecturer` table
    $sql = "INSERT INTO lecturer (lecturer_id, lecturer_name, department) VALUES (?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        // Bind the form inputs to the query
        $stmt->bind_param("sss", $lecturer_id, $lecturer_name, $department);

        // Execute the query to add the lecturer
        if ($stmt->execute()) {
            // Now, if courses were selected, insert them into the join table
            if (!empty($courses)) {
                // Prepare the SQL statement for the join table insertion
                $joinSql = "INSERT INTO lecturer_course (lecturer_id, course_id) VALUES (?, ?)";
                
                if ($joinStmt = $conn->prepare($joinSql)) {
                    // Loop through each selected course ID and insert it
                    foreach ($courses as $course_id) {
                        $joinStmt->bind_param("ss", $lecturer_id, $course_id);
                        $joinStmt->execute();
                    }
                    $joinStmt->close();
                } else {
                    // Optional: You can log or display an error if the join statement fails to prepare
                    echo "<p>Error preparing join query: " . $conn->error . "</p>";
                }
            }
            
            // Close the lecturer statement and redirect with success status
            $stmt->close();
            header("Location: lecturer.php?status=success");
            exit();
        } else {
            // Display error if lecturer query fails
            echo "<p>Error: " . $stmt->error . "</p>";
            $stmt->close();
            header("Location: lecturer.php?status=error");
            exit();
        }
    } else {
        // Error preparing the SQL statement
        echo "<p>Error preparing the query: " . $conn->error . "</p>";
        header("Location: lecturer.php?status=error");
        exit();
    }

    // Close the database connection
    $conn->close();
}
?>
