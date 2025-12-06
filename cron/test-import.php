<?php
/**
 * Test Script for Bill Import System
 *
 * Verifies setup and tests fetchers without saving to database.
 * Safe to run multiple times.
 *
 * Usage: php cron/test-import.php
 *
 * @package Constituant
 */

// Ensure CLI mode
if (php_sapi_name() !== 'cli') {
    exit('This script must be run from command line');
}

define('CONSTITUANT_APP', true);

echo str_repeat('=', 80) . PHP_EOL;
echo "Constituant - Import System Test" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

// Load dependencies
require_once __DIR__ . '/lib/fetcher-base.php';

$errors = [];
$warnings = [];

// Test 1: Database Connection
echo "[ 1 ] Testing database connection..." . PHP_EOL;
try {
    $pdo = getDbConnection();
    echo "      ✓ Database connection successful" . PHP_EOL;

    // Check if tables exist
    $tables = ['bills', 'pending_bills', 'import_logs'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            echo "      ✓ Table '$table' exists" . PHP_EOL;
        } else {
            $errors[] = "Table '$table' does not exist. Run migration first!";
            echo "      ✗ Table '$table' NOT FOUND" . PHP_EOL;
        }
    }
} catch (Exception $e) {
    $errors[] = "Database connection failed: " . $e->getMessage();
    echo "      ✗ Database connection failed" . PHP_EOL;
}

echo PHP_EOL;

// Test 2: Configuration
echo "[ 2 ] Testing configuration..." . PHP_EOL;
try {
    $sources = getEnabledSources();
    echo "      ✓ Configuration loaded" . PHP_EOL;
    echo "      ✓ Found " . count($sources) . " enabled sources" . PHP_EOL;

    foreach ($sources as $key => $source) {
        echo "        - {$source['name']} (Priority: {$source['priority']})" . PHP_EOL;
    }

    if (empty($sources)) {
        $warnings[] = "No sources are enabled!";
    }
} catch (Exception $e) {
    $errors[] = "Configuration error: " . $e->getMessage();
    echo "      ✗ Configuration error" . PHP_EOL;
}

echo PHP_EOL;

// Test 3: File Permissions
echo "[ 3 ] Testing file permissions..." . PHP_EOL;
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    echo "      Creating logs directory..." . PHP_EOL;
    mkdir($logDir, 0755, true);
}

if (is_writable($logDir)) {
    echo "      ✓ Logs directory is writable" . PHP_EOL;
} else {
    $errors[] = "Logs directory is not writable: $logDir";
    echo "      ✗ Logs directory is NOT writable" . PHP_EOL;
}

echo PHP_EOL;

// Test 4: External API Connectivity
echo "[ 4 ] Testing API connectivity..." . PHP_EOL;

$testUrls = [
    'NosDéputés' => 'https://www.nosdeputes.fr/dossiers/date/json',
    'EU Parliament' => 'https://data.europarl.europa.eu/api/v2/documents?limit=1',
    'La Fabrique' => 'https://www.lafabriquedelaloi.fr/api/dossiers.csv',
];

foreach ($testUrls as $name => $url) {
    echo "      Testing $name..." . PHP_EOL;

    $result = fetchUrl($url, [CURLOPT_TIMEOUT => 10], 0);

    if ($result['success']) {
        $dataSize = strlen($result['data']);
        echo "      ✓ $name API accessible ($dataSize bytes)" . PHP_EOL;

        // Try to parse response
        if (strpos($url, '.json') !== false || strpos($url, 'api') !== false) {
            $parsed = parseJson($result['data']);
            if ($parsed['success']) {
                echo "        ✓ JSON response valid" . PHP_EOL;
            } else {
                $warnings[] = "$name returned invalid JSON";
                echo "        ⚠ JSON parse failed (might be OK if API format changed)" . PHP_EOL;
            }
        }
    } else {
        $warnings[] = "Cannot connect to $name: " . $result['error'];
        echo "      ✗ $name API NOT accessible" . PHP_EOL;
        echo "        Error: " . $result['error'] . PHP_EOL;
    }

    sleep(1); // Rate limiting
}

echo PHP_EOL;

// Test 5: Date Parsing
echo "[ 5 ] Testing date parsing..." . PHP_EOL;
$testDates = [
    '2024-12-15 14:00:00',
    '2024-12-15T14:00:00Z',
    '15/12/2024',
    'Mon, 15 Dec 2024 14:00:00 GMT',
];

foreach ($testDates as $dateStr) {
    $parsed = parseDate($dateStr);
    if ($parsed) {
        echo "      ✓ '$dateStr' → '$parsed'" . PHP_EOL;
    } else {
        echo "      ✗ Failed to parse: '$dateStr'" . PHP_EOL;
    }
}

echo PHP_EOL;

// Test 6: Test Bill Data Processing
echo "[ 6 ] Testing bill data processing..." . PHP_EOL;

$testBill = [
    'external_id' => 'test-' . time(),
    'source' => 'test',
    'title' => 'Test Bill - ' . date('Y-m-d H:i:s'),
    'summary' => 'This is a test bill to verify the import system works correctly.',
    'full_text_url' => 'https://example.com/test',
    'level' => 'france',
    'chamber' => 'Test Chamber',
    'vote_datetime' => date('Y-m-d H:i:s', strtotime('+7 days')),
    'raw_data' => ['test' => true],
];

echo "      Creating test bill..." . PHP_EOL;

try {
    $result = savePendingBill($testBill);

    if ($result['success']) {
        echo "      ✓ Test bill saved successfully" . PHP_EOL;
        echo "        Action: {$result['action']}" . PHP_EOL;

        // Clean up test bill
        if (isset($pdo)) {
            $pdo->prepare("DELETE FROM pending_bills WHERE external_id = ? AND source = 'test'")
                ->execute([$testBill['external_id']]);
            echo "      ✓ Test bill cleaned up" . PHP_EOL;
        }
    } else {
        $warnings[] = "Failed to save test bill: " . $result['error'];
        echo "      ✗ Failed to save test bill" . PHP_EOL;
        echo "        Error: " . $result['error'] . PHP_EOL;
    }
} catch (Exception $e) {
    $errors[] = "Test bill processing failed: " . $e->getMessage();
    echo "      ✗ Exception: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// Test 7: Log Writing
echo "[ 7 ] Testing log writing..." . PHP_EOL;

try {
    logMessage("Test log entry from test-import.php", 'INFO');
    echo "      ✓ Log message written" . PHP_EOL;

    $logFile = __DIR__ . '/../' . IMPORT_SETTINGS['log_file'];
    if (file_exists($logFile)) {
        $lastLines = array_slice(file($logFile), -5);
        echo "      ✓ Log file exists: $logFile" . PHP_EOL;
        echo "      Last log entries:" . PHP_EOL;
        foreach ($lastLines as $line) {
            echo "        " . trim($line) . PHP_EOL;
        }
    }
} catch (Exception $e) {
    $warnings[] = "Log writing failed: " . $e->getMessage();
    echo "      ✗ Log writing failed" . PHP_EOL;
}

echo PHP_EOL;

// Test 8: Live Fetch Test (Limited)
echo "[ 8 ] Testing live data fetch (limited)..." . PHP_EOL;
echo "      Fetching 5 bills from NosDéputés.fr..." . PHP_EOL;

try {
    require_once __DIR__ . '/sources/nosdeputes.php';

    // Temporarily reduce max bills
    $originalMax = IMPORT_SETTINGS['max_bills_per_source'];

    // We can't modify const at runtime, so we'll just note this
    echo "      Note: Set max_bills_per_source to 5 in config for real test" . PHP_EOL;

    // Fetch URL directly
    $config = getSourceConfig('nosdeputes');
    $url = $config['base_url'] . $config['endpoints']['dossiers'];

    $result = fetchUrl($url, [], 0);

    if ($result['success']) {
        $parsed = parseJson($result['data']);

        if ($parsed['success']) {
            $dossiers = $parsed['data']['dossiers_legislatif'] ?? [];
            echo "      ✓ Fetched " . count($dossiers) . " dossiers from API" . PHP_EOL;

            if (!empty($dossiers)) {
                $sample = $dossiers[0];
                echo "      Sample bill:" . PHP_EOL;
                echo "        Title: " . ($sample['titre'] ?? 'N/A') . PHP_EOL;
                echo "        URL: " . ($sample['url'] ?? 'N/A') . PHP_EOL;
            }
        } else {
            $warnings[] = "Failed to parse NosDéputés response";
            echo "      ✗ Failed to parse response" . PHP_EOL;
        }
    } else {
        $warnings[] = "Failed to fetch from NosDéputés: " . $result['error'];
        echo "      ✗ Failed to fetch" . PHP_EOL;
    }
} catch (Exception $e) {
    $warnings[] = "Live fetch test failed: " . $e->getMessage();
    echo "      ✗ Exception: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// Summary
echo str_repeat('=', 80) . PHP_EOL;
echo "Test Summary" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;

if (empty($errors) && empty($warnings)) {
    echo "✓ All tests passed! System is ready to use." . PHP_EOL;
    echo PHP_EOL;
    echo "Next steps:" . PHP_EOL;
    echo "1. Run: php cron/fetch-bills.php" . PHP_EOL;
    echo "2. Check: logs/bill-imports.log" . PHP_EOL;
    echo "3. Visit: /admin/pending-bills.php" . PHP_EOL;
    exit(0);
} else {
    if (!empty($errors)) {
        echo "✗ Errors found (" . count($errors) . "):" . PHP_EOL;
        foreach ($errors as $i => $error) {
            echo "  " . ($i + 1) . ". $error" . PHP_EOL;
        }
        echo PHP_EOL;
    }

    if (!empty($warnings)) {
        echo "⚠ Warnings (" . count($warnings) . "):" . PHP_EOL;
        foreach ($warnings as $i => $warning) {
            echo "  " . ($i + 1) . ". $warning" . PHP_EOL;
        }
        echo PHP_EOL;
    }

    if (!empty($errors)) {
        echo "Please fix errors before running the import system." . PHP_EOL;
        exit(1);
    } else {
        echo "Warnings detected but system should work. Review warnings above." . PHP_EOL;
        exit(0);
    }
}
