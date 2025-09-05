<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<div class="sidebar" id="sidebar">
  <div class="nav-links">
    <!-- Dashboard -->
    <div class="nav-section">
      <a href="index.php" class="nav-link <?= ($currentPage == 'index.php') ? 'active' : '' ?>">
        <i class="fas fa-home me-2"></i>Dashboard
      </a>
    </div>

    <!-- Data Management Section -->
    <div class="nav-section">

      <div class="dropdown-header" data-bs-target="#dataManagementCollapse" aria-expanded="false">
        <i class="fas fa-database me-2"></i>Data Management
        <i class="fas fa-chevron-down ms-auto"></i>
      </div>
      <div class="collapse" id="dataManagementCollapse">
        <div class="dropdown-menu-items">
          <a href="department.php" class="nav-link <?= ($currentPage == 'department.php') ? 'active' : '' ?>">
            <i class="fas fa-building me-2"></i>Department
          </a>
          <a href="programs.php" class="nav-link <?= ($currentPage == 'programs.php') ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap me-2"></i>Programs
          </a>
          <a href="lecturers.php" class="nav-link <?= ($currentPage == 'lecturers.php') ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher me-2"></i>Lecturers
          </a>
          <a href="courses.php" class="nav-link <?= ($currentPage == 'courses.php') ? 'active' : '' ?>">
            <i class="fas fa-book me-2"></i>Courses
          </a>
          <a href="levels.php" class="nav-link <?= ($currentPage == 'levels.php') ? 'active' : '' ?>">
            <i class="fas fa-layer-group me-2"></i>Levels
          </a>
          <a href="classes.php" class="nav-link <?= ($currentPage == 'classes.php') ? 'active' : '' ?>">
            <i class="fas fa-users me-2"></i>Classes
          </a>
          <a href="rooms.php" class="nav-link <?= ($currentPage == 'rooms.php') ? 'active' : '' ?>">
            <i class="fas fa-door-open me-2"></i>Rooms
          </a>
        </div>
      </div>
    </div>

    <!-- Timetable Management Section -->
    <div class="nav-section">

      <div class="dropdown-header" data-bs-target="#timetableManagementCollapse" aria-expanded="false">
        <i class="fas fa-calendar-alt me-2"></i>Timetable Management
        <i class="fas fa-chevron-down ms-auto"></i>
      </div>
              <div class="collapse" id="timetableManagementCollapse">
          <div class="dropdown-menu-items">
                         <a href="generate_timetable.php" class="nav-link <?= ($currentPage == 'generate_timetable.php') ? 'active' : '' ?>">
               <i class="fas fa-calendar-plus me-2"></i>Generate Lecture Timetable
             </a>
                         <a href="generate_exams.php" class="nav-link <?= ($currentPage == 'generate_exams.php') ? 'active' : '' ?>">
               <i class="fas fa-file-alt me-2"></i>Generate Exams Timetable
             </a>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                         <a href="#jobsModal" class="nav-link" data-bs-toggle="modal" data-bs-target="#jobsModal">
               <i class="fas fa-tasks me-2"></i>Jobs
             </a>
            <?php endif; ?>
             <a href="time_slots.php" class="nav-link <?= ($currentPage == 'time_slots.php') ? 'active' : '' ?>">
               <i class="fas fa-clock me-2"></i>Time Slots
             </a>
             <a href="saved_timetable.php" class="nav-link <?= ($currentPage == 'saved_timetable.php') ? 'active' : '' ?>">
               <i class="fas fa-save me-2"></i>Saved Timetable
             </a>
          </div>
        </div>
    </div>

    <!-- Assignment Management Section -->
    <div class="nav-section">

      <div class="dropdown-header" data-bs-target="#assignmentManagementCollapse" aria-expanded="false">
        <i class="fas fa-tasks me-2"></i>Assignment Management
        <i class="fas fa-chevron-down ms-auto"></i>
      </div>
      <div class="collapse" id="assignmentManagementCollapse">
        <div class="dropdown-menu-items">
          <a href="lecturer_courses.php" class="nav-link <?= ($currentPage == 'lecturer_courses.php') ? 'active' : '' ?>">
            <i class="fas fa-user-plus me-2"></i>Lecturer Course
          </a>
          <a href="class_courses.php" class="nav-link <?= ($currentPage == 'class_courses.php') ? 'active' : '' ?>">
            <i class="fas fa-sitemap me-2"></i>Class Course
          </a>
          <a href="course_roomtype.php" class="nav-link <?= ($currentPage == 'course_roomtype.php') ? 'active' : '' ?>">
            <i class="fas fa-book-clock me-2"></i>Course Room Type
          </a>
        </div>
      </div>
    </div>

  </div>
  
  <!-- Stream Management - Fixed at bottom -->
  <div class="stream-management-section">
    <a href="streams.php" class="nav-link stream-management-link <?= ($currentPage == 'streams.php') ? 'active' : '' ?>">
      <i class="fas fa-cogs me-2"></i>Stream Management
    </a>
  </div>
</div>


