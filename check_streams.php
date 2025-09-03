<?php
// Simple script to check streams table
include 'connect.php';

echo "<h2>Streams Table Check</h2>";

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'streams'");
if ($table_check && $table_check->num_rows > 0) {
    echo "✅ Streams table exists<br>";
} else {
    echo "❌ Streams table does not exist<br>";
    exit;
}

// Check table structure
echo "<h3>Table Structure:</h3>";
$columns = $conn->query("SHOW COLUMNS FROM streams");
if ($columns) {
    while ($col = $columns->fetch_assoc()) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
} else {
    echo "❌ Could not get table structure<br>";
}

// Check all streams (including inactive)
echo "<h3>All Streams (including inactive):</h3>";
$all_streams = $conn->query("SELECT id, name, code, is_active FROM streams ORDER BY id");
if ($all_streams && $all_streams->num_rows > 0) {
    echo "Found " . $all_streams->num_rows . " streams:<br>";
    while ($stream = $all_streams->fetch_assoc()) {
        $status = $stream['is_active'] ? 'Active' : 'Inactive';
        echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . ", Status: " . $status . "<br>";
    }
} else {
    echo "❌ No streams found in table<br>";
}

// Check active streams only
echo "<h3>Active Streams Only:</h3>";
$active_streams = $conn->query("SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name");
if ($active_streams && $active_streams->num_rows > 0) {
    echo "Found " . $active_streams->num_rows . " active streams:<br>";
    while ($stream = $active_streams->fetch_assoc()) {
        echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . "<br>";
    }
} else {
    echo "❌ No active streams found<br>";
}

$conn->close();
?>
