<?php
/**
 * Main Bill Import Cron Script
 *
 * Orchestrates fetching bills from all enabled sources.
 * Run via cron: php /path/to/cron/fetch-bills.php
 *
 * Example cron entry (every 6 hours):
 * 0 *\/6 * * * /usr/local/php8.1/bin/php /home/user/constituant/cron/fetch-bills.php >> /home/user/logs/fetch-bills.log 2>&1
 *
 * @package Constituant
 */

// Ensure we're running in CLI mode
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from command line');
}

// Define app constant
define('CONSTITUANT_APP', true);

// Start time tracking
$scriptStartTime = microtime(true);

// Load dependencies
require_once __DIR__ . '/lib/fetcher-base.php';

// Load source fetchers
require_once __DIR__ . '/sources/nosdeputes.php';
require_once __DIR__ . '/sources/eu-parliament.php';
require_once __DIR__ . '/sources/lafabrique.php';

// Output header
echo str_repeat('=', 80) . PHP_EOL;
echo "Constituant - Automatic Bill Import" . PHP_EOL;
echo "Started: " . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

logMessage("===== Starting bill import process =====");

// Overall statistics
$overallStats = [
    'sources_run' => 0,
    'sources_success' => 0,
    'sources_failed' => 0,
    'total_new' => 0,
    'total_updated' => 0,
    'total_errors' => 0,
];

try {
    // Auto-update bill status first (mark passed bills as completed)
    if (IMPORT_SETTINGS['auto_update_status']) {
        logMessage("Auto-updating bill statuses...");
        $updated = autoUpdateBillStatus();
        if ($updated > 0) {
            logMessage("Marked $updated bills as completed");
        }
    }

    // Get enabled sources sorted by priority
    $sources = getEnabledSources();

    if (empty($sources)) {
        logMessage("No enabled sources found", 'WARNING');
        echo "Warning: No enabled sources configured" . PHP_EOL;
        exit(0);
    }

    logMessage("Found " . count($sources) . " enabled sources");
    echo "Enabled sources: " . count($sources) . PHP_EOL . PHP_EOL;

    // Fetch from each source
    foreach ($sources as $sourceKey => $sourceConfig) {
        $overallStats['sources_run']++;

        echo str_repeat('-', 80) . PHP_EOL;
        echo "Source: {$sourceConfig['name']} (Priority: {$sourceConfig['priority']})" . PHP_EOL;
        echo str_repeat('-', 80) . PHP_EOL;

        logMessage("--- Processing source: {$sourceConfig['name']} ---");

        try {
            // Call appropriate fetcher function
            $result = callSourceFetcher($sourceKey);

            if ($result['status'] === 'success' || $result['status'] === 'partial') {
                $overallStats['sources_success']++;
                $overallStats['total_new'] += $result['new'] ?? 0;
                $overallStats['total_updated'] += $result['updated'] ?? 0;

                echo "✓ Status: " . strtoupper($result['status']) . PHP_EOL;
                echo "  New: {$result['new']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}" . PHP_EOL;

                if (!empty($result['errors'])) {
                    echo "  Errors: " . count($result['errors']) . PHP_EOL;
                    $overallStats['total_errors'] += count($result['errors']);
                }
            } else {
                $overallStats['sources_failed']++;
                echo "✗ Status: FAILED" . PHP_EOL;

                if (!empty($result['errors'])) {
                    echo "  Errors: " . implode(', ', array_slice($result['errors'], 0, 3)) . PHP_EOL;
                    $overallStats['total_errors'] += count($result['errors']);
                }
            }

        } catch (Exception $e) {
            $overallStats['sources_failed']++;
            logMessage("Source {$sourceKey} failed: " . $e->getMessage(), 'ERROR');
            echo "✗ Exception: " . $e->getMessage() . PHP_EOL;
        }

        echo PHP_EOL;

        // Delay between sources to be respectful
        if ($sourceKey !== array_key_last($sources)) {
            sleep(2);
        }
    }

    // Summary
    echo str_repeat('=', 80) . PHP_EOL;
    echo "Import Summary" . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo "Sources Run: {$overallStats['sources_run']}" . PHP_EOL;
    echo "Success: {$overallStats['sources_success']}" . PHP_EOL;
    echo "Failed: {$overallStats['sources_failed']}" . PHP_EOL;
    echo "Total New Bills: {$overallStats['total_new']}" . PHP_EOL;
    echo "Total Updated: {$overallStats['total_updated']}" . PHP_EOL;
    echo "Total Errors: {$overallStats['total_errors']}" . PHP_EOL;

    $executionTime = microtime(true) - $scriptStartTime;
    echo "Execution Time: " . round($executionTime, 2) . "s" . PHP_EOL;
    echo "Completed: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;

    logMessage("===== Import process completed =====");
    logMessage("Summary: {$overallStats['total_new']} new, {$overallStats['total_updated']} updated, {$overallStats['total_errors']} errors");

    // Send notification email if configured and there are new bills
    if (IMPORT_SETTINGS['notify_admin'] && $overallStats['total_new'] > 0) {
        sendAdminNotification($overallStats);
    }

    // Exit with appropriate code
    exit($overallStats['sources_failed'] > 0 ? 1 : 0);

} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'ERROR');
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

/**
 * Call the appropriate fetcher function for a source
 *
 * @param string $sourceKey Source identifier
 * @return array Import result
 */
function callSourceFetcher(string $sourceKey): array
{
    switch ($sourceKey) {
        case 'nosdeputes':
            return fetchNosDePutes();

        case 'eu-parliament':
            return fetchEUParliament();

        case 'lafabrique':
            return fetchLaFabrique();

        case 'epdb':
            // Not implemented yet
            logMessage("EPDB fetcher not implemented", 'WARNING');
            return ['status' => 'skipped', 'new' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        default:
            logMessage("Unknown source: $sourceKey", 'WARNING');
            return ['status' => 'skipped', 'new' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }
}

/**
 * Send email notification to admin about new bills
 *
 * @param array $stats Import statistics
 * @return void
 */
function sendAdminNotification(array $stats): void
{
    try {
        $to = IMPORT_SETTINGS['admin_email'];
        $subject = "Constituant: {$stats['total_new']} nouveaux projets de loi";

        $message = "Bonjour,\n\n";
        $message .= "L'import automatique a trouvé {$stats['total_new']} nouveaux projets de loi.\n\n";
        $message .= "Résumé:\n";
        $message .= "- Nouveaux: {$stats['total_new']}\n";
        $message .= "- Mis à jour: {$stats['total_updated']}\n";
        $message .= "- Erreurs: {$stats['total_errors']}\n\n";
        $message .= "Veuillez vous connecter à l'interface admin pour les examiner:\n";
        $message .= "https://constituant.fr/admin/pending-bills.php\n\n";
        $message .= "Cordialement,\n";
        $message .= "Constituant\n";

        $headers = [
            'From: noreply@constituant.fr',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = mail($to, $subject, $message, implode("\r\n", $headers));

        if ($sent) {
            logMessage("Notification email sent to $to");
        } else {
            logMessage("Failed to send notification email", 'WARNING');
        }

    } catch (Exception $e) {
        logMessage("Error sending notification: " . $e->getMessage(), 'WARNING');
    }
}
