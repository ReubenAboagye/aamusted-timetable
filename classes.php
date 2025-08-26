<?php

// Determine the current page filename
$currentPage = basename($_SERVER['PHP_SELF']);

// Connect to the database
include 'connect.php';

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchQuery = "";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $searchQuery = " WHERE class.class_name LIKE '%$search%' 
                     OR class.department LIKE '%$search%'
                     OR class.level LIKE '%$search%'
                     OR course.course_name LIKE '%$search%'";
}

// Updated query with renamed primary key and search filter
$sql = "SELECT 
          class.class_id, 
          class.class_name, 
          class.department, 
          class.level, 
          class.class_session, 
          class.anydis, 
          class.capacity,
          GROUP_CONCAT(DISTINCT CASE WHEN course.semester = '1' THEN course.course_name END SEPARATOR ', ') AS sem1_courses,
          GROUP_CONCAT(DISTINCT CASE WHEN course.semester = '2' THEN course.course_name END SEPARATOR ', ') AS sem2_courses
        FROM class
        LEFT JOIN class_course ON class.class_id = class_course.class_id
        LEFT JOIN course ON class_course.course_id = course.course_id
        $searchQuery
        GROUP BY class.class_id";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Classes Management - TimeTable Generator</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <style>
    :root {
      --primary-color: #800020;   /* AAMUSTED maroon */
      --hover-color: #600010;       /* Darker maroon */
      --accent-color: #FFD700;      /* Accent goldenrod */
      --bg-color: #ffffff;          /* White background */
      --sidebar-bg: #f8f8f8;         /* Light gray sidebar */
      --footer-bg: #800020;         /* Footer same as primary */
    }
    /* Global Styles */
    body {
      font-family: 'Open Sans', sans-serif;
      background-color: var(--bg-color);
      margin: 0;
      padding-top: 70px; /* For fixed header */
      overflow: hidden;
      font-size: 14px;
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
      height: calc(100vh - 70px);
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
      display: block;
      width: 100%;
      padding: 5px 10px;
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      transition: background-color 0.3s, color 0.3s;
    }
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
    /* Table Styles */
    .table-custom {
      background-color: var(--bg-color);
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .table-custom th {
      background-color: var(--primary-color);
      color: var(--accent-color);
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
    /* Back to Top Button */
    #backToTop {
      position: fixed;
      bottom: 30px;
      right: 30px;
      z-index: 9999;
      display: none;
      background: rgba(128, 0, 32, 0.7);
      border: none;
      outline: none;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      cursor: pointer;
      transition: background 0.3s ease, transform 0.3s ease;
      padding: 0;
      overflow: hidden;
    }
    #backToTop svg {
      display: block;
      width: 100%;
      height: 100%;
    }
    #backToTop .arrow-icon {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      color: #FFD700;
      font-size: 1.5rem;
      pointer-events: none;
    }
    #backToTop:hover {
      background: rgba(96, 0, 16, 0.9);
      transform: scale(1.1);
    }
    /* Responsive Adjustments */
    @media (max-width: 768px) {
      .sidebar { width: 200px; }
      .main-content.shift { margin-left: 200px; }
      .footer.shift { left: 200px; }
    }
    @media (max-width: 576px) {
      .sidebar { width: 250px; }
      .main-content.shift { margin-left: 0; }
      .footer.shift { left: 0; }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <nav class="navbar navbar-dark">
    <div class="container-fluid">
      <button id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <a class="navbar-brand text-white" href="#">
        <img src="images/aamustedLog.png" alt="AAMUSTED Logo">TimeTable Generator
      </a>
      <div class="ms-auto text-white" id="currentTime">12:00:00 PM</div>
    </div>
  </nav>
  
  <!-- Sidebar -->
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
    <h2>Classes Management</h2>
    <!-- Search & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="input-group" style="width: 300px;">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" id="searchInput" class="form-control" placeholder="Search for classes...">
      </div>
      <div>
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">Import</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add Class</button>
      </div>
    </div>
    
    <!-- Classes Table -->
    <div class="table-responsive">
      <table class="table table-striped table-custom" id="classesTable">
        <thead>
          <tr>
            <th>Class Name</th>
            <th>Department</th>
            <th>Level</th>
            <th>Session</th>
            <th>Anydis</th>
            <th>Capacity</th>
            <th>Semester 1 Courses</th>
            <th>Semester 2 Courses</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
            if ($result) {
              while ($row = $result->fetch_assoc()) { ?>
                  <tr>
                      <td><?php echo $row['class_name']; ?></td>
                      <td><?php echo $row['department']; ?></td>
                      <td><?php echo $row['level']; ?></td>
                      <td><?php echo $row['class_session']; ?></td>
                      <td><?php echo $row['anydis']; ?></td>
                      <td><?php echo $row['capacity']; ?></td>
                      <td><?php echo $row['sem1_courses'] ?: 'No Courses Assigned'; ?></td>
                      <td><?php echo $row['sem2_courses'] ?: 'No Courses Assigned'; ?></td>
                      <td>
                          <a href="delete_class.php?class_name=<?php echo $row['class_name']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this class?');">Delete</a>
                      </td>
                  </tr>
          <?php }
            }
            $conn->close();
          ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <!-- Footer -->
  <div class="footer" id="footer">
    &copy; 2025 TimeTable Generator
  </div>
  
  <!-- Back to Top Button with Progress Indicator -->
  <button id="backToTop">
    <svg width="50" height="50" viewBox="0 0 50 50">
      <circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"/>
    </svg>
    <i class="fas fa-arrow-up arrow-icon"></i>
  </button>
  
  <!-- Import Modal (Accept Only Excel Files) -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel">Import Classes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="import_classes.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
              <label for="file" class="form-label">Choose Excel File</label>
              <input type="file" class="form-control" id="file" name="file" required accept=".xls, .xlsx">
            </div>
            <button type="submit" class="btn btn-primary">Upload</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Data Entry Modal -->
  <div class="modal fade" id="dataEntryModal" tabindex="-1" aria-labelledby="dataEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="dataEntryModalLabel">Enter Class Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="addclassesform.php" method="POST">
            <div class="mb-3">
              <label for="class_name" class="form-label">Class Name</label>
              <input type="text" class="form-control" id="class_name" name="class_name" placeholder="Enter Class Name" required>
            </div>
            <div class="mb-3">
              <label for="department" class="form-label">Department</label>
              <select class="form-select" id="department" name="department" required>
                <option selected disabled>Select Department</option>
                <?php
                  include 'connect.php';
                  $dept_query = "SELECT department_name FROM department";
                  $dept_result = $conn->query($dept_query);
                  if ($dept_result->num_rows > 0) {
                      while ($dept_row = $dept_result->fetch_assoc()) {
                          echo "<option value='{$dept_row['department_name']}'>{$dept_row['department_name']}</option>";
                      }
                  } else {
                      echo "<option disabled>No departments available</option>";
                  }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="level" class="form-label">Level</label>
              <select class="form-select" id="level" name="level" required>
                <option selected disabled>Select Level</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="300">300</option>
                <option value="400">400</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="class_session" class="form-label">Class Session</label>
              <select class="form-select" id="class_session" name="class_session" required>
                <option selected disabled>Select Class Session</option>
                <option value="Regular">Regular</option>
                <option value="Evening">Evening</option>
                <option value="Weekend">Weekend</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="anydis" class="form-label">Any Disabled?</label>
              <select class="form-select" id="anydis" name="anydis" required>
                <option selected disabled>Select If Any Disabled</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="capacity" class="form-label">Capacity</label>
              <input type="text" class="form-control" id="capacity" name="capacity" placeholder="Enter Capacity" required>
            </div>
            <!-- New fields for selecting Semester 1 and Semester 2 courses -->
            <div class="mb-3">
              <label for="sem1_courses" class="form-label">Select Semester 1 Courses</label>
              <div class="dropdown">
                <button class="btn btn-light w-100 dropdown-toggle" type="button" id="dropdownMenuSem1" data-bs-toggle="dropdown" aria-expanded="false">
                  Select Semester 1 Courses
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="dropdownMenuSem1" style="max-height: 200px; overflow-y: auto;">
                  <?php
                    include 'connect.php';
                    $querySem1 = "SELECT * FROM course WHERE semester = '1'";
                    $resultSem1 = mysqli_query($conn, $querySem1);
                    while ($rowSem1 = mysqli_fetch_assoc($resultSem1)) {
                        echo "<li><input type='checkbox' name='sem1_courses[]' value='{$rowSem1['course_id']}'> {$rowSem1['course_name']}</li>";
                    }
                  ?>
                </ul>
              </div>
            </div>
            <div class="mb-3">
              <label for="sem2_courses" class="form-label">Select Semester 2 Courses</label>
              <div class="dropdown">
                <button class="btn btn-light w-100 dropdown-toggle" type="button" id="dropdownMenuSem2" data-bs-toggle="dropdown" aria-expanded="false">
                  Select Semester 2 Courses
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="dropdownMenuSem2" style="max-height: 200px; overflow-y: auto;">
                  <?php
                    include 'connect.php';
                    $querySem2 = "SELECT * FROM course WHERE semester = '2'";
                    $resultSem2 = mysqli_query($conn, $querySem2);
                    while ($rowSem2 = mysqli_fetch_assoc($resultSem2)) {
                        echo "<li><input type='checkbox' name='sem2_courses[]' value='{$rowSem2['course_id']}'> {$rowSem2['course_name']}</li>";
                    }
                  ?>
                </ul>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Bootstrap Bundle with Popper JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
  
  <!-- Main Functionalities -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
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
    
      // Search functionality for classes table
      document.getElementById('searchInput').addEventListener('keyup', function() {
        let searchValue = this.value.toLowerCase();
        let rows = document.querySelectorAll('#classesTable tbody tr');
        rows.forEach(row => {
          let cells = row.querySelectorAll('td');
          let matchFound = false;
          for (let i = 0; i < cells.length - 1; i++) {
            if (cells[i].textContent.toLowerCase().includes(searchValue)) {
              matchFound = true;
              break;
            }
          }
          row.style.display = matchFound ? '' : 'none';
        });
      });
    
      // Back to Top Button with Progress Indicator using mainContent scroll
      const backToTopButton = document.getElementById("backToTop");
      const progressCircle = document.getElementById("progressCircle");
      const circumference = 2 * Math.PI * 20;
      progressCircle.style.strokeDasharray = circumference;
      progressCircle.style.strokeDashoffset = circumference;
      
      const mainContent = document.getElementById("mainContent");
      mainContent.addEventListener("scroll", function() {
        const scrollTop = mainContent.scrollTop;
        if (scrollTop > 100) {
          backToTopButton.style.display = "block";
        } else {
          backToTopButton.style.display = "none";
        }
        
        const scrollHeight = mainContent.scrollHeight - mainContent.clientHeight;
        const scrollPercentage = scrollTop / scrollHeight;
        const offset = circumference - (scrollPercentage * circumference);
        progressCircle.style.strokeDashoffset = offset;
      });
      
      backToTopButton.addEventListener("click", function() {
        mainContent.scrollTo({ top: 0, behavior: "smooth" });
      });
    });
  </script>
  
  <!-- Additional Back to Top Button Styling (Optional) -->
  <style>
    /* Optional additional styling */
  </style>
  
</body>
</html>
