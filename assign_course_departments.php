<?php
// Course-Department Assignment Interface
include 'connect.php';
include 'includes/flash.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for better security
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
}

$pageTitle = 'Course-Department Assignment';
include 'includes/header.php';
include 'includes/sidebar.php';

// Handle form submission for assigning courses to departments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'assign_course') {
        $course_id = (int)$_POST['course_id'];
        $department_id = (int)$_POST['department_id'];
        
        if ($course_id > 0 && $department_id > 0) {
            $sql = "UPDATE courses SET department_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $department_id, $course_id);
            
            if ($stmt->execute()) {
                $success_message = "Course assigned to department successfully!";
            } else {
                $error_message = "Error assigning course: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Invalid course or department selection.";
        }
    } elseif ($action === 'bulk_assign') {
        $assignments = json_decode($_POST['assignments'], true);
        $success_count = 0;
        $error_count = 0;
        
        foreach ($assignments as $assignment) {
            $course_id = (int)$assignment['course_id'];
            $department_id = (int)$assignment['department_id'];
            
            if ($course_id > 0 && $department_id > 0) {
                $sql = "UPDATE courses SET department_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $department_id, $course_id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                $stmt->close();
            }
        }
        
        if ($success_count > 0) {
            $success_message = "Successfully assigned $success_count courses!";
            if ($error_count > 0) {
                $success_message .= " $error_count assignments failed.";
            }
        } else {
            $error_message = "No courses were assigned. Please check your selections.";
        }
    }
}

// Fetch departments for dropdown
$dept_sql = "SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);

// Fetch courses with current department assignments
$courses_sql = "
    SELECT 
        c.id,
        c.code,
        c.name,
        c.department_id,
        d.name as department_name,
        d.code as department_code
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE c.is_active = 1
    ORDER BY c.department_id IS NULL DESC, d.name, c.code
";
$courses_result = $conn->query($courses_sql);

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_courses,
        COUNT(department_id) as assigned_courses,
        COUNT(*) - COUNT(department_id) as unassigned_courses
    FROM courses 
    WHERE is_active = 1
";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total_courses' => 0, 'assigned_courses' => 0, 'unassigned_courses' => 0];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-link me-2"></i>Course-Department Assignment</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-success" onclick="showBulkAssignModal()">
                    <i class="fas fa-tasks me-2"></i>Bulk Assign
                </button>
                <button class="btn btn-info" onclick="showSuggestionsModal()">
                    <i class="fas fa-lightbulb me-2"></i>Smart Suggestions
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-3 m-3">
            <div class="col-md-4">
                <div class="card theme-card bg-theme-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['total_courses']; ?></h4>
                                <p class="card-text">Total Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card theme-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['assigned_courses']; ?></h4>
                                <p class="card-text">Assigned Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card theme-card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $stats['unassigned_courses']; ?></h4>
                                <p class="card-text">Unassigned Courses</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-container m-3">
            <input type="text" class="search-input" id="searchInput" placeholder="Search courses...">
        </div>

        <div class="table-responsive">
            <table class="table" id="coursesTable">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Current Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($courses_result && $courses_result->num_rows > 0): ?>
                        <?php while ($row = $courses_result->fetch_assoc()): ?>
                            <tr class="<?php echo $row['department_id'] ? '' : 'table-warning'; ?>">
                                <td><strong><?php echo htmlspecialchars($row['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td>
                                    <?php if ($row['department_id']): ?>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($row['department_code'] . ' - ' . $row['department_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="assignCourse(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['code'])); ?>', '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', <?php echo $row['department_id'] ?: 'null'; ?>)">
                                        <i class="fas fa-edit"></i> Assign
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-book"></i>
                                <p>No courses found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Course Modal -->
<div class="modal fade" id="assignCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Course to Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_course">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="course_id" id="assign_course_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <div class="p-2 bg-light border rounded">
                            <strong id="assign_course_code"></strong> - <span id="assign_course_name"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assign_department_id" class="form-label">Assign to Department *</label>
                        <select class="form-control" id="assign_department_id" name="department_id" required>
                            <option value="">Select Department...</option>
                            <?php 
                            $dept_result->data_seek(0); // Reset result pointer
                            while ($dept = $dept_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['code'] . ' - ' . $dept['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Smart Suggestions Modal -->
<div class="modal fade" id="suggestionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Smart Assignment Suggestions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Based on course codes and names, here are suggested department assignments:</p>
                <div id="suggestionsContent">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="applySuggestions()">Apply All Suggestions</button>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
function assignCourse(id, code, name, currentDeptId) {
    document.getElementById('assign_course_id').value = id;
    document.getElementById('assign_course_code').textContent = code;
    document.getElementById('assign_course_name').textContent = name;
    
    // Set current department if assigned
    if (currentDeptId) {
        document.getElementById('assign_department_id').value = currentDeptId;
    } else {
        document.getElementById('assign_department_id').value = '';
    }
    
    var el = document.getElementById('assignCourseModal');
    if (!el) return console.error('assignCourseModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el).show();
}

function showSuggestionsModal() {
    generateSuggestions();
    var el2 = document.getElementById('suggestionsModal');
    if (!el2) return console.error('suggestionsModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el2).show();
}

function generateSuggestions() {
    const suggestions = [
        // Computer Science / IT courses
        {pattern: /^CS|IT|COMP/i, department: 'Information Technlogy Education', reason: 'Course code indicates Computer Science/IT'},
        {pattern: /programming|software|database|network|algorithm/i, department: 'Information Technlogy Education', reason: 'Course content relates to IT/Programming'},
        
        // Mathematics courses
        {pattern: /^MATH|MTH/i, department: 'Mathematics Education', reason: 'Course code indicates Mathematics'},
        {pattern: /calculus|algebra|statistics|probability|geometry/i, department: 'Mathematics Education', reason: 'Course content relates to Mathematics'},
        
        // Engineering courses
        {pattern: /^ENG|ENGR/i, department: 'Electrical and Electronics Engineering', reason: 'Course code indicates Engineering'},
        {pattern: /circuit|electronics|electrical/i, department: 'Electrical and Electronics Engineering', reason: 'Course content relates to Electrical Engineering'},
        {pattern: /mechanical|automotive/i, department: 'Mechanical and Automotive Engineering', reason: 'Course content relates to Mechanical Engineering'},
        {pattern: /civil|construction/i, department: 'Construction Technology and Management Education', reason: 'Course content relates to Civil/Construction'},
        
        // Business/Management courses
        {pattern: /^BUS|MGT|ACCT/i, department: 'Management Education', reason: 'Course code indicates Business/Management'},
        {pattern: /management|business|accounting|finance|economics/i, department: 'Management Education', reason: 'Course content relates to Business/Management'},
        
        // Education courses
        {pattern: /^EDU|EDUC/i, department: 'Mathematics Education', reason: 'Course code indicates Education (assign to appropriate dept)'},
        {pattern: /pedagogy|teaching|curriculum|educational/i, department: 'Mathematics Education', reason: 'Course content relates to Education'},
    ];
    
    const rows = document.querySelectorAll('#coursesTable tbody tr');
    const suggestionsContent = document.getElementById('suggestionsContent');
    suggestionsContent.innerHTML = '';
    
    let suggestionsFound = 0;
    
    rows.forEach(row => {
        const cells = row.cells;
        if (cells.length >= 4) {
            const courseCode = cells[0].textContent.trim();
            const courseName = cells[1].textContent.trim();
            const currentDept = cells[2].textContent.trim();
            
            if (currentDept === 'Unassigned') {
                for (let suggestion of suggestions) {
                    if (suggestion.pattern.test(courseCode + ' ' + courseName)) {
                        suggestionsFound++;
                        const suggestionDiv = document.createElement('div');
                        suggestionDiv.className = 'border p-3 mb-2 rounded';
                        suggestionDiv.innerHTML = `
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>${courseCode}</strong> - ${courseName}
                                    <br><small class="text-muted">Suggested: ${suggestion.department}</small>
                                    <br><small class="text-info">${suggestion.reason}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-success" onclick="applySingleSuggestion('${courseCode}', '${suggestion.department}')">
                                    Apply
                                </button>
                            </div>
                        `;
                        suggestionsContent.appendChild(suggestionDiv);
                        break;
                    }
                }
            }
        }
    });
    
    if (suggestionsFound === 0) {
        suggestionsContent.innerHTML = '<div class="text-center text-muted">No suggestions available. All courses may already be assigned.</div>';
    }
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#coursesTable tbody tr');
            
            tableRows.forEach(row => {
                const code = row.cells[0].textContent.toLowerCase();
                const name = row.cells[1].textContent.toLowerCase();
                const dept = row.cells[2].textContent.toLowerCase();
                
                if (code.includes(searchTerm) || name.includes(searchTerm) || dept.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>
