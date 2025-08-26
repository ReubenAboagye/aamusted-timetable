<?php
// Connect to the database and fetch building data
include 'connect.php';

$sql = "SELECT * FROM building";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Buildings Management - TimeTable Generator</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <!-- Google Font: Open Sans -->
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
      background-color: var(--bg-color);
      margin: 0;
      padding-top: 70px;
      overflow: hidden;
      font-size: 14px;
    }
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
    <h2>Buildings Management</h2>
    <!-- Search & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="input-group" style="width: 300px;">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" id="searchBuildingInput" class="form-control" placeholder="Search for buildings...">
      </div>
      <div>
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">Import</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal" onclick="setModalAction('add')">Add Building</button>
      </div>
    </div>
    
    <!-- Buildings Table -->
    <div class="table-responsive">
      <table class="table table-striped table-custom" id="buildingTable">
        <thead>
          <tr>
            <th>Building Id</th>
            <th>Building Type</th>
            <th>Building Name</th>
            <th>Floor (Division)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
            include 'connect.php';
            if ($conn->connect_error) {
              die("Connection failed: " . $conn->connect_error);
            }
            $sql = "SELECT * FROM building";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['building_id']}</td>
                        <td>{$row['building_type']}</td>
                        <td>{$row['building_name']}</td>
                        <td>{$row['division']}</td>
                        <td>
                          <a href='delete_building.php?building_id={$row['building_id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this building?\")'>Delete</a>
                        </td>
                      </tr>";
              }
            } else {
              echo "<tr><td colspan='5' class='text-center'>No building found</td></tr>";
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
  
  <!-- Import Modal -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel">Import Buildings</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="import_buildings.php" method="POST" enctype="multipart/form-data">
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
          <h5 class="modal-title" id="dataEntryModalLabel">Enter Building Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="addbuildingform.php" method="POST" id="buildingForm">
            <div class="mb-3">
              <label for="building_id" class="form-label">Building Id</label>
              <input type="text" name="building_id" class="form-control" id="building_id" placeholder="Enter Building Id" required>
            </div>
            <div class="mb-3">
              <label for="building_type" class="form-label">Building Type</label>
              <select class="form-select" name="building_type" id="building_type" required>
                <option selected disabled>Select Building Type</option>
                <option value="Academic Building">LAB</option>
                <option value="Administration Block">LECTURER HALLS</option>
                <option value="Library">MIX</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="building_name" class="form-label">Building Name</label>
              <input type="text" name="building_name" class="form-control" id="building_name" placeholder="Enter Building Name" required>
            </div>
            <div class="mb-3">
              <label for="division" class="form-label">Floor (Division)</label>
              <select class="form-select" name="division" id="division" required>
                <option selected disabled>Select Floor (Division)</option>
                <option value="Floor 1">Floor 1</option>
                <option value="Floor 2">Floor 2</option>
                <option value="Floor 3">Floor 3</option>
                <option value="Floor 4">Floor 4</option>
                <option value="Floor 5">Floor 5</option>
                <option value="Floor 6">Floor 6</option>
                <option value="Floor 7">Floor 7</option>
              </select>
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
  
  <!-- Toast Notification -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="buildingToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header">
        <strong class="me-auto">Notification</strong>
        <small>Just now</small>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        Building has been added successfully!
      </div>
    </div>
  </div>
  
  <!-- Bootstrap Bundle with Popper JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
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
    
      // Search functionality for buildings table
      document.getElementById('searchBuildingInput').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#buildingTable tbody tr');
        rows.forEach(row => {
          const cells = row.querySelectorAll('td');
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
    
      // Back to Top Button with Progress Indicator
      const backToTopButton = document.getElementById("backToTop");
      const progressCircle = document.getElementById("progressCircle");
      const circumference = 2 * Math.PI * 20;
      progressCircle.style.strokeDasharray = circumference;
      progressCircle.style.strokeDashoffset = circumference;
      
      const mainContent = document.getElementById("mainContent");
      mainContent.addEventListener("scroll", function() {
        const scrollTop = mainContent.scrollTop;
        backToTopButton.style.display = (scrollTop > 100) ? "block" : "none";
        const scrollHeight = mainContent.scrollHeight - mainContent.clientHeight;
        const scrollPercentage = scrollTop / scrollHeight;
        const offset = circumference - (scrollPercentage * circumference);
        progressCircle.style.strokeDashoffset = offset;
      });
      
      backToTopButton.addEventListener("click", function() {
        mainContent.scrollTo({ top: 0, behavior: "smooth" });
      });
      
      // Function to set modal action (reset form for "add" action)
      window.setModalAction = function(action) {
        if (action === 'add') {
          document.getElementById('dataEntryModalLabel').textContent = 'Enter Building Details';
          document.getElementById('buildingForm').reset();
        }
      }
      
      // Debug: Log URL parameters
      const params = new URLSearchParams(window.location.search);
      const status = params.get('status');
      console.log("Status parameter:", status);
      
      // Check for success status in URL and show toast notification
      if (status === 'success') {
        var buildingToastEl = document.getElementById('buildingToast');
        if (buildingToastEl) {
          var buildingToast = new bootstrap.Toast(buildingToastEl);
          buildingToast.show();
          console.log("Toast has been shown.");
        } else {
          console.log("Toast element not found!");
        }
      }
    });
  </script>
</body>
</html>
