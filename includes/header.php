<?php
if (!isset($pageTitle)) {
  $pageTitle = 'University Timetable Generator';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
      padding-top: 70px; /* For fixed header */
      overflow: hidden;
      font-size: 14px;
    }
    .navbar { background-color: var(--primary-color); position: fixed; top: 0; width: 100%; z-index: 1050; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .navbar-brand { font-weight: 600; font-size: 1.75rem; display: flex; align-items: center; }
    .navbar-brand img { height: 40px; margin-right: 10px; }
    #sidebarToggle { border: none; background: transparent; color: #fff; font-size: 1.5rem; margin-right: 10px; }
    .sidebar { background-color: var(--sidebar-bg); position: fixed; top: 70px; left: 0; width: 250px; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); transition: transform 0.3s ease; transform: translateX(0); z-index: 1040; height: calc(100vh - 70px); overflow-y: auto; }
    .sidebar.collapsed { transform: translateX(-100%); }
    .nav-links { display: flex; flex-direction: column; gap: 5px; padding-bottom: 20px; }
    .nav-links a { display: block; width: 100%; padding: 5px 10px; color: var(--primary-color); text-decoration: none; font-weight: 600; font-size: 1rem; }
    .nav-links a:not(:last-child) { border-bottom: 1px solid #ccc; margin-bottom: 5px; padding-bottom: 5px; }
    .nav-links a:hover, .nav-links a.active { background-color: var(--primary-color); color: #fff; border-radius: 4px; }
    .main-content { transition: margin-left 0.3s ease; margin-left: 250px; padding: 20px; height: calc(100vh - 70px); overflow: auto; }
    .main-content.collapsed { margin-left: 0; }
    .footer { background-color: var(--footer-bg); color: #fff; padding: 10px; text-align: center; position: fixed; bottom: 0; left: 250px; right: 0; transition: left 0.3s ease; z-index: 1030; }
    .footer.collapsed { left: 0; }
    @media (max-width: 768px) { .sidebar { width: 200px; } .main-content.shift { margin-left: 200px; } .footer.shift { left: 200px; } }
    @media (max-width: 576px) { .sidebar { width: 250px; } .main-content.shift { margin-left: 0; } .footer.shift { left: 0; } }
    
    /* Consistent Table Styling */
    .table-container {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      overflow: hidden;
    }
    
    .table-header {
      background: linear-gradient(135deg, var(--primary-color), var(--hover-color));
      color: white;
      padding: 15px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .table-header h4 {
      margin: 0;
      font-weight: 600;
      font-size: 1.25rem;
    }
    
    .table-responsive {
      border-radius: 0 0 8px 8px;
    }
    
    .table {
      margin-bottom: 0;
      border: none;
    }
    
    .table thead th {
      background: #f8f9fa;
      border: none;
      padding: 12px 15px;
      font-weight: 600;
      color: #495057;
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 0.5px;
      border-bottom: 2px solid #dee2e6;
    }
    
    .table tbody tr {
      border-bottom: 1px solid #f1f3f4;
      transition: all 0.2s ease;
    }
    
    .table tbody tr:hover {
      background-color: #f8f9fa;
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .table tbody td {
      padding: 12px 15px;
      vertical-align: middle;
      border: none;
    }
    
    .table tbody td:first-child {
      font-weight: 600;
      color: var(--primary-color);
    }
    
    .badge {
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 12px;
      font-weight: 500;
    }
    
    .btn-sm {
      padding: 4px 12px;
      font-size: 0.8rem;
      border-radius: 6px;
    }
    
    .btn-outline-primary {
      border-color: var(--primary-color);
      color: var(--primary-color);
    }
    
    .btn-outline-primary:hover {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
      color: white;
    }
    
    .btn-outline-danger {
      border-color: #dc3545;
      color: #dc3545;
    }
    
    .btn-outline-danger:hover {
      background-color: #dc3545;
      border-color: #dc3545;
      color: white;
    }
    
    .search-container {
      margin-bottom: 20px;
    }
    
    .search-input {
      border: 2px solid #e9ecef;
      border-radius: 25px;
      padding: 10px 20px;
      width: 100%;
      max-width: 400px;
      transition: all 0.3s ease;
    }
    
    .search-input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(128, 0, 32, 0.25);
      outline: none;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #6c757d;
    }
    
    .empty-state i {
      font-size: 3rem;
      margin-bottom: 15px;
      opacity: 0.5;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <nav class="navbar navbar-dark">
    <div class="container-fluid">
      <button id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <a class="navbar-brand text-white" href="#">
        <img src="images/aamustedLog.png" alt="AAMUSTED Logo">University Timetable Generator
      </a>
      <div class="ms-auto text-white" id="currentTime">12:00:00 PM</div>
    </div>
  </nav>


