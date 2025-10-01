<?php
// Simple script to add break time slots to the database
include 'connect.php';

echo "<h2>Adding Break Time Slots</h2>";

// Add some break time slots
$break_slots = [
    ['12:00:00', '13:00:00', 60, 1, 0], // Lunch break
    ['15:00:00', '16:00:00', 60, 1, 0], // Tea break
];

$insert_sql = "INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);

foreach ($break_slots as $slot) {
    $stmt->bind_param('ssiii', $slot[0], $slot[1], $slot[2], $slot[3], $slot[4]);
    
    if ($stmt->execute()) {
        echo "<p> Added break slot: {$slot[0]} - {$slot[1]} (Duration: {$slot[2]} min)</p>";
    } else {
        if ($stmt->errno == 1062) {
            echo "<p> Break slot already exists: {$slot[0]} - {$slot[1]}</p>";
        } else {
            echo "<p> Error adding break slot: {$stmt->error}</p>";
        }
    }
}

$stmt->close();

// Show current time slots
echo "<h3>Current Time Slots:</h3>";
$result = $conn->query("SELECT id, start_time, end_time, duration, is_break, is_mandatory FROM time_slots ORDER BY start_time");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Start Time</th><th>End Time</th><th>Duration</th><th>Is Break</th><th>Is Mandatory</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $break_status = $row['is_break'] ? 'Yes' : 'No';
        $mandatory_status = $row['is_mandatory'] ? 'Yes' : 'No';
        $row_color = $row['is_break'] ? 'background-color: #f8d7da;' : '';
        
        echo "<tr style='$row_color'>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['start_time']}</td>";
        echo "<td>{$row['end_time']}</td>";
        echo "<td>{$row['duration']} min</td>";
        echo "<td><strong>$break_status</strong></td>";
        echo "<td>$mandatory_status</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No time slots found.</p>";
}

echo "<p><a href='generate_timetable.php'>Go to Timetable Generation</a></p>";
?>
