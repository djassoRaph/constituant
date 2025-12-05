<?php
/**
 * Get Results API Endpoint
 *
 * Returns vote statistics for a specific bill.
 *
 * Method: GET
 * Query params:
 *   - bill_id: The ID of the bill (required)
 *
 * Response format:
 * {
 *   "success": true,
 *   "bill_id": "eu-dsa-2024",
 *   "bill_title": "Digital Services Act - Amendment 247",
 *   "votes": {
 *     "for": 234,
 *     "against": 45,
 *     "abstain": 21,
 *     "total": 300
 *   },
 *   "percentages": {
 *     "for": 78,
 *     "against": 15,
 *     "abstain": 7
 *   }
 * }
 *
 * @package Constituant
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
}

try {
    // Get bill_id parameter
    $billId = $_GET['bill_id'] ?? null;

    if (empty($billId)) {
        sendErrorResponse('Le paramètre bill_id est requis');
    }

    $billId = trim($billId);

    // Check if bill exists
    $billQuery = "SELECT id, title FROM bills WHERE id = ?";
    $billStmt = dbQuery($billQuery, [$billId]);
    $bill = $billStmt->fetch();

    if (!$bill) {
        sendErrorResponse('Projet de loi introuvable', 404);
    }

    // Get vote statistics
    $statsQuery = "
        SELECT
            COUNT(*) as total_votes,
            SUM(CASE WHEN vote_type = 'for' THEN 1 ELSE 0 END) as votes_for,
            SUM(CASE WHEN vote_type = 'against' THEN 1 ELSE 0 END) as votes_against,
            SUM(CASE WHEN vote_type = 'abstain' THEN 1 ELSE 0 END) as votes_abstain
        FROM votes
        WHERE bill_id = ?
    ";

    $statsStmt = dbQuery($statsQuery, [$billId]);
    $stats = $statsStmt->fetch();

    $totalVotes = (int)$stats['total_votes'];
    $votesFor = (int)$stats['votes_for'];
    $votesAgainst = (int)$stats['votes_against'];
    $votesAbstain = (int)$stats['votes_abstain'];

    // Calculate percentages
    if ($totalVotes > 0) {
        $percentFor = round(($votesFor / $totalVotes) * 100);
        $percentAgainst = round(($votesAgainst / $totalVotes) * 100);
        $percentAbstain = round(($votesAbstain / $totalVotes) * 100);
    } else {
        $percentFor = 0;
        $percentAgainst = 0;
        $percentAbstain = 0;
    }

    // Get vote timeline (last 24 hours by hour)
    $timelineQuery = "
        SELECT
            DATE_FORMAT(voted_at, '%Y-%m-%d %H:00:00') as hour,
            vote_type,
            COUNT(*) as count
        FROM votes
        WHERE bill_id = ?
        AND voted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY hour, vote_type
        ORDER BY hour ASC
    ";

    $timelineStmt = dbQuery($timelineQuery, [$billId]);
    $timeline = $timelineStmt->fetchAll();

    // Send response
    sendJsonResponse([
        'success' => true,
        'bill_id' => $bill['id'],
        'bill_title' => $bill['title'],
        'votes' => [
            'for' => $votesFor,
            'against' => $votesAgainst,
            'abstain' => $votesAbstain,
            'total' => $totalVotes
        ],
        'percentages' => [
            'for' => $percentFor,
            'against' => $percentAgainst,
            'abstain' => $percentAbstain
        ],
        'timeline' => $timeline,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log('Database error in get-results.php: ' . $e->getMessage());
    sendErrorResponse('Erreur de base de données', 500);
} catch (Exception $e) {
    error_log('Error in get-results.php: ' . $e->getMessage());
    sendErrorResponse('Une erreur est survenue', 500);
}
