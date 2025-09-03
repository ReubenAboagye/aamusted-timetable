<?php
$pageTitle = 'Manage Time Slots';
include 'includes/header.php';
include 'includes/sidebar.php';

$success_message = '';
$error_message = '';

// Fetch streams for selector
$streams_rs = $conn->query("SELECT id, name FROM streams WHERE is_active = 1 ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	$action = $_POST['action'];
	if ($action === 'add') {
		$start_time = $conn->real_escape_string($_POST['start_time']);
		$end_time = $conn->real_escape_string($_POST['end_time']);
		$duration = (int)$_POST['duration'];
		$is_break = isset($_POST['is_break']) ? 1 : 0;
		$is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

		$stmt = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, ?, ?, ?)");
		$stmt->bind_param('ssiii', $start_time, $end_time, $duration, $is_break, $is_mandatory);
		if ($stmt->execute()) {
			$success_message = 'Time slot added successfully.';
		} else {
			$error_message = 'Failed to add time slot: ' . $conn->error;
		}
		$stmt->close();
	} elseif ($action === 'edit') {
		$id = (int)$_POST['id'];
		$start_time = $conn->real_escape_string($_POST['start_time']);
		$end_time = $conn->real_escape_string($_POST['end_time']);
		$duration = (int)$_POST['duration'];
		$is_break = isset($_POST['is_break']) ? 1 : 0;
		$is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

		$stmt = $conn->prepare("UPDATE time_slots SET start_time = ?, end_time = ?, duration = ?, is_break = ?, is_mandatory = ? WHERE id = ?");
		$stmt->bind_param('ssiiii', $start_time, $end_time, $duration, $is_break, $is_mandatory, $id);
		if ($stmt->execute()) {
			$success_message = 'Time slot updated successfully.';
		} else {
			$error_message = 'Failed to update time slot: ' . $conn->error;
		}
		$stmt->close();
	} elseif ($action === 'delete') {
		$id = (int)$_POST['id'];
		$stmt = $conn->prepare("DELETE FROM time_slots WHERE id = ?");
		$stmt->bind_param('i', $id);
		if ($stmt->execute()) {
			$success_message = 'Time slot deleted successfully.';
		} else {
			$error_message = 'Failed to delete time slot: ' . $conn->error;
		}
		$stmt->close();
	}
}

// Fetch time slots
$ts_rs = $conn->query("SELECT id, start_time, end_time, duration, is_break, is_mandatory FROM time_slots ORDER BY start_time");
?>

<div class="main-content" id="mainContent">
	<div class="table-container">
		<div class="table-header d-flex justify-content-between align-items-center">
			<h4><i class="fas fa-clock me-2"></i>Time Slots Management</h4>
			<div>
				<select id="streamSelector" class="form-select d-inline-block me-2" style="width:220px;">
					<option value="0">Select Stream (all slots)</option>
					<?php while ($s = $streams_rs->fetch_assoc()): ?>
						<option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
					<?php endwhile; ?>
				</select>
				<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTimeSlotModal">
					<i class="fas fa-plus me-2"></i>Add Time Slot
				</button>
			</div>
		</div>

		<?php if ($success_message): ?>
			<div class="alert alert-success alert-dismissible fade show m-3" role="alert"><?php echo $success_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
		<?php endif; ?>
		<?php if ($error_message): ?>
			<div class="alert alert-danger alert-dismissible fade show m-3" role="alert"><?php echo $error_message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
		<?php endif; ?>

		<table class="table table-striped m-3" id="timeslotTable">
			<thead>
				<tr><th>Start</th><th>End</th><th>Duration (min)</th><th>Break</th><th>Mandatory</th><th>Assigned</th><th>Actions</th></tr>
			</thead>
			<tbody>
				<?php while ($ts = $ts_rs->fetch_assoc()): ?>
					<tr data-timeslot-id="<?php echo $ts['id']; ?>">
						<td><?php echo substr($ts['start_time'],0,5); ?></td>
						<td><?php echo substr($ts['end_time'],0,5); ?></td>
						<td><?php echo htmlspecialchars($ts['duration']); ?></td>
						<td><?php echo $ts['is_break'] ? 'Yes' : 'No'; ?></td>
						<td><?php echo $ts['is_mandatory'] ? 'Yes' : 'No'; ?></td>
						<td class="assigned-cell">-</td>
						<td>
							<button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editTimeSlotModal<?php echo $ts['id']; ?>">Edit</button>
							<form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this time slot?');">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="id" value="<?php echo $ts['id']; ?>">
								<button type="submit" class="btn btn-sm btn-danger">Delete</button>
							</form>
						</td>
					</tr>

					<!-- Edit Modal -->
					<div class="modal fade" id="editTimeSlotModal<?php echo $ts['id']; ?>" tabindex="-1" aria-hidden="true">
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-header"><h5 class="modal-title">Edit Time Slot</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
								<form method="post">
									<div class="modal-body">
										<input type="hidden" name="action" value="edit">
										<input type="hidden" name="id" value="<?php echo $ts['id']; ?>">
										<div class="mb-3"><label class="form-label">Start Time</label><input type="time" name="start_time" class="form-control" value="<?php echo $ts['start_time']; ?>" required></div>
										<div class="mb-3"><label class="form-label">End Time</label><input type="time" name="end_time" class="form-control" value="<?php echo $ts['end_time']; ?>" required></div>
										<div class="mb-3"><label class="form-label">Duration (minutes)</label><input type="number" name="duration" class="form-control" value="<?php echo $ts['duration']; ?>" required></div>
										<div class="form-check mb-3"><input type="checkbox" name="is_break" class="form-check-input" id="ts_break_<?php echo $ts['id']; ?>" <?php echo $ts['is_break'] ? 'checked' : ''; ?>><label class="form-check-label" for="ts_break_<?php echo $ts['id']; ?>">Is Break</label></div>
										<div class="form-check mb-3"><input type="checkbox" name="is_mandatory" class="form-check-input" id="ts_mand_<?php echo $ts['id']; ?>" <?php echo $ts['is_mandatory'] ? 'checked' : ''; ?>><label class="form-check-label" for="ts_mand_<?php echo $ts['id']; ?>">Is Mandatory</label></div>
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

<script>
(function(){
	const selector = document.getElementById('streamSelector');
	selector.addEventListener('change', function(){
		const streamId = selector.value;
		// reset assigned cells
		document.querySelectorAll('#timeslotTable tbody tr').forEach(r => {
			r.querySelector('.assigned-cell').textContent = '-';
			r.classList.remove('assigned');
		});
		if (streamId && streamId !== '0') {
			fetch('get_stream_time_slots.php?stream_id=' + encodeURIComponent(streamId))
				.then(r => r.json())
				.then(ids => {
					ids.forEach(id => {
						const row = document.querySelector('#timeslotTable tbody tr[data-timeslot-id="' + id + '"]');
						if (row) {
							row.querySelector('.assigned-cell').textContent = 'Yes';
							row.classList.add('assigned');
						}
					});
				})
				.catch(() => {});
		}
	});
})();
</script>

<?php include 'includes/footer.php'; ?>
