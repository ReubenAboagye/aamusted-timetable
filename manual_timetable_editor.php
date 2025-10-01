<?php
/**
 * Manual Timetable Editor
 * Allows manual editing of specific timetable versions
 */

$pageTitle = 'Manual Timetable Editor';
include 'connect.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Get parameters
$stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$version = isset($_GET['version']) ? $_GET['version'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'second';

if (!$stream_id || !$version) {
    header('Location: saved_timetable.php');
    exit;
}

// Get stream name
$stream_query = "SELECT name FROM streams WHERE id = ? AND is_active = 1";
$stmt = $conn->prepare($stream_query);
$stmt->bind_param("i", $stream_id);
$stmt->execute();
$stream_result = $stmt->get_result();
$stream_name = $stream_result->fetch_assoc()['name'] ?? 'Unknown Stream';
$stmt->close();

// Handle manual edits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_entry') {
        $entry_id = intval($_POST['entry_id']);
        $new_day_id = intval($_POST['day_id']);
        $new_time_slot_id = intval($_POST['time_slot_id']);
        $new_room_id = intval($_POST['room_id']);
        
        // Check for conflicts before updating
        $conflict_check = "
            SELECT COUNT(*) as conflict_count
            FROM timetable t1
            WHERE t1.id != ? 
            AND t1.day_id = ? 
            AND t1.time_slot_id = ? 
            AND (
                t1.room_id = ? OR
                t1.class_course_id = (
                    SELECT t2.class_course_id FROM timetable t2 WHERE t2.id = ?
                ) OR
                t1.lecturer_course_id = (
                    SELECT t2.lecturer_course_id FROM timetable t2 WHERE t2.id = ?
                )
            )
            AND t1.semester = ? AND t1.version = ?
        ";
        
        $stmt = $conn->prepare($conflict_check);
        $stmt->bind_param("iiiiiiis", $entry_id, $new_day_id, $new_time_slot_id, $new_room_id, $entry_id, $entry_id, $semester, $version);
        $stmt->execute();
        $conflict_result = $stmt->get_result();
        $conflict_count = $conflict_result->fetch_assoc()['conflict_count'];
        $stmt->close();
        
        if ($conflict_count > 0) {
            $error_message = "Cannot update: This would create a conflict with existing entries.";
        } else {
            // Update the entry
            $update_query = "UPDATE timetable SET day_id = ?, time_slot_id = ?, room_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("iiii", $new_day_id, $new_time_slot_id, $new_room_id, $entry_id);
            
            if ($stmt->execute()) {
                $success_message = "Timetable entry updated successfully!";
            } else {
                $error_message = "Failed to update entry: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    if ($action === 'delete_entry') {
        $entry_id = intval($_POST['entry_id']);
        
        $delete_query = "DELETE FROM timetable WHERE id = ? AND version = ? AND semester = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("iss", $entry_id, $version, $semester);
        
        if ($stmt->execute()) {
            $success_message = "Timetable entry deleted successfully!";
        } else {
            $error_message = "Failed to delete entry: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get timetable entries for this version
$timetable_query = "
    SELECT t.*, 
           l.name as lecturer_name,
           c.name as course_name,
           cl.name as class_name,
           d.name as day_name,
           ts.start_time, ts.end_time,
           r.name as room_name
    FROM timetable t
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes cl ON cc.class_id = cl.id
    JOIN courses c ON cc.course_id = c.id
    JOIN days d ON t.day_id = d.id
    JOIN time_slots ts ON t.time_slot_id = ts.id
    JOIN rooms r ON t.room_id = r.id
    WHERE cl.stream_id = ? AND t.version = ? AND t.semester = ?
    ORDER BY d.id, ts.start_time, cl.name
";

$stmt = $conn->prepare($timetable_query);
$stmt->bind_param("iss", $stream_id, $version, $semester);
$stmt->execute();
$timetable_entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get available days, time slots, and rooms for editing
$days_query = "SELECT id, name FROM days WHERE is_active = 1 ORDER BY id";
$days_result = $conn->query($days_query);
$available_days = $days_result->fetch_all(MYSQLI_ASSOC);

$time_slots_query = "SELECT id, start_time, end_time FROM time_slots WHERE is_active = 1 ORDER BY start_time";
$time_slots_result = $conn->query($time_slots_query);
$available_time_slots = $time_slots_result->fetch_all(MYSQLI_ASSOC);

$rooms_query = "SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY name";
$rooms_result = $conn->query($rooms_query);
$available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content" id="mainContent">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-edit me-2"></i>Manual Timetable Editor</h2>
            <p class="text-muted mb-0">
                <strong>Stream:</strong> <?php echo htmlspecialchars($stream_name); ?> | 
                <strong>Version:</strong> <?php echo htmlspecialchars($version); ?> | 
                <strong>Semester:</strong> <?php echo htmlspecialchars(ucfirst($semester)); ?>
            </p>
        </div>
        <div>
            <a href="advanced_timetable_editor.php?stream_id=<?php echo $stream_id; ?>&version=<?php echo urlencode($version); ?>&semester=<?php echo urlencode($semester); ?>" class="btn btn-primary me-2">
                <i class="fas fa-table"></i> Grid View
            </a>
            <a href="saved_timetable.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to Versions
            </a>
            <a href="generate_timetable.php?edit_stream_id=<?php echo $stream_id; ?>&version=<?php echo urlencode($version); ?>&semester=<?php echo urlencode($semester); ?>" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> View Version
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Timetable Entries -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-calendar-alt me-2"></i>Timetable Entries (<?php echo count($timetable_entries); ?> entries)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($timetable_entries)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times text-muted fa-3x mb-3"></i>
                    <h5 class="text-muted">No timetable entries found</h5>
                    <p class="text-muted">This version appears to be empty.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Class</th>
                                <th>Course</th>
                                <th>Lecturer</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetable_entries as $entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entry['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['lecturer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['day_name']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['start_time'] . ' - ' . $entry['end_time']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['room_name']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="editEntry(<?php echo $entry['id']; ?>, '<?php echo htmlspecialchars($entry['day_name']); ?>', '<?php echo htmlspecialchars($entry['start_time'] . ' - ' . $entry['end_time']); ?>', '<?php echo htmlspecialchars($entry['room_name']); ?>', <?php echo $entry['day_id']; ?>, <?php echo $entry['time_slot_id']; ?>, <?php echo $entry['room_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" onclick="deleteEntry(<?php echo $entry['id']; ?>, '<?php echo htmlspecialchars($entry['class_name'] . ' - ' . $entry['course_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Entry Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Timetable Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_entry">
                    <input type="hidden" name="entry_id" id="editEntryId">
                    
                    <div class="mb-3">
                        <label class="form-label">Current Entry</label>
                        <div class="form-control-plaintext" id="currentEntryInfo"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editDay" class="form-label">Day</label>
                        <select class="form-select" name="day_id" id="editDay" required>
                            <?php foreach ($available_days as $day): ?>
                                <option value="<?php echo $day['id']; ?>"><?php echo htmlspecialchars($day['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editTimeSlot" class="form-label">Time Slot</label>
                        <select class="form-select" name="time_slot_id" id="editTimeSlot" required>
                            <?php foreach ($available_time_slots as $slot): ?>
                                <option value="<?php echo $slot['id']; ?>"><?php echo htmlspecialchars($slot['start_time'] . ' - ' . $slot['end_time']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editRoom" class="form-label">Room</label>
                        <select class="form-select" name="room_id" id="editRoom" required>
                            <?php foreach ($available_rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name'] . ' (Capacity: ' . $room['capacity'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Entry Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_entry">
                    <input type="hidden" name="entry_id" id="deleteEntryId">
                    
                    <p>Are you sure you want to delete this timetable entry?</p>
                    <p class="text-danger"><strong id="deleteEntryInfo"></strong></p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editEntry(entryId, dayName, timeSlot, roomName, currentDayId, currentTimeSlotId, currentRoomId) {
    document.getElementById('editEntryId').value = entryId;
    document.getElementById('currentEntryInfo').textContent = `${dayName} | ${timeSlot} | ${roomName}`;
    document.getElementById('editDay').value = currentDayId;
    document.getElementById('editTimeSlot').value = currentTimeSlotId;
    document.getElementById('editRoom').value = currentRoomId;
    
    var modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

function deleteEntry(entryId, entryInfo) {
    document.getElementById('deleteEntryId').value = entryId;
    document.getElementById('deleteEntryInfo').textContent = entryInfo;
    
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
