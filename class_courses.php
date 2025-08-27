<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new class-course assignment
                $classId = $_POST['classId'];
                $courseId = $_POST['courseId'];
                $semesterId = $_POST['semesterId'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if this assignment already exists
                $checkSql = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND semester_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iii", $classId, $courseId, $semesterId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This course is already assigned to this class for the selected semester!');</script>";
                } else {
                    $sql = "INSERT INTO class_courses (class_id, course_id, semester_id, is_active) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("iiii", $classId, $courseId, $semesterId, $isActive);
                        if ($stmt->execute()) {
                            echo "<script>alert('Course assigned to class successfully!'); window.location.href='class_courses.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing class-course assignment
                $id = $_POST['id'];
                $classId = $_POST['classId'];
                $courseId = $_POST['courseId'];
                $semesterId = $_POST['semesterId'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if this assignment already exists for other records
                $checkSql = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND semester_id = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iiii", $classId, $courseId, $semesterId, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This course is already assigned to this class for the selected semester!');</script>";
                } else {
                    $sql = "UPDATE class_courses SET class_id = ?, course_id = ?, semester_id = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("iiiii", $classId, $courseId, $semesterId, $isActive, $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Assignment updated successfully!'); window.location.href='class_courses.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete class-course assignment
                $id = $_POST['id'];
                
                // Check if assignment has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM timetable WHERE class_course_id = ?) as timetable_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['timetable_count'] > 0) {
                    echo "<script>alert('Cannot delete assignment: It has dependent timetables. Consider deactivating instead.');</script>";
                } else {
                    $sql = "DELETE FROM class_courses WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Assignment deleted successfully!'); window.location.href='class_courses.php';</script>";
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

// Fetch existing class-course assignments for display
$classCourses = [];
$sql = "SELECT cc.*, c.name as class_name, c.level as class_level, 
               co.code as course_code, co.name as course_name, co.credits,
               s.name as semester_name, s.start_date, s.end_date,
               d.name as department_name
        FROM class_courses cc 
        JOIN classes c ON cc.class_id = c.id 
        JOIN courses co ON cc.course_id = co.id 
        JOIN semesters s ON cc.semester_id = s.id
        JOIN departments d ON c.department_id = d.id
        ORDER BY s.start_date DESC, c.name, co.code";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classCourses[] = $row;
    }
}

// Fetch classes for dropdown
$classes = [];
$sql = "SELECT c.id, c.name, c.level, d.name as department_name, d.code as department_code 
        FROM classes c 
        JOIN departments d ON c.department_id = d.id 
        WHERE c.is_active = 1 
        ORDER BY c.name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Fetch courses for dropdown
$courses = [];
$sql = "SELECT id, code, name, credits, level FROM courses WHERE is_active = 1 ORDER BY code";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fetch semesters for dropdown
$semesters = [];
$sql = "SELECT id, name, start_date, end_date FROM semesters WHERE is_active = 1 ORDER BY start_date DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class-Course Assignments - University Timetable Generator</title>
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
<?php $pageTitle = 'Manage Class-Course Assignments'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h2>Manage Class-Course Assignments</h2>
        
        <!-- Search Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search assignments...">
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add New Assignment</button>
            </div>
        </div>
        
        <div class="row">
        <div class="row">
            <!-- Add Class-Course Assignment Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 id="formHeader">Assign Course to Class</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="classCourseForm">
                            <input type="hidden" id="action" name="action" value="add">
                            <input type="hidden" id="assignmentId" name="id" value="">
                            
                            <div class="alert alert-info" id="formMode" style="display: none;">
                                <strong>Edit Mode:</strong> You are currently editing an existing assignment. Click "Cancel Edit" to return to add mode.
                            </div>
                            <div class="mb-3">
                                <label for="classId" class="form-label">Class *</label>
                                <select class="form-select" id="classId" name="classId" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?> (<?php echo htmlspecialchars($class['level']); ?>) - <?php echo htmlspecialchars($class['department_code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the class to assign a course to</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="courseId" class="form-label">Course *</label>
                                <select class="form-select" id="courseId" name="courseId" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?> (<?php echo $course['credits']; ?> credits, Level <?php echo $course['level']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the course to assign</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="semesterId" class="form-label">Semester *</label>
                                <select class="form-select" id="semesterId" name="semesterId" required>
                                    <option value="">Select Semester</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['id']; ?>">
                                            <?php echo htmlspecialchars($semester['name']); ?> (<?php echo date('M d', strtotime($semester['start_date'])); ?> - <?php echo date('M d, Y', strtotime($semester['end_date'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the semester for this assignment</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                                    <label class="form-check-label" for="isActive">
                                        Assignment is Active
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Assign Course to Class</button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Display Class-Course Assignments -->
            <div class="col-md-6">
                <div class="table-container">
                    <div class="table-header">
                        <h4><i class="fas fa-link me-2"></i>Existing Assignments</h4>
                    </div>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search assignments...">
                    </div>
                    <?php if (empty($classCourses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-link"></i>
                            <h5>No course assignments found</h5>
                            <p>Start by assigning courses to classes using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" id="classCoursesTable">
                                    <thead>
                                        <tr>
                                            <th>Class</th>
                                            <th>Course</th>
                                            <th>Semester</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classCourses as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['class_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['class_level']); ?> - <?php echo htmlspecialchars($assignment['department_name']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['course_name']); ?> (<?php echo $assignment['credits']; ?> credits)</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($assignment['semester_name']); ?></span><br>
                                                    <small class="text-muted"><?php echo date('M d', strtotime($assignment['start_date'])); ?> - <?php echo date('M d', strtotime($assignment['end_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['id']; ?>, <?php echo $assignment['class_id']; ?>, <?php echo $assignment['course_id']; ?>, <?php echo $assignment['semester_id']; ?>, <?php echo $assignment['is_active']; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['class_name']); ?> - <?php echo htmlspecialchars($assignment['course_code']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body p-2">
                                                <h6 class="mb-0"><?php echo count(array_filter($classCourses, function($a) { return $a['is_active']; })); ?></h6>
                                                <small>Active</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-info text-white">
                                            <div class="card-body p-2">
                                                <h6 class="mb-0"><?php echo count(array_unique(array_column($classCourses, 'class_id'))); ?></h6>
                                                <small>Classes</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-success text-white">
                                            <div class="card-body p-2">
                                                <h6 class="mb-0"><?php echo count(array_unique(array_column($classCourses, 'course_id'))); ?></h6>
                                                <small>Courses</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
    
        </div>
    </div>
    
<?php include 'includes/footer.php'; ?>
    
    <script>
        function editAssignment(id, classId, courseId, semesterId, isActive) {
            // Set form to edit mode
            document.getElementById('action').value = 'update';
            document.getElementById('assignmentId').value = id;
            document.getElementById('classId').value = classId;
            document.getElementById('courseId').value = courseId;
            document.getElementById('semesterId').value = semesterId;
            document.getElementById('isActive').checked = isActive == 1;
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Edit Assignment';
            document.getElementById('submitBtn').textContent = 'Update Assignment';
            document.getElementById('submitBtn').className = 'btn btn-warning';
            document.getElementById('cancelBtn').style.display = 'block';
            document.getElementById('formMode').style.display = 'block';
            
            // Scroll to form
            document.getElementById('classCourseForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelEdit() {
            // Reset form to add mode
            document.getElementById('action').value = 'add';
            document.getElementById('assignmentId').value = '';
            document.getElementById('classCourseForm').reset();
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Assign Course to Class';
            document.getElementById('submitBtn').textContent = 'Assign Course to Class';
            document.getElementById('submitBtn').className = 'btn btn-primary';
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('formMode').style.display = 'none';
            
            // Reset default values
            document.getElementById('isActive').checked = true;
        }
        
        function deleteAssignment(id, assignmentName) {
            if (confirm(`Are you sure you want to delete the assignment "${assignmentName}"?`)) {
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
        
        // Search functionality for class courses table
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#classCoursesTable tbody tr');
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

