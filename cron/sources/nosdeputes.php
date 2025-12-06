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

        $dossiers = $parsed['data']['dossiers_legislatif'] ?? [];
        logMessage("Found " . count($dossiers) . " dossiers");

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

        // Also fetch recent scrutins (votes) for more detailed info
        fetchNosDePutesScrutins($config, $stats);

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
    // NosDéputés structure varies, handle different formats
    $titre = $dossier['titre'] ?? $dossier['title'] ?? null;
    $url = $dossier['url'] ?? $dossier['url_dossier_assemblee'] ?? null;

    if (empty($titre)) {
        logMessage("Skipping dossier without title", 'WARNING');
        return null;
    }

    // Use dossier ID or URL as external ID
    $externalId = $dossier['id'] ?? $dossier['numero'] ?? null;
    if (!$externalId && $url) {
        // Extract ID from URL if available
        preg_match('/\/dossiers\/(\w+)/', $url, $matches);
        $externalId = $matches[1] ?? md5($titre);
    }

    // Extract summary
    $summary = $dossier['resume'] ?? $dossier['description'] ?? null;
    if (empty($summary) && isset($dossier['texte'])) {
        $summary = extractSummaryFromHtml($dossier['texte']);
    }

    // Parse dates
    $voteDate = null;
    if (isset($dossier['date_scrutin'])) {
        $voteDate = parseDate($dossier['date_scrutin']);
    } elseif (isset($dossier['date'])) {
        $voteDate = parseDate($dossier['date']);
    }

    // Get chamber
    $chamber = 'Assemblée Nationale';
    if (isset($dossier['assemblee'])) {
        $chamber = $dossier['assemblee'] === 'senat' ? 'Sénat' : 'Assemblée Nationale';
    }

    // Get full text URL
    $fullTextUrl = $url ?? $dossier['url_texte'] ?? null;

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
