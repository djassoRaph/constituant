<?php
/**
 * EU Parliament Bill Fetcher
 *
 * Fetches legislative procedures from European Parliament Open Data API
 * Documentation: https://data.europarl.europa.eu/en/developer-corner/opendata-api
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';

/**
 * Fetch bills from EU Parliament
 *
 * @return array Import statistics
 */
function fetchEUParliament(): array
{
    $startTime = microtime(true);
    $source = 'eu-parliament';

    logMessage("Starting EU Parliament import...");

    $config = getSourceConfig($source);
    if (!$config || !$config['enabled']) {
        logMessage("EU Parliament source is disabled", 'WARNING');
        return ['status' => 'skipped'];
    }

    $stats = [
        'fetched' => 0,
        'new' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    try {
        // Fetch from EU Parliament Open Data API
        // Using SPARQL endpoint for legislative procedures
        $documents = fetchEUDocuments($config);

        if (empty($documents)) {
            logMessage("No documents fetched from EU Parliament", 'WARNING');
            throw new Exception("No documents retrieved");
        }

        logMessage("Found " . count($documents) . " EU documents");

        $maxBills = IMPORT_SETTINGS['max_bills_per_source'];
        $processed = 0;

        foreach ($documents as $document) {
            if ($processed >= $maxBills) {
                logMessage("Reached max bills limit ($maxBills), stopping");
                break;
            }

            $stats['fetched']++;

            // Extract bill data
            $billData = extractEUBillData($document, $source);

            if (!$billData) {
                $stats['skipped']++;
                continue;
            }

            // Save to pending_bills
            $saveResult = savePendingBill($billData);

            if ($saveResult['success']) {
                if ($saveResult['action'] === 'inserted') {
                    $stats['new']++;
                    logMessage("New EU bill: {$billData['title']}", 'INFO');
                } elseif ($saveResult['action'] === 'updated') {
                    $stats['updated']++;
                    logMessage("Updated EU bill: {$billData['title']}", 'INFO');
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['errors'][] = $saveResult['error'];
                logMessage("Error saving bill: " . $saveResult['error'], 'WARNING');
            }

            $processed++;

            // Rate limiting
            if ($processed < count($documents)) {
                sleep($config['rate_limit']['delay_seconds']);
            }
        }

        $executionTime = microtime(true) - $startTime;
        $status = empty($stats['errors']) ? 'success' : 'partial';

        logImport($source, $status, $stats, $executionTime);
        logMessage("EU Parliament import completed in " . round($executionTime, 2) . "s");
        logMessage("Stats: {$stats['new']} new, {$stats['updated']} updated, {$stats['skipped']} skipped");

        return array_merge($stats, ['status' => $status]);

    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        $stats['errors'][] = $e->getMessage();

        logImport($source, 'failed', $stats, $executionTime);
        logMessage("EU Parliament import failed: " . $e->getMessage(), 'ERROR');

        return array_merge($stats, ['status' => 'failed']);
    }
}

/**
 * Fetch documents from EU Parliament API
 *
 * @param array $config Source configuration
 * @return array Documents
 */
function fetchEUDocuments(array $config): array
{
    // EU Parliament Open Data API endpoint for legislative procedures
    // Using simplified REST API approach
    $apiUrl = $config['base_url'] . '/api/v2/documents';

    // Build query parameters for recent legislative procedures
    $params = [
        'type' => 'LEGISLATIVE_PROCEDURE',
        'limit' => IMPORT_SETTINGS['max_bills_per_source'],
        'offset' => 0,
        'sort' => '-date', // Most recent first
        'format' => 'application/json',
    ];

    $url = $apiUrl . '?' . http_build_query($params);

    logMessage("Fetching from EU API: $url");

    $result = fetchUrl($url, [
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en',
        ],
    ], 0);

    if (!$result['success']) {
        logMessage("EU API request failed: " . $result['error'], 'ERROR');
        // Fallback to RSS feed if API fails
        return fetchEURssFeed($config);
    }

    $parsed = parseJson($result['data']);
    if (!$parsed['success']) {
        logMessage("EU API parse failed: " . $parsed['error'], 'ERROR');
        return fetchEURssFeed($config);
    }

    return $parsed['data']['data'] ?? $parsed['data']['items'] ?? [];
}

/**
 * Fallback: Fetch from EU Parliament RSS feed
 *
 * @param array $config Source configuration
 * @return array Documents
 */
function fetchEURssFeed(array $config): array
{
    logMessage("Falling back to EU Parliament RSS feed");

    // RSS feed for legislative procedures
    $rssUrl = $config['endpoints']['oeil_rss'] . '?type=legislative';

    $result = fetchUrl($rssUrl, [], 0);

    if (!$result['success']) {
        logMessage("RSS fetch failed: " . $result['error'], 'ERROR');
        return [];
    }

    // Parse XML
    try {
        $xml = simplexml_load_string($result['data']);

        if ($xml === false) {
            logMessage("RSS parse failed", 'ERROR');
            return [];
        }

        $documents = [];
        $maxItems = min(IMPORT_SETTINGS['max_bills_per_source'], 50);

        foreach ($xml->channel->item as $index => $item) {
            if ($index >= $maxItems) {
                break;
            }

            $documents[] = [
                'title' => (string)$item->title,
                'description' => (string)$item->description,
                'link' => (string)$item->link,
                'pubDate' => (string)$item->pubDate,
                'guid' => (string)$item->guid,
            ];
        }

        logMessage("Fetched " . count($documents) . " items from RSS");
        return $documents;

    } catch (Exception $e) {
        logMessage("RSS processing failed: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Extract bill data from EU Parliament document
 *
 * @param array $document Document data from API or RSS
 * @param string $source Source name
 * @return array|null Bill data or null if invalid
 */
function extractEUBillData(array $document, string $source): ?array
{
    // Handle both API and RSS formats
    $title = $document['title'] ?? $document['label'] ?? null;

    if (empty($title)) {
        logMessage("Skipping EU document without title", 'WARNING');
        return null;
    }

    // Extract ID
    $externalId = $document['id'] ?? $document['reference'] ?? $document['guid'] ?? md5($title);

    // Extract summary/description
    $summary = $document['description'] ?? $document['summary'] ?? null;
    if (empty($summary) && isset($document['abstract'])) {
        $summary = $document['abstract'];
    }

    // Get URL
    $url = $document['link'] ?? $document['url'] ?? null;
    if (!$url && isset($document['reference'])) {
        // Build EUR-Lex URL from reference
        $url = "https://eur-lex.europa.eu/legal-content/EN/ALL/?uri=CELEX:" . $document['reference'];
    }

    // Parse date
    $voteDate = null;
    $dateField = $document['date'] ?? $document['pubDate'] ?? $document['adoptionDate'] ?? null;
    if ($dateField) {
        $voteDate = parseDate($dateField);
    }

    // Determine chamber
    $chamber = 'European Parliament';
    if (isset($document['chamber']) || isset($document['body'])) {
        $chamberCode = $document['chamber'] ?? $document['body'];
        $chamber = mapEUChamber($chamberCode);
    }

    // Clean and validate
    $cleanTitle = cleanText($title, 500);
    $cleanSummary = cleanText($summary, 5000);

    // Some EU documents have very technical titles - make them more readable
    $cleanTitle = makeEUTitleReadable($cleanTitle);

    return [
        'external_id' => (string)$externalId,
        'source' => $source,
        'title' => $cleanTitle,
        'summary' => $cleanSummary,
        'full_text_url' => $url,
        'level' => 'eu',
        'chamber' => $chamber,
        'vote_datetime' => $voteDate,
        'raw_data' => $document,
    ];
}

/**
 * Map EU chamber codes to readable names
 *
 * @param string $code Chamber code
 * @return string Readable chamber name
 */
function mapEUChamber(string $code): string
{
    $chambers = [
        'EP' => 'European Parliament',
        'COUNCIL' => 'Council of the European Union',
        'COMMISSION' => 'European Commission',
        'PARL' => 'European Parliament',
    ];

    return $chambers[strtoupper($code)] ?? 'European Parliament';
}

/**
 * Make EU technical titles more readable
 *
 * @param string $title Technical title
 * @return string Readable title
 */
function makeEUTitleReadable(string $title): string
{
    // Remove excessive reference numbers
    $title = preg_replace('/\b\d{4}\/\d{4}\(COD\)\b/', '', $title);
    $title = preg_replace('/\bCOM\(\d{4}\)\s*\d+\b/', '', $title);

    // Clean up whitespace
    $title = preg_replace('/\s+/', ' ', trim($title));

    return $title;
}

// If run directly (not included), execute fetcher
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $result = fetchEUParliament();
    exit($result['status'] === 'success' ? 0 : 1);
}
