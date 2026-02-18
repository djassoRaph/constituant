<?php
/**
 * Légifrance API Fetcher
 *
 * Fetches active legislative dossiers (bills currently in parliament)
 * via the official Légifrance REST API through the PISTE platform.
 *
 * Auth:    OAuth2 client credentials (see cron/lib/piste-api.php)
 * Source:  https://piste.gouv.fr — Légifrance API v2.4.2
 * Fund:    dossiersLegislatifs — bills going through parliament (not yet promulgated)
 *
 * These are UPCOMING bills — citizens can vote on Constituant.fr before
 * the actual parliamentary vote takes place.
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';
require_once __DIR__ . '/../lib/piste-api.php';

// ============================================================================
// MAIN ENTRY POINT
// ============================================================================

/**
 * Fetch active legislative dossiers (upcoming bills) from the Légifrance API.
 *
 * @return array Import statistics
 */
function fetchLegifrance(): array
{
    $startTime = microtime(true);
    $source    = 'legifrance-api';

    echo "\n" . str_repeat('=', 70) . "\n";
    echo "Légifrance API - Active Legislative Dossiers (Upcoming Bills)\n";
    echo str_repeat('=', 70) . "\n\n";

    logMessage("===== Starting Légifrance API import (dossiersLegislatifs) =====");

    $stats = [
        'fetched' => 0,
        'new'     => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors'  => [],
    ];

    try {
        // Step 1: Authenticate
        echo "Step 1: Authenticating with PISTE...\n";
        $token = getPisteAccessToken();
        echo "  OK\n\n";

        // Step 2: Ping to verify API is reachable
        echo "Step 2: Pinging Légifrance API...\n";
        if (!pingLegifranceApi($token)) {
            logMessage("Légifrance API ping failed — continuing anyway", 'WARNING');
            echo "  WARNING: Ping returned unexpected response, continuing...\n\n";
        } else {
            echo "  OK\n\n";
        }

        // Step 3: Fetch active legislative dossiers (projets de loi + propositions de loi)
        // Each call requires a specific 'type' — the API does not accept a wildcard.
        // No pagination parameters — the API returns all dossiers for the given type+legislature.
        echo "Step 3: Fetching active legislative dossiers (bills in parliament)...\n";

        $types = ['PROJET_LOI', 'PROPOSITION_LOI'];
        $results = [];

        foreach ($types as $type) {
            $requestBody = buildDossierListRequest($type);
            logMessage("Légifrance: Fetching dossiersLegislatifs type=$type legislature=17");

            try {
                $response = callLegifranceApi('list/dossiersLegislatifs', $requestBody, $token);

                // Response key is 'dossiersLegislatifs' (confirmed from API response)
                $batch = $response['dossiersLegislatifs'] ?? [];

                echo "  $type: " . count($batch) . " dossiers\n";
                logMessage("Légifrance: $type — " . count($batch) . " dossiers");

                // Debug on first non-empty batch
                if (!empty($batch)) {
                    logMessage("Légifrance: Dossier item keys [$type]: " . implode(', ', array_keys($batch[0])));
                } else {
                    $preview = json_encode(array_slice($response, 0, 2, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    logMessage("Légifrance: Empty results for $type. Response keys: " . implode(', ', array_keys($response)), 'WARNING');
                    logMessage("Légifrance: Response preview: " . $preview, 'WARNING');
                }

                $results = array_merge($results, $batch);

            } catch (Exception $e) {
                logMessage("Légifrance: Failed to fetch type=$type: " . $e->getMessage(), 'WARNING');
                echo "  [WARN] $type failed: " . $e->getMessage() . "\n";
            }
        }

        echo "\n  Total dossiers collected: " . count($results) . "\n\n";
        logMessage("Légifrance: Total dossiers collected: " . count($results));

        // Step 4: Process dossiers
        echo "Step 4: Processing dossiers...\n";
        echo str_repeat('-', 70) . "\n";

        $stats['fetched'] = count($results);

        foreach ($results as $dossier) {
            $result = processLegifranceDossier($dossier, $source);

            switch ($result['action']) {
                case 'inserted':
                    $stats['new']++;
                    echo "  [NEW] " . truncateLegiTitle($dossier) . "\n";
                    break;
                case 'updated':
                    $stats['updated']++;
                    echo "  [UPD] " . truncateLegiTitle($dossier) . "\n";
                    break;
                case 'skipped':
                    $stats['skipped']++;
                    break;
                case 'error':
                    $stats['errors'][] = $result['error'];
                    echo "  [ERR] " . truncateLegiTitle($dossier) . ": " . $result['error'] . "\n";
                    break;
            }

            // Small delay between AI calls
            if ($result['action'] === 'inserted' || $result['action'] === 'updated') {
                usleep(500000);
            }
        }

        // Final
        $executionTime = microtime(true) - $startTime;
        $status = empty($stats['errors']) ? 'success' : 'partial';

        logImportOperation($source, $status, $stats, $executionTime);
        logMessage("Légifrance import done: {$stats['new']} new, {$stats['updated']} updated");

        echo "\n" . str_repeat('=', 70) . "\n";
        echo "Légifrance Import Complete\n";
        echo "  New: {$stats['new']} | Updated: {$stats['updated']} | Skipped: {$stats['skipped']} | Errors: " . count($stats['errors']) . "\n";
        echo "  Time: " . round($executionTime, 2) . "s\n";
        echo str_repeat('=', 70) . "\n\n";

        return array_merge($stats, ['status' => $status]);

    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        $stats['errors'][] = $e->getMessage();

        logMessage("Légifrance import failed: " . $e->getMessage(), 'ERROR');
        logImportOperation($source, 'failed', $stats, $executionTime);

        echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";

        return array_merge($stats, ['status' => 'failed']);
    }
}

// ============================================================================
// BUILD DOSSIER LIST REQUEST
// ============================================================================

/**
 * Build the POST /list/dossiersLegislatifs request body.
 *
 * Fetches bills currently being debated in the French parliament.
 * These have not yet been promulgated — they are upcoming/active bills.
 *
 * Note: The exact filtres facettes for this endpoint are documented at:
 *   https://raw.githubusercontent.com/betagouv/api_gouv_swaggers/main/swaggers/api-legifrance-v2.json
 * If the response is empty, try removing the filtres[] to get all dossiers.
 */
function buildDossierListRequest(string $type): array
{
    // Required fields per DossiersLegislatifRequest schema:
    //   - type:          one of PROJET_LOI | PROPOSITION_LOI | LOI_PUBLIEE | ORDONNANCE_PUBLIEE
    //   - legislatureId: integer — 17th legislature started July 2024
    // No pageNumber/pageSize — the API returns all dossiers for the given type+legislature.
    return [
        'type'          => $type,
        'legislatureId' => 17,
    ];
}

// ============================================================================
// PROCESS A SINGLE DOSSIER
// ============================================================================

/**
 * Extract fields from a legislative dossier and save as an upcoming bill.
 *
 * Dossier IDs use the format "DOSSIERLEGI..." or similar.
 * Since the vote date is not yet set, we estimate it as 30 days from now.
 * The status is set to 'upcoming' so users can vote before parliament does.
 */
function processLegifranceDossier(array $dossier, string $source): array
{
    try {
        // Log keys of the first item to help verify field mapping
        static $loggedKeys = false;
        if (!$loggedKeys) {
            logMessage("Légifrance: Dossier keys: " . implode(', ', array_keys($dossier)));
            $loggedKeys = true;
        }

        // Extract ID — dossiers use 'id', 'cid', 'dossierId', etc.
        $cid = $dossier['id']        ?? $dossier['cid']       ?? $dossier['dossierId']
            ?? $dossier['idDossier'] ?? $dossier['reference'] ?? null;

        // Extract title — 'titre', 'title', 'titreDossier', etc.
        $title = $dossier['titre']        ?? $dossier['title']
              ?? $dossier['titreDossier'] ?? $dossier['titreOfficiel']
              ?? null;

        if (empty($cid) || empty($title)) {
            logMessage(
                "Légifrance: Skipping dossier — missing id or title. Keys: " . implode(', ', array_keys($dossier)) .
                " | Preview: " . substr(json_encode($dossier, JSON_UNESCAPED_UNICODE), 0, 300),
                'WARNING'
            );
            return ['action' => 'skipped', 'reason' => 'missing_fields'];
        }

        $billId = generateBillId($source, $cid, $title);

        // Skip if recently updated (< 7 days)
        $pdo  = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT id, updated_at FROM bills WHERE id = ? OR (source = ? AND external_id = ?)"
        );
        $stmt->execute([$billId, $source, $cid]);
        $existing = $stmt->fetch();

        if ($existing) {
            $daysSince = (new DateTime())->diff(new DateTime($existing['updated_at']))->days;
            if ($daysSince < 7) {
                return ['action' => 'skipped', 'reason' => 'recently_updated'];
            }
        }

        // Estimate vote date — dossiers in parliament typically take 1–3 months
        // Use 30 days from now as a placeholder; update when the actual date is known
        $voteDate = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Try to use a real date if available (e.g. scheduled session date)
        $dateStr = $dossier['dateExamen']     ?? $dossier['datePrevueVote']
                ?? $dossier['dateDernierTexte'] ?? $dossier['dateDepot']
                ?? null;

        if ($dateStr) {
            $ts = is_numeric($dateStr) ? (int)($dateStr / 1000) : strtotime($dateStr);
            if ($ts && $ts > time()) {
                // Only use if it's actually in the future
                $voteDate = date('Y-m-d', $ts) . ' 12:00:00';
            }
        }

        // Build summary from available dossier fields
        $summary = buildDossierSummary($dossier);

        // Determine chamber from dossier data
        $chamber = extractDossierChamber($dossier);

        // Full text URL — dossiers link differently to promulgated laws
        $fullTextUrl = $dossier['urlDossier'] ?? $dossier['url']
                    ?? "https://www.legifrance.gouv.fr/dossierlegislatif/id/{$cid}";

        $billData = [
            'id'                       => $billId,
            'external_id'              => $cid,
            'source'                   => $source,
            'title'                    => cleanText($title, 500),
            'summary'                  => cleanText($summary, 5000),
            'full_text_url'            => $fullTextUrl,
            'level'                    => 'france',
            'chamber'                  => $chamber,
            'vote_datetime'            => $voteDate,
            'status'                   => 'upcoming',
            'theme'                    => 'Sans catégorie',
            'ai_summary'               => null,
            'ai_confidence'            => null,
            'mistral_ai_json_response' => json_encode($dossier, JSON_UNESCAPED_UNICODE),
        ];

        // AI classification
        if (defined('ENABLE_AI_CLASSIFICATION') && ENABLE_AI_CLASSIFICATION) {
            $aiResult = classifyBillWithRetry($billData['title'], $billData['summary']);
            if ($aiResult['error'] === null) {
                $billData['theme']                    = $aiResult['theme'];
                $billData['ai_summary']               = $aiResult['abstract'] ?? $aiResult['summary'];
                $billData['ai_confidence']            = $aiResult['confidence'];
                $billData['ai_processed_at']          = date('Y-m-d H:i:s');
                $billData['mistral_ai_json_response'] = $aiResult['mistral_ai_json_response'] ?? null;
            } else {
                logMessage("Légifrance: AI classification failed for $cid: " . $aiResult['error'], 'WARNING');
            }
        }

        $saveResult = saveBillToProduction($billData);

        return $saveResult['success']
            ? ['action' => $saveResult['action']]
            : ['action' => 'error', 'error' => $saveResult['error']];

    } catch (Exception $e) {
        logMessage("Légifrance: Error processing dossier: " . $e->getMessage(), 'ERROR');
        return ['action' => 'error', 'error' => $e->getMessage()];
    }
}

/**
 * Build a human-readable summary from available dossier fields.
 */
function buildDossierSummary(array $dossier): string
{
    $parts = [];

    if (!empty($dossier['nature']) || !empty($dossier['typeTexte'])) {
        $parts[] = "Type : " . ($dossier['nature'] ?? $dossier['typeTexte']);
    }
    if (!empty($dossier['nor'])) {
        $parts[] = "NOR : " . $dossier['nor'];
    }
    if (!empty($dossier['dateDepot'])) {
        $parts[] = "Déposé le : " . $dossier['dateDepot'];
    }
    if (!empty($dossier['themes'])) {
        $themes = is_array($dossier['themes']) ? implode(', ', $dossier['themes']) : $dossier['themes'];
        $parts[] = "Thèmes : " . $themes;
    }
    if (!empty($dossier['motsCles'])) {
        $mots = is_array($dossier['motsCles']) ? implode(', ', $dossier['motsCles']) : $dossier['motsCles'];
        $parts[] = "Mots-clés : " . $mots;
    }
    if (!empty($dossier['resume']) || !empty($dossier['exposéDesMotifs'])) {
        $parts[] = $dossier['resume'] ?? $dossier['exposéDesMotifs'];
    }

    return empty($parts)
        ? "Projet ou proposition de loi en cours d'examen au Parlement. Consultez le dossier complet sur Légifrance."
        : implode('. ', $parts) . '.';
}

/**
 * Determine the legislative chamber from dossier data.
 */
function extractDossierChamber(array $dossier): string
{
    $raw = $dossier['organeRef'] ?? $dossier['chambre'] ?? $dossier['origine'] ?? '';
    $raw = strtolower((string)$raw);

    if (str_contains($raw, 'senat') || str_contains($raw, 'sénat') || $raw === 'sn') {
        return 'Sénat';
    }
    if (str_contains($raw, 'assembl') || $raw === 'an') {
        return 'Assemblée Nationale';
    }
    return 'Parlement';
}

/**
 * Truncate a dossier title for console output.
 */
function truncateLegiTitle(array $item, int $max = 60): string
{
    $title = $item['titre']        ?? $item['title']
          ?? $item['titreDossier'] ?? $item['titreOfficiel']
          ?? $item['titles'][0]['title'] ?? $item['titles'][0]['titre']
          ?? 'Unknown';
    return mb_strlen($title) > $max ? mb_substr($title, 0, $max - 3) . '...' : $title;
}

// ============================================================================
// LEGACY: LODA_DATE SEARCH (promulgated/completed laws — kept for reference)
// ============================================================================

/**
 * Build a POST /search request body for already-promulgated laws.
 *
 * Fund LODA_DATE contains laws AFTER publication in the Journal Officiel.
 * These are PAST/COMPLETED — use this only if you want to import
 * promulgated laws for historical reference, not for citizen voting.
 *
 * @deprecated Use buildDossierListRequest() + processLegifranceDossier() instead
 */
function buildSearchRequest(string $dateFrom, string $dateTo, int $page, int $size): array
{
    return [
        'fond'      => 'LODA_DATE',
        'recherche' => [
            'pageNumber'    => $page,
            'pageSize'      => $size,
            'sort'          => 'DATE_SIGNATURE',
            'typeRecherche' => 'TOUS_LES_MOTS_DANS_UN_CHAMP',
            'champs'        => [],
            'filtres'       => [
                [
                    'facette' => 'NATURE',
                    'valeurs' => ['LOI'],
                ],
                [
                    'facette' => 'DATE_SIGNATURE',
                    'dates'   => [
                        'start' => $dateFrom,
                        'end'   => $dateTo,
                    ],
                ],
            ],
        ],
    ];
}

// ============================================================================
// CLI EXECUTION
// ============================================================================

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "\n";
    echo str_repeat('*', 70) . "\n";
    echo "*  Légifrance API Fetcher — Direct Execution                         *\n";
    echo str_repeat('*', 70) . "\n";

    $result = fetchLegifrance();

    echo "\n--- JSON Result ---\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    exit($result['status'] === 'success' ? 0 : 1);
}
