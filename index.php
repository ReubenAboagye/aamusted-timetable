<?php
// Include custom error handler for better error display
include_once 'includes/custom_error_handler.php';

session_start();
include 'connect.php';

// --- Handle selected stream ---
// If user selects a new stream from dropdown, update session
if (isset($_GET['stream_id'])) {
    $_SESSION['active_stream'] = intval($_GET['stream_id']);
}

// Validate stream selection first
include 'includes/stream_validation.php';
$stream_validation = getCurrentStreamInfo($conn);

if (!$stream_validation['valid']) {
    // Show warning but don't redirect on dashboard
    $stream_warning = $stream_validation['message'];
    // Set fallback values
    $active_stream = 1;
    $current_stream_name = 'No Stream Selected';
} else {
    // Ensure we have proper stream information for header
    $active_stream = $stream_validation['stream_id'];
    $current_stream_name = $stream_validation['stream_name'];
    // Update session to ensure consistency
    $_SESSION['active_stream'] = $active_stream;
    $_SESSION['current_stream_id'] = $active_stream;
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

// Counts per stream - check if tables have stream_id column
$dept_count = 0;
$course_count = 0;
$lect_count = 0;
$room_count = 0;

// Check departments table
$col = $conn->query("SHOW COLUMNS FROM departments LIKE 'stream_id'");
$has_dept_stream = ($col && $col->num_rows > 0);
if ($col) $col->close();
if ($has_dept_stream) {
    $dept_count = getCount($conn, "SELECT COUNT(*) AS c FROM departments WHERE is_active = 1 AND stream_id = ?", $active_stream);
} else {
    $dept_count = getCount($conn, "SELECT COUNT(*) AS c FROM departments WHERE is_active = 1");
}

// Check courses table
$col = $conn->query("SHOW COLUMNS FROM courses LIKE 'stream_id'");
$has_course_stream = ($col && $col->num_rows > 0);
if ($col) $col->close();
if ($has_course_stream) {
    $course_count = getCount($conn, "SELECT COUNT(*) AS c FROM courses WHERE is_active = 1 AND stream_id = ?", $active_stream);
} else {
    $course_count = getCount($conn, "SELECT COUNT(*) AS c FROM courses WHERE is_active = 1");
}

// Check lecturers table
$col = $conn->query("SHOW COLUMNS FROM lecturers LIKE 'stream_id'");
$has_lect_stream = ($col && $col->num_rows > 0);
if ($col) $col->close();
if ($has_lect_stream) {
    $lect_count = getCount($conn, "SELECT COUNT(*) AS c FROM lecturers WHERE is_active = 1 AND stream_id = ?", $active_stream);
} else {
    $lect_count = getCount($conn, "SELECT COUNT(*) AS c FROM lecturers WHERE is_active = 1");
}

// Check rooms table
$col = $conn->query("SHOW COLUMNS FROM rooms LIKE 'stream_id'");
$has_room_stream = ($col && $col->num_rows > 0);
if ($col) $col->close();
if ($has_room_stream) {
    $room_count = getCount($conn, "SELECT COUNT(*) AS c FROM rooms WHERE is_active = 1 AND stream_id = ?", $active_stream);
} else {
    $room_count = getCount($conn, "SELECT COUNT(*) AS c FROM rooms WHERE is_active = 1");
}

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

// Get current stream name from validation result
if (isset($stream_validation['stream_name'])) {
    $current_stream_name = $stream_validation['stream_name'];
} else {
    $current_stream_name = 'No Stream Selected';
}
?>

<?php $pageTitle = 'Dashboard'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

<style>
  /* Enhanced Dashboard Styling with Design System */
  .dashboard-search { 
    margin: var(--spacing-sm) 0 var(--spacing-lg); 
  }
  
  #dashboardGrid .col-md-3 { 
    min-width: 260px; 
    margin-bottom: var(--spacing-md);
  }
  
  .grid-button {
    position: relative;
    display: block;
    padding: var(--spacing-lg);
    border-radius: var(--radius-lg);
    color: #fff;
    text-decoration: none;
    min-height: 120px;
    box-shadow: var(--shadow-md);
    transition: all 0.2s ease;
    background-color: var(--primary-color);
    border: 1px solid rgba(255,255,255,0.1);
  }
  
  .grid-button:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    color: #fff;
    background-color: var(--hover-color);
    text-decoration: none;
  }
  
  .grid-button i {
    font-size: var(--font-size-2xl);
    margin-bottom: var(--spacing-sm);
    opacity: 0.95;
  }
  
  .grid-button > div { 
    font-weight: var(--font-weight-bold); 
    letter-spacing: 0.3px; 
    font-size: var(--font-size-lg);
  }
  
  .count-circle {
    position: absolute;
    top: var(--spacing-sm);
    right: var(--spacing-sm);
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    font-weight: var(--font-weight-bold);
    min-width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 var(--spacing-sm);
    backdrop-filter: blur(2px);
    font-size: var(--font-size-sm);
  }
  
  .count-description {
    font-size: var(--font-size-xs);
    opacity: 0.8;
    text-align: left;
    line-height: 1.1;
    font-weight: var(--font-weight-normal);
    margin-top: var(--spacing-xs);
  }
  
  .stream-select { 
    margin: var(--spacing-md) 0; 
  }

  /* Enhanced Page Header */
  .page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
    flex-wrap: wrap;
  }
  
  .page-header h2 { 
    margin: 0; 
    font-size: var(--font-size-2xl); 
    font-weight: var(--font-weight-bold); 
    color: var(--text-primary);
  }
  
  .stream-select { 
    margin: 0; 
  }

  /* Enhanced Stream Selector */
  .stream-select label {
    display: inline-block;
    margin-right: var(--spacing-sm);
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    font-size: var(--font-size-sm);
  }
  
  .stream-select select {
    padding: var(--spacing-sm) var(--spacing-md);
    border: 2px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: #fff;
    color: var(--text-primary);
    font-size: var(--font-size-sm);
    min-width: 220px;
    box-shadow: var(--shadow-sm);
    transition: all 0.2s ease;
    font-weight: var(--font-weight-medium);
  }
  
  .stream-select select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(128,0,32,0.25);
    outline: none;
  }

  /* Enhanced Mobile Responsiveness */
  @media (max-width: 1200px) {
    #dashboardGrid .col-md-3 { 
      min-width: 240px; 
    }
    .grid-button {
      min-height: 110px;
      padding: var(--spacing-md);
    }
    .grid-button i {
      font-size: var(--font-size-xl);
    }
  }
  
  @media (max-width: 992px) {
    .page-header h2 { 
      font-size: var(--font-size-xl); 
    }
    .grid-button {
      min-height: 100px;
      padding: var(--spacing-md);
    }
    .grid-button i {
      font-size: var(--font-size-lg);
    }
    .grid-button > div {
      font-size: var(--font-size-base);
    }
  }
  
  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: var(--spacing-sm);
    }
    .page-header h2 { 
      font-size: var(--font-size-lg); 
    }
    .stream-select { 
      width: 100%; 
    }
    .stream-select form { 
      width: 100%; 
    }
    .stream-select select { 
      width: 100%; 
      min-width: 0; 
      box-sizing: border-box; 
    }
    #dashboardGrid .col-md-3 { 
      min-width: 100%; 
    }
    .grid-button {
      min-height: 90px;
      padding: var(--spacing-md);
    }
  }
  
  @media (max-width: 576px) {
    .page-header h2 { 
      font-size: var(--font-size-base); 
    }
    .grid-button {
      min-height: 80px;
      padding: var(--spacing-sm);
    }
    .grid-button i {
      font-size: var(--font-size-base);
    }
    .grid-button > div {
      font-size: var(--font-size-sm);
    }
    .count-circle {
      min-width: 28px;
      height: 28px;
      font-size: var(--font-size-xs);
    }
    .count-description {
      font-size: 0.7rem;
    }
  }
  
  @media (max-width: 480px) {
    .grid-button {
      min-height: 70px;
      padding: var(--spacing-xs);
    }
    .grid-button i {
      font-size: var(--font-size-sm);
      margin-bottom: var(--spacing-xs);
    }
    .grid-button > div {
      font-size: var(--font-size-xs);
    }
    .count-circle {
      min-width: 24px;
      height: 24px;
      font-size: 0.7rem;
    }
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

  <!-- Stream Selection Notice -->
  <?php if (isset($stream_warning)): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
      <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div class="flex-grow-1">
          <h6 class="alert-heading mb-1">No Stream Selected</h6>
          <p class="mb-2"><?= htmlspecialchars($stream_warning) ?></p>
          <div class="btn-group" role="group">
            <a href="streams.php" class="btn btn-sm btn-outline-warning">
              <i class="fas fa-stream me-1"></i>Select Stream
            </a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        </div>
      </div>
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
