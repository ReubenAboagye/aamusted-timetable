<?php
// Connect to the database and fetch department data
include 'connect.php';

$sql = "SELECT * FROM departments";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Departments Management - University Timetable Generator</title>
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
      overflow: auto;
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
      z-index: 1040; /* Higher than footer, lower than navbar */
      overflow-y: auto; /* Scrollable if content is too long */
    }
    .sidebar.show {
      transform: translateX(0);
    }
    .nav-links {
      display: flex;
      flex-direction: column;
      gap: 5px;
      padding-bottom: 20px; /* Add bottom padding to prevent overlap with footer */
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
      min-height: calc(100vh - 70px);
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
      z-index: 1030; /* Lower than sidebar */
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
<?php $pageTitle = 'Departments Management'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <h2>Departments Management</h2>
    <!-- Search & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="input-group" style="width: 300px;">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" class="form-control" id="searchInput" placeholder="Search for departments...">
      </div>
      <div>
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">Import</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add</button>
      </div>
    </div>
    
    <!-- Departments Table -->
    <div class="table-container">
      <div class="table-header">
        <h4><i class="fas fa-building me-2"></i>Existing Departments</h4>
      </div>
      <div class="search-container">
        <input type="text" id="searchInput" class="search-input" placeholder="Search departments...">
      </div>
      <div class="table-responsive">
        <table class="table" id="departmentsTable">
        <thead>
          <tr>
            <th>Department ID</th>
            <th>Department Name</th>
            <th>Department Code</th>
            <th>Department Short Name</th>
            <th>Head of Department</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($result) {
              while ($row = $result->fetch_assoc()) {
                  $status = $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                  echo "<tr>
                          <td>{$row['id']}</td>
                          <td>{$row['name']}</td>
                          <td>{$row['code']}</td>
                          <td>{$row['short_name']}</td>
                          <td>{$row['head_of_department']}</td>
                          <td>{$status}</td>
                          <td>
                              <a href='delete_department.php?department_id={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this department?\")'>Delete</a>
                          </td>
                        </tr>";
              }
          } else {
              echo "<tr><td colspan='7' class='text-center'>No departments found</td></tr>";
          }
          $conn->close();
          ?>
        </tbody>
      </table>
        </div>
        <div class="mt-3">
  
        </div>
      </div>
    </div>
    
<?php include 'includes/footer.php'; ?>
  
  <!-- Import Modal (Excel Files Only) -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel">Import Departments</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="import_department.php" method="POST" enctype="multipart/form-data">
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
          <h5 class="modal-title" id="dataEntryModalLabel">Add New Department</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="department.php" method="POST">
            <div class="mb-3">
              <label for="departmentName" class="form-label">Department Name *</label>
              <input type="text" class="form-control" id="departmentName" name="departmentName" required>
            </div>
            <div class="mb-3">
              <label for="departmentCode" class="form-label">Department Code *</label>
              <input type="text" class="form-control" id="departmentCode" name="departmentCode" required maxlength="20">
              <small class="form-text text-muted">Unique code for the department (max 20 characters)</small>
            </div>
            <div class="mb-3">
              <label for="departmentShortName" class="form-label">Department Short Name</label>
              <input type="text" class="form-control" id="departmentShortName" name="departmentShortName" maxlength="10">
              <small class="form-text text-muted">Abbreviated name (max 10 characters)</small>
            </div>
            <div class="mb-3">
              <label for="headOfDepartment" class="form-label">Head of Department</label>
              <input type="text" class="form-control" id="headOfDepartment" name="headOfDepartment" maxlength="100">
            </div>
            <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                <label class="form-check-label" for="isActive">
                  Department is Active
                </label>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Department</button>
          </form>
        </div>
      </div>
    </div>
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
    
    // Search functionality for departments table
    document.getElementById('searchInput').addEventListener('keyup', function() {
      let searchValue = this.value.toLowerCase();
      let rows = document.querySelectorAll('#departmentsTable tbody tr');
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
    
    // Add form submission handler for the data entry modal
    document.querySelector('#dataEntryModal form').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Get form data
      const formData = new FormData(this);
      formData.append('action', 'add');
      
      // Handle checkbox value for isActive
      const isActiveCheckbox = document.getElementById('isActive');
      formData.set('isActive', isActiveCheckbox.checked ? '1' : '0');
      
      // Submit form data to adddepartmentform.php for processing
      fetch('adddepartmentform.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('dataEntryModal'));
        modal.hide();
        
        // Show success message and reload page
        alert('Department added successfully!');
        window.location.reload();
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error adding department. Please try again.');
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
  </script>
</body>
</html>
