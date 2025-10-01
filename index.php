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

// Validate stream selection
include 'includes/stream_validation.php';
$stream_validation = getCurrentStreamInfo($conn);
if (!$stream_validation['valid']) {
    // Show warning but don't redirect on dashboard
    $stream_warning = $stream_validation['message'];
}

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

// Count classes that need timetables (more meaningful than total timetable entries)
$classes_count = getCount($conn, "SELECT COUNT(*) AS c FROM classes WHERE stream_id = ? AND is_active = 1", $active_stream);

// Count distinct saved timetable versions (grouped by stream, version, semester)
$saved_timetables_count = 0;
try {
    // Check if we have the newer schema with class_course_id
    $col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
    $has_class_course = ($col && $col->num_rows > 0);
    if ($col) $col->close();
    
    if ($has_class_course) {
        // Newer schema: use class_course_id
        $saved_query = "
            SELECT COUNT(DISTINCT CONCAT(c.stream_id, '-', COALESCE(t.version, 'regular'), '-', t.semester)) AS c 
            FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            WHERE c.stream_id = ?
        ";
    } else {
        // Older schema: use class_id
        $saved_query = "
            SELECT COUNT(DISTINCT CONCAT(c.stream_id, '-', COALESCE(t.version, 'regular'), '-', t.semester)) AS c 
            FROM timetable t
            JOIN classes c ON t.class_id = c.id
            WHERE c.stream_id = ?
        ";
    }
    
    $stmt = $conn->prepare($saved_query);
    if ($stmt) {
        $stmt->bind_param("i", $active_stream);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $saved_timetables_count = $row ? intval($row['c']) : 0;
        $stmt->close();
    }
} catch (Exception $e) {
    // Fallback to 0 if there's an error
    $saved_timetables_count = 0;
}

// Get streams dynamically
$streams = [];
$result = $conn->query("SELECT id, name, is_active FROM streams ORDER BY id ASC");
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
  .count-description {
    font-size: 0.75rem;
    opacity: 0.8;
    text-align: left;
    line-height: 1.1;
    font-weight: normal;
    margin-top: 4px;
  }
  .stream-select { margin: 15px 0; }

  /* Page header: Dashboard title on left, stream switch aligned right */
  .page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }
  .page-header h2 { margin: 0; font-size: 1.35rem; font-weight: 700; }
  .stream-select { margin: 0; }

  /* Responsive: stack header elements on small screens */
  @media (max-width: 576px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }
    .page-header h2 { font-size: 1.2rem; }
    .stream-select { width: 100%; }
    .stream-select form { width: 100%; }
    .stream-select select { width: 100%; min-width: 0; box-sizing: border-box; }
  }

  /* Stream selector styling */
  .stream-select label {
    display: inline-block;
    margin-right: 8px;
    font-weight: 700;
    color: #333;
    font-size: 0.95rem;
  }
  .stream-select select {
    padding: 8px 12px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #fff;
    color: #333;
    font-size: 0.95rem;
    min-width: 220px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }
  .stream-select select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(128,0,32,0.08);
    outline: none;
  }
</style>

<div class="main-content" id="mainContent">
  <div class="page-header">
    <h2>Dashboard</h2>
    <div class="stream-select">
      <form id="streamForm">
        <label for="stream_id"><strong>Switch Stream:</strong></label>
        <select name="stream_id" id="stream_id">
          <?php foreach ($streams as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($active_stream == $s['id'] ? 'selected' : '') ?>>
              <?= htmlspecialchars($s['name']) ?><?= isset($s['is_active']) && !$s['is_active'] ? ' (inactive)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="switchStreamBtn" class="btn btn-outline-primary btn-sm ms-2">Switch</button>
      </form>
    </div>
  </div>

  <!-- Stream Warning -->
  <?php if (isset($stream_warning)): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong>Stream Selection Required:</strong> <?= htmlspecialchars($stream_warning) ?>
      <a href="streams.php" class="btn btn-sm btn-outline-warning ms-2">Select Stream</a>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

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
       <a href="generate_timetable.php" class="grid-button">
         <i class="fas fa-calendar-plus"></i>
         <div>Generate Timetable</div>
         <div class="count-description">active classes</div>
         <span class="count-circle"><?= $classes_count ?></span>
       </a>
     </div>
     
     <div class="col-md-3">
       <a href="saved_timetable.php" class="grid-button">
         <i class="fas fa-save"></i>
         <div>Saved Timetables</div>
         <div class="count-description">distinct versions</div>
         <span class="count-circle"><?= $saved_timetables_count ?></span>
       </a>
     </div>

    <div class="col-md-3">
      <a href="department.php" class="grid-button">
        <i class="fas fa-building"></i>
        <div>Departments</div>
        <div class="count-description">active depts</div>
        <span class="count-circle"><?= $dept_count ?></span>
      </a>
    </div>
    
    <div class="col-md-3">
      <a href="lecturers.php" class="grid-button">
        <i class="fas fa-chalkboard-teacher"></i>
        <div>Lecturers</div>
        <div class="count-description">teaching staff</div>
        <span class="count-circle"><?= $lect_count ?></span>
      </a>
    </div>
    
    <div class="col-md-3">
      <a href="rooms.php" class="grid-button">
        <i class="fas fa-door-open"></i>
        <div>Rooms</div>
        <div class="count-description">available spaces</div>
        <span class="count-circle"><?= $room_count ?></span>
      </a>
    </div>

    <div class="col-md-3">
      <a href="courses.php" class="grid-button">
        <i class="fas fa-book"></i>
        <div>Courses</div>
        <div class="count-description">active courses</div>
        <span class="count-circle"><?= $course_count ?></span>
      </a>
    </div>
  </div>
  
  <!-- Dashboard Overview -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Dashboard Overview</h5>
      <p class="card-text">Welcome to the AAMUSTED Timetable Generator! This dashboard provides quick access to the core modules for the 
        <strong><?= htmlspecialchars($current_stream_name) ?></strong> stream:
      </p>
      <ul class="mb-0">
        <li><strong>Timetable:</strong> <?= ($saved_timetables_count > 0) ? 'View existing timetables' : 'Generate new timetables'; ?></li>
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

  // Switch stream button: activates selected stream (server) and reloads
  document.getElementById('switchStreamBtn').addEventListener('click', function(){
    const sel = document.getElementById('stream_id');
    const streamId = sel ? sel.value : null;
    if (!streamId) return;

    fetch('change_stream.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'stream_id=' + encodeURIComponent(streamId)
    }).then(r => r.json()).then(data => {
      if (data && data.success) {
        window.location.reload();
      } else {
        alert('Failed to switch stream: ' + (data.message || 'unknown'));
      }
    }).catch(() => { alert('Network error while switching stream'); });
  });
</script>
</body>
</html>
