<?php
/**
 * Mistral AI Integration for Legislative Bill Classification
 *
 * This module provides AI-powered classification and summarization
 * of legislative bills using the Mistral AI API.
 *
 * @package Constituant
 * @version 1.0.0
 */

// Load API keys from secure config file
require_once __DIR__ . '/config/api-keys.php';

// Predefined legislative categories
define('LEGISLATIVE_CATEGORIES', [
    'Économie & Finances',
    'Travail & Emploi',
    'Santé',
    'Éducation',
    'Justice',
    'Sécurité & Défense',
    'Environnement & Énergie',
    'Transports & Infrastructures',
    'Agriculture',
    'Culture & Communication',
    'Affaires sociales',
    'Numérique',
    'Affaires européennes',
    'Institutions',
    'Sans catégorie'
]);

/**
 * Classify a legislative bill using Mistral AI
 *
 * This function sends bill information to Mistral AI for automatic
 * classification and plain-language summarization.
 *
 * @param string $title The bill title
 * @param string $description The bill description/summary
 * @param string $fullText The complete legislative text
 * @return array Associative array with 'theme', 'summary', 'json_response', and 'error' keys
 */
function classifyBillWithAI($title, $description, $fullText) {
    // Validate inputs
    if (empty($title) || empty($description)) {
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => 'Title and description are required'
        ];
    }

    // Build the AI prompt
    $categoriesList = implode(', ', LEGISLATIVE_CATEGORIES);

    $prompt = "Classify this French legislation code, provide structured information and a brief summary.

Categories: {$categoriesList}

Title: {$title}
Description: {$description}
Full Text: " . substr($fullText, 0, 3000) . "

Return ONLY valid JSON with this EXACT structure (no markdown, no backticks):
{
  \"theme\": \"category name from the list above\",
  \"abstract\": \"Ultra-short hook (max 20 words)\",
  \"summary\": \"Full explanation (2-3 sentences)\",
  \"pour\": \"One or two key arguments IN FAVOR, simple.\",
  \"contre\": \"One or two key arguments AGAINST, simple.\",
  \"concerne\": [\"Specific group 1\", \"Specific group 2\", \"Specific group 3\"]
}

CRITICAL DISTINCTIONS:
- 'abstract' = Ultra-short hook (20 words max) - what users see when scrolling
- 'summary' = Full explanation (2-3 sentences) - what users see when they expand details
- 'abstract' and 'summary' MUST be DIFFERENT texts with different information depth

RULES:
- 'theme' must be ONE of the categories listed above, with EXACT spelling
- 'abstract' is SHORT and catchy (e.g., \"Réduit les cotisations sociales des entreprises pendant 6 mois.\")
- 'summary' is DETAILED and informative (e.g., \"Cette loi propose une réduction temporaire des cotisations sociales pour les entreprises. Elle vise à soutenir leur trésorerie pendant une période difficile. Les PME et startups sont particulièrement concernées.\")
- 'pour' and 'contre' are ONE sentence each, simple language, no jargon.
For 'concerne': List 3-5 SPECIFIC groups affected (NOT 'tous les citoyens'), think jobs, or demographic. 
Examples: 'Les propriétaires d'animaux domestiques', 'Les PME de moins de 50 salariés', 'Les étudiants en médecine'.";

    // Prepare API request payload
    $payload = [
        'model' => MISTRAL_MODEL,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 500
    ];

    // Initialize curl
    $ch = curl_init(MISTRAL_API_ENDPOINT);

    if ($ch === false) {
        $errorMsg = 'Failed to initialize curl';
        error_log("Mistral AI Error: {$errorMsg}");
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => $errorMsg
        ];
    }

    // Set curl options
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => MISTRAL_TIMEOUT,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MISTRAL_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Handle curl errors
    if ($response === false) {
        $errorMsg = "Curl error: {$curlError}";
        error_log("Mistral AI Error: {$errorMsg}");
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => $errorMsg
        ];
    }

    // Handle HTTP errors
    if ($httpCode !== 200) {
        $errorMsg = "HTTP {$httpCode}: " . substr($response, 0, 200);
        error_log("Mistral AI Error: {$errorMsg}");
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => $errorMsg
        ];
    }

    // Parse API response
    $responseData = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = 'Failed to parse API response: ' . json_last_error_msg();
        error_log("Mistral AI Error: {$errorMsg}");
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => $errorMsg
        ];
    }

    // Extract AI response content
    if (!isset($responseData['choices'][0]['message']['content'])) {
        $errorMsg = 'Invalid API response structure';
        error_log("Mistral AI Error: {$errorMsg}");
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => $errorMsg
        ];
    }

    $aiContent = $responseData['choices'][0]['message']['content'];

    // Strip markdown code fences (```json ... ``` or ``` ... ```)
    $aiContent = preg_replace('/^```(?:json)?\s*\n/m', '', $aiContent);
    $aiContent = preg_replace('/\n```\s*$/m', '', $aiContent);
    $aiContent = trim($aiContent);

    // Parse AI-generated JSON
    $aiResult = json_decode($aiContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = 'Failed to parse AI JSON response: ' . json_last_error_msg();
        error_log("Mistral AI Error: {$errorMsg} | Content: {$aiContent}");
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => $errorMsg
        ];
    }

    // Validate response structure - check for required fields
    if (!isset($aiResult['theme']) || !isset($aiResult['summary'])) {
        $errorMsg = 'AI response missing required fields (theme or summary)';
        error_log("Mistral AI Error: {$errorMsg}");
        return [
            'theme' => 'Sans catégorie',
            'summary' => null,
            'json_response' => null,
            'error' => $errorMsg
        ];
    }

    // Validate theme is in allowed categories
    $theme = $aiResult['theme'];
    if (!in_array($theme, LEGISLATIVE_CATEGORIES)) {
        error_log("Mistral AI Warning: Invalid theme '{$theme}', defaulting to 'Sans catégorie'");
        $theme = 'Sans catégorie';
    }

    // Prepare the full JSON response for storage
    $jsonResponse = json_encode([
        'summary' => trim($aiResult['summary'] ?? ''),
        'pour' => trim($aiResult['pour'] ?? ''),
        'contre' => trim($aiResult['contre'] ?? ''),
        'concerne' => $aiResult['concerne'] ?? []
    ], JSON_UNESCAPED_UNICODE);

    // Return successful classification with all data
    return [
        'theme' => $theme,
        'abstract' => trim($aiResult['abstract']),
        'summary' => trim($aiResult['summary']),
        'json_response' => $jsonResponse,
        'error' => null
    ];
}

/**
 * Batch classify multiple bills
 *
 * @param array $bills Array of bills, each with 'title', 'description', 'fullText' keys
 * @return array Array of classification results
 */
function classifyBillsBatch($bills) {
    $results = [];

    foreach ($bills as $index => $bill) {
        $result = classifyBillWithAI(
            $bill['title'] ?? '',
            $bill['description'] ?? '',
            $bill['fullText'] ?? ''
        );

        $results[$index] = $result;

        // Add small delay to avoid rate limiting
        if ($index < count($bills) - 1) {
            usleep(100000); // 100ms delay
        }
    }

    return $results;
}
