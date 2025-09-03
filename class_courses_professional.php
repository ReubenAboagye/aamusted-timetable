<?php
/**
 * Professional Class-Course Assignment Management
 * Department-oriented with comprehensive validation and high-quality logic
 */

$pageTitle = 'Professional Class-Course Management';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

// Include corrected stream manager
include_once 'includes/stream_manager_corrected.php';
$streamManager = getStreamManager();

$success_message = '';
$error_message = '';
$warning_message = '';

// ============================================================================
// POST REQUEST HANDLING
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    
    // Single assignment with professional validation
    if ($action === 'assign_single') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $assigned_by = $_POST['assigned_by'] ?? 'System Admin';
        
        if ($class_id > 0 && $course_id > 0) {
            $result = assignCourseToClassProfessional($class_id, $course_id, $semester, $academic_year, $assigned_by);
            
            if ($result['success']) {
                $success_message = "Course assigned successfully!";
                if (!empty($result['warnings'])) {
                    $warning_message = "Warnings: " . implode('; ', $result['warnings']);
                }
            } else {
                $error_message = "Assignment failed: " . implode('; ', $result['errors'] ?? []);
            }
        } else {
            $error_message = "Please select both class and course.";
        }
    }
    
    // Bulk assignment with validation
    if ($action === 'assign_bulk') {
        $class_ids = $_POST['class_ids'] ?? [];
        $course_ids = $_POST['course_ids'] ?? [];
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $assigned_by = $_POST['assigned_by'] ?? 'System Admin';
        
        if (!empty($class_ids) && !empty($course_ids)) {
            $total_attempts = count($class_ids) * count($course_ids);
            $success_count = 0;
            $error_count = 0;
            $warning_count = 0;
            $errors = [];
            $warnings = [];
            
            foreach ($class_ids as $class_id) {
                foreach ($course_ids as $course_id) {
                    $result = assignCourseToClassProfessional($class_id, $course_id, $semester, $academic_year, $assigned_by);
                    
                    if ($result['success']) {
                        $success_count++;
                        if (!empty($result['warnings'])) {
                            $warning_count++;
                            $warnings = array_merge($warnings, $result['warnings']);
                        }
                    } else {
                        $error_count++;
                        $errors = array_merge($errors, $result['errors'] ?? []);
                    }
                }
            }
            
            // Comprehensive feedback
            if ($success_count > 0 && $error_count === 0) {
                $success_message = "Bulk assignment completed successfully! $success_count assignments created.";
            } elseif ($success_count > 0) {
                $success_message = "Partial success: $success_count successful, $error_count failed.";
                $error_message = "Errors: " . implode('; ', array_slice(array_unique($errors), 0, 5));
            } else {
                $error_message = "Bulk assignment failed: " . implode('; ', array_slice(array_unique($errors), 0, 5));
            }
            
            if ($warning_count > 0) {
                $warning_message = "Warnings: " . implode('; ', array_slice(array_unique($warnings), 0, 5));
            }
        } else {
            $error_message = "Please select both classes and courses.";
        }
    }
    
    // Smart assignment based on department and level
    if ($action === 'smart_assign') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $assigned_by = $_POST['assigned_by'] ?? 'System Admin';
        
        if ($class_id > 0) {
            // Get recommended courses
            $recommended_result = $streamManager->getRecommendedCoursesForClass($class_id);
            $assigned_count = 0;
            $skipped_count = 0;
            
            while ($course = $recommended_result->fetch_assoc()) {
                // Only assign highly recommended courses (score > 15) that aren't already assigned
                if ($course['recommendation_score'] > 15 && $course['assignment_status'] !== 'assigned') {
                    $result = assignCourseToClassProfessional($class_id, $course['id'], $semester, $academic_year, $assigned_by);
                    
                    if ($result['success']) {
                        $assigned_count++;
                    } else {
                        $skipped_count++;
                    }
                } else {
                    $skipped_count++;
                }
            }
            
            if ($assigned_count > 0) {
                $success_message = "Smart assignment completed! $assigned_count courses assigned, $skipped_count skipped.";
            } else {
                $warning_message = "No suitable courses found for smart assignment. All recommended courses may already be assigned.";
            }
        }
    }
    
    // Delete assignment
    if ($action === 'delete' && isset($_POST['class_course_id'])) {
        $class_course_id = (int)$_POST['class_course_id'];
        
        // Verify the assignment belongs to a class in the current stream
        $verify_sql = "SELECT cc.id, c.name as class_name, co.course_code
                       FROM class_courses cc
                       JOIN classes c ON cc.class_id = c.id
                       JOIN courses co ON cc.course_id = co.id
                       WHERE cc.id = ? AND c.stream_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param('ii', $class_course_id, $streamManager->getCurrentStreamId());
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $assignment_data = $verify_result->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE class_courses SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param('i', $class_course_id);
            
            if ($stmt->execute()) {
                $success_message = "Assignment deleted: {$assignment_data['course_code']} removed from {$assignment_data['class_name']}";
            } else {
                $error_message = "Error deleting assignment.";
            }
            $stmt->close();
        } else {
            $error_message = "Assignment not found or does not belong to current stream.";
        }
        $verify_stmt->close();
    }
}

// ============================================================================
// DATA PREPARATION
// ============================================================================

$current_stream_id = $streamManager->getCurrentStreamId();
$stream_settings = $streamManager->getCurrentStreamSettings();

// Get filter parameters
$selected_department = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$selected_program = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$selected_level = isset($_GET['level_id']) ? (int)$_GET['level_id'] : 0;
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// Get all departments (global)
$departments_sql = "SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name";
$departments_result = $conn->query($departments_sql);

// Get all programs (global, but can filter by department)
$programs_sql = "SELECT p.id, p.name, p.code, d.name as department_name 
                 FROM programs p 
                 JOIN departments d ON p.department_id = d.id 
                 WHERE p.is_active = 1";
if ($selected_department > 0) {
    $programs_sql .= " AND p.department_id = $selected_department";
}
$programs_sql .= " ORDER BY d.name, p.name";
$programs_result = $conn->query($programs_sql);

// Get all levels (global)
$levels_sql = "SELECT id, name, numeric_value FROM levels WHERE is_active = 1 ORDER BY numeric_value";
$levels_result = $conn->query($levels_sql);

// Get classes for current stream (ONLY classes are stream-filtered)
$classes_sql = "SELECT c.id, c.name, c.class_code, c.capacity, c.current_enrollment,
                       d.name as department_name, p.name as program_name, l.name as level_name,
                       c.academic_year, c.semester
                FROM classes c
                JOIN departments d ON c.department_id = d.id
                JOIN programs p ON c.program_id = p.id
                JOIN levels l ON c.level_id = l.id
                WHERE c.stream_id = ? AND c.is_active = 1";

$params = [$current_stream_id];
$types = 'i';

if ($selected_department > 0) {
    $classes_sql .= " AND c.department_id = ?";
    $params[] = $selected_department;
    $types .= 'i';
}

if ($selected_program > 0) {
    $classes_sql .= " AND c.program_id = ?";
    $params[] = $selected_program;
    $types .= 'i';
}

if ($selected_level > 0) {
    $classes_sql .= " AND c.level_id = ?";
    $params[] = $selected_level;
    $types .= 'i';
}

if (!empty($search_name)) {
    $classes_sql .= " AND c.name LIKE ?";
    $params[] = "%$search_name%";
    $types .= 's';
}

$classes_sql .= " ORDER BY d.name, p.name, l.numeric_value, c.name";

$classes_stmt = $conn->prepare($classes_sql);
$classes_stmt->bind_param($types, ...$params);
$classes_stmt->execute();
$classes_result = $classes_stmt->get_result();

// Get all courses (global - NOT filtered by stream)
$courses_sql = "SELECT co.id, co.course_code, co.course_name, co.credits, co.course_type,
                       d.name as department_name, l.name as level_name
                FROM courses co
                JOIN departments d ON co.department_id = d.id
                JOIN levels l ON co.level_id = l.id
                WHERE co.is_active = 1
                ORDER BY d.name, l.numeric_value, co.course_code";
$courses_result = $conn->query($courses_sql);

// Get current assignments with detailed information
$assignments_sql = "SELECT 
                        cc.id as assignment_id,
                        cc.class_id,
                        cc.course_id,
                        cc.semester,
                        cc.academic_year,
                        cc.assigned_by,
                        cc.assignment_reason,
                        cc.is_mandatory,
                        cc.created_at,
                        
                        c.name as class_name,
                        c.class_code,
                        cd.department_name as class_department,
                        cd.program_name,
                        cd.level_name as class_level,
                        
                        co.course_code,
                        co.course_name,
                        co.credits,
                        co.course_type,
                        cod.name as course_department,
                        col.name as course_level,
                        
                        -- Validation indicators
                        CASE WHEN cd.department_name = cod.name THEN 'match' ELSE 'mismatch' END as dept_match,
                        CASE WHEN cd.level_name = col.name THEN 'match' ELSE 'mismatch' END as level_match,
                        
                        -- Assignment quality score
                        (
                            CASE WHEN cd.department_name = cod.name THEN 10 ELSE -5 END +
                            CASE WHEN cd.level_name = col.name THEN 10 ELSE -10 END +
                            CASE WHEN co.course_type = 'core' THEN 5 ELSE 0 END
                        ) as quality_score
                        
                    FROM class_courses cc
                    JOIN class_details cd ON cc.class_id = cd.id
                    JOIN courses co ON cc.course_id = co.id
                    JOIN departments cod ON co.department_id = cod.id
                    JOIN levels col ON co.level_id = col.id
                    WHERE cc.is_active = 1 AND cd.stream_name = (SELECT name FROM streams WHERE id = ?)";

$assignment_params = [$current_stream_id];
$assignment_types = 'i';

// Apply same filters as classes
if ($selected_department > 0) {
    $assignments_sql .= " AND cd.id IN (SELECT id FROM classes WHERE department_id = ?)";
    $assignment_params[] = $selected_department;
    $assignment_types .= 'i';
}

if ($selected_program > 0) {
    $assignments_sql .= " AND cd.id IN (SELECT id FROM classes WHERE program_id = ?)";
    $assignment_params[] = $selected_program;
    $assignment_types .= 'i';
}

$assignments_sql .= " ORDER BY cd.department_name, cd.program_name, cd.level_name, cd.class_name, co.course_code";

$assignments_stmt = $conn->prepare($assignments_sql);
$assignments_stmt->bind_param($assignment_types, ...$assignment_params);
$assignments_stmt->execute();
$assignments_result = $assignments_stmt->get_result();

?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><?php echo $pageTitle; ?></h2>
                <p class="text-muted">Department-oriented course assignments with professional validation</p>
            </div>
            <div>
                <?php echo $streamManager->getStreamBadge(); ?>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($warning_message)): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $warning_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Filters & Search</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select name="department_id" id="department_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $selected_department == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="program_id" class="form-label">Program</label>
                        <select name="program_id" id="program_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Programs</option>
                            <?php while ($prog = $programs_result->fetch_assoc()): ?>
                                <option value="<?php echo $prog['id']; ?>" 
                                    <?php echo $selected_program == $prog['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prog['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="level_id" class="form-label">Level</label>
                        <select name="level_id" id="level_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Levels</option>
                            <?php while ($level = $levels_result->fetch_assoc()): ?>
                                <option value="<?php echo $level['id']; ?>" 
                                    <?php echo $selected_level == $level['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($level['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="search_name" class="form-label">Search Class Name</label>
                        <input type="text" name="search_name" id="search_name" class="form-control" 
                               value="<?php echo htmlspecialchars($search_name); ?>" 
                               placeholder="Enter class name...">
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assignment Forms Section -->
        <div class="row mb-4">
            <!-- Single Assignment -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Single Assignment</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="singleAssignForm">
                            <input type="hidden" name="action" value="assign_single">
                            
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Select Class <span class="text-danger">*</span></label>
                                <select name="class_id" id="class_id" class="form-select" required>
                                    <option value="">Choose a class...</option>
                                    <?php 
                                    $classes_result->data_seek(0); // Reset pointer
                                    while ($class = $classes_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $class['id']; ?>" 
                                                data-department="<?php echo $class['department_name']; ?>"
                                                data-level="<?php echo $class['level_name']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?> 
                                            (<?php echo $class['department_name']; ?> - <?php echo $class['level_name']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Select Course <span class="text-danger">*</span></label>
                                <select name="course_id" id="course_id" class="form-select" required>
                                    <option value="">Choose a course...</option>
                                    <?php while ($course = $courses_result->fetch_assoc()): ?>
                                        <option value="<?php echo $course['id']; ?>"
                                                data-department="<?php echo $course['department_name']; ?>"
                                                data-level="<?php echo $course['level_name']; ?>"
                                                data-type="<?php echo $course['course_type']; ?>">
                                            <?php echo htmlspecialchars($course['course_code']); ?> - 
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                            (<?php echo $course['department_name']; ?> - <?php echo $course['level_name']; ?> - <?php echo ucfirst($course['course_type']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="semester" class="form-label">Semester</label>
                                    <select name="semester" id="semester" class="form-select">
                                        <option value="first">First Semester</option>
                                        <option value="second">Second Semester</option>
                                        <option value="summer">Summer Semester</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="academic_year" class="form-label">Academic Year</label>
                                    <input type="text" name="academic_year" id="academic_year" class="form-control" value="2024/2025">
                                </div>
                            </div>
                            
                            <div class="mb-3 mt-3">
                                <label for="assigned_by" class="form-label">Assigned By</label>
                                <input type="text" name="assigned_by" id="assigned_by" class="form-control" 
                                       value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System Admin'); ?>">
                            </div>
                            
                            <!-- Compatibility Indicator -->
                            <div id="compatibility-indicator" class="alert" style="display: none;"></div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Assign Course
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Smart Assignment -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-magic"></i> Smart Assignment</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Automatically assign recommended courses based on department and level compatibility.</p>
                        
                        <form method="POST" id="smartAssignForm">
                            <input type="hidden" name="action" value="smart_assign">
                            
                            <div class="mb-3">
                                <label for="smart_class_id" class="form-label">Select Class</label>
                                <select name="class_id" id="smart_class_id" class="form-select" required>
                                    <option value="">Choose a class...</option>
                                    <?php 
                                    $classes_result->data_seek(0);
                                    while ($class = $classes_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?>
                                            (<?php echo $class['department_name']; ?> - <?php echo $class['level_name']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="smart_semester" class="form-label">Semester</label>
                                    <select name="semester" id="smart_semester" class="form-select">
                                        <option value="first">First Semester</option>
                                        <option value="second">Second Semester</option>
                                        <option value="summer">Summer Semester</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="smart_academic_year" class="form-label">Academic Year</label>
                                    <input type="text" name="academic_year" id="smart_academic_year" class="form-control" value="2024/2025">
                                </div>
                            </div>
                            
                            <div class="mb-3 mt-3">
                                <label for="smart_assigned_by" class="form-label">Assigned By</label>
                                <input type="text" name="assigned_by" id="smart_assigned_by" class="form-control" 
                                       value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System Admin'); ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-magic"></i> Smart Assign
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Assignments Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list"></i> Current Assignments</h5>
                <div>
                    <span class="badge bg-info">Stream: <?php echo $streamManager->getCurrentStreamName(); ?></span>
                    <span class="badge bg-secondary">Total: <?php echo $assignments_result->num_rows; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($assignments_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Class</th>
                                    <th>Course</th>
                                    <th>Department Match</th>
                                    <th>Level Match</th>
                                    <th>Course Type</th>
                                    <th>Quality Score</th>
                                    <th>Assigned By</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($assignment['class_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo $assignment['class_department']; ?> - <?php echo $assignment['class_level']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($assignment['course_name']); ?></small><br>
                                            <small class="text-muted">
                                                <?php echo $assignment['course_department']; ?> - <?php echo $assignment['course_level']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($assignment['dept_match'] === 'match'): ?>
                                                <span class="badge bg-success">✓ Match</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">⚠ Different</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['level_match'] === 'match'): ?>
                                                <span class="badge bg-success">✓ Match</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">✗ Mismatch</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $assignment['course_type'] === 'core' ? 'primary' : 'secondary'; ?>">
                                                <?php echo ucfirst($assignment['course_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $score = $assignment['quality_score'];
                                            $score_class = $score >= 15 ? 'success' : ($score >= 5 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $score_class; ?>"><?php echo $score; ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($assignment['assigned_by'] ?: 'Unknown'); ?><br>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($assignment['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo $assignment['semester']; ?><br>
                                            <small class="text-muted"><?php echo $assignment['academic_year']; ?></small>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to remove this assignment?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="class_course_id" value="<?php echo $assignment['assignment_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Assignment">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No assignments found</h5>
                        <p class="text-muted">Start by assigning courses to classes using the forms above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Enhanced UX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('class_id');
    const courseSelect = document.getElementById('course_id');
    const compatibilityIndicator = document.getElementById('compatibility-indicator');
    
    function checkCompatibility() {
        const classOption = classSelect.options[classSelect.selectedIndex];
        const courseOption = courseSelect.options[courseSelect.selectedIndex];
        
        if (!classOption || !courseOption || !classOption.value || !courseOption.value) {
            compatibilityIndicator.style.display = 'none';
            return;
        }
        
        const classDept = classOption.getAttribute('data-department');
        const classLevel = classOption.getAttribute('data-level');
        const courseDept = courseOption.getAttribute('data-department');
        const courseLevel = courseOption.getAttribute('data-level');
        const courseType = courseOption.getAttribute('data-type');
        
        let score = 0;
        let messages = [];
        let alertClass = 'alert-info';
        
        // Department compatibility
        if (classDept === courseDept) {
            score += 10;
            messages.push('✓ Same department (' + classDept + ')');
        } else {
            if (courseType === 'core') {
                score -= 5;
                messages.push('⚠ Core course from different department');
                alertClass = 'alert-warning';
            } else {
                score += 2;
                messages.push('○ Cross-departmental elective (acceptable)');
            }
        }
        
        // Level compatibility
        if (classLevel === courseLevel) {
            score += 10;
            messages.push('✓ Same level (' + classLevel + ')');
        } else {
            score -= 10;
            messages.push('✗ Level mismatch: Class (' + classLevel + ') vs Course (' + courseLevel + ')');
            alertClass = 'alert-danger';
        }
        
        // Course type bonus
        if (courseType === 'core') {
            score += 5;
            messages.push('✓ Core course');
        } else if (courseType === 'elective') {
            score += 2;
            messages.push('○ Elective course');
        }
        
        // Show compatibility
        compatibilityIndicator.className = 'alert ' + alertClass;
        compatibilityIndicator.innerHTML = '<strong>Compatibility Score: ' + score + '</strong><br>' + messages.join('<br>');
        compatibilityIndicator.style.display = 'block';
        
        // Enable/disable submit based on critical errors
        const submitBtn = document.querySelector('#singleAssignForm button[type="submit"]');
        if (score < 0) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Cannot Assign (Critical Issues)';
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Assign Course';
        }
    }
    
    classSelect.addEventListener('change', checkCompatibility);
    courseSelect.addEventListener('change', checkCompatibility);
});
</script>

<?php include 'includes/footer.php'; ?>