<?php
/**
 * Semester Helper Functions
 * Provides consistent semester representation across the application
 */

if (!function_exists('normalizeSemester')) {
    /**
     * Normalize semester input to string representation
     * @param mixed $semester Input semester (1, 2, 'first', 'second', etc.)
     * @return string Normalized semester ('first' or 'second')
     */
    function normalizeSemester($semester) {
        if (is_numeric($semester)) {
            return ($semester == 1) ? 'first' : 'second';
        }
        
        $semester = strtolower(trim($semester));
        
        switch ($semester) {
            case '1':
            case 'first':
            case 'semester1':
            case 'sem1':
                return 'first';
            case '2':
            case 'second':
            case 'semester2':
            case 'sem2':
                return 'second';
            default:
                return 'second'; // Default fallback
        }
    }
}

if (!function_exists('semesterToNumeric')) {
    /**
     * Convert semester to numeric representation
     * @param mixed $semester Input semester
     * @return int Numeric semester (1 or 2)
     */
    function semesterToNumeric($semester) {
        $normalized = normalizeSemester($semester);
        return ($normalized === 'first') ? 1 : 2;
    }
}

if (!function_exists('semesterToDisplay')) {
    /**
     * Convert semester to display format
     * @param mixed $semester Input semester
     * @return string Display format ('First Semester' or 'Second Semester')
     */
    function semesterToDisplay($semester) {
        $normalized = normalizeSemester($semester);
        return ucfirst($normalized) . ' Semester';
    }
}

if (!function_exists('validateSemester')) {
    /**
     * Validate if semester is valid
     * @param mixed $semester Input semester
     * @return bool True if valid
     */
    function validateSemester($semester) {
        $normalized = normalizeSemester($semester);
        return in_array($normalized, ['first', 'second']);
    }
}
?>