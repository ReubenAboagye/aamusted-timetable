<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new time slot
                $startTime = $_POST['startTime'];
                $endTime = $_POST['endTime'];
                $isBreak = isset($_POST['isBreak']) ? 1 : 0;
                $isMandatory = isset($_POST['isMandatory']) ? 1 : 0;

                // Calculate duration in minutes
                $start = new DateTime($startTime);
                $end = new DateTime($endTime);
                $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;

                // Validate that end time is after start time
                if ($duration <= 0) {
                    echo "<script>alert('End time must be after start time!');</script>";
                } else {
                    // Check if time slot already exists
                    $checkSql = "SELECT id FROM time_slots WHERE start_time = ? AND end_time = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("ss", $startTime, $endTime);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        echo "<script>alert('Time slot already exists!');</script>";
                    } else {
                        $sql = "INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ssiii", $startTime, $endTime, $duration, $isBreak, $isMandatory);
                            if ($stmt->execute()) {
                                echo "<script>alert('Time slot added successfully!'); window.location.href='time_slots.php';</script>";
                            } else {
                                echo "Error: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                    }
                    $checkStmt->close();
                }
                break;

            case 'update':
                // Update existing time slot
                $id = $_POST['id'];
                $startTime = $_POST['startTime'];
                $endTime = $_POST['endTime'];
                $isBreak = isset($_POST['isBreak']) ? 1 : 0;
                $isMandatory = isset($_POST['isMandatory']) ? 1 : 0;

                // Calculate duration in minutes
                $start = new DateTime($startTime);
                $end = new DateTime($endTime);
                $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;

                // Validate that end time is after start time
                if ($duration <= 0) {
                    echo "<script>alert('End time must be after start time!');</script>";
                } else {
                    // Check if time slot already exists for other slots
                    $checkSql = "SELECT id FROM time_slots WHERE start_time = ? AND end_time = ? AND id != ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("ssi", $startTime, $endTime, $id);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        echo "<script>alert('Time slot already exists!');</script>";
                    } else {
                        $sql = "UPDATE time_slots SET start_time = ?, end_time = ?, duration = ?, is_break = ?, is_mandatory = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ssiiii", $startTime, $endTime, $duration, $isBreak, $isMandatory, $id);
                            if ($stmt->execute()) {
                                echo "<script>alert('Time slot updated successfully!'); window.location.href='time_slots.php';</script>";
                            } else {
                                echo "Error: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                    }
                    $checkStmt->close();
                }
                break;

            case 'delete':
                // Delete time slot
                $id = $_POST['id'];
                
                // Check if time slot has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM timetable WHERE time_slot_id = ?) as timetable_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['timetable_count'] > 0) {
                    echo "<script>alert('Cannot delete time slot: It has dependent timetables. Consider deactivating instead.');</script>";
                } else {
                    $sql = "DELETE FROM time_slots WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Time slot deleted successfully!'); window.location.href='time_slots.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                break;
        }
    }
}

// Fetch existing time slots for display
$timeSlots = [];
$sql = "SELECT * FROM time_slots ORDER BY start_time";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $timeSlots[] = $row;
    }
}

// Predefined time slot templates for common academic schedules
$timeSlotTemplates = [
    'morning_8am' => ['08:00', '09:00', 'Morning 8 AM'],
    'morning_9am' => ['09:00', '10:00', 'Morning 9 AM'],
    'morning_10am' => ['10:00', '11:00', 'Morning 10 AM'],
    'morning_11am' => ['11:00', '12:00', 'Morning 11 AM'],
    'lunch_break' => ['12:00', '13:00', 'Lunch Break'],
    'afternoon_1pm' => ['13:00', '14:00', 'Afternoon 1 PM'],
    'afternoon_2pm' => ['14:00', '15:00', 'Afternoon 2 PM'],
    'afternoon_3pm' => ['15:00', '16:00', 'Afternoon 3 PM'],
    'afternoon_4pm' => ['16:00', '17:00', 'Afternoon 4 PM'],
    'evening_5pm' => ['17:00', '18:00', 'Evening 5 PM'],
    'evening_6pm' => ['18:00', '19:00', 'Evening 6 PM'],
    'evening_7pm' => ['19:00', '20:00', 'Evening 7 PM']
];
?>

<?php $pageTitle = 'Manage Time Slots'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

<div class="main-content" id="mainContent">
    <h2>Manage Time Slots</h2>
    
    <!-- Search Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="input-group" style="width: 300px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control search-input" id="searchInput" placeholder="Search time slots...">
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#timeSlotModal">
                <i class="fas fa-plus me-2"></i>Add New Time Slot
            </button>
        </div>
    </div>
    
    <div class="row">
        <!-- Display Time Slots -->
        <div class="col-md-12">
            <div class="table-container">
                <div class="table-header">
                    <h4><i class="fas fa-clock me-2"></i>Existing Time Slots</h4>
                </div>
                <?php if (empty($timeSlots)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <h5>No time slots found</h5>
                        <p>Start by adding your first time slot using the button above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="timeSlotsTable">
                            <thead>
                                <tr>
                                    <th>Time Range</th>
                                    <th>Duration</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?></strong><br>
                                            <small class="text-muted"><?php echo $slot['start_time']; ?> - <?php echo $slot['end_time']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $slot['duration']; ?> min</span>
                                        </td>
                                        <td>
                                            <?php if ($slot['is_break']): ?>
                                                <span class="badge bg-warning">Break</span>
                                            <?php elseif ($slot['is_mandatory']): ?>
                                                <span class="badge bg-success">Mandatory</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Regular</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editTimeSlot(<?php echo $slot['id']; ?>, '<?php echo $slot['start_time']; ?>', '<?php echo $slot['end_time']; ?>', <?php echo $slot['is_break']; ?>, <?php echo $slot['is_mandatory']; ?>)">Edit</button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTimeSlot(<?php echo $slot['id']; ?>, '<?php echo $slot['start_time']; ?> - <?php echo $slot['end_time']; ?>')">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body p-2">
                                        <h6 class="mb-0"><?php echo count(array_filter($timeSlots, function($s) { return !$s['is_break'] && !$s['is_mandatory']; })); ?></h6>
                                        <small>Regular Slots</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body p-2">
                                        <h6 class="mb-0"><?php echo count(array_filter($timeSlots, function($s) { return $s['is_break']; })); ?></h6>
                                        <small>Break Times</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body p-2">
                                        <h6 class="mb-0"><?php echo count(array_filter($timeSlots, function($s) { return $s['is_mandatory']; })); ?></h6>
                                        <small>Mandatory</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Time Slot Modal -->
<div class="modal fade" id="timeSlotModal" tabindex="-1" aria-labelledby="timeSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="timeSlotModalLabel">Add New Time Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="timeSlotForm">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="timeSlotId" name="id" value="">
                    
                    <div class="alert alert-info" id="formMode" style="display: none;">
                        <strong>Edit Mode:</strong> You are currently editing an existing time slot. Click "Cancel Edit" to return to add mode.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="startTime" class="form-label">Start Time *</label>
                                <input type="time" class="form-control" id="startTime" name="startTime" required>
                                <div class="form-text">When the slot begins</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="endTime" class="form-label">End Time *</label>
                                <input type="time" class="form-control" id="endTime" name="endTime" required>
                                <div class="form-text">When the slot ends</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isBreak" name="isBreak">
                                    <label class="form-check-label" for="isBreak">
                                        This is a Break Time
                                    </label>
                                    <div class="form-text">Break times won't be scheduled for classes</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isMandatory" name="isMandatory">
                                    <label class="form-check-label" for="isMandatory">
                                        Mandatory Slot
                                    </label>
                                    <div class="form-text">Must be included in all timetables</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label class="form-label">Quick Add Templates</label>
                        <div class="row">
                            <?php foreach ($timeSlotTemplates as $key => $template): ?>
                                <div class="col-md-6 mb-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 template-btn" 
                                            data-start="<?php echo $template[0]; ?>" 
                                            data-end="<?php echo $template[1]; ?>"
                                            data-is-break="<?php echo ($key === 'lunch_break') ? '1' : '0'; ?>">
                                        <?php echo $template[2]; ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Click any template to auto-fill the form</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Time Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editTimeSlot(id, startTime, endTime, isBreak, isMandatory) {
        // Set form to edit mode
        document.getElementById('action').value = 'update';
        document.getElementById('timeSlotId').value = id;
        document.getElementById('startTime').value = startTime;
        document.getElementById('endTime').value = endTime;
        document.getElementById('isBreak').checked = isBreak == 1;
        document.getElementById('isMandatory').checked = isMandatory == 1;
        
        // Update form appearance
        document.getElementById('timeSlotModalLabel').textContent = 'Edit Time Slot';
        document.getElementById('submitBtn').textContent = 'Update Time Slot';
        document.getElementById('submitBtn').className = 'btn btn-warning';
        document.getElementById('cancelBtn').style.display = 'inline-block';
        document.getElementById('formMode').style.display = 'block';
        
        // Show modal
        var modal = new bootstrap.Modal(document.getElementById('timeSlotModal'));
        modal.show();
    }
    
    function cancelEdit() {
        // Reset form to add mode
        document.getElementById('action').value = 'add';
        document.getElementById('timeSlotForm').reset();
        document.getElementById('timeSlotId').value = '';
        
        // Update form appearance
        document.getElementById('timeSlotModalLabel').textContent = 'Add New Time Slot';
        document.getElementById('submitBtn').textContent = 'Add Time Slot';
        document.getElementById('submitBtn').className = 'btn btn-primary';
        document.getElementById('cancelBtn').style.display = 'none';
        document.getElementById('formMode').style.display = 'none';
    }
    
    function deleteTimeSlot(id, timeRange) {
        if (confirm(`Are you sure you want to delete the time slot "${timeRange}"?`)) {
            // Create a form to submit the deletion
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Template button functionality
    document.addEventListener('DOMContentLoaded', function() {
        const templateBtns = document.querySelectorAll('.template-btn');
        templateBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const startTime = this.getAttribute('data-start');
                const endTime = this.getAttribute('data-end');
                const isBreak = this.getAttribute('data-is-break');
                
                document.getElementById('startTime').value = startTime;
                document.getElementById('endTime').value = endTime;
                document.getElementById('isBreak').checked = isBreak === '1';
                document.getElementById('isMandatory').checked = false;
            });
        });
        
        // Reset modal when it's closed
        var timeSlotModal = document.getElementById('timeSlotModal');
        timeSlotModal.addEventListener('hidden.bs.modal', function () {
            cancelEdit();
        });
    });
</script>

