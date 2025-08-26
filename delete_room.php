<?php
include 'connect.php'; // Include database connection

// Check if 'room_name' is provided in the URL query string
if (isset($_GET['room_name'])) {
    // Get the room name from the URL
    $room_name = $_GET['room_name'];

    // Prepare SQL to check if the room exists
    $check_sql = "SELECT room_name FROM room WHERE room_name = ?";
    
    // Prepare the statement
    if ($check_stmt = $conn->prepare($check_sql)) {
        // Bind the room_name parameter as a string
        $check_stmt->bind_param("s", $room_name);
        $check_stmt->execute();
        $check_stmt->store_result();

        // Check if the room exists
        if ($check_stmt->num_rows > 0) {
            // Prepare SQL to delete the room
            $delete_sql = "DELETE FROM room WHERE room_name = ?";
            
            // Prepare the delete statement
            if ($delete_stmt = $conn->prepare($delete_sql)) {
                // Bind the room_name as a string
                $delete_stmt->bind_param("s", $room_name);

                // Execute the delete query
                if ($delete_stmt->execute()) {
                    echo "<script>alert('Room deleted successfully!'); window.location.href='rooms.php';</script>";
                } else {
                    echo "Error deleting room: " . $delete_stmt->error;
                }

                // Close the delete statement
                $delete_stmt->close();
            } else {
                echo "Error preparing delete statement: " . $conn->error;
            }
        } else {
            echo "<script>alert('Room not found!'); window.location.href='rooms.php';</script>";
        }

        // Close the check statement
        $check_stmt->close();
    } else {
        echo "Error preparing check statement: " . $conn->error;
    }
} else {
    echo "<script>alert('Room name not provided!'); window.location.href='rooms.php';</script>";
}

// Close the database connection
$conn->close();
?>
