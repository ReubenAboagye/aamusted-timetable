<?php
/**
 * Enhanced Manual Timetable Editor with Drag & Drop
 * Advanced interface for manual timetable editing
 */

// Handle AJAX updates for drag & drop and edits FIRST (before any includes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Include only what's needed for AJAX
    include 'connect.php';
    
    // Get parameters from URL
    $stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
    $version = isset($_GET['version']) ? $_GET['version'] : '';
    $semester = isset($_GET['semester']) ? $_GET['semester'] : 'second';
    
    // Clear any previous output
    ob_clean();
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    // Debug logging
    error_log("AJAX Action: " . $action);
    error_log("POST data: " . print_r($_POST, true));
    
    if ($action === 'update_entry') {
        try {
            $entry_id = intval($_POST['entry_id']);
            $new_day_id = intval($_POST['day_id']);
            $new_time_slot_id = intval($_POST['time_slot_id']);
            $new_room_id = intval($_POST['room_id']);
            
            // Get current entry details first
            $current_entry_query = "SELECT academic_year, timetable_type, class_course_id, lecturer_course_id FROM timetable WHERE id = ?";
            $stmt = $conn->prepare($current_entry_query);
            $stmt->bind_param("i", $entry_id);
            $stmt->execute();
            $current_entry = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Check for conflicts before updating
            $conflict_check = "
                SELECT COUNT(*) as conflict_count
                FROM timetable t1
                WHERE t1.id != ? 
                AND t1.day_id = ? 
                AND t1.time_slot_id = ? 
                AND (
                    t1.room_id = ? OR
                    t1.class_course_id = ? OR
                    t1.lecturer_course_id = ?
                )
                AND t1.semester = ? AND t1.version = ? AND t1.academic_year = ? AND t1.timetable_type = ?
            ";
            
            $stmt = $conn->prepare($conflict_check);
            $stmt->bind_param("iiiiiiisss", $entry_id, $new_day_id, $new_time_slot_id, $new_room_id, $current_entry['class_course_id'], $current_entry['lecturer_course_id'], $semester, $version, $current_entry['academic_year'], $current_entry['timetable_type']);
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
        } catch (Exception $e) {
            // Check if it's a constraint violation and provide user-friendly message
            if (strpos($e->getMessage(), 'uq_timetable_same_version') !== false) {
                $response['message'] = 'Cannot move entry: This time slot is already occupied by another class in the same room.';
            } elseif (strpos($e->getMessage(), 'uq_timetable_class_course_time') !== false) {
                $response['message'] = 'Cannot move entry: This class already has another course scheduled at this time.';
            } elseif (strpos($e->getMessage(), 'uq_timetable_class_time') !== false) {
                $response['message'] = 'Cannot move entry: This would create a scheduling conflict for the class.';
            } else {
                $response['message'] = 'Unable to update entry. Please try again or contact support if the problem persists.';
            }
            error_log("Update entry error: " . $e->getMessage());
        }
    }
    
    if ($action === 'delete_entry') {
        try {
            $entry_id = intval($_POST['entry_id']);
            
            $delete_query = "DELETE FROM timetable WHERE id = ? AND version = ? AND semester = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("iss", $entry_id, $version, $semester);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Entry deleted successfully!';
            } else {
                $response['message'] = 'Failed to delete entry: ' . $conn->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = 'Unable to delete entry. Please try again or contact support if the problem persists.';
            error_log("Delete entry error: " . $e->getMessage());
        }
    }
    
    // Debug logging
    error_log("Response: " . json_encode($response));
    
    echo json_encode($response);
    exit;
}

// Regular page load - include everything
$pageTitle = 'Advanced Timetable Editor';
include 'connect.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Get parameters (versions, stream, semester)
$stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$version = isset($_GET['version']) ? $_GET['version'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : 'second';

if (!$stream_id || !$version) {
    header('Location: saved_timetable.php');
    exit;
}

// Get stream name ( for display purposes )
$stream_query = "SELECT name FROM streams WHERE id = ? AND is_active = 1";
$stmt = $conn->prepare($stream_query);
$stmt->bind_param("i", $stream_id);
$stmt->execute();
$stream_result = $stmt->get_result();
$stream_name = $stream_result->fetch_assoc()['name'] ?? 'Unknown Stream';
$stmt->close();

// Get timetable entries organized by day and time slot
$timetable_query = "
    SELECT t.*, 
           l.name as lecturer_name,
           c.name as course_name,
           cl.name as class_name,
           d.name as day_name, d.id as day_id,
           ts.start_time, ts.end_time, ts.id as time_slot_id,
           r.name as room_name, r.id as room_id,
           CONCAT(cl.name, COALESCE(CONCAT(' - ', t.division_label), '')) as class_division_name,
           t.is_combined,
           t.combined_classes
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
    ORDER BY d.id, ts.start_time, cl.name, t.division_label
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

$time_slots_query = "SELECT id, start_time, end_time FROM time_slots ORDER BY start_time";
$time_slots_result = $conn->query($time_slots_query);
$available_time_slots = $time_slots_result->fetch_all(MYSQLI_ASSOC);

$rooms_query = "SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY name";
$rooms_result = $conn->query($rooms_query);
$available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);

// Organize entries by day and time slot for grid display
$timetable_grid = [];
$class_colors = [];
$color_palette = [
    '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', 
    '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#343a40', '#0d6efd',
    '#1e3a8a', '#059669', '#dc2626', '#d97706', '#0891b2', '#7c3aed',
    '#be185d', '#374151', '#1f2937', '#f59e0b', '#10b981', '#ef4444',
    '#8b5cf6', '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
];

// Assign colors to classes (not divisions to avoid excessive colors)
$class_index = 0;
foreach ($timetable_entries as $entry) {
    $class_name = $entry['class_name'];
    if (!isset($class_colors[$class_name])) {
        $class_colors[$class_name] = $color_palette[$class_index % count($color_palette)];
        $class_index++;
    }
}

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

    <!-- Class Color Legend -->
        <?php if (!empty($class_colors)): ?>
        <div class="class-legend">
            <h6><i class="fas fa-palette me-2"></i>Class Color Legend</h6>
            <p class="text-muted small mb-2">
                Each class has a unique color. Divisions (A, B, C, D) share the same color as their parent class.
                <br><i class="fas fa-users text-warning me-1"></i> <strong>Combined Classes:</strong> Multiple classes taught together in the same room.
            </p>
            <?php foreach ($class_colors as $class_name => $color): ?>
                <span class="legend-item" style="background: linear-gradient(135deg, <?php echo $color; ?>, <?php echo $color; ?>dd);">
                    <?php echo htmlspecialchars($class_name); ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
                                                <?php
                                                // Check if this is a combined class
                                                $is_combined = $entry['is_combined'] == 1;
                                                $combined_classes_display = '';
                                                
                                                if ($is_combined && !empty($entry['combined_classes'])) {
                                                    $combined_class_ids = json_decode($entry['combined_classes'], true);
                                                    if (is_array($combined_class_ids) && count($combined_class_ids) > 1) {
                                                        // Get class names for combined classes
                                                        $combined_names = [];
                                                        foreach ($combined_class_ids as $class_course_id) {
                                                            // Find the class name for this class_course_id
                                                            foreach ($timetable_entries as $other_entry) {
                                                                if ($other_entry['class_course_id'] == $class_course_id) {
                                                                    $combined_names[] = $other_entry['class_division_name'];
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        if (count($combined_names) > 1) {
                                                            $combined_classes_display = implode(', ', $combined_names);
                                                        }
                                                    }
                                                }
                                                ?>
                                                <div class="timetable-entry <?php echo $is_combined ? 'combined-entry' : ''; ?>" 
                                                     data-entry-id="<?php echo $entry['id']; ?>"
                                                     data-class="<?php echo htmlspecialchars($entry['class_division_name']); ?>"
                                                     data-course="<?php echo htmlspecialchars($entry['course_name']); ?>"
                                                     data-lecturer="<?php echo htmlspecialchars($entry['lecturer_name']); ?>"
                                                     data-room="<?php echo htmlspecialchars($entry['room_name']); ?>"
                                                     data-room-id="<?php echo $entry['room_id']; ?>"
                                                     style="background: linear-gradient(135deg, <?php echo $class_colors[$entry['class_name']]; ?>, <?php echo $class_colors[$entry['class_name']]; ?>dd);"
                                                     draggable="true">
                                                    <div class="entry-content">
                                                        <div class="entry-class">
                                                            <?php if ($is_combined && !empty($combined_classes_display)): ?>
                                                                <i class="fas fa-users me-1" title="Combined Classes"></i>
                                                                <?php echo htmlspecialchars($combined_classes_display); ?>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($entry['class_division_name']); ?>
                                                            <?php endif; ?>
                                                        </div>
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
    color: white;
    border-radius: 8px;
    padding: 10px;
    margin: 3px 0;
    cursor: move;
    transition: all 0.3s ease;
    position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.2);
}

.timetable-entry.combined-entry {
    border: 2px solid #ffc107;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
}

.timetable-entry.combined-entry:hover {
    border-color: #ffca2c;
    box-shadow: 0 6px 16px rgba(255, 193, 7, 0.4);
}


.timetable-entry:hover {
    transform: scale(1.02);
    box-shadow: 0 6px 12px rgba(0,0,0,0.25);
    border-color: rgba(255,255,255,0.4);
}

.timetable-entry.dragging {
    opacity: 0.7;
    transform: rotate(3deg) scale(1.05);
    box-shadow: 0 8px 16px rgba(0,0,0,0.3);
    z-index: 1000;
}

.timetable-cell.drag-over {
    background-color: rgba(33, 150, 243, 0.1);
    border: 2px dashed #2196f3;
    border-radius: 4px;
}

.entry-content {
    font-size: 0.8rem;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.entry-class {
    font-weight: bold;
    font-size: 0.9rem;
    margin-bottom: 2px;
}

.entry-course {
    font-style: italic;
    font-size: 0.8rem;
    margin-bottom: 2px;
    opacity: 0.95;
}

.entry-lecturer {
    font-size: 0.75rem;
    opacity: 0.9;
    margin-bottom: 1px;
}

.entry-room {
    font-size: 0.75rem;
    opacity: 0.85;
    font-weight: 500;
}

.entry-actions {
    position: absolute;
    top: 4px;
    right: 4px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.timetable-entry:hover .entry-actions {
    opacity: 1;
}

.entry-actions .btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 2px 6px;
    font-size: 0.7rem;
    margin-left: 2px;
}

.entry-actions .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    color: white;
}

.time-slot-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    font-weight: bold;
    vertical-align: middle;
    border-right: 1px solid #dee2e6;
    position: sticky;
    left: 0;
    z-index: 10;
}

#messageContainer {
    margin-bottom: 1rem;
}

/* Class color legend */
.class-legend {
    margin-bottom: 1rem;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.class-legend h6 {
    margin-bottom: 8px;
    font-weight: bold;
    color: #495057;
}

.legend-item {
    display: inline-block;
    margin: 2px 8px 2px 0;
    padding: 4px 8px;
    border-radius: 4px;
    color: white;
    font-size: 0.8rem;
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

/* Responsive improvements */
@media (max-width: 768px) {
    .timetable-entry {
        padding: 6px;
        font-size: 0.7rem;
    }
    
    .entry-class {
        font-size: 0.8rem;
    }
    
    .entry-course {
        font-size: 0.7rem;
    }
    
    .entry-lecturer, .entry-room {
        font-size: 0.65rem;
    }
}
</style>

<script>
let draggedElement = null;

// Drag and drop functionality for timetable entries 
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
    // Get the current room from the entry element
    const entry = document.querySelector(`[data-entry-id="${entryId}"]`);
    const currentRoomId = entry ? entry.dataset.roomId : '';
    
    const formData = new FormData();
    formData.append('action', 'update_entry');
    formData.append('entry_id', entryId);
    formData.append('day_id', dayId);
    formData.append('time_slot_id', timeSlotId);
    formData.append('room_id', currentRoomId); // Keep current room
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showMessage(data.success ? 'success' : 'danger', data.message);
        if (data.success) {
            // Optionally reload the page to refresh data from server
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

// Handle form submission for editing entries
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
