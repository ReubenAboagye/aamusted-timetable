<?php
// List all backup files in this directory
$backupFiles = glob(__DIR__ . '/backup_before_reset_*.sql');

// Sort by modification time (newest first)
usort($backupFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup Archives</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800020;
            --hover-maroon: #600010;
            --dark-maroon: #4a0013;
            --brand-green: #198754;
            --brand-gold: #FFD700;
            --text-muted: #6c757d;
            --border-light: #e9ecef;
            --bg-light: #f8f9fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--brand-green) 0%, #15803d 100%);
            color: white;
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.1;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        
        .header-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .header-icon i {
            font-size: 2.5em;
            color: white;
        }
        
        .header h1 {
            font-size: 2.25em;
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .backup-list {
            margin-top: 20px;
        }
        
        .backup-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s;
        }
        
        .backup-item:hover {
            border-color: #16a34a;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);
        }
        
        .backup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .backup-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1.1em;
        }
        
        .backup-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            color: #64748b;
            font-size: 0.9em;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--brand-green) 0%, #15803d 100%);
            color: white;
            border: 1px solid var(--brand-green);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #15803d 0%, #14532d 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(22, 163, 74, 0.3);
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
            border: 1px solid #64748b;
        }
        
        .btn-secondary:hover {
            background: #475569;
            border-color: #475569;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state h2 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        
        .back-link a {
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .back-link a:hover {
            color: var(--hover-maroon);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <h1>Database Backup Archives</h1>
                <p>View and download backup files created before database resets</p>
            </div>
        </div>
        
        <div class="content">
            <?php if (empty($backupFiles)): ?>
                <div class="empty-state">
                    <h2>No Backups Found</h2>
                    <p>Backups will appear here when you reset the database with backup enabled.</p>
                </div>
            <?php else: ?>
                <p style="color: #64748b; margin-bottom: 20px;">
                    Found <?php echo count($backupFiles); ?> backup file(s)
                </p>
                
                <div class="backup-list">
                    <?php foreach ($backupFiles as $file): 
                        $filename = basename($file);
                        $size = filesize($file);
                        $modified = filemtime($file);
                        $sizeFormatted = $size < 1024 ? $size . ' B' : 
                                        ($size < 1048576 ? round($size/1024, 2) . ' KB' : 
                                        round($size/1048576, 2) . ' MB');
                    ?>
                        <div class="backup-item">
                            <div class="backup-header">
                                <div class="backup-name"><i class="fas fa-file-archive"></i> <?php echo htmlspecialchars($filename); ?></div>
                                <a href="<?php echo htmlspecialchars($filename); ?>" download class="btn btn-primary">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                            <div class="backup-info">
                                <div class="info-item">
                                    <strong><i class="fas fa-hdd"></i> Size:</strong> <?php echo $sizeFormatted; ?>
                                </div>
                                <div class="info-item">
                                    <strong><i class="fas fa-calendar"></i> Created:</strong> <?php echo date('Y-m-d H:i:s', $modified); ?>
                                </div>
                                <div class="info-item">
                                    <strong><i class="fas fa-clock"></i> Age:</strong> <?php 
                                        $age = time() - $modified;
                                        if ($age < 3600) {
                                            echo round($age/60) . ' minutes ago';
                                        } elseif ($age < 86400) {
                                            echo round($age/3600) . ' hours ago';
                                        } else {
                                            echo round($age/86400) . ' days ago';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fef9c3 100%); border: 2px solid #fbbf24; border-left: 5px solid #f59e0b; border-radius: 8px; padding: 20px; margin-top: 20px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);">
                    <strong style="color: #b45309; display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <i class="fas fa-lightbulb"></i> Restore Instructions:
                    </strong>
                    <span style="color: #92400e; display: block; line-height: 1.6;">
                        To restore a backup, use phpMyAdmin or the MySQL command line. 
                        See <a href="README.md" style="color: #15803d; font-weight: 600;"><i class="fas fa-book"></i> README.md</a> for detailed instructions.
                    </span>
                </div>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="../../reset_database.php"><i class="fas fa-arrow-left"></i> Back to Reset Database</a> | 
                <a href="../../index.php"><i class="fas fa-home"></i> Home</a>
            </div>
        </div>
    </div>
</body>
</html>

