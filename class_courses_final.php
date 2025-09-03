<?php
/**
 * FINAL Professional Class-Course Assignment Management
 * Based on your actual schema with department-oriented validation
 * High-quality implementation with professional checks
 */

$pageTitle = 'Professional Class-Course Management';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

// Include final stream manager
include_once 'includes/stream_manager_final.php';
$streamManager = getStreamManager();

$success_message = '';
$error_message = '';
$warning_message = '';

// ============================================================================
// PROFESSIONAL POST REQUEST HANDLING
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    
    // Professional single assignment
    if ($action === 'assign_single') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        $lecturer_id = !empty($_POST['lecturer_id']) ? (int)$_POST['lecturer_id'] : null;
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $assigned_by = $_POST['assigned_by'] ?? ($_SESSION['user_name'] ?? 'System Admin');
        
        if ($class_id > 0 && $course_id > 0) {
            $result = assignCourseToClassProfessional($class_id, $course_id, $lecturer_id, $semester, $academic_year, $assigned_by);
            
            if ($result['success']) {
                $success_message = "Course assigned successfully! Quality Score: {$result['quality_score']}/50";
                if (!empty($result['warnings'])) {
                    $warning_message = "Warnings: " . implode('; ', $result['warnings']);
                }
            } else {
                $error_message = "Assignment failed: " . implode('; ', $result['errors'] ?? [$result['message'] ?? 'Unknown error']);
            }
        } else {
            $error_message = "Please select both class and course.";
        }
    }
    
    // Smart assignment based on professional recommendations
    if ($action === 'smart_assign') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $assigned_by = $_POST['assigned_by'] ?? ($_SESSION['user_name'] ?? 'System Admin');
        $min_quality_score = (int)($_POST['min_quality_score'] ?? 30);
        
        if ($class_id > 0) {
            $recommended_result = $streamManager->getRecommendedCoursesForClass($class_id);
            $assigned_count = 0;
            $skipped_count = 0;
            $errors = [];
            
            while ($course = $recommended_result->fetch_assoc()) {
                // Only assign highly recommended courses that aren't already assigned
                if ($course['recommendation_score'] >= $min_quality_score && 
                    $course['recommendation_status'] !== 'already_assigned') {
                    
                    $result = assignCourseToClassProfessional(
                        $class_id, 
                        $course['course_id'], 
                        null, 
                        $semester, 
                        $academic_year, 
                        $assigned_by . ' (Smart Assignment)'
                    );
                    
                    if ($result['success']) {
                        $assigned_count++;
                    } else {
                        $skipped_count++;
                        $errors[] = "Failed to assign {$course['course_code']}: " . implode('; ', $result['errors'] ?? []);
                    }
                } else {
                    $skipped_count++;
                }
            }
            
            if ($assigned_count > 0) {
                $success_message = "Smart assignment completed! $assigned_count courses assigned, $skipped_count skipped.";
                if (!empty($errors)) {
                    $warning_message = "Some assignments failed: " . implode('; ', array_slice($errors, 0, 3));
                }
            } else {
                $warning_message = "No suitable courses found for smart assignment. Try lowering the minimum quality score or assign courses manually.";
            }
        }
    }
    
    // Bulk assignment with professional validation
    if ($action === 'assign_bulk') {
        $class_ids = $_POST['class_ids'] ?? [];
        $course_ids = $_POST['course_ids'] ?? [];
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $assigned_by = $_POST['assigned_by'] ?? ($_SESSION['user_name'] ?? 'System Admin');
        
        if (!empty($class_ids) && !empty($course_ids)) {
            $total_attempts = count($class_ids) * count($course_ids);
            $success_count = 0;
            $error_count = 0;
            $warning_count = 0;
            $errors = [];
            $warnings = [];
            
            foreach ($class_ids as $class_id) {
                foreach ($course_ids as $course_id) {
                    $result = assignCourseToClassProfessional(
                        $class_id, 
                        $course_id, 
                        null, 
                        $semester, 
                        $academic_year, 
                        $assigned_by . ' (Bulk Assignment)'
                    );
                    
                    if ($result['success']) {
                        $success_count++;
                        if (!empty($result['warnings'])) {
                            $warning_count++;
                            $warnings = array_merge($warnings, $result['warnings']);
                        }
                    } else {
                        $error_count++;
                        $errors = array_merge($errors, $result['errors'] ?? [$result['message'] ?? 'Unknown error']);
                    }
                }
            }
            
            // Professional feedback
            if ($success_count > 0 && $error_count === 0) {
                $success_message = "Bulk assignment completed successfully! $success_count assignments created out of $total_attempts attempts.";
            } elseif ($success_count > 0) {
                $success_message = "Partial success: $success_count successful, $error_count failed out of $total_attempts attempts.";
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
    
    // Delete assignment
    if ($action === 'delete' && isset($_POST['class_course_id'])) {
        $class_course_id = (int)$_POST['class_course_id'];
        
        // Verify the assignment belongs to a class in the current stream
        $verify_sql = "SELECT cc.id, c.name as class_name, co.code as course_code, cc.quality_score
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
                $success_message = "Assignment deleted: {$assignment_data['course_code']} removed from {$assignment_data['class_name']} (Quality Score: {$assignment_data['quality_score']})";
            } else {
                $error_message = "Error deleting assignment: " . $conn->error;
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

// Get programs filtered by department if selected
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
$levels_sql = "SELECT id, name, code, numeric_value FROM levels WHERE is_active = 1 ORDER BY numeric_value";
$levels_result = $conn->query($levels_sql);

// Get classes for current stream with filters
$filters = [
    'department_id' => $selected_department > 0 ? $selected_department : null,
    'program_id' => $selected_program > 0 ? $selected_program : null,
    'level_id' => $selected_level > 0 ? $selected_level : null,
    'search_name' => !empty($search_name) ? $search_name : null
];
$classes_result = $streamManager->getCurrentStreamClasses($filters);

// Get all courses (global) with compatibility if class selected
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$courses_result = $streamManager->getCoursesWithCompatibility($selected_class_id);

// Get all lecturers (global)
$lecturers_sql = "SELECT l.id, l.name, l.title, l.rank, d.name as department_name
                  FROM lecturers l
                  LEFT JOIN departments d ON l.department_id = d.id
                  WHERE l.is_active = 1
                  ORDER BY d.name, l.name";
$lecturers_result = $conn->query($lecturers_sql);

// Get current assignments with quality metrics
$assignments_sql = "SELECT * FROM assignment_quality_monitor 
                   WHERE stream_name = (SELECT name FROM streams WHERE id = ?)";

$assignment_params = [$current_stream_id];
$assignment_types = 'i';

// Apply same filters as classes
if ($selected_department > 0) {
    $assignments_sql .= " AND class_department = (SELECT name FROM departments WHERE id = ?)";
    $assignment_params[] = $selected_department;
    $assignment_types .= 'i';
}

if ($selected_program > 0) {
    $assignments_sql .= " AND class_id IN (SELECT id FROM classes WHERE program_id = ?)";
    $assignment_params[] = $selected_program;
    $assignment_types .= 'i';
}

$assignments_sql .= " ORDER BY class_department, class_level, class_name, course_code";

$assignments_stmt = $conn->prepare($assignments_sql);
$assignments_stmt->bind_param($assignment_types, ...$assignment_params);
$assignments_stmt->execute();
$assignments_result = $assignments_stmt->get_result();

?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Professional Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-graduation-cap"></i> <?php echo $pageTitle; ?></h2>
                <p class="text-muted">
                    Department-oriented course assignments with professional validation and quality scoring
                    <br><small class="text-info">
                        <i class="fas fa-info-circle"></i>
                        Classes are stream-specific • Courses, lecturers, and rooms are global resources
                    </small>
                </p>
            </div>
            <div>
                <?php echo $streamManager->getStreamSelector(); ?>
            </div>
        </div>

        <!-- Professional Flash Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <strong>Success:</strong> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($warning_message)): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> <?php echo $warning_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Professional Filters Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Professional Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select name="department_id" id="department_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php $departments_result->data_seek(0); while ($dept = $departments_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $selected_department == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?> (<?php echo $dept['code']; ?>)
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
                        <label for="search_name" class="form-label">Search Class</label>
                        <input type="text" name="search_name" id="search_name" class="form-control" 
                               value="<?php echo htmlspecialchars($search_name); ?>" 
                               placeholder="Enter class name...">
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Professional Assignment Tools -->
        <div class="row mb-4">
            <!-- Single Assignment with Real-time Validation -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-plus-circle"></i> Professional Assignment</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="professionalAssignForm">
                            <input type="hidden" name="action" value="assign_single">
                            
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Select Class <span class="text-danger">*</span></label>
                                <select name="class_id" id="class_id" class="form-select" required onchange="updateCourseCompatibility()">
                                    <option value="">Choose a class...</option>
                                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                                        <option value="<?php echo $class['id']; ?>" 
                                                data-department="<?php echo $class['department_name']; ?>"
                                                data-level="<?php echo $class['level_name']; ?>"
                                                data-program="<?php echo $class['program_name']; ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?> 
                                            (<?php echo $class['department_name']; ?> - <?php echo $class['level_name']; ?>)
                                            <span class="text-muted">[<?php echo $class['assigned_courses_count']; ?> courses assigned]</span>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="course_id" class="form-label">Select Course <span class="text-danger">*</span></label>
                                <select name="course_id" id="course_id" class="form-select" required onchange="updateCompatibilityIndicator()">
                                    <option value="">Choose a course...</option>
                                    <?php while ($course = $courses_result->fetch_assoc()): ?>
                                        <option value="<?php echo $course['id']; ?>"
                                                data-department="<?php echo $course['department_name']; ?>"
                                                data-level="<?php echo $course['level_name']; ?>"
                                                data-type="<?php echo $course['course_type']; ?>"
                                                data-credits="<?php echo $course['credits']; ?>"
                                                <?php if (isset($course['compatibility_score'])): ?>
                                                    data-compatibility="<?php echo $course['compatibility_score']; ?>"
                                                    data-status="<?php echo $course['assignment_status']; ?>"
                                                <?php endif; ?>>
                                            <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?>
                                            <span class="text-muted">
                                                (<?php echo $course['department_name']; ?> - <?php echo $course['level_name']; ?> - <?php echo ucfirst($course['course_type']); ?>)
                                            </span>
                                            <?php if (isset($course['assignment_status']) && $course['assignment_status'] === 'assigned'): ?>
                                                <span class="text-warning">[ASSIGNED]</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lecturer_id" class="form-label">Preferred Lecturer (Optional)</label>
                                <select name="lecturer_id" id="lecturer_id" class="form-select">
                                    <option value="">Auto-assign based on lecturer-course mapping</option>
                                    <?php while ($lecturer = $lecturers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $lecturer['id']; ?>">
                                            <?php echo htmlspecialchars($lecturer['name']); ?>
                                            <?php if ($lecturer['title']): ?>(<?php echo $lecturer['title']; ?>)<?php endif; ?>
                                            - <?php echo htmlspecialchars($lecturer['department_name']); ?>
                                            [<?php echo ucfirst($lecturer['rank']); ?>]
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
                                    <input type="text" name="academic_year" id="academic_year" class="form-control" 
                                           value="2024/2025" pattern="[0-9]{4}/[0-9]{4}">
                                </div>
                            </div>
                            
                            <div class="mb-3 mt-3">
                                <label for="assigned_by" class="form-label">Assigned By</label>
                                <input type="text" name="assigned_by" id="assigned_by" class="form-control" 
                                       value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Academic Officer'); ?>">
                            </div>
                            
                            <!-- Professional Compatibility Indicator -->
                            <div id="compatibility-indicator" class="alert" style="display: none;"></div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-plus"></i> Assign Course Professionally
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Smart Assignment Tool -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-magic"></i> Smart Professional Assignment</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            <i class="fas fa-lightbulb"></i>
                            Automatically assign courses based on professional criteria:
                            department alignment, level matching, and course type priorities.
                        </p>
                        
                        <form method="POST" id="smartAssignForm">
                            <input type="hidden" name="action" value="smart_assign">
                            
                            <div class="mb-3">
                                <label for="smart_class_id" class="form-label">Select Class</label>
                                <select name="class_id" id="smart_class_id" class="form-select" required onchange="updateSmartAssignPreview()">
                                    <option value="">Choose a class...</option>
                                    <?php 
                                    $classes_result->data_seek(0);
                                    while ($class = $classes_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $class['id']; ?>"
                                                data-department="<?php echo $class['department_name']; ?>"
                                                data-level="<?php echo $class['level_name']; ?>"
                                                data-assigned="<?php echo $class['assigned_courses_count']; ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                            <span class="text-muted">
                                                (<?php echo $class['department_name']; ?> - <?php echo $class['level_name']; ?>)
                                                [<?php echo $class['assigned_courses_count']; ?> assigned]
                                            </span>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="min_quality_score" class="form-label">
                                    Minimum Quality Score 
                                    <span class="text-muted">(0-50)</span>
                                </label>
                                <input type="range" name="min_quality_score" id="min_quality_score" 
                                       class="form-range" min="0" max="50" value="30" 
                                       oninput="updateQualityScoreDisplay(this.value)">
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Lower Quality (0)</small>
                                    <span id="quality_score_display" class="badge bg-info">30</span>
                                    <small class="text-muted">Higher Quality (50)</small>
                                </div>
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
                                       value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Academic Officer'); ?> (Smart Assignment)">
                            </div>
                            
                            <!-- Smart Assignment Preview -->
                            <div id="smart-preview" class="alert alert-info" style="display: none;">
                                <strong>Preview:</strong> <span id="preview-text"></span>
                            </div>
                            
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-magic"></i> Smart Assign Professional
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Assignments with Quality Metrics -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list-alt"></i> Current Professional Assignments</h5>
                <div>
                    <span class="badge bg-info">Stream: <?php echo $streamManager->getCurrentStreamName(); ?></span>
                    <span class="badge bg-secondary">Total: <?php echo $assignments_result->num_rows; ?></span>
                    <?php 
                    // Calculate average quality score
                    $assignments_result->data_seek(0);
                    $total_score = 0;
                    $count = 0;
                    while ($temp = $assignments_result->fetch_assoc()) {
                        if ($temp['quality_score'] !== null) {
                            $total_score += $temp['quality_score'];
                            $count++;
                        }
                    }
                    $avg_quality = $count > 0 ? round($total_score / $count, 1) : 0;
                    $quality_class = $avg_quality >= 35 ? 'success' : ($avg_quality >= 25 ? 'warning' : 'danger');
                    ?>
                    <span class="badge bg-<?php echo $quality_class; ?>">Avg Quality: <?php echo $avg_quality; ?>/50</span>
                </div>
            </div>
            <div class="card-body">
                <?php $assignments_result->data_seek(0); if ($assignments_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Class</th>
                                    <th>Course</th>
                                    <th>Professional Validation</th>
                                    <th>Quality Score</th>
                                    <th>Assignment Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assignment = $assignments_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($assignment['class_name']); ?></strong>
                                                <?php if ($assignment['class_code']): ?>
                                                    <br><code class="text-muted"><?php echo $assignment['class_code']; ?></code>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $assignment['class_department']; ?> - <?php echo $assignment['class_level']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong>
                                                <br><small><?php echo htmlspecialchars($assignment['course_name']); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $assignment['course_department']; ?> - <?php echo $assignment['course_level']; ?>
                                            </small>
                                            <br>
                                            <span class="badge bg-<?php echo $assignment['course_type'] === 'core' ? 'primary' : 'secondary'; ?>">
                                                <?php echo ucfirst($assignment['course_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <?php if ($assignment['dept_match']): ?>
                                                    <span class="badge bg-success">✓ Dept Match</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">⚠ Cross-Dept</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-1">
                                                <?php if ($assignment['level_match']): ?>
                                                    <span class="badge bg-success">✓ Level Match</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">✗ Level Mismatch</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($assignment['primary_issue'] !== 'no_issues'): ?>
                                                <div>
                                                    <span class="badge bg-danger">
                                                        <?php echo str_replace('_', ' ', ucfirst($assignment['primary_issue'])); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $score = $assignment['quality_score'];
                                            $score_class = $score >= 40 ? 'success' : ($score >= 25 ? 'warning' : 'danger');
                                            $rating = $assignment['quality_rating'];
                                            ?>
                                            <div class="mb-1">
                                                <span class="badge bg-<?php echo $score_class; ?> fs-6">
                                                    <?php echo $score; ?>/50
                                                </span>
                                            </div>
                                            <small class="text-<?php echo $score_class; ?>">
                                                <?php echo ucfirst($rating); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <small class="text-muted">By:</small> 
                                                <?php echo htmlspecialchars($assignment['assigned_by'] ?: 'Unknown'); ?>
                                            </div>
                                            <div class="mb-1">
                                                <small class="text-muted">Date:</small> 
                                                <?php echo date('M j, Y', strtotime($assignment['assignment_date'])); ?>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php echo $assignment['approval_status'] === 'approved' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($assignment['approval_status']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirmDelete('<?php echo htmlspecialchars($assignment['course_code']); ?>', '<?php echo htmlspecialchars($assignment['class_name']); ?>')">
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
                    <div class="text-center py-5">
                        <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No assignments found for current stream</h5>
                        <p class="text-muted">Start by assigning courses to classes using the professional assignment tools above.</p>
                        <small class="text-info">
                            <i class="fas fa-info-circle"></i>
                            Remember: Only classes are stream-specific. Courses and lecturers are global resources.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Professional JavaScript for Enhanced UX -->
<script>
// Professional compatibility checking
function updateCompatibilityIndicator() {
    const classSelect = document.getElementById('class_id');
    const courseSelect = document.getElementById('course_id');
    const indicator = document.getElementById('compatibility-indicator');
    
    const classOption = classSelect.options[classSelect.selectedIndex];
    const courseOption = courseSelect.options[courseSelect.selectedIndex];
    
    if (!classOption || !courseOption || !classOption.value || !courseOption.value) {
        indicator.style.display = 'none';
        return;
    }
    
    const classDept = classOption.getAttribute('data-department');
    const classLevel = classOption.getAttribute('data-level');
    const courseDept = courseOption.getAttribute('data-department');
    const courseLevel = courseOption.getAttribute('data-level');
    const courseType = courseOption.getAttribute('data-type');
    const compatibilityScore = courseOption.getAttribute('data-compatibility');
    const assignmentStatus = courseOption.getAttribute('data-status');
    
    let score = 0;
    let messages = [];
    let alertClass = 'alert-info';
    
    // Professional validation logic
    if (classLevel === courseLevel) {
        score += 25;
        messages.push('✅ <strong>Level Match:</strong> ' + classLevel);
    } else {
        score -= 25;
        messages.push('❌ <strong>Level Mismatch:</strong> Class (' + classLevel + ') vs Course (' + courseLevel + ')');
        alertClass = 'alert-danger';
    }
    
    if (classDept === courseDept) {
        score += 20;
        messages.push('✅ <strong>Department Match:</strong> ' + classDept);
    } else {
        if (courseType === 'core') {
            score -= 15;
            messages.push('⚠️ <strong>Core Course from Different Department:</strong> ' + courseDept);
            alertClass = 'alert-warning';
        } else {
            score += 5;
            messages.push('ℹ️ <strong>Cross-Departmental ' + courseType.charAt(0).toUpperCase() + courseType.slice(1) + ':</strong> Acceptable');
        }
    }
    
    // Course type scoring
    switch (courseType) {
        case 'core':
            score += 15;
            messages.push('✅ <strong>Core Course:</strong> High priority');
            break;
        case 'elective':
            score += 8;
            messages.push('ℹ️ <strong>Elective Course:</strong> Flexible assignment');
            break;
        case 'practical':
            score += 12;
            messages.push('✅ <strong>Practical Course:</strong> Hands-on learning');
            break;
    }
    
    // Assignment status check
    if (assignmentStatus === 'assigned') {
        messages.push('⚠️ <strong>Already Assigned:</strong> This course is already assigned to this class');
        alertClass = 'alert-warning';
    }
    
    // Professional recommendation
    let recommendation = '';
    if (score >= 40) {
        recommendation = '<span class="badge bg-success">HIGHLY RECOMMENDED</span>';
    } else if (score >= 25) {
        recommendation = '<span class="badge bg-primary">RECOMMENDED</span>';
    } else if (score >= 10) {
        recommendation = '<span class="badge bg-warning">NEEDS REVIEW</span>';
    } else {
        recommendation = '<span class="badge bg-danger">NOT RECOMMENDED</span>';
        alertClass = 'alert-danger';
    }
    
    // Display results
    indicator.className = 'alert ' + alertClass;
    indicator.innerHTML = '<div class="d-flex justify-content-between align-items-center mb-2">' +
                         '<strong>Professional Compatibility Analysis</strong>' +
                         recommendation + '</div>' +
                         '<div class="mb-2"><strong>Quality Score: ' + Math.max(0, score) + '/50</strong></div>' +
                         messages.join('<br>');
    indicator.style.display = 'block';
    
    // Enable/disable submit based on critical errors
    const submitBtn = document.querySelector('#professionalAssignForm button[type="submit"]');
    if (score < 0 || assignmentStatus === 'assigned') {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-ban"></i> Cannot Assign (Critical Issues)';
        submitBtn.className = 'btn btn-danger btn-lg w-100';
    } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Assign Course Professionally';
        submitBtn.className = 'btn btn-primary btn-lg w-100';
    }
}

function updateQualityScoreDisplay(value) {
    document.getElementById('quality_score_display').textContent = value;
}

function updateSmartAssignPreview() {
    const classSelect = document.getElementById('smart_class_id');
    const preview = document.getElementById('smart-preview');
    const previewText = document.getElementById('preview-text');
    
    if (classSelect.value) {
        const option = classSelect.options[classSelect.selectedIndex];
        const dept = option.getAttribute('data-department');
        const level = option.getAttribute('data-level');
        const assigned = option.getAttribute('data-assigned');
        
        previewText.textContent = `Will analyze ${dept} ${level} courses and assign those with quality score ≥ threshold. Currently ${assigned} courses assigned.`;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

function confirmDelete(courseCode, className) {
    return confirm(`Are you sure you want to remove the assignment?\n\nCourse: ${courseCode}\nClass: ${className}\n\nThis action cannot be undone.`);
}

// Stream selector change handler
function changeStream(streamId) {
    if (streamId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'change_stream.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'stream_id';
        input.value = streamId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize compatibility checking
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('class_id').addEventListener('change', updateCompatibilityIndicator);
    document.getElementById('course_id').addEventListener('change', updateCompatibilityIndicator);
});
</script>

<?php include 'includes/footer.php'; ?>