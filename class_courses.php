<?php
$pageTitle = 'Map Courses to Classes (per Session)';
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
$sessions = $conn->query("SELECT id, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY academic_year DESC, semester_number");
$departments = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
$levels = $conn->query("SELECT id, name, year_number FROM levels ORDER BY year_number");

$selected_session = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($selected_session <= 0 && $sessions && $sessions->num_rows > 0) {
    $first = $sessions->fetch_assoc();
    $selected_session = (int)$first['id'];
    // reset pointer
    $sessions = $conn->query("SELECT id, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY academic_year DESC, semester_number");
}

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
if ($selected_session > 0) {
    $stmt = $conn->prepare("SELECT cc.id, cl.name AS class_name, co.name AS course_name, co.code
                            FROM class_courses cc
                            JOIN classes cl ON cl.id = cc.class_id
                            JOIN courses co ON co.id = cc.course_id
                            WHERE cc.session_id = ?
                            ORDER BY cl.name, co.name");
    if ($stmt) {
        $stmt->bind_param('i', $selected_session);
        $stmt->execute();
        $mappings = $stmt->get_result();
        $stmt->close();
    }
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-sitemap me-2"></i>Map Courses to Classes (per Session)</h4>
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
                        <label class="form-label">Session</label>
                        <select name="session_id" class="form-select" onchange="this.form.submit()">
                            <?php if ($sessions) { while ($s = $sessions->fetch_assoc()) { ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($selected_session == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['semester_name'] . ' (' . $s['academic_year'] . ')'); ?>
                                </option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php if ($departments) { while ($d = $departments->fetch_assoc()) { ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo ($selected_department == $d['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Level</label>
                        <select name="level_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php if ($levels) { while ($lv = $levels->fetch_assoc()) { ?>
                                <option value="<?php echo $lv['id']; ?>" <?php echo ($selected_level_id == $lv['id']) ? 'selected' : ''; ?>>
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
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="bulk_add" />
                    <input type="hidden" name="session_id" value="<?php echo $selected_session; ?>" />
                    <div class="col-md-6">
                        <label class="form-label">Classes (multi-select)</label>
                        <select name="class_ids[]" class="form-select" multiple size="8" required>
                            <?php if ($classes) { while ($cl = $classes->fetch_assoc()) { ?>
                                <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['name']); ?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Courses (multi-select)</label>
                        <select name="course_ids[]" class="form-select" multiple size="8" required>
                            <?php if ($courses) { while ($co = $courses->fetch_assoc()) { ?>
                                <option value="<?php echo $co['id']; ?>"><?php echo htmlspecialchars($co['name'] . ' (' . $co['code'] . ')'); ?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Mappings</button>
                    </div>
                </form>
                <div class="mt-2 text-muted"><small>Tip: Filter by Department and Level to select groups like "Level 100 ITE A/B/C" then assign multiple courses at once.</small></div>
            </div>
        </div>

        <div class="table-responsive m-3">
            <table class="table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Course</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mappings && $mappings->num_rows > 0): ?>
                        <?php while ($m = $mappings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['class_name']); ?></td>
                                <td><?php echo htmlspecialchars($m['course_name'] . ' (' . $m['code'] . ')'); ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Remove this mapping?')" style="display:inline;">
                                        <input type="hidden" name="action" value="delete" />
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>" />
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>No mappings for this session yet. Add some above.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

