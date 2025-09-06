<?php
$pageTitle = 'Manage Time Slots';
include 'includes/header.php';
include 'includes/sidebar.php';

$success_message = '';
$error_message = '';

// Determine current stream from stream manager (header/session)
include_once 'includes/stream_manager.php';
$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	$action = $_POST['action'];
			if ($action === 'add') {
		$start_time = $conn->real_escape_string($_POST['start_time']);
		$duration = (int)$_POST['duration'];
		$is_break = isset($_POST['is_break']) ? 1 : 0;
		$is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

		// Validate that start time is on the hour (XX:00)
		$start_minutes = (int)substr($start_time, -2);
		if ($start_minutes !== 0) {
			$error_message = 'Start time must be on the hour (XX:00 format).';
		} else {
			// Calculate end time as start time + duration minutes
			$start_obj = new DateTime($start_time);
			$end_obj = clone $start_obj;
			$end_obj->add(new DateInterval('PT' . $duration . 'M'));
			$calculated_end_time = $end_obj->format('H:i:s');
			
			// Use calculated end time
			$end_time = $calculated_end_time;

			$stmt = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param('ssiii', $start_time, $end_time, $duration, $is_break, $is_mandatory);
			if ($stmt->execute()) {
				$success_message = 'Time slot added successfully.';
			} else {
				$error_message = 'Failed to add time slot: ' . $conn->error;
			}
			$stmt->close();
		}
	} elseif ($action === 'add_multiple') {
		$base_start_time = $conn->real_escape_string($_POST['base_start_time']);
		$num_slots = (int)$_POST['num_slots'];
		$duration = (int)$_POST['duration'];
		$is_break = isset($_POST['is_break']) ? 1 : 0;
		$is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;

		// Validate that base start time is on the hour (XX:00)
		$start_minutes = (int)substr($base_start_time, -2);
		if ($start_minutes !== 0) {
			$error_message = 'Base start time must be on the hour (XX:00 format).';
		} else {
			$success_count = 0;
			$error_count = 0;

			$stmt = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param('ssiii', $start_time, $end_time, $duration, $is_break, $is_mandatory);

			for ($i = 0; $i < $num_slots; $i++) {
				// Calculate start time for this slot (XX:00 format)
				$start_time_obj = new DateTime($base_start_time);
				$start_time_obj->add(new DateInterval('PT' . ($i * $duration) . 'M')); // Use flexible duration intervals
				$start_time = $start_time_obj->format('H:i:s');

				// Calculate end time as start time + duration minutes
				$end_time_obj = clone $start_time_obj;
				$end_time_obj->add(new DateInterval('PT' . $duration . 'M'));
				$end_time = $end_time_obj->format('H:i:s');

				if ($stmt->execute()) {
					$success_count++;
				} else {
					$error_count++;
				}
			}
			$stmt->close();

			if ($error_count > 0) {
				$error_message = "Added $success_count time slots successfully. Failed to add $error_count time slots.";
			} else {
				$success_message = "Successfully added $success_count time slots.";
			}
		}
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

// Fetch assigned time slots for current stream so we can mark them server-side
$assigned_time_slot_ids = [];
$stmt_ass = $conn->prepare("SELECT time_slot_id FROM stream_time_slots WHERE stream_id = ? AND is_active = 1");
$stmt_ass->bind_param('i', $current_stream_id);
$stmt_ass->execute();
$resass = $stmt_ass->get_result();
while ($r = $resass->fetch_assoc()) { $assigned_time_slot_ids[] = (int)$r['time_slot_id']; }
$stmt_ass->close();
$assigned_map = array_flip($assigned_time_slot_ids);
?>

<div class="main-content" id="mainContent">
	<div class="table-container">
		<div class="table-header d-flex justify-content-between align-items-center">
			<h4><i class="fas fa-clock me-2"></i>Time Slots Management</h4>
			<div>
				<!-- <span class="me-3">Current Stream: <strong><?php echo htmlspecialchars($streamManager->getCurrentStreamName()); ?></strong></span> -->
				<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTimeSlotModal">
					<i class="fas fa-plus me-2"></i>Add Time Slot
				</button>
				<button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#addMultipleTimeSlotsModal">
					<i class="fas fa-layer-group me-2"></i>Add Multiple
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
						<td>
							<?php if ($ts['is_break']): ?>
								<span class="badge bg-warning text-dark">Break</span>
							<?php else: ?>
								<span class="badge bg-success">Regular</span>
							<?php endif; ?>
						</td>
						<td><?php echo $ts['is_mandatory'] ? 'Yes' : 'No'; ?></td>
						<td class="assigned-cell"><?php echo isset($assigned_map[$ts['id']]) ? 'Yes' : '-'; ?></td>
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

<!-- Add Time Slot Modal -->
<div class="modal fade" id="addTimeSlotModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add New Time Slot</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="add">
					<div class="mb-3">
						<label class="form-label">Start Time</label>
						<input type="time" name="start_time" class="form-control" step="3600" required>
						<small class="form-text text-muted">Must be on the hour (XX:00).</small>
					</div>
					<div class="mb-3">
						<label class="form-label">Duration (minutes)</label>
						<input type="number" name="duration" class="form-control" value="180" min="30" max="480" required>
						<small class="form-text text-muted">Duration in minutes (default: 180 minutes = 3 hours)</small>
					</div>
					<div class="mb-3">
						<label class="form-label">End Time</label>
						<input type="time" name="end_time" class="form-control" step="3600" readonly>
						<small class="form-text text-muted">Automatically calculated as start time + duration</small>
					</div>
					<div class="form-check mb-3">
						<input type="checkbox" name="is_break" class="form-check-input" id="add_ts_break">
						<label class="form-check-label" for="add_ts_break">Is Break</label>
					</div>
					<div class="form-check mb-3">
						<input type="checkbox" name="is_mandatory" class="form-check-input" id="add_ts_mand">
						<label class="form-check-label" for="add_ts_mand">Is Mandatory</label>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Add Time Slot</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Add Multiple Time Slots Modal -->
<div class="modal fade" id="addMultipleTimeSlotsModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Multiple Time Slots</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="add_multiple">
					
					<div class="row">
						<div class="col-md-4">
							<div class="mb-3">
								<label class="form-label">Base Start Time</label>
								<input type="time" name="base_start_time" class="form-control" step="3600" required>
								<small class="form-text text-muted">Must be on the hour (XX:00).</small>
							</div>
						</div>
						<div class="col-md-4">
							<div class="mb-3">
								<label class="form-label">Duration (minutes)</label>
								<input type="number" name="duration" class="form-control" value="180" min="30" max="480" required>
								<small class="form-text text-muted">Duration per slot (default: 180 min = 3 hours)</small>
							</div>
						</div>
						<div class="col-md-4">
							<div class="mb-3">
								<label class="form-label">Number of Slots</label>
								<input type="number" name="num_slots" class="form-control" min="1" max="20" value="5" required>
								<small class="form-text text-muted">How many slots to create (1-20)</small>
							</div>
						</div>
					</div>
					
					<div class="alert alert-info">
						<strong>Format:</strong> Each time slot will have the specified duration, starting on the hour (XX:00).<br>
						<strong>Example:</strong> If you start at 08:00 with 180-minute duration and create 3 slots, you'll get: 08:00-11:00, 11:00-14:00, 14:00-17:00
					</div>
					
					<div class="form-check mb-3">
						<input type="checkbox" name="is_break" class="form-check-input" id="add_multiple_ts_break">
						<label class="form-check-label" for="add_multiple_ts_break">Is Break</label>
					</div>
					<div class="form-check mb-3">
						<input type="checkbox" name="is_mandatory" class="form-check-input" id="add_multiple_ts_mand">
						<label class="form-check-label" for="add_multiple_ts_mand">Is Mandatory</label>
					</div>
					
					<div class="alert alert-info">
						<strong>Preview:</strong> This will create time slots starting from the base time, with the specified interval between each slot.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-success">Add Multiple Time Slots</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Assigned slots are rendered server-side based on the current stream in the header/session -->

<script>
// Auto-calculate end time when start time or duration changes
document.addEventListener('DOMContentLoaded', function() {
    const startTimeInput = document.querySelector('input[name="start_time"]');
    const endTimeInput = document.querySelector('input[name="end_time"]');
    const durationInput = document.querySelector('input[name="duration"]');
    
    function calculateEndTime() {
        const startTime = startTimeInput ? startTimeInput.value : '';
        const duration = durationInput ? parseInt(durationInput.value) || 180 : 180;
        
        if (startTime && endTimeInput) {
            // Create a Date object with the start time
            const startDate = new Date('2000-01-01T' + startTime + ':00');
            // Add duration minutes
            startDate.setMinutes(startDate.getMinutes() + duration);
            // Format as HH:MM
            const endTime = startDate.toTimeString().slice(0, 5);
            endTimeInput.value = endTime;
        }
    }
    
    if (startTimeInput) {
        startTimeInput.addEventListener('change', calculateEndTime);
    }
    
    if (durationInput) {
        durationInput.addEventListener('input', calculateEndTime);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
