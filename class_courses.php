<?php
include 'connect.php';

// Page title and layout includes
$pageTitle = 'Class Course Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Handle single assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'assign_single') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    
    if ($class_id > 0 && $course_id > 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $class_id, $course_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('class_courses.php', 'success', 'Course assigned to class successfully!');
        } else {
            $error_message = "Error assigning course to class.";
        }
        $stmt->close();
    } else {
        $error_message = "Please select both class and course.";
    }
    }

    // Handle bulk assignment
    if ($action === 'assign_bulk') {
    $class_ids = $_POST['class_ids'] ?? [];
    $course_ids = $_POST['course_ids'] ?? [];
    
    if (!empty($class_ids) && !empty($course_ids)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id) VALUES (?, ?)");
        
        foreach ($class_ids as $cid) {
            foreach ($course_ids as $coid) {
                $stmt->bind_param('ii', $cid, $coid);
                $stmt->execute();
            }
        }
        $stmt->close();
        redirect_with_flash('class_courses.php', 'success', 'Bulk assignment completed successfully!');
    } else {
        $error_message = "Please select both classes and courses.";
    }
    }

    // Handle deletion
    if ($action === 'delete' && isset($_POST['class_course_id'])) {
    $class_course_id = (int)$_POST['class_course_id'];
    $stmt = $conn->prepare("DELETE FROM class_courses WHERE id = ?");
    $stmt->bind_param('i', $class_course_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        redirect_with_flash('class_courses.php', 'success', 'Assignment deleted successfully!');
    } else {
        $error_message = "Error deleting assignment.";
    }
    $stmt->close();
}

// Close POST request handling
}

// Get filter parameters (improved: allow filtering by program and search)
$selected_program = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// Get all classes for selects (include human-readable level and program)
$classes_sql = "SELECT c.id, c.name, l.name AS level, p.name AS program_name FROM classes c LEFT JOIN levels l ON c.level_id = l.id LEFT JOIN programs p ON c.program_id = p.id WHERE c.is_active = 1 ORDER BY c.name";
$classes_result = $conn->query($classes_sql);

// Get all courses (with basic defensive query)
$courses_sql = "SELECT id, `code` AS course_code, `name` AS course_name FROM courses WHERE is_active = 1 ORDER BY `code`";
$courses_result = $conn->query($courses_sql);

// Prepare mappings query similar to lecturer_courses.php: show class -> assigned courses concatenated
$mappings_query = "SELECT c.id as class_id, c.name as class_name, l.name AS level, p.name AS program_name, GROUP_CONCAT(co.code ORDER BY co.code SEPARATOR ', ') AS course_codes
                   FROM classes c
                   LEFT JOIN levels l ON c.level_id = l.id
                   LEFT JOIN programs p ON c.program_id = p.id
                   LEFT JOIN class_courses cc ON c.id = cc.class_id AND cc.is_active = 1
                   LEFT JOIN courses co ON co.id = cc.course_id
                   WHERE c.is_active = 1";

$params = [];
$types = '';

if ($selected_program > 0) {
    $mappings_query .= " AND c.program_id = ?";
    $params[] = $selected_program;
    $types .= 'i';
}
if (!empty($search_name)) {
    $mappings_query .= " AND c.name LIKE ?";
    $params[] = '%' . $search_name . '%';
    $types .= 's';
}

$mappings_query .= " GROUP BY c.id, c.name, l.name, p.name ORDER BY p.name, c.name";

if (!empty($params)) {
    $stmt = $conn->prepare($mappings_query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $assignments_result = $stmt->get_result();
        $stmt->close();
    } else {
        // fallback and log
        error_log('class_courses mappings prepare failed: ' . $conn->error);
        $assignments_result = $conn->query($mappings_query);
    }
} else {
    $assignments_result = $conn->query($mappings_query);
}

// Build existing assignments array for bulk UI convenience
$existing_assignments = [];
$existing_assignments_sql = "SELECT cc.class_id, cc.course_id FROM class_courses cc WHERE cc.is_active = 1";
$existing_assignments_result = $conn->query($existing_assignments_sql);
if ($existing_assignments_result) {
    while ($r = $existing_assignments_result->fetch_assoc()) {
        $existing_assignments[] = $r['class_id'] . '_' . $r['course_id'];
    }
}
?>

<!-- Page-specific styles and assets (loaded in body so header remains authoritative) -->
<div class="main-content" id="mainContent">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <!-- DataTables CSS (Bootstrap 5 integration) -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Use global theme variables defined in includes/header.php:
           --primary-color, --hover-color, --brand-blue, --accent-color, --brand-green */
        :root {
            --muted-border: rgba(0,0,0,0.08);
        }
        .select2-container {
            width: 100% !important;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid var(--muted-border);
        }

        /* Brand header styling to match project theme */
        .card-header.bg-primary {
            background: var(--primary-color) !important;
            border-bottom: 1px solid var(--hover-color);
        }
        .card-header.bg-primary h5, .card-header.bg-primary .btn {
            color: #fff !important;
        }

        /* Buttons in header */
        .card-header .btn-outline-light {
            color: #fff;
            border-color: rgba(255,255,255,0.15);
        }
        .card-header .btn-light {
            background: #fff;
            color: var(--brand-maroon);
        }

        /* Project button color overrides to match global theme */
        .btn-primary {
            background-color: var(--brand-blue) !important;
            border-color: var(--brand-blue) !important;
            color: #fff !important;
        }
        .btn-primary:hover {
            background-color: #0b5ed7 !important;
            border-color: #0b5ed7 !important;
        }

        .btn-success {
            background-color: var(--brand-green) !important;
            border-color: var(--brand-green) !important;
            color: #fff !important;
        }
        .btn-success:hover {
            background-color: #157347 !important;
            border-color: #157347 !important;
        }

        /* Smaller buttons used across tables */
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        /* Action icons styling to match brand */
        .table .btn-danger {
            border-color: var(--brand-maroon);
            color: var(--brand-maroon);
            background: transparent;
        }
        .table .btn-danger:hover {
            background: rgba(122,11,28,0.05);
        }
        .table .btn-warning {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }
    </style>
    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-link me-2"></i>Class Course Management
                        </h5>
                        <div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal">
                                <i class="fas fa-plus me-1"></i>Assign Courses
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="assignmentsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Class</th>
                                        <th>Level</th>
                                        <th>Program</th>
                                        <th>Courses</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($assignments_result && $assignments_result->num_rows > 0): ?>
                                        <?php while ($row = $assignments_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['level']); ?></td>
                                                <td><?php echo htmlspecialchars($row['program_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['course_codes'] ?? ''); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary manage-assignments-btn" data-classid="<?php echo (int)$row['class_id']; ?>">
                                                        <i class="fas fa-edit"></i> Manage
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                <i class="fas fa-info-circle me-2"></i>No assignments found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Assignment Modal REMOVED -->

    <!-- Edit Modal (assign courses to a class) -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Course Assignments
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Class *</label>
                            <select id="modal_class_select" class="form-select">
                                <option value="">Select Class</option>
                                <?php if ($classes_result) { $classes_result->data_seek(0); while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?php echo (int)$class['id']; ?>"><?php echo htmlspecialchars($class['name'] . ' (' . $class['level'] . ')'); ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <div class="form-text">Choose a class to assign courses to</div>
                            <div class="mt-3">
                                <label class="form-label">Filter by Department</label>
                                <select id="filterDept" class="form-select">
                                    <option value="">All Departments</option>
                                    <?php
                                    // Fetch departments for filter
                                    $departments_list = [];
                                    $dept_q = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
                                    if ($dept_q) {
                                        while ($d = $dept_q->fetch_assoc()) {
                                            $departments_list[] = $d;
                                            echo '<option value="' . (int)$d['id'] . '">' . htmlspecialchars($d['name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">Filter by Level</label>
                                <select id="filterLevel" class="form-select">
                                    <option value="">All Levels</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                    <option value="300">300</option>
                                    <option value="400">400</option>
                                    <option value="500">500</option>
                                </select>
                            </div>
                            <div class="mt-2">
                                <label class="form-label">Filter by Academic Semester</label>
                                <select id="filterSemester" class="form-select">
                                    <option value="">All (1/2)</option>
                                    <option value="1">Semester 1 </option>
                                    <option value="2">Semester 2 </option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-12 mb-2">
                                    <input type="text" id="courseSearch" class="form-control" placeholder="Search courses...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Available Courses</label>
                                    <div id="availableCoursesList" style="max-height:320px; overflow:auto; border:1px solid #e9ecef; border-radius:4px; padding:6px;"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Assigned Courses</label>
                                    <div id="assignedCoursesList" style="min-height:320px; border:1px solid #e9ecef; border-radius:4px; padding:6px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for better dropdown experience
            $('#bulk_classes, #bulk_courses').select2({
                placeholder: "Select options...",
                allowClear: true
            });
            
            // Initialize DataTable for better table experience
            $('#assignmentsTable').DataTable({
                pageLength: 25,
                order: [[0, 'asc'], [2, 'asc']],
                language: {
                    search: "Search assignments:",
                    lengthMenu: "Show _MENU_ assignments per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ assignments"
                }
            });
        });
    </script>
    <script>
    // Manage assignments for a class (similar to lecturer_courses.editMapping)
    let currentClassId = null;
    let availableCourses = [];
    let assignedCourses = [];

    // Open modal from Manage button
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.manage-assignments-btn');
        if (btn) {
            var classId = btn.dataset.classid;
            if (classId) {
                // Preselect class in modal and open
                var sel = document.getElementById('modal_class_select');
                if (sel) sel.value = classId;
                // Trigger loading
                loadClassCourses(classId);
                var el = document.getElementById('editModal');
                bootstrap.Modal.getOrCreateInstance(el).show();
            }
        }
    });

    function editMappingClass(classId) {
        currentClassId = classId;
        loadClassCourses(classId);
    }

    function loadClassCourses(classId) {
        currentClassId = classId;
        const availableList = document.getElementById('availableCoursesList');
        const assignedList = document.getElementById('assignedCoursesList');
        if (!availableList || !assignedList) return;
        availableList.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading courses...</div>';
        assignedList.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading courses...</div>';

        fetch('get_class_courses.php?class_id=' + encodeURIComponent(classId))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    availableCourses = data.data.available_courses || [];
                    assignedCourses = data.data.assigned_courses || [];
                    // If API returned class_department_id, preselect department filter by default
                    if (data.data.class_department_id) {
                        const deptSel = document.getElementById('filterDept');
                        if (deptSel) deptSel.value = data.data.class_department_id;
                    }
                    // If API returned class_level_band, preselect level filter and trigger filtering
                    if (data.data.class_level_band) {
                        const levelSel = document.getElementById('filterLevel');
                        if (levelSel) levelSel.value = data.data.class_level_band;
                    }
                    // If API returned class_level_band, preselect academic semester (odd->1 / even->2)
                    if (data.data.class_level_band) {
                        const semSel = document.getElementById('filterSemester');
                        if (semSel) {
                            // Determine default academic semester: for each level we choose semester 1 (odd) by default
                            semSel.value = '1';
                        }
                    }
                    populateCourseLists();
                } else {
                    throw new Error(data.error || 'Failed to fetch courses');
                }
            })
            .catch(err => {
                console.error('Error fetching class courses:', err);
                availableList.innerHTML = '<div class="text-center p-3 text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading courses</div>';
                assignedList.innerHTML = '<div class="text-center p-3 text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading courses</div>';
            });
    }

    function populateCourseLists() {
        const availableList = document.getElementById('availableCoursesList');
        const assignedList = document.getElementById('assignedCoursesList');
        availableList.innerHTML = '';
        assignedList.innerHTML = '';

        // Show available courses filtered by current department and level selections
        const deptVal = document.getElementById('filterDept') ? document.getElementById('filterDept').value : '';
        const levelVal = document.getElementById('filterLevel') ? document.getElementById('filterLevel').value : '';
        const semesterVal = document.getElementById('filterSemester') ? document.getElementById('filterSemester').value : '';
        
        availableCourses.forEach(course => {
            // department filter
            if (deptVal && String(course.department_id) !== String(deptVal)) return;
            
            // level filter: extract first digit from 3-digit course code and convert to level band
            if (levelVal) {
                const courseLevel = extractLevelFromCourseCode(course.course_code);
                if (courseLevel !== parseInt(levelVal)) return;
            }
            
            // semester filter: extract second digit from 3-digit course code (odd=1, even=2)
            if (semesterVal) {
                const courseSemester = extractSemesterFromCourseCode(course.course_code);
                if (courseSemester !== parseInt(semesterVal)) return;
            }

            const courseDiv = document.createElement('div');
            courseDiv.className = 'course-item p-2 border-bottom d-flex justify-content-between align-items-center';
            courseDiv.innerHTML = `<span>${course.course_code} - ${course.course_name}</span><button class="btn btn-sm btn-outline-primary" onclick="assignCourse('${course.id}')"><i class="fas fa-plus"></i></button>`;
            availableList.appendChild(courseDiv);
        });

        assignedCourses.forEach(course => {
            const courseDiv = document.createElement('div');
            courseDiv.className = 'course-item p-2 border-bottom';
            courseDiv.innerHTML = `<div class="d-flex justify-content-between align-items-center"><span>${course.course_code} - ${course.course_name}</span><button class="btn btn-sm btn-outline-danger" onclick="unassignCourse('${course.id}')"><i class="fas fa-minus"></i></button></div>`;
            assignedList.appendChild(courseDiv);
        });
    }

    // Helper function to extract level from course code (first digit of 3-digit number)
    function extractLevelFromCourseCode(courseCode) {
        const match = courseCode.match(/(\d{3})/);
        if (match) {
            const threeDigit = match[1];
            const firstDigit = parseInt(threeDigit.charAt(0));
            return firstDigit * 100; // Convert to level band (e.g., 3 -> 300)
        }
        return null;
    }

    // Helper function to extract semester from course code (second digit of 3-digit number)
    function extractSemesterFromCourseCode(courseCode) {
        const match = courseCode.match(/(\d{3})/);
        if (match) {
            const threeDigit = match[1];
            const secondDigit = parseInt(threeDigit.charAt(1));
            return secondDigit % 2 === 1 ? 1 : 2; // Odd = semester 1, even = semester 2
        }
        return null;
    }

    function assignCourse(courseId) {
        const idx = availableCourses.findIndex(c => c.id == courseId);
        if (idx !== -1) {
            assignedCourses.push(availableCourses[idx]);
            availableCourses.splice(idx, 1);
            populateCourseLists();
        }
    }

    function unassignCourse(courseId) {
        const idx = assignedCourses.findIndex(c => c.id == courseId);
        if (idx !== -1) {
            availableCourses.push(assignedCourses[idx]);
            assignedCourses.splice(idx, 1);
            populateCourseLists();
        }
    }

    function saveAssignmentsClass() {
        const courseIds = assignedCourses.map(c => c.id);
        const saveButton = document.querySelector('#editModal .btn-primary');
        const originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveButton.disabled = true;

        fetch('save_class_courses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ class_id: currentClassId, assigned_course_ids: courseIds })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                var modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                modal.hide();
                // show success
                location.reload();
            } else {
                throw new Error(data.error || 'Save failed');
            }
        })
        .catch(err => { console.error(err); alert('Error saving assignments: ' + err.message); })
        .finally(() => { saveButton.innerHTML = originalText; saveButton.disabled = false; });
    }
    
    // Wire up Save button inside editModal and class select/search
    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.querySelector('#editModal .btn-primary');
        if (btn) btn.addEventListener('click', saveAssignmentsClass);

        var sel = document.getElementById('modal_class_select');
        if (sel) sel.addEventListener('change', function(){
            var val = this.value;
            if (val) loadClassCourses(val);
            else { availableCourses = []; assignedCourses = []; populateCourseLists(); }
        });

        var search = document.getElementById('courseSearch');
        if (search) search.addEventListener('input', function(){
            var q = this.value.trim().toLowerCase();
            filterAvailable(q);
        });

        var deptFilter = document.getElementById('filterDept');
        var levelFilter = document.getElementById('filterLevel');
        var semesterFilter = document.getElementById('filterSemester');
        if (deptFilter) deptFilter.addEventListener('change', function(){ populateCourseLists(); });
        if (levelFilter) levelFilter.addEventListener('change', function(){ populateCourseLists(); });
        if (semesterFilter) semesterFilter.addEventListener('change', function(){ populateCourseLists(); });
    });

    function filterAvailable(query) {
        const list = document.getElementById('availableCoursesList');
        if (!list) return;
        list.innerHTML = '';
        const deptVal = document.getElementById('filterDept') ? document.getElementById('filterDept').value : '';
        const levelVal = document.getElementById('filterLevel') ? document.getElementById('filterLevel').value : '';
        const semesterVal = document.getElementById('filterSemester') ? document.getElementById('filterSemester').value : '';
        
        availableCourses.forEach(course => {
            const text = (course.course_code + ' ' + course.course_name).toLowerCase();
            
            // department filter
            if (deptVal && String(course.department_id) !== String(deptVal)) return;
            
            // level filter: extract first digit from 3-digit course code and convert to level band
            if (levelVal) {
                const courseLevel = extractLevelFromCourseCode(course.course_code);
                if (courseLevel !== parseInt(levelVal)) return;
            }
            
            // semester filter: extract second digit from 3-digit course code (odd=1, even=2)
            if (semesterVal) {
                const courseSemester = extractSemesterFromCourseCode(course.course_code);
                if (courseSemester !== parseInt(semesterVal)) return;
            }
            
            if (!query || text.indexOf(query) !== -1) {
                const courseDiv = document.createElement('div');
                courseDiv.className = 'course-item p-2 border-bottom d-flex justify-content-between align-items-center';
                courseDiv.innerHTML = `<span>${course.course_code} - ${course.course_name}</span><button class="btn btn-sm btn-outline-primary" onclick="assignCourse('${course.id}')"><i class="fas fa-plus"></i></button>`;
                list.appendChild(courseDiv);
            }
        });
    }
    </script>
</body>
</html>

