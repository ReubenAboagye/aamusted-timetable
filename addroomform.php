<?php
include 'connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['room_name'], $_POST['room_type'], $_POST['capacity'], $_POST['building_id'])) {
        $room_name = $_POST['room_name'];
        $room_type = $_POST['room_type'];
        $capacity = $_POST['capacity'];
        $building_id = $_POST['building_id'];

        // Insert into rooms table
        $insert_sql = "INSERT INTO room (room_name, room_type, capacity, building_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);

        // Check if prepare() succeeded
        if (!$stmt) {
            die("Error preparing statement: " . $conn->error);
        }

        $stmt->bind_param("ssii", $room_name, $room_type, $capacity, $building_id);

        // Execute and check for errors
        if ($stmt->execute()) {
            header("Location: rooms.php?status=success");
            exit();
        } else {
            die("Execution error: " . $stmt->error);
        }

        $stmt->close();
    } else {
        die("Missing required form fields!");
    }

    $conn->close();
}
?>
