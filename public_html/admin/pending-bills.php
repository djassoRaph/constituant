<?php
/**
 * Constituant - Admin Pending Bills Review
 *
 * Review and approve/reject bills from automated imports.
 *
 * @package Constituant
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

startSession();

// Must be logged in
if (!isAdminLoggedIn()) {
    header('Location: /admin/');
    exit;
}

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de sécurité invalide';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $billId = (int)($_POST['bill_id'] ?? 0);

        try {
            if ($action === 'approve') {
                $result = approvePendingBill($billId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } elseif ($action === 'reject') {
                $result = rejectPendingBill($billId, $_POST['notes'] ?? '');
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } elseif ($action === 'edit_approve') {
                $result = editAndApproveBill($billId, $_POST);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            }
        } catch (Exception $e) {
            $message = 'Erreur: ' . $e->getMessage();
            $messageType = 'error';
            error_log('Admin pending bills error: ' . $e->getMessage());
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'pending';
$source = $_GET['source'] ?? '';

// Get pending bills
$pendingBills = getPendingBills($filter, $source);

// Get statistics
$stats = getPendingBillsStats();

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projets de loi en attente - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo SITE_VERSION; ?>">
    <style>
        .pending-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }

        .filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .bill-card {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .bill-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-france { background: #cfe2ff; color: #084298; }
        .badge-eu { background: #fff3cd; color: #664d03; }
        .badge-nosdeputes { background: #d1e7dd; color: #0f5132; }
        .badge-lafabrique { background: #e2d9f3; color: #432874; }
        .badge-eu-parliament { background: #fff3cd; color: #664d03; }

        .bill-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .bill-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .bill-summary {
            margin: 1rem 0;
            line-height: 1.6;
        }

        .bill-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-edit { background: #007bff; color: white; }
        .btn-details { background: #6c757d; color: white; }

        .btn:hover { opacity: 0.9; }

        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .message-success { background: #d1e7dd; color: #0f5132; }
        .message-error { background: #f8d7da; color: #842029; }

        .edit-form {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
            display: none;
        }

        .edit-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .raw-data {
            margin-top: 1rem;
            padding: 1rem;
            background: #f1f3f5;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
        }

        .no-bills {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="admin-container" style="max-width: 1400px; margin: 2rem auto; padding: 0 1rem;">
        <div class="pending-header">
            <div>
                <h1>Projets de loi en attente</h1>
                <p>Examiner et approuver les imports automatiques</p>
            </div>
            <a href="/admin/" class="btn" style="background: #6c757d; color: white;">← Retour au tableau de bord</a>
        </div>

        <?php if ($message): ?>
            <div class="message message-<?php echo sanitizeOutput($messageType); ?>">
                <?php echo sanitizeOutput($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>En attente</h3>
                <div class="value"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Approuvés</h3>
                <div class="value"><?php echo $stats['approved']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejetés</h3>
                <div class="value"><?php echo $stats['rejected']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total</h3>
                <div class="value"><?php echo $stats['total']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                En attente (<?php echo $stats['pending']; ?>)
            </a>
            <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                Approuvés (<?php echo $stats['approved']; ?>)
            </a>
            <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                Rejetés (<?php echo $stats['rejected']; ?>)
            </a>
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                Tous (<?php echo $stats['total']; ?>)
            </a>
        </div>

        <!-- Bills List -->
        <?php if (empty($pendingBills)): ?>
            <div class="no-bills">
                <h3>Aucun projet de loi <?php echo $filter === 'all' ? '' : $filter; ?></h3>
                <p>Les imports automatiques apparaîtront ici pour validation.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingBills as $bill): ?>
                <div class="bill-card" id="bill-<?php echo $bill['id']; ?>">
                    <div class="bill-header">
                        <div>
                            <span class="bill-badge badge-<?php echo $bill['level']; ?>">
                                <?php echo strtoupper($bill['level']); ?>
                            </span>
                            <span class="bill-badge badge-<?php echo sanitizeOutput($bill['source']); ?>">
                                <?php echo sanitizeOutput($bill['source']); ?>
                            </span>
                        </div>
                        <small style="color: #666;">
                            Importé le <?php echo date('d/m/Y H:i', strtotime($bill['fetched_at'])); ?>
                        </small>
                    </div>

                    <h2 class="bill-title"><?php echo sanitizeOutput($bill['title']); ?></h2>

                    <div class="bill-meta">
                        <?php if ($bill['chamber']): ?>
                            <span><strong>Chambre:</strong> <?php echo sanitizeOutput($bill['chamber']); ?></span>
                        <?php endif; ?>
                        <?php if ($bill['vote_datetime']): ?>
                            <span><strong>Vote prévu:</strong> <?php echo date('d/m/Y H:i', strtotime($bill['vote_datetime'])); ?></span>
                        <?php endif; ?>
                        <span><strong>ID externe:</strong> <?php echo sanitizeOutput($bill['external_id']); ?></span>
                    </div>

                    <?php if ($bill['summary']): ?>
                        <div class="bill-summary">
                            <?php echo nl2br(sanitizeOutput($bill['summary'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($bill['full_text_url']): ?>
                        <p>
                            <a href="<?php echo sanitizeOutput($bill['full_text_url']); ?>" target="_blank" rel="noopener">
                                📄 Voir le texte complet →
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php if ($bill['status'] === 'pending'): ?>
                        <!-- Action Buttons -->
                        <div class="bill-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                <button type="submit" class="btn btn-approve" onclick="return confirm('Approuver ce projet de loi ?')">
                                    ✓ Approuver
                                </button>
                            </form>

                            <button class="btn btn-edit" onclick="toggleEdit(<?php echo $bill['id']; ?>)">
                                ✏ Modifier puis approuver
                            </button>

                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                <button type="submit" class="btn btn-reject" onclick="return confirm('Rejeter ce projet de loi ?')">
                                    ✗ Rejeter
                                </button>
                            </form>

                            <button class="btn btn-details" onclick="toggleRawData(<?php echo $bill['id']; ?>)">
                                🔍 Données brutes
                            </button>
                        </div>

                        <!-- Edit Form -->
                        <div class="edit-form" id="edit-<?php echo $bill['id']; ?>">
                            <h4>Modifier avant d'approuver</h4>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="edit_approve">
                                <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">

                                <div class="form-group">
                                    <label>ID unique (pour l'URL):</label>
                                    <input type="text" name="id" value="<?php echo generateBillId($bill); ?>" required pattern="[a-z0-9-]+" title="Lettres minuscules, chiffres et tirets uniquement">
                                </div>

                                <div class="form-group">
                                    <label>Titre:</label>
                                    <input type="text" name="title" value="<?php echo sanitizeOutput($bill['title']); ?>" required maxlength="500">
                                </div>

                                <div class="form-group">
                                    <label>Résumé:</label>
                                    <textarea name="summary" required><?php echo sanitizeOutput($bill['summary']); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Date et heure du vote:</label>
                                    <input type="datetime-local" name="vote_datetime" value="<?php echo $bill['vote_datetime'] ? date('Y-m-d\TH:i', strtotime($bill['vote_datetime'])) : ''; ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Niveau:</label>
                                    <select name="level" required>
                                        <option value="france" <?php echo $bill['level'] === 'france' ? 'selected' : ''; ?>>France</option>
                                        <option value="eu" <?php echo $bill['level'] === 'eu' ? 'selected' : ''; ?>>EU</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Chambre:</label>
                                    <input type="text" name="chamber" value="<?php echo sanitizeOutput($bill['chamber']); ?>" maxlength="100">
                                </div>

                                <div class="form-group">
                                    <label>URL du texte complet:</label>
                                    <input type="url" name="full_text_url" value="<?php echo sanitizeOutput($bill['full_text_url']); ?>" maxlength="500">
                                </div>

                                <div class="bill-actions">
                                    <button type="submit" class="btn btn-approve">Sauvegarder et approuver</button>
                                    <button type="button" class="btn btn-details" onclick="toggleEdit(<?php echo $bill['id']; ?>)">Annuler</button>
                                </div>
                            </form>
                        </div>

                        <!-- Raw Data -->
                        <div class="raw-data" id="raw-<?php echo $bill['id']; ?>" style="display: none;">
                            <pre><?php echo sanitizeOutput(json_encode(json_decode($bill['raw_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                        </div>
                    <?php else: ?>
                        <p style="margin-top: 1rem; color: #666;">
                            <strong>Statut:</strong> <?php echo ucfirst($bill['status']); ?>
                            <?php if ($bill['reviewed_at']): ?>
                                le <?php echo date('d/m/Y H:i', strtotime($bill['reviewed_at'])); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($bill['notes']): ?>
                            <p style="font-style: italic;"><?php echo sanitizeOutput($bill['notes']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function toggleEdit(billId) {
            const editForm = document.getElementById('edit-' + billId);
            editForm.classList.toggle('active');
        }

        function toggleRawData(billId) {
            const rawData = document.getElementById('raw-' + billId);
            rawData.style.display = rawData.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>

<?php

/**
 * Get pending bills with optional filtering
 */
function getPendingBills(string $status = 'pending', string $source = ''): array
{
    $query = "SELECT * FROM pending_bills WHERE 1=1";
    $params = [];

    if ($status !== 'all') {
        $query .= " AND status = :status";
        $params[':status'] = $status;
    }

    if (!empty($source)) {
        $query .= " AND source = :source";
        $params[':source'] = $source;
    }

    $query .= " ORDER BY fetched_at DESC";

    return dbQuery($query, $params)->fetchAll();
}

/**
 * Get statistics for pending bills
 */
function getPendingBillsStats(): array
{
    $query = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM pending_bills
    ";

    return dbQuery($query)->fetch();
}

/**
 * Approve a pending bill (copy to bills table)
 */
function approvePendingBill(int $billId): array
{
    try {
        // Get pending bill
        $bill = dbQuery("SELECT * FROM pending_bills WHERE id = :id", [':id' => $billId])->fetch();

        if (!$bill) {
            return ['success' => false, 'message' => 'Projet de loi non trouvé'];
        }

        if ($bill['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Ce projet a déjà été traité'];
        }

        // Generate unique ID for bills table
        $uniqueId = generateBillId($bill);

        // Check if ID already exists
        $existing = dbQuery("SELECT id FROM bills WHERE id = :id", [':id' => $uniqueId])->fetch();
        if ($existing) {
            $uniqueId .= '-' . time();
        }

        // Insert into bills table
        dbQuery("
            INSERT INTO bills (id, title, summary, full_text_url, level, chamber, vote_datetime, status)
            VALUES (:id, :title, :summary, :url, :level, :chamber, :vote_datetime, 'upcoming')
        ", [
            ':id' => $uniqueId,
            ':title' => $bill['title'],
            ':summary' => $bill['summary'],
            ':url' => $bill['full_text_url'],
            ':level' => $bill['level'],
            ':chamber' => $bill['chamber'],
            ':vote_datetime' => $bill['vote_datetime'] ?? date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        // Update pending bill status
        dbQuery("
            UPDATE pending_bills
            SET status = 'approved', reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ", [':id' => $billId]);

        return ['success' => true, 'message' => 'Projet de loi approuvé avec succès'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Reject a pending bill
 */
function rejectPendingBill(int $billId, string $notes = ''): array
{
    try {
        dbQuery("
            UPDATE pending_bills
            SET status = 'rejected', reviewed_at = CURRENT_TIMESTAMP, notes = :notes
            WHERE id = :id AND status = 'pending'
        ", [
            ':id' => $billId,
            ':notes' => $notes,
        ]);

        return ['success' => true, 'message' => 'Projet de loi rejeté'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Edit and approve a bill
 */
function editAndApproveBill(int $billId, array $data): array
{
    try {
        // Validate
        $requiredFields = ['id', 'title', 'summary', 'vote_datetime', 'level'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Champ requis manquant: $field"];
            }
        }

        // Check if ID exists
        $existing = dbQuery("SELECT id FROM bills WHERE id = :id", [':id' => $data['id']])->fetch();
        if ($existing) {
            return ['success' => false, 'message' => 'Un projet avec cet ID existe déjà'];
        }

        // Insert into bills table
        dbQuery("
            INSERT INTO bills (id, title, summary, full_text_url, level, chamber, vote_datetime, status)
            VALUES (:id, :title, :summary, :url, :level, :chamber, :vote_datetime, 'upcoming')
        ", [
            ':id' => $data['id'],
            ':title' => $data['title'],
            ':summary' => $data['summary'],
            ':url' => $data['full_text_url'] ?? null,
            ':level' => $data['level'],
            ':chamber' => $data['chamber'] ?? null,
            ':vote_datetime' => $data['vote_datetime'],
        ]);

        // Update pending bill
        dbQuery("
            UPDATE pending_bills
            SET status = 'approved', reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ", [':id' => $billId]);

        return ['success' => true, 'message' => 'Projet de loi modifié et approuvé'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Generate a unique bill ID from pending bill data
 */
function generateBillId(array $bill): string
{
    $prefix = $bill['level'] === 'eu' ? 'eu' : 'fr';

    // Try to extract a meaningful slug from title
    $slug = strtolower($bill['title']);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 40);

    // Add year
    $year = date('Y');

    return $prefix . '-' . $slug . '-' . $year;
}
