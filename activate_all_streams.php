<?php
include 'connect.php';

echo "<h2>Activate All Streams</h2>";

// First, show current status
echo "<h3>Current Stream Status:</h3>";
$all_streams = $conn->query("SELECT id, name, code, is_active FROM streams ORDER BY id");
if ($all_streams && $all_streams->num_rows > 0) {
    echo "Found " . $all_streams->num_rows . " streams:<br>";
    while ($stream = $all_streams->fetch_assoc()) {
        $status = $stream['is_active'] ? 'Active' : 'Inactive';
        echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . ", Status: " . $status . "<br>";
    }
} else {
    echo "No streams found in database<br>";
    exit;
}

// Activate all streams
echo "<h3>Activating all streams...</h3>";
$update_sql = "UPDATE streams SET is_active = 1 WHERE is_active = 0";
$result = $conn->query($update_sql);

if ($result) {
    $affected_rows = $conn->affected_rows;
    echo "✅ Successfully activated " . $affected_rows . " streams<br>";
} else {
    echo "❌ Error activating streams: " . $conn->error . "<br>";
}

// Show updated status
echo "<h3>Updated Stream Status:</h3>";
$all_streams = $conn->query("SELECT id, name, code, is_active FROM streams ORDER BY id");
if ($all_streams && $all_streams->num_rows > 0) {
    echo "All " . $all_streams->num_rows . " streams are now active:<br>";
    while ($stream = $all_streams->fetch_assoc()) {
        $status = $stream['is_active'] ? 'Active' : 'Inactive';
        echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . ", Status: " . $status . "<br>";
    }
}

echo "<br><a href='classes.php'>← Back to Classes Management</a>";

$conn->close();
?>
