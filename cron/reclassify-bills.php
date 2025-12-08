<?php
/**
 * Re-classify Existing Bills with Mistral AI
 *
 * This script re-classifies bills that were imported before AI integration
 * or that failed AI classification during import.
 *
 * Usage: php cron/reclassify-bills.php [--limit=N] [--force]
 *
 * Options:
 *   --limit=N   Process only N bills (default: 10)
 *   --force     Re-classify even if already classified
 *
 * @package Constituant
 */

// Ensure we're running in CLI mode
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from command line');
}

// Define app constant
define('CONSTITUANT_APP', true);

// Load dependencies
require_once __DIR__ . '/lib/fetcher-base.php';

// Parse command line arguments
$options = getopt('', ['limit::', 'force']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 10;
$force = isset($options['force']);

// Output header
echo str_repeat('=', 80) . PHP_EOL;
echo "Constituant - Re-classify Bills with AI" . PHP_EOL;
echo "Started: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Limit: $limit bills" . PHP_EOL;
echo "Force: " . ($force ? 'Yes' : 'No') . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

logMessage("===== Starting bill re-classification =====");

$stats = [
    'processed' => 0,
    'success' => 0,
    'failed' => 0,
    'skipped' => 0,
];

try {
    // Get database connection
    $pdo = getDbConnection();

    // Build query based on force flag
    if ($force) {
        // Re-classify all bills
        $query = "
            SELECT id, title, summary, full_text_url
            FROM pending_bills
            WHERE status = 'pending'
            ORDER BY created_at DESC
            LIMIT :limit
        ";
    } else {
        // Only classify bills that haven't been classified yet
        $query = "
            SELECT id, title, summary, full_text_url
            FROM pending_bills
            WHERE status = 'pending'
            AND (ai_processed_at IS NULL OR theme = 'Sans catégorie')
            ORDER BY created_at DESC
            LIMIT :limit
        ";
    }

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalBills = count($bills);

    if ($totalBills === 0) {
        echo "No bills found for re-classification." . PHP_EOL;
        logMessage("No bills found for re-classification");
        exit(0);
    }

    echo "Found $totalBills bills to process" . PHP_EOL . PHP_EOL;
    logMessage("Found $totalBills bills to re-classify");

    // Process each bill
    foreach ($bills as $index => $bill) {
        $stats['processed']++;
        $billNum = $index + 1;

        echo "[$billNum/$totalBills] Processing: {$bill['title']}" . PHP_EOL;
        logMessage("Processing bill ID {$bill['id']}: {$bill['title']}");

        // Fetch full text if URL exists
        $fullText = '';
        if (!empty($bill['full_text_url'])) {
            echo "  Fetching full text..." . PHP_EOL;
            $fullText = fetchFullTextContent($bill['full_text_url']);

            if (empty($fullText)) {
                echo "  Warning: Could not fetch full text" . PHP_EOL;
            } else {
                echo "  Fetched " . strlen($fullText) . " characters" . PHP_EOL;
            }
        }

        // Skip if no content available
        if (empty($bill['summary']) && empty($fullText)) {
            echo "  ⊘ Skipped: No content available for classification" . PHP_EOL;
            $stats['skipped']++;
            echo PHP_EOL;
            continue;
        }

        // Call Mistral AI
        echo "  Calling Mistral AI..." . PHP_EOL;
        $aiResult = classifyBillWithAI(
            $bill['title'],
            $bill['summary'] ?? '',
            $fullText
        );

        if ($aiResult['error'] === null) {
            // Update database
            $updateStmt = $pdo->prepare("
                UPDATE pending_bills
                SET theme = :theme,
                    ai_summary = :summary,
                    ai_processed_at = NOW()
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':theme' => $aiResult['theme'],
                ':summary' => $aiResult['summary'],
                ':id' => $bill['id'],
            ]);

            echo "  ✓ Success: Classified as '{$aiResult['theme']}'" . PHP_EOL;
            echo "  Summary: " . substr($aiResult['summary'], 0, 100) . "..." . PHP_EOL;
            logMessage("Bill {$bill['id']} classified as: {$aiResult['theme']}");
            $stats['success']++;

        } else {
            echo "  ✗ Failed: {$aiResult['error']}" . PHP_EOL;
            logMessage("Failed to classify bill {$bill['id']}: {$aiResult['error']}", 'ERROR');
            $stats['failed']++;
        }

        echo PHP_EOL;

        // Small delay to avoid rate limiting
        if ($billNum < $totalBills) {
            sleep(1);
        }
    }

    // Summary
    echo str_repeat('=', 80) . PHP_EOL;
    echo "Re-classification Summary" . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo "Processed: {$stats['processed']}" . PHP_EOL;
    echo "Success: {$stats['success']}" . PHP_EOL;
    echo "Failed: {$stats['failed']}" . PHP_EOL;
    echo "Skipped: {$stats['skipped']}" . PHP_EOL;
    echo "Completed: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;

    logMessage("===== Re-classification completed =====");
    logMessage("Stats: {$stats['success']} success, {$stats['failed']} failed, {$stats['skipped']} skipped");

    exit($stats['failed'] > 0 ? 1 : 0);

} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'ERROR');
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
