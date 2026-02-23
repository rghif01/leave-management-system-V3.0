<?php
/**
 * APM - Database Backup
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);

$backupDir = BASE_PATH . '/backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);

$message = '';
$error   = '';

if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    if (!validateCsrf($_GET['token'] ?? '')) { $error = 'Invalid token.'; goto display; }
    
    $filename = 'apm_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    try {
        $pdo = getDB();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $sql = "-- APM Leave Management System Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . DB_NAME . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Drop + create
            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createStmt['Create Table'] . ";\n\n";
            
            // Data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
            if (!empty($rows)) {
                $sql .= "INSERT INTO `$table` VALUES\n";
                $inserts = [];
                foreach ($rows as $row) {
                    $escaped = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote($v);
                    }, $row);
                    $inserts[] = '(' . implode(',', $escaped) . ')';
                }
                $sql .= implode(",\n", $inserts) . ";\n\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($filepath, $sql);
        
        // Clean old backups
        $retention = (int)(getSetting('backup_retention_days') ?? 30);
        foreach (glob($backupDir . '*.sql') as $f) {
            if ((time() - filemtime($f)) > $retention * 86400) unlink($f);
        }
        
        logActivity('BACKUP', "Database backup created: $filename");
        $message = "Backup created: <strong>$filename</strong> (" . round(filesize($filepath)/1024, 1) . " KB)";
        
    } catch (Exception $e) {
        $error = 'Backup failed: ' . $e->getMessage();
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = $backupDir . $file;
    if (file_exists($path) && preg_match('/^apm_backup_[\d_-]+\.sql$/', $file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = $backupDir . $file;
    if (file_exists($path) && preg_match('/^apm_backup_[\d_-]+\.sql$/', $file)) {
        unlink($path);
        $message = "Backup deleted.";
    }
}

display:
// List backups
$backups = [];
foreach (glob($backupDir . '*.sql') as $f) {
    $backups[] = [
        'name' => basename($f),
        'size' => round(filesize($f)/1024, 1),
        'date' => date('d M Y H:i', filemtime($f)),
    ];
}
usort($backups, fn($a,$b) => strcmp($b['name'], $a['name']));

$pageTitle = 'Database Backup';
$activePage = 'settings';
include BASE_PATH . '/includes/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-cloud-download text-primary me-2"></i>Database Backup</h4>

<?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center py-4">
                <div class="fs-1 text-primary mb-2"><i class="bi bi-cloud-download"></i></div>
                <h5 class="fw-bold">Create Backup</h5>
                <p class="text-muted small">Generate a complete SQL backup of all data</p>
                <a href="?action=backup&token=<?= e(generateCsrfToken()) ?>" class="btn btn-primary" 
                   onclick="return confirm('Start database backup?')">
                    <i class="bi bi-play-fill me-1"></i>Start Backup
                </a>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="fw-bold"><i class="bi bi-info-circle text-info me-2"></i>Cron Job Setup</h6>
                <p class="text-muted small">For automatic daily backups, add this to Hostinger cron (2:00 AM):</p>
                <code class="small d-block bg-light p-2 rounded">0 2 * * * php <?= BASE_PATH ?>/admin/cron_backup.php</code>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold">Backup Files (<?= count($backups) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($backups)): ?>
                <div class="text-center py-5 text-muted">No backups yet</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Filename</th><th>Date</th><th>Size</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($backups as $b): ?>
                            <tr>
                                <td><code class="small"><?= e($b['name']) ?></code></td>
                                <td class="small"><?= $b['date'] ?></td>
                                <td><span class="badge bg-light text-dark"><?= $b['size'] ?> KB</span></td>
                                <td>
                                    <a href="?action=download&file=<?= urlencode($b['name']) ?>" class="btn btn-xs btn-outline-success py-0 px-2"><i class="bi bi-download"></i></a>
                                    <a href="?action=delete&file=<?= urlencode($b['name']) ?>" class="btn btn-xs btn-outline-danger py-0 px-2" data-confirm="Delete this backup?"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
