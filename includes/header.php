<?php
if (!isset($pageTitle)) {
  $pageTitle = 'University Timetable Generator';
}

// Ensure session is started early (before any output) so StreamManager can use sessions
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}


// Ensure database connection is available
if (!isset($conn) || $conn === null) {
  $connectPath = __DIR__ . '/../connect.php';
  if (file_exists($connectPath)) {
    include_once $connectPath;
  } else {
    include_once 'connect.php';
  }
}

// Suppress direct PHP error output so raw error dumps don't break the page layout
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// --- Prepare embedded logo data URI for error UI (preferred filename aamusted-logo.png)
$logoDataUri = '';
$preferredLogo = __DIR__ . '/..//images/aamusted-logo.png';
$fallbackLogo = __DIR__ . '/../images/aamustedLog.png';
if (file_exists($preferredLogo)) {
    $b = @file_get_contents($preferredLogo);
    if ($b !== false) $logoDataUri = 'data:image/png;base64,' . base64_encode($b);
} elseif (file_exists($fallbackLogo)) {
    $b = @file_get_contents($fallbackLogo);
    if ($b !== false) $logoDataUri = 'data:image/png;base64,' . base64_encode($b);
}

// Convert warnings/notices to exceptions so they can be handled uniformly
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// Handle uncaught exceptions and display the same friendly error card
set_exception_handler(function($ex) {
    $msg = $ex->getMessage() . " in " . $ex->getFile() . ':' . $ex->getLine();
    $escaped = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Render a visible HTML error card immediately (avoid complex JS string interpolation)
    echo "<div id=\"mainContent\" class=\"main-content\">";
    echo "<div class=\"card border-danger mb-3\"><div class=\"card-body\">";
    // Embed logo as data URI so it always displays on error pages regardless of request path
    $preferred = __DIR__ . '/../images/aamusted-logo.png';
    $fallback = __DIR__ . '/../images/aamustedLog.png';
    $dataUri = '';
    if (file_exists($preferred)) {
        $bin = @file_get_contents($preferred);
        if ($bin !== false) {
            $dataUri = 'data:image/png;base64,' . base64_encode($bin);
        }
    } elseif (file_exists($fallback)) {
        $bin = @file_get_contents($fallback);
        if ($bin !== false) {
            $dataUri = 'data:image/png;base64,' . base64_encode($bin);
        }
    }
    // Place logo above the error header
    if ($dataUri !== '') {
        $imgEsc = htmlspecialchars($dataUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<div style=\"text-align:center;margin-bottom:12px;\"><img src=\"" . $imgEsc . "\" alt=\"Logo\" style=\"max-height:96px;\"/></div>";
    }
    echo "<div style=\"display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;\">";
    echo "<h5 class=\"card-title text-danger\" style=\"margin:0;font-size:1.125rem\">Unexpected system error</h5>";
    echo "<div><button class=\"btn btn-sm btn-outline-secondary copyErrorBtn\" title=\"Copy error\"><i class=\"fas fa-copy\"></i></button></div>";
    echo "</div>";
    echo "<pre class=\"error-pre\" style=\"white-space:pre-wrap;color:#a00;margin:0;\">" . $escaped . "</pre>";
    echo "</div></div></div>";

    // Inline script to wire the copy button; copies textContent of the <pre>
    echo "<script>(function(){var btn=document.querySelector('#mainContent .copyErrorBtn'); if(btn){btn.addEventListener('click',function(){var t=document.querySelector('#mainContent .error-pre').textContent||''; if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(function(){btn.innerHTML='';var chk=document.createElement('i');chk.className='fas fa-check';btn.appendChild(chk);setTimeout(function(){btn.innerHTML='';var ic=document.createElement('i');ic.className='fas fa-copy';btn.appendChild(ic);},1500);});}else{var ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');btn.innerHTML='';var chk2=document.createElement('i');chk2.className='fas fa-check';btn.appendChild(chk2);setTimeout(function(){btn.innerHTML='';var ic2=document.createElement('i');ic2.className='fas fa-copy';btn.appendChild(ic2);},1500);}catch(e){}document.body.removeChild(ta);}});} })();</script>";
    exit(1);
});

// Shutdown handler: if a fatal error occurred, inject a friendly error box into the page
register_shutdown_function(function() {
    $err = error_get_last();
    if (!$err) return;
    $msg = $err['message'] . " in " . $err['file'] . ':' . $err['line'];
    $jsmsg = json_encode($msg);

    // Insert a small JS snippet that adds an error card into #mainContent so layout remains intact
    // The injected card includes a copy button so users can copy the error text for support
    echo '<script>document.addEventListener("DOMContentLoaded", function(){'
        . 'var main = document.getElementById("mainContent");'
        . 'if (!main) { main = document.createElement("div"); main.id = "mainContent"; main.className = "main-content"; document.body.appendChild(main); }'
        . 'var errBox = document.createElement("div");'
        . 'errBox.className = "card border-danger mb-3";'
        . 'errBox.innerHTML = "<div class=\"card-body\">'
            . '<div style=\"display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;\">'
            . '<h5 class=\\"card-title text-danger\\" style=\\"margin:0;\\">An error occurred</h5>'
            . '<div><button class=\\"btn btn-sm btn-outline-secondary copyErrorBtn\\" title=\\"Copy error\\"><i class=\\"fas fa-copy\\"></i></button></div>'
            . '</div>'
            . '<pre class=\\"error-pre\\" style=\\"white-space:pre-wrap;color:#a00;margin:0;\\">' . $jsmsg . '</pre>'
            . '</div>";'
        . 'if (main.firstChild) main.insertBefore(errBox, main.firstChild); else main.appendChild(errBox);'
        . 'var btn = errBox.querySelector(".copyErrorBtn");'
        . 'if (btn) { btn.addEventListener("click", function(){'
        . '  var text = errBox.querySelector(".error-pre").textContent || "";'
        . '  if (navigator.clipboard && navigator.clipboard.writeText) {'
        . '    navigator.clipboard.writeText(text).then(function(){ btn.innerHTML = "<i class=\\\"fas fa-check\\\"></i>"; setTimeout(function(){ btn.innerHTML = "<i class=\\\"fas fa-copy\\\"></i>"; },1500); });'
        . '  } else {'
        . '    var ta = document.createElement("textarea"); ta.value = text; document.body.appendChild(ta); ta.select(); try { document.execCommand("copy"); btn.innerHTML = "<i class=\\\"fas fa-check\\\"></i>"; setTimeout(function(){ btn.innerHTML = "<i class=\\\"fas fa-copy\\\"></i>"; },1500); } catch(e){} document.body.removeChild(ta);'
        . '  }'
        . '}); }'
        . '});</script>';
});

// Include flash helper so pages can set redirect flashes
$flashFile = __DIR__ . '/flash.php';
if (file_exists($flashFile)) {
    include_once $flashFile;
}

// --- Handle stream switching (keep session key consistent with index.php) ---
if (isset($_GET['stream_id'])) {
    $_SESSION['active_stream'] = intval($_GET['stream_id']);
}

// Determine active stream (use value from including page if provided)
$active_stream = $active_stream ?? $_SESSION['active_stream'] ?? $_SESSION['stream_id'] ?? 1; // default to Regular (id=1)

// --- Fetch streams dynamically if not already provided by caller ---
if (!isset($streams) || !is_array($streams) || empty($streams)) {
    $streams = [];

    // Ensure $conn is available; try to include the central connection if not
    if (!isset($conn) || !$conn) {
        $connFile = __DIR__ . '/../connect.php';
        if (file_exists($connFile)) {
            include_once $connFile;
        }
    }

    if (isset($conn) && $conn) {
        $result = $conn->query("SELECT id, name FROM streams WHERE is_active = 1 ORDER BY id ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $streams[] = $row;
            }
        }
    }

    // Fallback: ensure at least one default stream exists in the UI
    if (empty($streams)) {
        $streams[] = ['id' => 1, 'name' => 'Regular'];
    }
}

// --- Get current stream name if not provided by caller ---
if (!isset($current_stream_name) || $current_stream_name === '') {
    $current_stream_name = '';
    foreach ($streams as $s) {
        if ($s['id'] == $active_stream) {
            $current_stream_name = $s['name'];
            break;
        }
    }
}

// Render any flash message (if included by pages), except on rooms.php per request
$__current_script = basename($_SERVER['PHP_SELF'] ?? '');
if ($__current_script !== 'rooms.php') {
  if (function_exists('flash_get')) {
    $flash = flash_get();
    if ($flash) {
      // Make available to the page templates; assign to the conventional variables
      if (isset($flash['type']) && in_array($flash['type'], ['error', 'danger'])) {
        $error_message = $flash['message'];
      } else {
        $success_message = $flash['message'];
      }
    }
  }
}
// Include admin jobs modal so it's available across admin pages (allow disabling)
if (!isset($show_admin_jobs_modal) || $show_admin_jobs_modal !== false) {
    $adminJobsModal = __DIR__ . '/admin_jobs_modal.php';
    if (file_exists($adminJobsModal)) {
        include_once $adminJobsModal;
    }
}

// --- Helper function for counts (guard against redeclaration) ---
if (!function_exists('getCount')) {
    function getCount($conn, $query, $stream_id) {
        $stmt = $conn->prepare($query);
        if ($stmt === false) return 0;
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? intval(array_values($row)[0]) : 0;
    }
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
      /* Primary brand maroon (header/cards) */
      --primary-color: #800020;
      /* Slightly darker hover for maroon elements */
      --hover-color: #600010;
      /* Primary action blue (Add buttons) */
      --brand-blue: #0d6efd;
      /* Accent / warning gold used for yellow cards */
      --accent-color: #FFD700;
      /* Brand success green used on success buttons/cards */
      --brand-green: #198754;
      --bg-color: #ffffff;
      --sidebar-bg: #f8f8f8;
      --footer-bg: #800020;
    }
    body {
      font-family: 'Open Sans', sans-serif;
      background-color: var(--bg-color);
      margin: 0;
      padding-top: 70px; /* For fixed header */
      overflow: auto;
      font-size: 14px;
    }
    .navbar { background-color: var(--primary-color); position: fixed; top: 0; width: 100%; z-index: 1050; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .navbar-brand { font-weight: 600; font-size: 1.25rem; display: flex; align-items: center; letter-spacing: 0.4px; }
    .navbar-brand img { height: 32px; margin-right: 10px; }
    #sidebarToggle { 
      border: none; 
      background: transparent; 
      color: #fff; 
      font-size: 1.5rem; 
      margin-right: 10px; 
      cursor: pointer;
      padding: 8px;
      border-radius: 4px;
      transition: all 0.2s ease;
    }
    
    #sidebarToggle:hover {
      background-color: rgba(255, 255, 255, 0.1);
      transform: scale(1.1);
    }
    .sidebar { 
      background-color: var(--sidebar-bg);
      position: fixed; 
      top: 70px; 
      left: 0; 
      width: 280px; 
      box-shadow: 2px 0 5px rgba(0,0,0,0.1); 
      transition: transform 0.3s ease; 
      transform: translateX(0); 
      z-index: 1040; 
      height: calc(100vh - 70px); 
      display: flex;
      flex-direction: column;
    }
    .sidebar.collapsed { transform: translateX(-100%); }
    
    .nav-links { 
      display: flex; 
      flex-direction: column; 
      gap: 0; 
      padding: 20px 20px 80px 20px;
      overflow-y: auto;
      flex: 1;
    }
    .nav-section { 
      margin-bottom: 15px; 
    }
    
    .nav-link { 
      display: block; 
      width: 100%; 
      padding: 8px 12px; 
      color: var(--primary-color); 
      text-decoration: none; 
      font-weight: 600; 
      font-size: 0.9rem;
      border-radius: 4px;
      transition: all 0.2s ease;
    }
    .nav-link:hover, .nav-link.active { 
      background-color: var(--primary-color); 
      color: #fff; 
      transform: translateX(5px);
    }
    .dropdown-header {
      display: flex;
      align-items: center;
      width: 100%;
      padding: 8px 12px;
      color: var(--primary-color);
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      border-radius: 4px;
      transition: all 0.2s ease;
      background-color: rgba(128, 0, 32, 0.1);
    }
    .dropdown-header:hover {
      background-color: rgba(128, 0, 32, 0.2);
      color: var(--hover-color);
    }
    .dropdown-header i.fa-chevron-down {
      transition: transform 0.2s ease;
    }
    .dropdown-header[aria-expanded="true"] i.fa-chevron-down {
      transform: rotate(180deg);
    }
    
    /* Ensure chevron rotates when Bootstrap adds 'show' class */
    .collapse.show ~ .dropdown-header i.fa-chevron-down,
    .dropdown-header[aria-expanded="true"] i.fa-chevron-down {
      transform: rotate(180deg);
    }
    
    .dropdown-menu-items {
      margin-left: 15px;
      margin-top: 5px;
      border-left: 2px solid rgba(128, 0, 32, 0.2);
      padding-left: 10px;
      transition: all 0.3s ease;
    }
    
    /* Smooth dropdown animation */
    .collapse {
      transition: all 0.3s ease;
    }
    
    .collapse:not(.show) {
      display: none;
    }
    
    .collapse.show {
      display: block;
    }
    .dropdown-menu-items .nav-link {
      font-size: 0.85rem;
      padding: 6px 10px;
      margin-bottom: 2px;
    }
    .dropdown-menu-items .nav-link:hover {
      transform: translateX(3px);
    }
    
    /* Stream Management Section - Fixed at bottom */
    .stream-management-section {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 280px;
      padding: 20px;
      border-top: 2px solid rgba(128, 0, 32, 0.2);
      background-color: var(--sidebar-bg);
      flex-shrink: 0;
      z-index: 1041;
    }
    
    .stream-management-link {
      background-color: rgba(128, 0, 32, 0.1);
      border: 2px solid rgba(128, 0, 32, 0.3);
      font-weight: 700;
      text-align: center;
      padding: 12px 16px;
      margin: 0;
    }
    
    .stream-management-link:hover,
    .stream-management-link.active {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
      color: white;
      transform: scale(1.02);
    }
    .main-content { transition: margin-left 0.3s ease; margin-left: 280px; padding: 20px; height: calc(100vh - 70px); overflow: auto; }
    .main-content.collapsed { margin-left: 0; }
    .footer { background-color: var(--footer-bg); color: #fff; padding: 10px; text-align: center; position: fixed; bottom: 0; left: 280px; right: 0; transition: left 0.3s ease; z-index: 1030; }
    .footer.collapsed { 
      left: 0; 
    }
    @media (max-width: 768px) { .sidebar { width: 250px; } .main-content.shift { margin-left: 250px; } .footer.shift { left: 250px; } }
    @media (max-width: 576px) { .sidebar { width: 280px; } .main-content.shift { margin-left: 0; } .footer.shift { left: 0; } }
    
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

    /* Theme-specific card colors matching logo: red, gold, green */
    :root {
      --theme-red: #7a0b1c; /* primary maroon */
      --theme-gold: #FFC107; /* gold / warning */
      --theme-green: #198754; /* success green */
    }

    .bg-theme-primary { background-color: var(--theme-red) !important; color: #fff !important; }
    .bg-theme-accent { background-color: var(--theme-gold) !important; color: #222 !important; }
    .bg-theme-green { background-color: var(--theme-green) !important; color: #fff !important; }

    .theme-card { border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
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
      <div class="mx-auto text-white" id="currentStream">
        <i class="fas fa-clock me-2"></i>Current Stream: 
        <span class="me-2"><strong><?= htmlspecialchars($current_stream_name) ?></strong></span>
      </div>
      <div class="ms-auto text-white" id="currentTime"></div>
    </div>
  </nav>
  
  <script>
  // Dynamic clock for header: updates every second
  document.addEventListener('DOMContentLoaded', function() {
    function updateTime() {
      var now = new Date();
      var timeString = now.toLocaleTimeString('en-US', {
        hour12: true,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
      var el = document.getElementById('currentTime');
      if (el) el.textContent = timeString;
    }
    updateTime();
    setInterval(updateTime, 1000);
  });

  (function(){
    var storageKey = 'sidebarCollapsed';
    function getEl(id){ try { return document.getElementById(id); } catch(e) { return null; } }

    // Ensure toggle button exists (create fallback if missing)
    var sidebarToggle = getEl('sidebarToggle');
    if (!sidebarToggle) {
      try {
        var navContainer = document.querySelector('.navbar .container-fluid') || document.body;
        sidebarToggle = document.createElement('button');
        sidebarToggle.id = 'sidebarToggle';
        sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
        sidebarToggle.style.border = 'none';
        sidebarToggle.style.background = 'transparent';
        sidebarToggle.style.color = '#fff';
        sidebarToggle.style.fontSize = '1.5rem';
        sidebarToggle.style.cursor = 'pointer';
        if (navContainer.firstChild) navContainer.insertBefore(sidebarToggle, navContainer.firstChild);
        else navContainer.appendChild(sidebarToggle);
      } catch (e) { /* fail silently */ }
    }

    function applyState(collapsed) {
      var sidebar = getEl('sidebar');
      var main = getEl('mainContent');
      var footer = getEl('footer');
      if (!sidebar && !main && !footer) return;
      if (collapsed) {
        if (sidebar) sidebar.classList.add('collapsed');
        if (main) main.classList.add('collapsed');
        if (footer) footer.classList.add('collapsed');
      } else {
        if (sidebar) sidebar.classList.remove('collapsed');
        if (main) main.classList.remove('collapsed');
        if (footer) footer.classList.remove('collapsed');
      }
    }

    // Initialize from localStorage (safe)
    try {
      var stored = localStorage.getItem(storageKey);
      applyState(stored === 'true');
    } catch (e) { /* ignore storage errors */ }

    // Also ensure state is applied after DOM content loads (in case sidebar wasn't present yet)
    try {
      document.addEventListener('DOMContentLoaded', function(){
        try {
          var storedAfter = localStorage.getItem(storageKey);
          applyState(storedAfter === 'true');
        } catch (e) { }
      });
    } catch (e) { }

    // Attach click handler (safe)
    try {
      sidebarToggle.addEventListener('click', function(e) {
        try {
          var sidebar = getEl('sidebar');
          var main = getEl('mainContent');
          var footer = getEl('footer');
          var collapsed = false;
          if (sidebar) collapsed = sidebar.classList.toggle('collapsed');
          if (main) main.classList.toggle('collapsed', collapsed);
          if (footer) footer.classList.toggle('collapsed', collapsed);
          try { localStorage.setItem(storageKey, String(collapsed)); } catch (e) {}
        } catch (e) { /* silent */ }
      });
      // Mark handler attached so other scripts (footer) can skip attaching again
      try { window.__sidebarToggleAttached = true; } catch (e) {}
    } catch (e) { /* silent */ }
  })();
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', function(){
    // Animated collapse open/close helpers
    function animateOpen(el){
      el.style.display = 'block';
      var height = el.scrollHeight + 'px';
      el.style.overflow = 'hidden';
      el.style.maxHeight = '0';
      // force reflow
      el.offsetHeight;
      el.style.transition = 'max-height 300ms ease';
      el.style.maxHeight = height;
      el.classList.add('show');
      setTimeout(function(){
        el.style.maxHeight = '';
        el.style.overflow = '';
        el.style.transition = '';
      }, 300);
    }

    function animateClose(el){
      el.style.overflow = 'hidden';
      el.style.maxHeight = el.scrollHeight + 'px';
      // force reflow
      el.offsetHeight;
      el.style.transition = 'max-height 300ms ease';
      el.style.maxHeight = '0';
      setTimeout(function(){
        el.classList.remove('show');
        el.style.display = 'none';
        el.style.maxHeight = '';
        el.style.overflow = '';
        el.style.transition = '';
      }, 300);
    }

    // Initialize collapse elements visibility to avoid jump
    document.querySelectorAll('.collapse').forEach(function(c){
      if (c.classList.contains('show')) {
        c.style.display = 'block';
      } else {
        c.style.display = 'none';
      }
    });

    // Wire dropdown headers to toggle their target collapses with animation
    document.querySelectorAll('.dropdown-header').forEach(function(header){
      header.addEventListener('click', function(e){
        e.preventDefault();
        var targetSelector = header.getAttribute('data-bs-target');
        if (!targetSelector) return;
        var target = document.querySelector(targetSelector);
        if (!target) return;

        var isOpen = target.classList.contains('show');
        // Close other open collapses first (exclusive behavior)
        document.querySelectorAll('.collapse.show').forEach(function(open){
          if (open !== target) {
            animateClose(open);
            var associatedHeader = document.querySelector('[data-bs-target="#' + open.id + '"]');
            if (associatedHeader) associatedHeader.setAttribute('aria-expanded', 'false');
          }
        });

        if (isOpen) {
          animateClose(target);
          header.setAttribute('aria-expanded', 'false');
        } else {
          animateOpen(target);
          header.setAttribute('aria-expanded', 'true');
        }
      });
    });
  });
  </script>

  <!-- Bootstrap JS Bundle (includes Popper) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

  <!-- Stream Change JavaScript -->
  <script>
  function changeStream(streamId) {
    fetch('change_stream.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'stream_id=' + streamId
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Reload the page to show filtered data
        window.location.reload();
      } else {
        alert('Error changing stream: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error changing stream. Please try again.');
    });
  }

  // Set the current stream based on session
  document.addEventListener('DOMContentLoaded', function() {
    // This will be populated by PHP when the page loads
    const currentStreamId = '<?php 
      if (isset($conn)) {
        include_once "includes/stream_manager.php";
        $streamManager = getStreamManager();
        echo $streamManager->getCurrentStreamId();
      } else {
        echo "1";
      }
    ?>';
    
    if (currentStreamId && document.getElementById('streamSelect')) {
      document.getElementById('streamSelect').value = currentStreamId;
    }
  });
  </script>


