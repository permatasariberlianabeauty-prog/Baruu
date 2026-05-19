<?php
/**
 * NOXARA Cron - Credit Pending Mining Profits
 * Schedule: Every 15 minutes
 * Command: php /path/to/cron/daily_profit.php --secret=CRON_SECRET
 */
define('CRON_RUN', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/bootstrap.php';

// Verify secret key (CLI or HTTP)
$secret = $argv[1] ?? $_GET['secret'] ?? '';
$secret = str_replace('--secret=', '', $secret);

if ($secret !== CRON_SECRET) {
    http_response_code(403);
    die('Unauthorized');
}

$startTime = microtime(true);
$processed = 0;
$errors    = 0;
$log       = [];

try {
    // Find all pending mining logs ready to be credited
    $pendingLogs = db()->fetchAll(
        "SELECT ml.id FROM mining_logs ml
         WHERE ml.status = 'pending'
         AND ml.credited_at <= NOW()
         ORDER BY ml.credited_at ASC
         LIMIT 500"
    );

    foreach ($pendingLogs as $log_row) {
        $result = creditMiningProfit($log_row['id']);
        if ($result['success']) {
            $processed++;
        } else {
            $errors++;
            $log[] = 'Failed ID ' . $log_row['id'] . ': ' . $result['message'];
        }
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    $message  = "Processed: $processed, Errors: $errors";

    // Log to cron_logs
    db()->execute(
        'INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?, ?, ?, ?)',
        'sssi',
        'daily_profit',
        $errors === 0 ? 'success' : 'error',
        $message . (empty($log) ? '' : ' | ' . implode('; ', array_slice($log, 0, 5))),
        $duration
    );

    echo "✅ daily_profit: $message ({$duration}ms)\n";

} catch (Throwable $e) {
    $duration = round((microtime(true) - $startTime) * 1000);
    db()->execute(
        'INSERT INTO cron_logs (job, status, message, duration_ms) VALUES (?, ?, ?, ?)',
        'sssi', 'daily_profit', 'error', $e->getMessage(), $duration
    );
    echo "❌ daily_profit ERROR: " . $e->getMessage() . "\n";
    error_log('Cron daily_profit error: ' . $e->getMessage());
}
