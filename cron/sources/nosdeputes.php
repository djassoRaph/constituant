<?php
/**
 * NosDéputés.fr Bill Fetcher
 *
 * Fetches legislative bills from NosDéputés.fr API
 * Documentation: https://github.com/regardscitoyens/nosdeputes.fr/blob/master/doc/api.md
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';

/**
 * Fetch bills from NosDéputés.fr
 *
 * @return array Import statistics
 */
function fetchNosDePutes(): array
{
    $startTime = microtime(true);
    $source = 'nosdeputes';

    logMessage("Starting NosDéputés.fr import...");

    $config = getSourceConfig($source);
    if (!$config || !$config['enabled']) {
        logMessage("NosDéputés.fr source is disabled", 'WARNING');
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
        // Fetch recent dossiers (legislative files)
        $dossiersUrl = $config['base_url'] . $config['endpoints']['dossiers'];
        logMessage("Fetching dossiers from: $dossiersUrl");

        $result = fetchUrl($dossiersUrl, [], 0);

        if (!$result['success']) {
            throw new Exception($result['error']);
        }

        $parsed = parseJson($result['data']);
        if (!$parsed['success']) {
            throw new Exception($parsed['error']);
        }

        // API structure changed: now uses 'sections' instead of 'dossiers_legislatif'
        $sections = $parsed['data']['sections'] ?? [];
        logMessage("Found " . count($sections) . " dossier sections");

        // Extract dossiers from sections
        $dossiers = [];
        foreach ($sections as $sectionWrapper) {
            if (isset($sectionWrapper['section'])) {
                $dossiers[] = $sectionWrapper['section'];
            }
        }

        logMessage("Extracted " . count($dossiers) . " dossiers from sections");

        $maxBills = IMPORT_SETTINGS['max_bills_per_source'];
        $processed = 0;

        foreach ($dossiers as $dossier) {
            if ($processed >= $maxBills) {
                logMessage("Reached max bills limit ($maxBills), stopping");
                break;
            }

            $stats['fetched']++;

            // Extract bill data
            $billData = extractNosDePutesBillData($dossier, $source);

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
            if ($processed < count($dossiers)) {
                sleep($config['rate_limit']['delay_seconds']);
            }
        }

        // Scrutins (votes) could be fetched separately, but dossiers provide enough info
        // Uncomment if needed: fetchNosDePutesScrutins($config, $stats);

        $executionTime = microtime(true) - $startTime;
        $status = empty($stats['errors']) ? 'success' : 'partial';

        logImport($source, $status, $stats, $executionTime);
        logMessage("NosDéputés.fr import completed in " . round($executionTime, 2) . "s");
        logMessage("Stats: {$stats['new']} new, {$stats['updated']} updated, {$stats['skipped']} skipped");

        return array_merge($stats, ['status' => $status]);

    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        $stats['errors'][] = $e->getMessage();

        logImport($source, 'failed', $stats, $executionTime);
        logMessage("NosDéputés.fr import failed: " . $e->getMessage(), 'ERROR');

        return array_merge($stats, ['status' => 'failed']);
    }
}

/**
 * Extract bill data from NosDéputés dossier
 *
 * @param array $dossier Dossier data from API
 * @param string $source Source name
 * @return array|null Bill data or null if invalid
 */
function extractNosDePutesBillData(array $dossier, string $source): ?array
{
    // NosDéputés API structure (new format from 'sections' array):
    // - id (numeric)
    // - id_dossier_institution (string identifier)
    // - titre (title)
    // - url_institution (Assemblée Nationale URL)
    // - url_nosdeputes (NosDéputés URL)
    // - url_nosdeputes_api (API URL for details)
    // - min_date, max_date (date range)

    $titre = $dossier['titre'] ?? null;

    if (empty($titre)) {
        logMessage("Skipping dossier without title", 'WARNING');
        return null;
    }

    // Use id_dossier_institution as external ID (more stable than numeric id)
    $externalId = $dossier['id_dossier_institution'] ?? $dossier['id'] ?? md5($titre);

    // Get URLs
    $url = $dossier['url_nosdeputes'] ?? $dossier['url_institution'] ?? null;
    $fullTextUrl = $dossier['url_institution'] ?? $url;

    // Use max_date as the latest activity date
    $voteDate = null;
    if (isset($dossier['max_date'])) {
        $voteDate = parseDate($dossier['max_date']);
    } elseif (isset($dossier['min_date'])) {
        $voteDate = parseDate($dossier['min_date']);
    }

    // Extract summary from title (create a brief description)
    $summary = "Dossier législatif examiné à l'Assemblée nationale";
    if (isset($dossier['nb_interventions'])) {
        $summary .= " ({$dossier['nb_interventions']} interventions)";
    }

    // Determine chamber from URL
    $chamber = 'Assemblée Nationale';
    if (!empty($url) && stripos($url, 'senat.fr') !== false) {
        $chamber = 'Sénat';
    }

    return [
        'external_id' => (string)$externalId,
        'source' => $source,
        'title' => cleanText($titre, 500),
        'summary' => cleanText($summary, 5000),
        'full_text_url' => $fullTextUrl,
        'level' => 'france',
        'chamber' => $chamber,
        'vote_datetime' => $voteDate,
        'raw_data' => $dossier,
    ];
}

/**
 * Fetch scrutins (voting sessions) for additional bill information
 *
 * @param array $config Source configuration
 * @param array &$stats Statistics array (passed by reference)
 * @return void
 */
function fetchNosDePutesScrutins(array $config, array &$stats): void
{
    try {
        $scrutinsUrl = $config['base_url'] . $config['endpoints']['scrutins'];
        logMessage("Fetching scrutins from: $scrutinsUrl");

        sleep($config['rate_limit']['delay_seconds']);

        $result = fetchUrl($scrutinsUrl, [], 0);

        if (!$result['success']) {
            logMessage("Failed to fetch scrutins: " . $result['error'], 'WARNING');
            return;
        }

        $parsed = parseJson($result['data']);
        if (!$parsed['success']) {
            logMessage("Failed to parse scrutins: " . $parsed['error'], 'WARNING');
            return;
        }

        $scrutins = $parsed['data']['scrutins'] ?? [];
        logMessage("Found " . count($scrutins) . " scrutins");

        $maxScrutins = 20; // Limit scrutins to process
        $processed = 0;

        foreach ($scrutins as $scrutin) {
            if ($processed >= $maxScrutins) {
                break;
            }

            // Extract bill data from scrutin
            $billData = extractScrutinBillData($scrutin, 'nosdeputes');

            if ($billData) {
                $saveResult = savePendingBill($billData);

                if ($saveResult['success'] && $saveResult['action'] === 'inserted') {
                    $stats['new']++;
                    logMessage("New bill from scrutin: {$billData['title']}", 'INFO');
                }
            }

            $processed++;
            sleep($config['rate_limit']['delay_seconds']);
        }

    } catch (Exception $e) {
        logMessage("Error fetching scrutins: " . $e->getMessage(), 'WARNING');
    }
}

/**
 * Extract bill data from scrutin (voting session)
 *
 * @param array $scrutin Scrutin data
 * @param string $source Source name
 * @return array|null Bill data or null if invalid
 */
function extractScrutinBillData(array $scrutin, string $source): ?array
{
    $titre = $scrutin['titre'] ?? $scrutin['objet'] ?? null;

    if (empty($titre)) {
        return null;
    }

    $externalId = 'scrutin-' . ($scrutin['numero'] ?? md5($titre));

    // Parse date
    $voteDate = null;
    if (isset($scrutin['date'])) {
        $voteDate = parseDate($scrutin['date']);
    }

    // Get URL
    $url = $scrutin['url'] ?? null;

    // Extract summary from context
    $summary = $scrutin['contexte'] ?? $scrutin['demandeur'] ?? null;

    return [
        'external_id' => (string)$externalId,
        'source' => $source,
        'title' => cleanText($titre, 500),
        'summary' => cleanText($summary, 5000),
        'full_text_url' => $url,
        'level' => 'france',
        'chamber' => 'Assemblée Nationale',
        'vote_datetime' => $voteDate,
        'raw_data' => $scrutin,
    ];
}

// If run directly (not included), execute fetcher
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $result = fetchNosDePutes();
    exit($result['status'] === 'success' ? 0 : 1);
}
