<?php
include 'connect.php'; // Include database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $building_id = $_POST['building_id'];
    $building_type = $_POST['building_type'];
    $building_name = $_POST['building_name'];
    $division = $_POST['division'];
    
    // Insert data into database
    $sql = "INSERT INTO building ( building_id, building_type, building_name, division) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ssss",$building_id, $building_type, $building_name, $division);
        if ($stmt->execute()) {
            echo "<script>alert('Building added successfully!'); window.location.href='buildings.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
    
    $conn->close();
}
?>