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

// Get all lecturer conflicts from database
// First, find conflict groups (lecturer + time combinations with multiple entries)
$conflictsQuery = "SELECT lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year,
                   COUNT(*) as conflict_count
                   FROM timetable t
                   JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                   WHERE t.semester = ? AND t.academic_year = ? AND t.timetable_type = 'lecture'
                   GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year
                   HAVING conflict_count > 1
                   ORDER BY lc.lecturer_id, t.day_id, t.time_slot_id";

$current_semester = $_GET['semester'] ?? 'first';
$current_academic_year = $_GET['academic_year'] ?? date('Y') . '/' . (date('Y') + 1);

$stmt = $conn->prepare($conflictsQuery);
$stmt->bind_param("ss", $current_semester, $current_academic_year);
$stmt->execute();
$conflictGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get detailed conflict entries for each conflict group
$detailedConflicts = [];
$conflicts = []; // Will store enriched conflict data

foreach ($conflictGroups as $conflictGroup) {
    $detailQuery = "SELECT t.*, lc.lecturer_id, l.name as lecturer_name, c.name as course_name, 
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
                    WHERE lc.lecturer_id = ? AND t.day_id = ? AND t.time_slot_id = ? 
                    AND t.semester = ? AND t.academic_year = ? AND t.timetable_type = 'lecture'
                    ORDER BY t.id";
    
    $stmt = $conn->prepare($detailQuery);
    $stmt->bind_param("iiiss", 
        $conflictGroup['lecturer_id'], 
        $conflictGroup['day_id'], 
        $conflictGroup['time_slot_id'],
        $conflictGroup['semester'],
        $conflictGroup['academic_year']
    );
    $stmt->execute();
    $conflictEntries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (!empty($conflictEntries)) {
        // Create enriched conflict data with details from first entry
        $firstEntry = $conflictEntries[0];
        $conflict = [
            'lecturer_id' => $conflictGroup['lecturer_id'],
            'day_id' => $conflictGroup['day_id'],
            'time_slot_id' => $conflictGroup['time_slot_id'],
            'semester' => $conflictGroup['semester'],
            'academic_year' => $conflictGroup['academic_year'],
            'conflict_count' => $conflictGroup['conflict_count'],
            'lecturer_name' => $firstEntry['lecturer_name'],
            'day_name' => $firstEntry['day_name'],
            'start_time' => $firstEntry['start_time'],
            'end_time' => $firstEntry['end_time']
        ];
        
        $conflicts[] = $conflict;
        $detailedConflicts[$conflictGroup['lecturer_id'] . '-' . $conflictGroup['day_id'] . '-' . $conflictGroup['time_slot_id']] = $conflictEntries;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div id="mainContent" class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Lecturer Conflicts Management</h2>
        <div>
            <a href="generate_timetable.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Generation
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
                <div class="col-md-4">
                    <label for="semester" class="form-label">Semester</label>
                    <select name="semester" id="semester" class="form-select">
                        <option value="first" <?= $current_semester === 'first' ? 'selected' : '' ?>>First Semester</option>
                        <option value="second" <?= $current_semester === 'second' ? 'selected' : '' ?>>Second Semester</option>
                        <option value="summer" <?= $current_semester === 'summer' ? 'selected' : '' ?>>Summer</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <input type="text" name="academic_year" id="academic_year" class="form-control" 
                           value="<?= htmlspecialchars($current_academic_year) ?>" placeholder="e.g., 2025/2026">
                </div>
                <div class="col-md-4">
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
            <h5 class="mb-0">Lecturer Conflicts (<?= count($conflicts) ?> found)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($conflicts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5 class="text-muted">No lecturer conflicts found!</h5>
                    <p class="text-muted">All lecturers have been scheduled without conflicts for the selected semester and academic year.</p>
                </div>
            <?php else: ?>
                <?php foreach ($conflicts as $conflict): ?>
                    <?php 
                    $conflictKey = $conflict['lecturer_id'] . '-' . $conflict['day_id'] . '-' . $conflict['time_slot_id'];
                    $conflictEntries = $detailedConflicts[$conflictKey] ?? [];
                    ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0 text-danger">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Lecturer Conflict: <?= htmlspecialchars($conflict['lecturer_name']) ?>
                            </h6>
                            <span class="badge bg-danger"><?= $conflict['conflict_count'] ?> conflicts</span>
                        </div>
                        <p class="text-muted mb-3">
                            <strong>Time:</strong> <?= htmlspecialchars($conflict['day_name']) ?> 
                            <?= htmlspecialchars($conflict['start_time']) ?> - <?= htmlspecialchars($conflict['end_time']) ?>
                        </p>
                        
                        <div class="row">
                            <?php foreach ($conflictEntries as $entry): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="card border-warning">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?= htmlspecialchars($entry['class_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($entry['course_name']) ?></small><br>
                                                    <small class="text-muted">Room: <?= htmlspecialchars($entry['room_name']) ?></small>
                                                </div>
                                                <div>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this entry?')">
                                                        <input type="hidden" name="action" value="resolve_conflict">
                                                        <input type="hidden" name="conflict_id" value="<?= $entry['id'] ?>">
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
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
