<?php
/**
 * Add Bill API Endpoint (Admin Only)
 *
 * Allows admin to add or update bills.
 *
 * Method: POST
 * Content-Type: application/json
 *
 * Request body:
 * {
 *   "admin_password": "constituant2024",
 *   "bill": {
 *     "id": "eu-dsa-2024",
 *     "title": "...",
 *     "summary": "...",
 *     "full_text_url": "...",
 *     "level": "eu",
 *     "chamber": "European Parliament",
 *     "vote_datetime": "2024-12-15 14:00:00",
 *     "status": "upcoming"
 *   },
 *   "action": "create"
 * }
 *
 * Response format:
 * {
 *   "success": true,
 *   "message": "Projet de loi ajouté avec succès",
 *   "bill_id": "eu-dsa-2024"
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

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse('Invalid JSON format');
    }

    // Validate admin password
    if (!isset($data['admin_password']) || !validateAdminPassword($data['admin_password'])) {
        sendErrorResponse('Mot de passe administrateur invalide', 401);
    }

    // Validate bill data
    if (empty($data['bill']) || !is_array($data['bill'])) {
        sendErrorResponse('Données du projet de loi manquantes');
    }

    $bill = $data['bill'];
    $action = $data['action'] ?? 'create'; // create, update, delete

    // Validate required fields for create/update
    if ($action === 'create' || $action === 'update') {
        $requiredFields = ['id', 'title', 'summary', 'level', 'chamber', 'vote_datetime'];

        foreach ($requiredFields as $field) {
            if (empty($bill[$field])) {
                sendErrorResponse("Le champ '$field' est requis");
            }
        }

        // Validate level
        $validLevels = ['eu', 'france'];
        if (!in_array($bill['level'], $validLevels)) {
            sendErrorResponse('Niveau invalide. Doit être: eu ou france');
        }

        // Validate status
        $status = $bill['status'] ?? 'upcoming';
        $validStatuses = ['upcoming', 'voting_now', 'completed'];
        if (!in_array($status, $validStatuses)) {
            sendErrorResponse('Statut invalide. Doit être: upcoming, voting_now, ou completed');
        }

        // Validate datetime format
        $voteDatetime = DateTime::createFromFormat('Y-m-d H:i:s', $bill['vote_datetime']);
        if (!$voteDatetime) {
            sendErrorResponse('Format de date invalide. Utilisez: YYYY-MM-DD HH:MM:SS');
        }
    }

    // Handle different actions
    switch ($action) {
        case 'create':
            // Check if bill already exists
            $checkQuery = "SELECT id FROM bills WHERE id = ?";
            $checkStmt = dbQuery($checkQuery, [$bill['id']]);

            if ($checkStmt->fetch()) {
                sendErrorResponse('Un projet de loi avec cet ID existe déjà. Utilisez action "update" pour le modifier.');
            }

            // Insert new bill
            $insertQuery = "
                INSERT INTO bills (id, title, summary, full_text_url, level, chamber, vote_datetime, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            dbQuery($insertQuery, [
                $bill['id'],
                $bill['title'],
                $bill['summary'],
                $bill['full_text_url'] ?? null,
                $bill['level'],
                $bill['chamber'],
                $bill['vote_datetime'],
                $status
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Projet de loi ajouté avec succès',
                'bill_id' => $bill['id'],
                'action' => 'created'
            ], 201);
            break;

        case 'update':
            // Check if bill exists
            $checkQuery = "SELECT id FROM bills WHERE id = ?";
            $checkStmt = dbQuery($checkQuery, [$bill['id']]);

            if (!$checkStmt->fetch()) {
                sendErrorResponse('Projet de loi introuvable', 404);
            }

            // Update bill
            $updateQuery = "
                UPDATE bills
                SET title = ?,
                    summary = ?,
                    full_text_url = ?,
                    level = ?,
                    chamber = ?,
                    vote_datetime = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";

            dbQuery($updateQuery, [
                $bill['title'],
                $bill['summary'],
                $bill['full_text_url'] ?? null,
                $bill['level'],
                $bill['chamber'],
                $bill['vote_datetime'],
                $status,
                $bill['id']
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Projet de loi mis à jour avec succès',
                'bill_id' => $bill['id'],
                'action' => 'updated'
            ]);
            break;

        case 'delete':
            // Validate bill ID
            if (empty($bill['id'])) {
                sendErrorResponse('ID du projet de loi requis pour la suppression');
            }

            // Check if bill exists
            $checkQuery = "SELECT id, title FROM bills WHERE id = ?";
            $checkStmt = dbQuery($checkQuery, [$bill['id']]);
            $existingBill = $checkStmt->fetch();

            if (!$existingBill) {
                sendErrorResponse('Projet de loi introuvable', 404);
            }

            // Delete bill (votes will be cascade deleted)
            $deleteQuery = "DELETE FROM bills WHERE id = ?";
            dbQuery($deleteQuery, [$bill['id']]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Projet de loi supprimé avec succès',
                'bill_id' => $bill['id'],
                'bill_title' => $existingBill['title'],
                'action' => 'deleted'
            ]);
            break;

        default:
            sendErrorResponse('Action invalide. Doit être: create, update, ou delete');
    }

} catch (PDOException $e) {
    error_log('Database error in add-bill.php: ' . $e->getMessage());
    sendErrorResponse('Erreur de base de données', 500);
} catch (Exception $e) {
    error_log('Error in add-bill.php: ' . $e->getMessage());
    sendErrorResponse('Une erreur est survenue', 500);
}
