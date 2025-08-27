<?php
// Query counts for each module
include 'connect.php';

// Department count
$dept_query = "SELECT COUNT(*) AS dept_count FROM departments WHERE is_active = 1";
$dept_result = $conn->query($dept_query);
if (!$dept_result) {
    die("Department query failed: " . $conn->error);
}
$dept_row = $dept_result->fetch_assoc();
$dept_count = $dept_row['dept_count'];



// Course count
$course_query = "SELECT COUNT(*) AS course_count FROM courses WHERE is_active = 1";
$course_result = $conn->query($course_query);
if (!$course_result) {
    die("Course query failed: " . $conn->error);
}
$course_row = $course_result->fetch_assoc();
$course_count = $course_row['course_count'];

// Lecturer count
$lect_query = "SELECT COUNT(*) AS lect_count FROM lecturers WHERE is_active = 1";
$lect_result = $conn->query($lect_query);
if (!$lect_result) {
    die("Lecturer query failed: " . $conn->error);
}
$lect_row = $lect_result->fetch_assoc();
$lect_count = $lect_row['lect_count'];

// Room count
$room_query = "SELECT COUNT(*) AS room_count FROM rooms WHERE is_active = 1";
$room_result = $conn->query($room_query);
if (!$room_result) {
    die("Room query failed: " . $conn->error);
}
$room_row = $room_result->fetch_assoc();
$room_count = $room_row['room_count'];




// Timetable entries count
$timetable_query = "SELECT COUNT(*) AS timetable_count FROM timetable";
$timetable_result = $conn->query($timetable_query);
if (!$timetable_result) {
    die("Timetable query failed: " . $conn->error);
}
$timetable_row = $timetable_result->fetch_assoc();
$timetable_count = $timetable_row['timetable_count'];



$conn->close();
?>
<?php $pageTitle = 'Dashboard'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <h2>Dashboard</h2>
    <!-- Dashboard Search Bar -->
    <div class="dashboard-search">
      <div class="input-group" style="width: 300px;">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" id="dashboardSearchInput" class="form-control" placeholder="Search modules...">
      </div>
    </div>
    <!-- Grid Buttons -->
    <div class="row g-3 mb-3" id="dashboardGrid">
      <!-- Main Dashboard Cards -->
      <div class="col-md-3">
        <?php if ($timetable_count > 0): ?>
          <a href="view_timetable.php" class="grid-button">
            <i class="fas fa-table"></i>
            <div>View Timetable</div>
            <span class="count-circle"><?php echo $timetable_count; ?></span>
          </a>
        <?php else: ?>
          <a href="timetable.php" class="grid-button">
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
          <span class="count-circle"><?php echo $dept_count; ?></span>
        </a>
      </div>
      
      <div class="col-md-3">
        <a href="lecturers.php" class="grid-button">
          <i class="fas fa-chalkboard-teacher"></i>
          <div>Lecturers</div>
          <span class="count-circle"><?php echo $lect_count; ?></span>
        </a>
      </div>
      
      <div class="col-md-3">
        <a href="rooms.php" class="grid-button">
          <i class="fas fa-door-open"></i>
          <div>Rooms</div>
          <span class="count-circle"><?php echo $room_count; ?></span>
        </a>
      </div>
    </div>
    
    <!-- Dashboard Overview -->
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Dashboard Overview</h5>
        <p class="card-text">Welcome to the University Timetable Generator! This dashboard provides quick access to the core modules:</p>
        <ul class="mb-0">
          <li><strong>Timetable:</strong> <?php echo ($timetable_count > 0) ? 'View existing timetables' : 'Generate new timetables'; ?></li>
          <li><strong>Departments:</strong> Manage academic departments and structure</li>
          <li><strong>Lecturers:</strong> Manage teaching staff and assignments</li>
          <li><strong>Rooms:</strong> Manage physical spaces and facilities</li>
        </ul>
        <p class="mt-2 mb-0"><small class="text-muted">Access all 14 modules through the sidebar navigation for comprehensive system management.</small></p>
      </div>
    </div>
  </div>
  
<?php include 'includes/footer.php'; ?>
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
    
    // Toggle sidebar visibility
    document.getElementById('sidebarToggle').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      const footer = document.getElementById('footer');
      sidebar.classList.toggle('show');
      if (sidebar.classList.contains('show')) {
        mainContent.classList.add('shift');
        footer.classList.add('shift');
      } else {
        mainContent.classList.remove('shift');
        footer.classList.remove('shift');
      }
    });
    
    // Dashboard grid search functionality
    document.getElementById('dashboardSearchInput').addEventListener('keyup', function() {
      const searchValue = this.value.toLowerCase();
      const gridButtons = document.querySelectorAll('#dashboardGrid .grid-button');
      gridButtons.forEach(button => {
        const text = button.textContent.toLowerCase();
        if (text.includes(searchValue)) {
          button.parentElement.style.display = '';
        } else {
          button.parentElement.style.display = 'none';
        }
      });
    });
    
    // Back to Top Button Setup
    const backToTopButton = document.getElementById("backToTop");
    const progressCircle = document.getElementById("progressCircle");
    const circumference = 2 * Math.PI * 20;
    progressCircle.style.strokeDasharray = circumference;
    progressCircle.style.strokeDashoffset = circumference;
    
    window.addEventListener("scroll", function() {
      const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
      if (scrollTop > 100) {
        backToTopButton.style.display = "block";
      } else {
        backToTopButton.style.display = "none";
      }
      
      const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
      const scrollPercentage = scrollTop / scrollHeight;
      const offset = circumference - (scrollPercentage * circumference);
      progressCircle.style.strokeDashoffset = offset;
    });
    
    backToTopButton.addEventListener("click", function() {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
    
    // Modal action setup (for resetting form if needed)
    function setModalAction(action) {
      const modalTitle = document.getElementById('dataEntryModalLabel');
      if (action === 'add') {
        modalTitle.textContent = 'Enter Course Details';
      } else if (action === 'edit') {
        modalTitle.textContent = 'Edit Course Details';
      }
    }
  </script>
</body>
</html>
