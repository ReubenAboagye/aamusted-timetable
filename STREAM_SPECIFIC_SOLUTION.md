# Stream-Specific Courses and Programs Solution

## Problem Statement

The timetable system was experiencing conflicts when the same course (e.g., "Artificial Intelligence") needed different course codes for different streams:
- **Regular classes**: ITC343 - Artificial Intelligence
- **Evening classes**: ITC345 - Artificial Intelligence  
- **Weekend classes**: ITC347 - Artificial Intelligence

The original system had a global unique constraint on `course_code`, preventing the same course code from being used across different streams, even when the course names were identical.

## Solution Overview

The solution makes both **courses** and **programs** stream-specific by:

1. **Removing global unique constraints** on course codes and program codes
2. **Adding composite unique constraints** that allow the same code across different streams
3. **Updating application logic** to handle stream-specific validation
4. **Maintaining data integrity** within each stream

## Database Changes

### Courses Table
- **Removed**: `UNIQUE KEY course_code`
- **Added**: `UNIQUE KEY (course_code, stream_id)`
- **Added**: Index `idx_courses_stream_code (stream_id, course_code)`

### Programs Table  
- **Removed**: `UNIQUE KEY uq_program_code`
- **Added**: `UNIQUE KEY (code, stream_id)`
- **Added**: Index `idx_programs_stream_code (stream_id, code)`

## Migration Files

### 1. `migrations/stream_specific_courses_programs.sql`
Contains the complete SQL migration script with:
- Database schema changes
- Data updates
- Verification queries
- Example data

### 2. `apply_stream_specific_migration.php`
PHP script to safely apply the migration with:
- Transaction safety
- Error handling
- Verification steps
- Progress reporting

## Application Logic Updates

### AJAX API Changes (`ajax_api.php`)

#### Course Actions
- **Add**: Validates course codes within current stream only
- **Edit**: Updates courses with stream-specific validation
- **Delete**: Deletes courses within current stream only
- **Get List**: Filters courses by current stream
- **Bulk Import**: Imports courses to current stream

#### Program Actions (New Implementation)
- **Add**: Validates program codes within current stream only
- **Edit**: Updates programs with stream-specific validation  
- **Delete**: Deletes programs within current stream only
- **Get List**: Filters programs by current stream
- **Get Departments**: Retrieves departments for dropdowns

## Key Features

### ✅ Stream Isolation
- Each stream maintains its own course and program codes
- No conflicts between streams
- Clean separation of data

### ✅ Flexible Naming
- Same course name can have different codes per stream
- Same program name can have different codes per stream
- Maintains academic flexibility

### ✅ Data Integrity
- Prevents duplicate codes within the same stream
- Maintains referential integrity
- Proper constraint validation

### ✅ Performance Optimized
- Stream-specific indexes for fast queries
- Efficient filtering by stream
- Optimized database operations

## Usage Examples

### Adding Stream-Specific Courses

```php
// Regular Stream (ID: 1)
INSERT INTO courses (course_code, course_name, department_id, stream_id, ...) 
VALUES ('ITC343', 'Artificial Intelligence', 1, 1, ...);

// Evening Stream (ID: 3)  
INSERT INTO courses (course_code, course_name, department_id, stream_id, ...)
VALUES ('ITC345', 'Artificial Intelligence', 1, 3, ...);

// Weekend Stream (ID: 2)
INSERT INTO courses (course_code, course_name, department_id, stream_id, ...)
VALUES ('ITC347', 'Artificial Intelligence', 1, 2, ...);
```

### Adding Stream-Specific Programs

```php
// Regular Stream
INSERT INTO programs (name, code, department_id, stream_id, duration_years)
VALUES ('Computer Science', 'CS101', 1, 1, 4);

// Evening Stream
INSERT INTO programs (name, code, department_id, stream_id, duration_years)  
VALUES ('Computer Science', 'CS102', 1, 3, 4);
```

## Testing

### Test Script: `test_stream_specific_solution.php`

The test script verifies:
1. ✅ Migration status
2. ✅ Stream-specific course creation
3. ✅ Stream-specific program creation  
4. ✅ Duplicate prevention within streams
5. ✅ Cross-stream code allowance
6. ✅ Data integrity validation

## Implementation Steps

### 1. Apply Migration
```bash
# Run the migration script
php apply_stream_specific_migration.php
```

### 2. Test the Solution
```bash
# Run the test script
php test_stream_specific_solution.php
```

### 3. Update Existing Data
- Existing courses and programs are automatically assigned to stream_id = 1 (Regular)
- No data loss occurs during migration
- All existing functionality remains intact

## Benefits

### For Administrators
- **Flexible Course Management**: Same courses can have different codes per stream
- **Clear Organization**: Stream-specific data separation
- **No Conflicts**: Eliminates course code conflicts between streams

### For Students
- **Clear Identification**: Course codes are stream-specific
- **Consistent Naming**: Same course names across streams
- **Better Organization**: Stream-specific course listings

### For System
- **Data Integrity**: Proper constraint validation
- **Performance**: Optimized queries with stream-specific indexes
- **Scalability**: Easy to add new streams without conflicts

## Migration Safety

- **Transaction-based**: All changes are wrapped in transactions
- **Rollback Support**: Failed migrations are automatically rolled back
- **Data Preservation**: No existing data is lost
- **Verification**: Comprehensive verification steps included
- **Backup Recommended**: Always backup database before migration

## Future Enhancements

1. **Stream-Specific Lecturers**: Assign lecturers to specific streams
2. **Stream-Specific Rooms**: Room availability per stream
3. **Stream-Specific Time Slots**: Different time slots per stream
4. **Cross-Stream Reporting**: Reports across multiple streams
5. **Stream Templates**: Predefined course sets per stream

## Conclusion

This solution successfully resolves the course code conflicts by making courses and programs stream-specific while maintaining data integrity and system performance. The implementation is backward-compatible and provides a solid foundation for future stream-specific enhancements.
