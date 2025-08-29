<?php
$pageTitle = 'Stream Filtering Test';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Include stream manager
include 'includes/stream_manager.php';
$streamManager = getStreamManager();

// Test queries to verify stream filtering
$current_stream_id = $streamManager->getCurrentStreamId();
$current_stream_name = $streamManager->getCurrentStreamName();

// Get counts for each stream
$stream_counts = [];
$streams = $streamManager->getAllStreams();

foreach ($streams as $stream) {
    // Count classes
    $classes_sql = "SELECT COUNT(*) as count FROM classes WHERE stream_id = " . $stream['id'] . " AND is_active = 1";
    $classes_result = $conn->query($classes_sql);
    $classes_count = $classes_result->fetch_assoc()['count'];
    
    // Count courses
    $courses_sql = "SELECT COUNT(*) as count FROM courses WHERE stream_id = " . $stream['id'] . " AND is_active = 1";
    $courses_result = $conn->query($courses_sql);
    $courses_count = $courses_result->fetch_assoc()['count'];
    
    // Count lecturers
    $lecturers_sql = "SELECT COUNT(*) as count FROM lecturers WHERE stream_id = " . $stream['id'] . " AND is_active = 1";
    $lecturers_result = $conn->query($lecturers_sql);
    $lecturers_count = $lecturers_result->fetch_assoc()['count'];
    
    // Count departments
    $departments_sql = "SELECT COUNT(*) as count FROM departments WHERE stream_id = " . $stream['id'] . " AND is_active = 1";
    $departments_result = $conn->query($departments_sql);
    $departments_count = $departments_result->fetch_assoc()['count'];
    
    $stream_counts[$stream['id']] = [
        'name' => $stream['name'],
        'classes' => $classes_count,
        'courses' => $courses_count,
        'lecturers' => $lecturers_count,
        'departments' => $departments_count
    ];
}

// Get sample data for current stream
$sample_classes = $conn->query("SELECT name, level FROM classes WHERE stream_id = " . $current_stream_id . " AND is_active = 1 LIMIT 5");
$sample_courses = $conn->query("SELECT name, code FROM courses WHERE stream_id = " . $current_stream_id . " AND is_active = 1 LIMIT 5");
$sample_lecturers = $conn->query("SELECT name FROM lecturers WHERE stream_id = " . $current_stream_id . " AND is_active = 1 LIMIT 5");
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header">
            <h4><i class="fas fa-test-tube me-2"></i>Stream Filtering Test</h4>
        </div>
        
        <div class="m-3">
            <div class="alert alert-info">
                <strong>Current Stream:</strong> <?php echo $streamManager->getStreamBadge(); ?>
                <br>
                <strong>Stream ID:</strong> <?php echo $current_stream_id; ?>
            </div>
        </div>

        <!-- Stream Overview -->
        <div class="row m-3">
            <div class="col-12">
                <h5>Stream Data Overview</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Stream</th>
                                <th>Classes</th>
                                <th>Courses</th>
                                <th>Lecturers</th>
                                <th>Departments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stream_counts as $stream_id => $counts): ?>
                                <tr class="<?php echo ($stream_id == $current_stream_id) ? 'table-primary' : ''; ?>">
                                    <td>
                                        <?php echo htmlspecialchars($counts['name']); ?>
                                        <?php if ($stream_id == $current_stream_id): ?>
                                            <span class="badge bg-success">Current</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $counts['classes']; ?></td>
                                    <td><?php echo $counts['courses']; ?></td>
                                    <td><?php echo $counts['lecturers']; ?></td>
                                    <td><?php echo $counts['departments']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sample Data for Current Stream -->
        <div class="row m-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>Sample Classes (Current Stream)</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($sample_classes && $sample_classes->num_rows > 0): ?>
                            <ul class="list-unstyled">
                                <?php while ($class = $sample_classes->fetch_assoc()): ?>
                                    <li class="mb-2">
                                        <strong><?php echo htmlspecialchars($class['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($class['level']); ?></small>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No classes found in current stream.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>Sample Courses (Current Stream)</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($sample_courses && $sample_courses->num_rows > 0): ?>
                            <ul class="list-unstyled">
                                <?php while ($course = $sample_courses->fetch_assoc()): ?>
                                    <li class="mb-2">
                                        <strong><?php echo htmlspecialchars($course['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($course['code']); ?></small>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No courses found in current stream.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>Sample Lecturers (Current Stream)</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($sample_lecturers && $sample_lecturers->num_rows > 0): ?>
                            <ul class="list-unstyled">
                                <?php while ($lecturer = $sample_lecturers->fetch_assoc()): ?>
                                    <li class="mb-2">
                                        <strong><?php echo htmlspecialchars($lecturer['name']); ?></strong>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No lecturers found in current stream.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Testing Instructions -->
        <div class="m-3">
            <div class="alert alert-warning">
                <h6>Testing Instructions:</h6>
                <ol>
                    <li>Use the stream selector in the header to switch between streams</li>
                    <li>Notice how the data changes for each stream</li>
                    <li>Verify that only data from the selected stream is displayed</li>
                    <li>Check that new records are automatically assigned to the current stream</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>
