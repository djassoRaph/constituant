<?php
/**
 * Test Script - EU Parliament API Inspector
 *
 * Tests the EU Parliament API endpoints and diagnoses 406 errors
 * Run: php cron/test-eu-parliament-api.php
 */

define('CONSTITUANT_APP', true);

require_once __DIR__ . '/lib/fetcher-base.php';

echo str_repeat('=', 80) . PHP_EOL;
echo "EU Parliament - API Inspector" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

$config = getSourceConfig('eu-parliament');

// Test 1: API v2 Documents endpoint
echo "TEST 1: API v2 Documents Endpoint" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;

$apiUrl = $config['base_url'] . '/api/v2/documents';
$params = [
    'type' => 'LEGISLATIVE_PROCEDURE',
    'limit' => 5,
    'offset' => 0,
    'sort' => '-date',
    'format' => 'application/json',
];

$url = $apiUrl . '?' . http_build_query($params);
echo "URL: $url" . PHP_EOL;

$headers = [
    'Accept: application/json',
    'Accept-Language: en',
];
echo "Headers: " . implode(', ', $headers) . PHP_EOL . PHP_EOL;

$result = fetchUrl($url, [
    CURLOPT_HTTPHEADER => $headers,
], 0);

if (!$result['success']) {
    echo "✗ Failed: {$result['error']}" . PHP_EOL;

    // Try different Accept headers
    echo PHP_EOL . "Trying alternative headers..." . PHP_EOL;

    $alternativeHeaders = [
        ['Accept: application/ld+json'],
        ['Accept: application/sparql-results+json'],
        ['Accept: application/rdf+xml'],
        ['Accept: */*'],
    ];

    foreach ($alternativeHeaders as $altHeader) {
        echo "Testing with: " . implode(', ', $altHeader) . PHP_EOL;

        $altResult = fetchUrl($apiUrl . '?limit=1', [
            CURLOPT_HTTPHEADER => $altHeader,
        ], 0);

        if ($altResult['success']) {
            echo "✓ SUCCESS with these headers!" . PHP_EOL;
            echo "Response length: " . strlen($altResult['data']) . " bytes" . PHP_EOL;
            echo "Response preview: " . substr($altResult['data'], 0, 200) . "..." . PHP_EOL;
            break;
        } else {
            echo "  Still failed: {$altResult['error']}" . PHP_EOL;
        }
    }
} else {
    echo "✓ Success (" . strlen($result['data']) . " bytes)" . PHP_EOL;

    $parsed = parseJson($result['data']);
    if ($parsed['success']) {
        $data = $parsed['data'];
        echo "JSON parsed successfully" . PHP_EOL;
        echo "Top-level keys: " . implode(', ', array_keys($data)) . PHP_EOL;

        // Show structure
        if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
            echo "✓ Found 'data' array with " . count($data['data']) . " items" . PHP_EOL;

            echo PHP_EOL . "Sample document structure:" . PHP_EOL;
            $sample = $data['data'][0];
            foreach ($sample as $key => $value) {
                $display = is_array($value) ? '[array]' : (strlen((string)$value) > 100 ? substr((string)$value, 0, 100) . '...' : $value);
                echo "  $key: $display" . PHP_EOL;
            }
        } elseif (isset($data['items']) && is_array($data['items'])) {
            echo "✓ Found 'items' array with " . count($data['items']) . " items" . PHP_EOL;
        } else {
            echo "Available keys: " . implode(', ', array_keys($data)) . PHP_EOL;
        }
    } else {
        echo "✗ JSON parse failed: {$parsed['error']}" . PHP_EOL;
        echo "Response preview: " . substr($result['data'], 0, 300) . PHP_EOL;
    }
}

echo PHP_EOL;

// Test 2: RSS Feed fallback
echo "TEST 2: OEIL RSS Feed" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;

$rssUrl = $config['endpoints']['oeil_rss'] . '?type=legislative';
echo "URL: $rssUrl" . PHP_EOL;

$result = fetchUrl($rssUrl, [], 0);

if (!$result['success']) {
    echo "✗ Failed: {$result['error']}" . PHP_EOL;
} else {
    echo "✓ Success (" . strlen($result['data']) . " bytes)" . PHP_EOL;

    // Try to parse XML
    try {
        $xml = simplexml_load_string($result['data']);

        if ($xml === false) {
            echo "✗ XML parse failed" . PHP_EOL;
        } else {
            echo "✓ XML parsed successfully" . PHP_EOL;

            if (isset($xml->channel->item)) {
                $itemCount = count($xml->channel->item);
                echo "Found $itemCount RSS items" . PHP_EOL;

                if ($itemCount > 0) {
                    echo PHP_EOL . "Sample RSS item:" . PHP_EOL;
                    $item = $xml->channel->item[0];
                    echo "  Title: " . (string)$item->title . PHP_EOL;
                    echo "  Link: " . (string)$item->link . PHP_EOL;
                    echo "  PubDate: " . (string)$item->pubDate . PHP_EOL;
                    echo "  Description: " . substr((string)$item->description, 0, 100) . "..." . PHP_EOL;
                }
            } else {
                echo "No items found in RSS feed" . PHP_EOL;
            }
        }
    } catch (Exception $e) {
        echo "✗ XML processing failed: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL;

// Test 3: Alternative EP API endpoint
echo "TEST 3: Alternative EP Search API" . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;

$searchUrl = 'https://www.europarl.europa.eu/plenary/en/ajax/getSessionCalendar.html';
echo "URL: $searchUrl" . PHP_EOL;

$result = fetchUrl($searchUrl, [], 0);

if (!$result['success']) {
    echo "✗ Failed: {$result['error']}" . PHP_EOL;
} else {
    echo "✓ Success (alternate endpoint works)" . PHP_EOL;
    echo "Response length: " . strlen($result['data']) . " bytes" . PHP_EOL;
}

echo PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;
echo "RECOMMENDATIONS:" . PHP_EOL;
echo "1. If API returns 406: Use alternative Accept headers or RSS feed" . PHP_EOL;
echo "2. Update EU Parliament fetcher with working endpoint" . PHP_EOL;
echo "3. Consider using OEIL RSS as primary source" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;
