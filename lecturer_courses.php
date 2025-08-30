<?php
$pageTitle = 'Map Courses to Lecturers';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $lecturer_id = (int)($_POST['lecturer_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        if ($lecturer_id > 0 && $course_id > 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('ii', $lecturer_id, $course_id);
                if ($stmt->execute()) { $stmt->close(); redirect_with_flash('lecturer_courses.php', 'success', 'Mapping added.'); } else { $error_message = 'Insert failed: ' . $conn->error; }
                $stmt->close();
            } else { $error_message = 'Prepare failed: ' . $conn->error; }
        }
    } elseif ($_POST['action'] === 'bulk_add') {
        $lecturer_id = (int)($_POST['lecturer_id'] ?? 0);
        $course_ids = isset($_POST['course_ids']) && is_array($_POST['course_ids']) ? array_map('intval', $_POST['course_ids']) : [];
        if ($lecturer_id > 0 && !empty($course_ids)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
            if ($stmt) {
                foreach ($course_ids as $course_id) {
                    $stmt->bind_param('ii', $lecturer_id, $course_id);
                    $stmt->execute();
                }
                $stmt->close();
                redirect_with_flash('lecturer_courses.php', 'success', 'Bulk mappings added.');
            } else { $error_message = 'Prepare failed: ' . $conn->error; }
        }
    }
}

// Data for UI
$departments = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
$selected_department = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

$lecturers = $conn->query("SELECT id, name, department_id FROM lecturers WHERE is_active = 1 ORDER BY name");

// Build courses query defensively: some DB schemas include department_id on courses, others don't
$courses_query = "SELECT c.id, c.name, c.code FROM courses c WHERE c.is_active = 1 ORDER BY c.name";
$courses = $conn->query($courses_query);
if ($courses === false) {
    error_log('courses query failed: ' . $conn->error . ' -- Query: ' . $courses_query);
}
// If the above failed (schema mismatch), fall back to a simpler courses query
if ($courses === false) {
    // Log error for debugging (do not expose DB errors to users in production)
    error_log('courses query failed: ' . $conn->error . ' -- Query: ' . $courses_query);
    $courses = $conn->query("SELECT c.id, c.name, c.code, NULL AS department_name FROM courses c WHERE c.is_active = 1 ORDER BY c.name");
}
$mappings = $conn->query("SELECT l.id as lecturer_id, l.name AS lecturer_name, 
                          GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ') AS course_codes
                          FROM lecturers l
                          LEFT JOIN lecturer_courses lc ON l.id = lc.lecturer_id
                          LEFT JOIN courses c ON c.id = lc.course_id
                          WHERE l.is_active = 1
                          GROUP BY l.id, l.name
                          ORDER BY l.name");
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-user-plus me-2"></i>Map Courses to Lecturers</h4>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive m-3">
            <table class="table">
                <thead>
                    <tr>
                        <th>Lecturer</th>
                        <th>Course</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mappings && $mappings->num_rows > 0): ?>
                        <?php while ($m = $mappings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['lecturer_name']); ?></td>
                                <td><?php echo htmlspecialchars($m['course_codes']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editMapping(<?php echo $m['lecturer_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>No mappings yet. Add some above.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Course Assignment Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Course Assignments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Available Courses (Left Side) -->
                    <div class="col-md-6">
                        <h6>Available Courses</h6>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="courseSearch" placeholder="Search courses...">
                        </div>
                        <div class="border rounded p-2" style="height: 300px; overflow-y: auto;">
                            <div id="availableCoursesList">
                                <!-- Available courses will be populated here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assigned Courses (Right Side) -->
                    <div class="col-md-6">
                        <h6>Assigned Courses</h6>
                        <div class="border rounded p-2" style="height: 300px; overflow-y: auto;">
                            <div id="assignedCoursesList">
                                <!-- Assigned courses will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAssignments()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentLecturerId = null;
let availableCourses = [];
let assignedCourses = [];

function editMapping(lecturerId) {
    currentLecturerId = lecturerId;
    
    // Dummy data for testing - remove this when backend is ready
    availableCourses = [
        { id: '1', code: 'CS101', name: 'Introduction to Computer Science' },
        { id: '2', code: 'CS102', name: 'Programming Fundamentals' },
        { id: '3', code: 'CS201', name: 'Data Structures' },
        { id: '4', code: 'CS202', name: 'Algorithms' },
        { id: '5', code: 'CS301', name: 'Database Systems' },
        { id: '6', code: 'CS302', name: 'Software Engineering' },
        { id: '7', code: 'MATH101', name: 'Calculus I' },
        { id: '8', code: 'MATH102', name: 'Calculus II' },
        { id: '9', code: 'PHYS101', name: 'Physics I' },
        { id: '10', code: 'PHYS102', name: 'Physics II' }
    ];
    
    assignedCourses = [
        { id: '1', code: 'CS101', name: 'Introduction to Computer Science' },
        { id: '3', code: 'CS201', name: 'Data Structures' }
    ];
    
    populateCourseLists();
    
    // Use Bootstrap 5 modal methods instead of jQuery
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

function populateCourseLists() {
    // Populate available courses
    const availableList = document.getElementById('availableCoursesList');
    availableList.innerHTML = '';
    
    availableCourses.forEach(course => {
        const courseDiv = document.createElement('div');
        courseDiv.className = 'course-item p-2 border-bottom';
        courseDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <span>${course.code} - ${course.name}</span>
                <button class="btn btn-sm btn-outline-primary" onclick="assignCourse('${course.id}')">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        `;
        availableList.appendChild(courseDiv);
    });
    
    // Populate assigned courses
    const assignedList = document.getElementById('assignedCoursesList');
    assignedList.innerHTML = '';
    
    assignedCourses.forEach(course => {
        const courseDiv = document.createElement('div');
        courseDiv.className = 'course-item p-2 border-bottom';
        courseDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <span>${course.code} - ${course.name}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="unassignCourse('${course.id}')">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        `;
        assignedList.appendChild(courseDiv);
    });
}

function assignCourse(courseId) {
    const course = availableCourses.find(c => c.id === courseId);
    if (course) {
        assignedCourses.push(course);
        availableCourses = availableCourses.filter(c => c.id !== courseId);
        populateCourseLists();
    }
}

function unassignCourse(courseId) {
    const course = assignedCourses.find(c => c.id === courseId);
    if (course) {
        availableCourses.push(course);
        assignedCourses = assignedCourses.filter(c => c.id !== courseId);
        populateCourseLists();
    }
}

function saveAssignments() {
    const courseIds = assignedCourses.map(c => c.id);
    
    // Dummy save for testing - remove this when backend is ready
    alert(`Changes saved! Assigned courses: ${assignedCourses.map(c => c.code).join(', ')}`);
    
    // Use Bootstrap 5 modal methods instead of jQuery
    const modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
    modal.hide();
}

// Course search functionality
document.getElementById('courseSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const courseItems = document.querySelectorAll('#availableCoursesList .course-item');
    
    courseItems.forEach(item => {
        const courseText = item.textContent.toLowerCase();
        if (courseText.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>

