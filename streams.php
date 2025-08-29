<?php
$pageTitle = 'Stream Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Fetch streams from database
$sql = "SELECT * FROM streams WHERE is_active = 1 ORDER BY name";
$result = $conn->query($sql);

// Handle form submission for adding new stream
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $period_start = $conn->real_escape_string($_POST['period_start']);
        $period_end = $conn->real_escape_string($_POST['period_end']);
        $break_start = $conn->real_escape_string($_POST['break_start']);
        $break_end = $conn->real_escape_string($_POST['break_end']);
        $active_days = isset($_POST['active_days']) ? implode(',', $_POST['active_days']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "INSERT INTO streams (name, period_start, period_end, break_start, break_end, active_days, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $name, $period_start, $period_end, $break_start, $break_end, $active_days, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Stream added successfully!";
        } else {
            $error_message = "Error adding stream: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE streams SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success_message = "Stream deleted successfully!";
        } else {
            $error_message = "Error deleting stream: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $period_start = $conn->real_escape_string($_POST['period_start']);
        $period_end = $conn->real_escape_string($_POST['period_end']);
        $break_start = $conn->real_escape_string($_POST['break_start']);
        $break_end = $conn->real_escape_string($_POST['break_end']);
        $active_days = isset($_POST['active_days']) ? implode(',', $_POST['active_days']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "UPDATE streams SET name = ?, period_start = ?, period_end = ?, break_start = ?, break_end = ?, active_days = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssii", $name, $period_start, $period_end, $break_start, $break_end, $active_days, $is_active, $id);
        
        if ($stmt->execute()) {
            $success_message = "Stream updated successfully!";
        } else {
            $error_message = "Error updating stream: " . $conn->error;
        }
        $stmt->close();
    }
}

// Refresh the result after any changes
if (isset($success_message) || isset($error_message)) {
    $result = $conn->query("SELECT * FROM streams WHERE is_active = 1 ORDER BY name");
}

// Get stream data for editing
$edit_stream = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $conn->real_escape_string($_GET['edit']);
    $edit_sql = "SELECT * FROM streams WHERE id = ? AND is_active = 1";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $edit_stream = $edit_result->fetch_assoc();
    }
    $edit_stmt->close();
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-clock me-2"></i>Stream Management</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStreamModal">
                <i class="fas fa-plus me-2"></i>Add New Stream
            </button>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="search-container m-3">
            <input type="text" class="search-input" placeholder="Search streams...">
        </div>

        <div class="table-responsive">
            <table class="table" id="streamsTable">
                <thead>
                    <tr>
                        <th>Stream Name</th>
                        <th>Active Days</th>
                        <th>Period Time</th>
                        <th>Break Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td>
                                    <?php 
                                    if (!empty($row['active_days'])) {
                                        $days = explode(',', $row['active_days']);
                                        foreach ($days as $day) {
                                            echo '<span class="badge bg-success me-1">' . htmlspecialchars(trim($day)) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">No days set</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-warning">
                                        <?php echo htmlspecialchars($row['period_start'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($row['period_end'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($row['break_start'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($row['break_end'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this stream?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="fas fa-clock"></i>
                                <p>No streams found. Add your first stream to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Stream Modal -->
<div class="modal fade" id="addStreamModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $edit_stream ? 'Edit Stream' : 'Add New Stream'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_stream ? 'edit' : 'add'; ?>">
                    <?php if ($edit_stream): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_stream['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Stream Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo $edit_stream ? htmlspecialchars($edit_stream['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Active Days *</label>
                        <div class="row">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            $edit_days = $edit_stream ? explode(',', $edit_stream['active_days']) : [];
                            foreach ($days as $day): 
                                $checked = in_array($day, $edit_days) ? 'checked' : '';
                            ?>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="<?php echo strtolower($day); ?>" 
                                           name="active_days[]" value="<?php echo $day; ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label" for="<?php echo strtolower($day); ?>"><?php echo $day; ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_start" class="form-label">Period Start Time *</label>
                                <select class="form-control" id="period_start" name="period_start" required>
                                    <option value="">Select Start Time</option>
                                    <?php for ($hour = 6; $hour <= 22; $hour++): 
                                        $time = sprintf('%02d:00', $hour);
                                        $selected = ($edit_stream && $edit_stream['period_start'] == $time) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $time; ?>" <?php echo $selected; ?>><?php echo $time; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="period_end" class="form-label">Period End Time *</label>
                                <select class="form-control" id="period_end" name="period_end" required>
                                    <option value="">Select End Time</option>
                                    <?php for ($hour = 7; $hour <= 23; $hour++): 
                                        $time = sprintf('%02d:00', $hour);
                                        $selected = ($edit_stream && $edit_stream['period_end'] == $time) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $time; ?>" <?php echo $selected; ?>><?php echo $time; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="break_start" class="form-label">Break Start Time</label>
                                <select class="form-control" id="break_start" name="break_start">
                                    <option value="">Select Break Start</option>
                                    <?php for ($hour = 6; $hour <= 22; $hour++): 
                                        $time = sprintf('%02d:00', $hour);
                                        $selected = ($edit_stream && $edit_stream['break_start'] == $time) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $time; ?>" <?php echo $selected; ?>><?php echo $time; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="break_end" class="form-label">Break End Time</label>
                                <select class="form-control" id="break_end" name="break_end">
                                    <option value="">Select Break End</option>
                                    <?php for ($hour = 7; $hour <= 23; $hour++): 
                                        $time = sprintf('%02d:00', $hour);
                                        $selected = ($edit_stream && $edit_stream['break_end'] == $time) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $time; ?>" <?php echo $selected; ?>><?php echo $time; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                   <?php echo ($edit_stream && $edit_stream['is_active'] == 1) || !$edit_stream ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Stream Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><?php echo $edit_stream ? 'Update Stream' : 'Add Stream'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
// Auto-open modal if editing
<?php if ($edit_stream): ?>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('addStreamModal'));
    modal.show();
});
<?php endif; ?>

// Add validation to ensure period end is after period start
document.getElementById('period_end').addEventListener('change', function() {
    const startTime = document.getElementById('period_start').value;
    const endTime = this.value;
    
    if (startTime && endTime && startTime >= endTime) {
        alert('Period end time must be after period start time');
        this.value = '';
    }
});

// Add validation to ensure break end is after break start
document.getElementById('break_end').addEventListener('change', function() {
    const startTime = document.getElementById('break_start').value;
    const endTime = this.value;
    
    if (startTime && endTime && startTime >= endTime) {
        alert('Break end time must be after break start time');
        this.value = '';
    }
});

// Search functionality
document.querySelector('.search-input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#streamsTable tbody tr');
    
    tableRows.forEach(row => {
        const streamName = row.querySelector('td:first-child').textContent.toLowerCase();
        if (streamName.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>
