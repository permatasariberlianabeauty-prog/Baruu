<?php
/**
 * NOXARA Cron - Database Backup
 * Schedule: Daily at 02:00
 * Command: php /path/to/cron/backup.php --secret=CRON_SECRET
 */
define('CRON_RUN', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/bootstrap.php';

$secret = str_replace('--secret=', '', $argv[1] ?? $_GET['secret'] ?? '');
if ($secret !== CRON_SECRET) { http_response_code(403); die('Unauthorized'); }

$startTime = microtime(true);

try {
    $backupDir = ROOT_PATH . '/logs/backups';
    if (!is_dir($backupDir)) { mkdir($backupDir, 0750, true); }

    $filename  = 'backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
    $filepath  = $backupDir . '/' . $filename;

    // Build mysqldump command
    $cmd = sprintf(
        'mysqldump --host=%s --port=%s --user=%s --password=%s %s --single-transaction --routines --triggers 2>&1 | gzip > %s',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_PORT),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0 || !file_exists($filepath)) {
        // Fallback: PHP-based backup of key tables
        $tables = ['users','user_wallets','deposits','withdrawals','transactions','user_products','mining_logs','commissions'];
        $sql    = "-- NOXARA PHP Backup " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $rows = db()->fetchAll("SELECT * FROM `$table` LIMIT 10000");
            if (empty($rows)) continue;

            $cols = array_keys($rows[0]);
            $sql .= "-- Table: $table\n";
            foreach ($rows as $row) {
                $values = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", $row);
                $sql   .= "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }

        file_put_contents($filepath . '.php_backup.sql', $sql);
        $filename .= '.php_backup.sql';
        $filepath  = $backupDir . '/' . $filename;
    }

    // Clean old backups (keep last 7 days)
    $oldBackups = glob($backupDir . '/backup_*.sql*');
    if ($oldBackups) {
        usort($oldBackups, fn($a, $b) => filemtime($a) - filemtime($b));
        $toDelete = array_slice($oldBackups, 0, max(0, count($oldBackups) - 7));
        foreach ($toDelete as $old) { @unlink($old); }
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    $size     = file_exists($filepath) ? round(filesize($filepath) / 1024, 1) . 'KB' : 'N/A';
    $message  = "Backup: $filename ($size)";

    db()->execute('INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?,?,?,?)', 'sssi', 'backup', 'success', $message, $duration);
    echo "✅ backup: $message ({$duration}ms)\n";

} catch (Throwable $e) {
    $duration = round((microtime(true) - $startTime) * 1000);
    db()->execute('INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?,?,?,?)', 'sssi', 'backup', 'error', $e->getMessage(), $duration);
    echo "❌ backup ERROR: " . $e->getMessage() . "\n";
    error_log('Cron backup error: ' . $e->getMessage());
}
