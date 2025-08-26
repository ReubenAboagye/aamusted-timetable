<?php
// get_courses_by_department.php
include 'connect.php';

if(isset($_GET['department'])){
    $department = $conn->real_escape_string($_GET['department']);
    // Adjust the column name "department" if your courses table uses a different field name.
    $query = "SELECT * FROM course WHERE department = '$department'";
    $result = $conn->query($query);
    $courses = array();
    if($result && $result->num_rows > 0){
        while($row = $result->fetch_assoc()){
            $courses[] = $row;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($courses);
}
?>
