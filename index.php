<?php
// Query counts for each module
include 'connect.php';

// Department count
$dept_query = "SELECT COUNT(*) AS dept_count FROM department";
$dept_result = $conn->query($dept_query);
if (!$dept_result) {
    die("Department query failed: " . $conn->error);
}
$dept_row = $dept_result->fetch_assoc();
$dept_count = $dept_row['dept_count'];

// Lecturer count
$lect_query = "SELECT COUNT(*) AS lect_count FROM lecturer";
$lect_result = $conn->query($lect_query);
if (!$lect_result) {
    die("Lecturer query failed: " . $conn->error);
}
$lect_row = $lect_result->fetch_assoc();
$lect_count = $lect_row['lect_count'];

// Course count
$course_query = "SELECT COUNT(*) AS course_count FROM course";
$course_result = $conn->query($course_query);
if (!$course_result) {
    die("Course query failed: " . $conn->error);
}
$course_row = $course_result->fetch_assoc();
$course_count = $course_row['course_count'];

// Building count
$building_query = "SELECT COUNT(*) AS building_count FROM building";
$building_result = $conn->query($building_query);
if (!$building_result) {
    die("Building query failed: " . $conn->error);
}
$building_row = $building_result->fetch_assoc();
$building_count = $building_row['building_count'];

// Rooms count
$room_query = "SELECT COUNT(*) AS room_count FROM room";
$room_result = $conn->query($room_query);
if (!$room_result) {
    die("Rooms query failed: " . $conn->error);
}
$room_row = $room_result->fetch_assoc();
$room_count = $room_row['room_count'];

// Class count
$class_query = "SELECT COUNT(*) AS class_count FROM class";
$class_result = $conn->query($class_query);
if (!$class_result) {
    die("Class query failed: " . $conn->error);
}
$class_row = $class_result->fetch_assoc();
$class_count = $class_row['class_count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TimeTable Generator Dashboard</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <!-- Google Font: Open Sans -->
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    :root {
      --primary-color: #800020;   /* AAMUSTED maroon */
      --hover-color: #600010;     /* Darker maroon */
      --accent-color: #FFD700;    /* Accent goldenrod */
      --bg-color: #ffffff;        /* White background */
      --sidebar-bg: #f8f8f8;      /* Light gray sidebar */
      --footer-bg: #800020;       /* Footer same as primary */
    }
    /* Global Styles */
    body {
      font-family: 'Open Sans', sans-serif;
      background-color: var(--bg-color);
      margin: 0;
      padding-top: 70px; /* For fixed header */
      overflow: hidden;
      font-size: 14px; /* <--- Added this line to match smaller font style */
    }
    /* Header */
    .navbar {
      background-color: var(--primary-color);
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
    }
    .navbar-brand img {
      height: 40px;
      margin-right: 10px;
    }
    #sidebarToggle {
      border: none;
      background: transparent;
      color: #fff;
      font-size: 1.5rem;
      margin-right: 10px;
    }
    /* Sidebar */
    .sidebar {
      background-color: var(--sidebar-bg);
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
      align-items: flex-start;
      gap: 5px;
    }
    .nav-links a {
      display: block;
      width: 100%;
      padding: 5px 10px;  /* Reduced padding */
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;    /* Reduced font size */
      transition: background-color 0.3s, color 0.3s;
    }
    /* Separator for sidebar links */
    .nav-links a:not(:last-child) {
      border-bottom: 1px solid #ccc;
      margin-bottom: 5px;
      padding-bottom: 5px;
    }
    .nav-links a:hover,
    .nav-links a.active {
      background-color: var(--primary-color);
      color: #fff;
      border-radius: 4px;
    }
    /* Main Content */
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
    /* Dashboard Search Bar */
    .dashboard-search {
      margin-bottom: 20px;
    }
    .dashboard-search .input-group-text {
      font-size: 0.9rem;
      padding: 6px 10px;
    }
    /* Grid Buttons */
    .grid-button {
      background-color: var(--primary-color);
      color: #fff;
      padding: 15px;
      border-radius: 8px;
      text-align: center;
      transition: background-color 0.3s, transform 0.3s;
      text-decoration: none;
      display: block;
      font-size: 0.9rem;
    }
    .grid-button:hover {
      background-color: var(--accent-color);
      transform: scale(1.05);
      color: var(--primary-color);
    }
    .grid-button i {
      font-size: 1.5rem;
    }
    /* Count Circle on Buttons */
    .count-circle {
      background-color: var(--hover-color);
      color: #fff;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      margin-top: 5px;
    }
    /* Footer */
    .footer {
      background-color: var(--footer-bg);
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
    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .sidebar {
        width: 200px;
      }
      .main-content.shift {
        margin-left: 200px;
      }
      .footer.shift {
        left: 200px;
      }
    }
    @media (max-width: 576px) {
      .sidebar {
        width: 250px;
      }
      .main-content.shift {
        margin-left: 0;
      }
      .footer.shift {
        left: 0;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <nav class="navbar navbar-dark">
    <div class="container-fluid">
      <!-- Toggle Button -->
      <button id="sidebarToggle">
        <i class="fas fa-bars"></i>
      </button>
      <a class="navbar-brand text-white" href="#">
        <img src="images/aamustedLog.png" alt="AAMUSTED Logo">TimeTable Generator
      </a>
      <div class="ms-auto text-white" id="currentTime">12:00:00 PM</div>
    </div>
  </nav>
 
  <!-- Sidebar (Updated with View Timetable Link) -->
  <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
  <div class="sidebar" id="sidebar">
    <div class="nav-links">
      <a href="index.php" class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
      <a href="timetable.php" class="<?= ($currentPage == 'timetable.php') ? 'active' : '' ?>"><i class="fas fa-calendar-alt me-2"></i>Generate Timetable</a>
      <a href="view_timetable.php" class="<?= ($currentPage == 'view_timetable.php') ? 'active' : '' ?>"><i class="fas fa-table me-2"></i>View Timetable</a>
      <a href="department.php" class="<?= ($currentPage == 'department.php') ? 'active' : '' ?>"><i class="fas fa-building me-2"></i>Department</a>
      <a href="lecturer.php" class="<?= ($currentPage == 'lecturer.php') ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers</a>
      <a href="rooms.php" class="<?= ($currentPage == 'rooms.php') ? 'active' : '' ?>"><i class="fas fa-door-open me-2"></i>Rooms</a>
      <a href="courses.php" class="<?= ($currentPage == 'courses.php') ? 'active' : '' ?>"><i class="fas fa-book me-2"></i>Course</a>
      <a href="classes.php" class="<?= ($currentPage == 'classes.php') ? 'active' : '' ?>"><i class="fas fa-users me-2"></i>Classes</a>
      <a href="buildings.php" class="<?= ($currentPage == 'buildings.php') ? 'active' : '' ?>"><i class="fas fa-city me-2"></i>Buildings</a>
    </div>
  </div>
  
  <!-- Main Content -->
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
      <div class="col-md-4">
        <a href="department.php" class="grid-button">
          <i class="fas fa-building"></i>
          <div>Department</div>
          <span class="count-circle"><?php echo $dept_count; ?></span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="lecturer.php" class="grid-button">
          <i class="fas fa-chalkboard-teacher"></i>
          <div>Lecturers</div>
          <span class="count-circle"><?php echo $lect_count; ?></span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="courses.php" class="grid-button">
          <i class="fas fa-book"></i>
          <div>Course</div>
          <span class="count-circle"><?php echo $course_count; ?></span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="buildings.php" class="grid-button">
          <i class="fas fa-city"></i>
          <div>Buildings</div>
          <span class="count-circle"><?php echo $building_count; ?></span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="rooms.php" class="grid-button">
          <i class="fas fa-door-open"></i>
          <div>Rooms</div>
          <span class="count-circle"><?php echo $room_count; ?></span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="classes.php" class="grid-button">
          <i class="fas fa-users"></i>
          <div>Classes</div>
          <span class="count-circle"><?php echo $class_count; ?></span>
        </a>
      </div>
    </div>
    
    <!-- Dashboard Overview (Optional) -->
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Dashboard Overview</h5>
        <p class="card-text">Welcome to the TimeTable Generator. Use the grid above to navigate through the different modules.</p>
      </div>
    </div>
  </div>
  
  <!-- Footer -->
  <div class="footer" id="footer">
    &copy; 2025 TimeTable Generator
  </div>
  
  <!-- Back to Top Button -->
  <button id="backToTop">
    <svg width="50" height="50" viewBox="0 0 50 50">
      <circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"/>
    </svg>
    <i class="fas fa-arrow-up arrow-icon"></i>
  </button>
  
  <!-- Bootstrap Bundle with Popper -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
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
