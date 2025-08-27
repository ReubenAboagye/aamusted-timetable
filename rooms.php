<?php
include 'connect.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = $_POST['name'];
                $building = $_POST['building'];
                $roomType = $_POST['roomType'];
                $capacity = $_POST['capacity'];
                $sessionAvailability = isset($_POST['sessionAvailability']) ? json_encode($_POST['sessionAvailability']) : '[]';
                $facilities = isset($_POST['facilities']) ? json_encode($_POST['facilities']) : '[]';
                $accessibilityFeatures = isset($_POST['accessibilityFeatures']) ? json_encode($_POST['accessibilityFeatures']) : '[]';

                // Check if room already exists
                $checkSql = "SELECT id FROM rooms WHERE name = ? AND building = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ss", $name, $building);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Room already exists in this building!');</script>";
                } else {
                    $sql = "INSERT INTO rooms (name, building, room_type, capacity, session_availability, facilities, accessibility_features) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("sssisss", $name, $building, $roomType, $capacity, $sessionAvailability, $facilities, $accessibilityFeatures);
                        if ($stmt->execute()) {
                            echo "<script>alert('Room added successfully!'); window.location.href='rooms.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                $id = $_POST['id'];
                $name = $_POST['name'];
                $building = $_POST['building'];
                $roomType = $_POST['roomType'];
                $capacity = $_POST['capacity'];
                $sessionAvailability = isset($_POST['sessionAvailability']) ? json_encode($_POST['sessionAvailability']) : '[]';
                $facilities = isset($_POST['facilities']) ? json_encode($_POST['facilities']) : '[]';
                $accessibilityFeatures = isset($_POST['accessibilityFeatures']) ? json_encode($_POST['accessibilityFeatures']) : '[]';

                // Check if room already exists for other rooms
                $checkSql = "SELECT id FROM rooms WHERE name = ? AND building = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ssi", $name, $building, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Room already exists in this building!');</script>";
                } else {
                    $sql = "UPDATE rooms SET name = ?, building = ?, room_type = ?, capacity = ?, session_availability = ?, facilities = ?, accessibility_features = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("sssisssi", $name, $building, $roomType, $capacity, $sessionAvailability, $facilities, $accessibilityFeatures, $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Room updated successfully!'); window.location.href='rooms.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                $id = $_POST['id'];
                
                // Check if room has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM timetable WHERE room_id = ?) as timetable_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['timetable_count'] > 0) {
                    echo "<script>alert('Cannot delete room: It has dependent timetables. Consider deactivating instead.');</script>";
                } else {
                    $sql = "DELETE FROM rooms WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Room deleted successfully!'); window.location.href='rooms.php';</script>";
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

// Fetch existing rooms for display
$rooms = [];
$sql = "SELECT * FROM rooms ORDER BY building, name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}

// Get unique buildings for the form
$buildings = [];
$buildingSql = "SELECT DISTINCT building FROM rooms ORDER BY building";
$buildingResult = $conn->query($buildingSql);
if ($buildingResult) {
    while ($row = $buildingResult->fetch_assoc()) {
        $buildings[] = $row['building'];
    }
}

// Predefined options
$roomTypes = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
$sessionTypes = ['regular', 'evening', 'weekend', 'sandwich', 'distance'];
$facilityOptions = ['projector', 'whiteboard', 'computer', 'audio_system', 'air_conditioning', 'wifi'];
$accessibilityOptions = ['wheelchair_access', 'elevator', 'ramp', 'accessible_bathroom'];
?>

<?php $pageTitle = 'Manage Rooms'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

<div class="main-content" id="mainContent">
    <h2>Manage Rooms</h2>
    
    <!-- Search Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="input-group" style="width: 300px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" class="form-control search-input" id="searchInput" placeholder="Search rooms...">
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal">
                <i class="fas fa-plus me-2"></i>Add New Room
            </button>
        </div>
    </div>
    
    <div class="row">
        <!-- Display Rooms -->
        <div class="col-md-12">
            <div class="table-container">
                <div class="table-header">
                    <h4><i class="fas fa-door-open me-2"></i>Existing Rooms</h4>
                </div>
                <?php if (empty($rooms)): ?>
                    <div class="empty-state">
                        <i class="fas fa-door-open"></i>
                        <h5>No rooms found</h5>
                        <p>Start by adding your first room using the button above.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="roomsTable">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Building</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Session Availability</th>
                                    <th>Facilities</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($room['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($room['building']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $room['room_type'])); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $room['capacity']; ?> seats</span>
                                        </td>
                                        <td>
                                            <?php 
                                            $sessions = json_decode($room['session_availability'], true);
                                            if ($sessions): 
                                                foreach ($sessions as $session): ?>
                                                    <span class="badge bg-secondary me-1"><?php echo ucfirst($session); ?></span>
                                                <?php endforeach;
                                            else: ?>
                                                <span class="text-muted">All sessions</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $facilities = json_decode($room['facilities'], true);
                                            if ($facilities): 
                                                foreach ($facilities as $facility): ?>
                                                    <span class="badge bg-success me-1"><?php echo ucfirst(str_replace('_', ' ', $facility)); ?></span>
                                                <?php endforeach;
                                            else: ?>
                                                <span class="text-muted">Basic</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['name']); ?>', '<?php echo htmlspecialchars($room['building']); ?>', '<?php echo $room['room_type']; ?>', <?php echo $room['capacity']; ?>, <?php echo htmlspecialchars($room['session_availability']); ?>, <?php echo htmlspecialchars($room['facilities']); ?>, <?php echo htmlspecialchars($room['accessibility_features']); ?>)">Edit</button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['name']); ?>')">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body p-2">
                                        <h6 class="mb-0"><?php echo count($rooms); ?></h6>
                                        <small>Total Rooms</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body p-2">
                                        <h6 class="mb-0"><?php echo count(array_unique(array_column($rooms, 'building'))); ?></h6>
                                        <small>Buildings</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body p-2">
                                        <h6 class="mb-0"><?php echo array_sum(array_column($rooms, 'capacity')); ?></h6>
                                        <small>Total Capacity</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body p-2">
                                        <h6 class="mb-0"><?php echo count(array_filter($rooms, function($r) { return $r['room_type'] === 'laboratory'; })); ?></h6>
                                        <small>Labs</small>
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

<!-- Room Modal -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-labelledby="roomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomModalLabel">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="roomForm">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="roomId" name="id" value="">
                    
                    <div class="alert alert-info" id="formMode" style="display: none;">
                        <strong>Edit Mode:</strong> You are currently editing an existing room. Click "Cancel Edit" to return to add mode.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Room Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                                <div class="form-text">e.g., Room 101, Lab A, Auditorium</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="building" class="form-label">Building *</label>
                                <input type="text" class="form-control" id="building" name="building" list="buildingList" required>
                                <datalist id="buildingList">
                                    <?php foreach ($buildings as $building): ?>
                                        <option value="<?php echo htmlspecialchars($building); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text">e.g., Main Building, Science Block</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="roomType" class="form-label">Room Type *</label>
                                <select class="form-select" id="roomType" name="roomType" required>
                                    <option value="">Select room type</option>
                                    <?php foreach ($roomTypes as $type): ?>
                                        <option value="<?php echo $type; ?>"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity *</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" required>
                                <div class="form-text">Number of students the room can accommodate</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Session Availability</label>
                        <div class="row">
                            <?php foreach ($sessionTypes as $session): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sessionAvailability[]" value="<?php echo $session; ?>" id="session_<?php echo $session; ?>">
                                        <label class="form-check-label" for="session_<?php echo $session; ?>">
                                            <?php echo ucfirst($session); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Leave unchecked for all sessions</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Facilities</label>
                        <div class="row">
                            <?php foreach ($facilityOptions as $facility): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="facilities[]" value="<?php echo $facility; ?>" id="facility_<?php echo $facility; ?>">
                                        <label class="form-check-label" for="facility_<?php echo $facility; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $facility)); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Accessibility Features</label>
                        <div class="row">
                            <?php foreach ($accessibilityOptions as $feature): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="accessibilityFeatures[]" value="<?php echo $feature; ?>" id="accessibility_<?php echo $feature; ?>">
                                        <label class="form-check-label" for="accessibility_<?php echo $feature; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $feature)); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editRoom(id, name, building, roomType, capacity, sessionAvailability, facilities, accessibilityFeatures) {
        // Set form to edit mode
        document.getElementById('action').value = 'update';
        document.getElementById('roomId').value = id;
        document.getElementById('name').value = name;
        document.getElementById('building').value = building;
        document.getElementById('roomType').value = roomType;
        document.getElementById('capacity').value = capacity;
        
        // Reset all checkboxes first
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        
        // Set session availability
        try {
            const sessions = JSON.parse(sessionAvailability);
            sessions.forEach(session => {
                const checkbox = document.getElementById('session_' + session);
                if (checkbox) checkbox.checked = true;
            });
        } catch (e) {}
        
        // Set facilities
        try {
            const facs = JSON.parse(facilities);
            facs.forEach(facility => {
                const checkbox = document.getElementById('facility_' + facility);
                if (checkbox) checkbox.checked = true;
            });
        } catch (e) {}
        
        // Set accessibility features
        try {
            const features = JSON.parse(accessibilityFeatures);
            features.forEach(feature => {
                const checkbox = document.getElementById('accessibility_' + feature);
                if (checkbox) checkbox.checked = true;
            });
        } catch (e) {}
        
        // Update form appearance
        document.getElementById('roomModalLabel').textContent = 'Edit Room';
        document.getElementById('submitBtn').textContent = 'Update Room';
        document.getElementById('submitBtn').className = 'btn btn-warning';
        document.getElementById('cancelBtn').style.display = 'inline-block';
        document.getElementById('formMode').style.display = 'block';
        
        // Show modal
        var modal = new bootstrap.Modal(document.getElementById('roomModal'));
        modal.show();
    }
    
    function cancelEdit() {
        // Reset form to add mode
        document.getElementById('action').value = 'add';
        document.getElementById('roomForm').reset();
        document.getElementById('roomId').value = '';
        
        // Update form appearance
        document.getElementById('roomModalLabel').textContent = 'Add New Room';
        document.getElementById('submitBtn').textContent = 'Add Room';
        document.getElementById('submitBtn').className = 'btn btn-primary';
        document.getElementById('cancelBtn').style.display = 'none';
        document.getElementById('formMode').style.display = 'none';
    }
    
    function deleteRoom(id, roomName) {
        if (confirm(`Are you sure you want to delete the room "${roomName}"?`)) {
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
    
    // Reset modal when it's closed
    document.addEventListener('DOMContentLoaded', function() {
        var roomModal = document.getElementById('roomModal');
        roomModal.addEventListener('hidden.bs.modal', function () {
            cancelEdit();
        });
    });
</script>

