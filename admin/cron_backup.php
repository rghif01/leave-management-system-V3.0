<?php
/**
 * APM - Cron Backup Script
 * Run: 0 2 * * * php /path/to/APM/admin/cron_backup.php
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/functions.php';

// Only run from CLI
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_X_CRON'])) {
    http_response_code(403);
    die('CLI only');
}

$backupDir = BASE_PATH . '/backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);

$filename = 'apm_backup_' . date('Y-m-d_H-i-s') . '.sql';
$filepath = $backupDir . $filename;

try {
    $pdo = getDB();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $sql = "-- APM Cron Backup\n-- " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createStmt['Create Table'] . ";\n\n";
        
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if (!empty($rows)) {
            $sql .= "INSERT INTO `$table` VALUES\n";
            $inserts = [];
            foreach ($rows as $row) {
                $escaped = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                $inserts[] = '(' . implode(',', $escaped) . ')';
            }
            $sql .= implode(",\n", $inserts) . ";\n\n";
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($filepath, $sql);
    
    // Cleanup old
    $retention = (int)(getSetting('backup_retention_days') ?? 30);
    foreach (glob($backupDir . '*.sql') as $f) {
        if ((time() - filemtime($f)) > $retention * 86400) unlink($f);
    }
    
    logActivity('CRON_BACKUP', "Automated backup: $filename", 0);
    echo "Backup OK: $filename\n";
    
} catch (Exception $e) {
    echo "Backup FAILED: " . $e->getMessage() . "\n";
    error_log('APM Backup Error: ' . $e->getMessage());
}
