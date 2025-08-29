<?php
session_start();
include 'connect.php';

// --- Handle selected stream ---
// If user selects a new stream from dropdown, update session
if (isset($_GET['stream_id'])) {
    $_SESSION['active_stream'] = intval($_GET['stream_id']);
}

// Fallback: default = 1 (Regular)
$active_stream = $_SESSION['active_stream'] ?? 1;

// Helper function to safely count rows
function getCount($conn, $query, $stream_id = null) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        return 0;
    }

    // Bind parameter only if query contains a placeholder
    if (strpos($query, '?') !== false && $stream_id !== null) {
        $stmt->bind_param("i", $stream_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? intval($row['c']) : 0;
}

// Counts per stream
$dept_count      = getCount($conn, "SELECT COUNT(*) AS c FROM departments WHERE is_active = 1", $active_stream);
$course_count    = getCount($conn, "SELECT COUNT(*) AS c FROM courses WHERE is_active = 1", $active_stream);
$lect_count      = getCount($conn, "SELECT COUNT(*) AS c FROM lecturers WHERE is_active = 1", $active_stream);
$room_count      = getCount($conn, "SELECT COUNT(*) AS c FROM rooms WHERE is_active = 1", $active_stream);
$timetable_count = getCount($conn, "SELECT COUNT(*) AS c FROM timetable", $active_stream);

// Get streams dynamically
$streams = [];
$result = $conn->query("SELECT id, name FROM streams WHERE is_active = 1 ORDER BY id ASC");
while ($row = $result->fetch_assoc()) {
    $streams[] = $row;
}

// Get current stream name
$current_stream_name = 'Selected';
foreach ($streams as $s) {
    if ($s['id'] == $active_stream) {
        $current_stream_name = $s['name'];
        break;
    }
}
?>

<?php $pageTitle = 'Dashboard'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

<style>
  .dashboard-search { margin: 10px 0 20px; }
  #dashboardGrid .col-md-3 { min-width: 260px; }
  .grid-button {
    position: relative;
    display: block;
    padding: 20px;
    border-radius: 14px;
    color: #fff;
    text-decoration: none;
    min-height: 120px;
    box-shadow: 0 8px 18px rgba(0,0,0,0.15);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    background-color: var(--primary-color);
  }
  .grid-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.2);
    color: #fff;
    background-color: var(--hover-color);
  }
  .grid-button i {
    font-size: 28px;
    margin-bottom: 10px;
    opacity: 0.95;
  }
  .grid-button > div { font-weight: 700; letter-spacing: 0.3px; }
  .count-circle {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    font-weight: 700;
    min-width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 8px;
    backdrop-filter: blur(2px);
  }
  .stream-select { margin: 15px 0; }
</style>

<div class="main-content" id="mainContent">
  <h2>Dashboard</h2>

  <!-- Current Stream -->
  <h5 style="margin-top:10px; color:#444;">
    📌 Current Stream: <strong><?= htmlspecialchars($current_stream_name) ?></strong>
  </h5>

  <!-- Stream Selector -->
  <div class="stream-select">
    <form method="get" id="streamForm">
      <label for="stream_id"><strong>Switch Stream:</strong></label>
      <select name="stream_id" id="stream_id" onchange="document.getElementById('streamForm').submit();">
        <?php foreach ($streams as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($active_stream == $s['id'] ? 'selected' : '') ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <!-- Dashboard Search Bar -->
  <div class="dashboard-search">
    <div class="input-group" style="width: 300px;">
      <span class="input-group-text"><i class="fas fa-search"></i></span>
      <input type="text" id="dashboardSearchInput" class="form-control" placeholder="Search modules...">
    </div>
  </div>

  <!-- Grid Buttons -->
  <div class="row g-3 mb-3" id="dashboardGrid">
    <div class="col-md-3">
      <?php if ($timetable_count > 0): ?>
        <a href="view_timetable.php" class="grid-button">
          <i class="fas fa-table"></i>
          <div>View Timetable</div>
          <span class="count-circle"><?= $timetable_count ?></span>
        </a>
      <?php else: ?>
        <a href="generate_timetable.php" class="grid-button">
          <i class="fas fa-calendar-plus"></i>
          <div>Generate Timetable</div>
          <span class="count-circle">0</span>
        </a>
      <?php endif; ?>
    </div>
    
    <div class="col-md-3">
      <a href="adddepartmentform.php" class="grid-button">
        <i class="fas fa-building"></i>
        <div>Departments</div>
        <span class="count-circle"><?= $dept_count ?></span>
      </a>
    </div>
    
    <div class="col-md-3">
      <a href="lecturers.php" class="grid-button">
        <i class="fas fa-chalkboard-teacher"></i>
        <div>Lecturers</div>
        <span class="count-circle"><?= $lect_count ?></span>
      </a>
    </div>
    
    <div class="col-md-3">
      <a href="rooms.php" class="grid-button">
        <i class="fas fa-door-open"></i>
        <div>Rooms</div>
        <span class="count-circle"><?= $room_count ?></span>
      </a>
    </div>

    <div class="col-md-3">
      <a href="courses.php" class="grid-button">
        <i class="fas fa-book"></i>
        <div>Courses</div>
        <span class="count-circle"><?= $course_count ?></span>
      </a>
    </div>
  </div>
  
  <!-- Dashboard Overview -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Dashboard Overview</h5>
      <p class="card-text">Welcome to the University Timetable Generator! This dashboard provides quick access to the core modules for the 
        <strong><?= htmlspecialchars($current_stream_name) ?></strong> stream:
      </p>
      <ul class="mb-0">
        <li><strong>Timetable:</strong> <?= ($timetable_count > 0) ? 'View existing timetables' : 'Generate new timetables'; ?></li>
        <li><strong>Departments:</strong> Manage academic departments</li>
        <li><strong>Courses:</strong> Manage courses offered</li>
        <li><strong>Lecturers:</strong> Manage teaching staff</li>
        <li><strong>Rooms:</strong> Manage physical spaces</li>
      </ul>
      <p class="mt-2 mb-0"><small class="text-muted">Access all modules through the sidebar navigation for comprehensive system management.</small></p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; $conn->close(); ?>
<script>
  // Update current time in header
  function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
      hour12: true,
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
    document.getElementById('currentTime').textContent = timeString;
  }
  setInterval(updateTime, 1000);
  updateTime();

  // Dashboard grid search functionality
  document.getElementById('dashboardSearchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const gridButtons = document.querySelectorAll('#dashboardGrid .grid-button');
    gridButtons.forEach(button => {
      const text = button.textContent.toLowerCase();
      button.parentElement.style.display = text.includes(searchValue) ? '' : 'none';
    });
  });
</script>
</body>
</html>
