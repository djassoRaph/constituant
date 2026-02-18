<?php
/**
 * DILA Fetcher - Official French Legislative Data
 *
 * Fetches published laws from DILA (Direction de l'Information Légale et Administrative)
 * via data.gouv.fr CSV files. This is the official data source used by the Assemblée
 * Nationale's LexImpact team.
 *
 * Process:
 * 1. Download/cache CSV from data.gouv.fr
 * 2. Parse CSV with UTF-8 encoding
 * 3. Filter recent laws (configurable lookback)
 * 4. Classify with Mistral AI
 * 5. Save to production database
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';
require_once __DIR__ . '/../config/sources-dila.php';

/**
 * Main entry point: Fetch and process laws from DILA
 *
 * @return array Import statistics
 */
function fetchDILA(): array
{
    $startTime = microtime(true);
    $source = DILA_CONFIG['source_name'];

    logMessage("===== Starting DILA import =====");
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "DILA / Légifrance - Official Legislative Data Import\n";
    echo str_repeat('=', 70) . "\n\n";

    $stats = [
        'fetched' => 0,
        'new' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    try {
        // Step 1: Download/cache CSV
        logMessage("Step 1: Downloading CSV from data.gouv.fr...");
        echo "Step 1: Downloading CSV...\n";

        $csvData = downloadOrGetCachedCSV('laws');

        if ($csvData === null) {
            throw new Exception("Failed to download CSV and no valid cache available");
        }

        echo "  CSV data loaded (" . number_format(strlen($csvData)) . " bytes)\n\n";

        // Step 2: Parse CSV
        logMessage("Step 2: Parsing CSV...");
        echo "Step 2: Parsing CSV...\n";

        $laws = parseCSV($csvData);

        if (empty($laws)) {
            throw new Exception("No valid laws found in CSV");
        }

        echo "  Parsed " . count($laws) . " total laws\n\n";

        // Step 3: Filter recent laws
        logMessage("Step 3: Filtering recent laws...");
        echo "Step 3: Filtering recent laws...\n";

        $filteredLaws = filterRecentLaws($laws);
        $stats['fetched'] = count($filteredLaws);

        echo "  Filtered to " . count($filteredLaws) . " laws from last " . DILA_IMPORT_CONFIG['days_lookback'] . " days\n\n";

        // Step 4: Process each law
        logMessage("Step 4: Processing " . count($filteredLaws) . " laws...");
        echo "Step 4: Processing laws...\n";
        echo str_repeat('-', 70) . "\n";

        $processed = 0;
        $maxPerRun = DILA_IMPORT_CONFIG['max_laws_per_run'];

        foreach ($filteredLaws as $law) {
            if ($processed >= $maxPerRun) {
                logMessage("Reached max laws limit ($maxPerRun), stopping");
                echo "\n  Reached limit of $maxPerRun laws per run\n";
                break;
            }

            $result = processLaw($law, $source);

            switch ($result['action']) {
                case 'inserted':
                    $stats['new']++;
                    echo "  [NEW] " . truncateTitle($law['titre']) . "\n";
                    break;
                case 'updated':
                    $stats['updated']++;
                    echo "  [UPD] " . truncateTitle($law['titre']) . "\n";
                    break;
                case 'skipped':
                    $stats['skipped']++;
                    break;
                case 'error':
                    $stats['errors'][] = $result['error'];
                    echo "  [ERR] " . truncateTitle($law['titre']) . ": " . $result['error'] . "\n";
                    break;
            }

            $processed++;

            // Rate limiting between AI calls
            if ($processed < count($filteredLaws) && $result['action'] !== 'skipped') {
                usleep(500000); // 0.5 second delay
            }
        }

        // Step 5: Cleanup old cache files
        cleanupOldCacheFiles();

        // Final statistics
        $executionTime = microtime(true) - $startTime;
        $status = empty($stats['errors']) ? 'success' : 'partial';

        logImportOperation($source, $status, $stats, $executionTime);

        echo "\n" . str_repeat('=', 70) . "\n";
        echo "DILA Import Complete\n";
        echo str_repeat('=', 70) . "\n";
        echo "  Status: " . strtoupper($status) . "\n";
        echo "  Fetched: {$stats['fetched']}\n";
        echo "  New: {$stats['new']}\n";
        echo "  Updated: {$stats['updated']}\n";
        echo "  Skipped: {$stats['skipped']}\n";
        echo "  Errors: " . count($stats['errors']) . "\n";
        echo "  Time: " . round($executionTime, 2) . "s\n";
        echo str_repeat('=', 70) . "\n\n";

        logMessage("DILA import completed: {$stats['new']} new, {$stats['updated']} updated, {$stats['skipped']} skipped");

        return array_merge($stats, ['status' => $status]);

    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        $stats['errors'][] = $e->getMessage();

        logMessage("DILA import failed: " . $e->getMessage(), 'ERROR');
        logImportOperation($source, 'failed', $stats, $executionTime);

        echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";

        return array_merge($stats, ['status' => 'failed']);
    }
}

// ============================================================================
// STEP 1: DOWNLOAD / CACHE CSV
// ============================================================================

/**
 * Download CSV or use cached version
 *
 * @param string $type CSV type (laws, measures)
 * @return string|null CSV content or null on failure
 */
function downloadOrGetCachedCSV(string $type): ?string
{
    $config = getDILAConfig();
    $cacheConfig = getDILACacheConfig();

    // Ensure cache directory exists
    ensureCacheDirectory();

    $todayCachePath = getDILACachePath($type, date('Y-m-d'));
    $yesterdayCachePath = getDILACachePath($type, date('Y-m-d', strtotime('-1 day')));

    // Check if today's cache is valid
    if (isDILACacheValid($todayCachePath)) {
        logMessage("Using cached CSV from today: $todayCachePath");
        echo "  Using cached data (< 24 hours old)\n";
        return file_get_contents($todayCachePath);
    }

    // Try to download fresh CSV
    $url = $config['csv_urls'][$type] ?? null;
    if (empty($url)) {
        logMessage("No URL configured for CSV type: $type", 'ERROR');
        return null;
    }

    logMessage("Downloading fresh CSV from: $url");
    echo "  Downloading from data.gouv.fr...\n";

    $result = fetchUrl($url, [
        CURLOPT_HTTPHEADER => [
            'Accept: text/csv',
            'Accept-Charset: UTF-8',
        ],
        CURLOPT_TIMEOUT => 60, // Longer timeout for large CSV
    ], 0);

    if ($result['success']) {
        // Ensure UTF-8 encoding
        $csvData = ensureUtf8($result['data']);

        // Save to cache
        if (file_put_contents($todayCachePath, $csvData) !== false) {
            logMessage("CSV cached to: $todayCachePath");
            echo "  Downloaded and cached successfully\n";
        }

        return $csvData;
    }

    // Download failed, try yesterday's cache as fallback
    logMessage("Download failed: " . $result['error'] . ". Trying yesterday's cache...", 'WARNING');
    echo "  Download failed, trying yesterday's cache...\n";

    if (file_exists($yesterdayCachePath)) {
        logMessage("Using fallback cache from yesterday: $yesterdayCachePath");
        echo "  Using yesterday's cached data\n";
        return file_get_contents($yesterdayCachePath);
    }

    // No cache available
    logMessage("No cached CSV available", 'ERROR');
    return null;
}

/**
 * Ensure cache directory exists with proper permissions
 */
function ensureCacheDirectory(): void
{
    $cacheDir = getDILACacheConfig()['directory'];

    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            throw new Exception("Failed to create cache directory: $cacheDir");
        }
        logMessage("Created cache directory: $cacheDir");
    }
}

/**
 * Ensure string is UTF-8 encoded
 *
 * @param string $data Input data
 * @return string UTF-8 encoded data
 */
function ensureUtf8(string $data): string
{
    // Detect encoding
    $encoding = mb_detect_encoding($data, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

    if ($encoding && $encoding !== 'UTF-8') {
        $data = mb_convert_encoding($data, 'UTF-8', $encoding);
        logMessage("Converted CSV encoding from $encoding to UTF-8");
    }

    // Remove BOM if present
    $bom = pack('H*', 'EFBBBF');
    $data = preg_replace("/^$bom/", '', $data);

    return $data;
}

// ============================================================================
// STEP 2: PARSE CSV
// ============================================================================

/**
 * Parse CSV data into array of laws
 *
 * @param string $csvData Raw CSV content
 * @return array Array of law records
 */
function parseCSV(string $csvData): array
{
    $columnMap = getDILAColumnMap();
    $requiredFields = DILA_REQUIRED_FIELDS;

    // Split into lines
    $lines = preg_split('/\r?\n/', $csvData);

    if (count($lines) < 2) {
        logMessage("CSV has no data rows", 'WARNING');
        return [];
    }

    // Parse header row
    $headerLine = array_shift($lines);
    $headers = parseCSVLine($headerLine);

    if (empty($headers)) {
        logMessage("Failed to parse CSV header", 'ERROR');
        return [];
    }

    logMessage("CSV columns: " . implode(', ', array_slice($headers, 0, 5)) . "...");

    // Map header indices to our field names
    $headerToIndex = array_flip($headers);
    $fieldMapping = [];

    foreach ($columnMap as $csvHeader => $fieldName) {
        if (isset($headerToIndex[$csvHeader])) {
            $fieldMapping[$fieldName] = $headerToIndex[$csvHeader];
        }
    }

    // Check required fields are present
    foreach ($requiredFields as $field) {
        if (!isset($fieldMapping[$field])) {
            // Try to find the field by partial match
            $found = false;
            foreach ($columnMap as $csvHeader => $mappedField) {
                if ($mappedField === $field && isset($headerToIndex[$csvHeader])) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                logMessage("Required field '$field' not found in CSV", 'WARNING');
            }
        }
    }

    // Parse data rows
    $laws = [];
    $skippedRows = 0;

    foreach ($lines as $lineNum => $line) {
        if (empty(trim($line))) {
            continue;
        }

        $values = parseCSVLine($line);

        if (empty($values)) {
            $skippedRows++;
            continue;
        }

        // Map values to fields
        $law = [];
        foreach ($fieldMapping as $fieldName => $index) {
            $law[$fieldName] = isset($values[$index]) ? trim($values[$index]) : null;
        }

        // Store unmapped values in raw array for metadata
        $law['_raw'] = array_combine($headers, array_pad($values, count($headers), ''));

        // Skip rows with missing required fields
        $valid = true;
        foreach ($requiredFields as $field) {
            if (empty($law[$field])) {
                $valid = false;
                break;
            }
        }

        if ($valid) {
            $laws[] = $law;
        } else {
            $skippedRows++;
        }
    }

    if ($skippedRows > 0) {
        logMessage("Skipped $skippedRows rows with missing required fields");
    }

    return $laws;
}

/**
 * Parse a single CSV line handling quoted fields
 *
 * @param string $line CSV line
 * @return array Parsed values
 */
function parseCSVLine(string $line): array
{
    // Try semicolon delimiter first (common in French CSVs)
    $values = str_getcsv($line, ';', '"', '\\');

    // If we only got one value, try comma delimiter
    if (count($values) <= 1) {
        $values = str_getcsv($line, ',', '"', '\\');
    }

    return $values;
}

// ============================================================================
// STEP 3: FILTER RECENT LAWS
// ============================================================================

/**
 * Filter laws to only recent ones and sort by date
 *
 * @param array $laws All parsed laws
 * @return array Filtered and sorted laws
 */
function filterRecentLaws(array $laws): array
{
    $cutoffDate = getDILACutoffDate();
    $importConfig = getDILAImportConfig();

    logMessage("Filtering laws published after: $cutoffDate");

    $filtered = [];

    foreach ($laws as $law) {
        // Parse publication date
        $pubDate = normalizeDateToDatabaseFormat($law['date_publication'] ?? null);

        if ($pubDate === null) {
            continue;
        }

        // Check if within lookback period
        if ($pubDate >= $cutoffDate) {
            $law['_normalized_date'] = $pubDate;
            $filtered[] = $law;
        }
    }

    // Sort by publication date (newest first)
    usort($filtered, function ($a, $b) {
        return strcmp($b['_normalized_date'], $a['_normalized_date']);
    });

    logMessage("Filtered from " . count($laws) . " to " . count($filtered) . " laws");

    return $filtered;
}

/**
 * Normalize date string to Y-m-d format
 *
 * @param string|null $dateStr Input date string
 * @return string|null Normalized date or null
 */
function normalizeDateToDatabaseFormat(?string $dateStr): ?string
{
    if (empty($dateStr)) {
        return null;
    }

    // Common French date formats
    $formats = [
        'Y-m-d',           // 2024-01-15
        'd/m/Y',           // 15/01/2024
        'd-m-Y',           // 15-01-2024
        'Y/m/d',           // 2024/01/15
        'd.m.Y',           // 15.01.2024
        'j F Y',           // 15 janvier 2024
        'd M Y',           // 15 Jan 2024
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }

    // Try strtotime as fallback
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}

// ============================================================================
// STEP 4: PROCESS EACH LAW
// ============================================================================

/**
 * Process a single law: check existence, classify, save
 *
 * @param array $law Law data
 * @param string $source Source identifier
 * @return array Result with action and optional error
 */
function processLaw(array $law, string $source): array
{
    try {
        $pdo = getDbConnection();
        $importConfig = getDILAImportConfig();

        // Generate bill ID
        $billId = generateBillId($source, $law['id_loi'], $law['titre']);

        // Check if bill already exists
        $stmt = $pdo->prepare("SELECT id, updated_at FROM bills WHERE id = ? OR (source = ? AND external_id = ?)");
        $stmt->execute([$billId, $source, $law['id_loi']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Check if recently updated (skip if updated within N days)
            $lastUpdate = new DateTime($existing['updated_at']);
            $daysSinceUpdate = (new DateTime())->diff($lastUpdate)->days;

            if ($daysSinceUpdate < $importConfig['skip_if_updated_within_days']) {
                return ['action' => 'skipped', 'reason' => 'recently_updated'];
            }
        }

        // Prepare bill data for production
        $billData = prepareBillData($law, $source, $billId);

        // Classify with AI (if enabled)
        if (defined('ENABLE_AI_CLASSIFICATION') && ENABLE_AI_CLASSIFICATION) {
            $aiResult = classifyBillWithRetry(
                $billData['title'],
                $billData['summary'] ?? '',
                ''
            );

            if ($aiResult['error'] === null) {
                $billData['theme'] = $aiResult['theme'];
                $billData['ai_summary'] = $aiResult['abstract'] ?? $aiResult['summary'];
                $billData['ai_confidence'] = $aiResult['confidence'];
                $billData['ai_processed_at'] = date('Y-m-d H:i:s');
                $billData['mistral_ai_json_response'] = $aiResult['mistral_ai_json_response'] ?? null;
            } else {
                logMessage("AI classification failed for {$law['id_loi']}: {$aiResult['error']}", 'WARNING');
            }
        }

        // Save to production
        $saveResult = saveBillToProduction($billData);

        if ($saveResult['success']) {
            return ['action' => $saveResult['action']];
        } else {
            return ['action' => 'error', 'error' => $saveResult['error']];
        }

    } catch (Exception $e) {
        logMessage("Error processing law {$law['id_loi']}: " . $e->getMessage(), 'ERROR');
        return ['action' => 'error', 'error' => $e->getMessage()];
    }
}

/**
 * Prepare bill data for production database
 *
 * @param array $law Raw law data from CSV
 * @param string $source Source identifier
 * @param string $billId Generated bill ID
 * @return array Bill data ready for saveBillToProduction()
 */
function prepareBillData(array $law, string $source, string $billId): array
{
    $importConfig = getDILAImportConfig();

    // Build Légifrance URL
    $fullTextUrl = buildLegifranceUrl($law['id_loi']);

    // Build metadata JSON
    $metadata = [
        'dossier_an_id' => $law['dossier_an_id'] ?? null,
        'commission' => $law['commission'] ?? null,
        'legislature' => $law['legislature'] ?? null,
        'mots_cles' => $law['mots_cles'] ?? null,
        'taux_application' => $law['taux_application'] ?? null,
        'nb_mesures_attendues' => $law['nb_mesures_attendues'] ?? null,
        'nb_mesures_prises' => $law['nb_mesures_prises'] ?? null,
        'type_loi' => $law['type_loi'] ?? null,
        'procedure' => $law['procedure'] ?? null,
        'numero_loi' => $law['numero_loi'] ?? null,
    ];

    // Filter out null values from metadata
    $metadata = array_filter($metadata, fn($v) => $v !== null && $v !== '');

    // Determine chamber from commission or dossier
    $chamber = $importConfig['default_chamber'];
    if (!empty($law['commission'])) {
        // Commission names can help determine chamber
        $chamber = 'Assemblée Nationale'; // Default for DILA data
    }

    // Publication date becomes the vote_datetime (these are published laws)
    $voteDate = $law['_normalized_date'] ?? normalizeDateToDatabaseFormat($law['date_publication']);
    if ($voteDate) {
        $voteDate .= ' 12:00:00'; // Add noon time for display
    } else {
        $voteDate = date('Y-m-d H:i:s');
    }

    // Build summary from keywords and application rate
    $summary = buildSummary($law);

    return [
        'id' => $billId,
        'external_id' => (string)$law['id_loi'],
        'source' => $source,
        'title' => cleanText($law['titre'], 500),
        'summary' => cleanText($summary, 5000),
        'full_text_url' => $fullTextUrl,
        'level' => $importConfig['default_level'],
        'chamber' => $chamber,
        'vote_datetime' => $voteDate,
        'status' => 'completed', // Published laws are completed
        'theme' => 'Sans catégorie', // Will be set by AI
        'ai_summary' => null,
        'ai_confidence' => null,
        'mistral_ai_json_response' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
    ];
}

/**
 * Build a summary from available law data
 *
 * @param array $law Law data
 * @return string Summary text
 */
function buildSummary(array $law): string
{
    $parts = [];

    // Add law type
    if (!empty($law['type_loi'])) {
        $parts[] = "Type : " . $law['type_loi'];
    }

    // Add keywords
    if (!empty($law['mots_cles'])) {
        $keywords = str_replace([';', '|'], ', ', $law['mots_cles']);
        $parts[] = "Thèmes : " . $keywords;
    }

    // Add application rate
    if (!empty($law['taux_application'])) {
        $parts[] = "Taux d'application : " . $law['taux_application'];
    }

    // Add commission
    if (!empty($law['commission'])) {
        $parts[] = "Commission : " . $law['commission'];
    }

    if (empty($parts)) {
        return "Loi publiée au Journal Officiel. Consultez le texte intégral sur Légifrance.";
    }

    return implode('. ', $parts) . '.';
}

// ============================================================================
// STEP 5: CLEANUP
// ============================================================================

/**
 * Remove cache files older than max_age
 */
function cleanupOldCacheFiles(): void
{
    $cacheConfig = getDILACacheConfig();
    $cacheDir = $cacheConfig['directory'];
    $maxAge = $cacheConfig['max_age'];

    if (!is_dir($cacheDir)) {
        return;
    }

    $files = glob($cacheDir . '/dila_*.csv');
    $now = time();
    $deleted = 0;

    foreach ($files as $file) {
        if ($now - filemtime($file) > $maxAge) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }

    if ($deleted > 0) {
        logMessage("Cleaned up $deleted old cache files");
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Truncate title for display
 *
 * @param string $title Full title
 * @param int $maxLength Maximum length
 * @return string Truncated title
 */
function truncateTitle(string $title, int $maxLength = 60): string
{
    if (mb_strlen($title) <= $maxLength) {
        return $title;
    }
    return mb_substr($title, 0, $maxLength - 3) . '...';
}

// ============================================================================
// CLI EXECUTION
// ============================================================================

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "\n";
    echo str_repeat('*', 70) . "\n";
    echo "*  DILA / Légifrance Fetcher - Direct Execution                      *\n";
    echo str_repeat('*', 70) . "\n";

    $result = fetchDILA();

    // Output JSON result for scripting
    echo "\n--- JSON Result ---\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    exit($result['status'] === 'success' ? 0 : 1);
}