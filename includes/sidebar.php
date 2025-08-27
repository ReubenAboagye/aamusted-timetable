<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
<div class="sidebar" id="sidebar">
  <div class="nav-links">
    <a href="index.php" class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
    <a href="timetable.php" class="<?= ($currentPage == 'timetable.php') ? 'active' : '' ?>"><i class="fas fa-calendar-alt me-2"></i>Generate Timetable</a>
    <a href="view_timetable.php" class="<?= ($currentPage == 'view_timetable.php') ? 'active' : '' ?>"><i class="fas fa-table me-2"></i>View Timetable</a>
    <a href="timetable_lecturers.php" class="<?= ($currentPage == 'timetable_lecturers.php') ? 'active' : '' ?>"><i class="fas fa-users-cog me-2"></i>Co-Teaching</a>
    <a href="adddepartmentform.php" class="<?= ($currentPage == 'adddepartmentform.php') ? 'active' : '' ?>"><i class="fas fa-building me-2"></i>Departments</a>
    <a href="sessions.php" class="<?= ($currentPage == 'sessions.php') ? 'active' : '' ?>"><i class="fas fa-clock me-2"></i>Sessions</a>
    <a href="semesters.php" class="<?= ($currentPage == 'semesters.php') ? 'active' : '' ?>"><i class="fas fa-calendar me-2"></i>Semesters</a>
    <a href="classes.php" class="<?= ($currentPage == 'classes.php') ? 'active' : '' ?>"><i class="fas fa-users me-2"></i>Classes</a>
    <a href="courses.php" class="<?= ($currentPage == 'courses.php') ? 'active' : '' ?>"><i class="fas fa-book me-2"></i>Courses</a>
    <a href="lecturers.php" class="<?= ($currentPage == 'lecturers.php') ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers</a>
    <a href="rooms.php" class="<?= ($currentPage == 'rooms.php') ? 'active' : '' ?>"><i class="fas fa-door-open me-2"></i>Rooms</a>
    <a href="time_slots.php" class="<?= ($currentPage == 'time_slots.php') ? 'active' : '' ?>"><i class="fas fa-clock me-2"></i>Time Slots</a>
    <a href="class_courses.php" class="<?= ($currentPage == 'class_courses.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Class-Courses</a>
    <a href="lecturer_courses.php" class="<?= ($currentPage == 'lecturer_courses.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Lecturer-Courses</a>
    <a href="lecturer_session_availability.php" class="<?= ($currentPage == 'lecturer_session_availability.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Lecturer Sessions</a>
    <a href="course_session_availability.php" class="<?= ($currentPage == 'course_session_availability.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Course Sessions</a>
  </div>
</div>


