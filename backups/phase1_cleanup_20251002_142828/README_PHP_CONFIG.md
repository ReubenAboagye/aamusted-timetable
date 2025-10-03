# PHP Configuration Management Scripts for Timetable System
# Usage Guide and Instructions

Write-Host "=== PHP Configuration Management Scripts ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "This package contains three PowerShell scripts to manage your PHP configuration:" -ForegroundColor White
Write-Host ""
Write-Host "1. check_php_config.ps1  - Check current PHP configuration" -ForegroundColor Yellow
Write-Host "2. fix_php_config.ps1     - Automatically fix configuration issues" -ForegroundColor Yellow  
Write-Host "3. test_php_config.ps1   - Quick test of PHP functionality" -ForegroundColor Yellow
Write-Host ""

Write-Host "=== QUICK START ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Step 1: Check your current configuration" -ForegroundColor White
Write-Host "  .\check_php_config.ps1" -ForegroundColor Green
Write-Host ""
Write-Host "Step 2: Fix any issues found" -ForegroundColor White
Write-Host "  .\fix_php_config.ps1" -ForegroundColor Green
Write-Host ""
Write-Host "Step 3: Restart Apache in XAMPP Control Panel" -ForegroundColor White
Write-Host ""
Write-Host "Step 4: Verify the fix worked" -ForegroundColor White
Write-Host "  .\test_php_config.ps1" -ForegroundColor Green
Write-Host ""

Write-Host "=== DETAILED USAGE ===" -ForegroundColor Cyan
Write-Host ""

Write-Host "CHECK SCRIPT (check_php_config.ps1):" -ForegroundColor Yellow
Write-Host "  Purpose: Analyzes your current PHP configuration" -ForegroundColor White
Write-Host "  Usage:" -ForegroundColor White
Write-Host "    .\check_php_config.ps1" -ForegroundColor Green
Write-Host "    .\check_php_config.ps1 -PhpIniPath 'C:\xampp\php\php.ini'" -ForegroundColor Green
Write-Host "  What it checks:" -ForegroundColor White
Write-Host "    - Required PHP extensions (mysqli, json, fileinfo, openssl, mbstring)" -ForegroundColor Gray
Write-Host "    - Recommended extensions (gd, zip, xml, etc.)" -ForegroundColor Gray
Write-Host "    - Critical settings (memory_limit, max_execution_time, etc.)" -ForegroundColor Gray
Write-Host "    - PHP version compatibility" -ForegroundColor Gray
Write-Host ""

Write-Host "FIX SCRIPT (fix_php_config.ps1):" -ForegroundColor Yellow
Write-Host "  Purpose: Automatically modifies php.ini to fix configuration issues" -ForegroundColor White
Write-Host "  Usage:" -ForegroundColor White
Write-Host "    .\fix_php_config.ps1" -ForegroundColor Green
Write-Host "    .\fix_php_config.ps1 -PhpIniPath 'C:\xampp\php\php.ini'" -ForegroundColor Green
Write-Host "    .\fix_php_config.ps1 -Backup `$false" -ForegroundColor Green
Write-Host "    .\fix_php_config.ps1 -Force" -ForegroundColor Green
Write-Host "  Parameters:" -ForegroundColor White
Write-Host "    -PhpIniPath: Specify custom php.ini location" -ForegroundColor Gray
Write-Host "    -Backup: Create backup before modifying (default: true)" -ForegroundColor Gray
Write-Host "    -Force: Continue even if backup fails" -ForegroundColor Gray
Write-Host "  What it fixes:" -ForegroundColor White
Write-Host "    - Enables required PHP extensions" -ForegroundColor Gray
Write-Host "    - Sets memory_limit to 1024M" -ForegroundColor Gray
Write-Host "    - Sets max_execution_time to 1800 seconds" -ForegroundColor Gray
Write-Host "    - Configures file upload settings" -ForegroundColor Gray
Write-Host "    - Enables error logging and OPcache" -ForegroundColor Gray
Write-Host ""

Write-Host "TEST SCRIPT (test_php_config.ps1):" -ForegroundColor Yellow
Write-Host "  Purpose: Quick verification that PHP is working correctly" -ForegroundColor White
Write-Host "  Usage:" -ForegroundColor White
Write-Host "    .\test_php_config.ps1" -ForegroundColor Green
Write-Host "  What it tests:" -ForegroundColor White
Write-Host "    - PHP availability and version" -ForegroundColor Gray
Write-Host "    - Required extensions are loaded" -ForegroundColor Gray
Write-Host "    - Critical settings are configured" -ForegroundColor Gray
Write-Host ""

Write-Host "=== REQUIREMENTS FOR TIMETABLE SYSTEM ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Your timetable system requires these PHP configurations:" -ForegroundColor White
Write-Host ""
Write-Host "Required Extensions:" -ForegroundColor Yellow
Write-Host "  - mysqli: Database connectivity" -ForegroundColor White
Write-Host "  - json: API responses and data processing" -ForegroundColor White
Write-Host "  - fileinfo: File type detection" -ForegroundColor White
Write-Host "  - openssl: Secure connections" -ForegroundColor White
Write-Host "  - mbstring: Multibyte string support" -ForegroundColor White
Write-Host ""
Write-Host "Recommended Extensions:" -ForegroundColor Yellow
Write-Host "  - gd: PDF generation and image processing" -ForegroundColor White
Write-Host "  - zip: Excel file processing" -ForegroundColor White
Write-Host "  - xml/simplexml/dom: XML data processing" -ForegroundColor White
Write-Host "  - amqp: RabbitMQ message queuing (optional)" -ForegroundColor White
Write-Host ""
Write-Host "Critical Settings:" -ForegroundColor Yellow
Write-Host "  - memory_limit = 1024M (for genetic algorithm)" -ForegroundColor White
Write-Host "  - max_execution_time = 1800 (30 minutes for generation)" -ForegroundColor White
Write-Host "  - post_max_size = 50M (large form submissions)" -ForegroundColor White
Write-Host "  - upload_max_filesize = 20M (Excel/CSV uploads)" -ForegroundColor White
Write-Host ""

Write-Host "=== TROUBLESHOOTING ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Common Issues:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. 'PHP not found in PATH'" -ForegroundColor Red
Write-Host "   Solution: Add PHP to your system PATH or specify -PhpIniPath" -ForegroundColor White
Write-Host ""
Write-Host "2. 'No write permission to php.ini'" -ForegroundColor Red
Write-Host "   Solution: Run PowerShell as Administrator" -ForegroundColor White
Write-Host ""
Write-Host "3. 'PHP syntax error in php.ini'" -ForegroundColor Red
Write-Host "   Solution: Script will restore from backup automatically" -ForegroundColor White
Write-Host ""
Write-Host "4. Extensions still not loading after fix" -ForegroundColor Red
Write-Host "   Solution: Restart Apache in XAMPP Control Panel" -ForegroundColor White
Write-Host ""
Write-Host "5. XAMPP not found" -ForegroundColor Red
Write-Host "   Solution: Install XAMPP or specify custom PHP path" -ForegroundColor White
Write-Host ""

Write-Host "=== MANUAL CONFIGURATION ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "If you prefer to configure manually:" -ForegroundColor White
Write-Host "1. Open C:\xampp\php\php.ini in a text editor" -ForegroundColor Gray
Write-Host "2. Uncomment these lines (remove semicolon):" -ForegroundColor Gray
Write-Host "   extension=mysqli" -ForegroundColor Gray
Write-Host "   extension=json" -ForegroundColor Gray
Write-Host "   extension=fileinfo" -ForegroundColor Gray
Write-Host "   extension=openssl" -ForegroundColor Gray
Write-Host "   extension=mbstring" -ForegroundColor Gray
Write-Host "   extension=gd" -ForegroundColor Gray
Write-Host "   extension=zip" -ForegroundColor Gray
Write-Host "3. Set these values:" -ForegroundColor Gray
Write-Host "   memory_limit = 1024M" -ForegroundColor Gray
Write-Host "   max_execution_time = 1800" -ForegroundColor Gray
Write-Host "   post_max_size = 50M" -ForegroundColor Gray
Write-Host "   upload_max_filesize = 20M" -ForegroundColor Gray
Write-Host "4. Save and restart Apache" -ForegroundColor Gray
Write-Host ""

Write-Host "=== SUPPORT ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "For issues with the timetable system:" -ForegroundColor White
Write-Host "1. Run the check script to identify problems" -ForegroundColor Gray
Write-Host "2. Use the fix script to resolve configuration issues" -ForegroundColor Gray
Write-Host "3. Test with the test script to verify everything works" -ForegroundColor Gray
Write-Host "4. Check XAMPP error logs if problems persist" -ForegroundColor Gray
Write-Host ""

Write-Host "Scripts created for AAMUSTED Timetable System" -ForegroundColor Green
Write-Host "Generated at $(Get-Date)" -ForegroundColor Gray

