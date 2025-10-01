<?php
/**
 * Enhanced Manual Timetable Editor with Drag & Drop
 * Advanced interface for manual timetable editing
 */

$pageTitle = 'Advanced Timetable Editor';
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

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    if ($action === 'update_entry') {
        $entry_id = intval($_POST['entry_id']);
        $new_day_id = intval($_POST['day_id']);
        $new_time_slot_id = intval($_POST['time_slot_id']);
        $new_room_id = intval($_POST['room_id']);
        
        // Check for conflicts
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
            $response['message'] = 'This would create a conflict with existing entries.';
        } else {
            $update_query = "UPDATE timetable SET day_id = ?, time_slot_id = ?, room_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("iiii", $new_day_id, $new_time_slot_id, $new_room_id, $entry_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Entry updated successfully!';
            } else {
                $response['message'] = 'Failed to update entry: ' . $conn->error;
            }
            $stmt->close();
        }
    }
    
    echo json_encode($response);
    exit;
}

// Get timetable entries organized by day and time
$timetable_query = "
    SELECT t.*, 
           l.name as lecturer_name,
           c.name as course_name,
           cl.name as class_name,
           d.name as day_name, d.id as day_id,
           ts.start_time, ts.end_time, ts.id as time_slot_id,
           r.name as room_name, r.id as room_id
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

// Get available days, time slots, and rooms
$days_query = "SELECT id, name FROM days WHERE is_active = 1 ORDER BY id";
$days_result = $conn->query($days_query);
$available_days = $days_result->fetch_all(MYSQLI_ASSOC);

$time_slots_query = "SELECT id, start_time, end_time FROM time_slots WHERE is_active = 1 ORDER BY start_time";
$time_slots_result = $conn->query($time_slots_query);
$available_time_slots = $time_slots_result->fetch_all(MYSQLI_ASSOC);

$rooms_query = "SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY name";
$rooms_result = $conn->query($rooms_query);
$available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);

// Organize entries by day and time slot for grid display
$timetable_grid = [];
foreach ($timetable_entries as $entry) {
    $day_id = $entry['day_id'];
    $time_slot_id = $entry['time_slot_id'];
    
    if (!isset($timetable_grid[$day_id])) {
        $timetable_grid[$day_id] = [];
    }
    if (!isset($timetable_grid[$day_id][$time_slot_id])) {
        $timetable_grid[$day_id][$time_slot_id] = [];
    }
    
    $timetable_grid[$day_id][$time_slot_id][] = $entry;
}
?>

<div class="main-content" id="mainContent">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-calendar-alt me-2"></i>Advanced Timetable Editor</h2>
            <p class="text-muted mb-0">
                <strong>Stream:</strong> <?php echo htmlspecialchars($stream_name); ?> | 
                <strong>Version:</strong> <?php echo htmlspecialchars($version); ?> | 
                <strong>Semester:</strong> <?php echo htmlspecialchars(ucfirst($semester)); ?>
            </p>
        </div>
        <div>
            <a href="manual_timetable_editor.php?stream_id=<?php echo $stream_id; ?>&version=<?php echo urlencode($version); ?>&semester=<?php echo urlencode($semester); ?>" class="btn btn-outline-secondary me-2">
                <i class="fas fa-list"></i> List View
            </a>
            <a href="saved_timetable.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Back to Versions
            </a>
            <a href="generate_timetable.php?edit_stream_id=<?php echo $stream_id; ?>&version=<?php echo urlencode($version); ?>&semester=<?php echo urlencode($semester); ?>" class="btn btn-primary">
                <i class="fas fa-eye"></i> View Version
            </a>
        </div>
    </div>

    <!-- Messages -->
    <div id="messageContainer"></div>

    <!-- Timetable Grid -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-table me-2"></i>Timetable Grid View
                <small class="text-muted">(Drag entries to reschedule)</small>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered timetable-grid">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 120px;">Time</th>
                            <?php foreach ($available_days as $day): ?>
                                <th class="text-center"><?php echo htmlspecialchars($day['name']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_time_slots as $slot): ?>
                            <tr>
                                <td class="time-slot-header">
                                    <strong><?php echo htmlspecialchars($slot['start_time'] . ' - ' . $slot['end_time']); ?></strong>
                                </td>
                                <?php foreach ($available_days as $day): ?>
                                    <td class="timetable-cell" data-day="<?php echo $day['id']; ?>" data-time-slot="<?php echo $slot['id']; ?>">
                                        <?php if (isset($timetable_grid[$day['id']][$slot['id']])): ?>
                                            <?php foreach ($timetable_grid[$day['id']][$slot['id']] as $entry): ?>
                                                <div class="timetable-entry" 
                                                     data-entry-id="<?php echo $entry['id']; ?>"
                                                     data-class="<?php echo htmlspecialchars($entry['class_name']); ?>"
                                                     data-course="<?php echo htmlspecialchars($entry['course_name']); ?>"
                                                     data-lecturer="<?php echo htmlspecialchars($entry['lecturer_name']); ?>"
                                                     data-room="<?php echo htmlspecialchars($entry['room_name']); ?>"
                                                     draggable="true">
                                                    <div class="entry-content">
                                                        <div class="entry-class"><?php echo htmlspecialchars($entry['class_name']); ?></div>
                                                        <div class="entry-course"><?php echo htmlspecialchars($entry['course_name']); ?></div>
                                                        <div class="entry-lecturer"><?php echo htmlspecialchars($entry['lecturer_name']); ?></div>
                                                        <div class="entry-room"><?php echo htmlspecialchars($entry['room_name']); ?></div>
                                                    </div>
                                                    <div class="entry-actions">
                                                        <button class="btn btn-sm btn-outline-light" onclick="editEntry(<?php echo $entry['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-light" onclick="deleteEntry(<?php echo $entry['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
            <form id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_entry">
                    <input type="hidden" name="entry_id" id="editEntryId">
                    
                    <div class="mb-3">
                        <label class="form-label">Entry Details</label>
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

<style>
.timetable-grid {
    font-size: 0.9rem;
}

.timetable-cell {
    min-height: 80px;
    vertical-align: top;
    padding: 5px;
    position: relative;
}

.timetable-entry {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-radius: 6px;
    padding: 8px;
    margin: 2px 0;
    cursor: move;
    transition: all 0.3s ease;
    position: relative;
}

.timetable-entry:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.timetable-entry.dragging {
    opacity: 0.5;
    transform: rotate(5deg);
}

.timetable-cell.drag-over {
    background-color: #e3f2fd;
    border: 2px dashed #2196f3;
}

.entry-content {
    font-size: 0.8rem;
}

.entry-class {
    font-weight: bold;
    font-size: 0.9rem;
}

.entry-course {
    font-style: italic;
}

.entry-lecturer {
    font-size: 0.75rem;
    opacity: 0.9;
}

.entry-room {
    font-size: 0.75rem;
    opacity: 0.8;
}

.entry-actions {
    position: absolute;
    top: 2px;
    right: 2px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.timetable-entry:hover .entry-actions {
    opacity: 1;
}

.time-slot-header {
    background-color: #f8f9fa;
    font-weight: bold;
    vertical-align: middle;
}

#messageContainer {
    margin-bottom: 1rem;
}
</style>

<script>
let draggedElement = null;

// Drag and drop functionality
document.addEventListener('DOMContentLoaded', function() {
    const entries = document.querySelectorAll('.timetable-entry');
    const cells = document.querySelectorAll('.timetable-cell');
    
    entries.forEach(entry => {
        entry.addEventListener('dragstart', handleDragStart);
        entry.addEventListener('dragend', handleDragEnd);
    });
    
    cells.forEach(cell => {
        cell.addEventListener('dragover', handleDragOver);
        cell.addEventListener('drop', handleDrop);
        cell.addEventListener('dragenter', handleDragEnter);
        cell.addEventListener('dragleave', handleDragLeave);
    });
});

function handleDragStart(e) {
    draggedElement = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    draggedElement = null;
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDragEnter(e) {
    e.preventDefault();
    this.classList.add('drag-over');
}

function handleDragLeave(e) {
    this.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    this.classList.remove('drag-over');
    
    if (draggedElement && this !== draggedElement.parentElement) {
        const newDayId = this.dataset.day;
        const newTimeSlotId = this.dataset.timeSlot;
        
        // Update the entry
        updateEntryPosition(draggedElement.dataset.entryId, newDayId, newTimeSlotId);
        
        // Move the element visually
        this.appendChild(draggedElement);
    }
}

function updateEntryPosition(entryId, dayId, timeSlotId) {
    const formData = new FormData();
    formData.append('action', 'update_entry');
    formData.append('entry_id', entryId);
    formData.append('day_id', dayId);
    formData.append('time_slot_id', timeSlotId);
    formData.append('room_id', document.getElementById('editRoom').value); // Keep current room
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showMessage(data.success ? 'success' : 'danger', data.message);
        if (data.success) {
            // Optionally reload the page to refresh data
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => {
        showMessage('danger', 'Error updating entry: ' + error.message);
    });
}

function editEntry(entryId) {
    const entry = document.querySelector(`[data-entry-id="${entryId}"]`);
    if (entry) {
        document.getElementById('editEntryId').value = entryId;
        document.getElementById('currentEntryInfo').textContent = 
            `${entry.dataset.class} - ${entry.dataset.course} | ${entry.dataset.lecturer} | ${entry.dataset.room}`;
        
        // Set current values
        const cell = entry.closest('.timetable-cell');
        document.getElementById('editDay').value = cell.dataset.day;
        document.getElementById('editTimeSlot').value = cell.dataset.timeSlot;
        
        var modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    }
}

function deleteEntry(entryId) {
    if (confirm('Are you sure you want to delete this timetable entry?')) {
        const formData = new FormData();
        formData.append('action', 'delete_entry');
        formData.append('entry_id', entryId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showMessage(data.success ? 'success' : 'danger', data.message);
            if (data.success) {
                setTimeout(() => location.reload(), 1000);
            }
        })
        .catch(error => {
            showMessage('danger', 'Error deleting entry: ' + error.message);
        });
    }
}

function showMessage(type, message) {
    const container = document.getElementById('messageContainer');
    container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

// Handle form submission
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showMessage(data.success ? 'success' : 'danger', data.message);
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => {
        showMessage('danger', 'Error updating entry: ' + error.message);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
