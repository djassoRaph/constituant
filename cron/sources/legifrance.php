<?php
/**
 * Légifrance (DILA/PISTE) Fetcher - Official French Government Legal Data
 *
 * Fetches recently published laws from the Légifrance API via PISTE.
 * Uses OAuth 2.0 client credentials for authentication.
 * Replaces NosDéputés.fr, La Fabrique de la Loi, and EU Parliament fetchers.
 *
 * API Documentation: https://piste.gouv.fr/
 * Data License: Licence Ouverte 2.0
 *
 * @package Constituant
 * @since 2026-02
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';

// ============================================================================
// OAUTH TOKEN MANAGEMENT
// ============================================================================

/**
 * Get the OAuth URL based on environment setting
 *
 * @return string OAuth token URL
 */
function getLegifranceOAuthUrl(): string
{
    $env = defined('LEGIFRANCE_ENV') ? LEGIFRANCE_ENV : 'sandbox';
    return ($env === 'production')
        ? LEGIFRANCE_OAUTH_URL_PROD
        : LEGIFRANCE_OAUTH_URL_SANDBOX;
}

/**
 * Get the API base URL based on environment setting
 *
 * @return string API base URL
 */
function getLegifranceApiUrl(): string
{
    $env = defined('LEGIFRANCE_ENV') ? LEGIFRANCE_ENV : 'sandbox';
    return ($env === 'production')
        ? LEGIFRANCE_API_URL_PROD
        : LEGIFRANCE_API_URL_SANDBOX;
}

/**
 * Get the token cache file path
 *
 * @return string Path to cache file
 */
function getTokenCacheFile(): string
{
    $config = getSourceConfig('legifrance');
    if ($config && !empty($config['token_cache_file'])) {
        return $config['token_cache_file'];
    }
    return __DIR__ . '/../cache/legifrance_token.json';
}

/**
 * Get a valid OAuth bearer token (cached or fresh)
 *
 * Caches the token to disk and reuses it until 60 seconds before expiry.
 *
 * @return string Bearer access token
 * @throws Exception If token cannot be obtained
 */
function getLegifranceToken(): string
{
    $cacheFile = getTokenCacheFile();

    // Try cached token first
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['access_token'], $cached['expires_at'])) {
            // 60-second buffer before expiry
            if ($cached['expires_at'] > time() + 60) {
                logMessage("Using cached Légifrance token (expires in " . ($cached['expires_at'] - time()) . "s)");
                return $cached['access_token'];
            }
            logMessage("Cached Légifrance token expired, requesting new one");
        }
    }

    // Request new token
    $oauthUrl = getLegifranceOAuthUrl();
    logMessage("Requesting Légifrance OAuth token from: $oauthUrl");

    $postData = http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => LEGIFRANCE_CLIENT_ID,
        'client_secret' => LEGIFRANCE_CLIENT_SECRET,
        'scope'         => 'openid',
    ]);

    $ch = curl_init($oauthUrl);
    if ($ch === false) {
        throw new Exception('Failed to initialize curl for OAuth');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("OAuth curl error: $curlError");
    }

    if ($httpCode !== 200) {
        throw new Exception("OAuth HTTP $httpCode: " . substr($response, 0, 300));
    }

    $data = json_decode($response, true);
    if (!$data || empty($data['access_token'])) {
        throw new Exception("OAuth response missing access_token: " . substr($response, 0, 300));
    }

    $expiresIn = $data['expires_in'] ?? 3600;

    // Cache to disk
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $cacheData = [
        'access_token' => $data['access_token'],
        'expires_at'   => time() + $expiresIn,
        'obtained_at'  => date('Y-m-d H:i:s'),
    ];

    file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT), LOCK_EX);
    logMessage("Légifrance token obtained, expires in {$expiresIn}s");

    return $data['access_token'];
}

// ============================================================================
// API REQUEST HELPERS
// ============================================================================

/**
 * Make an authenticated POST request to the Légifrance API
 *
 * @param string $token  Bearer access token
 * @param string $path   API endpoint path (e.g. '/search')
 * @param array  $body   Request body (will be JSON-encoded)
 * @return array ['success' => bool, 'data' => mixed, 'error' => string|null]
 */
function legifranceApiPost(string $token, string $path, array $body): array
{
    $url = getLegifranceApiUrl() . $path;

    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'data' => null, 'error' => 'Failed to initialize curl'];
    }

    $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Constituant/2.0 (https://constituant.fr)',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'data' => null, 'error' => "Curl error: $curlError"];
    }

    // Handle specific HTTP errors
    if ($httpCode === 401) {
        return ['success' => false, 'data' => null, 'error' => 'TOKEN_EXPIRED'];
    }

    if ($httpCode === 429) {
        return ['success' => false, 'data' => null, 'error' => 'RATE_LIMITED'];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'data' => $response,
            'error' => "HTTP $httpCode: " . substr($response, 0, 300),
        ];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'data' => $response,
            'error' => 'JSON parse error: ' . json_last_error_msg(),
        ];
    }

    return ['success' => true, 'data' => $data, 'error' => null];
}

/**
 * Make an API request with automatic token refresh on 401
 *
 * @param string &$token  Bearer token (may be updated)
 * @param string $path    API endpoint path
 * @param array  $body    Request body
 * @return array Response data
 */
function legifranceApiPostWithRetry(string &$token, string $path, array $body): array
{
    $result = legifranceApiPost($token, $path, $body);

    // On 401, refresh token and retry once
    if (!$result['success'] && $result['error'] === 'TOKEN_EXPIRED') {
        logMessage("Token expired, refreshing...");

        // Delete cache to force refresh
        $cacheFile = getTokenCacheFile();
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        $token = getLegifranceToken();
        $result = legifranceApiPost($token, $path, $body);
    }

    // On 429, wait and retry once
    if (!$result['success'] && $result['error'] === 'RATE_LIMITED') {
        logMessage("Rate limited, waiting 60 seconds before retry...");
        sleep(60);
        $result = legifranceApiPost($token, $path, $body);
    }

    return $result;
}

// ============================================================================
// SEARCH & FETCH FUNCTIONS
// ============================================================================

/**
 * Search for recently published laws in the JORF fund
 *
 * @param string &$token   Bearer token
 * @param int    $daysBack How many days back to search (default: 90)
 * @return array List of law result items from the API
 */
function searchRecentLaws(string &$token, int $daysBack = 90): array
{
    // Calculate timestamps in milliseconds (Légifrance uses ms)
    $endTimestamp   = time() * 1000;
    $startTimestamp = (time() - ($daysBack * 86400)) * 1000;

    $body = [
        'recherche' => [
            'champs' => [
                [
                    'typeChamp' => 'ALL',
                    'criteres' => [
                        [
                            'typeRecherche' => 'TOUS_LES_MOTS',
                            'valeur'        => 'loi',
                            'operateur'     => 'ET',
                        ],
                    ],
                    'operateur' => 'ET',
                ],
            ],
            'filtres' => [
                [
                    'facette' => 'DATE_PUBLICATION',
                    'dates'   => [
                        'start' => (string) $startTimestamp,
                        'end'   => (string) $endTimestamp,
                    ],
                ],
                [
                    'facette' => 'NATURE',
                    'valeurs' => ['LOI', 'ORDONNANCE'],
                ],
            ],
            'pageNumber'     => 1,
            'pageSize'       => 50,
            'operateur'      => 'ET',
            'sort'           => 'DATE_DESC',
            'typePagination' => 'DEFAUT',
        ],
        'fond' => 'JORF',
    ];

    logMessage("Searching Légifrance JORF: last {$daysBack} days, LOI + ORDONNANCE");

    $result = legifranceApiPostWithRetry($token, '/search', $body);

    if (!$result['success']) {
        logMessage("Légifrance search failed: " . $result['error'], 'ERROR');
        return [];
    }

    // Extract results from response
    $results = $result['data']['results'] ?? [];

    logMessage("Légifrance search returned " . count($results) . " results");

    return $results;
}

/**
 * Get full text/details of a specific law
 *
 * @param string &$token Bearer token
 * @param string $lawId  JORF text ID (e.g. "JORFTEXT000049012345")
 * @return array|null Law details or null on error
 */
function getLawFullText(string &$token, string $lawId): ?array
{
    $body = ['id' => $lawId];

    $result = legifranceApiPostWithRetry($token, '/consult/legiPart', $body);

    if (!$result['success']) {
        logMessage("Failed to fetch law text for $lawId: " . $result['error'], 'WARNING');
        return null;
    }

    return $result['data'];
}

// ============================================================================
// DATA EXTRACTION & MAPPING
// ============================================================================

/**
 * Extract bill data from a Légifrance search result
 *
 * @param array  $result   Single result from /search response
 * @param string $source   Source identifier ('legifrance')
 * @return array|null Formatted bill data or null if invalid
 */
function extractLegifanceBillData(array $result, string $source): ?array
{
    // The search result structure:
    // - titles[]: array of { id, titre, ... }
    // - id: JORFTEXT ID
    // - titre: title text
    // - dateParution: publication timestamp (ms)
    // - nature: "LOI", "ORDONNANCE", etc.
    // - nor: NOR number
    // - dateSignature: signing date timestamp (ms)
    // - origine: origin info

    $titre = $result['titles'][0]['titre'] ?? $result['titre'] ?? null;
    $lawId = $result['titles'][0]['id'] ?? $result['id'] ?? null;

    if (empty($titre)) {
        logMessage("Skipping result without title", 'WARNING');
        return null;
    }

    if (empty($lawId)) {
        logMessage("Skipping result without ID: $titre", 'WARNING');
        return null;
    }

    // Parse dates (timestamps in milliseconds)
    $dateParution = null;
    if (isset($result['dateParution'])) {
        $dateParution = date('Y-m-d', $result['dateParution'] / 1000);
    } elseif (isset($result['titles'][0]['dateParution'])) {
        $dateParution = date('Y-m-d', $result['titles'][0]['dateParution'] / 1000);
    }

    $dateSignature = null;
    if (isset($result['dateSignature'])) {
        $dateSignature = date('Y-m-d', $result['dateSignature'] / 1000);
    } elseif (isset($result['titles'][0]['dateSignature'])) {
        $dateSignature = date('Y-m-d', $result['titles'][0]['dateSignature'] / 1000);
    }

    // Nature of the text
    $nature = $result['nature'] ?? $result['titles'][0]['nature'] ?? 'LOI';

    // NOR number (unique French reference)
    $nor = $result['nor'] ?? $result['titles'][0]['nor'] ?? null;

    // Origine
    $origine = $result['origine'] ?? $result['titles'][0]['origine'] ?? null;

    // Build Légifrance URL
    $legifranceUrl = 'https://www.legifrance.gouv.fr/jorf/id/' . $lawId;

    // Generate unique bill ID
    $billId = generateBillId($source, $lawId, $titre);

    // For newly published laws, the "vote" is essentially the publication date.
    // We set vote_datetime to publication date + a window for citizen engagement.
    // If the law was published recently, give 30 days from now for voting.
    $voteDate = date('Y-m-d H:i:s', strtotime('+30 days'));
    if ($dateParution) {
        $daysSincePublication = (time() - strtotime($dateParution)) / 86400;
        if ($daysSincePublication < 7) {
            // Very recent: give 45 days
            $voteDate = date('Y-m-d H:i:s', strtotime('+45 days'));
        } elseif ($daysSincePublication < 30) {
            // Recent: give 30 days
            $voteDate = date('Y-m-d H:i:s', strtotime('+30 days'));
        } else {
            // Older: give 14 days
            $voteDate = date('Y-m-d H:i:s', strtotime('+14 days'));
        }
    }

    // Build summary from available metadata
    $summaryParts = [];
    if ($nature === 'LOI') {
        $summaryParts[] = "Loi publiée au Journal Officiel";
    } elseif ($nature === 'ORDONNANCE') {
        $summaryParts[] = "Ordonnance publiée au Journal Officiel";
    } else {
        $summaryParts[] = "Texte législatif publié au Journal Officiel";
    }
    if ($dateParution) {
        $summaryParts[] = "le " . date('d/m/Y', strtotime($dateParution));
    }
    if ($nor) {
        $summaryParts[] = "(NOR: $nor)";
    }
    $summary = implode(' ', $summaryParts);

    // Determine chamber from title or origin
    $chamber = 'Assemblée Nationale';
    if ($origine && stripos($origine, 'senat') !== false) {
        $chamber = 'Sénat';
    }
    if (stripos($titre, 'sénat') !== false) {
        $chamber = 'Sénat';
    }

    // Build metadata JSON
    $metadata = json_encode([
        'nature'        => $nature,
        'nor'           => $nor,
        'dateParution'  => $dateParution,
        'dateSignature' => $dateSignature,
        'origine'       => $origine,
        'jorfId'        => $lawId,
    ], JSON_UNESCAPED_UNICODE);

    return [
        'id'            => $billId,
        'external_id'   => (string) $lawId,
        'source'        => $source,
        'title'         => cleanText($titre, 500),
        'summary'       => cleanText($summary, 5000),
        'full_text_url' => $legifranceUrl,
        'level'         => 'france',
        'chamber'       => $chamber,
        'vote_datetime' => $voteDate,
        'status'        => 'upcoming',
    ];
}

// ============================================================================
// MAIN FETCH FUNCTION
// ============================================================================

/**
 * Fetch and process laws from Légifrance API
 *
 * This is the main entry point called by fetch-bills.php.
 * Flow: OAuth token → Search recent laws → AI classify → Save to DB
 *
 * @return array Import statistics
 */
function fetchLegifrance(): array
{
    $startTime = microtime(true);
    $source = 'legifrance';

    logMessage("Starting Légifrance (DILA/PISTE) automated import...");
    echo "Starting Légifrance import (env: " . (defined('LEGIFRANCE_ENV') ? LEGIFRANCE_ENV : 'sandbox') . ")..." . PHP_EOL;

    $config = getSourceConfig($source);
    if (!$config || !$config['enabled']) {
        logMessage("Légifrance source is disabled", 'WARNING');
        return ['status' => 'skipped', 'fetched' => 0, 'new' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    $stats = [
        'fetched' => 0,
        'new'     => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors'  => [],
    ];

    try {
        // 1. Get OAuth token (cached)
        $token = getLegifranceToken();
        echo "  OAuth token obtained" . PHP_EOL;

        // 2. Search recent laws
        $daysBack = $config['search_days_back'] ?? 90;
        $laws = searchRecentLaws($token, $daysBack);

        if (empty($laws)) {
            logMessage("No laws found in Légifrance search", 'WARNING');
            echo "  No laws found" . PHP_EOL;

            $executionTime = microtime(true) - $startTime;
            logImportOperation($source, 'success', $stats, $executionTime);

            return array_merge($stats, ['status' => 'success']);
        }

        echo "  Found " . count($laws) . " laws" . PHP_EOL;

        // 3. Process each law
        $maxBills = $config['max_bills_per_run'] ?? MAX_BILLS_PER_SOURCE;
        $processed = 0;
        $rateLimitDelay = $config['rate_limit']['delay_seconds'] ?? 1;

        foreach ($laws as $law) {
            if ($processed >= $maxBills) {
                logMessage("Reached max bills limit ($maxBills), stopping");
                echo "  Reached limit of $maxBills bills" . PHP_EOL;
                break;
            }

            $stats['fetched']++;

            // Extract and validate bill data
            $billData = extractLegifanceBillData($law, $source);

            if (!$billData) {
                $stats['skipped']++;
                continue;
            }

            echo "  Processing: " . substr($billData['title'], 0, 70) . "..." . PHP_EOL;

            // 4. Classify with Mistral AI
            if (defined('ENABLE_AI_CLASSIFICATION') && ENABLE_AI_CLASSIFICATION) {
                logMessage("Classifying with AI: {$billData['title']}");
                echo "    Calling Mistral AI..." . PHP_EOL;

                $aiResult = classifyBillWithRetry(
                    $billData['title'],
                    $billData['summary'],
                    '' // Full text fetching can be added later
                );

                $billData['theme'] = $aiResult['theme'];
                $billData['ai_summary'] = $aiResult['abstract'] ?? null;
                $billData['ai_confidence'] = $aiResult['confidence'] ?? 0.0;
                $billData['ai_processed_at'] = date('Y-m-d H:i:s');
                $billData['mistral_ai_json_response'] = $aiResult['mistral_ai_json_response'] ?? null;

                if ($aiResult['error']) {
                    logMessage("AI classification had errors: {$aiResult['error']}", 'WARNING');
                    echo "    AI warning: {$aiResult['error']}" . PHP_EOL;
                } else {
                    echo "    AI classified as: {$aiResult['theme']}" . PHP_EOL;
                }
            }

            // 5. Save to production database
            $saveResult = saveBillToProduction($billData);

            if ($saveResult['success']) {
                if ($saveResult['action'] === 'inserted') {
                    $stats['new']++;
                    logMessage("New bill published: {$billData['title']}", 'INFO');
                    echo "    NEW bill saved" . PHP_EOL;
                } elseif ($saveResult['action'] === 'updated') {
                    $stats['updated']++;
                    logMessage("Bill updated: {$billData['title']}", 'INFO');
                    echo "    Bill updated" . PHP_EOL;
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['errors'][] = $saveResult['error'];
                logMessage("Error saving bill: " . $saveResult['error'], 'WARNING');
                echo "    ERROR: {$saveResult['error']}" . PHP_EOL;
            }

            $processed++;

            // Rate limiting between requests
            if ($processed < count($laws)) {
                sleep($rateLimitDelay);
            }
        }

        // Summary
        $executionTime = microtime(true) - $startTime;
        $status = empty($stats['errors']) ? 'success' : 'partial';

        logImportOperation($source, $status, $stats, $executionTime);
        logMessage("Légifrance import completed in " . round($executionTime, 2) . "s");
        logMessage("Stats: {$stats['new']} new, {$stats['updated']} updated, {$stats['skipped']} skipped");

        echo PHP_EOL;
        echo "  Import completed in " . round($executionTime, 2) . "s" . PHP_EOL;
        echo "  New: {$stats['new']}, Updated: {$stats['updated']}, Skipped: {$stats['skipped']}" . PHP_EOL;

        return array_merge($stats, ['status' => $status]);

    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        $stats['errors'][] = $e->getMessage();

        logImportOperation($source, 'failed', $stats, $executionTime);
        logMessage("Légifrance import failed: " . $e->getMessage(), 'ERROR');

        echo "  FATAL ERROR: " . $e->getMessage() . PHP_EOL;

        return array_merge($stats, ['status' => 'failed']);
    }
}

// ============================================================================
// CLI STANDALONE EXECUTION
// ============================================================================

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo PHP_EOL;
    echo str_repeat('=', 70) . PHP_EOL;
    echo "  LEGIFRANCE (DILA/PISTE) FETCHER - Constituant.fr" . PHP_EOL;
    echo "  Environment: " . (defined('LEGIFRANCE_ENV') ? LEGIFRANCE_ENV : 'sandbox') . PHP_EOL;
    echo str_repeat('=', 70) . PHP_EOL . PHP_EOL;

    $result = fetchLegifrance();

    echo PHP_EOL;
    echo str_repeat('=', 70) . PHP_EOL;
    echo "  RESULT: " . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    echo str_repeat('=', 70) . PHP_EOL;

    exit($result['status'] === 'success' ? 0 : 1);
}
