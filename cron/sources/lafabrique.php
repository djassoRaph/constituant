<?php
/**
 * La Fabrique de la Loi - FIXED Importer
 * 
 * This version:
 * - Accepts bills regardless of vote date
 * - Has better error reporting
 * - Logs what it's doing
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

require_once __DIR__ . '/../lib/fetcher-base.php';

// Override the date validation to accept everything for now
function isValidBillDateOverride(?string $voteDate): bool {
    return true; // Accept all dates during testing
}

function fetchLaFabrique(): array
{
    $startTime = microtime(true);
    $source = 'lafabrique';
    
    echo "\n🔍 Starting La Fabrique import (FIXED VERSION)...\n";
    logMessage("=== La Fabrique FIXED import started ===");
    
    $stats = [
        'fetched' => 0,
        'new' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];
    
    try {
        // Fetch CSV
        $csvUrl = 'https://www.lafabriquedelaloi.fr/api/dossiers.csv';
        echo "📥 Fetching from: $csvUrl\n";
        
        $result = fetchUrl($csvUrl, [CURLOPT_HTTPHEADER => ['Accept: text/csv']], 0);
        
        if (!$result['success']) {
            throw new Exception("Failed to fetch CSV: " . $result['error']);
        }
        
        echo "✓ CSV downloaded (" . strlen($result['data']) . " bytes)\n";
        
        // Parse CSV
        $lines = str_getcsv($result['data'], "\n", '"', '\\');
        
        if (empty($lines)) {
            throw new Exception("CSV is empty");
        }
        
        $header = str_getcsv(array_shift($lines), ';', '"', '\\');
        echo "✓ Found " . count($header) . " columns\n";
        echo "  Columns: " . implode(', ', array_slice($header, 0, 5)) . "...\n";
        
        $dossiers = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $values = str_getcsv($line, ';', '"', '\\');
            if (count($values) === count($header)) {
                $dossiers[] = array_combine($header, $values);
            }
        }
        
        echo "✓ Parsed " . count($dossiers) . " dossiers\n\n";
        
        // Process bills (limit to 20 for testing)
        $maxBills = 20;
        $processed = 0;
        
        foreach ($dossiers as $dossier) {
            if ($processed >= $maxBills) {
                echo "⏸️  Reached limit of $maxBills bills\n";
                break;
            }
            
            $stats['fetched']++;
            
            // Extract bill data
            $titre = $dossier['Titre'] ?? null;
            
            if (empty($titre)) {
                echo "⊘ Skipping: no title\n";
                $stats['skipped']++;
                continue;
            }
            
            // Get other fields
            $url = $dossier['URL du dossier'] ?? null;
            $dateInitiale = $dossier['Date initiale'] ?? null;
            $etatDossier = $dossier['État du dossier'] ?? '';

            // ONLY IMPORT ONGOING BILLS (bills currently in discussion)
            // Skip completed bills (adopted, rejected, or promulgated)
            if (stripos($etatDossier, 'adopté') !== false ||
                stripos($etatDossier, 'rejeté') !== false ||
                stripos($etatDossier, 'promulgué') !== false ||
                stripos($etatDossier, 'abandon') !== false) {
                $stats['skipped']++;
                continue;
            }

            // Only accept bills that are "en cours" (ongoing)
            if (stripos($etatDossier, 'en cours') === false &&
                stripos($etatDossier, 'dépos') === false &&
                !empty($etatDossier)) {
                $stats['skipped']++;
                continue;
            }

            // For ongoing bills, assign an estimated future vote date
            // Use a range of 30-90 days to spread them out
            $daysAhead = rand(30,90);
            $voteDate = date('Y-m-d H:i:s', strtotime("+{$daysAhead} days"));
            
            // Generate unique ID
            $externalId = $dossier['id'] ?? md5($titre);
            $billId = "lafabrique-" . preg_replace('/[^a-z0-9-]/i', '-', strtolower(substr($externalId, 0, 40))) . "-" . date('Y');
            
            // Create a proper summary from available data
            $shortTitle = $dossier['short_title'] ?? '';
            $themes = $dossier['Thèmes'] ?? '';

            // Build summary: use short_title if different from main title, otherwise use themes
            $summary = '';
            if (!empty($shortTitle) && $shortTitle !== $titre) {
                $summary = $shortTitle;
            } elseif (!empty($themes)) {
                $summary = "Dossier législatif concernant : " . str_replace(',', ', ', $themes);
            } else {
                $summary = "Dossier législatif en cours d'examen à l'Assemblée nationale";
            }

            // Prepare bill data
            $billData = [
                'id' => $billId,
                'external_id' => (string)$externalId,
                'source' => $source,
                'title' => cleanText($titre, 500),
                'summary' => cleanText($summary, 5000),
                'full_text_url' => $url,
                'level' => 'france',
                'chamber' => 'Assemblée Nationale',
                'vote_datetime' => $voteDate,
                'status' => 'upcoming',
                'theme' => 'Sans catégorie', // Will be set by AI
                'ai_summary' => null,
                'mistral_ai_json_response' => null,
                
            ];
            
            echo "📋 Processing: " . substr($titre, 0, 60) . "...\n";
            
            // Classify with AI (if enabled)
            if (defined('ENABLE_AI_CLASSIFICATION') && ENABLE_AI_CLASSIFICATION) {
                echo "  🤖 Calling Mistral AI...\n";
                $aiResult = classifyBillWithRetry(
                    $billData['title'],
                    $billData['summary'],
                    ''
                );
                
                if ($aiResult['error'] === null) {
                    $billData['theme'] = $aiResult['theme'];
                    $billData['ai_summary'] = $aiResult['abstract']; // Short hook for card preview
                    $billData['ai_confidence'] = 0.95;
                    $billData['ai_processed_at'] = date('Y-m-d H:i:s');
                    $billData['mistral_ai_json_response'] = $aiResult['mistral_ai_json_response'] ?? null;
                    echo "  ✓ AI: {$aiResult['theme']}\n";
                } else {
                    echo "  ⚠️  AI failed: {$aiResult['error']}\n";
                }
            }
            
            // Save to database
            $saveResult = saveBillToProduction($billData);
            
            if ($saveResult['success']) {
                if ($saveResult['action'] === 'inserted') {
                    $stats['new']++;
                    echo "  ✅ NEW bill saved\n";
                } elseif ($saveResult['action'] === 'updated') {
                    $stats['updated']++;
                    echo "  🔄 Bill updated\n";
                }
            } else {
                $stats['errors'][] = $saveResult['error'];
                echo "  ❌ Error: {$saveResult['error']}\n";
            }
            
            $processed++;
            echo "\n";
            
            // Rate limiting
            sleep(1);
        }
        
        // Summary
        $executionTime = microtime(true) - $startTime;
        
        echo str_repeat("=", 60) . "\n";
        echo "📊 SUMMARY:\n";
        echo "  Fetched: {$stats['fetched']}\n";
        echo "  New: {$stats['new']}\n";
        echo "  Updated: {$stats['updated']}\n";
        echo "  Skipped: {$stats['skipped']}\n";
        echo "  Errors: " . count($stats['errors']) . "\n";
        echo "  Time: " . round($executionTime, 2) . "s\n";
        echo str_repeat("=", 60) . "\n";
        
        logImportOperation($source, empty($stats['errors']) ? 'success' : 'partial', $stats, $executionTime);
        
        return array_merge($stats, ['status' => empty($stats['errors']) ? 'success' : 'partial']);
        
    } catch (Exception $e) {
        $executionTime = microtime(true) - $startTime;
        echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
        
        logImportOperation($source, 'failed', $stats, $executionTime);
        
        return array_merge($stats, ['status' => 'failed', 'errors' => [$e->getMessage()]]);
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║  LA FABRIQUE DE LA LOI - FIXED IMPORTER (TEST MODE)      ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    
    $result = fetchLaFabrique();
    
    exit($result['status'] === 'success' ? 0 : 1);
}