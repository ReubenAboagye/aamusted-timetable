<?php
include 'connect.php';

echo "<h2>Semester Assignment Analysis</h2>\n";

// Check class_courses semester assignments
$sql = "SELECT cc.id, cc.class_id, cc.course_id, cc.semester, co.code as course_code
        FROM class_courses cc 
        JOIN courses co ON cc.course_id = co.id 
        WHERE cc.is_active = 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " class-course assignments</p>\n";
    
    $semester_issues = [];
    $correct_assignments = [];
    
    while ($row = $result->fetch_assoc()) {
        $courseCode = $row['course_code'];
        $currentSemester = $row['semester'];
        $expectedSemester = null;
        
        // Extract semester from course code
        if (preg_match('/(\d{3})/', $courseCode, $matches)) {
            $threeDigit = $matches[1];
            $secondDigit = (int)substr($threeDigit, 1, 1);
            $expectedSemester = ($secondDigit % 2 === 1) ? 'first' : 'second';
        }
        
        if ($expectedSemester && $expectedSemester !== $currentSemester) {
            $semester_issues[] = [
                'id' => $row['id'],
                'course_code' => $courseCode,
                'current_semester' => $currentSemester,
                'expected_semester' => $expectedSemester
            ];
        } else {
            $correct_assignments[] = [
                'id' => $row['id'],
                'course_code' => $courseCode,
                'semester' => $currentSemester
            ];
        }
    }
    
    echo "<h3>Semester Assignment Issues</h3>\n";
    if (empty($semester_issues)) {
        echo "<p style='color: green;'> All semester assignments are correct!</p>\n";
    } else {
        echo "<p style='color: orange;'> Found " . count($semester_issues) . " semester assignment issues:</p>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>ID</th><th>Course Code</th><th>Current Semester</th><th>Expected Semester</th></tr>\n";
        
        foreach ($semester_issues as $issue) {
            echo "<tr>";
            echo "<td>" . $issue['id'] . "</td>";
            echo "<td>" . $issue['course_code'] . "</td>";
            echo "<td>" . $issue['current_semester'] . "</td>";
            echo "<td>" . $issue['expected_semester'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        echo "<p><strong>Recommendation:</strong> Run the fix_semester_assignments.php script to correct these issues.</p>\n";
    }
    
    echo "<h3>Correct Assignments</h3>\n";
    echo "<p> " . count($correct_assignments) . " assignments are correct</p>\n";
    
} else {
    echo "<p style='color: red;'>No class-course assignments found or error querying database</p>\n";
}

// Check for academic year assignments
echo "<h3>Academic Year Analysis</h3>\n";
$sql = "SELECT academic_year, COUNT(*) as count FROM class_courses WHERE is_active = 1 GROUP BY academic_year";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<p>Academic year distribution:</p>\n";
    echo "<ul>\n";
    while ($row = $result->fetch_assoc()) {
        $year = $row['academic_year'] ?: 'NULL';
        echo "<li>$year: " . $row['count'] . " assignments</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p style='color: orange;'> No academic year data found</p>\n";
}

// Check stream assignments
echo "<h3>Stream Analysis</h3>\n";
$sql = "SELECT c.stream_id, s.name as stream_name, COUNT(*) as count 
        FROM class_courses cc 
        JOIN classes c ON cc.class_id = c.id 
        JOIN streams s ON c.stream_id = s.id 
        WHERE cc.is_active = 1 
        GROUP BY c.stream_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<p>Stream distribution:</p>\n";
    echo "<ul>\n";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . $row['stream_name'] . " (ID: " . $row['stream_id'] . "): " . $row['count'] . " assignments</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p style='color: orange;'> No stream data found</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Semester analysis completed.</strong></p>\n";
?>
