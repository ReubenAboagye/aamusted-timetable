<?php
include 'connect.php';
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$timeSlots = ['07:00-10:00','10:00-13:00','14:00-17:00','17:00-20:00'];

// If the form is submitted, process the update.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $timetable_id = intval($_POST['timetable_id']);
    $day = $_POST['day'];
    $time_slot = $_POST['time_slot'];
    $room_id = intval($_POST['room_id']);
    // Update the timetable record.
    $stmt = $conn->prepare("UPDATE timetable SET day = ?, time_slot = ?, room_id = ? WHERE timetable_id = ?");
    if (!$stmt) { die("Prepare failed: " . $conn->error); }
    $stmt->bind_param("ssii", $day, $time_slot, $room_id, $timetable_id);
    if ($stmt->execute()) {
        echo "Timetable entry updated successfully. <a href='edit_timetable.php?semester=" . $_POST['semester'] . "'>Go back</a>";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Otherwise, display the edit form.
if (!isset($_GET['timetable_id'])) {
    die("No timetable entry specified.");
}
$timetable_id = intval($_GET['timetable_id']);

// Retrieve the current timetable entry.
$sql = "SELECT * FROM timetable WHERE timetable_id = $timetable_id";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    die("No timetable entry found.");
}
$entry = $result->fetch_assoc();

// Get list of available rooms.
$roomSql = "SELECT * FROM room";
$roomResult = $conn->query($roomSql);
$rooms = [];
while ($row = $roomResult->fetch_assoc()) {
    $rooms[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update Timetable Entry</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h3>Update Timetable Entry</h3>
  <form action="update_timetable.php" method="POST">
    <input type="hidden" name="timetable_id" value="<?php echo htmlspecialchars($entry['timetable_id']); ?>">
    <input type="hidden" name="semester" value="<?php echo htmlspecialchars($entry['semester']); ?>">
    <div class="mb-3">
      <label for="day" class="form-label">Day</label>
      <select name="day" id="day" class="form-select" required>
        <?php foreach ($days as $d): ?>
          <option value="<?php echo $d; ?>" <?php if ($entry['day'] == $d) echo "selected"; ?>>
            <?php echo $d; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label for="time_slot" class="form-label">Time Slot</label>
      <select name="time_slot" id="time_slot" class="form-select" required>
        <?php foreach ($timeSlots as $ts): ?>
          <option value="<?php echo $ts; ?>" <?php if ($entry['time_slot'] == $ts) echo "selected"; ?>>
            <?php echo $ts; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label for="room_id" class="form-label">Room</label>
      <select name="room_id" id="room_id" class="form-select" required>
        <?php foreach ($rooms as $room): ?>
          <option value="<?php echo $room['room_id']; ?>" <?php if ($entry['room_id'] == $room['room_id']) echo "selected"; ?>>
            <?php echo htmlspecialchars($room['room_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Update Entry</button>
  </form>
</body>
</html>
<?php
$conn->close();
?>
