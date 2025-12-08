<?php
/**
 * La Fabrique de la Loi Bill Fetcher
 *
 * Fetches legislative bills from La Fabrique de la Loi CSV API
 * Website: https://www.lafabriquedelaloi.fr
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';

/**
 * Fetch bills from La Fabrique de la Loi
 *
 * @return array Import statistics
 */
function fetchLaFabrique(): array
{
    $startTime = microtime(true);
    $source = 'lafabrique';

    logMessage("Starting La Fabrique de la Loi import...");

    $config = getSourceConfig($source);
    if (!$config || !$config['enabled']) {
        logMessage("La Fabrique de la Loi source is disabled", 'WARNING');
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
        // Fetch CSV data
        $csvUrl = $config['base_url'] . $config['endpoints']['dossiers'];
        logMessage("Fetching CSV from: $csvUrl");

        $result = fetchUrl($csvUrl, [
            CURLOPT_HTTPHEADER => [
                'Accept: text/csv',
            ],
        ], 0);

        if (!$result['success']) {
            throw new Exception($result['error']);
        }

        // Parse CSV
        $dossiers = parseCsvData($result['data']);

        if (empty($dossiers)) {
            throw new Exception("No dossiers parsed from CSV");
        }

        logMessage("Found " . count($dossiers) . " dossiers in CSV");

        $maxBills = IMPORT_SETTINGS['max_bills_per_source'];
        $processed = 0;

        foreach ($dossiers as $dossier) {
            if ($processed >= $maxBills) {
                logMessage("Reached max bills limit ($maxBills), stopping");
                break;
            }

            $stats['fetched']++;

            // Extract bill data
            $billData = extractLaFabriqueBillData($dossier, $source);

            if (!$billData) {
                $stats['skipped']++;
                continue;
            }

            // Save to pending_bills
            $saveResult = savePendingBill($billData);

            if ($saveResult['success']) {
                if ($saveResult['action'] === 'inserted') {
                    $stats['new']++;
                    logMessage("New bill: {$billData['title']}", 'INFO');
                } elseif ($saveResult['action'] === 'updated') {
                    $stats['updated']++;
                    logMessage("Updated bill: {$billData['title']}", 'INFO');
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['errors'][] = $saveResult['error'];
                logMessage("Error saving bill: " . $saveResult['error'], 'WARNING');
            }

            $processed++;

            // Rate limiting
            sleep($config['rate_limit']['delay_seconds']);
        }

        $executionTime = microtime(true) - $startTime;
        $status = empty($stats['errors']) ? 'success' : 'partial';

        logImport($source, $status, $stats, $executionTime);
        logMessage("La Fabrique import completed in " . round($executionTime, 2) . "s");
        logMessage("Stats: {$stats['new']} new, {$stats['updated']} updated, {$stats['skipped']} skipped");

        return array_merge($stats, ['status' => $status]);

    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        $stats['errors'][] = $e->getMessage();

        logImport($source, 'failed', $stats, $executionTime);
        logMessage("La Fabrique import failed: " . $e->getMessage(), 'ERROR');

        return array_merge($stats, ['status' => 'failed']);
    }
}

/**
 * Parse CSV data into array of dossiers
 *
 * @param string $csvData Raw CSV data
 * @return array Array of dossiers
 */
function parseCsvData(string $csvData): array
{
    $lines = str_getcsv($csvData, "\n", '"', '\\');

    if (empty($lines)) {
        return [];
    }

    // First line is header
    $header = str_getcsv(array_shift($lines), ';', '"', '\\');

    // DEBUG: Log actual CSV headers
    logMessage("CSV Headers found: " . implode(', ', $header), 'INFO');
    logMessage("Total columns: " . count($header), 'INFO');

    $dossiers = [];

    foreach ($lines as $line) {
        if (empty(trim($line))) {
            continue;
        }

        $values = str_getcsv($line, ';', '"', '\\');

        if (count($values) !== count($header)) {
            logMessage("CSV line has incorrect column count (expected " . count($header) . ", got " . count($values) . "), skipping", 'WARNING');
            continue;
        }

        $dossier = array_combine($header, $values);
        $dossiers[] = $dossier;
    }

    // DEBUG: Log first dossier structure if available
    if (!empty($dossiers)) {
        logMessage("Sample dossier keys: " . implode(', ', array_keys($dossiers[0])), 'INFO');
        logMessage("Sample dossier values (first 3): " . json_encode(array_slice($dossiers[0], 0, 3)), 'INFO');
    }

    return $dossiers;
}

/**
 * Extract bill data from La Fabrique dossier
 *
 * @param array $dossier Dossier data from CSV
 * @param string $source Source name
 * @return array|null Bill data or null if invalid
 */
function extractLaFabriqueBillData(array $dossier, string $source): ?array
{
    // La Fabrique CSV structure (columns may vary):
    // - id or dossier_id
    // - titre or title or long_title
    // - url
    // - date_depot or date
    // - statut or status
    // - assemblee or chamber

    // Get title - CSV uses 'Titre' with capital T!
    $titre = $dossier['Titre'] ?? $dossier['short_title'] ?? null;

    if (empty($titre)) {
        logMessage("Skipping dossier without title", 'WARNING');
        return null;
    }

    // Get ID
    $externalId = $dossier['id'] ?? md5($titre);

    // Get summary - use short_title or Thèmes as fallback
    $summary = $dossier['short_title'] ?? null;
    if (empty($summary) && !empty($dossier['Thèmes'])) {
        $summary = "Thèmes: " . $dossier['Thèmes'];
    }

    // Get URL - CSV column is 'URL du dossier'
    $url = $dossier['URL du dossier'] ?? null;

    // Parse date - CSV uses 'Date initiale'
    $voteDate = null;
    $dateField = $dossier['Date initiale'] ?? $dossier['Date de promulgation'] ?? null;
    if ($dateField) {
        $voteDate = parseDate($dateField);
    }

    // Get chamber - determine from URL or assemblee_id
    $chamber = 'Assemblée Nationale';
    if (!empty($url) && stripos($url, 'senat.fr') !== false) {
        $chamber = 'Sénat';
    } elseif (!empty($url) && stripos($url, 'assemblee-nationale.fr') !== false) {
        $chamber = 'Assemblée Nationale';
    }

    // Check status - CSV uses 'État du dossier'
    $status = $dossier['État du dossier'] ?? '';
    if (stripos($status, 'adopté') !== false ||
        stripos($status, 'rejeté') !== false ||
        stripos($status, 'promulgué') !== false) {
        logMessage("Skipping completed bill: $titre", 'INFO');
        return null;
    }

    // Skip if promulgation date exists (already enacted)
    if (!empty($dossier['Date de promulgation']) && !empty($dossier['Numéro de la loi'])) {
        logMessage("Skipping promulgated law: $titre", 'INFO');
        return null;
    }

    return [
        'external_id' => (string)$externalId,
        'source' => $source,
        'title' => cleanText($titre, 500),
        'summary' => cleanText($summary, 5000),
        'full_text_url' => $url,
        'level' => 'france',
        'chamber' => $chamber,
        'vote_datetime' => $voteDate,
        'raw_data' => $dossier,
    ];
}

// If run directly (not included), execute fetcher
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $result = fetchLaFabrique();
    exit($result['status'] === 'success' ? 0 : 1);
}
