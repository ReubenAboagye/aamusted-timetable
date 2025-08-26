<?php
include 'connect.php';

if (isset($_GET['lecturer_id'])) {
    // Cast lecturer_id to an integer to match the "i" bind type.
    $lecturer_id = (int) $_GET['lecturer_id'];

    // Delete the lecturer from the database.
    $sql = "DELETE FROM lecturer WHERE lecturer_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $lecturer_id);

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header("Location: lecturer.php?status=success");
            exit();
        } else {
            $stmt->close();
            $conn->close();
            header("Location: lecturer.php?status=error");
            exit();
        }
    } else {
        $conn->close();
        header("Location: lecturer.php?status=error");
        exit();
    }
} else {
    header("Location: lecturer.php?status=error");
    exit();
}
?>
