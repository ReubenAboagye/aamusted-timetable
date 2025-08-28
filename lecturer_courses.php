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
                if ($stmt->execute()) { $success_message = 'Mapping added.'; } else { $error_message = 'Insert failed: ' . $conn->error; }
                $stmt->close();
            } else { $error_message = 'Prepare failed: ' . $conn->error; }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM lecturer_courses WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) { $success_message = 'Mapping removed.'; } else { $error_message = 'Delete failed: ' . $conn->error; }
                $stmt->close();
            } else { $error_message = 'Prepare failed: ' . $conn->error; }
        }
    }
}

// Data for UI
$lecturers = $conn->query("SELECT id, name, department_id FROM lecturers WHERE is_active = 1 ORDER BY name");
$courses = $conn->query("SELECT c.id, c.name, c.code, d.name AS department_name FROM courses c JOIN departments d ON d.id = c.department_id WHERE c.is_active = 1 ORDER BY c.name");
$mappings = $conn->query("SELECT lc.id, l.name AS lecturer_name, c.name AS course_name, c.code
                          FROM lecturer_courses lc
                          JOIN lecturers l ON l.id = lc.lecturer_id
                          JOIN courses c ON c.id = lc.course_id
                          ORDER BY l.name, c.name");
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

        <div class="card m-3">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add" />
                    <div class="col-md-5">
                        <label class="form-label">Lecturer</label>
                        <select name="lecturer_id" class="form-select" required>
                            <option value="">Choose lecturer...</option>
                            <?php if ($lecturers) { while ($l = $lecturers->fetch_assoc()) { ?>
                                <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Choose course...</option>
                            <?php if ($courses) { while ($c = $courses->fetch_assoc()) { ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' (' . $c['code'] . ') - ' . $c['department_name']); ?></option>
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
                                <p>No mappings yet. Add some above.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

