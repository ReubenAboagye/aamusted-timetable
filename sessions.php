<?php
// Connect to the database and fetch sessions data
include 'connect.php';

$sql = "SELECT * FROM sessions";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sessions Management - University Timetable Generator</title>
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
<?php $pageTitle = 'Sessions Management'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <h2>Sessions Management</h2>
    <!-- Search & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="input-group" style="width: 300px;">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" class="form-control" id="searchInput" placeholder="Search for sessions...">
      </div>
      <div>
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">Import</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add</button>
      </div>
    </div>
    
    <!-- Sessions Table -->
    <div class="table-container">
      <div class="table-header">
        <h4><i class="fas fa-calendar-alt me-2"></i>Existing Sessions</h4>
      </div>
      <div class="search-container">
        <input type="text" id="searchInput" class="search-input" placeholder="Search sessions...">
      </div>
      <div class="table-responsive">
        <table class="table" id="sessionsTable">
        <thead>
          <tr>
            <th>Session ID</th>
            <th>Academic Year</th>
            <th>Semester</th>
            <th>Semester Name</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($result) {
              while ($row = $result->fetch_assoc()) {
                  $status = $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                  echo "<tr>";
                  echo "<td>{$row['id']}</td>";
                  echo "<td>{$row['academic_year']}</td>";
                  echo "<td>Semester {$row['semester_number']}</td>";
                  echo "<td>{$row['semester_name']}</td>";
                  echo "<td>" . ($row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : 'Not set') . "</td>";
                  echo "<td>" . ($row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : 'Not set') . "</td>";
                  echo "<td>{$status}</td>";
                  echo "<td>";
                  echo "<button type='button' class='btn btn-secondary btn-sm me-1 edit-session-btn' data-id='" . $row['id'] . "' data-academic-year='" . htmlspecialchars($row['academic_year'], ENT_QUOTES) . "' data-semester='" . htmlspecialchars($row['semester_number'], ENT_QUOTES) . "' data-semester-name='" . htmlspecialchars($row['semester_name'], ENT_QUOTES) . "' data-start-date='" . $row['start_date'] . "' data-end-date='" . $row['end_date'] . "'>Edit</button>";
                  echo "<a href='delete_session.php?session_id={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this session?\")'>Delete</a>";
                  echo "</td>";
                  echo "</tr>";
              }
          } else {
              echo "<tr><td colspan='8' class='text-center'>No sessions found</td></tr>";
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
  
  <!-- Footer -->
  <div class="footer" id="footer">
    &copy; 2025 University Timetable Generator
  </div>
  
  <!-- Import Modal (Excel Files Only) -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel">Import Sessions</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="import_session.php" method="POST" enctype="multipart/form-data">
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
    <div class="modal-dialog modal-lg">
      <div class="modal-content p-2">
        <div class="modal-header py-1">
          <h5 class="modal-title fs-6" id="dataEntryModalLabel">Add New Session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body py-2">
          <form action="addsessionform.php" method="POST" class="row g-2">
            <div class="col-6">
              <label for="academicYear" class="form-label">Academic Year</label>
              <input type="text" class="form-control form-control-sm" id="academicYear" name="academicYear" placeholder="e.g., 2024/2025" required>
            </div>
            <div class="col-6">
              <label for="semester" class="form-label">Semester</label>
              <select class="form-select form-select-sm" id="semester" name="semester" required>
                <option value="">Select Semester</option>
                <option value="1">First Semester</option>
                <option value="2">Second Semester</option>
                <option value="3">Third Semester</option>
              </select>
            </div>
            <div class="col-12">
              <label for="semester_name" class="form-label">Semester Name</label>
              <input type="text" class="form-control form-control-sm" id="semester_name" name="semester_name" placeholder="e.g., First Semester 2024/2025" required>
            </div>
            <div class="col-6">
              <label for="startDate" class="form-label">Start Date</label>
              <input type="date" class="form-control form-control-sm" id="startDate" name="startDate">
            </div>
            <div class="col-6">
              <label for="endDate" class="form-label">End Date</label>
              <input type="date" class="form-control form-control-sm" id="endDate" name="endDate">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                <label class="form-check-label" for="isActive">
                  Session is Active
                </label>
              </div>
            </div>
            <div class="col-12 text-end">
              <button type="submit" class="btn btn-primary btn-sm">Add Session</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Edit Data Modal -->
  <div class="modal fade" id="editDataModal" tabindex="-1" aria-labelledby="editDataModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content p-2">
        <div class="modal-header py-1">
          <h5 class="modal-title fs-6" id="editDataModalLabel">Edit Session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body py-2">
          <form id="editSessionForm" action="update_session.php" method="POST" class="row g-2">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_session_id" name="session_id">
            <div class="col-6">
              <label for="edit_academicYear" class="form-label">Academic Year</label>
              <input type="text" class="form-control form-control-sm" id="edit_academicYear" name="academicYear" placeholder="e.g., 2024/2025" required>
            </div>
            <div class="col-6">
              <label for="edit_semester" class="form-label">Semester</label>
              <select class="form-select form-select-sm" id="edit_semester" name="semester" required>
                <option value="">Select Semester</option>
                <option value="1">First Semester</option>
                <option value="2">Second Semester</option>
                <option value="3">Third Semester</option>
              </select>
            </div>
            <div class="col-12">
              <label for="edit_semester_name" class="form-label">Semester Name</label>
              <input type="text" class="form-control form-control-sm" id="edit_semester_name" name="semester_name" placeholder="e.g., First Semester 2024/2025" required>
            </div>
            <div class="col-6">
              <label for="edit_startDate" class="form-label">Start Date</label>
              <input type="date" class="form-control form-control-sm" id="edit_startDate" name="startDate">
            </div>
            <div class="col-6">
              <label for="edit_endDate" class="form-label">End Date</label>
              <input type="date" class="form-control form-control-sm" id="edit_endDate" name="endDate">
            </div>
            <div class="col-12 text-end">
              <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
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
    
    // Search functionality for sessions table
    document.getElementById('searchInput').addEventListener('keyup', function() {
      let searchValue = this.value.toLowerCase();
      let rows = document.querySelectorAll('#sessionsTable tbody tr');
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
      const form = this;
      const formData = new FormData(form);

      // Submit form data to addsessionform.php for processing
      fetch('addsessionform.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Close modal
          const modal = bootstrap.Modal.getInstance(document.getElementById('dataEntryModal'));
          modal.hide();

          // Show success message and reload page
          alert(data.message);
          window.location.reload();
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error adding session. Please try again.');
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

    // Edit button handler: populate edit modal and show
    document.addEventListener('click', function(e) {
      if (e.target && e.target.classList.contains('edit-session-btn')) {
        const btn = e.target;
        const id = btn.getAttribute('data-id');
        const academicYear = btn.getAttribute('data-academic-year');
        const semester = btn.getAttribute('data-semester');
        const semesterName = btn.getAttribute('data-semester-name');
        const startDate = btn.getAttribute('data-start-date');
        const endDate = btn.getAttribute('data-end-date');

        document.getElementById('edit_session_id').value = id;
        document.getElementById('edit_academicYear').value = academicYear;
        document.getElementById('edit_semester').value = semester;
        document.getElementById('edit_semester_name').value = semesterName;
        document.getElementById('edit_startDate').value = startDate;
        document.getElementById('edit_endDate').value = endDate;

        const modalEl = document.getElementById('editDataModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
      }
    });
  </script>
</body>
</html>
