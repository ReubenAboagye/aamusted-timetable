<?php
include 'connect.php';

echo "<h2>Debugging Semester Filtering Issue</h2>\n";

// Test parameters
$stream_id = 1;
$academic_year = '2025/2026';
$semester = 1;

echo "<p><strong>Parameters:</strong></p>\n";
echo "<ul>\n";
echo "<li>Stream ID: $stream_id</li>\n";
echo "<li>Academic Year: $academic_year</li>\n";
echo "<li>Semester: $semester</li>\n";
echo "</ul>\n";

try {
    // Step 1: Check raw class_courses data
    echo "<h3>Step 1: Raw Class Courses Data</h3>\n";
    $sql = "SELECT cc.id, cc.class_id, cc.course_id, cc.lecturer_id, cc.semester, cc.academic_year, cc.is_active, co.code as course_code
            FROM class_courses cc 
            JOIN classes c ON cc.class_id = c.id
            JOIN courses co ON cc.course_id = co.id
            WHERE cc.is_active = 1 AND c.stream_id = " . intval($stream_id) . " AND cc.academic_year = '" . $conn->real_escape_string($academic_year) . "'";
    
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        echo "<p>Found " . $result->num_rows . " class courses before semester filtering:</p>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>ID</th><th>Course Code</th><th>DB Semester</th><th>Expected Semester (from code)</th></tr>\n";
        
        while ($row = $result->fetch_assoc()) {
            $courseCode = $row['course_code'];
            $expectedSemester = null;
            
            if (preg_match('/(\d{3})/', $courseCode, $matches)) {
                $threeDigit = $matches[1];
                $secondDigit = (int)substr($threeDigit, 1, 1);
                $expectedSemester = ($secondDigit % 2 === 1) ? 1 : 2;
            }
            
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $courseCode . "</td>";
            echo "<td>" . $row['semester'] . "</td>";
            echo "<td>" . ($expectedSemester ? $expectedSemester : 'Unknown') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p style='color: red;'>No class courses found in database!</p>\n";
    }
    
    // Step 2: Test the semester filtering logic
    echo "<h3>Step 2: Semester Filtering Logic Test</h3>\n";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $filteredCount = 0;
        $unfilteredCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            $courseCode = $row['course_code'];
            $isInSemester = false;
            
            if (preg_match('/(\d{3})/', $courseCode, $matches)) {
                $threeDigit = $matches[1];
                $secondDigit = (int)substr($threeDigit, 1, 1);
                
                if ($semester == 1) {
                    // First semester: second digit is odd (1,3,5,7,9)
                    $isInSemester = $secondDigit % 2 === 1;
                } else {
                    // Second semester: second digit is even (2,4,6,8,0)
                    $isInSemester = $secondDigit % 2 === 0;
                }
            } else {
                // If no 3-digit pattern found, include in both semesters
                $isInSemester = true;
            }
            
            if ($isInSemester) {
                $filteredCount++;
            } else {
                $unfilteredCount++;
            }
        }
        
        echo "<p>Semester filtering results:</p>\n";
        echo "<ul>\n";
        echo "<li>Total class courses: " . $result->num_rows . "</li>\n";
        echo "<li>In semester $semester: $filteredCount</li>\n";
        echo "<li>Not in semester $semester: $unfilteredCount</li>\n";
        echo "</ul>\n";
        
        if ($filteredCount == 0) {
            echo "<p style='color: red;'>❌ All class courses were filtered out!</p>\n";
            echo "<p>This means either:</p>\n";
            echo "<ul>\n";
            echo "<li>Course codes don't follow the expected pattern</li>\n";
            echo "<li>Semester filtering logic is too strict</li>\n";
            echo "<li>All courses belong to the other semester</li>\n";
            echo "</ul>\n";
        }
    }
    
    // Step 3: Check course codes pattern
    echo "<h3>Step 3: Course Code Pattern Analysis</h3>\n";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $patterns = [];
        
        while ($row = $result->fetch_assoc()) {
            $courseCode = $row['course_code'];
            if (preg_match('/(\d{3})/', $courseCode, $matches)) {
                $threeDigit = $matches[1];
                $secondDigit = (int)substr($threeDigit, 1, 1);
                $patterns[] = [
                    'course_code' => $courseCode,
                    'three_digit' => $threeDigit,
                    'second_digit' => $secondDigit,
                    'semester' => ($secondDigit % 2 === 1) ? 1 : 2
                ];
            } else {
                $patterns[] = [
                    'course_code' => $courseCode,
                    'three_digit' => 'N/A',
                    'second_digit' => 'N/A',
                    'semester' => 'Unknown'
                ];
            }
        }
        
        echo "<p>Course code patterns:</p>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Course Code</th><th>3-Digit</th><th>2nd Digit</th><th>Expected Semester</th></tr>\n";
        
        foreach ($patterns as $pattern) {
            echo "<tr>";
            echo "<td>" . $pattern['course_code'] . "</td>";
            echo "<td>" . $pattern['three_digit'] . "</td>";
            echo "<td>" . $pattern['second_digit'] . "</td>";
            echo "<td>" . $pattern['semester'] . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Step 4: Test with different semester
    echo "<h3>Step 4: Test with Semester 2</h3>\n";
    $semester2 = 2;
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $filteredCount2 = 0;
        
        while ($row = $result->fetch_assoc()) {
            $courseCode = $row['course_code'];
            $isInSemester = false;
            
            if (preg_match('/(\d{3})/', $courseCode, $matches)) {
                $threeDigit = $matches[1];
                $secondDigit = (int)substr($threeDigit, 1, 1);
                $isInSemester = $secondDigit % 2 === 0; // Even for semester 2
            } else {
                $isInSemester = true;
            }
            
            if ($isInSemester) {
                $filteredCount2++;
            }
        }
        
        echo "<p>Semester 2 filtering results: $filteredCount2 courses</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Debugging completed.</strong></p>\n";
?>
