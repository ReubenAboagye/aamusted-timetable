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
    }
}

// Data for UI
$sessions = $conn->query("SELECT id, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY academic_year DESC, semester_number");
$classes = $conn->query("SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name");
$courses = $conn->query("SELECT id, name, code FROM courses WHERE is_active = 1 ORDER BY name");

$selected_session = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($selected_session <= 0 && $sessions && $sessions->num_rows > 0) {
    $first = $sessions->fetch_assoc();
    $selected_session = (int)$first['id'];
    // reset pointer
    $sessions = $conn->query("SELECT id, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY academic_year DESC, semester_number");
}

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
                </form>
            </div>
        </div>

        <div class="card m-3">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add" />
                    <input type="hidden" name="session_id" value="<?php echo $selected_session; ?>" />
                    <div class="col-md-4">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">Choose class...</option>
                            <?php if ($classes) { while ($cl = $classes->fetch_assoc()) { ?>
                                <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['name']); ?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Choose course...</option>
                            <?php if ($courses) { while ($co = $courses->fetch_assoc()) { ?>
                                <option value="<?php echo $co['id']; ?>"><?php echo htmlspecialchars($co['name'] . ' (' . $co['code'] . ')'); ?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Add</button>
                    </div>
                </form>
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

