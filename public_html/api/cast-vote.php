<?php
/**
 * Cast Vote API Endpoint
 *
 * Allows users to cast their vote on a bill.
 *
 * Method: POST
 * Content-Type: application/json
 *
 * Request body:
 * {
 *   "bill_id": "eu-dsa-2024",
 *   "vote_type": "for"
 * }
 *
 * Response format:
 * {
 *   "success": true,
 *   "message": "Vote enregistré",
 *   "vote": {
 *     "bill_id": "eu-dsa-2024",
 *     "vote_type": "for"
 *   }
 * }
 *
 * @package Constituant
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
}

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse('Invalid JSON format');
    }

    // Validate required fields
    if (empty($data['bill_id'])) {
        sendErrorResponse('Le champ bill_id est requis');
    }

    if (empty($data['vote_type'])) {
        sendErrorResponse('Le champ vote_type est requis');
    }

    $billId = trim($data['bill_id']);
    $voteType = trim($data['vote_type']);

    // Validate vote type
    $validVoteTypes = ['for', 'against', 'abstain'];
    if (!in_array($voteType, $validVoteTypes)) {
        sendErrorResponse('Type de vote invalide. Doit être: for, against, ou abstain');
    }

    // Get user information
    $voterIP = getUserIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Validate IP address
    if ($voterIP === '0.0.0.0') {
        sendErrorResponse('Impossible de déterminer votre adresse IP', 500);
    }

    // Check if bill exists
    $billCheckQuery = "SELECT id, title, status FROM bills WHERE id = ?";
    $billStmt = dbQuery($billCheckQuery, [$billId]);
    $bill = $billStmt->fetch();

    if (!$bill) {
        sendErrorResponse('Projet de loi introuvable', 404);
    }

    // Check if bill is still open for voting
    if ($bill['status'] === 'completed') {
        sendErrorResponse('Le vote pour ce projet de loi est terminé');
    }

    // Rate limiting: Check how many votes this IP has cast in the last hour
    $rateLimitQuery = "
        SELECT COUNT(*) as vote_count
        FROM votes
        WHERE voter_ip = ?
        AND voted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ";
    $rateLimitStmt = dbQuery($rateLimitQuery, [$voterIP, VOTE_RATE_WINDOW]);
    $rateLimitResult = $rateLimitStmt->fetch();

    if ($rateLimitResult['vote_count'] >= VOTE_RATE_LIMIT) {
        sendErrorResponse('Limite de votes atteinte. Veuillez réessayer plus tard.', 429);
    }

    // Check if user already voted on this bill
    $existingVoteQuery = "SELECT vote_type FROM votes WHERE bill_id = ? AND voter_ip = ?";
    $existingVoteStmt = dbQuery($existingVoteQuery, [$billId, $voterIP]);
    $existingVote = $existingVoteStmt->fetch();

    if ($existingVote) {
        // User already voted - check if they're trying to change their vote
        if ($existingVote['vote_type'] === $voteType) {
            sendErrorResponse('Vous avez déjà voté "' . ucfirst($voteType) . '" pour ce projet de loi');
        }

        // Allow vote change (update existing vote)
        $updateQuery = "
            UPDATE votes
            SET vote_type = ?, voted_at = CURRENT_TIMESTAMP, user_agent = ?
            WHERE bill_id = ? AND voter_ip = ?
        ";
        dbQuery($updateQuery, [$voteType, $userAgent, $billId, $voterIP]);

        sendJsonResponse([
            'success' => true,
            'message' => 'Vote modifié avec succès',
            'vote' => [
                'bill_id' => $billId,
                'bill_title' => $bill['title'],
                'vote_type' => $voteType,
                'action' => 'updated'
            ]
        ]);
    }

    // Insert new vote
    $insertQuery = "
        INSERT INTO votes (bill_id, vote_type, voter_ip, user_agent)
        VALUES (?, ?, ?, ?)
    ";

    try {
        dbQuery($insertQuery, [$billId, $voteType, $voterIP, $userAgent]);

        // Success response
        sendJsonResponse([
            'success' => true,
            'message' => 'Vote enregistré avec succès',
            'vote' => [
                'bill_id' => $billId,
                'bill_title' => $bill['title'],
                'vote_type' => $voteType,
                'action' => 'created'
            ]
        ], 201);

    } catch (PDOException $e) {
        // Handle unique constraint violation (race condition)
        if ($e->getCode() == 23000) {
            sendErrorResponse('Vous avez déjà voté sur ce projet de loi');
        }
        throw $e;
    }

} catch (PDOException $e) {
    error_log('Database error in cast-vote.php: ' . $e->getMessage());
    sendErrorResponse('Erreur lors de l\'enregistrement du vote', 500);
} catch (Exception $e) {
    error_log('Error in cast-vote.php: ' . $e->getMessage());
    sendErrorResponse('Une erreur est survenue', 500);
}
