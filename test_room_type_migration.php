<?php
// Test script to verify room_type migration from ENUM to VARCHAR
// This helps confirm that the "Data truncated" error is fixed

include 'connect.php';

echo "<h2>Room Type Migration Test</h2>";

// Check current table structure
echo "<h3>1. Current Table Structure</h3>";
$result = $conn->query("SHOW COLUMNS FROM rooms LIKE 'room_type'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p><strong>Column:</strong> {$row['Field']}</p>";
    echo "<p><strong>Type:</strong> {$row['Type']}</p>";
    echo "<p><strong>Null:</strong> {$row['Null']}</p>";
    echo "<p><strong>Comment:</strong> {$row['Comment']}</p>";
    
    if (strpos($row['Type'], 'enum') !== false) {
        echo "<p style='color: red;'><strong>⚠️ WARNING:</strong> room_type is still ENUM. Run the migration script first.</p>";
    } else {
        echo "<p style='color: green;'><strong>✅ SUCCESS:</strong> room_type is now VARCHAR. Migration completed!</p>";
    }
} else {
    echo "<p style='color: red;'>Error: Could not check table structure</p>";
}

// Test insert with different room types
echo "<h3>2. Testing Room Type Inserts</h3>";
$test_room_types = [
    'classroom',
    'lecture_hall', 
    'laboratory',
    'computer_lab',
    'seminar_room',
    'auditorium'
];

foreach ($test_room_types as $room_type) {
    echo "<p>Testing room_type: <strong>'$room_type'</strong>...</p>";
    
    try {
        // Try to insert a test record
        $sql = "INSERT INTO rooms (name, building, room_type, capacity, stream_availability, facilities, accessibility_features, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $test_name = "TEST_" . strtoupper($room_type) . "_" . time();
            $test_building = "Test Building";
            $capacity = 30;
            $stream_availability = '["regular"]';
            $facilities = '[]';
            $accessibility_features = '[]';
            $is_active = 1;
            
            $stmt->bind_param("ssissssi", $test_name, $test_building, $room_type, $capacity, $stream_availability, $facilities, $accessibility_features, $is_active);
            
            if ($stmt->execute()) {
                echo "<p style='color: green;'>✅ SUCCESS: Inserted room_type '$room_type'</p>";
                
                // Clean up test record
                $delete_sql = "DELETE FROM rooms WHERE name = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("s", $test_name);
                $delete_stmt->execute();
                $delete_stmt->close();
                
            } else {
                echo "<p style='color: red;'>❌ FAILED: Could not insert room_type '$room_type' - " . $stmt->error . "</p>";
            }
            $stmt->close();
        } else {
            echo "<p style='color: red;'>❌ FAILED: Could not prepare statement for room_type '$room_type'</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ ERROR: Exception for room_type '$room_type' - " . $e->getMessage() . "</p>";
    }
}

// Show current rooms count
echo "<h3>3. Current Rooms Count</h3>";
$count_result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE is_active = 1");
if ($count_result && $count_result->num_rows > 0) {
    $count_row = $count_result->fetch_assoc();
    echo "<p>Total active rooms: <strong>{$count_row['total']}</strong></p>";
}

// Show sample of existing room types
echo "<h3>4. Sample of Existing Room Types</h3>";
$sample_result = $conn->query("SELECT DISTINCT room_type, COUNT(*) as count FROM rooms WHERE is_active = 1 GROUP BY room_type ORDER BY count DESC LIMIT 10");
if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Room Type</th><th>Count</th></tr>";
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr><td>{$row['room_type']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No rooms found or error occurred.</p>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If room_type is still ENUM, run the migration script: <code>migrate_room_type_to_varchar.sql</code></li>";
echo "<li>If room_type is now VARCHAR, test your CSV import again</li>";
echo "<li>The 'Data truncated' error should be resolved</li>";
echo "</ul>";

$conn->close();
?>
