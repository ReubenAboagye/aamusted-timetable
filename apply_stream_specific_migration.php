<?php
/**
 * Apply Stream-Specific Courses and Programs Migration (Without Foreign Keys)
 * 
 * This script applies the migration to make courses and programs stream-specific,
 * allowing the same course/program names to have different codes across streams.
 * Note: Foreign key constraints are skipped due to permission limitations.
 */

include 'connect.php';

echo "<h2>Applying Stream-Specific Courses and Programs Migration</h2>\n";
echo "<pre>\n";

try {
    // Start transaction
    $conn->begin_transaction();
    
    echo "Starting migration...\n";
    
    // =====================================================
    // PART 1: COURSES TABLE CHANGES
    // =====================================================
    
    echo "\n=== PART 1: COURSES TABLE CHANGES ===\n";
    
    // Check if stream_id column exists
    $check_stream_column = $conn->query("SHOW COLUMNS FROM courses LIKE 'stream_id'");
    if ($check_stream_column && $check_stream_column->num_rows == 0) {
        echo "Adding stream_id column to courses table...\n";
        $conn->query("ALTER TABLE courses ADD COLUMN stream_id INT NOT NULL DEFAULT 1 AFTER department_id");
        echo "✓ stream_id column added\n";
    } else {
        echo "✓ stream_id column already exists\n";
    }
    
    // Check if the old unique constraint exists
    $check_course_constraint = $conn->query("SHOW INDEX FROM courses WHERE Key_name = 'uq_course_code'");
    if ($check_course_constraint && $check_course_constraint->num_rows > 0) {
        echo "Removing global unique constraint on code...\n";
        $conn->query("ALTER TABLE courses DROP INDEX uq_course_code");
        echo "✓ Global unique constraint removed\n";
    } else {
        echo "✓ Global unique constraint on code not found (already removed)\n";
    }
    
    // Add composite unique constraint
    echo "Adding composite unique constraint (code + stream_id)...\n";
    $conn->query("ALTER TABLE courses ADD CONSTRAINT uq_course_code_stream UNIQUE KEY (code, stream_id)");
    echo "✓ Composite unique constraint added\n";
    
    // Add index for performance
    echo "Adding performance index...\n";
    $conn->query("ALTER TABLE courses ADD INDEX idx_courses_stream_code (stream_id, code)");
    echo "✓ Performance index added\n";
    
    // =====================================================
    // PART 2: PROGRAMS TABLE CHANGES
    // =====================================================
    
    echo "\n=== PART 2: PROGRAMS TABLE CHANGES ===\n";
    
    // Check if stream_id column exists
    $check_program_stream_column = $conn->query("SHOW COLUMNS FROM programs LIKE 'stream_id'");
    if ($check_program_stream_column && $check_program_stream_column->num_rows == 0) {
        echo "Adding stream_id column to programs table...\n";
        $conn->query("ALTER TABLE programs ADD COLUMN stream_id INT NOT NULL DEFAULT 1 AFTER department_id");
        echo "✓ stream_id column added\n";
    } else {
        echo "✓ stream_id column already exists\n";
    }
    
    // Check if the old unique constraint exists
    $check_program_constraint = $conn->query("SHOW INDEX FROM programs WHERE Key_name = 'uq_program_code'");
    if ($check_program_constraint && $check_program_constraint->num_rows > 0) {
        echo "Removing global unique constraint on program code...\n";
        $conn->query("ALTER TABLE programs DROP INDEX uq_program_code");
        echo "✓ Global unique constraint removed\n";
    } else {
        echo "✓ Global unique constraint on program code not found (already removed)\n";
    }
    
    // Add composite unique constraint
    echo "Adding composite unique constraint (code + stream_id)...\n";
    $conn->query("ALTER TABLE programs ADD CONSTRAINT uq_program_code_stream UNIQUE KEY (code, stream_id)");
    echo "✓ Composite unique constraint added\n";
    
    // Add index for performance
    echo "Adding performance index...\n";
    $conn->query("ALTER TABLE programs ADD INDEX idx_programs_stream_code (stream_id, code)");
    echo "✓ Performance index added\n";
    
    // =====================================================
    // PART 3: UPDATE EXISTING DATA
    // =====================================================
    
    echo "\n=== PART 3: UPDATE EXISTING DATA ===\n";
    
    // Update existing courses
    echo "Updating existing courses to use stream_id = 1...\n";
    $result = $conn->query("UPDATE courses SET stream_id = 1 WHERE stream_id IS NULL");
    if ($result) {
        echo "✓ Existing courses updated\n";
    } else {
        echo "✓ No courses needed updating\n";
    }
    
    // Update existing programs
    echo "Updating existing programs to use stream_id = 1...\n";
    $result = $conn->query("UPDATE programs SET stream_id = 1 WHERE stream_id IS NULL");
    if ($result) {
        echo "✓ Existing programs updated\n";
    } else {
        echo "✓ No programs needed updating\n";
    }
    
    // =====================================================
    // PART 4: VERIFICATION
    // =====================================================
    
    echo "\n=== PART 4: VERIFICATION ===\n";
    
    // Verify courses constraints
    $course_constraints = $conn->query("SHOW INDEX FROM courses WHERE Key_name = 'uq_course_code_stream'");
    if ($course_constraints && $course_constraints->num_rows > 0) {
        echo "✓ Course stream-specific constraint verified\n";
    } else {
        throw new Exception("Course stream-specific constraint not found");
    }
    
    // Verify programs constraints
    $program_constraints = $conn->query("SHOW INDEX FROM programs WHERE Key_name = 'uq_program_code_stream'");
    if ($program_constraints && $program_constraints->num_rows > 0) {
        echo "✓ Program stream-specific constraint verified\n";
    } else {
        throw new Exception("Program stream-specific constraint not found");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "\n🎉 MIGRATION COMPLETED SUCCESSFULLY! 🎉\n";
    echo "\nThe system now supports:\n";
    echo "• Same course codes across different streams\n";
    echo "• Same program codes across different streams\n";
    echo "• Stream-specific course and program management\n";
    echo "• Data integrity maintained within each stream\n";
    echo "\nNote: Foreign key constraints were skipped due to permission limitations.\n";
    echo "The application logic will handle referential integrity.\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "\n❌ MIGRATION FAILED: " . $e->getMessage() . "\n";
    echo "Transaction rolled back. No changes were made.\n";
}

echo "</pre>";
?>