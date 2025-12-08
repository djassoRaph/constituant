<?php
/**
 * Base Fetcher Utilities
 *
 * Common functions for all bill data fetchers.
 * Handles HTTP requests, logging, database operations, and error handling.
 *
 * @package Constituant
 */

// Prevent direct access
if (!defined('CONSTITUANT_APP')) {
    define('CONSTITUANT_APP', true);
}

// Load dependencies
require_once __DIR__ . '/../../public_html/config/config.php';
require_once __DIR__ . '/../../public_html/config/database.php';
require_once __DIR__ . '/../../public_html/config/sources.php';
require_once __DIR__ . '/../../public_html/includes/mistral_ai.php';

// Set timezone
date_default_timezone_set(IMPORT_SETTINGS['timezone']);

/**
 * Make HTTP request with error handling and rate limiting
 *
 * @param string $url URL to fetch
 * @param array $options CURL options
 * @param int $delay Delay in seconds before request
 * @return array ['success' => bool, 'data' => mixed, 'error' => string]
 */
function fetchUrl(string $url, array $options = [], int $delay = 0): array
{
    // Rate limiting delay
    if ($delay > 0) {
        sleep($delay);
    }

    $ch = curl_init();

    $defaultOptions = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Constituant/1.0 (Civic Platform; +https://constituant.fr)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
        ],
    ];

    curl_setopt_array($ch, array_replace($defaultOptions, $options));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'data' => null,
            'error' => "CURL error: $error",
        ];
    }

    if ($httpCode >= 400) {
        return [
            'success' => false,
            'data' => null,
            'error' => "HTTP error $httpCode",
        ];
    }

    return [
        'success' => true,
        'data' => $response,
        'error' => null,
    ];
}

/**
 * Parse JSON response
 *
 * @param string $jsonString JSON string
 * @return array ['success' => bool, 'data' => mixed, 'error' => string]
 */
function parseJson(string $jsonString): array
{
    $data = json_decode($jsonString, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'data' => null,
            'error' => 'JSON parse error: ' . json_last_error_msg(),
        ];
    }

    return [
        'success' => true,
        'data' => $data,
        'error' => null,
    ];
}

/**
 * Insert or update pending bill
 *
 * @param array $billData Bill data
 * @return array ['success' => bool, 'action' => string, 'error' => string]
 */
function savePendingBill(array $billData): array
{
    try {
        // Validate required fields
        $required = ['external_id', 'source', 'title', 'level'];
        foreach ($required as $field) {
            if (empty($billData[$field])) {
                return [
                    'success' => false,
                    'action' => 'skipped',
                    'error' => "Missing required field: $field",
                ];
            }
        }

        // Check if already exists
        $checkQuery = "
            SELECT id, status FROM pending_bills
            WHERE source = :source AND external_id = :external_id
        ";
        $existing = dbQuery($checkQuery, [
            ':source' => $billData['source'],
            ':external_id' => $billData['external_id'],
        ])->fetch();

        if ($existing) {
            // Don't update if already reviewed
            if (in_array($existing['status'], ['approved', 'rejected'])) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'error' => 'Already reviewed',
                ];
            }

            // AI Classification for updated bill (only if not already classified)
            $theme = null;
            $aiSummary = null;
            $aiProcessedAt = null;

            // Check if bill was already AI-processed
            $checkAI = dbQuery(
                "SELECT theme, ai_processed_at FROM pending_bills WHERE id = :id",
                [':id' => $existing['id']]
            )->fetch();

            // Only re-classify if never classified before or theme is 'Sans catégorie'
            if (empty($checkAI['ai_processed_at']) || $checkAI['theme'] === 'Sans catégorie') {
                if (!empty($billData['full_text_url'])) {
                    logMessage("Re-fetching for AI classification (update): {$billData['full_text_url']}");
                    $fullTextContent = fetchFullTextContent($billData['full_text_url']);

                    if (!empty($fullTextContent) || !empty($billData['summary'])) {
                        logMessage("Calling Mistral AI for updated bill...");
                        $aiResult = classifyBillWithAI(
                            $billData['title'],
                            $billData['summary'] ?? '',
                            $fullTextContent
                        );

                        if ($aiResult['error'] === null) {
                            $theme = $aiResult['theme'];
                            $aiSummary = $aiResult['summary'];
                            $aiProcessedAt = 'NOW()';
                            logMessage("AI re-classified as: $theme");
                        } else {
                            logMessage("AI re-classification failed: {$aiResult['error']}", 'WARNING');
                        }
                    }
                }
            }

            // Update existing pending bill
            $updateQuery = "
                UPDATE pending_bills SET
                    title = :title,
                    summary = :summary,
                    full_text_url = :full_text_url,
                    level = :level,
                    chamber = :chamber,
                    vote_datetime = :vote_datetime,
                    raw_data = :raw_data,
                    fetched_at = CURRENT_TIMESTAMP"
                    . ($theme !== null ? ", theme = :theme, ai_summary = :ai_summary, ai_processed_at = NOW()" : "") . "
                WHERE id = :id
            ";

            $params = [
                ':id' => $existing['id'],
                ':title' => $billData['title'],
                ':summary' => $billData['summary'] ?? null,
                ':full_text_url' => $billData['full_text_url'] ?? null,
                ':level' => $billData['level'],
                ':chamber' => $billData['chamber'] ?? IMPORT_SETTINGS['default_chambers'][$billData['level']] ?? null,
                ':vote_datetime' => $billData['vote_datetime'] ?? null,
                ':raw_data' => isset($billData['raw_data']) ? json_encode($billData['raw_data']) : null,
            ];

            if ($theme !== null) {
                $params[':theme'] = $theme;
                $params[':ai_summary'] = $aiSummary;
            }

            dbQuery($updateQuery, $params);

            return [
                'success' => true,
                'action' => 'updated',
                'error' => null,
            ];
        }

        // AI Classification for new bill
        $theme = 'Sans catégorie';
        $aiSummary = null;
        $aiProcessedAt = null;

        // Attempt to classify with AI
        if (!empty($billData['full_text_url'])) {
            logMessage("Fetching full text for AI classification from: {$billData['full_text_url']}");
            $fullTextContent = fetchFullTextContent($billData['full_text_url']);

            if (!empty($fullTextContent) || !empty($billData['summary'])) {
                logMessage("Calling Mistral AI for classification...");
                $aiResult = classifyBillWithAI(
                    $billData['title'],
                    $billData['summary'] ?? '',
                    $fullTextContent
                );

                if ($aiResult['error'] === null) {
                    $theme = $aiResult['theme'];
                    $aiSummary = $aiResult['summary'];
                    $aiProcessedAt = 'NOW()';
                    logMessage("AI classified as: $theme");
                } else {
                    logMessage("AI classification failed: {$aiResult['error']}", 'WARNING');
                }
            }
        }

        // Insert new pending bill
        $insertQuery = "
            INSERT INTO pending_bills (
                external_id, source, title, summary, full_text_url,
                level, chamber, vote_datetime, theme, ai_summary,
                ai_processed_at, raw_data, status
            ) VALUES (
                :external_id, :source, :title, :summary, :full_text_url,
                :level, :chamber, :vote_datetime, :theme, :ai_summary,
                " . ($aiProcessedAt ?? 'NULL') . ", :raw_data, 'pending'
            )
        ";

        dbQuery($insertQuery, [
            ':external_id' => $billData['external_id'],
            ':source' => $billData['source'],
            ':title' => $billData['title'],
            ':summary' => $billData['summary'] ?? null,
            ':full_text_url' => $billData['full_text_url'] ?? null,
            ':level' => $billData['level'],
            ':chamber' => $billData['chamber'] ?? IMPORT_SETTINGS['default_chambers'][$billData['level']] ?? null,
            ':vote_datetime' => $billData['vote_datetime'] ?? null,
            ':theme' => $theme,
            ':ai_summary' => $aiSummary,
            ':raw_data' => isset($billData['raw_data']) ? json_encode($billData['raw_data']) : null,
        ]);

        return [
            'success' => true,
            'action' => 'inserted',
            'error' => null,
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'action' => 'error',
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Log import operation
 *
 * @param string $source Source name
 * @param string $status Status (success, partial, failed)
 * @param array $stats Import statistics
 * @param float $executionTime Execution time in seconds
 * @return void
 */
function logImport(string $source, string $status, array $stats, float $executionTime): void
{
    try {
        $query = "
            INSERT INTO import_logs (
                source, status, bills_fetched, bills_new, bills_updated,
                errors, execution_time, completed_at
            ) VALUES (
                :source, :status, :bills_fetched, :bills_new, :bills_updated,
                :errors, :execution_time, CURRENT_TIMESTAMP
            )
        ";

        dbQuery($query, [
            ':source' => $source,
            ':status' => $status,
            ':bills_fetched' => $stats['fetched'] ?? 0,
            ':bills_new' => $stats['new'] ?? 0,
            ':bills_updated' => $stats['updated'] ?? 0,
            ':errors' => isset($stats['errors']) ? json_encode($stats['errors']) : null,
            ':execution_time' => $executionTime,
        ]);
    } catch (Exception $e) {
        logMessage("Failed to log import: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Write log message to file and error log
 *
 * @param string $message Log message
 * @param string $level Log level (INFO, WARNING, ERROR)
 * @return void
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;

    // Log to file
    $logFile = __DIR__ . '/../../' . IMPORT_SETTINGS['log_file'];
    $logDir = dirname($logFile);

    // Ensure log directory exists
    if (!is_dir($logDir)) {
        if (!@mkdir($logDir, 0755, true)) {
            error_log("Failed to create log directory: $logDir");
            return;
        }
    }

    // Write to log file with error handling
    $result = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    if ($result === false) {
        error_log("Failed to write to log file: $logFile (check permissions)");
    }

    // Also log to PHP error log for important messages
    if (in_array($level, ['ERROR', 'WARNING'])) {
        error_log($logEntry);
    }

    // Echo in CLI mode
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

/**
 * Parse various date formats to MySQL DATETIME
 *
 * @param string|null $dateString Date string
 * @return string|null MySQL DATETIME or null
 */
function parseDate(?string $dateString): ?string
{
    if (empty($dateString)) {
        return null;
    }

    try {
        $date = new DateTime($dateString, new DateTimeZone(IMPORT_SETTINGS['timezone']));
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        logMessage("Failed to parse date '$dateString': " . $e->getMessage(), 'WARNING');
        return null;
    }
}

/**
 * Clean and truncate text to fit field limits
 *
 * @param string|null $text Text to clean
 * @param int $maxLength Maximum length
 * @return string|null Cleaned text
 */
function cleanText(?string $text, int $maxLength = null): ?string
{
    if (empty($text)) {
        return null;
    }

    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', trim($text));

    // Truncate if needed
    if ($maxLength && mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength - 3) . '...';
    }

    return $text;
}

/**
 * Extract summary from HTML content (if summary is missing)
 *
 * @param string $html HTML content
 * @param int $maxLength Maximum summary length
 * @return string|null Extracted summary
 */
function extractSummaryFromHtml(string $html, int $maxLength = 500): ?string
{
    // Strip HTML tags
    $text = strip_tags($html);

    // Get first paragraph or sentence
    $text = preg_replace('/\s+/', ' ', trim($text));

    // Take first sentence or up to maxLength
    $sentences = preg_split('/[.!?]+/', $text, 2);
    $summary = $sentences[0] ?? '';

    return cleanText($summary, $maxLength);
}

/**
 * Auto-update bill status based on vote datetime
 * Marks bills as 'completed' if vote date has passed
 *
 * @return int Number of bills updated
 */
function autoUpdateBillStatus(): int
{
    try {
        $query = "
            UPDATE bills
            SET status = 'completed'
            WHERE status IN ('upcoming', 'voting_now')
            AND vote_datetime < NOW()
        ";

        $stmt = dbQuery($query);
        return $stmt->rowCount();
    } catch (Exception $e) {
        logMessage("Auto-update status failed: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Fetch full text content from URL
 * Handles HTML and attempts to extract readable text
 *
 * @param string|null $url URL to fetch full text from
 * @param int $maxLength Maximum length in characters (default 50000)
 * @return string Full text content or empty string
 */
function fetchFullTextContent(?string $url, int $maxLength = 50000): string
{
    if (empty($url)) {
        return '';
    }

    try {
        // Fetch the content
        $result = fetchUrl($url, [
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
            ],
        ]);

        if (!$result['success']) {
            logMessage("Failed to fetch full text from $url: {$result['error']}", 'WARNING');
            return '';
        }

        $content = $result['data'];

        // Check content type - skip PDFs for now (would need PDF parser)
        if (stripos($content, '%PDF-') === 0) {
            logMessage("Skipping PDF content from $url (PDF parsing not yet implemented)", 'INFO');
            return '';
        }

        // Strip HTML tags to get plain text
        $text = strip_tags($content);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Truncate to max length
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
            logMessage("Full text truncated from " . mb_strlen($content) . " to $maxLength chars", 'INFO');
        }

        return $text;

    } catch (Exception $e) {
        logMessage("Exception fetching full text from $url: " . $e->getMessage(), 'WARNING');
        return '';
    }
}
