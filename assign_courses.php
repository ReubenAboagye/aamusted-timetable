<?php
include 'connect.php';

// Page title and layout includes
$pageTitle = 'Assign Courses to Class';
include 'includes/header.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'assign_courses') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $course_ids = $_POST['course_ids'] ?? [];
        
        if ($class_id > 0 && !empty($course_ids)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id) VALUES (?, ?)");
            
            foreach ($course_ids as $course_id) {
                $stmt->bind_param('ii', $class_id, $course_id);
                $stmt->execute();
            }
            $stmt->close();
            redirect_with_flash('assign_courses.php', 'success', 'Courses assigned to class successfully!');
        } else {
            $error_message = "Please select both class and courses.";
        }
    }
}

// Get all classes
$classes_sql = "SELECT id, name, level FROM classes WHERE is_active = 1 ORDER BY name";
$classes_result = $conn->query($classes_sql);

// Get all courses
$courses_sql = "SELECT id, course_code, course_name FROM courses WHERE is_active = 1 ORDER BY course_code";
$courses_result = $conn->query($courses_sql);

// Get existing assignments for selected class
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$existing_assignments = [];

if ($selected_class_id > 0) {
    $existing_sql = "SELECT course_id FROM class_courses WHERE class_id = ? AND is_active = 1";
    $existing_stmt = $conn->prepare($existing_sql);
    $existing_stmt->bind_param('i', $selected_class_id);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();
    
    while ($row = $existing_result->fetch_assoc()) {
        $existing_assignments[] = $row['course_id'];
    }
    $existing_stmt->close();
}
?>

<!-- Bootstrap CSS and JS are included globally in includes/header.php -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .course-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .course-item:hover {
            background-color: #f8f9fa;
        }
        .course-item.selected {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>Assign Courses to Class
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="class_id" class="form-label">Select Class</label>
                                    <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Choose a class...</option>
                                        <?php if ($classes_result): ?>
                                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                                                <option value="<?php echo $class['id']; ?>" 
                                                        <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['name'] . ' (' . $class['level'] . ')'); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </form>

                        <?php if ($selected_class_id > 0): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Available Courses</h6>
                                        </div>
                                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                            <?php if ($courses_result): ?>
                                                <?php while ($course = $courses_result->fetch_assoc()): ?>
                                                    <?php $is_assigned = in_array($course['id'], $existing_assignments); ?>
                                                    <div class="course-item p-2 border rounded mb-2 <?php echo $is_assigned ? 'selected' : ''; ?>"
                                                         data-course-id="<?php echo $course['id']; ?>"
                                                         onclick="toggleCourse(this, <?php echo $course['id']; ?>)">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($course['course_code']); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($course['course_name']); ?></small>
                                                            </div>
                                                            <div>
                                                                <?php if ($is_assigned): ?>
                                                                    <span class="badge bg-success">Assigned</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">Available</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <p class="text-muted">No courses available.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Selected Courses</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="selectedCoursesList">
                                                <p class="text-muted">Click on courses to select them for assignment.</p>
                                            </div>
                                            
                                            <form method="POST" id="assignmentForm">
                                                <input type="hidden" name="action" value="assign_courses">
                                                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                                                <div id="selectedCourseIds"></div>
                                                
                                                <button type="submit" class="btn btn-primary w-100" id="assignBtn" disabled>
                                                    <i class="fas fa-save me-1"></i>Assign Selected Courses
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-hand-point-up fa-3x mb-3"></i>
                                <h5>Select a class to assign courses</h5>
                                <p>Choose a class from the dropdown above to start assigning courses.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedCourses = new Set();
        
        function toggleCourse(element, courseId) {
            if (selectedCourses.has(courseId)) {
                selectedCourses.delete(courseId);
                element.classList.remove('selected');
            } else {
                selectedCourses.add(courseId);
                element.classList.add('selected');
            }
            
            updateSelectedCoursesList();
            updateAssignButton();
        }
        
        function updateSelectedCoursesList() {
            const container = document.getElementById('selectedCoursesList');
            const courseIdsContainer = document.getElementById('selectedCourseIds');
            
            if (selectedCourses.size === 0) {
                container.innerHTML = '<p class="text-muted">Click on courses to select them for assignment.</p>';
                courseIdsContainer.innerHTML = '';
                return;
            }
            
            let html = '<div class="mb-3">';
            courseIdsContainer.innerHTML = '';
            
            selectedCourses.forEach(courseId => {
                // Find course details from the available courses
                const courseElement = document.querySelector(`[data-course-id="${courseId}"]`);
                const courseCode = courseElement.querySelector('strong').textContent;
                const courseName = courseElement.querySelector('small').textContent;
                
                html += `
                    <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                        <div>
                            <strong>${courseCode}</strong><br>
                            <small class="text-muted">${courseName}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeCourse(${courseId})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                // Add hidden input for form submission
                courseIdsContainer.innerHTML += `<input type="hidden" name="course_ids[]" value="${courseId}">`;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function removeCourse(courseId) {
            selectedCourses.delete(courseId);
            const courseElement = document.querySelector(`[data-course-id="${courseId}"]`);
            courseElement.classList.remove('selected');
            updateSelectedCoursesList();
            updateAssignButton();
        }
        
        function updateAssignButton() {
            const assignBtn = document.getElementById('assignBtn');
            assignBtn.disabled = selectedCourses.size === 0;
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCoursesList();
            updateAssignButton();
        });
    </script>
</body>
</html>
