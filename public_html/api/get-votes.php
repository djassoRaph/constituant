<?php
/**
 * Get Votes API Endpoint
 *
 * Returns all bills with vote statistics and user's voting status.
 *
 * Method: GET
 * Query params:
 *   - level: Filter by 'eu', 'france', or 'all' (default: 'all')
 *   - status: Filter by status 'upcoming', 'voting_now', 'completed' (optional)
 *
 * Response format:
 * {
 *   "success": true,
 *   "bills": [
 *     {
 *       "id": "eu-dsa-2024",
 *       "title": "...",
 *       "summary": "...",
 *       "full_text_url": "...",
 *       "level": "eu",
 *       "chamber": "...",
 *       "vote_datetime": "2024-12-15 14:00:00",
 *       "vote_datetime_formatted": "15 déc 2024, 14:00",
 *       "status": "upcoming",
 *       "urgency": {
 *         "is_soon": true,
 *         "label": "Vote aujourd'hui",
 *         "urgency": "urgent"
 *       },
 *       "votes": {
 *         "for": 234,
 *         "against": 45,
 *         "abstain": 21,
 *         "total": 300
 *       },
 *       "percentages": {
 *         "for": 78,
 *         "against": 15,
 *         "abstain": 7
 *       },
 *       "user_voted": "for"
 *     }
 *   ]
 * }
 *
 * @package Constituant
 */

// Start output buffering to catch any errors
ob_start();

// Set error handler to return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr in $errfile on line $errline"
    ]);
    exit;
});

// Set exception handler to return JSON
set_exception_handler(function($exception) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit;
});

require_once __DIR__ . '/../../cron/lib/config/config.php';
require_once __DIR__ . '/../../cron/lib/config/database.php';

// Clean output buffer before sending JSON
ob_end_clean();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
}

try {
    // Get filter parameters
    $level = $_GET['level'] ?? 'all';
    $status = $_GET['status'] ?? null;

    // Validate level parameter
    $validLevels = ['all', 'eu', 'france'];
    if (!in_array($level, $validLevels)) {
        sendErrorResponse('Invalid level parameter. Must be: all, eu, or france');
    }

    // Build query
    $query = "
        SELECT
            b.id,
            b.title,
            b.summary,
            b.ai_summary,
            b.mistral_ai_json_response,
            b.theme,
            b.full_text_url,
            b.level,
            b.chamber,
            b.vote_datetime,
            b.status,
            COUNT(v.id) as total_votes,
            SUM(CASE WHEN v.vote_type = 'for' THEN 1 ELSE 0 END) as votes_for,
            SUM(CASE WHEN v.vote_type = 'against' THEN 1 ELSE 0 END) as votes_against,
            SUM(CASE WHEN v.vote_type = 'abstain' THEN 1 ELSE 0 END) as votes_abstain
        FROM bills b
        LEFT JOIN votes v ON b.id = v.bill_id
    ";

    $params = [];
    $conditions = [];

    // Add level filter
    if ($level !== 'all') {
        $conditions[] = "b.level = ?";
        $params[] = $level;
    }

    // Add status filter
    if ($status !== null) {
        $validStatuses = ['upcoming', 'voting_now', 'completed'];
        if (in_array($status, $validStatuses)) {
            $conditions[] = "b.status = ?";
            $params[] = $status;
        }
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $query .= " GROUP BY b.id ORDER BY b.vote_datetime ASC";

    // Execute query
    $stmt = dbQuery($query, $params);
    $bills = $stmt->fetchAll();

    // Get user's IP for vote status
    $userIP = getUserIP();

    // Get user's votes
    $userVotesQuery = "SELECT bill_id, vote_type FROM votes WHERE voter_ip = ?";
    $userVotesStmt = dbQuery($userVotesQuery, [$userIP]);
    $userVotes = [];
    foreach ($userVotesStmt->fetchAll() as $vote) {
        $userVotes[$vote['bill_id']] = $vote['vote_type'];
    }

    // Format response
    $formattedBills = [];
    foreach ($bills as $bill) {
        $totalVotes = (int)$bill['total_votes'];
        $votesFor = (int)$bill['votes_for'];
        $votesAgainst = (int)$bill['votes_against'];
        $votesAbstain = (int)$bill['votes_abstain'];

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

        // Get urgency information
        $urgency = getVoteUrgency($bill['vote_datetime']);

        // Parse AI JSON response
        $aiData = null;
        if (!empty($bill['mistral_ai_json_response'])) {
            $decoded = json_decode($bill['mistral_ai_json_response'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $aiData = $decoded;
            }
        }

        $formattedBills[] = [
            'id' => $bill['id'],
            'title' => $bill['title'],
            'summary' => $bill['summary'],
            'ai_summary' => $bill['ai_summary'],
            'ai_data' => $aiData,
            'theme' => $bill['theme'] ?? 'Sans catégorie',
            'full_text_url' => $bill['full_text_url'],
            'level' => $bill['level'],
            'chamber' => $bill['chamber'],
            'vote_datetime' => $bill['vote_datetime'],
            'vote_datetime_formatted' => formatDateTime($bill['vote_datetime'], 'd M Y, H:i'),
            'status' => $bill['status'],
            'urgency' => $urgency,
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
            'user_voted' => $userVotes[$bill['id']] ?? null
        ];
    }

    // Send response
    sendJsonResponse([
        'success' => true,
        'bills' => $formattedBills,
        'count' => count($formattedBills)
    ]);

} catch (PDOException $e) {
    error_log('Database error in get-votes.php: ' . $e->getMessage());
    sendErrorResponse('Database error occurred', 500);
} catch (Exception $e) {
    error_log('Error in get-votes.php: ' . $e->getMessage());
    sendErrorResponse('An error occurred', 500);
}
