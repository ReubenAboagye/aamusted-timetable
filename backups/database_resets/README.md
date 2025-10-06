# Database Reset Backups

This directory contains automatic backups created before database resets.

## Backup File Naming Convention

Files are named with the pattern: `backup_before_reset_YYYY-MM-DD_HHMMSS.sql`

Example: `backup_before_reset_2025-10-06_143022.sql`

## How to Restore a Backup

### Method 1: Using phpMyAdmin
1. Log in to phpMyAdmin
2. Select the `timetable_system` database
3. Click on the "Import" tab
4. Choose the backup file (.sql)
5. Click "Go" to import

### Method 2: Using MySQL Command Line
```bash
mysql -u username -p timetable_system < backup_before_reset_2025-10-06_143022.sql
```

Replace `username` with your MySQL username and use the correct backup filename.

### Method 3: Using PHP Script
Create a restore script or use the following command in your terminal:

```bash
php -r "
\$conn = new mysqli('localhost', 'root', '', 'timetable_system');
\$sql = file_get_contents('backup_before_reset_2025-10-06_143022.sql');
\$conn->multi_query(\$sql);
"
```

## Important Notes

- **Keep backups safe**: These files contain all your database data
- **Test restores**: Occasionally test that backups can be restored successfully
- **Storage space**: Large databases create large backup files - monitor disk space
- **Security**: Backup files may contain sensitive data - protect access to this directory

## Automatic Cleanup

Consider implementing a cleanup policy to remove old backups:
- Keep daily backups for 7 days
- Keep weekly backups for 1 month
- Keep monthly backups for 1 year

## Backup Contents

Each backup includes:
- All table structures (CREATE TABLE statements)
- All data (INSERT statements)
- Proper foreign key handling (SET FOREIGN_KEY_CHECKS)
- Complete restore capability

## File Size Reference

Typical backup sizes:
- Empty database: ~50 KB (structure only)
- Small dataset: 100-500 KB
- Medium dataset: 500 KB - 5 MB
- Large dataset: 5 MB+

## Troubleshooting

### Backup too large?
Consider compressing backups:
```bash
gzip backup_before_reset_2025-10-06_143022.sql
```

### Restore fails?
- Check MySQL version compatibility
- Verify file encoding (should be UTF-8)
- Ensure sufficient privileges
- Check for syntax errors in the backup file

## Support

For issues with backups or restores, check:
1. MySQL error logs
2. PHP error logs
3. File permissions on backup directory
4. Available disk space

