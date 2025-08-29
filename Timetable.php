<?php
include 'connect.php';

// -------------------------
// When no semester is provided:
// Show the selection form with AJAX and a loading overlay.
if (!isset($_POST['semester'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Select Semester - TimeTable Generator</title>
      <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
      <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
      <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet" />
      <style>
        :root { 
          --primary-color: #800020; 
          --hover-color: #600010; 
          --accent-color: #FFD700; 
          --bg-color: #ffffff; 
          --sidebar-bg: #f8f8f8; 
          --footer-bg: #800020; 
        }
        body { 
          font-family: 'Open Sans', sans-serif; 
          background: var(--bg-color); 
          margin: 0; 
          padding-top: 70px; 
          font-size: 14px; 
        }
        .navbar { 
          background: var(--primary-color); 
          position: fixed; 
          top: 0; 
          width: 100%; 
          z-index: 1050; 
          box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .navbar-brand { 
          font-weight: 600; 
          font-size: 1.75rem; 
          display: flex; 
          align-items: center; 
          color: var(--accent-color) !important; 
        }
        #sidebarToggle { 
          border: none; 
          background: transparent; 
          color: #fff; 
          font-size: 1.5rem; 
          margin-right: 10px; 
        }
        .sidebar { 
          background: var(--sidebar-bg); 
          position: fixed; 
          top: 70px; 
          left: 0; 
          width: 250px; 
          padding: 20px; 
          box-shadow: 2px 0 5px rgba(0,0,0,0.1); 
          transition: transform 0.3s ease; 
          transform: translateX(-100%); 
        }
        .sidebar.show { 
          transform: translateX(0); 
        }
        .nav-links { 
          display: flex; 
          flex-direction: column; 
          gap: 5px; 
        }
        .nav-links a { 
          display: flex; 
          align-items: center; 
          width: 100%; 
          padding: 5px 10px; 
          color: var(--primary-color); 
          text-decoration: none; 
          font-weight: 600; 
          font-size: 1rem; 
          transition: background 0.3s, color 0.3s; 
        }
        .nav-links a:hover, .nav-links a.active { 
          background: var(--primary-color); 
          color: #fff; 
          border-radius: 4px; 
        }
        .main-content { 
          transition: margin-left 0.3s ease; 
          margin-left: 0; 
          padding: 20px; 
          height: calc(100vh - 70px); 
          overflow: auto; 
        }
        .main-content.shift { 
          margin-left: 250px; 
        }
        .footer { 
          background: var(--footer-bg); 
          color: #fff; 
          padding: 10px; 
          text-align: center; 
          position: fixed; 
          bottom: 0; 
          left: 0; 
          right: 0; 
          transition: left 0.3s ease; 
        }
        .footer.shift { 
          left: 250px; 
        }
        .timetable-form { 
          max-width: 400px; 
          margin: 0 auto; 
        }
        /* Loading overlay styles */
        #loadingOverlay {
          display: none;
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0,0,0,0.5);
          z-index: 9999;
          align-items: center;
          justify-content: center;
          flex-direction: column;
        }
        #loadingOverlay .loading-content {
          text-align: center;
        }
        #loadingOverlay .loading-content img {
          height: 80px;
          margin-bottom: 20px;
          border-radius: 50%;
          /* Pop animation using scale effect */
          animation: pop 1.5s infinite;
        }
        #loadingOverlay .loading-content #loadingPercentage {
          color: #fff;
          font-size: 24px;
        }
        @keyframes pop {
          0% { transform: scale(1); }
          50% { transform: scale(1.2); }
          100% { transform: scale(1); }
        }
      </style>
    </head>
    <body>
      <nav class="navbar navbar-dark">
        <div class="container-fluid">
          <button id="sidebarToggle"><i class="fas fa-bars"></i></button>
          <a class="navbar-brand text-white" href="#">
            <img src="images/aamustedLog.png" alt="AAMUSTED Logo" style="height:40px; margin-right:10px;">TimeTable Generator
          </a>
          <div class="ms-auto text-white" id="currentTime">12:00:00 PM</div>
        </div>
      </nav>
      <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
      <div class="sidebar" id="sidebar">
        <div class="nav-links">
          <a href="index.php" class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
          <a href="Timetable.php" class="<?= ($currentPage == 'Timetable.php') ? 'active' : '' ?>"><i class="fas fa-calendar-alt me-2"></i>Generate Timetable</a>
          <a href="view_timetable.php" class="<?= ($currentPage == 'view_timetable.php') ? 'active' : '' ?>"><i class="fas fa-table me-2"></i>View Timetable</a>
          <a href="department.php" class="<?= ($currentPage == 'department.php') ? 'active' : '' ?>"><i class="fas fa-building me-2"></i>Department</a>
          <a href="lecturer.php" class="<?= ($currentPage == 'lecturer.php') ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers</a>
          <a href="rooms.php" class="<?= ($currentPage == 'rooms.php') ? 'active' : '' ?>"><i class="fas fa-door-open me-2"></i>Rooms</a>
          <a href="courses.php" class="<?= ($currentPage == 'courses.php') ? 'active' : '' ?>"><i class="fas fa-book me-2"></i>Course</a>
          <a href="classes.php" class="<?= ($currentPage == 'classes.php') ? 'active' : '' ?>"><i class="fas fa-users me-2"></i>Classes</a>
          <a href="buildings.php" class="<?= ($currentPage == 'buildings.php') ? 'active' : '' ?>"><i class="fas fa-city me-2"></i>Buildings</a>
        </div>
      </div>
      <div class="main-content" id="mainContent">
        <h2>Select Semester to Generate Timetable</h2>
        <form id="generateTimetableForm" action="Timetable.php" method="POST" class="timetable-form mb-4">
          <div class="mb-3">
            <label for="semester" class="form-label">Semester:</label>
            <select name="semester" id="semester" class="form-select" required>
              <option value="" disabled selected>Select Semester</option>
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-100">Generate Timetable</button>
        </form>
        <div id="timetableContainer"></div>
      </div>
      <!-- Edit Timetable Button Added Here -->
    <div id="editTimetableButton" class="mt-3 text-center">
      <a href="edit_timetable.php" class="btn btn-secondary">Edit Timetable</a>
    </div>
      <div class="footer" id="footer">&copy; 2025 TimeTable Generator</div>
      <!-- Loading overlay with round logo and percentage -->
      <div id="loadingOverlay">
        <div class="loading-content">
          <img src="images/aamustedLog.png" alt="AAMUSTED Logo">
          <div id="loadingPercentage">0%</div>
        </div>
      </div>
      <button id="backToTop" style="display: none;">
        <svg width="50" height="50" viewBox="0 0 50 50">
          <circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"/>
        </svg>
        <i class="fas fa-arrow-up arrow-icon"></i>
      </button>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
      <script>
        // Update time in navbar
        function updateTime(){
          const now = new Date(),
                timeString = now.toLocaleTimeString('en-US', {hour12:true, hour:'2-digit', minute:'2-digit', second:'2-digit'});
          document.getElementById('currentTime').textContent = timeString;
        }
        setInterval(updateTime, 1000);
        updateTime();
        
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function(){
          const sidebar = document.getElementById('sidebar'),
                mainContent = document.getElementById('mainContent'),
                footer = document.getElementById('footer');
          sidebar.classList.toggle('show');
          if(sidebar.classList.contains('show')){
            mainContent.classList.add('shift');
            footer.classList.add('shift');
          } else {
            mainContent.classList.remove('shift');
            footer.classList.remove('shift');
          }
        });
        
        // AJAX submission for timetable generation with loading overlay and progress
        document.getElementById('generateTimetableForm').addEventListener('submit', function(e){
          e.preventDefault();
          var loadingOverlay = document.getElementById('loadingOverlay');
          loadingOverlay.style.display = 'flex';
          var xhr = new XMLHttpRequest();
          xhr.open('POST', 'Timetable.php', true);
          
          // Generate a progress token for this request and start polling server progress
          var progressToken = Math.random().toString(36).slice(2) + Date.now().toString(36);
          var pollId = null;
          function startPolling(){
            var endpoint = 'get_progress.php?token=' + encodeURIComponent(progressToken);
            pollId = setInterval(function(){
              fetch(endpoint, { cache: 'no-store' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                  if (data && typeof data.percent === 'number') {
                    var pct = Math.max(0, Math.min(100, Math.round(data.percent)));
                    document.getElementById('loadingPercentage').innerText = pct + '%';
                  }
                  if (data && data.done) {
                    clearInterval(pollId);
                  }
                })
                .catch(function(){ /* ignore polling errors */ });
            }, 700);
          }
          startPolling();
          
          xhr.onreadystatechange = function() {
            if(xhr.readyState == 4 && xhr.status == 200) {
              document.getElementById('timetableContainer').innerHTML = xhr.responseText;
              // In AJAX response, you may already include an Edit button if needed.
              if (pollId) { clearInterval(pollId); }
              loadingOverlay.style.display = 'none';
            }
          };
          
          var formData = new FormData(document.getElementById('generateTimetableForm'));
          formData.append('ajax', 'true'); // flag for AJAX response
          formData.append('progress_token', progressToken);
          xhr.send(formData);
        });
        
        // Back-to-top button
        const backToTopButton = document.getElementById("backToTop"),
              progressCircle = document.getElementById("progressCircle"),
              circumference = 2 * Math.PI * 20;
        progressCircle.style.strokeDasharray = circumference;
        progressCircle.style.strokeDashoffset = circumference;
        window.addEventListener("scroll", function(){
          const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
          backToTopButton.style.display = scrollTop > 100 ? "block" : "none";
          const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight,
                scrollPercentage = scrollTop / scrollHeight;
          progressCircle.style.strokeDashoffset = circumference - (scrollPercentage * circumference);
        });
        backToTopButton.addEventListener("click", function(){
          window.scrollTo({top:0, behavior:"smooth"});
        });
      </script>
    </body>
    </html>
    <?php
    $conn->close();
    exit;
}

// -------------------------
// When a semester is provided, generate the timetable

$semester = intval($_POST['semester']);

// 1. Retrieve courses (with lecturer info) for the selected semester.
$sql = "SELECT co.course_id, co.course_name, cc.class_id, l.lecturer_id, l.lecturer_name
        FROM course co
        JOIN class_course cc ON co.course_id = cc.course_id
        JOIN lecturer_course lc ON co.course_id = lc.course_id
        JOIN lecturer l ON lc.lecturer_id = l.lecturer_id
        WHERE co.semester = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing courses statement: " . $conn->error);
}

$stmt->bind_param("i", $semester);
$stmt->execute();
$coursesResult = $stmt->get_result();

if (!$coursesResult) {
    die("Error retrieving courses: " . $conn->error);
}

$courses = [];
while ($row = $coursesResult->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

if (empty($courses)) {
    die("No courses available for Semester $semester.");
}

// 2. Retrieve distinct class information for the selected semester.
$classSql = "SELECT DISTINCT c.class_id, c.class_name, c.class_size
             FROM class c
             JOIN class_course cc ON c.class_id = cc.class_id
             JOIN course co ON cc.course_id = co.course_id
             WHERE co.semester = ?";

$stmt = $conn->prepare($classSql);
if (!$stmt) {
    die("Error preparing class statement: " . $conn->error);
}

$stmt->bind_param("i", $semester);
$stmt->execute();
$classResult = $stmt->get_result();

if (!$classResult) {
    die("Error retrieving class information: " . $conn->error);
}

$classes = [];
while ($row = $classResult->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

if (empty($classes)) {
    die("No classes found for Semester $semester.");
}

// 3. Retrieve rooms from the room table with capacity information.
$roomSql = "SELECT room_id, room_name, capacity, room_type FROM room";
$roomResult = $conn->query($roomSql);
if (!$roomResult) {
    die("Error retrieving rooms: " . $conn->error);
}
$rooms = [];
while ($row = $roomResult->fetch_assoc()) {
    $rooms[] = $row;
}
if (empty($rooms)) {
    die("No rooms found.");
}

// 4. Retrieve lecturer information with availability constraints
$lecturerSql = "SELECT l.lecturer_id, l.lecturer_name, l.max_daily_courses, l.preferred_times
                FROM lecturer l
                JOIN lecturer_course lc ON l.lecturer_id = lc.lecturer_id
                JOIN course co ON lc.course_id = co.course_id
                WHERE co.semester = ?
                GROUP BY l.lecturer_id";

$stmt = $conn->prepare($lecturerSql);
if (!$stmt) {
    die("Error preparing lecturer statement: " . $conn->error);
}

$stmt->bind_param("i", $semester);
$stmt->execute();
$lecturerResult = $stmt->get_result();

$lecturers = [];
while ($row = $lecturerResult->fetch_assoc()) {
    $lecturers[] = $row;
}
$stmt->close();

// Include the improved GA timetable generator.
include 'ga_timetable_generator.php';

// Create an instance of the GA using classes, courses, rooms, and lecturers.
$ga = new GeneticAlgorithm($classes, $courses, $rooms, $lecturers);

// Pass through progress token if provided (for AJAX polling)
if (isset($_POST['progress_token'])) {
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['progress_token']);
    if ($token) {
        // Ensure uploads directory exists
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        // Seed an initial progress file
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'progress_' . $token . '.json', json_encode([
            'generation' => 0,
            'total' => 0,
            'percent' => 0,
            'bestFitness' => 0,
            'done' => false,
            'timestamp' => time()
        ]), LOCK_EX);
        // Set token into GA to write progress during evolve()
        if (method_exists($ga, 'setProgressToken')) {
            $ga->setProgressToken($token);
        }
    }
}

// Initialize with a larger population for better results
$ga->initializePopulation(100);

// Evolve for more generations with the improved algorithm
$bestTimetable = $ga->evolve(200);

// Get constraint violation report
$constraintReport = $ga->getConstraintReport($bestTimetable);

// Insert the GA-generated timetable into the database using INSERT IGNORE.
$insertedCount = 0;
$errorCount = 0;

foreach ($bestTimetable as $entry) {
    $class_id    = $entry['class_id'];
    $course_id   = $entry['course_id'];
    $lecturer_id = $entry['lecturer_id'];
    $room_id     = $entry['room_id'];
    $day         = $entry['day'];
    $time_slot   = $entry['time_slot'];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO timetable (semester, day, time_slot, class_id, course_id, lecturer_id, room_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $errorCount++;
        continue;
    }
    
    $stmt->bind_param("issiiii", $semester, $day, $time_slot, $class_id, $course_id, $lecturer_id, $room_id);
    if ($stmt->execute()) {
        $insertedCount++;
    } else {
        $errorCount++;
    }
    $stmt->close();
}

// Display generation results
echo "<div class='alert alert-info'>";
echo "<h4>Timetable Generation Complete!</h4>";
echo "<p><strong>Generated for:</strong> Semester $semester</p>";
echo "<p><strong>Courses assigned:</strong> $insertedCount</p>";
echo "<p><strong>Errors:</strong> $errorCount</p>";
echo "<p><strong>Fitness Score:</strong> " . number_format($constraintReport['fitness_score'], 4) . "</p>";
echo "</div>";

// Build a grid for rendering.
// Define days and time blocks for rendering. We include a fixed break slot.
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$timeBlocks = [
    '07:00-10:00',
    '10:00-13:00',
    'BREAK (13:00-14:00)',  // Fixed break column.
    '14:00-17:00',
    '17:00-20:00'
];
$timetableGrid = [];
foreach ($days as $d) {
    foreach ($timeBlocks as $tb) {
        if ($tb === 'BREAK (13:00-14:00)') {
            $timetableGrid[$d][$tb] = "BREAK";
        } else {
            $timetableGrid[$d][$tb] = [];
        }
    }
}

// Populate the grid with GA results.
foreach ($bestTimetable as $entry) {
    $day = $entry['day'];
    $timeSlot = $entry['time_slot']; // Must be one of the available slots.
    if (in_array($timeSlot, ['07:00-10:00','10:00-13:00','14:00-17:00','17:00-20:00'])) {
        $className = $entry['class_name'];
        $courseName = $entry['course_name'];
        $lecturerName = $entry['lecturer_name'];
        $roomName = $entry['room_name'];
        $info = "<strong>$className</strong><br>$courseName<br>$lecturerName<br><em>Room: $roomName</em>";
        $timetableGrid[$day][$timeSlot][] = $info;
    }
}

// If this is an AJAX request, output only the timetable table markup along with an "Edit Timetable" button.
if(isset($_POST['ajax']) && $_POST['ajax'] == 'true'){
    ?>
    <table class="table table-bordered text-center align-middle">
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
            <th scope="row" style="white-space: nowrap;"><?php echo htmlspecialchars($d); ?></th>
            <?php foreach ($timeBlocks as $tb): ?>
              <td>
                <?php
                if ($tb === 'BREAK (13:00-14:00)') {
                    echo "BREAK";
                } else {
                    $entries = $timetableGrid[$d][$tb];
                    if (!empty($entries)) {
                        echo implode('<hr>', $entries);
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
    <div id="editTimetableButton" class="mt-3 text-center">
      <a href="edit_timetable.php" class="btn btn-secondary">Edit Timetable</a>
    </div>
    <?php
    $conn->close();
    exit;
}
  
// If not an AJAX request, output the full UI for the generated timetable.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Generated Timetable</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />  
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    :root { 
      --primary-color: #800020; 
      --hover-color: #600010; 
      --accent-color: #FFD700; 
      --bg-color: #ffffff; 
      --sidebar-bg: #f8f8f8; 
      --footer-bg: #800020; 
    }
    body { 
      font-family: 'Open Sans', sans-serif; 
      background: var(--bg-color); 
      margin: 0; 
      padding-top: 70px; 
      font-size: 14px; 
    }
    .navbar { 
      background: var(--primary-color); 
      position: fixed; 
      top: 0; 
      width: 100%; 
      z-index: 1050; 
      box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
    }
    .navbar-brand { 
      font-weight: 600; 
      font-size: 1.75rem; 
      display: flex; 
      align-items: center; 
      color: var(--accent-color) !important; 
    }
    #sidebarToggle { 
      border: none; 
      background: transparent; 
      color: #fff; 
      font-size: 1.5rem; 
      margin-right: 10px; 
    }
    .sidebar { 
      background: var(--sidebar-bg); 
      position: fixed; 
      top: 70px; 
      left: 0; 
      width: 250px; 
      padding: 20px; 
      box-shadow: 2px 0 5px rgba(0,0,0,0.1); 
      transition: transform 0.3s ease; 
      transform: translateX(-100%); 
    }
    .sidebar.show { 
      transform: translateX(0); 
    }
    .nav-links { 
      display: flex; 
      flex-direction: column; 
      gap: 5px; 
    }
    .nav-links a { 
      display: flex; 
      align-items: center; 
      width: 100%; 
      padding: 5px 10px; 
      color: var(--primary-color); 
      text-decoration: none; 
      font-weight: 600; 
      font-size: 1rem; 
      transition: background 0.3s, color 0.3s; 
    }
    .nav-links a:hover, .nav-links a.active { 
      background: var(--primary-color); 
      color: #fff; 
      border-radius: 4px; 
    }
    .main-content { 
      transition: margin-left 0.3s ease; 
      margin-left: 0; 
      padding: 20px; 
      height: calc(100vh - 70px); 
      overflow: auto; 
    }
    .main-content.shift { 
      margin-left: 250px; 
    }
    .footer { 
      background: var(--footer-bg); 
      color: #fff; 
      padding: 10px; 
      text-align: center; 
      position: fixed; 
      bottom: 0; 
      left: 0; 
      right: 0; 
      transition: left 0.3s ease; 
    }
    .footer.shift { 
      left: 250px; 
    }
    table { margin-top: 20px; }
    th, td { vertical-align: middle !important; }
  </style>
</head>
<body>
  <nav class="navbar navbar-dark">
    <div class="container-fluid">
      <button id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <a class="navbar-brand text-white" href="#">
        <img src="images/aamustedLog.png" alt="AAMUSTED Logo" style="height:40px; margin-right:10px;">TimeTable Generator
      </a>
      <div class="ms-auto text-white" id="currentTime">12:00:00 PM</div>
    </div>
  </nav>
  <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
  <div class="sidebar" id="sidebar">
    <div class="nav-links">
      <a href="index.php" class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
      <a href="Timetable.php" class="<?= ($currentPage == 'Timetable.php') ? 'active' : '' ?>"><i class="fas fa-calendar-alt me-2"></i>Generate Timetable</a>
      <a href="view_timetable.php" class="<?= ($currentPage == 'view_timetable.php') ? 'active' : '' ?>"><i class="fas fa-table me-2"></i>View Timetable</a>
      <a href="department.php" class="<?= ($currentPage == 'department.php') ? 'active' : '' ?>"><i class="fas fa-building me-2"></i>Department</a>
      <a href="lecturer.php" class="<?= ($currentPage == 'lecturer.php') ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers</a>
      <a href="rooms.php" class="<?= ($currentPage == 'rooms.php') ? 'active' : '' ?>"><i class="fas fa-door-open me-2"></i>Rooms</a>
      <a href="courses.php" class="<?= ($currentPage == 'courses.php') ? 'active' : '' ?>"><i class="fas fa-book me-2"></i>Course</a>
      <a href="classes.php" class="<?= ($currentPage == 'classes.php') ? 'active' : '' ?>"><i class="fas fa-users me-2"></i>Classes</a>
      <a href="buildings.php" class="<?= ($currentPage == 'buildings.php') ? 'active' : '' ?>"><i class="fas fa-city me-2"></i>Buildings</a>
    </div>
  </div>
  <div class="main-content" id="mainContent">
    <h3 class="mb-3">Generated Timetable for Semester <?php echo htmlspecialchars($semester); ?></h3>
    <table class="table table-bordered text-center align-middle">
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
            <th scope="row" style="white-space: nowrap;"><?php echo htmlspecialchars($d); ?></th>
            <?php foreach ($timeBlocks as $tb): ?>
              <td>
                <?php
                if ($tb === 'BREAK (13:00-14:00)') {
                    echo "BREAK";
                } else {
                    $entries = $timetableGrid[$d][$tb];
                    if (!empty($entries)) {
                        echo implode('<hr>', $entries);
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
   
  </div>
  <div class="footer" id="footer">&copy; 2025 TimeTable Generator</div>
  <button id="backToTop" style="display: none;">
    <svg width="50" height="50" viewBox="0 0 50 50">
      <circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"/>
    </svg>
    <i class="fas fa-arrow-up arrow-icon"></i>
  </button>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
  <script>
    function updateTime(){
      const now = new Date(),
            timeString = now.toLocaleTimeString('en-US', {hour12:true, hour:'2-digit', minute:'2-digit', second:'2-digit'});
      document.getElementById('currentTime').textContent = timeString;
    }
    setInterval(updateTime, 1000);
    updateTime();
    document.getElementById('sidebarToggle').addEventListener('click', function(){
      const sidebar = document.getElementById('sidebar'),
            mainContent = document.getElementById('mainContent'),
            footer = document.getElementById('footer');
      sidebar.classList.toggle('show');
      if(sidebar.classList.contains('show')){
        mainContent.classList.add('shift');
        footer.classList.add('shift');
      } else {
        mainContent.classList.remove('shift');
        footer.classList.remove('shift');
      }
    });
    const backToTopButton = document.getElementById("backToTop"),
          progressCircle = document.getElementById("progressCircle"),
          circumference = 2 * Math.PI * 20;
    progressCircle.style.strokeDasharray = circumference;
    progressCircle.style.strokeDashoffset = circumference;
    window.addEventListener("scroll", function(){
      const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
      backToTopButton.style.display = scrollTop > 100 ? "block" : "none";
      const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight,
            scrollPercentage = scrollTop / scrollHeight;
      progressCircle.style.strokeDashoffset = circumference - (scrollPercentage * circumference);
    });
    backToTopButton.addEventListener("click", function(){
      window.scrollTo({top:0, behavior:"smooth"});
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>
