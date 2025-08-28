<?php
$pageTitle = 'Time Slots Management';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $duration = (int)($_POST['duration'] ?? 60);
        $is_break = isset($_POST['is_break']) ? 1 : 0;
        $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssiii', $start_time, $end_time, $duration, $is_break, $is_mandatory);
            if ($stmt->execute()) { $success_message = 'Time slot added.'; } else { $error_message = 'Insert failed: ' . $conn->error; }
            $stmt->close();
        } else { $error_message = 'Prepare failed: ' . $conn->error; }
    } elseif ($_POST['action'] === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $duration = (int)($_POST['duration'] ?? 60);
        $is_break = isset($_POST['is_break']) ? 1 : 0;
        $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE time_slots SET start_time = ?, end_time = ?, duration = ?, is_break = ?, is_mandatory = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('ssiiii', $start_time, $end_time, $duration, $is_break, $is_mandatory, $id);
            if ($stmt->execute()) { $success_message = 'Time slot updated.'; } else { $error_message = 'Update failed: ' . $conn->error; }
            $stmt->close();
        } else { $error_message = 'Prepare failed: ' . $conn->error; }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM time_slots WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) { $success_message = 'Time slot deleted.'; } else { $error_message = 'Delete failed: ' . $conn->error; }
            $stmt->close();
        } else { $error_message = 'Prepare failed: ' . $conn->error; }
    } elseif ($_POST['action'] === 'generate_hourly') {
        $created = 0;
        for ($h = 7; $h < 20; $h++) {
            $start = sprintf('%02d:00:00', $h);
            $end = sprintf('%02d:00:00', $h + 1);
            $stmt = $conn->prepare("SELECT id FROM time_slots WHERE start_time = ? AND end_time = ? LIMIT 1");
            $stmt->bind_param('ss', $start, $end);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, 60, 0, 1)");
                $ins->bind_param('ss', $start, $end);
                $ins->execute();
                $ins->close();
                $created++;
            }
            $stmt->close();
        }
        $success_message = "Generated hourly slots (07:00-20:00). Created: $created";
    }
}

$slots = $conn->query("SELECT * FROM time_slots ORDER BY start_time");
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-clock me-2"></i>Time Slots</h4>
            <form method="POST" class="m-0">
                <input type="hidden" name="action" value="generate_hourly" />
                <button type="submit" class="btn btn-outline-primary"><i class="fas fa-bolt me-2"></i>Generate Hourly 07–20</button>
            </form>
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
                    <div class="col-md-3">
                        <label class="form-label">Start Time</label>
                        <input type="time" name="start_time" class="form-control" required />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Time</label>
                        <input type="time" name="end_time" class="form-control" required />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Duration (min)</label>
                        <input type="number" name="duration" class="form-control" value="60" min="1" />
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check me-3">
                            <input class="form-check-input" type="checkbox" name="is_break" id="is_break" />
                            <label class="form-check-label" for="is_break">Break</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_mandatory" id="is_mandatory" checked />
                            <label class="form-check-label" for="is_mandatory">Mandatory</label>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Add</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive m-3">
            <table class="table" id="timeSlotsTable">
                <thead>
                    <tr>
                        <th>Start</th>
                        <th>End</th>
                        <th>Duration</th>
                        <th>Break</th>
                        <th>Mandatory</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($slots && $slots->num_rows > 0): ?>
                        <?php while ($slot = $slots->fetch_assoc()): ?>
                            <tr>
                                <form method="POST">
                                    <td><input type="time" class="form-control" name="start_time" value="<?php echo substr($slot['start_time'],0,5); ?>" required /></td>
                                    <td><input type="time" class="form-control" name="end_time" value="<?php echo substr($slot['end_time'],0,5); ?>" required /></td>
                                    <td style="max-width:120px"><input type="number" class="form-control" name="duration" value="<?php echo (int)$slot['duration']; ?>" /></td>
                                    <td><input type="checkbox" class="form-check-input" name="is_break" <?php echo $slot['is_break'] ? 'checked' : ''; ?> /></td>
                                    <td><input type="checkbox" class="form-check-input" name="is_mandatory" <?php echo $slot['is_mandatory'] ? 'checked' : ''; ?> /></td>
                                    <td>
                                        <input type="hidden" name="id" value="<?php echo $slot['id']; ?>" />
                                        <button class="btn btn-sm btn-outline-primary" name="action" value="update"><i class="fas fa-save"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" onclick="return confirm('Delete this time slot?')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </form>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>No time slots yet. Use the form above or Generate Hourly.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

