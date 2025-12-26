<?php
/**
 * Test Script - Logging Functionality
 *
 * Tests that logs are being written correctly
 * Run: php cron/test-logging.php
 */

define('CONSTITUANT_APP', true);

require_once __DIR__ . '/../lib/fetcher-base.php';

echo "Testing Logging Functionality" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;

// Get log file path
$logFile = __DIR__ . '/../' . IMPORT_SETTINGS['log_file'];
echo "Log file path: $logFile" . PHP_EOL;

// Check if logs directory exists
$logDir = dirname($logFile);
echo "Log directory: $logDir" . PHP_EOL;

if (is_dir($logDir)) {
    echo "✓ Log directory exists" . PHP_EOL;
} else {
    echo "✗ Log directory does NOT exist" . PHP_EOL;
}

// Check if directory is writable
if (is_writable($logDir)) {
    echo "✓ Log directory is writable" . PHP_EOL;
} else {
    echo "✗ Log directory is NOT writable" . PHP_EOL;
    echo "  Run: chmod 755 $logDir" . PHP_EOL;
}

echo PHP_EOL;

// Test writing logs
echo "Writing test log messages..." . PHP_EOL;

logMessage("Test INFO message");
logMessage("Test WARNING message", 'WARNING');
logMessage("Test ERROR message", 'ERROR');

echo PHP_EOL;

// Check if log file was created
if (file_exists($logFile)) {
    echo "✓ Log file created: $logFile" . PHP_EOL;

    // Show last 10 lines
    echo PHP_EOL . "Last 10 lines of log file:" . PHP_EOL;
    echo str_repeat('-', 80) . PHP_EOL;
    $lines = file($logFile);
    $lastLines = array_slice($lines, -10);
    echo implode('', $lastLines);
    echo str_repeat('-', 80) . PHP_EOL;
} else {
    echo "✗ Log file was NOT created" . PHP_EOL;
    echo "  Check permissions and path" . PHP_EOL;
}

echo PHP_EOL;
echo "Test complete!" . PHP_EOL;
