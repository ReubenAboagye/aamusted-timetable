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
<?php $pageTitle = 'Generate Timetable'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

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
<?php include 'includes/footer.php'; ?>
