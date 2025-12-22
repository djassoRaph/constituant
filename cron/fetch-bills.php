<?php
/**
 * Automated Bill Import - Main Cron Script
 *
 * Fully automated: Fetch → AI Classify → Publish
 * No human approval needed
 *
 * Usage: php /path/to/fetch-bills.php
 *
 * Cron example (every 6 hours):
 * 0 *\/6 * * * /usr/bin/php /path/to/fetch-bills.php >> /path/to/logs/cron.log 2>&1
 *
 * @package Constituant
 */

// Ensure CLI mode only
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
require_once __DIR__ . '/sources/lafabrique.php';

// Output header
echo str_repeat('=', 80) . PHP_EOL;
echo "Constituant - Automated Bill Import" . PHP_EOL;
echo "Started: " . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

logMessage("===== Starting automated import process =====");

// Statistics
$overallStats = [
    'sources_run' => 0,
    'sources_success' => 0,
    'sources_failed' => 0,
    'total_new' => 0,
    'total_updated' => 0,
    'total_skipped' => 0,
    'total_errors' => 0,
];

try {
    // Update bill statuses first (mark past bills as completed)
    logMessage("Updating bill statuses...");
    updateBillStatuses();
    
    // Get enabled sources
    $sources = getEnabledSources();
    
    if (empty($sources)) {
        logMessage("No enabled sources found", 'WARNING');
        echo "⚠ Warning: No enabled sources configured" . PHP_EOL;
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
            // Call appropriate fetcher
            $result = callSourceFetcher($sourceKey);
            
            if ($result['status'] === 'success' || $result['status'] === 'partial') {
                $overallStats['sources_success']++;
                $overallStats['total_new'] += $result['new'] ?? 0;
                $overallStats['total_updated'] += $result['updated'] ?? 0;
                $overallStats['total_skipped'] += $result['skipped'] ?? 0;
                
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
    echo "Total Skipped: {$overallStats['total_skipped']}" . PHP_EOL;
    echo "Total Errors: {$overallStats['total_errors']}" . PHP_EOL;
    
    $executionTime = microtime(true) - $scriptStartTime;
    echo "Execution Time: " . round($executionTime, 2) . "s" . PHP_EOL;
    echo "Completed: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    
    logMessage("===== Import process completed =====");
    logMessage("Summary: {$overallStats['total_new']} new, {$overallStats['total_updated']} updated, {$overallStats['total_errors']} errors");
    
    // Exit with appropriate code
    exit($overallStats['sources_failed'] > 0 ? 1 : 0);
    
} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'ERROR');
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

/**
 * Call appropriate fetcher for source
 *
 * @param string $sourceKey Source identifier
 * @return array Import result
 */
function callSourceFetcher(string $sourceKey): array
{
    switch ($sourceKey) {
        case 'nosdeputes':
            return fetchNosDePutes();
            
        case 'lafabrique':
            return fetchLaFabrique();
            
        case 'eu-parliament':
            return fetchEUParliament();
            
        default:
            logMessage("Unknown source: $sourceKey", 'WARNING');
            return [
                'status' => 'skipped',
                'new' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => []
            ];
    }
}

/**
 * Update bill statuses based on vote_datetime
 *
 * @return int Number of bills updated
 */
function updateBillStatuses(): int
{
    try {
        $pdo = getDbConnection();
        
        // Call stored procedure if it exists
        try {
            $pdo->query("CALL update_bill_statuses()");
            logMessage("Bill statuses updated via stored procedure");
        } catch (PDOException $e) {
            // Stored procedure doesn't exist, do it manually
            $updated = 0;
            
            // Mark completed bills
            $stmt = $pdo->query("
                UPDATE bills
                SET status = 'completed'
                WHERE vote_datetime < NOW()
                AND status != 'completed'
            ");
            $updated += $stmt->rowCount();
            
            // Mark voting_now bills
            $stmt = $pdo->query("
                UPDATE bills
                SET status = 'voting_now'
                WHERE vote_datetime >= NOW()
                AND vote_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND status != 'voting_now'
                AND status != 'completed'
            ");
            $updated += $stmt->rowCount();
            
            // Mark upcoming bills
            $stmt = $pdo->query("
                UPDATE bills
                SET status = 'upcoming'
                WHERE vote_datetime > DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND status != 'upcoming'
            ");
            $updated += $stmt->rowCount();
            
            logMessage("Updated $updated bill statuses manually");
        }
        
        return 0;
        
    } catch (PDOException $e) {
        logMessage("Error updating bill statuses: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}
