<?php
/**
 * Lecturer Conflicts Management Page
 * This page allows administrators to view and resolve lecturer scheduling conflicts
 */

include 'connect.php';

// Start session to access stored conflicts
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle conflict resolution
if ($_POST['action'] ?? '' === 'resolve_conflict') {
    $conflict_id = (int)($_POST['conflict_id'] ?? 0);
    $resolution = $_POST['resolution'] ?? '';
    
    if ($conflict_id && $resolution) {
        // Get conflict details
        $conflictQuery = "SELECT t.*, lc.lecturer_id, l.name as lecturer_name, c.name as course_name, 
                         cl.name as class_name, d.name as day_name, ts.start_time, ts.end_time, r.name as room_name
                         FROM timetable t
                         JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                         JOIN lecturers l ON lc.lecturer_id = l.id
                         JOIN class_courses cc ON t.class_course_id = cc.id
                         JOIN classes cl ON cc.class_id = cl.id
                         JOIN courses c ON cc.course_id = c.id
                         JOIN days d ON t.day_id = d.id
                         JOIN time_slots ts ON t.time_slot_id = ts.id
                         JOIN rooms r ON t.room_id = r.id
                         WHERE t.id = ?";
        
        $stmt = $conn->prepare($conflictQuery);
        $stmt->bind_param("i", $conflict_id);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($conflict) {
            if ($resolution === 'delete') {
                // Delete the conflicting entry
                $deleteQuery = "DELETE FROM timetable WHERE id = ?";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("i", $conflict_id);
                if ($stmt->execute()) {
                    $success_message = "Conflict resolved: Entry deleted successfully.";
                } else {
                    $error_message = "Failed to delete entry: " . $conn->error;
                }
                $stmt->close();
            } elseif ($resolution === 'reschedule') {
                // Mark for rescheduling (could be implemented later)
                $success_message = "Entry marked for rescheduling. Manual intervention required.";
            }
        }
    }
}

// Get filter parameters
$current_semester = $_GET['semester'] ?? 'second';  // Default to 'second' where the data exists
$current_academic_year = $_GET['academic_year'] ?? '2025/2026';  // Use the actual academic year
$current_version = $_GET['version'] ?? null;

// Initialize empty arrays - data will be loaded via AJAX
$conflicts = [];
$detailedConflicts = [];

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div id="mainContent" class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Lecturer Conflicts Management
        <?php if ($current_version): ?>
            <small class="text-muted">- Version: <?php echo htmlspecialchars($current_version); ?></small>
        <?php endif; ?>
        </h2>
        <div>
            <?php if ($current_version): ?>
                <a href="generate_timetable.php?edit_stream_id=<?php echo $current_stream_id ?? 1; ?>&version=<?php echo urlencode($current_version); ?>&semester=<?php echo urlencode($current_semester); ?>" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Back to Version
                </a>
            <?php endif; ?>
            <a href="generate_timetable.php" class="btn btn-primary">
                <i class="fas fa-cogs"></i> Generate Timetable
            </a>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Options -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="semester" class="form-label">Semester</label>
                    <select name="semester" id="semester" class="form-select">
                        <option value="first" <?= $current_semester === 'first' ? 'selected' : '' ?>>First Semester</option>
                        <option value="second" <?= $current_semester === 'second' ? 'selected' : '' ?>>Second Semester</option>
                        <option value="summer" <?= $current_semester === 'summer' ? 'selected' : '' ?>>Summer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <input type="text" name="academic_year" id="academic_year" class="form-control" 
                           value="<?= htmlspecialchars($current_academic_year) ?>" placeholder="e.g., 2025/2026">
                </div>
                <div class="col-md-3">
                    <label for="version" class="form-label">Timetable Version</label>
                    <select name="version" id="version" class="form-select">
                        <option value="">All Versions</option>
                        <?php
                        // Get available versions for the current semester and academic year
                        $versions_query = "SELECT DISTINCT version FROM timetable 
                                         WHERE semester = ? AND academic_year = ? AND timetable_type = 'lecture' 
                                         ORDER BY version";
                        $stmt = $conn->prepare($versions_query);
                        $stmt->bind_param("ss", $current_semester, $current_academic_year);
                        $stmt->execute();
                        $versions_result = $stmt->get_result();
                        
                        while ($version_row = $versions_result->fetch_assoc()) {
                            $selected = ($current_version === $version_row['version']) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($version_row['version']) . "\" $selected>" . 
                                 htmlspecialchars($version_row['version']) . "</option>";
                        }
                        $stmt->close();
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="lecturer_conflicts.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Conflicts List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Lecturer Conflicts (<span id="conflict-count">Loading...</span>)
                <?php if ($current_version): ?>
                    <small class="text-muted">- Version: <?= htmlspecialchars($current_version) ?></small>
                <?php else: ?>
                    <small class="text-muted">- All Versions</small>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <!-- Loading indicator -->
            <div id="loading-indicator" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading lecturer conflicts...</p>
            </div>
            
            <!-- Conflicts container -->
            <div id="conflicts-container" style="display: none;">
                <!-- Conflicts will be loaded here via AJAX -->
            </div>
            
            <!-- No conflicts message -->
            <div id="no-conflicts" class="text-center py-4" style="display: none;">
                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                <h5 class="text-muted">No lecturer conflicts found!</h5>
                <p class="text-muted">All lecturers have been scheduled without conflicts for the selected semester and academic year.</p>
            </div>
            
            <!-- Pagination -->
            <div id="pagination-container" class="d-flex justify-content-between align-items-center mt-3" style="display: none;">
                <div>
                    <button id="prev-page" class="btn btn-outline-secondary" disabled>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button id="next-page" class="btn btn-outline-secondary" disabled>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div>
                    <span id="page-info" class="text-muted"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
let currentFilters = {
    semester: '<?= $current_semester ?>',
    academic_year: '<?= $current_academic_year ?>',
    version: '<?= $current_version ?>'
};

// Load conflicts on page load
document.addEventListener('DOMContentLoaded', function() {
    loadConflicts();
    
    // Handle form submission
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        currentFilters.semester = document.getElementById('semester').value;
        currentFilters.academic_year = document.getElementById('academic_year').value;
        currentFilters.version = document.getElementById('version').value;
        currentPage = 1;
        loadConflicts();
    });
    
    // Handle pagination
    document.getElementById('prev-page').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadConflicts();
        }
    });
    
    document.getElementById('next-page').addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadConflicts();
        }
    });
});

function loadConflicts() {
    const loadingIndicator = document.getElementById('loading-indicator');
    const conflictsContainer = document.getElementById('conflicts-container');
    const noConflicts = document.getElementById('no-conflicts');
    const paginationContainer = document.getElementById('pagination-container');
    const conflictCount = document.getElementById('conflict-count');
    
    // Show loading
    loadingIndicator.style.display = 'block';
    conflictsContainer.style.display = 'none';
    noConflicts.style.display = 'none';
    paginationContainer.style.display = 'none';
    
    // Build URL
    const params = new URLSearchParams({
        semester: currentFilters.semester,
        academic_year: currentFilters.academic_year,
        page: currentPage,
        limit: 20
    });
    
    if (currentFilters.version) {
        params.append('version', currentFilters.version);
    }
    
    // Fetch conflicts
    fetch(`lecturer_conflicts_ajax.php?${params}`)
        .then(response => response.json())
        .then(data => {
            loadingIndicator.style.display = 'none';
            
            if (data.success) {
                if (data.conflicts.length > 0) {
                    displayConflicts(data.conflicts, data.detailedConflicts);
                    updatePagination(data.pagination);
                    conflictCount.textContent = data.pagination.total_count;
                } else {
                    noConflicts.style.display = 'block';
                    conflictCount.textContent = '0';
                }
            } else {
                alert('Error loading conflicts: ' + data.error);
            }
        })
        .catch(error => {
            loadingIndicator.style.display = 'none';
            alert('Error loading conflicts: ' + error.message);
        });
}

function displayConflicts(conflicts, detailedConflicts) {
    const container = document.getElementById('conflicts-container');
    const currentVersion = currentFilters.version;
    
    let html = '';
    
    conflicts.forEach(conflict => {
        const conflictKey = conflict.lecturer_id + '-' + conflict.day_id + '-' + conflict.time_slot_id;
        const conflictEntries = detailedConflicts[conflictKey] || [];
        
        html += `
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 text-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Lecturer Conflict: ${escapeHtml(conflict.lecturer_name)}
                    </h6>
                    <span class="badge bg-danger">${conflict.conflict_count} conflicts</span>
                </div>
                <p class="text-muted mb-3">
                    <strong>Time:</strong> ${escapeHtml(conflict.day_name)} 
                    ${escapeHtml(conflict.start_time)} - ${escapeHtml(conflict.end_time)}
                    ${!currentVersion ? '<br><strong>Version:</strong> ' + escapeHtml(conflict.version) : ''}
                </p>
                
                <div class="row">
        `;
        
        conflictEntries.forEach(entry => {
            html += `
                <div class="col-md-6 mb-2">
                    <div class="card border-warning">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>${escapeHtml(entry.class_name)}</strong><br>
                                    <small class="text-muted">${escapeHtml(entry.course_name)}</small><br>
                                    <small class="text-muted">Room: ${escapeHtml(entry.room_name)}</small>
                                </div>
                                <div>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this entry?')">
                                        <input type="hidden" name="action" value="resolve_conflict">
                                        <input type="hidden" name="conflict_id" value="${entry.id}">
                                        <input type="hidden" name="resolution" value="delete">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete this entry">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.style.display = 'block';
}

function updatePagination(pagination) {
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');
    const paginationContainer = document.getElementById('pagination-container');
    
    currentPage = pagination.current_page;
    totalPages = pagination.total_pages;
    
    prevBtn.disabled = currentPage <= 1;
    nextBtn.disabled = currentPage >= totalPages;
    
    pageInfo.textContent = `Page ${currentPage} of ${totalPages} (${pagination.total_count} total conflicts)`;
    
    if (totalPages > 1) {
        paginationContainer.style.display = 'flex';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>
