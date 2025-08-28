<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<div class="sidebar" id="sidebar">
  <div class="nav-links">
    <!-- Main Menu Items -->
    <div class="nav-section">
      <a href="index.php" class="nav-link <?= ($currentPage == 'index.php') ? 'active' : '' ?>">
        <i class="fas fa-home me-2"></i>Dashboard
      </a>
      <a href="view_timetable.php" class="nav-link <?= ($currentPage == 'view_timetable.php') ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt me-2"></i>Timetable
      </a>
      <a href="generate_timetable.php" class="nav-link <?= ($currentPage == 'generate_timetable.php') ? 'active' : '' ?>">
        <i class="fas fa-calendar-plus me-2"></i>Generate Timetable
      </a>
    </div>

    <!-- Management Section -->
    <div class="nav-section">
      <div class="dropdown-header" data-bs-toggle="collapse" data-bs-target="#managementCollapse" aria-expanded="false">
        <i class="fas fa-cogs me-2"></i>Management
        <i class="fas fa-chevron-down ms-auto"></i>
      </div>
      <div class="collapse" id="managementCollapse">
        <div class="dropdown-menu-items">
          <a href="department.php" class="nav-link <?= ($currentPage == 'department.php') ? 'active' : '' ?>">
            <i class="fas fa-building me-2"></i>Department
          </a>
          <a href="programs.php" class="nav-link <?= ($currentPage == 'programs.php') ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap me-2"></i>Programs
          </a>
          <a href="levels.php" class="nav-link <?= ($currentPage == 'levels.php') ? 'active' : '' ?>">
            <i class="fas fa-layer-group me-2"></i>Levels
          </a>
          <a href="streams.php" class="nav-link <?= ($currentPage == 'streams.php') ? 'active' : '' ?>">
            <i class="fas fa-clock me-2"></i>Streams
          </a>
          <a href="sessions.php" class="nav-link <?= ($currentPage == 'sessions.php') ? 'active' : '' ?>">
            <i class="fas fa-calendar me-2"></i>Sessions
          </a>
          <a href="lecturers.php" class="nav-link <?= ($currentPage == 'lecturers.php') ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher me-2"></i>Lecturers
          </a>
          <a href="courses.php" class="nav-link <?= ($currentPage == 'courses.php') ? 'active' : '' ?>">
            <i class="fas fa-book me-2"></i>Courses
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

    <!-- Session Management Section -->
    <div class="nav-section">
      <div class="dropdown-header" data-bs-toggle="collapse" data-bs-target="#sessionCollapse" aria-expanded="false">
        <i class="fas fa-calendar-check me-2"></i>Session Management
        <i class="fas fa-chevron-down ms-auto"></i>
      </div>
      <div class="collapse" id="sessionCollapse">
        <div class="dropdown-menu-items">
          <a href="lecturer_session_availability.php" class="nav-link <?= ($currentPage == 'lecturer_session_availability.php') ? 'active' : '' ?>">
            <i class="fas fa-user-clock me-2"></i>Lecturers Session
          </a>
          <a href="course_session_availability.php" class="nav-link <?= ($currentPage == 'course_session_availability.php') ? 'active' : '' ?>">
            <i class="fas fa-book-clock me-2"></i>Course Session
          </a>
          <a href="lecturer_courses.php" class="nav-link <?= ($currentPage == 'lecturer_courses.php') ? 'active' : '' ?>">
            <i class="fas fa-user-plus me-2"></i>Course → Lecturer
          </a>
          <a href="class_courses.php" class="nav-link <?= ($currentPage == 'class_courses.php') ? 'active' : '' ?>">
            <i class="fas fa-sitemap me-2"></i>Course → Class (per Session)
          </a>
        </div>
      </div>
    </div>
  </div>
</div>


