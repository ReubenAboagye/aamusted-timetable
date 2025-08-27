<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new lecturer
                $lecturerName = $_POST['lecturerName'];
                $departmentId = $_POST['departmentId'];
                $specialization = $_POST['specialization'];
                $maxDailyCourses = $_POST['maxDailyCourses'];
                $maxWeeklyHours = $_POST['maxWeeklyHours'];
                $maxSessionsPerWeek = $_POST['maxSessionsPerWeek'];
                $preferredDays = isset($_POST['preferredDays']) ? json_encode($_POST['preferredDays']) : '[]';
                $unavailableTimes = isset($_POST['unavailableTimes']) ? $_POST['unavailableTimes'] : '';
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if lecturer name already exists in the same department
                $checkSql = "SELECT id FROM lecturers WHERE name = ? AND department_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("si", $lecturerName, $departmentId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Lecturer name already exists in this department!');</script>";
                } else {
                    $sql = "INSERT INTO lecturers (name, department_id, specialization, max_daily_courses, max_weekly_hours, max_sessions_per_week, preferred_days, unavailable_times, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("sisiiissi", $lecturerName, $departmentId, $specialization, $maxDailyCourses, $maxWeeklyHours, $maxSessionsPerWeek, $preferredDays, $unavailableTimes, $isActive);
                        if ($stmt->execute()) {
                            echo "<script>alert('Lecturer added successfully!'); window.location.href='lecturers.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing lecturer
                $id = $_POST['id'];
                $lecturerName = $_POST['lecturerName'];
                $departmentId = $_POST['departmentId'];
                $specialization = $_POST['specialization'];
                $maxDailyCourses = $_POST['maxDailyCourses'];
                $maxWeeklyHours = $_POST['maxWeeklyHours'];
                $maxSessionsPerWeek = $_POST['maxSessionsPerWeek'];
                $preferredDays = isset($_POST['preferredDays']) ? json_encode($_POST['preferredDays']) : '[]';
                $unavailableTimes = isset($_POST['unavailableTimes']) ? $_POST['unavailableTimes'] : '';
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if lecturer name already exists for other lecturers in the same department
                $checkSql = "SELECT id FROM lecturers WHERE name = ? AND department_id = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("sii", $lecturerName, $departmentId, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Lecturer name already exists in this department!');</script>";
                } else {
                    $sql = "UPDATE lecturers SET name = ?, department_id = ?, specialization = ?, max_daily_courses = ?, max_weekly_hours = ?, max_sessions_per_week = ?, preferred_days = ?, unavailable_times = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("sisiiissii", $lecturerName, $departmentId, $specialization, $maxDailyCourses, $maxWeeklyHours, $maxSessionsPerWeek, $preferredDays, $unavailableTimes, $isActive, $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Lecturer updated successfully!'); window.location.href='lecturers.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete lecturer
                $id = $_POST['id'];
                
                // Check if lecturer has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM lecturer_courses WHERE lecturer_id = ?) as lecturer_course_count,
                    (SELECT COUNT(*) FROM lecturer_session_availability WHERE lecturer_id = ?) as session_availability_count,
                    (SELECT COUNT(*) FROM timetable WHERE lecturer_id = ?) as timetable_count,
                    (SELECT COUNT(*) FROM timetable_lecturers WHERE lecturer_id = ?) as timetable_lecturer_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iiii", $id, $id, $id, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['lecturer_course_count'] > 0 || $dependencies['session_availability_count'] > 0 || $dependencies['timetable_count'] > 0 || $dependencies['timetable_lecturer_count'] > 0) {
                    echo "<script>alert('Cannot delete lecturer: Has dependent records. Consider deactivating instead.');</script>";
                } else {
                    $sql = "DELETE FROM lecturers WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Lecturer deleted successfully!'); window.location.href='lecturers.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                break;
        }
    }
}

// Fetch departments for dropdown
$departments = [];
$deptSql = "SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name";
$deptResult = $conn->query($deptSql);
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Fetch existing lecturers for display
$lecturers = [];
$sql = "SELECT l.*, d.name as department_name, d.code as department_code
        FROM lecturers l
        JOIN departments d ON l.department_id = d.id
        ORDER BY l.name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = $row;
    }
}

// Days array for preferred days
$days = [
    'monday' => 'Monday',
    'tuesday' => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday',
    'saturday' => 'Saturday',
    'sunday' => 'Sunday'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lecturers - University Timetable Generator</title>
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
    
    <!-- Sidebar (Updated with all 14 tables) -->
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <div class="sidebar" id="sidebar">
        <div class="nav-links">
            <a href="index.php" class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
            
            <!-- Core Timetable Management -->
            <a href="timetable.php" class="<?= ($currentPage == 'timetable.php') ? 'active' : '' ?>"><i class="fas fa-calendar-alt me-2"></i>Generate Timetable</a>
            <a href="view_timetable.php" class="<?= ($currentPage == 'view_timetable.php') ? 'active' : '' ?>"><i class="fas fa-table me-2"></i>View Timetable</a>
            <a href="timetable_lecturers.php" class="<?= ($currentPage == 'timetable_lecturers.php') ? 'active' : '' ?>"><i class="fas fa-users-cog me-2"></i>Co-Teaching</a>
            
            <!-- Academic Structure -->
            <a href="department.php" class="<?= ($currentPage == 'department.php') ? 'active' : '' ?>"><i class="fas fa-building me-2"></i>Departments</a>
            <a href="session.php" class="<?= ($currentPage == 'session.php') ? 'active' : '' ?>"><i class="fas fa-clock me-2"></i>Sessions</a>
            <a href="semesters.php" class="<?= ($currentPage == 'semesters.php') ? 'active' : '' ?>"><i class="fas fa-calendar me-2"></i>Semesters</a>
            <a href="classes.php" class="<?= ($currentPage == 'classes.php') ? 'active' : '' ?>"><i class="fas fa-users me-2"></i>Classes</a>
            <a href="courses.php" class="<?= ($currentPage == 'courses.php') ? 'active' : '' ?>"><i class="fas fa-book me-2"></i>Courses</a>
            
            <!-- Staff & Resources -->
            <a href="lecturers.php" class="<?= ($currentPage == 'lecturers.php') ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers</a>
            <a href="rooms.php" class="<?= ($currentPage == 'rooms.php') ? 'active' : '' ?>"><i class="fas fa-door-open me-2"></i>Rooms</a>
            <a href="time_slots.php" class="<?= ($currentPage == 'time_slots.php') ? 'active' : '' ?>"><i class="fas fa-clock me-2"></i>Time Slots</a>
            
            <!-- Relationship Management -->
            <a href="class_courses.php" class="<?= ($currentPage == 'class_courses.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Class-Courses</a>
            <a href="lecturer_courses.php" class="<?= ($currentPage == 'lecturer_courses.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Lecturer-Courses</a>
            <a href="lecturer_session_availability.php" class="<?= ($currentPage == 'lecturer_session_availability.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Lecturer Sessions</a>
            <a href="course_session_availability.php" class="<?= ($currentPage == 'course_session_availability.php') ? 'active' : '' ?>"><i class="fas fa-link me-2"></i>Course Sessions</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h2>Manage Lecturers</h2>
        
        <!-- Search Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control search-input" id="searchInput" placeholder="Search lecturers...">
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#lecturerModal">
                    <i class="fas fa-plus me-2"></i>Add New Lecturer
                </button>
            </div>
        </div>
        
        <div class="row">
            <!-- Display Lecturers -->
            <div class="col-md-12">
                <div class="table-container">
                    <div class="table-header">
                        <h4><i class="fas fa-chalkboard-teacher me-2"></i>Existing Lecturers</h4>
                    </div>
                    <?php if (empty($lecturers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h5>No lecturers found</h5>
                            <p>Start by adding your first lecturer using the button above.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" id="lecturersTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Specialization</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lecturers as $lecturer): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($lecturer['name']); ?></strong>
                                                    <?php if ($lecturer['is_active'] == 0): ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($lecturer['department_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($lecturer['department_code']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($lecturer['specialization']): ?>
                                                        <?php echo htmlspecialchars($lecturer['specialization']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editLecturer(<?php echo $lecturer['id']; ?>, '<?php echo htmlspecialchars($lecturer['name']); ?>', <?php echo $lecturer['department_id']; ?>, '<?php echo htmlspecialchars($lecturer['specialization']); ?>', <?php echo $lecturer['max_daily_courses']; ?>, <?php echo $lecturer['max_weekly_hours']; ?>, <?php echo $lecturer['max_sessions_per_week']; ?>, '<?php echo $lecturer['preferred_days']; ?>', '<?php echo htmlspecialchars($lecturer['unavailable_times']); ?>', <?php echo $lecturer['is_active']; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteLecturer(<?php echo $lecturer['id']; ?>, '<?php echo htmlspecialchars($lecturer['name']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer" id="footer">
        &copy; 2025 University Timetable Generator
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
        
        // Search functionality for lecturers table
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#lecturersTable tbody tr');
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
        
        // Edit lecturer function
        function editLecturer(id, name, departmentId, specialization, maxDaily, maxWeekly, maxSessions, preferredDays, unavailableTimes, isActive) {
            // Set form to edit mode
            document.getElementById('action').value = 'update';
            document.getElementById('lecturerId').value = id;
            document.getElementById('lecturerName').value = name;
            document.getElementById('departmentId').value = departmentId;
            document.getElementById('specialization').value = specialization;
            document.getElementById('maxDailyCourses').value = maxDaily;
            document.getElementById('maxWeeklyHours').value = maxWeekly;
            document.getElementById('maxSessionsPerWeek').value = maxSessions;
            document.getElementById('unavailableTimes').value = unavailableTimes;
            document.getElementById('isActive').checked = isActive == 1;
            
            // Clear and set preferred days
            document.querySelectorAll('input[name="preferredDays[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (preferredDays && preferredDays !== '[]') {
                try {
                    const days = JSON.parse(preferredDays);
                    days.forEach(day => {
                        const checkbox = document.getElementById('day_' + day);
                        if (checkbox) checkbox.checked = true;
                    });
                } catch (e) {
                    console.error('Error parsing preferred days:', e);
                }
            }
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Edit Lecturer';
            document.getElementById('submitBtn').textContent = 'Update Lecturer';
            document.getElementById('submitBtn').className = 'btn btn-warning';
            document.getElementById('cancelBtn').style.display = 'block';
            document.getElementById('formMode').style.display = 'block';
            
            // Scroll to form
            document.getElementById('lecturerForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Cancel edit function
        function cancelEdit() {
            // Reset form to add mode
            document.getElementById('action').value = 'add';
            document.getElementById('lecturerForm').reset();
            document.getElementById('lecturerId').value = '';
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Add New Lecturer';
            document.getElementById('submitBtn').textContent = 'Add Lecturer';
            document.getElementById('submitBtn').className = 'btn btn-primary';
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('formMode').style.display = 'none';
        }
        
        // Delete lecturer function
        function deleteLecturer(id, name) {
            if (confirm(`Are you sure you want to delete the lecturer "${name}"?`)) {
                // Create a form to submit the deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
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
