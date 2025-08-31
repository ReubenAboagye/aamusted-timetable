<?php
$pageTitle = 'Manage Streams';
include 'includes/header.php';
include 'includes/sidebar.php';

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
        $active_days = isset($_POST['active_days']) ? implode(',', $_POST['active_days']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $sql = "INSERT INTO streams 
                (name, code, description, period_start, period_end, break_start, break_end, active_days, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $name, $code, $description, $period_start, $period_end, $break_start, $break_end, $active_days, $is_active);

        if ($stmt->execute()) {
            $success_message = "✅ Stream added successfully!";
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
        $active_days = isset($_POST['active_days']) ? implode(',', $_POST['active_days']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $sql = "UPDATE streams 
                SET name = ?, code = ?, description = ?, period_start = ?, period_end = ?, break_start = ?, break_end = ?, active_days = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssii", $name, $code, $description, $period_start, $period_end, $break_start, $break_end, $active_days, $is_active, $id);

        if ($stmt->execute()) {
            $success_message = "✅ Stream updated successfully!";
        } else {
            $error_message = "❌ Error updating stream: " . $conn->error;
        }
        $stmt->close();
    }

    // DELETE STREAM (soft delete → set inactive)
    elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];

        $sql = "UPDATE streams SET is_active = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_message = "✅ Stream deleted successfully!";
        } else {
            $error_message = "❌ Error deleting stream: " . $conn->error;
        }
        $stmt->close();
    }
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

        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
            <label class="form-check-label" for="is_active">Active</label>
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
                <td><?= $row['active_days']; ?></td>
                <td><?= $row['is_active'] ? '✅ Active' : '❌ Inactive'; ?></td>
                <td><?= $row['created_at']; ?></td>
                <td>
                    <!-- Delete Form -->
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
