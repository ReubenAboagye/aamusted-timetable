<?php
include 'connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Generate Timetable - TimeTable Generator</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    :root { --primary-color: #800020; --hover-color: #600010; --accent-color: #FFD700; --bg-color: #ffffff; --sidebar-bg: #f8f8f8; --footer-bg: #800020; }
    body { font-family: 'Open Sans', sans-serif; background: var(--bg-color); margin: 0; padding-top: 70px; font-size: 14px; }
    .navbar { background: var(--primary-color); position: fixed; top: 0; width: 100%; z-index: 1050; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .navbar-brand { font-weight: 600; font-size: 1.75rem; display: flex; align-items: center; }
    .navbar-brand img { height: 40px; margin-right: 10px; }
    #sidebarToggle { border: none; background: transparent; color: #fff; font-size: 1.5rem; margin-right: 10px; }
    .sidebar { background: var(--sidebar-bg); position: fixed; top: 70px; left: 0; width: 250px; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); transition: transform 0.3s ease; transform: translateX(-100%); }
    .sidebar.show { transform: translateX(0); }
    .nav-links { display: flex; flex-direction: column; gap: 5px; }
    .nav-links a { display: flex; align-items: center; width: 100%; padding: 5px 10px; color: var(--primary-color); text-decoration: none; font-weight: 600; font-size: 1rem; transition: background 0.3s, color 0.3s; }
    .nav-links a:hover, .nav-links a.active { background: var(--primary-color); color: #fff; border-radius: 4px; }
    .main-content { transition: margin-left 0.3s ease; margin-left: 0; padding: 20px; height: calc(100vh - 70px); overflow: auto; }
    .main-content.shift { margin-left: 250px; }
    .footer { background: var(--footer-bg); color: #fff; padding: 10px; text-align: center; position: fixed; bottom: 0; left: 0; right: 0; transition: left 0.3s ease; }
    .footer.shift { left: 250px; }
    #backToTop { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: none; background: rgba(128,0,32,0.7); border: none; outline: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; transition: background 0.3s ease, transform 0.3s ease; padding: 0; overflow: hidden; }
    #backToTop svg { display: block; width: 100%; height: 100%; }
    #backToTop .arrow-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #FFD700; font-size: 1.5rem; pointer-events: none; }
    #backToTop:hover { background: rgba(96,0,16,0.9); transform: scale(1.1); }
    .timetable-form { max-width: 400px; margin: 0 auto; }
  </style>
</head>
<body>
  <nav class="navbar navbar-dark">
    <div class="container-fluid">
      <button id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <a class="navbar-brand text-white" href="#">
        <img src="images/aamustedLog.png" alt="AAMUSTED Logo">TimeTable Generator
      </a>
      <div class="ms-auto text-white" id="currentTime">12:00:00 PM</div>
    </div>
  </nav>
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
  <div class="main-content" id="mainContent">
    <h2>Generate Timetable</h2>
    <form id="generateTimetableForm" class="timetable-form mb-4">
      <div class="mb-3">
        <label for="semester" class="form-label">Select Semester</label>
        <select id="semester" name="semester" class="form-select">
          <option selected disabled>Select Semester</option>
          <option value="1">Semester 1</option>
          <option value="2">Semester 2</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary w-100">Generate Timetable</button>
    </form>
    <div id="timetableContainer"></div>
  </div>
  <div class="footer" id="footer">&copy; 2025 TimeTable Generator</div>
  <button id="backToTop">
    <svg width="50" height="50" viewBox="0 0 50 50">
      <circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"/>
    </svg>
    <i class="fas fa-arrow-up arrow-icon"></i>
  </button>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
  <script>
    function updateTime(){const now=new Date(),timeString=now.toLocaleTimeString('en-US',{hour12:true,hour:'2-digit',minute:'2-digit',second:'2-digit'});document.getElementById('currentTime').textContent=timeString;}
    setInterval(updateTime,1000);updateTime();
    document.getElementById('sidebarToggle').addEventListener('click',function(){const sidebar=document.getElementById('sidebar'),mainContent=document.getElementById('mainContent'),footer=document.getElementById('footer');sidebar.classList.toggle('show');if(sidebar.classList.contains('show')){mainContent.classList.add('shift');footer.classList.add('shift');}else{mainContent.classList.remove('shift');footer.classList.remove('shift');}});
    document.getElementById('generateTimetableForm').addEventListener('submit',function(e){e.preventDefault();const formData=new FormData(this);fetch('generate_timetable.php',{method:'POST',body:formData}).then(response=>response.text()).then(data=>{document.getElementById('timetableContainer').innerHTML=data;}).catch(error=>console.error('Error:',error));});
    const backToTopButton=document.getElementById("backToTop"),progressCircle=document.getElementById("progressCircle"),circumference=2*Math.PI*20;progressCircle.style.strokeDasharray=circumference;progressCircle.style.strokeDashoffset=circumference;
    window.addEventListener("scroll",function(){const scrollTop=document.documentElement.scrollTop||document.body.scrollTop;backToTopButton.style.display=scrollTop>100?"block":"none";const scrollHeight=document.documentElement.scrollHeight-document.documentElement.clientHeight,scrollPercentage=scrollTop/scrollHeight;progressCircle.style.strokeDashoffset=circumference-(scrollPercentage*circumference);});
    backToTopButton.addEventListener("click",function(){window.scrollTo({top:0,behavior:"smooth"});});
  </script>
</body>
</html>
