<?php
// update_class.php
include 'connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Make sure classid is passed and not empty
    if (isset($_POST['classid']) && !empty($_POST['classid'])) {
        $classid = $_POST['classid'];
        $department = $_POST['department'];
        $level = $_POST['level'];
        $class_session = $_POST['class_session'];
        $anydis = $_POST['anydis'];
        $capacity = $_POST['capacity'];

        // Update the class data in the database
        $sql = "UPDATE class SET classid = ?, department = ?, level = ?, class_session = ?, anydis = ?, capacity = ? WHERE classid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssii", $department, $level, $class_session, $anydis, $capacity, $classid);

        if ($stmt->execute()) {
            echo "Class updated successfully!";
        } else {
            echo "Error updating class.";
        }
    } else {
        echo "Class ID not provided.";
    }
}
?>
