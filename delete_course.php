<?php
include 'connect.php'; // Include database connection

// Check if 'course_code' is provided in the URL query string
if (isset($_GET['course_code'])) {
    // Get the course_code from the URL
    $course_code = $_GET['course_code'];

    // Prepare SQL to retrieve the course_id based on course_code
    $select_sql = "SELECT course_id FROM course WHERE course_code = ?";
    if ($select_stmt = $conn->prepare($select_sql)) {
        $select_stmt->bind_param("s", $course_code);
        $select_stmt->execute();
        $select_stmt->bind_result($course_id);
        
        if ($select_stmt->fetch()) {
            // We now have the course_id corresponding to the provided course_code
            $select_stmt->close();

            // First, delete any dependent records in lecturer_course that reference this course
            $delete_dependent_sql = "DELETE FROM lecturer_course WHERE course_id = ?";
            if ($dependent_stmt = $conn->prepare($delete_dependent_sql)) {
                $dependent_stmt->bind_param("i", $course_id);
                $dependent_stmt->execute();
                $dependent_stmt->close();
            } else {
                echo "<script>alert('Error preparing dependent deletion statement!'); window.location.href='courses.php';</script>";
                exit();
            }

            // Now, delete the course record from the course table
            $delete_course_sql = "DELETE FROM course WHERE course_id = ?";
            if ($delete_stmt = $conn->prepare($delete_course_sql)) {
                $delete_stmt->bind_param("i", $course_id);
                if ($delete_stmt->execute()) {
                    echo "<script>alert('Course deleted successfully!'); window.location.href='courses.php';</script>";
                } else {
                    echo "<script>alert('Error deleting course: " . $delete_stmt->error . "'); window.location.href='courses.php';</script>";
                }
                $delete_stmt->close();
            } else {
                echo "<script>alert('Error preparing course deletion statement!'); window.location.href='courses.php';</script>";
            }
        } else {
            // No course found with the given course_code
            echo "<script>alert('Course not found!'); window.location.href='classes.php';</script>";
            $select_stmt->close();
        }
    } else {
        echo "<script>alert('Error preparing course select statement!'); window.location.href='classes.php';</script>";
    }
} else {
    echo "<script>alert('Course Code not provided!'); window.location.href='courses.php';</script>";
}

// Close the database connection
$conn->close();
?>
