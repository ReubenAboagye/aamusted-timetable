<?php
/**
 * Test script to verify room_type migration from ENUM to VARCHAR
 * Run this after executing the migration script
 */

require_once 'connect.php';

echo "<h2>Testing Room Type Migration</h2>";

// Test 1: Check current table structure
echo "<h3>1. Current Table Structure:</h3>";
$result = $conn->query("DESCRIBE rooms");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error describing table: " . $conn->error;
}

// Test 2: Try inserting with different room types
echo "<h3>2. Testing Room Type Insertions:</h3>";

$test_room_types = [
    'classroom',
    'lecture_hall', 
    'laboratory',
    'computer_lab',
    'seminar_room',
    'auditorium',
    'test_type' // This should work now since it's VARCHAR
];

foreach ($test_room_types as $room_type) {
    $test_name = "TEST_" . strtoupper($room_type);
    $test_building = "TEST_BUILDING";
    
    // Try to insert
    $sql = "INSERT INTO rooms (name, building, room_type, capacity, session_availability, facilities, accessibility_features, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $session_availability = '["regular"]';
        $facilities = '[]';
        $accessibility_features = '[]';
        $capacity = 30;
        $is_active = 1;
        
        $stmt->bind_param("ssissssi", $test_name, $test_building, $room_type, $capacity, $session_availability, $facilities, $accessibility_features, $is_active);
        
        if ($stmt->execute()) {
            echo "✅ SUCCESS: Inserted room type '$room_type'<br>";
            
            // Clean up test data
            $conn->query("DELETE FROM rooms WHERE name = '$test_name' AND building = '$test_building'");
        } else {
            echo "❌ FAILED: Could not insert room type '$room_type' - " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "❌ ERROR: Could not prepare statement for '$room_type' - " . $conn->error . "<br>";
    }
}

echo "<h3>3. Migration Status:</h3>";
$result = $conn->query("SHOW COLUMNS FROM rooms LIKE 'room_type'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (strpos($row['Type'], 'enum') !== false) {
        echo "❌ <strong>MIGRATION NEEDED:</strong> room_type is still ENUM: {$row['Type']}<br>";
        echo "Please run: <code>ALTER TABLE rooms MODIFY COLUMN room_type VARCHAR(50) NOT NULL;</code><br>";
    } else {
        echo "✅ <strong>MIGRATION COMPLETE:</strong> room_type is now: {$row['Type']}<br>";
        echo "The application now uses hardcoded validation for room types.<br>";
    }
} else {
    echo "❌ ERROR: Could not check room_type column";
}

echo "<h3>4. Current Room Types in Database:</h3>";
$result = $conn->query("SELECT DISTINCT room_type FROM rooms ORDER BY room_type");
if ($result) {
    echo "Existing room types: ";
    $types = [];
    while ($row = $result->fetch_assoc()) {
        $types[] = $row['room_type'];
    }
    echo implode(', ', $types) ?: 'None';
} else {
    echo "Error querying room types: " . $conn->error;
}

$conn->close();
?>
