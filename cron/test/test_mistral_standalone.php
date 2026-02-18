#!/usr/bin/env php
<?php
/**
 * Mistral AI Classification Test - STANDALONE
 * 
 * Tests Mistral API with real French legislation to see EXACTLY what it returns.
 * No database, no dependencies - just pure API testing.
 * 
 * Usage: php test_mistral_standalone.php
 * 
 * REQUIREMENTS:
 * - PHP 8.0+
 * - curl extension
 * - Mistral API key (free tier works)
 * 
 * Get your free API key: https://console.mistral.ai/api-keys/
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================================
// CONFIGURATION
// ============================================================================

// TODO: Add your Mistral API key here
// Get it from: https://console.mistral.ai/api-keys/
const MISTRAL_API_KEY = 'MISTRAL_API_KEY not configured. Get it from https://console.mistral.ai/api-keys/';

// Mistral API endpoint
const MISTRAL_API_URL = 'https://api.mistral.ai/v1/chat/completions';

// Model to use (mistral-small is free and good enough)
const MISTRAL_MODEL = 'mistral-small-latest';

// Themes disponibles (from your existing system)
const THEMES = [
    'Affaires sociales',
    'Économie',
    'Environnement & Énergie',
    'Justice',
    'Numérique',
    'Santé',
    'Éducation',
    'Défense',
    'Culture',
    'Agriculture',
    'Transports',
    'Logement',
    'Institutions',
    'International',
    'Libertés publiques',
    'Transparence',
    'Sans catégorie',
];

// ============================================================================
// TEST BILLS - Real French legislation samples
// ============================================================================

$testBills = [
    [
        'title' => 'Référendum d\'Initiative Citoyenne Constituant',
        'summary' => 'Permet à 700 000 citoyens de déclencher un référendum sur n\'importe quel sujet, y compris la Constitution. Le résultat est contraignant pour le gouvernement.',
        'source' => 'Assemblée Nationale',
    ],
    [
        'title' => 'Retour de l\'âge légal de départ à la retraite à 62 ans',
        'summary' => 'Annule la réforme Macron de 2023 qui a fait passer l\'âge de départ de 62 à 64 ans. Rétablit l\'ancien système avec prise en compte de la pénibilité.',
        'source' => 'Assemblée Nationale',
    ],
    [
        'title' => 'Surveillance algorithmique dans l\'espace public',
        'summary' => 'Autorise l\'utilisation de caméras équipées de reconnaissance faciale et d\'analyse comportementale dans les transports publics, gares et grands événements.',
        'source' => 'Assemblée Nationale',
    ],
    [
        'title' => 'Mécanisme d\'ajustement carbone aux frontières',
        'summary' => 'Impose une taxe de 45€ par tonne de CO2 sur les importations d\'acier, ciment, aluminium et engrais en provenance de pays hors-UE avec politiques climatiques faibles.',
        'source' => 'European Parliament',
    ],
    [
        'title' => 'Revalorisation du SMIC à 15€ net de l\'heure',
        'summary' => 'Augmentation du salaire minimum de 11.65€ à 15€ net/heure, soit +28%. Concerne 3.5 millions de salariés. Le patronat est opposé, les syndicats mobilisés.',
        'source' => 'Assemblée Nationale',
    ],
];

// ============================================================================
// FUNCTIONS
// ============================================================================

/**
 * Call Mistral AI to classify a bill
 * 
 * @param string $title Bill title
 * @param string $summary Bill summary
 * @return array ['success' => bool, 'theme' => string, 'summary' => string, 'confidence' => float, 'error' => string|null]
 */
function classifyWithMistral(string $title, string $summary): array
{
    if (MISTRAL_API_KEY === 'YOUR_MISTRAL_API_KEY_HERE') {
        return [
            'success' => false,
            'theme' => null,
            'summary' => null,
            'confidence' => null,
            'error' => 'MISTRAL_API_KEY not configured. Get it from https://console.mistral.ai/api-keys/',
            'raw_response' => null,
        ];
    }

    $themesStr = implode(', ', THEMES);

    $prompt = <<<PROMPT
Tu es un assistant qui analyse les projets de loi français et européens.

Ta tâche est de :
1. Classer ce projet de loi dans UNE des catégories suivantes : {$themesStr}
2. Créer un résumé en français simple et direct pour les citoyens (2-3 phrases maximum)

Le résumé doit :
- Être compréhensible par tout citoyen
- Expliquer l'impact concret sur la vie des gens
- Utiliser un ton direct et factuel (pas condescendant)
- Éviter le jargon juridique
- Max 280 caractères, idéal pour un post Twitter/X

Voici le projet de loi à analyser :

TITRE : {$title}

RÉSUMÉ TECHNIQUE : {$summary}

Réponds UNIQUEMENT au format JSON suivant (sans markdown, sans backticks) :
{
  "theme": "le thème choisi parmi la liste",
  "summary": "ton résumé citoyen en 2-3 phrases",
  "confidence": 0.95
}
PROMPT;

    $data = [
        'model' => MISTRAL_MODEL,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.3, // Low temperature for consistent classification
        'max_tokens' => 500,
    ];

    $ch = curl_init(MISTRAL_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MISTRAL_API_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'theme' => null,
            'summary' => null,
            'confidence' => null,
            'error' => "CURL error: $error",
            'raw_response' => null,
        ];
    }

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'theme' => null,
            'summary' => null,
            'confidence' => null,
            'error' => "HTTP $httpCode: $response",
            'raw_response' => $response,
        ];
    }

    $decoded = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'theme' => null,
            'summary' => null,
            'confidence' => null,
            'error' => 'JSON decode error: ' . json_last_error_msg(),
            'raw_response' => $response,
        ];
    }

    // Extract content from Mistral response
    $content = $decoded['choices'][0]['message']['content'] ?? null;
    
    if (!$content) {
        return [
            'success' => false,
            'theme' => null,
            'summary' => null,
            'confidence' => null,
            'error' => 'No content in response',
            'raw_response' => $response,
        ];
    }

    // Parse the JSON content (Mistral might wrap it in markdown)
    $content = trim($content);
    $content = preg_replace('/^```json\s*/', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    
    $result = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'theme' => null,
            'summary' => null,
            'confidence' => null,
            'error' => 'Failed to parse Mistral JSON: ' . json_last_error_msg(),
            'raw_response' => $content,
        ];
    }

    return [
        'success' => true,
        'theme' => $result['theme'] ?? 'Sans catégorie',
        'summary' => $result['summary'] ?? '',
        'confidence' => $result['confidence'] ?? 0.0,
        'error' => null,
        'raw_response' => $response,
    ];
}

/**
 * Pretty print result
 */
function printResult(array $bill, array $result, int $index, int $total): void
{
    echo str_repeat('─', 80) . PHP_EOL;
    echo "[$index/$total] {$bill['title']}" . PHP_EOL;
    echo str_repeat('─', 80) . PHP_EOL;
    
    echo "📄 Original Summary:" . PHP_EOL;
    echo "   " . wordwrap($bill['summary'], 75, PHP_EOL . "   ") . PHP_EOL;
    echo PHP_EOL;

    if ($result['success']) {
        echo "✅ MISTRAL CLASSIFICATION:" . PHP_EOL;
        echo "   🏷️  Theme: {$result['theme']}" . PHP_EOL;
        echo "   📊 Confidence: " . ($result['confidence'] * 100) . "%" . PHP_EOL;
        echo PHP_EOL;
        echo "   🎯 Citizen Summary:" . PHP_EOL;
        echo "   " . wordwrap($result['summary'], 75, PHP_EOL . "   ") . PHP_EOL;
    } else {
        echo "❌ ERROR: {$result['error']}" . PHP_EOL;
        
        if ($result['raw_response']) {
            echo PHP_EOL;
            echo "   Raw response (first 300 chars):" . PHP_EOL;
            echo "   " . substr($result['raw_response'], 0, 300) . "..." . PHP_EOL;
        }
    }
    
    echo PHP_EOL;
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

echo str_repeat('═', 80) . PHP_EOL;
echo "🤖 MISTRAL AI CLASSIFICATION TEST - STANDALONE" . PHP_EOL;
echo str_repeat('═', 80) . PHP_EOL;
echo PHP_EOL;

echo "Configuration:" . PHP_EOL;
echo "  API Endpoint: " . MISTRAL_API_URL . PHP_EOL;
echo "  Model: " . MISTRAL_MODEL . PHP_EOL;
echo "  Test Bills: " . count($testBills) . PHP_EOL;
echo "  Available Themes: " . count(THEMES) . PHP_EOL;
echo PHP_EOL;

// Check API key
if (MISTRAL_API_KEY === 'YOUR_MISTRAL_API_KEY_HERE') {
    echo "⚠️  WARNING: Mistral API key not configured!" . PHP_EOL;
    echo PHP_EOL;
    echo "To get your FREE API key:" . PHP_EOL;
    echo "  1. Visit: https://console.mistral.ai/api-keys/" . PHP_EOL;
    echo "  2. Sign up (it's free)" . PHP_EOL;
    echo "  3. Create an API key" . PHP_EOL;
    echo "  4. Edit this file and set MISTRAL_API_KEY" . PHP_EOL;
    echo PHP_EOL;
    echo "Press ENTER to continue anyway (tests will fail)..." . PHP_EOL;
    fgets(STDIN);
}

echo str_repeat('═', 80) . PHP_EOL;
echo "TESTING MISTRAL AI WITH REAL FRENCH LEGISLATION" . PHP_EOL;
echo str_repeat('═', 80) . PHP_EOL;
echo PHP_EOL;

$stats = [
    'total' => count($testBills),
    'success' => 0,
    'failed' => 0,
];

$results = [];

foreach ($testBills as $index => $bill) {
    $billNum = $index + 1;
    
    echo "Processing bill $billNum/" . $stats['total'] . "..." . PHP_EOL;
    
    $result = classifyWithMistral($bill['title'], $bill['summary']);
    
    $results[] = [
        'bill' => $bill,
        'result' => $result,
    ];
    
    if ($result['success']) {
        $stats['success']++;
    } else {
        $stats['failed']++;
    }
    
    printResult($bill, $result, $billNum, $stats['total']);
    
    // Rate limiting - be nice to Mistral API
    if ($billNum < $stats['total']) {
        echo "⏳ Waiting 2 seconds (rate limiting)..." . PHP_EOL . PHP_EOL;
        sleep(2);
    }
}

// ============================================================================
// SUMMARY
// ============================================================================

echo str_repeat('═', 80) . PHP_EOL;
echo "📊 TEST SUMMARY" . PHP_EOL;
echo str_repeat('═', 80) . PHP_EOL;
echo PHP_EOL;

echo "Total Bills Tested: {$stats['total']}" . PHP_EOL;
echo "✅ Successful: {$stats['success']}" . PHP_EOL;
echo "❌ Failed: {$stats['failed']}" . PHP_EOL;
echo PHP_EOL;

if ($stats['success'] > 0) {
    echo "🎯 THEME DISTRIBUTION:" . PHP_EOL;
    $themeCount = [];
    foreach ($results as $item) {
        if ($item['result']['success']) {
            $theme = $item['result']['theme'];
            $themeCount[$theme] = ($themeCount[$theme] ?? 0) + 1;
        }
    }
    
    arsort($themeCount);
    foreach ($themeCount as $theme => $count) {
        echo "  $theme: $count" . PHP_EOL;
    }
    echo PHP_EOL;
    
    echo "📝 SAMPLE SQL FOR DATABASE:" . PHP_EOL;
    echo str_repeat('─', 80) . PHP_EOL;
    
    foreach ($results as $index => $item) {
        if (!$item['result']['success']) continue;
        
        $bill = $item['bill'];
        $result = $item['result'];
        
        $id = 'fr-' . strtolower(str_replace(' ', '-', substr($bill['title'], 0, 30))) . '-2026';
        $id = preg_replace('/[^a-z0-9-]/', '', $id);
        
        echo "INSERT INTO bills (id, title, summary, ai_summary, theme, level, chamber, vote_datetime, ai_confidence, ai_processed_at) VALUES" . PHP_EOL;
        echo "(" . PHP_EOL;
        echo "    " . var_export($id, true) . "," . PHP_EOL;
        echo "    " . var_export($bill['title'], true) . "," . PHP_EOL;
        echo "    " . var_export($bill['summary'], true) . "," . PHP_EOL;
        echo "    " . var_export($result['summary'], true) . "," . PHP_EOL;
        echo "    " . var_export($result['theme'], true) . "," . PHP_EOL;
        echo "    'france'," . PHP_EOL;
        echo "    'Assemblée Nationale'," . PHP_EOL;
        echo "    '2026-0" . ($index + 1) . "-15 15:00:00'," . PHP_EOL;
        echo "    " . $result['confidence'] . "," . PHP_EOL;
        echo "    CURRENT_TIMESTAMP" . PHP_EOL;
        echo ");" . PHP_EOL;
        echo PHP_EOL;
    }
}

echo str_repeat('═', 80) . PHP_EOL;
echo "✅ TEST COMPLETE" . PHP_EOL;
echo PHP_EOL;

if ($stats['failed'] > 0) {
    echo "⚠️  Some tests failed. Check errors above." . PHP_EOL;
    exit(1);
} else {
    echo "🎉 All tests passed! Mistral AI is working correctly." . PHP_EOL;
    exit(0);
}