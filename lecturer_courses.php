<?php
$pageTitle = 'Map Courses to Lecturers';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'add') {
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
    } elseif ($action === 'bulk_add') {
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

// Get filter parameters
$selected_department = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// Data for UI
$departments = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
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

// Build mappings query with filters
$mappings_query = "SELECT l.id as lecturer_id, l.name AS lecturer_name, 
                   d.name AS department_name,
                   GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ') AS course_codes
                   FROM lecturers l
                   LEFT JOIN departments d ON l.department_id = d.id
                   LEFT JOIN lecturer_courses lc ON l.id = lc.lecturer_id
                   LEFT JOIN courses c ON c.id = lc.course_id
                   WHERE l.is_active = 1";

$params = [];
$types = '';

// Add department filter
if ($selected_department > 0) {
    $mappings_query .= " AND l.department_id = ?";
    $params[] = $selected_department;
    $types .= 'i';
}

// Add name search filter
if (!empty($search_name)) {
    $mappings_query .= " AND l.name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $types .= 's';
}

$mappings_query .= " GROUP BY l.id, l.name, d.name ORDER BY l.name";

// Execute the query with parameters if any
if (!empty($params)) {
    $stmt = $conn->prepare($mappings_query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $mappings = $stmt->get_result();
        $stmt->close();
    } else {
        $mappings = false;
        error_log('mappings query prepare failed: ' . $conn->error);
    }
} else {
    $mappings = $conn->query($mappings_query);
}
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

        <!-- Filter Section -->
        <div class="card m-3">
            <div class="card-body">
                <form method="GET" action="lecturer_courses.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="department_id" class="form-label">Filter by Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="0">All Departments</option>
                            <?php if ($departments && $departments->num_rows > 0): ?>
                                <?php while ($dept = $departments->fetch_assoc()): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($selected_department == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search_name" class="form-label">Search by Lecturer Name</label>
                        <input type="text" class="form-control" id="search_name" name="search_name" 
                               value="<?php echo htmlspecialchars($search_name); ?>" 
                               placeholder="Enter lecturer name...">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="lecturer_courses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive m-3">
            <table class="table">
                <thead>
                    <tr>
                        <th>Lecturer</th>
                        <th>Department</th>
                        <th>Course</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mappings && $mappings->num_rows > 0): ?>
                        <?php while ($m = $mappings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['lecturer_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($m['department_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($m['course_codes'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editMapping(<?php echo $m['lecturer_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>No mappings found. <?php if (!empty($search_name) || $selected_department > 0): ?>Try adjusting your filters.<?php else: ?>Add some mappings above.<?php endif; ?></p>
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
    
    // Show loading state
    const availableList = document.getElementById('availableCoursesList');
    const assignedList = document.getElementById('assignedCoursesList');
    availableList.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading courses...</div>';
    assignedList.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading courses...</div>';
    
    // Fetch courses from database
    fetch(`get_lecturer_courses.php?lecturer_id=${lecturerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                availableCourses = data.data.available_courses;
                assignedCourses = data.data.assigned_courses;
                populateCourseLists();
            } else {
                throw new Error(data.error || 'Failed to fetch courses');
            }
        })
        .catch(error => {
            console.error('Error fetching courses:', error);
            availableList.innerHTML = '<div class="text-center p-3 text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading courses</div>';
            assignedList.innerHTML = '<div class="text-center p-3 text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading courses</div>';
        });
    
    // Use Bootstrap 5 modal methods instead of jQuery
    const el = document.getElementById('editModal');
    if (!el) return console.error('editModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el).show();
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
    
    // Show loading state
    const saveButton = document.querySelector('#editModal .btn-primary');
    const originalText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveButton.disabled = true;
    
    // Send data to server
    fetch('save_lecturer_courses.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            lecturer_id: currentLecturerId,
            assigned_course_ids: courseIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
            modal.hide();
            
            // Show success alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show m-3';
            alertDiv.innerHTML = `
                ${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.table-container').insertBefore(alertDiv, document.querySelector('.table-responsive'));
            
            // Reload the page after a short delay to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data.error || 'Failed to save assignments');
        }
    })
    .catch(error => {
        console.error('Error saving assignments:', error);
        alert('Error saving assignments: ' + error.message);
    })
    .finally(() => {
        // Restore button state
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
    });
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

