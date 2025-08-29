<?php 
$pageTitle = 'Dashboard'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 
?>

<style>
  /* Dashboard Cards */
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
</style>

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
      <a href="view_timetable.php" class="grid-button">
        <i class="fas fa-table"></i>
        <div>View Timetable</div>
        <span class="count-circle">12</span>
      </a>
    </div>
    
    <div class="col-md-3">
      <a href="adddepartmentform.php" class="grid-button">
        <i class="fas fa-building"></i>
        <div>Departments</div>
        <span class="count-circle">5</span>
      </a>
    </div>
    
    <div class="col-md-3">
      <a href="lecturers.php" class="grid-button">
        <i class="fas fa-chalkboard-teacher"></i>
        <div>Lecturers</div>
        <span class="count-circle">15</span>
      </a>
    </div>
    
    <div class="col-md-3">
      <a href="rooms.php" class="grid-button">
        <i class="fas fa-door-open"></i>
        <div>Rooms</div>
        <span class="count-circle">12</span>
      </a>
    </div>
  </div>
  
  <!-- Dashboard Overview -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Dashboard Overview</h5>
      <p class="card-text">Welcome to the University Timetable Generator! This dashboard provides quick access to the core modules:</p>
      <ul class="mb-0">
        <li><strong>Timetable:</strong> View existing timetables</li>
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
    if (document.getElementById('currentTime')) {
      document.getElementById('currentTime').textContent = timeString;
    }
  }
  setInterval(updateTime, 1000);
  updateTime();
  
  // Toggle sidebar visibility
  if (document.getElementById('sidebarToggle')) {
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
  }
  
  // Dashboard grid search functionality
  if (document.getElementById('dashboardSearchInput')) {
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
  }
</script>
