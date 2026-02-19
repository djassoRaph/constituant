<?php
/**
 * Re-classify Existing Bills with Mistral AI
 *
 * Usage: php cron/reclassify-bills.php [--limit=N] [--force]
 *
 * Options:
 *   --limit=N   Process only N bills (default: 10)
 *   --force     Re-classify even bills that already have AI data
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from command line');
}

define('CONSTITUANT_APP', true);

require_once __DIR__ . '/lib/fetcher-base.php';
require_once __DIR__ . '/lib/mistral_ai.php';

// Parse arguments
$options = getopt('', ['limit::', 'force']);
$limit   = isset($options['limit']) ? (int)$options['limit'] : 10;
$force   = isset($options['force']);

echo str_repeat('=', 80) . PHP_EOL;
echo "Constituant - Re-classify Bills with AI" . PHP_EOL;
echo "Started: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Limit: $limit bills | Force: " . ($force ? 'Yes' : 'No') . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL . PHP_EOL;

$stats = ['processed' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];

try {
    $pdo = getDbConnection();

    // Build query - target `bills` table (not pending_bills)
    if ($force) {
        $query = "
            SELECT id, title, summary, full_text_url
            FROM bills
            ORDER BY created_at DESC
            LIMIT :limit
        ";
    } else {
        $query = "
            SELECT id, title, summary, full_text_url
            FROM bills
            WHERE mistral_ai_json_response IS NULL
               OR theme = 'Sans catégorie'
            ORDER BY created_at DESC
            LIMIT :limit
        ";
    }

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($bills);

    if ($total === 0) {
        echo "No bills found for re-classification." . PHP_EOL;
        exit(0);
    }

    echo "Found $total bills to process" . PHP_EOL . PHP_EOL;

    foreach ($bills as $index => $bill) {
        $stats['processed']++;
        $num = $index + 1;

        echo "[$num/$total] {$bill['title']}" . PHP_EOL;

        // Skip if no content at all
        if (empty($bill['summary'])) {
            echo "  ⊘ Skipped: No summary content available" . PHP_EOL . PHP_EOL;
            $stats['skipped']++;
            continue;
        }

        // Call Mistral AI - uses classifyBillWithAI() from mistral_ai.php
        echo "  Calling Mistral AI..." . PHP_EOL;
        $aiResult = classifyBillWithAI(
            $bill['title'],
            $bill['summary'],
            '' // full text optional
        );

        if ($aiResult['error'] === null) {
            $updateStmt = $pdo->prepare("
                UPDATE bills
                SET theme                    = :theme,
                    ai_summary               = :ai_summary,
                    mistral_ai_json_response = :json_response
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':theme'         => $aiResult['theme'],
                ':ai_summary'    => $aiResult['abstract'],
                ':json_response' => $aiResult['json_response'],
                ':id'            => $bill['id'],
            ]);

            echo "  ✓ Classified as '{$aiResult['theme']}'" . PHP_EOL;
            echo "  Abstract: " . substr($aiResult['abstract'], 0, 100) . PHP_EOL;
            $stats['success']++;

        } else {
            echo "  ✗ Failed: {$aiResult['error']}" . PHP_EOL;
            $stats['failed']++;
        }

        echo PHP_EOL;

        // Avoid Mistral rate limiting
        if ($num < $total) {
            sleep(1);
        }
    }

    echo str_repeat('=', 80) . PHP_EOL;
    echo "Summary: {$stats['success']} success | {$stats['failed']} failed | {$stats['skipped']} skipped" . PHP_EOL;
    echo "Completed: " . date('Y-m-d H:i:s') . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;

    exit($stats['failed'] > 0 ? 1 : 0);

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}