<?php
$pageTitle = 'Manage Streams';
include 'includes/header.php';
include 'includes/sidebar.php';

// Include custom dialog system
echo '<link rel="stylesheet" href="css/custom-dialogs.css">';
echo '<script src="js/custom-dialogs.js"></script>';

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
        // Store active days as JSON to match DB JSON column
        $active_days = isset($_POST['active_days']) ? json_encode(array_values($_POST['active_days'])) : json_encode([]);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // If this stream is being set as active, deactivate all other streams first
        if ($is_active) {
            $deactivate_sql = "UPDATE streams SET is_active = 0, updated_at = NOW() WHERE is_active = 1";
            $conn->query($deactivate_sql);
        }

        $sql = "INSERT INTO streams 
                (name, code, description, active_days, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $code, $description, $active_days, $is_active);

        if ($stmt->execute()) {
            $stream_id = $conn->insert_id;
            $success_message = "Stream added successfully!";

            // If this stream is set as active, update session
            if ($is_active) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['current_stream_id'] = $stream_id;
                $_SESSION['active_stream'] = $stream_id;
                $_SESSION['stream_id'] = $stream_id;
            }

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
            $error_message = "Error adding stream: " . $conn->error;
        }
        $stmt->close();
    }

    // EDIT STREAM
    elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $description = $conn->real_escape_string($_POST['description']);
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
                SET name = ?, code = ?, description = ?, active_days = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssii", $name, $code, $description, $active_days, $is_active, $id);

        if ($stmt->execute()) {
            $success_message = "Stream updated successfully!";

            // If this stream is set as active, update session
            if ($is_active) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['current_stream_id'] = $id;
                $_SESSION['active_stream'] = $id;
                $_SESSION['stream_id'] = $id;
            }

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
            $error_message = "Error updating stream: " . $conn->error;
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
                // Update session to set this as the current stream
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['current_stream_id'] = $id;
                $_SESSION['active_stream'] = $id;
                $_SESSION['stream_id'] = $id;
                
                $conn->commit();
                $success_message = "Stream activated successfully! All other streams have been deactivated.";
            } else {
                throw new Exception("Error activating stream: " . $conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }

    // DEACTIVATE STREAM (soft delete → set inactive)
    elseif ($_POST['action'] === 'deactivate' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];

        $sql = "UPDATE streams SET is_active = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_message = "Stream deactivated successfully!";
        } else {
            $error_message = "Error deactivating stream: " . $conn->error;
        }
        $stmt->close();
    }

    // DELETE STREAM (permanent deletion)
    elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];

        // Start transaction to ensure data consistency
        $conn->begin_transaction();

        try {
            // Check if stream is currently active
            $check_active = $conn->prepare("SELECT is_active FROM streams WHERE id = ?");
            $check_active->bind_param("i", $id);
            $check_active->execute();
            $active_result = $check_active->get_result();
            $stream_data = $active_result->fetch_assoc();
            $check_active->close();

            if ($stream_data && $stream_data['is_active']) {
                throw new Exception("Cannot delete the currently active stream. Please activate a different stream first.");
            }

            // Check for foreign key constraints
            // Check if stream is referenced in classes (if stream_id column exists)
            $classes_count = 0;
            $col_check = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
            if ($col_check && $col_check->num_rows > 0) {
                $check_classes = $conn->prepare("SELECT COUNT(*) as count FROM classes WHERE stream_id = ?");
                $check_classes->bind_param("i", $id);
                $check_classes->execute();
                $classes_result = $check_classes->get_result();
                $classes_count = $classes_result->fetch_assoc()['count'];
                $check_classes->close();
            }
            
            if ($classes_count > 0) {
                throw new Exception("Cannot delete stream. It is referenced by $classes_count classes. Please reassign or delete the classes first.");
            }
            
            // Check if stream is referenced in stream_time_slots
            $check_time_slots = $conn->prepare("SELECT COUNT(*) as count FROM stream_time_slots WHERE stream_id = ?");
            $check_time_slots->bind_param("i", $id);
            $check_time_slots->execute();
            $time_slots_result = $check_time_slots->get_result();
            $time_slots_count = $time_slots_result->fetch_assoc()['count'];
            $check_time_slots->close();

            if ($time_slots_count > 0) {
                // Delete related stream_time_slots records first
                $delete_time_slots = $conn->prepare("DELETE FROM stream_time_slots WHERE stream_id = ?");
                $delete_time_slots->bind_param("i", $id);
                $delete_time_slots->execute();
                $delete_time_slots->close();
            }

            // Now delete the stream
            $delete_sql = "DELETE FROM streams WHERE id = ?";
            $stmt = $conn->prepare($delete_sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $conn->commit();
                    $success_message = "Stream deleted successfully!";
                } else {
                    throw new Exception("Stream not found or already deleted.");
                }
            } else {
                throw new Exception("Error deleting stream: " . $conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }

    // No regenerate action needed with mapping model
}

// -------------------- MIGRATE EXISTING STREAM DAYS --------------------
// Convert short day names to full day names for existing streams
$migration_sql = "SELECT id, active_days FROM streams WHERE active_days IS NOT NULL AND active_days != ''";
$migration_result = $conn->query($migration_sql);

if ($migration_result && $migration_result->num_rows > 0) {
    $day_mapping = [
        'Mon' => 'Monday',
        'Tue' => 'Tuesday', 
        'Wed' => 'Wednesday',
        'Thu' => 'Thursday',
        'Fri' => 'Friday',
        'Sat' => 'Saturday',
        'Sun' => 'Sunday'
    ];
    
    while ($migration_row = $migration_result->fetch_assoc()) {
        $active_days_json = $migration_row['active_days'];
        $active_days_array = json_decode($active_days_json, true);
        
        if (is_array($active_days_array)) {
            $needs_update = false;
            $updated_days = [];
            
            foreach ($active_days_array as $day) {
                if (isset($day_mapping[$day])) {
                    $updated_days[] = $day_mapping[$day];
                    $needs_update = true;
                } else {
                    $updated_days[] = $day;
                }
            }
            
            if ($needs_update) {
                $updated_json = json_encode($updated_days);
                $update_sql = "UPDATE streams SET active_days = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $updated_json, $migration_row['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
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

    <!-- Stream Status Explanation -->
    <div class="alert alert-info">
        <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Stream Status Guide:</h6>
        <ul class="mb-0">
            <li><strong>Currently Active:</strong> Stream is active in the database (only one stream can be active at a time)</li>
            <li><strong>Selected for You:</strong> This is your current working stream (used for timetable generation and filtering)</li>
            <li><strong>Selected but Inactive:</strong> This is your selected stream but it's not active in the database</li>
        </ul>
        <p class="mb-0 mt-2"><small>When you activate a stream, it automatically becomes your selected stream for the current session.</small></p>
    </div>

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

        <div class="mb-3">
            <label class="form-label">Active Days</label><br>
            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
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
                <?php while ($gs = $global_slots_rs->fetch_assoc()): ?>
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
                <th>Days</th>
                <th>Delete</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']); ?></td>
                <td><?= htmlspecialchars($row['code']); ?></td>
                <td><?= htmlspecialchars($row['description']); ?></td>
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
                <td>
                    <form method="POST" class="mb-0">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm w-100" 
                                onclick="confirmStreamDeletion(event, <?= $row['id']; ?>, '<?= addslashes($row['name']); ?>')"
                                <?= $row['is_active'] ? 'disabled' : ''; ?>>
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                    <?php if ($row['is_active']): ?>
                        <small class="text-muted d-block mt-1">Cannot delete active stream</small>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <button type="button" class="btn btn-primary btn-sm w-100 mb-1" onclick="editStream(<?= $row['id']; ?>)">Edit</button>
                        <?php if ($row['is_active']): ?>
                            <!-- Deactivate Form -->
                            <form method="POST" class="mb-0">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                <button type="submit" class="btn btn-warning btn-sm w-100" onclick="confirmStreamDeactivation(event, <?= $row['id']; ?>, '<?= addslashes($row['name']); ?>')">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <!-- Activate Form -->
                            <form method="POST" class="mb-0">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm w-100" onclick="confirmStreamActivation(event, <?= $row['id']; ?>, '<?= addslashes($row['name']); ?>')">Activate</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <!-- EDIT STREAM MODAL -->
    <div class="modal fade" id="editStreamModal" tabindex="-1" aria-labelledby="editStreamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStreamModalLabel">Edit Stream</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editStreamForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editStreamId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stream Name *</label>
                                <input type="text" name="name" id="editStreamName" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stream Code *</label>
                                <input type="text" name="code" id="editStreamCode" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" id="editStreamDescription" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Active Days</label><br>
                            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                                <label class="me-2">
                                    <input type="checkbox" name="active_days[]" value="<?= $day; ?>" class="edit-active-days"> <?= $day; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Time Slots for this Stream</label>
                            <div class="row" id="editTimeSlotsContainer">
                                <?php 
                                $global_slots_rs = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
                                while ($gs = $global_slots_rs->fetch_assoc()): 
                                ?>
                                    <div class="col-md-3">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input edit-time-slots" name="time_slots[]" value="<?= $gs['id']; ?>">
                                            <?= htmlspecialchars(substr($gs['start_time'], 0, 5) . ' - ' . substr($gs['end_time'], 0, 5)); ?>
                                        </label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">Active (will deactivate all other streams)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stream</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

    <script>
    // Custom dialog functions for stream management
    async function confirmStreamDeletion(event, streamId, streamName) {
        event.preventDefault(); // Prevent form submission
        
        const confirmed = await customDanger(
            `Are you sure you want to permanently delete the stream "${streamName}"?<br><br><strong>This action cannot be undone!</strong><br><br>This will also delete all associated:<br>• Classes<br>• Courses<br>• Lecturer assignments<br>• Timetable entries`,
            {
                title: 'Delete Stream',
                confirmText: 'Delete Permanently',
                cancelText: 'Cancel',
                confirmButtonClass: 'danger'
            }
        );
        
        if (confirmed) {
            // Submit the form
            event.target.closest('form').submit();
        }
    }

    async function confirmStreamDeactivation(event, streamId, streamName) {
        event.preventDefault(); // Prevent form submission
        
        const confirmed = await customWarning(
            `Are you sure you want to deactivate the stream "${streamName}"?<br><br>This will:<br>• Make the stream inactive<br>• Prevent new timetable generation for this stream<br>• Keep existing data intact`,
            {
                title: 'Deactivate Stream',
                confirmText: 'Deactivate',
                cancelText: 'Cancel',
                confirmButtonClass: 'warning'
            }
        );
        
        if (confirmed) {
            // Submit the form
            event.target.closest('form').submit();
        }
    }

    async function confirmStreamActivation(event, streamId, streamName) {
        event.preventDefault(); // Prevent form submission
        
        const confirmed = await customWarning(
            `Are you sure you want to activate the stream "${streamName}"?<br><br>This will:<br>• Make this stream the active stream<br>• <strong>Deactivate all other streams</strong><br>• Set this as your current working stream`,
            {
                title: 'Activate Stream',
                confirmText: 'Activate',
                cancelText: 'Cancel',
                confirmButtonClass: 'success'
            }
        );
        
        if (confirmed) {
            // Submit the form
            event.target.closest('form').submit();
        }
    }

    function editStream(streamId) {
    // Fetch stream data via AJAX
    fetch('get_stream_data.php?id=' + streamId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stream = data.stream;
                
                // Populate form fields
                document.getElementById('editStreamId').value = stream.id;
                document.getElementById('editStreamName').value = stream.name;
                document.getElementById('editStreamCode').value = stream.code;
                document.getElementById('editStreamDescription').value = stream.description;
                document.getElementById('editIsActive').checked = stream.is_active == 1;
                
                // Handle active days
                const activeDays = stream.active_days ? JSON.parse(stream.active_days) : [];
                document.querySelectorAll('.edit-active-days').forEach(checkbox => {
                    checkbox.checked = activeDays.includes(checkbox.value);
                });
                
                // Handle time slots
                const selectedTimeSlots = data.selected_time_slots || [];
                document.querySelectorAll('.edit-time-slots').forEach(checkbox => {
                    checkbox.checked = selectedTimeSlots.includes(parseInt(checkbox.value));
                });
                
                // Show modal
                new bootstrap.Modal(document.getElementById('editStreamModal')).show();
            } else {
                alert('Error loading stream data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading stream data');
        });
}
</script>

<?php include 'includes/footer.php'; ?>
