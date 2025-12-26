<?php
/**
 * Test Script - La Fabrique CSV Structure Inspector
 *
 * Fetches the CSV and displays the actual column structure
 * Run: php cron/test-lafabrique-csv.php
 */

define('CONSTITUANT_APP', true);

require_once __DIR__ . '/lib/fetcher-base.php';

echo str_repeat('=', 80) . PHP_EOL;
echo "La Fabrique de la Loi - CSV Structure Inspector" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

$csvUrl = 'https://www.lafabriquedelaloi.fr/api/dossiers.csv';

echo "Fetching CSV from: $csvUrl" . PHP_EOL;

$result = fetchUrl($csvUrl, [
    CURLOPT_HTTPHEADER => [
        'Accept: text/csv',
    ],
], 0);

if (!$result['success']) {
    echo "✗ Failed to fetch CSV: {$result['error']}" . PHP_EOL;
    exit(1);
}

echo "✓ CSV fetched successfully (" . strlen($result['data']) . " bytes)" . PHP_EOL . PHP_EOL;

// Parse CSV
$lines = str_getcsv($result['data'], "\n", '"', '\\');

if (empty($lines)) {
    echo "✗ No lines found in CSV" . PHP_EOL;
    exit(1);
}

// Get header
$header = str_getcsv($lines[0], ';', '"', '\\');

echo "CSV STRUCTURE:" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
echo "Total lines: " . count($lines) . PHP_EOL;
echo "Total columns: " . count($header) . PHP_EOL . PHP_EOL;

echo "COLUMN NAMES:" . PHP_EOL;
foreach ($header as $index => $columnName) {
    echo sprintf("  [%2d] %s", $index, $columnName) . PHP_EOL;
}

echo PHP_EOL;

// Show first 3 data rows
echo "SAMPLE DATA (first 3 rows):" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;

for ($i = 1; $i <= min(3, count($lines) - 1); $i++) {
    $values = str_getcsv($lines[$i], ';', '"', '\\');

    if (count($values) === count($header)) {
        $row = array_combine($header, $values);

        echo PHP_EOL . "Row $i:" . PHP_EOL;
        foreach ($row as $key => $value) {
            // Truncate long values
            $displayValue = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
            echo sprintf("  %-25s : %s", $key, $displayValue) . PHP_EOL;
        }
    } else {
        echo "Row $i: Column count mismatch!" . PHP_EOL;
    }
}

echo PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;
echo "Analysis complete!" . PHP_EOL;
echo PHP_EOL;

echo "RECOMMENDATIONS:" . PHP_EOL;
echo "1. Look for title-related columns above (titre, title, intitule, etc.)" . PHP_EOL;
echo "2. Check date columns for vote_datetime mapping" . PHP_EOL;
echo "3. Verify ID column naming" . PHP_EOL;
echo "4. Update extractLaFabriqueBillData() with correct column names" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;
