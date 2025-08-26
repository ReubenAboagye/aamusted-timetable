<?php
include 'connect.php';

// Check if semester is provided via POST or GET
if (!isset($_POST['semester']) && !isset($_GET['semester'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Select Semester - TimeTable Generator</title>
      <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
    </head>
    <body class="p-4">
      <h3>Select a Semester to Edit Timetable</h3>
      <form action="edit_timetable.php" method="GET">
        <div class="mb-3">
          <label for="semester" class="form-label">Semester:</label>
          <select name="semester" id="semester" class="form-select" required>
            <option value="" disabled selected>Select Semester</option>
            <option value="1">Semester 1</option>
            <option value="2">Semester 2</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">View Timetable</button>
      </form>
    </body>
    </html>
    <?php
    $conn->close();
    exit;
}

// Get semester from POST or GET
$semester = isset($_POST['semester']) ? intval($_POST['semester']) : intval($_GET['semester']);

// Query the stored timetable for this semester, joining additional details.
$sql = "SELECT t.timetable_id, t.semester, t.day, t.time_slot, t.class_id, t.course_id, t.lecturer_id, t.room_id,
               c.class_name, co.course_name, l.lecturer_name, r.room_name
        FROM timetable t
        JOIN class c ON t.class_id = c.class_id
        JOIN course co ON t.course_id = co.course_id
        JOIN lecturer l ON t.lecturer_id = l.lecturer_id
        JOIN room r ON t.room_id = r.room_id
        WHERE t.semester = $semester
        ORDER BY FIELD(t.day, 'Monday','Tuesday','Wednesday','Thursday','Friday'), t.time_slot";
$result = $conn->query($sql);
$timetable = [];
while ($row = $result->fetch_assoc()) {
    $timetable[] = $row;
}

// Define days and time blocks for rendering.
// We use the same available teaching slots plus a fixed break column.
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$timeBlocks = [
    '07:00-10:00',
    '10:00-13:00',
    'BREAK (13:00-14:00)',  // Fixed break
    '14:00-17:00',
    '17:00-20:00'
];

// Build a grid: for each day and slot, collect entries.
$grid = [];
foreach ($days as $d) {
    foreach ($timeBlocks as $tb) {
        if ($tb === 'BREAK (13:00-14:00)') {
            $grid[$d][$tb] = "BREAK";
        } else {
            $grid[$d][$tb] = [];
        }
    }
}
foreach ($timetable as $entry) {
    $d = $entry['day'];
    $ts = $entry['time_slot']; // Must match one of the available slots
    if (isset($grid[$d][$ts])) {
        // Create a display block including an Edit link.
        $info = "<div class='edit-entry' style='font-size: 0.9em;'>
                    <strong>{$entry['class_name']}</strong><br>
                    {$entry['course_name']}<br>
                    {$entry['lecturer_name']}<br>
                    <em>Room: {$entry['room_name']}</em><br>
                    <a href='update_timetable.php?timetable_id={$entry['timetable_id']}' class='btn btn-sm btn-primary mt-1'>Edit</a>
                 </div>";
        $grid[$d][$ts][] = $info;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Timetable - TimeTable Generator</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
  <style>
    table { margin-top: 20px; }
    td, th { vertical-align: middle; }
  </style>
</head>
<body class="p-4">
  <h3>Edit Timetable for Semester <?php echo htmlspecialchars($semester); ?></h3>
  <p>
    <a href="try.php">Generate New Timetable</a> | 
    <a href="reoptimize.php?semester=<?php echo $semester; ?>" class="btn btn-warning btn-sm">Re-optimize Timetable</a>
  </p>
  <table class="table table-bordered text-center">
    <thead>
      <tr>
        <th>Day</th>
        <?php foreach ($timeBlocks as $tb): ?>
          <th><?php echo htmlspecialchars($tb); ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($days as $d): ?>
      <tr>
        <th><?php echo htmlspecialchars($d); ?></th>
        <?php foreach ($timeBlocks as $tb): ?>
        <td>
          <?php
          if ($tb === 'BREAK (13:00-14:00)') {
             echo "BREAK";
          } else {
             if (!empty($grid[$d][$tb])) {
                 echo implode('<hr>', $grid[$d][$tb]);
             } else {
                 echo '—';
             }
          }
          ?>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
<?php
$conn->close();
?>
