<?php
$pageTitle = 'Manage Streams';
include 'includes/header.php';
include 'includes/sidebar.php';
// Time slots are global; streams select slots via mapping

$success_message = "";
$error_message = "";

// -------------------- ADD / EDIT / DELETE HANDLER --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ADD STREAM
    if ($_POST['action'] === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $description = $conn->real_escape_string($_POST['description']);
        $period_start = $conn->real_escape_string($_POST['period_start']);
        $period_end = $conn->real_escape_string($_POST['period_end']);
        $break_start = $conn->real_escape_string($_POST['break_start']);
        $break_end = $conn->real_escape_string($_POST['break_end']);
        // Store active days as JSON to match DB JSON column
        $active_days = isset($_POST['active_days']) ? json_encode(array_values($_POST['active_days'])) : json_encode([]);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // If this stream is being set as active, deactivate all other streams first
        if ($is_active) {
            $deactivate_sql = "UPDATE streams SET is_active = 0, updated_at = NOW() WHERE is_active = 1";
            $conn->query($deactivate_sql);
        }

        $sql = "INSERT INTO streams 
                (name, code, description, period_start, period_end, break_start, break_end, active_days, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $name, $code, $description, $period_start, $period_end, $break_start, $break_end, $active_days, $is_active);

        if ($stmt->execute()) {
            $stream_id = $conn->insert_id;
            $success_message = "✅ Stream added successfully!";

            // Persist selected time slots mapping if provided
            if (!empty($_POST['time_slots']) && is_array($_POST['time_slots'])) {
                $ins = $conn->prepare("INSERT IGNORE INTO stream_time_slots (stream_id, time_slot_id, is_active) VALUES (?, ?, 1)");
                foreach ($_POST['time_slots'] as $slot_id) {
                    $sid = (int)$slot_id;
                    $ins->bind_param("ii", $stream_id, $sid);
                    $ins->execute();
                }
                if (isset($ins) && $ins) $ins->close();
            }
        } else {
            $error_message = "❌ Error adding stream: " . $conn->error;
        }
        $stmt->close();
    }

    // EDIT STREAM
    elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $description = $conn->real_escape_string($_POST['description']);
        $period_start = $conn->real_escape_string($_POST['period_start']);
        $period_end = $conn->real_escape_string($_POST['period_end']);
        $break_start = $conn->real_escape_string($_POST['break_start']);
        $break_end = $conn->real_escape_string($_POST['break_end']);
        // Store active days as JSON to match DB JSON column
        $active_days = isset($_POST['active_days']) ? json_encode(array_values($_POST['active_days'])) : json_encode([]);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // If this stream is being set as active, deactivate all other streams first
        if ($is_active) {
            $deactivate_sql = "UPDATE streams SET is_active = 0, updated_at = NOW() WHERE is_active = 1 AND id != ?";
            $deactivate_stmt = $conn->prepare($deactivate_sql);
            $deactivate_stmt->bind_param("i", $id);
            $deactivate_stmt->execute();
            $deactivate_stmt->close();
        }

        $sql = "UPDATE streams 
                SET name = ?, code = ?, description = ?, period_start = ?, period_end = ?, break_start = ?, break_end = ?, active_days = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssii", $name, $code, $description, $period_start, $period_end, $break_start, $break_end, $active_days, $is_active, $id);

        if ($stmt->execute()) {
            $success_message = "✅ Stream updated successfully!";

            // Update selected time slots mapping
            if (isset($_POST['time_slots']) && is_array($_POST['time_slots'])) {
                // Clear existing
                $del = $conn->prepare("DELETE FROM stream_time_slots WHERE stream_id = ?");
                $del->bind_param("i", $id);
                $del->execute();
                $del->close();

                // Insert new selections
                $ins = $conn->prepare("INSERT IGNORE INTO stream_time_slots (stream_id, time_slot_id, is_active) VALUES (?, ?, 1)");
                foreach ($_POST['time_slots'] as $slot_id) {
                    $sid = (int)$slot_id;
                    $ins->bind_param("ii", $id, $sid);
                    $ins->execute();
                }
                if (isset($ins) && $ins) $ins->close();
            }
        } else {
            $error_message = "❌ Error updating stream: " . $conn->error;
        }
        $stmt->close();
    }

    // ACTIVATE STREAM (make this stream active and deactivate all others)
    elseif ($_POST['action'] === 'activate' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];

        // Start transaction to ensure data consistency
        $conn->begin_transaction();

        try {
            // Deactivate all streams first
            $deactivate_sql = "UPDATE streams SET is_active = 0, updated_at = NOW() WHERE is_active = 1";
            $conn->query($deactivate_sql);

            // Activate the selected stream
            $activate_sql = "UPDATE streams SET is_active = 1, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($activate_sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $conn->commit();
                $success_message = "✅ Stream activated successfully! All other streams have been deactivated.";
            } else {
                throw new Exception("Error activating stream: " . $conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "❌ " . $e->getMessage();
        }
    }

    // DEACTIVATE STREAM (soft delete → set inactive)
    elseif ($_POST['action'] === 'deactivate' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];

        $sql = "UPDATE streams SET is_active = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_message = "✅ Stream deactivated successfully!";
        } else {
            $error_message = "❌ Error deactivating stream: " . $conn->error;
        }
        $stmt->close();
    }

    // No regenerate action needed with mapping model
}

// -------------------- FETCH ALL STREAMS --------------------
$result = $conn->query("SELECT * FROM streams ORDER BY created_at DESC");
?>

<div class="main-content" id="mainContent">

<div class="container mt-4">
    <h2 class="mb-3">Manage Streams</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= $success_message; ?></div>
    <?php elseif ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message; ?></div>
    <?php endif; ?>

    <!-- ADD STREAM FORM -->
    <form method="POST" class="border p-3 rounded mb-4">
        <input type="hidden" name="action" value="add">

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Stream Name *</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Stream Code *</label>
                <input type="text" name="code" class="form-control" placeholder="e.g. REG" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control">
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label">Period Start</label>
                <input type="time" name="period_start" class="form-control">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Period End</label>
                <input type="time" name="period_end" class="form-control">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Break Start</label>
                <input type="time" name="break_start" class="form-control">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Break End</label>
                <input type="time" name="break_end" class="form-control">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Active Days</label><br>
            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                <label class="me-2"><input type="checkbox" name="active_days[]" value="<?= $day; ?>"> <?= $day; ?></label>
            <?php endforeach; ?>
        </div>

        <?php 
        // Fetch global time slots to allow selection for this stream
        $global_slots_rs = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
        ?>
        <div class="mb-3">
            <label class="form-label">Select Time Slots for this Stream</label>
            <div class="row">
                <?php while ($gs = $global_slots_rs && $global_slots_rs->fetch_assoc()): ?>
                    <div class="col-md-3">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" name="time_slots[]" value="<?= $gs['id']; ?>">
                            <?= htmlspecialchars(substr($gs['start_time'], 0, 5) . ' - ' . substr($gs['end_time'], 0, 5)); ?>
                        </label>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
            <label class="form-check-label" for="is_active">Active (will deactivate all other streams)</label>
        </div>

        <button type="submit" class="btn btn-primary">Add Stream</button>
    </form>

    <!-- STREAMS TABLE -->
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Description</th>
                <th>Periods</th>
                <th>Break</th>
                <th>Days</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']); ?></td>
                <td><?= htmlspecialchars($row['code']); ?></td>
                <td><?= htmlspecialchars($row['description']); ?></td>
                <td><?= $row['period_start'] . " - " . $row['period_end']; ?></td>
                <td><?= $row['break_start'] . " - " . $row['break_end']; ?></td>
                <td><?php
                    $days = $row['active_days'];
                    $decoded = [];
                    if ($days !== null && $days !== '') {
                        $tmp = json_decode($days, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                            $decoded = $tmp;
                        } else {
                            // Fallback for legacy comma-separated values
                            $decoded = array_filter(array_map('trim', explode(',', $days)));
                        }
                    }
                    echo htmlspecialchars(implode(', ', $decoded));
                ?></td>
                <td><?= $row['is_active'] ? '✅ Active' : '❌ Inactive'; ?></td>
                <td><?= $row['created_at']; ?></td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <?php if ($row['is_active']): ?>
                            <span class="badge bg-success mb-1">Currently Active</span>
                            <!-- Deactivate Form -->
                            <form method="POST" class="mb-0">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                <button type="submit" class="btn btn-warning btn-sm w-100" onclick="return confirm('Are you sure you want to deactivate this stream?')">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <!-- Activate Form -->
                            <form method="POST" class="mb-0">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('This will deactivate all other streams. Continue?')">Activate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
