<?php
$pageTitle = 'Levels Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Handle form submission for adding new level
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $year_number = $conn->real_escape_string($_POST['year_number']);
        
        $sql = "INSERT INTO levels (name, year_number) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $name, $year_number);
        
        if ($stmt->execute()) {
            $success_message = "Level added successfully!";
        } else {
            $error_message = "Error adding level: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $year_number = $conn->real_escape_string($_POST['year_number']);
        
        $sql = "UPDATE levels SET name = ?, year_number = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $name, $year_number, $id);
        
        if ($stmt->execute()) {
            $success_message = "Level updated successfully!";
        } else {
            $error_message = "Error updating level: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        
        // Check if level is being used in classes
        $check_sql = "SELECT COUNT(*) as count FROM classes WHERE level = (SELECT name FROM levels WHERE id = ?)";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $usage_count = $check_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($usage_count > 0) {
            $error_message = "Cannot delete level. It is being used by $usage_count class(es).";
        } else {
            $sql = "DELETE FROM levels WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Level deleted successfully!";
            } else {
                $error_message = "Error deleting level: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch levels
$sql = "SELECT * FROM levels ORDER BY year_number, name";
$result = $conn->query($sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-layer-group me-2"></i>Levels Management</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLevelModal">
                <i class="fas fa-plus me-2"></i>Add New Level
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
            <input type="text" class="search-input" placeholder="Search levels...">
        </div>

        <div class="table-responsive">
            <table class="table" id="levelsTable">
                <thead>
                    <tr>
                        <th>Level Name</th>
                        <th>Year Number</th>
                        <th>Usage Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            // Get usage count for this level
                            $usage_sql = "SELECT COUNT(*) as count FROM classes WHERE level = ?";
                            $usage_stmt = $conn->prepare($usage_sql);
                            $usage_stmt->bind_param("s", $row['name']);
                            $usage_stmt->execute();
                            $usage_result = $usage_stmt->get_result();
                            $usage_count = $usage_result->fetch_assoc()['count'];
                            $usage_stmt->close();
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($row['year_number']); ?></span></td>
                                <td>
                                    <?php if ($usage_count > 0): ?>
                                        <span class="badge bg-warning"><?php echo $usage_count; ?> class(es)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0 classes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editLevel(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>', <?php echo $row['year_number']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($usage_count == 0): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this level?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete - level is in use">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-layer-group"></i>
                                <p>No levels found. Add your first level to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Level Modal -->
<div class="modal fade" id="addLevelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Level Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g., Level 100, Year 1, First Year">
                        <small class="form-text text-muted">This is the display name for the level</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="year_number" class="form-label">Year Number *</label>
                        <input type="number" class="form-control" id="year_number" name="year_number" min="1" max="10" value="1" required>
                        <small class="form-text text-muted">Numeric representation (1 = First Year, 2 = Second Year, etc.)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Level</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Level Modal -->
<div class="modal fade" id="editLevelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Level Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_year_number" class="form-label">Year Number *</label>
                        <input type="number" class="form-control" id="edit_year_number" name="year_number" min="1" max="10" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Level</button>
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
function editLevel(id, name, yearNumber) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_year_number').value = yearNumber;
    
    // Show the edit modal
    const editModal = new bootstrap.Modal(document.getElementById('editLevelModal'));
    editModal.show();
}

// Search functionality
document.querySelector('.search-input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('levelsTable');
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
