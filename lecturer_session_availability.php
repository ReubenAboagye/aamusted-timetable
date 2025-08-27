<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new lecturer-session availability
                $lecturerId = $_POST['lecturerId'];
                $sessionId = $_POST['sessionId'];

                // Check if this assignment already exists
                $checkSql = "SELECT lecturer_id FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $lecturerId, $sessionId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This lecturer is already available in the selected session!');</script>";
                } else {
                    $sql = "INSERT INTO lecturer_session_availability (lecturer_id, session_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ii", $lecturerId, $sessionId);
                        if ($stmt->execute()) {
                            echo "<script>alert('Lecturer assigned to session successfully!'); window.location.href='lecturer_session_availability.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing lecturer-session availability
                $oldLecturerId = $_POST['oldLecturerId'];
                $oldSessionId = $_POST['oldSessionId'];
                $newLecturerId = $_POST['lecturerId'];
                $newSessionId = $_POST['sessionId'];

                // Check if the new combination already exists
                $checkSql = "SELECT lecturer_id FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $newLecturerId, $newSessionId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0 && ($oldLecturerId != $newLecturerId || $oldSessionId != $newSessionId)) {
                    echo "<script>alert('This lecturer is already available in the selected session!');</script>";
                } else {
                    // Delete old record and insert new one (since it's a composite key table)
                    $deleteSql = "DELETE FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
                    $deleteStmt = $conn->prepare($deleteSql);
                    if ($deleteStmt) {
                        $deleteStmt->bind_param("ii", $oldLecturerId, $oldSessionId);
                        if ($deleteStmt->execute()) {
                            // Insert new record
                            $insertSql = "INSERT INTO lecturer_session_availability (lecturer_id, session_id) VALUES (?, ?)";
                            $insertStmt = $conn->prepare($insertSql);
                            if ($insertStmt) {
                                $insertStmt->bind_param("ii", $newLecturerId, $newSessionId);
                                if ($insertStmt->execute()) {
                                    echo "<script>alert('Availability updated successfully!'); window.location.href='lecturer_session_availability.php';</script>";
                                } else {
                                    echo "Error: " . $insertStmt->error;
                                }
                                $insertStmt->close();
                            }
                        } else {
                            echo "Error: " . $deleteStmt->error;
                        }
                        $deleteStmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete lecturer-session availability
                $lecturerId = $_POST['lecturerId'];
                $sessionId = $_POST['sessionId'];
                
                // Check if assignment has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM timetable WHERE lecturer_id = ? AND session_id = ?) as timetable_count,
                    (SELECT COUNT(*) FROM timetable_lecturers WHERE lecturer_id = ?) as team_teaching_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iii", $lecturerId, $sessionId, $lecturerId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['timetable_count'] > 0 || $dependencies['team_teaching_count'] > 0) {
                    echo "<script>alert('Cannot delete availability: It has dependent timetables or team teaching assignments. Consider removing timetables first.');</script>";
                } else {
                    $sql = "DELETE FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ii", $lecturerId, $sessionId);
                        if ($stmt->execute()) {
                            echo "<script>alert('Availability removed successfully!'); window.location.href='lecturer_session_availability.php';</script>";
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

// Fetch existing lecturer-session assignments for display
$lecturerSessions = [];
$sql = "SELECT lsa.*, l.name as lecturer_name, l.rank,
               CONCAT(s.academic_year, ' - Semester ', s.semester) as session_name, 
               'Regular' as session_type, s.start_date as start_time, s.end_date as end_time,
               d.name as department_name, d.code as department_code
        FROM lecturer_session_availability lsa 
        JOIN lecturers l ON lsa.lecturer_id = l.id 
        JOIN sessions s ON lsa.session_id = s.id 
        JOIN departments d ON l.department_id = d.id
        ORDER BY l.name, s.academic_year, s.semester";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lecturerSessions[] = $row;
    }
}

// Fetch lecturers for dropdown
$lecturers = [];
$sql = "SELECT l.id, l.name, l.rank,
               d.name as department_name, d.code as department_code 
        FROM lecturers l 
        JOIN departments d ON l.department_id = d.id 
        WHERE l.is_active = 1 
        ORDER BY l.name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = $row;
    }
}

// Fetch sessions for dropdown
$sessions = [];
$sql = "SELECT id, CONCAT(academic_year, ' - Semester ', semester) as name, 
               'Regular' as type, start_date as start_time, end_date as end_time 
        FROM sessions WHERE is_active = 1 ORDER BY academic_year, semester";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
}

// Get statistics
$totalAssignments = count($lecturerSessions);
$uniqueLecturers = count(array_unique(array_column($lecturerSessions, 'lecturer_id')));
$uniqueSessions = count(array_unique(array_column($lecturerSessions, 'session_id')));

// Group by session type for better analysis
$sessionTypeCounts = [];
foreach ($lecturerSessions as $assignment) {
    $type = $assignment['session_type'];
    if (!isset($sessionTypeCounts[$type])) {
        $sessionTypeCounts[$type] = 0;
    }
    $sessionTypeCounts[$type]++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lecturer Session Availability - University Timetable Generator</title>
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
<?php $pageTitle = 'Manage Lecturer Session Availability'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h2>Manage Lecturer Session Availability</h2>
        
        <!-- Search Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search availability...">
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add New Availability</button>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $totalAssignments; ?></h4>
                        <small>Total Assignments</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $uniqueLecturers; ?></h4>
                        <small>Lecturers Available</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $uniqueSessions; ?></h4>
                        <small>Sessions Covered</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo count($sessionTypeCounts); ?></h4>
                        <small>Session Types</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Type Breakdown -->
        <?php if (!empty($sessionTypeCounts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Session Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($sessionTypeCounts as $type => $count): ?>
                                <div class="col-md-2 mb-2">
                                    <div class="card bg-light">
                                        <div class="card-body text-center p-2">
                                            <h6 class="mb-0"><?php echo ucfirst($type); ?></h6>
                                            <small class="text-muted"><?php echo $count; ?> lecturers</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Lecturer-Session Assignment Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 id="formHeader">Assign Lecturer to Session</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="lecturerSessionForm">
                            <input type="hidden" id="action" name="action" value="add">
                            <input type="hidden" id="oldLecturerId" name="oldLecturerId" value="">
                            <input type="hidden" id="oldSessionId" name="oldSessionId" value="">
                            
                            <div class="alert alert-info" id="formMode" style="display: none;">
                                <strong>Edit Mode:</strong> You are currently editing an existing assignment. Click "Cancel Edit" to return to add mode.
                            </div>
                            <div class="mb-3">
                                <label for="lecturerId" class="form-label">Lecturer *</label>
                                <select class="form-select" id="lecturerId" name="lecturerId" required>
                                    <option value="">Select Lecturer</option>
                                    <?php foreach ($lecturers as $lecturer): ?>
                                        <option value="<?php echo $lecturer['id']; ?>">
                                            <?php echo htmlspecialchars($lecturer['name']); ?> 
                                            <?php if ($lecturer['rank']): ?>
                            (<?php echo htmlspecialchars($lecturer['rank']); ?>)
                        <?php endif; ?>
                                            - <?php echo htmlspecialchars($lecturer['department_code']); ?>
                                            <br><small class="text-muted">
                                                Department: <?php echo htmlspecialchars($lecturer['department_name']); ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the lecturer to make available in a session</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sessionId" class="form-label">Session *</label>
                                <select class="form-select" id="sessionId" name="sessionId" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>">
                                            <?php echo htmlspecialchars($session['name']); ?> (<?php echo ucfirst($session['type']); ?>)<br>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the session this lecturer will be available in</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Assign Lecturer to Session</button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Display Lecturer-Session Assignments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="table-header">
                        <h4><i class="fas fa-link me-2"></i>Existing Assignments</h4>
                    </div>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search assignments...">
                    </div>
                    <?php if (empty($lecturerSessions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-link"></i>
                                <h5>No session assignments found</h5>
                                <p>Start by assigning lecturers to sessions using the form on the left.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table" id="lecturerSessionTable">
                                    <thead>
                                        <tr>
                                            <th>Lecturer</th>
                                            <th>Session</th>
                                            <th>Constraints</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lecturerSessions as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['lecturer_name']); ?></strong><br>
                                                    <?php if ($assignment['rank']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($assignment['rank']); ?></small><br>
                            <?php endif; ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['department_name']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($assignment['session_name']); ?></span><br>
                                                    <small class="text-muted"><?php echo ucfirst($assignment['session_type']); ?></small><br>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($assignment['start_time'])); ?> - <?php echo date('g:i A', strtotime($assignment['end_time'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong>Department:</strong> <?php echo htmlspecialchars($assignment['department_name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['lecturer_id']; ?>, <?php echo $assignment['session_id']; ?>, '<?php echo htmlspecialchars($assignment['lecturer_name']); ?>', '<?php echo htmlspecialchars($assignment['session_name']); ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['lecturer_id']; ?>, <?php echo $assignment['session_id']; ?>, '<?php echo htmlspecialchars($assignment['lecturer_name']); ?> - <?php echo htmlspecialchars($assignment['session_name']); ?>')">Delete</button>
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
        
        <div class="mt-3">
    
        </div>
    </div>
    
<?php include 'includes/footer.php'; ?>
    
    <script>
        function editAssignment(lecturerId, sessionId, lecturerName, sessionName) {
            // Set form to edit mode
            document.getElementById('action').value = 'update';
            document.getElementById('oldLecturerId').value = lecturerId;
            document.getElementById('oldSessionId').value = sessionId;
            document.getElementById('lecturerId').value = lecturerId;
            document.getElementById('sessionId').value = sessionId;
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Edit Assignment';
            document.getElementById('submitBtn').textContent = 'Update Assignment';
            document.getElementById('submitBtn').className = 'btn btn-warning';
            document.getElementById('cancelBtn').style.display = 'block';
            document.getElementById('formMode').style.display = 'block';
            
            // Scroll to form
            document.getElementById('lecturerSessionForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelEdit() {
            // Reset form to add mode
            document.getElementById('action').value = 'add';
            document.getElementById('oldLecturerId').value = '';
            document.getElementById('oldSessionId').value = '';
            document.getElementById('lecturerSessionForm').reset();
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Assign Lecturer to Session';
            document.getElementById('submitBtn').textContent = 'Assign Lecturer to Session';
            document.getElementById('submitBtn').className = 'btn btn-primary';
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('formMode').style.display = 'none';
        }
        
        function deleteAssignment(lecturerId, sessionId, assignmentName) {
            if (confirm(`Are you sure you want to delete the assignment "${assignmentName}"?`)) {
                // Create a form to submit the deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const lecturerInput = document.createElement('input');
                lecturerInput.type = 'hidden';
                lecturerInput.name = 'lecturerId';
                lecturerInput.value = lecturerId;
                
                const sessionInput = document.createElement('input');
                sessionInput.type = 'hidden';
                sessionInput.name = 'sessionId';
                sessionInput.value = sessionId;
                
                form.appendChild(actionInput);
                form.appendChild(lecturerInput);
                form.appendChild(sessionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
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
        
        // Search functionality for lecturer session table
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#lecturerSessionTable tbody tr');
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

