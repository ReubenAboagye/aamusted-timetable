<?php
$pageTitle = 'Map Courses to Classes ';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        $session_id = (int)($_POST['session_id'] ?? 0);
        if ($class_id > 0 && $course_id > 0 && $session_id > 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id, session_id) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('iii', $class_id, $course_id, $session_id);
                if ($stmt->execute()) { $success_message = 'Mapping added.'; } else { $error_message = 'Insert failed: ' . $conn->error; }
                $stmt->close();
            } else { $error_message = 'Prepare failed: ' . $conn->error; }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM class_courses WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) { $success_message = 'Mapping removed.'; } else { $error_message = 'Delete failed: ' . $conn->error; }
                $stmt->close();
            } else { $error_message = 'Prepare failed: ' . $conn->error; }
        }
    } elseif ($_POST['action'] === 'bulk_add') {
        $session_id = (int)($_POST['session_id'] ?? 0);
        $class_ids = isset($_POST['class_ids']) && is_array($_POST['class_ids']) ? array_map('intval', $_POST['class_ids']) : [];
        $course_ids = isset($_POST['course_ids']) && is_array($_POST['course_ids']) ? array_map('intval', $_POST['course_ids']) : [];
        if ($session_id > 0 && !empty($class_ids) && !empty($course_ids)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id, session_id) VALUES (?, ?, ?)");
            if ($stmt) {
                foreach ($class_ids as $cid) {
                    foreach ($course_ids as $coid) {
                        $stmt->bind_param('iii', $cid, $coid, $session_id);
                        $stmt->execute();
                    }
                }
                $stmt->close();
                $success_message = 'Bulk mappings added.';
            } else {
                $error_message = 'Prepare failed: ' . $conn->error;
            }
        }
    }
}

// Data for UI
$levels = $conn->query("SELECT id, name, year_number FROM levels ORDER BY year_number");
$departments = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");

$selected_department = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$selected_level_id = isset($_GET['level_id']) ? (int)$_GET['level_id'] : 0;
$level_name = null; $level_year = null;
if ($selected_level_id > 0) {
    $stmt = $conn->prepare("SELECT name, year_number FROM levels WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $selected_level_id);
    $stmt->execute();
    $rs = $stmt->get_result();
    if ($rs && $row = $rs->fetch_assoc()) { $level_name = $row['name']; $level_year = (int)$row['year_number']; }
    $stmt->close();
}

// Filtered classes
$classes_query = "SELECT id, name FROM classes WHERE is_active = 1";
$params = [];$types = '';
if ($selected_department > 0) { $classes_query .= " AND department_id = ?"; $params[] = $selected_department; $types .= 'i'; }
if ($level_name) { $classes_query .= " AND level = ?"; $params[] = $level_name; $types .= 's'; }
$classes_query .= " ORDER BY name";
$classes_stmt = $conn->prepare($classes_query);
if ($classes_stmt && !empty($params)) { $classes_stmt->bind_param($types, ...$params); }
$classes_stmt && $classes_stmt->execute();
$classes = $classes_stmt ? $classes_stmt->get_result() : $conn->query("SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name");
if ($classes_stmt) { $classes_stmt->close(); }

// Filtered courses
$courses_query = "SELECT id, name, code FROM courses WHERE is_active = 1";
$cparams = [];$ctypes = '';
if ($selected_department > 0) { $courses_query .= " AND department_id = ?"; $cparams[] = $selected_department; $ctypes .= 'i'; }
if ($level_year) { $courses_query .= " AND level = ?"; $cparams[] = $level_year; $ctypes .= 'i'; }
$courses_query .= " ORDER BY name";
$courses_stmt = $conn->prepare($courses_query);
if ($courses_stmt && !empty($cparams)) { $courses_stmt->bind_param($ctypes, ...$cparams); }
$courses_stmt && $courses_stmt->execute();
$courses = $courses_stmt ? $courses_stmt->get_result() : $conn->query("SELECT id, name, code FROM courses WHERE is_active = 1 ORDER BY name");
if ($courses_stmt) { $courses_stmt->close(); }

$mappings = null;
// Get all active sessions to identify semester 1 and 2
$all_sessions = $conn->query("SELECT id, semester_number, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY semester_number");
$semester1_id = null;
$semester2_id = null;

if ($all_sessions) {
    while ($sess = $all_sessions->fetch_assoc()) {
        if ($sess['semester_number'] == 1) {
            $semester1_id = $sess['id'];
        } elseif ($sess['semester_number'] == 2) {
            $semester2_id = $sess['id'];
        }
    }
}

// Get classes with their courses for both semesters
$stmt = $conn->prepare("
    SELECT 
        cl.id as class_id,
        cl.name AS class_name,
        cl.department_id,
        cl.level
    FROM classes cl 
    WHERE cl.is_active = 1
    " . ($selected_department > 0 ? "AND cl.department_id = ?" : "") . "
    " . ($level_name ? "AND cl.level = ?" : "") . "
    ORDER BY cl.name
");

if ($stmt) {
    $params = [];
    $types = '';
    if ($selected_department > 0) {
        $params[] = $selected_department;
        $types .= 'i';
    }
    if ($level_name) {
        $params[] = $level_name;
        $types .= 's';
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $classes_result = $stmt->get_result();
    $stmt->close();
    
    // Build the mappings array with semester data
    $mappings = [];
    if ($classes_result) {
        while ($class = $classes_result->fetch_assoc()) {
            $class_id = $class['class_id'];
            
            // Get semester 1 courses
            $sem1_courses = [];
            if ($semester1_id) {
                $stmt = $conn->prepare("
                    SELECT co.name, co.code 
                    FROM class_courses cc
                    JOIN courses co ON co.id = cc.course_id
                    WHERE cc.class_id = ? AND cc.session_id = ?
                    ORDER BY co.name
                ");
                $stmt->bind_param('ii', $class_id, $semester1_id);
                $stmt->execute();
                $sem1_result = $stmt->get_result();
                while ($course = $sem1_result->fetch_assoc()) {
                    $sem1_courses[] = $course['name'] . ' (' . $course['code'] . ')';
                }
                $stmt->close();
            }
            
            // Get semester 2 courses
            $sem2_courses = [];
            if ($semester2_id) {
                $stmt = $conn->prepare("
                    SELECT co.name, co.code 
                    FROM class_courses cc
                    JOIN courses co ON co.id = cc.course_id
                    WHERE cc.class_id = ? AND cc.session_id = ?
                    ORDER BY co.name
                ");
                $stmt->bind_param('ii', $class_id, $semester2_id);
                $stmt->execute();
                $sem2_result = $stmt->get_result();
                while ($course = $sem2_result->fetch_assoc()) {
                    $sem2_courses[] = $course['name'] . ' (' . $course['code'] . ')';
                }
                $stmt->close();
            }
            
            $mappings[] = [
                'class_name' => $class['class_name'],
                'semester1_courses' => $sem1_courses,
                'semester2_courses' => $sem2_courses
            ];
        }
    }
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-sitemap me-2"></i>Map Courses to Classes </h4>
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

        <div class="card m-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Program</label>
                        <select name="department_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php if ($departments) { while ($d = $departments->fetch_assoc()) { ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo ($selected_department == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Level</label>
                        <select name="level_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php if ($levels) { while ($lv = $levels->fetch_assoc()) { ?>
                                <option value="<?php echo $lv['id']; ?>" <?php echo ($selected_level_id == $lv['id']) ? 'selected' : ''; ?>
                                    <?php echo htmlspecialchars($lv['name']); ?>
                                </option>
                            <?php } } ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="card m-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Course Assignment</h6>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignCoursesModal">
                        <i class="fas fa-plus me-2"></i>Assign Courses
                    </button>
                </div>
                <div class="text-muted"><small>Use the filters above to select specific classes, then click "Assign Courses" to assign courses to all filtered classes.</small></div>
            </div>
        </div>

        <!-- Assign Courses Modal -->
        <div class="modal fade" id="assignCoursesModal" tabindex="-1" aria-labelledby="assignCoursesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignCoursesModalLabel">Assign Courses to Classes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <!-- Available Courses (Left Side) -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Available Courses</h6>
                                        <div class="mt-2">
                                            <select class="form-select" id="courseDropdown" size="8">
                                                <option value="">Select courses to assign...</option>
                                                <!-- Courses will be populated here -->
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="d-grid gap-2 p-2">
                                            <button class="btn btn-outline-primary btn-sm" id="addSelectedCourses">
                                                <i class="fas fa-plus me-2"></i>Add Selected Courses
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Selected Courses (Right Side) -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Selected Courses</h6>
                                        <small class="text-muted">Courses to be assigned to all filtered classes</small>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="list-group list-group-flush" id="selectedCoursesList" style="max-height: 400px; overflow-y: auto;">
                                            <!-- Selected courses will appear here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selected Classes Info -->
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <strong>Classes to receive courses:</strong>
                                <div id="selectedClassesInfo" class="mt-2">
                                    <!-- Class information will be displayed here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmAssign">Assign Courses</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const courseDropdown = document.getElementById('courseDropdown');
            const addSelectedCoursesBtn = document.getElementById('addSelectedCourses');
            const selectedCoursesList = document.getElementById('selectedCoursesList');
            const selectedClassesInfo = document.getElementById('selectedClassesInfo');
            const confirmAssign = document.getElementById('confirmAssign');
            
            let allCourses = [];
            let selectedCourses = [];
            let filteredClasses = [];
            
            // Load courses when modal opens
            document.getElementById('assignCoursesModal').addEventListener('show.bs.modal', function() {
                loadCourses();
                loadFilteredClasses();
            });
            
            // Load available courses
            function loadCourses() {
                fetch('get_courses.php?department_id=<?php echo $selected_department; ?>&level=<?php echo $level_year; ?>')
                    .then(response => response.json())
                    .then(data => {
                        allCourses = data;
                        displayAvailableCourses();
                    })
                    .catch(error => console.error('Error loading courses:', error));
            }
            
            // Load filtered classes
            function loadFilteredClasses() {
                fetch('get_filtered_classes.php?department_id=<?php echo $selected_department; ?>&level=<?php echo $level_name; ?>')
                    .then(response => response.json())
                    .then(data => {
                        filteredClasses = data;
                        displaySelectedClasses();
                    })
                    .catch(error => console.error('Error loading classes:', error));
            }
            
            // Display available courses in dropdown
            function displayAvailableCourses() {
                courseDropdown.innerHTML = '<option value="">Select courses to assign...</option>';
                allCourses.forEach(course => {
                    const option = document.createElement('option');
                    option.value = course.id;
                    option.textContent = `${course.code} - ${course.name}`;
                    courseDropdown.appendChild(option);
                });
            }
            
            // Add selected courses to the right list
            addSelectedCoursesBtn.addEventListener('click', function() {
                const selectedCourseIds = Array.from(courseDropdown.selectedOptions).map(option => option.value);
                const newSelectedCourses = allCourses.filter(course => selectedCourseIds.includes(course.id));
                
                newSelectedCourses.forEach(course => {
                    if (!selectedCourses.find(sc => sc.id === course.id)) {
                        selectedCourses.push({id: course.id, code: course.code, name: course.name});
                    }
                });
                displaySelectedCourses();
                courseDropdown.value = ''; // Clear selected options
            });
            
            // Remove course from selected list
            window.removeCourse = function(courseId) {
                selectedCourses = selectedCourses.filter(sc => sc.id !== courseId);
                displaySelectedCourses();
            };
            
            // Display selected courses
            function displaySelectedCourses() {
                selectedCoursesList.innerHTML = '';
                selectedCourses.forEach(course => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center';
                    item.innerHTML = `
                        <div>
                            <strong>${course.code}</strong><br>
                            <small class="text-muted">${course.name}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeCourse(${course.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    selectedCoursesList.appendChild(item);
                });
            }
            
            // Display selected classes info
            function displaySelectedClasses() {
                if (filteredClasses.length === 0) {
                    selectedClassesInfo.innerHTML = '<span class="text-warning">No classes match the current filters. Please adjust your Program and Level selection.</span>';
                    confirmAssign.disabled = true;
                } else {
                    selectedClassesInfo.innerHTML = filteredClasses.map(cls => 
                        `<span class="badge bg-primary me-1">${cls.name}</span>`
                    ).join('');
                    confirmAssign.disabled = false;
                }
            }
            
            // Confirm assignment
            confirmAssign.addEventListener('click', function() {
                if (selectedCourses.length === 0) {
                    alert('Please select at least one course to assign.');
                    return;
                }
                
                if (filteredClasses.length === 0) {
                    alert('No classes selected. Please adjust your filters.');
                    return;
                }
                
                // Prepare data for assignment
                const assignmentData = {
                    action: 'bulk_assign',
                    class_ids: filteredClasses.map(cls => cls.id),
                    course_ids: selectedCourses.map(course => course.id)
                };
                
                // Send assignment request
                fetch('assign_courses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(assignmentData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Courses assigned successfully!');
                        location.reload(); // Refresh the page to show new assignments
                    } else {
                        alert('Error assigning courses: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error assigning courses. Please try again.');
                });
            });
        });
        </script>

        <div class="table-responsive m-3">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Class Name</th>
                        <th>Semester 1 Courses</th>
                        <th>Semester 2 Courses</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mappings && count($mappings) > 0): ?>
                        <?php foreach ($mappings as $m): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($m['class_name']); ?></td>
                                <td>
                                    <?php if (!empty($m['semester1_courses'])): ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($m['semester1_courses'] as $course): ?>
                                                <li class="mb-1">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($course); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No courses assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($m['semester2_courses'])): ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($m['semester2_courses'] as $course): ?>
                                                <li class="mb-1">
                                                    <span class="badge bg-success"><?php echo htmlspecialchars($course); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No courses assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No classes found for the selected filters.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

