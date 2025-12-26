<?php
/**
 * NosDéputés.fr Fetcher - Fully Automated
 *
 * Fetches bills → Classifies with AI → Publishes automatically
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';

/**
 * Fetch and process bills from NosDéputés.fr
 *
 * @return array Import statistics
 */
function fetchNosDePutes(): array
{
    $startTime = microtime(true);
    $source = 'nosdeputes';
    
    logMessage("Starting NosDéputés.fr automated import...");
    
    $config = getSourceConfig($source);
    if (!$config || !$config['enabled']) {
        logMessage("NosDéputés.fr source is disabled", 'WARNING');
        return ['status' => 'skipped', 'new' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }
    
    $stats = [
        'fetched' => 0,
        'new' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];
    
    try {
        // Fetch dossiers from API
        $dossiersUrl = $config['base_url'] . $config['endpoints']['dossiers'];
        logMessage("Fetching from: $dossiersUrl");
        
        $result = fetchUrl($dossiersUrl, [], 0);
        
        if (!$result['success']) {
            throw new Exception($result['error']);
        }
        
        // Parse JSON
        $parsed = parseJson($result['data']);
        if (!$parsed['success']) {
            throw new Exception($parsed['error']);
        }
        
        // Extract dossiers (API structure: sections array)
        $sections = $parsed['data']['sections'] ?? [];
        logMessage("Found " . count($sections) . " dossier sections");
        
        $dossiers = [];
        foreach ($sections as $sectionWrapper) {
            if (isset($sectionWrapper['section'])) {
                $dossiers[] = $sectionWrapper['section'];
            }
        }
        
        logMessage("Extracted " . count($dossiers) . " dossiers from sections");
        
        // Process each dossier
        $processed = 0;
        foreach ($dossiers as $dossier) {
            if ($processed >= MAX_BILLS_PER_SOURCE) {
                logMessage("Reached max bills limit, stopping");
                break;
            }
            
            $stats['fetched']++;
            
            // Extract and validate bill data
            $billData = extractNosDePutesBillData($dossier, $source);
            
            if (!$billData) {
                $stats['skipped']++;
                continue;
            }
            
            // Check date validity
            if (!isValidBillDate($billData['vote_datetime'])) {
                logMessage("Skipping bill with invalid date: {$billData['title']}", 'INFO');
                $stats['skipped']++;
                continue;
            }
            
            // Classify with AI
            logMessage("Classifying with AI: {$billData['title']}");
            $aiResult = classifyBillWithRetry(
                $billData['title'],
                $billData['summary'] ?? '',
                '' // No full text for NosDéputés
            );
            
            // Add AI results to bill data
            $billData['theme'] = $aiResult['theme'];
            $billData['ai_summary'] = $aiResult['abstract']; // Short hook for card preview
            $billData['ai_confidence'] = $aiResult['confidence'];
            $billData['ai_processed_at'] = date('Y-m-d H:i:s');
            $billData['mistral_ai_json_response'] = $aiResult['mistral_ai_json_response'] ?? null;
            
            if ($aiResult['error']) {
                logMessage("AI classification had errors: {$aiResult['error']}", 'WARNING');
            }
            
            // Save directly to production
            $saveResult = saveBillToProduction($billData);
            
            if ($saveResult['success']) {
                if ($saveResult['action'] === 'inserted') {
                    $stats['new']++;
                    logMessage("✓ New bill published: {$billData['title']}", 'INFO');
                } elseif ($saveResult['action'] === 'updated') {
                    $stats['updated']++;
                    logMessage("↻ Bill updated: {$billData['title']}", 'INFO');
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['errors'][] = $saveResult['error'];
                logMessage("✗ Error saving bill: " . $saveResult['error'], 'WARNING');
            }
            
            $processed++;
            
            // Rate limiting
            if ($processed < count($dossiers)) {
                sleep(RATE_LIMIT_DELAY);
            }
        }
        
        $executionTime = microtime(true) - $startTime;
        $status = empty($stats['errors']) ? 'success' : 'partial';
        
        logImportOperation($source, $status, $stats, $executionTime);
        logMessage("NosDéputés.fr import completed in " . round($executionTime, 2) . "s");
        logMessage("Stats: {$stats['new']} new, {$stats['updated']} updated, {$stats['skipped']} skipped");
        
        return array_merge($stats, ['status' => $status]);
        
    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        $stats['errors'][] = $e->getMessage();
        
        logImportOperation($source, 'failed', $stats, $executionTime);
        logMessage("NosDéputés.fr import failed: " . $e->getMessage(), 'ERROR');
        
        return array_merge($stats, ['status' => 'failed']);
    }
}

/**
 * Extract bill data from NosDéputés dossier
 *
 * @param array $dossier Dossier data
 * @param string $source Source name
 * @return array|null Bill data or null if invalid
 */
function extractNosDePutesBillData(array $dossier, string $source): ?array
{
    $titre = $dossier['titre'] ?? null;
    
    if (empty($titre)) {
        logMessage("Skipping dossier without title", 'WARNING');
        return null;
    }
    
    // Get external ID
    $externalId = $dossier['id_dossier_institution'] ?? $dossier['id'] ?? null;
    
    if (empty($externalId)) {
        logMessage("Skipping dossier without ID", 'WARNING');
        return null;
    }
    
    // Generate unique bill ID
    $billId = generateBillId($source, $externalId, $titre);
    
    // Get URLs
    $url = $dossier['url_nosdeputes'] ?? $dossier['url_institution'] ?? null;
    $fullTextUrl = $dossier['url_institution'] ?? $url;

    // Check if bill is still active/ongoing
    // Skip if it has recent activity in the far past (likely completed)
    $maxDate = null;
    if (isset($dossier['max_date'])) {
        $maxDate = parseDate($dossier['max_date']);
    }

    // If max_date is more than 90 days in the past, bill is likely completed - skip it
    if ($maxDate) {
        $daysSinceActivity = (time() - strtotime($maxDate)) / (60 * 60 * 24);
        if ($daysSinceActivity > 90) {
            logMessage("Skipping inactive dossier (no activity for {$daysSinceActivity} days): $titre", 'INFO');
            return null;
        }
    }

    // For ongoing/active bills, assign an estimated future vote date
    // Use a range of 30-90 days to spread them out
    $daysAhead = rand(30, 90);
    $voteDate = date('Y-m-d H:i:s', strtotime("+{$daysAhead} days"));
    
    // Create summary from available data
    $summary = '';

    // Try to get a meaningful description
    if (!empty($dossier['objet'])) {
        $summary = $dossier['objet'];
    } elseif (!empty($dossier['type'])) {
        $summary = "Dossier de type : " . $dossier['type'];
        if (isset($dossier['nb_interventions'])) {
            $summary .= " ({$dossier['nb_interventions']} interventions)";
        }
    } else {
        $summary = "Dossier législatif examiné à l'Assemblée nationale";
        if (isset($dossier['nb_interventions'])) {
            $summary .= " ({$dossier['nb_interventions']} interventions)";
        }
    }
    
    // Determine chamber
    $chamber = 'Assemblée Nationale';
    if (!empty($url) && stripos($url, 'senat.fr') !== false) {
        $chamber = 'Sénat';
    }
    
    return [
        'id' => $billId,
        'external_id' => (string)$externalId,
        'source' => $source,
        'title' => cleanText($titre, 500),
        'summary' => cleanText($summary, 5000),
        'full_text_url' => $fullTextUrl,
        'level' => 'france',
        'chamber' => $chamber,
        'vote_datetime' => $voteDate,
        'status' => 'upcoming',
    ];
}

// If run directly, execute fetcher
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $result = fetchNosDePutes();
    exit($result['status'] === 'success' ? 0 : 1);
}
