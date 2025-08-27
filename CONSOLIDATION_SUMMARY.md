# Sessions and Semesters Table Consolidation

## Overview
This document explains the consolidation of the `sessions` and `semesters` tables into a single `sessions` table to eliminate confusion and improve data management.

## What Was Consolidated

### Before (Two Separate Tables)
1. **`sessions` table**: Academic sessions with academic year + semester number
2. **`semesters` table**: Calendar semesters with names and dates

### After (One Consolidated Table)
1. **`sessions` table**: Combined academic sessions with all information in one place

## New Table Structure

```sql
CREATE TABLE sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  academic_year VARCHAR(20) NOT NULL,        -- e.g., "2024/2025"
  semester_number ENUM('1','2','3') NOT NULL, -- 1, 2, or 3
  semester_name VARCHAR(100) NOT NULL,        -- e.g., "First Semester 2024/2025"
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Ensure unique academic year + semester combinations
  UNIQUE KEY uq_academic_semester (academic_year, semester_number)
);
```

## Benefits of Consolidation

1. **Eliminates Confusion**: One table, one concept
2. **Better Data Integrity**: All session information in one place
3. **Easier Maintenance**: No need to sync data between tables
4. **Simpler Queries**: No JOINs needed between sessions and semesters
5. **Consistent Data**: Academic year, semester number, and dates always aligned

## Migration Process

### Files Created/Modified

1. **`migrate_to_consolidated_sessions.sql`** - Migration script
2. **`sessions.php`** - Updated to show semester name column
3. **`addsessionform.php`** - Updated to handle semester_name field
4. **`update_session.php`** - Updated to handle new structure
5. **`import_session.php`** - Updated for new structure

### Migration Steps

1. Create new consolidated table structure
2. Migrate existing sessions data
3. Migrate existing semesters data (if any)
4. Update foreign key references
5. Drop old tables
6. Rename new table to 'sessions' for backward compatibility

## Data Mapping

| Old Field | New Field | Description |
|-----------|-----------|-------------|
| `sessions.academic_year` | `sessions.academic_year` | Academic year (e.g., "2024/2025") |
| `sessions.semester` | `sessions.semester_number` | Semester number (1, 2, or 3) |
| `sessions.start_date` | `sessions.start_date` | Session start date |
| `sessions.end_date` | `sessions.end_date` | Session end date |
| `sessions.is_active` | `sessions.is_active` | Session status |
| `semesters.name` | `sessions.semester_name` | Full semester name |
| N/A | `sessions.created_at` | Creation timestamp |
| N/A | `sessions.updated_at` | Last update timestamp |

## Usage Examples

### Adding a New Session
```php
INSERT INTO sessions (academic_year, semester_number, semester_name, start_date, end_date, is_active) 
VALUES ('2024/2025', '1', 'First Semester 2024/2025', '2024-09-01', '2024-12-20', 1);
```

### Querying Active Sessions
```php
SELECT * FROM sessions WHERE is_active = 1 ORDER BY academic_year, semester_number;
```

### Finding Sessions by Academic Year
```php
SELECT * FROM sessions WHERE academic_year = '2024/2025';
```

## Backward Compatibility

- The table is still named `sessions` for existing code compatibility
- All existing foreign key relationships are maintained
- The `semester` field is now `semester_number` for clarity
- New `semester_name` field provides human-readable semester names

## Next Steps

1. **Run the migration script** on your database
2. **Test the updated forms** to ensure they work correctly
3. **Update any other code** that references the old table structure
4. **Remove the old `semesters.php`** file if no longer needed
5. **Update import/export scripts** to use the new structure

## Notes

- The `semesters.php` page can be removed or redirected to `sessions.php`
- All semester management is now handled through the sessions interface
- The consolidation maintains the same functionality while simplifying the data model
