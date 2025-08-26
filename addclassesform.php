<?php
include 'connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $class_name    = $_POST['class_name'];
    $department    = $_POST['department'];
    $level         = $_POST['level'];
    $class_session = $_POST['class_session'];
    $anydis        = $_POST['anydis'];
    $capacity      = $_POST['capacity'];
    
    // Retrieve courses from each semester (if any)
    $sem1_courses = isset($_POST['sem1_courses']) ? $_POST['sem1_courses'] : [];
    $sem2_courses = isset($_POST['sem2_courses']) ? $_POST['sem2_courses'] : [];

    // Insert class details into the class table
    $sql = "INSERT INTO class (class_name, department, level, class_session, anydis, capacity) 
            VALUES (?, ?, ?, ?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssss", $class_name, $department, $level, $class_session, $anydis, $capacity);
        if ($stmt->execute()) {
            $class_id = $stmt->insert_id; // Get the new class ID

            // Insert Semester 1 courses into the class_course table
            if (!empty($sem1_courses)) {
                foreach ($sem1_courses as $course_id) {
                    $sql_course = "INSERT INTO class_course (class_id, course_id, semester) VALUES (?, ?, 1)";
                    if ($stmt_course = $conn->prepare($sql_course)) {
                        $stmt_course->bind_param("ii", $class_id, $course_id);
                        $stmt_course->execute();
                        $stmt_course->close();
                    } else {
                        echo "Error preparing sem1 course insertion: " . $conn->error;
                    }
                }
            }

            // Insert Semester 2 courses into the class_course table
            if (!empty($sem2_courses)) {
                foreach ($sem2_courses as $course_id) {
                    $sql_course = "INSERT INTO class_course (class_id, course_id, semester) VALUES (?, ?, 2)";
                    if ($stmt_course = $conn->prepare($sql_course)) {
                        $stmt_course->bind_param("ii", $class_id, $course_id);
                        $stmt_course->execute();
                        $stmt_course->close();
                    } else {
                        echo "Error preparing sem2 course insertion: " . $conn->error;
                    }
                }
            }

            // Redirect on success
            header("Location: classes.php?status=success");
            exit();
        } else {
            echo "Error inserting class: " . $stmt->error;
            header("Location: classes.php?status=error");
            exit();
        }
        $stmt->close();
    } else {
        echo "Error preparing class insertion: " . $conn->error;
        header("Location: classes.php?status=error");
        exit();
    }
    $conn->close();
}
?>
