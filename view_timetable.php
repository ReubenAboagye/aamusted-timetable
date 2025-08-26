<?php
// Include database connection
include 'connect.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get values from the form
    $selected_class = $_POST['class'];
    $selected_semester = $_POST['semester'];

    // Corrected SQL query (use 'time_slot' instead of 'time')
    $query = "SELECT day, time_slot, course_id, lecturer_id, room_id 
              FROM timetable 
              WHERE class_id = ? AND semester = ?";

    // Prepare statement
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("ss", $selected_class, $selected_semester);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        die("Prepare failed: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable</title>
</head>
<body>

<h2>View Timetable</h2>

<form method="POST" action="view_timetable.php">
    <label for="class">Select Class:</label>
    <select name="class" required>
        <option value="1">Class 1</option>
        <option value="2">Class 2</option>
        <option value="3">Class 3</option>
    </select>

    <label for="semester">Select Semester:</label>
    <select name="semester" required>
        <option value="1">Semester 1</option>
        <option value="2">Semester 2</option>
    </select>

    <button type="submit">View Timetable</button>
</form>

<?php if (!empty($result) && $result->num_rows > 0): ?>
    <table border="1">
        <tr>
            <th>Day</th>
            <th>Time Slot</th>
            <th>Course ID</th>
            <th>Lecturer ID</th>
            <th>Room ID</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['day']) ?></td>
                <td><?= htmlspecialchars($row['time_slot']) ?></td>
                <td><?= htmlspecialchars($row['course_id']) ?></td>
                <td><?= htmlspecialchars($row['lecturer_id']) ?></td>
                <td><?= htmlspecialchars($row['room_id']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p>No timetable found for the selected class and semester.</p>
<?php endif; ?>

</body>
</html>
