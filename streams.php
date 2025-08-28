<?php
$pageTitle = 'Streams Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Handle form submission for adding new stream
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $sql = "INSERT INTO streams (name, code, description) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $name, $code, $description);
        
        if ($stmt->execute()) {
            $success_message = "Stream added successfully!";
        } else {
            $error_message = "Error adding stream: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $code = $conn->real_escape_string($_POST['code']);
        $description = $conn->real_escape_string($_POST['description']);
        
        $sql = "UPDATE streams SET name = ?, code = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $code, $description, $id);
        
        if ($stmt->execute()) {
            $success_message = "Stream updated successfully!";
        } else {
            $error_message = "Error updating stream: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        
        // Check if stream is being used in classes
        $check_sql = "SELECT COUNT(*) as count FROM classes WHERE stream_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $usage_count = $check_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($usage_count > 0) {
            $error_message = "Cannot delete stream. It is being used by $usage_count class(es).";
        } else {
            $sql = "DELETE FROM streams WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Stream deleted successfully!";
            } else {
                $error_message = "Error deleting stream: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch streams
$sql = "SELECT * FROM streams ORDER BY name";
$result = $conn->query($sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-clock me-2"></i>Streams Management</h4>
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
                        <th>Code</th>
                        <th>Description</th>
                        <th>Usage Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            // Get usage count for this stream
                            $usage_sql = "SELECT COUNT(*) as count FROM classes WHERE stream_id = ?";
                            $usage_stmt = $conn->prepare($usage_sql);
                            $usage_stmt->bind_param("i", $row['id']);
                            $usage_stmt->execute();
                            $usage_result = $usage_stmt->get_result();
                            $usage_count = $usage_result->fetch_assoc()['count'];
                            $usage_stmt->close();
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($row['code']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? 'No description'); ?></td>
                                <td>
                                    <?php if ($usage_count > 0): ?>
                                        <span class="badge bg-warning"><?php echo $usage_count; ?> class(es)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0 classes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editStream(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>', '<?php echo htmlspecialchars($row['code']); ?>', '<?php echo htmlspecialchars($row['description'] ?? ''); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($usage_count == 0): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this stream?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete - stream is in use">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
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

<!-- Add Stream Modal -->
<div class="modal fade" id="addStreamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Stream</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Stream Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g., Regular, Weekend, Evening">
                        <small class="form-text text-muted">Display name for the stream</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Stream Code *</label>
                        <input type="text" class="form-control" id="code" name="code" required placeholder="e.g., REG, WKD, EVE">
                        <small class="form-text text-muted">Short code for the stream (3-4 characters)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="e.g., Standard weekday classes (Monday to Friday)"></textarea>
                        <small class="form-text text-muted">Optional description of when this stream operates</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Stream</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Stream Modal -->
<div class="modal fade" id="editStreamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Stream</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Stream Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_code" class="form-label">Stream Code *</label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
function editStream(id, name, code, description) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_description').value = description;
    
    // Show the edit modal
    const editModal = new bootstrap.Modal(document.getElementById('editStreamModal'));
    editModal.show();
}

// Search functionality
document.querySelector('.search-input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('streamsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < cells.length - 1; j++) { // Exclude actions column
            const cellText = cells[j].textContent.toLowerCase();
            if (cellText.includes(searchTerm)) {
                found = true;
                break;
            }
        }
        
        row.style.display = found ? '' : 'none';
    }
});
</script>
