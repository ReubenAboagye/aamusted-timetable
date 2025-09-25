<?php
$pageTitle = 'Manage Levels';
include 'includes/header.php';
include 'includes/sidebar.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	$action = $_POST['action'];
	if ($action === 'add') {
		$name = $conn->real_escape_string($_POST['name']);
		$code = $conn->real_escape_string($_POST['code']);
		$is_active = isset($_POST['is_active']) ? 1 : 0;

		$stmt = $conn->prepare("INSERT INTO levels (name, code, is_active, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
		$stmt->bind_param('ssi', $name, $code, $is_active);
		if ($stmt->execute()) {
			$success_message = 'Level added successfully.';
		} else {
			$error_message = 'Failed to add level: ' . $conn->error;
		}
		$stmt->close();
	} elseif ($action === 'edit') {
		$id = (int)$_POST['id'];
		$name = $conn->real_escape_string($_POST['name']);
		$code = $conn->real_escape_string($_POST['code']);
		$is_active = isset($_POST['is_active']) ? 1 : 0;

		$stmt = $conn->prepare("UPDATE levels SET name = ?, code = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
		$stmt->bind_param('ssii', $name, $code, $is_active, $id);
		if ($stmt->execute()) {
			$success_message = 'Level updated successfully.';
		} else {
			$error_message = 'Failed to update level: ' . $conn->error;
		}
		$stmt->close();
	} elseif ($action === 'delete') {
		$id = (int)$_POST['id'];
		$stmt = $conn->prepare("DELETE FROM levels WHERE id = ?");
		$stmt->bind_param('i', $id);
		if ($stmt->execute()) {
			$success_message = 'Level deleted successfully.';
		} else {
			$error_message = 'Failed to delete level: ' . $conn->error;
		}
		$stmt->close();
	}
}

// Fetch levels
$levels_rs = $conn->query("SELECT id, name, code, is_active FROM levels ORDER BY id");
?>

<div class="main-content" id="mainContent">
	<div class="table-container">
		<div class="table-header d-flex justify-content-between align-items-center">
			<h4><i class="fas fa-layer-group me-2"></i>Levels Management</h4>
			<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLevelModal">
				<i class="fas fa-plus me-2"></i>Add Level
			</button>
		</div>

		<?php if ($success_message): ?>
			<div class="alert alert-success alert-dismissible fade show m-3" role="alert"><?php echo $success_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
		<?php endif; ?>
		<?php if ($error_message): ?>
			<div class="alert alert-danger alert-dismissible fade show m-3" role="alert"><?php echo $error_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
		<?php endif; ?>

		<table class="table table-striped m-3">
				<thead>
					<tr><th>Name</th><th>Code</th><th>Active</th><th>Actions</th></tr>
				</thead>
			<tbody>
				<?php while ($lvl = $levels_rs->fetch_assoc()): ?>
						<tr>
							<td><?php echo htmlspecialchars($lvl['name']); ?></td>
							<td><?php echo htmlspecialchars($lvl['code']); ?></td>
							<td><?php echo $lvl['is_active'] ? 'Yes' : 'No'; ?></td>
						<td>
							<button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editLevelModal<?php echo $lvl['id']; ?>">Edit</button>
							<form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this level?');">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="id" value="<?php echo $lvl['id']; ?>">
								<button type="submit" class="btn btn-sm btn-danger">Delete</button>
							</form>
						</td>
					</tr>

					<!-- Edit Modal -->
					<div class="modal fade" id="editLevelModal<?php echo $lvl['id']; ?>" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-header"><h5 class="modal-title">Edit Level</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
								<form method="post">
									<div class="modal-body">
										<input type="hidden" name="action" value="edit">
										<input type="hidden" name="id" value="<?php echo $lvl['id']; ?>">
								<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="<?php echo htmlspecialchars($lvl['name']); ?>"></div>
								<div class="mb-3"><label class="form-label">Code</label><input name="code" class="form-control" value="<?php echo htmlspecialchars($lvl['code']); ?>"></div>
										<div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" id="lvl_active_<?php echo $lvl['id']; ?>" <?php echo $lvl['is_active'] ? 'checked' : ''; ?>><label class="form-check-label" for="lvl_active_<?php echo $lvl['id']; ?>">Active</label></div>
									</div>
									<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
								</form>
							</div>
						</div>
					</div>
				<?php endwhile; ?>
			</tbody>
		</table>
	</div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addLevelModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header"><h5 class="modal-title">Add Level</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="add">
						<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
						<div class="mb-3"><label class="form-label">Code</label><input name="code" class="form-control"></div>
					<div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" id="add_lvl_active" checked><label class="form-check-label" for="add_lvl_active">Active</label></div>
				</div>
				<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
			</form>
		</div>
	</div>
</div>

<?php include 'includes/footer.php'; ?>
