<?php
/**
 * Test Script - NosDéputés API Inspector
 *
 * Tests the NosDéputés.fr API endpoints and shows the response structure
 * Run: php cron/test-nosdeputes-api.php
 */

define('CONSTITUANT_APP', true);

require_once __DIR__ . '/lib/fetcher-base.php';

echo str_repeat('=', 80) . PHP_EOL;
echo "NosDéputés.fr - API Inspector" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

$config = getSourceConfig('nosdeputes');

// Test 1: Dossiers endpoint
echo "TEST 1: Dossiers Endpoint" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;

$dossiersUrl = $config['base_url'] . $config['endpoints']['dossiers'];
echo "URL: $dossiersUrl" . PHP_EOL;

$result = fetchUrl($dossiersUrl, [], 0);

if (!$result['success']) {
    echo "✗ Failed: {$result['error']}" . PHP_EOL . PHP_EOL;
} else {
    echo "✓ Success (" . strlen($result['data']) . " bytes)" . PHP_EOL;

    $parsed = parseJson($result['data']);
    if ($parsed['success']) {
        $data = $parsed['data'];
        echo "JSON parsed successfully" . PHP_EOL;
        echo "Top-level keys: " . implode(', ', array_keys($data)) . PHP_EOL;

        // Check for dossiers_legislatif
        if (isset($data['dossiers_legislatif'])) {
            echo "✓ Found 'dossiers_legislatif' key with " . count($data['dossiers_legislatif']) . " items" . PHP_EOL;

            if (!empty($data['dossiers_legislatif'])) {
                echo PHP_EOL . "Sample dossier structure:" . PHP_EOL;
                $sample = $data['dossiers_legislatif'][0];
                foreach ($sample as $key => $value) {
                    $display = is_array($value) ? '[array]' : (strlen((string)$value) > 100 ? substr((string)$value, 0, 100) . '...' : $value);
                    echo "  $key: $display" . PHP_EOL;
                }
            }
        } else {
            echo "✗ 'dossiers_legislatif' key NOT FOUND" . PHP_EOL;
            echo "Available keys: " . implode(', ', array_keys($data)) . PHP_EOL;

            // Show first 500 chars of response
            echo PHP_EOL . "Response preview:" . PHP_EOL;
            echo substr($result['data'], 0, 500) . "..." . PHP_EOL;
        }
    } else {
        echo "✗ JSON parse failed: {$parsed['error']}" . PHP_EOL;
        echo "Response preview: " . substr($result['data'], 0, 300) . PHP_EOL;
    }
}

echo PHP_EOL;

// Test 2: Scrutins endpoint
echo "TEST 2: Scrutins Endpoint" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;

$scrutinsUrl = $config['base_url'] . $config['endpoints']['scrutins'];
echo "URL: $scrutinsUrl" . PHP_EOL;

$result = fetchUrl($scrutinsUrl, [], 0);

if (!$result['success']) {
    echo "✗ Failed: {$result['error']}" . PHP_EOL . PHP_EOL;
} else {
    echo "✓ Success (" . strlen($result['data']) . " bytes)" . PHP_EOL;

    $parsed = parseJson($result['data']);
    if ($parsed['success']) {
        $data = $parsed['data'];
        echo "JSON parsed successfully" . PHP_EOL;
        echo "Top-level keys: " . implode(', ', array_keys($data)) . PHP_EOL;

        // Check for scrutins
        if (isset($data['scrutins'])) {
            echo "✓ Found 'scrutins' key with " . count($data['scrutins']) . " items" . PHP_EOL;

            if (!empty($data['scrutins'])) {
                echo PHP_EOL . "Sample scrutin structure:" . PHP_EOL;
                $sample = $data['scrutins'][0];
                foreach ($sample as $key => $value) {
                    $display = is_array($value) ? '[array]' : (strlen((string)$value) > 100 ? substr((string)$value, 0, 100) . '...' : $value);
                    echo "  $key: $display" . PHP_EOL;
                }
            }
        } else {
            echo "✗ 'scrutins' key NOT FOUND" . PHP_EOL;
            echo "Available keys: " . implode(', ', array_keys($data)) . PHP_EOL;
        }
    } else {
        echo "✗ JSON parse failed: {$parsed['error']}" . PHP_EOL;
    }
}

echo PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;
echo "RECOMMENDATIONS:" . PHP_EOL;
echo "1. Check if the API structure has changed" . PHP_EOL;
echo "2. Verify the correct JSON keys for dossiers and scrutins" . PHP_EOL;
echo "3. Update extractNosDePutesBillData() with correct field names" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;
