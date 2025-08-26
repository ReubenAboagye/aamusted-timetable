<?php
include 'connect.php'; // Include database connection

// Check if 'building_id' is provided in the URL query string
if (isset($_GET['building_id'])) {
    // Get the building ID from the URL
    $building_id = $_GET['building_id'];

    // Prepare SQL to check if the building exists
    $check_sql = "SELECT building_id FROM building WHERE building_id = ?";
    
    // Prepare the statement
    if ($check_stmt = $conn->prepare($check_sql)) {
        // Bind the building_id parameter to the query
        $check_stmt->bind_param("i", $building_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        // Check if the building exists
        if ($check_stmt->num_rows > 0) {
            // Prepare SQL to delete the building
            $delete_sql = "DELETE FROM building WHERE building_id = ?";
            
            // Prepare the delete statement
            if ($delete_stmt = $conn->prepare($delete_sql)) {
                // Bind the building_id to the delete query
                $delete_stmt->bind_param("i", $building_id);

                // Execute the delete query
                if ($delete_stmt->execute()) {
                    echo "<script>alert('Building deleted successfully!'); window.location.href='buildings.php';</script>";
                } else {
                    echo "Error deleting building: " . $delete_stmt->error;
                }

                // Close the delete statement
                $delete_stmt->close();
            } else {
                echo "Error preparing delete statement: " . $conn->error;
            }
        } else {
            echo "<script>alert('Building not found!'); window.location.href='buildings.php';</script>";
        }

        // Close the check statement
        $check_stmt->close();
    } else {
        echo "Error preparing check statement: " . $conn->error;
    }
} else {
    echo "<script>alert('Building ID not provided!'); window.location.href='buildings.php';</script>";
}

// Close the database connection
$conn->close();
?>
