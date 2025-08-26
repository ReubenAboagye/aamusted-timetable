<?php
include 'connect.php';

error_log("Attempting to delete ALL courses.");

// Begin transaction
$conn->begin_transaction();

try {
    // Delete all dependent rows from lecturer_course
    $sql1 = "DELETE FROM lecturer_course";
    if (!$conn->query($sql1)) {
        throw new Exception("Failed to delete from lecturer_course: " . $conn->error);
    }

    // Delete all rows from course table
    $sql2 = "DELETE FROM course";
    if (!$conn->query($sql2)) {
        throw new Exception("Failed to delete from course: " . $conn->error);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'All courses deleted.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Deletion failed: ' . $e->getMessage()]);
}

$conn->close();
?>
