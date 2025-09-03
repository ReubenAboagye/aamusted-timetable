<?php
/**
 * Stream Consistency Validation Script
 * This script checks and reports stream consistency issues across the database
 */

include 'connect.php';

// Include stream manager
if (file_exists(__DIR__ . '/includes/stream_manager.php')) {
    include_once __DIR__ . '/includes/stream_manager.php';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Consistency Validation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1>Stream Consistency Validation Report</h1>
    <p class="text-muted">Checking for stream consistency issues across the database...</p>
    
    <?php
    $issues_found = 0;
    $total_checks = 0;
    
    echo "<div class='row'>";
    
    // 1. Check class_courses stream consistency
    echo "<div class='col-md-6'>";
    echo "<div class='card mb-4'>";
    echo "<div class='card-header'><h5>Class-Course Stream Consistency</h5></div>";
    echo "<div class='card-body'>";
    
    $sql = "SELECT cc.id, cc.class_id, cc.course_id, c.stream_id as class_stream, co.stream_id as course_stream, 
                   c.name as class_name, co.course_code, cc.stream_id as cc_stream_id
            FROM class_courses cc
            JOIN classes c ON cc.class_id = c.id
            JOIN courses co ON cc.course_id = co.id
            WHERE c.stream_id != co.stream_id OR cc.stream_id != c.stream_id";
    
    $result = $conn->query($sql);
    $total_checks++;
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>❌ Found " . $result->num_rows . " stream inconsistencies:</strong>";
        echo "<ul class='mt-2'>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>Class '{$row['class_name']}' (Stream {$row['class_stream']}) assigned to Course '{$row['course_code']}' (Stream {$row['course_stream']})</li>";
            $issues_found++;
        }
        echo "</ul></div>";
    } else {
        echo "<div class='alert alert-success'>✅ All class-course assignments are stream-consistent</div>";
    }
    
    echo "</div></div></div>";
    
    // 2. Check lecturer-course assignments
    echo "<div class='col-md-6'>";
    echo "<div class='card mb-4'>";
    echo "<div class='card-header'><h5>Lecturer-Course Stream Consistency</h5></div>";
    echo "<div class='card-body'>";
    
    $sql = "SELECT lc.id, lc.lecturer_id, lc.course_id, l.stream_id as lecturer_stream, co.stream_id as course_stream,
                   l.name as lecturer_name, co.course_code
            FROM lecturer_courses lc
            JOIN lecturers l ON lc.lecturer_id = l.id
            JOIN courses co ON lc.course_id = co.id
            WHERE l.stream_id != co.stream_id";
    
    $result = $conn->query($sql);
    $total_checks++;
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>❌ Found " . $result->num_rows . " lecturer-course stream inconsistencies:</strong>";
        echo "<ul class='mt-2'>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>Lecturer '{$row['lecturer_name']}' (Stream {$row['lecturer_stream']}) assigned to Course '{$row['course_code']}' (Stream {$row['course_stream']})</li>";
            $issues_found++;
        }
        echo "</ul></div>";
    } else {
        echo "<div class='alert alert-success'>✅ All lecturer-course assignments are stream-consistent</div>";
    }
    
    echo "</div></div></div>";
    
    echo "</div>"; // End row
    
    echo "<div class='row'>";
    
    // 3. Check timetable stream consistency
    echo "<div class='col-md-12'>";
    echo "<div class='card mb-4'>";
    echo "<div class='card-header'><h5>Timetable Stream Consistency</h5></div>";
    echo "<div class='card-body'>";
    
    $sql = "SELECT t.id, c.stream_id as class_stream, co.stream_id as course_stream, 
                   l.stream_id as lecturer_stream, r.stream_id as room_stream,
                   c.name as class_name, co.course_code, l.name as lecturer_name, r.name as room_name
            FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            JOIN courses co ON cc.course_id = co.id
            JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
            JOIN lecturers l ON lc.lecturer_id = l.id
            JOIN rooms r ON t.room_id = r.id
            WHERE c.stream_id != co.stream_id 
               OR c.stream_id != l.stream_id 
               OR c.stream_id != r.stream_id";
    
    $result = $conn->query($sql);
    $total_checks++;
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>❌ Found " . $result->num_rows . " timetable stream inconsistencies:</strong>";
        echo "<div style='max-height: 300px; overflow-y: auto;'>";
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>Class</th><th>Course</th><th>Lecturer</th><th>Room</th><th>Streams</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['class_name']}</td>";
            echo "<td>{$row['course_code']}</td>";
            echo "<td>{$row['lecturer_name']}</td>";
            echo "<td>{$row['room_name']}</td>";
            echo "<td>C:{$row['class_stream']}, Co:{$row['course_stream']}, L:{$row['lecturer_stream']}, R:{$row['room_stream']}</td>";
            echo "</tr>";
            $issues_found++;
        }
        echo "</tbody></table>";
        echo "</div></div>";
    } else {
        echo "<div class='alert alert-success'>✅ All timetable entries are stream-consistent</div>";
    }
    
    echo "</div></div></div>";
    
    // 4. Check for orphaned records
    echo "<div class='col-md-12'>";
    echo "<div class='card mb-4'>";
    echo "<div class='card-header'><h5>Orphaned Records Check</h5></div>";
    echo "<div class='card-body'>";
    
    // Check for class_courses without valid classes
    $sql = "SELECT cc.id, cc.class_id, cc.course_id FROM class_courses cc LEFT JOIN classes c ON cc.class_id = c.id WHERE c.id IS NULL";
    $result = $conn->query($sql);
    $total_checks++;
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='alert alert-warning'>⚠️ Found " . $result->num_rows . " class_courses with invalid class references</div>";
        $issues_found += $result->num_rows;
    }
    
    // Check for class_courses without valid courses
    $sql = "SELECT cc.id, cc.class_id, cc.course_id FROM class_courses cc LEFT JOIN courses co ON cc.course_id = co.id WHERE co.id IS NULL";
    $result = $conn->query($sql);
    $total_checks++;
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='alert alert-warning'>⚠️ Found " . $result->num_rows . " class_courses with invalid course references</div>";
        $issues_found += $result->num_rows;
    }
    
    if ($issues_found == 0) {
        echo "<div class='alert alert-success'>✅ No orphaned records found</div>";
    }
    
    echo "</div></div></div>";
    
    echo "</div>"; // End row
    
    // Summary
    echo "<div class='card mt-4'>";
    echo "<div class='card-header'><h4>Validation Summary</h4></div>";
    echo "<div class='card-body'>";
    
    if ($issues_found == 0) {
        echo "<div class='alert alert-success'>";
        echo "<h5>🎉 Database is stream-consistent!</h5>";
        echo "<p>All $total_checks validation checks passed. No stream consistency issues found.</p>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-danger'>";
        echo "<h5>⚠️ Issues Found</h5>";
        echo "<p>Found $issues_found stream consistency issues across $total_checks checks.</p>";
        echo "<p><strong>Recommendation:</strong> Run the database migrations to fix these issues.</p>";
        echo "</div>";
    }
    
    // Stream statistics
    echo "<h5>Stream Statistics</h5>";
    $streams_sql = "SELECT s.id, s.name, s.code,
                           (SELECT COUNT(*) FROM classes WHERE stream_id = s.id AND is_active = 1) as class_count,
                           (SELECT COUNT(*) FROM courses WHERE stream_id = s.id AND is_active = 1) as course_count,
                           (SELECT COUNT(*) FROM lecturers WHERE stream_id = s.id AND is_active = 1) as lecturer_count,
                           (SELECT COUNT(*) FROM rooms WHERE stream_id = s.id AND is_active = 1) as room_count
                    FROM streams s WHERE s.is_active = 1 ORDER BY s.id";
    $streams_result = $conn->query($streams_sql);
    
    if ($streams_result && $streams_result->num_rows > 0) {
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>Stream</th><th>Classes</th><th>Courses</th><th>Lecturers</th><th>Rooms</th></tr></thead>";
        echo "<tbody>";
        while ($stream = $streams_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>{$stream['name']}</strong> ({$stream['code']})</td>";
            echo "<td>{$stream['class_count']}</td>";
            echo "<td>{$stream['course_count']}</td>";
            echo "<td>{$stream['lecturer_count']}</td>";
            echo "<td>{$stream['room_count']}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
    
    echo "</div></div>";
    ?>
    
    <div class="mt-4">
        <a href="run_migrations.php" class="btn btn-warning">Run Database Migrations</a>
        <a href="index.php" class="btn btn-secondary">Return to Dashboard</a>
        <a href="generate_timetable.php" class="btn btn-primary">Generate Timetable</a>
    </div>
</div>
</body>
</html>
