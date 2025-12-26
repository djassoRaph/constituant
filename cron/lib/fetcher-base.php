<?php
/**
 * Fetcher Base Library - Simplified for Full Automation
 *
 * Shared functions for automated bill imports
 * No admin approval - bills go directly to production
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

// Load configuration
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/api-keys.php';
require_once __DIR__ . '/mistral_ai.php';

// ============================================================================
// CONFIGURATION
// ============================================================================

// Import settings
define('MAX_BILLS_PER_SOURCE', 50);  // Maximum bills to fetch per source per run
define('MIN_DAYS_AHEAD', 1);         // Only import bills with votes at least 1 day in future
define('MAX_DAYS_AHEAD', 120);        // Only import bills with votes within 120 days
define('RATE_LIMIT_DELAY', 1);       // Seconds to wait between API calls

// Mistral AI settings
define('ENABLE_AI_CLASSIFICATION', true);  // Enable/disable AI classification
define('AI_TIMEOUT', 30);            // Seconds to wait for AI response
define('AI_MAX_RETRIES', 2);         // Number of retries for AI classification

// Themes (must match Mistral AI categories)
define('VALID_THEMES', [
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

// ============================================================================
// CORE FUNCTIONS
// ============================================================================

/**
 * Save bill directly to production (no pending approval)
 *
 * @param array $billData Bill data
 * @return array ['success' => bool, 'action' => string, 'error' => string|null]
 */
function saveBillToProduction(array $billData): array
{
    try {
        $pdo = getDbConnection();
        
        // Validate required fields
        $required = ['id', 'title', 'level', 'chamber', 'vote_datetime', 'source', 'external_id'];
        foreach ($required as $field) {
            if (empty($billData[$field])) {
                return [
                    'success' => false,
                    'action' => 'validation_failed',
                    'error' => "Missing required field: $field"
                ];
            }
        }
        
        // Check if bill already exists
        $checkQuery = "SELECT id, updated_at FROM bills WHERE id = ?";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([$billData['id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing bill
            $updateQuery = "
                UPDATE bills SET
                    title = ?,
                    summary = ?,
                    ai_summary = ?,
                    json_response = ?,
                    full_text_url = ?,
                    theme = ?,
                    ai_confidence = ?,
                    level = ?,
                    chamber = ?,
                    vote_datetime = ?,
                    status = ?,
                    ai_processed_at = ?
                WHERE id = ?
            ";
            
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([
                $billData['title'],
                $billData['summary'] ?? null,
                $billData['ai_summary'] ?? null,
                $billData['full_text_url'] ?? null,
                $billData['theme'] ?? 'Sans catégorie',
                $billData['ai_confidence'] ?? null,
                $billData['level'],
                $billData['chamber'],
                $billData['vote_datetime'],
                $billData['status'] ?? 'upcoming',
                $billData['ai_processed_at'] ?? null,
                $billData['id']
            ]);
            
            return [
                'success' => true,
                'action' => 'updated',
                'error' => null
            ];
        }
        
        // Insert new bill
        $insertQuery = "
            INSERT INTO bills (
                id, title, summary, ai_summary, json_response,full_text_url,
                theme, ai_confidence, level, chamber, vote_datetime,
                status, source, external_id, ai_processed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute([
            $billData['id'],
            $billData['title'],
            $billData['summary'] ?? null,
            $billData['ai_summary'] ?? null,
            $billData['full_text_url'] ?? null,
            $billData['theme'] ?? 'Sans catégorie',
            $billData['ai_confidence'] ?? null,
            $billData['level'],
            $billData['chamber'],
            $billData['vote_datetime'],
            $billData['status'] ?? 'upcoming',
            $billData['source'],
            $billData['external_id'],
            $billData['ai_processed_at'] ?? null
        ]);
        
        return [
            'success' => true,
            'action' => 'inserted',
            'error' => null
        ];
        
    } catch (PDOException $e) {
        logMessage("Database error in saveBillToProduction: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'action' => 'database_error',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Classify bill with Mistral AI (with retry logic)
 *
 * @param string $title Bill title
 * @param string $summary Bill summary
 * @param string $fullText Full bill text (optional)
 * @return array ['theme' => string, 'summary' => string, 'confidence' => float, 'error' => string|null]
 */
function classifyBillWithRetry(string $title, string $summary, string $fullText = ''): array
{
    $attempts = 0;
    $lastError = null;

    while ($attempts < AI_MAX_RETRIES) {
        $attempts++;

        // Call Mistral AI (from mistral_ai.php)
        $result = classifyBillWithAI($title, $summary, $fullText);

        if ($result['error'] === null) {
            // Success!
            return [
                'theme' => $result['theme'],
                'summary' => $result['summary'],
                'json_response' => $result['json_response'],
                'confidence' => 0.95, // Mistral typically returns high confidence
                'error' => null
            ];
        }

        $lastError = $result['error'];

        // Wait before retry
        if ($attempts < AI_MAX_RETRIES) {
            sleep(2);
        }
    }

    // All retries failed
    logMessage("AI classification failed after $attempts attempts: $lastError", 'ERROR');

    return [
        'theme' => 'Sans catégorie',
        'summary' => substr($summary, 0, 280), // Use truncated original summary
        'confidence' => 0.0,
        'error' => $lastError
    ];
}

/**
 * Fetch URL with error handling
 *
 * @param string $url URL to fetch
 * @param array $options CURL options
 * @param int $delay Delay before request (seconds)
 * @return array ['success' => bool, 'data' => string, 'error' => string|null]
 */
function fetchUrl(string $url, array $options = [], int $delay = 0): array
{
    if ($delay > 0) {
        sleep($delay);
    }
    
    $ch = curl_init($url);
    
    if ($ch === false) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'Failed to initialize CURL'
        ];
    }
    
    // Default CURL options
    $defaultOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Constituant/1.0 (https://constituant.fr)',
    ];
    
    // Merge with custom options
    $options = $options + $defaultOptions;
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        return [
            'success' => false,
            'data' => null,
            'error' => "CURL error: $error"
        ];
    }
    
    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'data' => $response,
            'error' => "HTTP error $httpCode"
        ];
    }
    
    return [
        'success' => true,
        'data' => $response,
        'error' => null
    ];
}

/**
 * Parse JSON response
 *
 * @param string $json JSON string
 * @return array ['success' => bool, 'data' => array, 'error' => string|null]
 */
function parseJson(string $json): array
{
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'data' => null,
            'error' => json_last_error_msg()
        ];
    }
    
    return [
        'success' => true,
        'data' => $data,
        'error' => null
    ];
}

/**
 * Parse date string to MySQL datetime format
 *
 * @param string $dateStr Date string
 * @return string|null MySQL datetime or null if parsing fails
 */
function parseDate(string $dateStr): ?string
{
    try {
        // Try multiple date formats
        $formats = [
            'Y-m-d H:i:s',      // MySQL format
            'Y-m-d\TH:i:s\Z',   // ISO 8601
            'Y-m-d\TH:i:sP',    // ISO 8601 with timezone
            'Y-m-d',            // Date only
            'D, d M Y H:i:s O', // RFC 2822 (RSS)
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        // Try strtotime as fallback
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        logMessage("Failed to parse date: $dateStr", 'WARNING');
        return null;
        
    } catch (Exception $e) {
        logMessage("Date parsing error: " . $e->getMessage(), 'WARNING');
        return null;
    }
}

/**
 * Clean text for database storage
 *
 * @param string|null $text Text to clean
 * @param int $maxLength Maximum length
 * @return string|null Cleaned text
 */
function cleanText(?string $text, int $maxLength = 0): ?string
{
    if ($text === null || $text === '') {
        return null;
    }
    
    // Remove HTML tags
    $text = strip_tags($text);
    
    // Normalize whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Truncate if needed
    if ($maxLength > 0 && mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength - 3) . '...';
    }
    
    return $text;
}

/**
 * Generate unique bill ID
 *
 * @param string $source Source name
 * @param string $externalId External ID
 * @param string $title Bill title
 * @return string Unique bill ID
 */
function generateBillId(string $source, string $externalId, string $title): string
{
    // Create clean ID from external ID or title
    $cleanId = $externalId ?: $title;
    
    // Remove special characters, keep only alphanumeric and hyphens
    $cleanId = preg_replace('/[^a-z0-9-]/i', '-', $cleanId);
    $cleanId = preg_replace('/-+/', '-', $cleanId);
    $cleanId = trim($cleanId, '-');
    $cleanId = strtolower($cleanId);
    
    // Truncate to 60 chars
    if (strlen($cleanId) > 60) {
        $cleanId = substr($cleanId, 0, 60);
    }
    
    // Add year suffix
    $year = date('Y');
    
    return "{$source}-{$cleanId}-{$year}";
}

/**
 * Check if bill date is within valid range
 *
 * @param string $voteDate Vote datetime
 * @return bool True if valid
 */
function isValidBillDate(string $voteDate): bool
{
    $now = new DateTime();
    $date = new DateTime($voteDate);
    
    $daysAhead = ($date->getTimestamp() - $now->getTimestamp()) / (60 * 60 * 24);
    
    return ($daysAhead >= MIN_DAYS_AHEAD && $daysAhead <= MAX_DAYS_AHEAD);
}

/**
 * Log import operation
 *
 * @param string $source Source name
 * @param string $status Status (success/partial/failed)
 * @param array $stats Statistics
 * @param float $executionTime Execution time in seconds
 * @return void
 */
function logImportOperation(string $source, string $status, array $stats, float $executionTime): void
{
    try {
        $pdo = getDbConnection();
        
        $query = "
            INSERT INTO import_logs (
                source, status, bills_fetched, bills_new, bills_updated,
                bills_skipped, errors_count, execution_time, error_messages, completed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $source,
            $status,
            $stats['fetched'] ?? 0,
            $stats['new'] ?? 0,
            $stats['updated'] ?? 0,
            $stats['skipped'] ?? 0,
            count($stats['errors'] ?? []),
            round($executionTime, 2),
            json_encode($stats['errors'] ?? [], JSON_UNESCAPED_UNICODE)
        ]);
        
    } catch (PDOException $e) {
        logMessage("Failed to log import operation: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Log message to file
 *
 * @param string $message Message to log
 * @param string $level Log level (INFO/WARNING/ERROR)
 * @return void
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    $logFile = __DIR__ . '/../../logs/bill-imports.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] [{$level}] {$message}\n";
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Get source configuration
 *
 * @param string $source Source name
 * @return array|null Source config or null if not found
 */
function getSourceConfig(string $source): ?array
{
    $sources = [
        'nosdeputes' => [
            'name' => 'NosDéputés.fr',
            'enabled' => true,
            'priority' => 1,
            'base_url' => 'https://www.nosdeputes.fr',
            'endpoints' => [
                'dossiers' => '/dossiers/date/json',
            ],
        ],
        'lafabrique' => [
            'name' => 'La Fabrique de la Loi',
            'enabled' => true,
            'priority' => 2,
            'base_url' => 'https://www.lafabriquedelaloi.fr',
            'endpoints' => [
                'dossiers' => '/api/dossiers.csv',
            ],
        ],
        'eu-parliament' => [
            'name' => 'European Parliament',
            'enabled' => false, // Disabled due to 406 errors
            'priority' => 3,
            'base_url' => 'https://data.europarl.europa.eu',
            'endpoints' => [
                'documents' => '/api/v2/documents',
            ],
        ],
    ];
    
    return $sources[$source] ?? null;
}

/**
 * Get all enabled sources sorted by priority
 *
 * @return array Enabled sources
 */
function getEnabledSources(): array
{
    $sources = [
        'nosdeputes' => getSourceConfig('nosdeputes'),
        'lafabrique' => getSourceConfig('lafabrique'),
        'eu-parliament' => getSourceConfig('eu-parliament'),
    ];
    
    // Filter enabled only
    $enabled = array_filter($sources, fn($s) => $s['enabled']);
    
    // Sort by priority
    uasort($enabled, fn($a, $b) => $a['priority'] <=> $b['priority']);
    
    return $enabled;
}
