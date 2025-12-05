<?php
/**
 * Constituant - Admin Panel
 *
 * Simple admin interface to manage bills.
 *
 * @package Constituant
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

startSession();

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $password = $_POST['password'] ?? '';

    if (validateAdminPassword($password)) {
        loginAdmin();
        header('Location: /admin/');
        exit;
    } else {
        $loginError = 'Mot de passe incorrect';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    logoutAdmin();
    header('Location: /admin/');
    exit;
}

// Check if logged in
$isLoggedIn = isAdminLoggedIn();

// Get all bills if logged in
$bills = [];
if ($isLoggedIn) {
    try {
        $query = "
            SELECT
                b.*,
                COUNT(v.id) as total_votes
            FROM bills b
            LEFT JOIN votes v ON b.id = v.bill_id
            GROUP BY b.id
            ORDER BY b.vote_datetime DESC
        ";
        $stmt = dbQuery($query);
        $bills = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Admin error: ' . $e->getMessage());
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo SITE_VERSION; ?>">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #dee2e6;
        }

        .login-form {
            max-width: 400px;
            margin: 4rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #212529;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2E5090;
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a6f;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-danger {
            background: #E63946;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .bills-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .bills-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .bills-table th,
        .bills-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .bills-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .bills-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-eu {
            background: #003399;
            color: white;
        }

        .badge-france {
            background: #0055A4;
            color: white;
        }

        .badge-upcoming {
            background: #ffc107;
            color: #000;
        }

        .badge-voting {
            background: #28a745;
            color: white;
        }

        .badge-completed {
            background: #6c757d;
            color: white;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-dialog {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php if (!$isLoggedIn): ?>
            <!-- Login Form -->
            <div class="login-form">
                <h2>Connexion Admin</h2>
                <?php if (isset($loginError)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary">Se connecter</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Admin Dashboard -->
            <div class="admin-header">
                <h1>Gestion des projets de loi</h1>
                <div>
                    <button onclick="openAddModal()" class="btn btn-success">Ajouter un projet</button>
                    <a href="?logout=1" class="btn btn-secondary">Déconnexion</a>
                </div>
            </div>

            <?php if (empty($bills)): ?>
                <div class="alert alert-success">
                    Aucun projet de loi. Commencez par en ajouter un !
                </div>
            <?php else: ?>
                <div class="bills-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Titre</th>
                                <th>Niveau</th>
                                <th>Date du vote</th>
                                <th>Statut</th>
                                <th>Votes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($bill['id']); ?></code></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($bill['title']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($bill['chamber']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $bill['level']; ?>">
                                            <?php echo strtoupper($bill['level']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDateTime($bill['vote_datetime'], 'd/m/Y H:i'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php
                                            echo $bill['status'] === 'upcoming' ? 'upcoming' :
                                                 ($bill['status'] === 'voting_now' ? 'voting' : 'completed');
                                        ?>">
                                            <?php echo htmlspecialchars($bill['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $bill['total_votes']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick='editBill(<?php echo json_encode($bill); ?>)'
                                                    class="btn btn-primary btn-small">
                                                Modifier
                                            </button>
                                            <button onclick="deleteBill('<?php echo htmlspecialchars($bill['id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($bill['title'], ENT_QUOTES); ?>')"
                                                    class="btn btn-danger btn-small">
                                                Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Add/Edit Bill Modal -->
            <div id="bill-modal" class="modal">
                <div class="modal-dialog">
                    <h2 id="modal-title">Ajouter un projet de loi</h2>
                    <form id="bill-form" onsubmit="submitBill(event)">
                        <input type="hidden" id="bill-action" value="create">

                        <div class="form-group">
                            <label for="bill-id">ID *</label>
                            <input type="text" id="bill-id" required pattern="[a-z0-9-]+"
                                   placeholder="ex: eu-dsa-2024">
                            <small>Lettres minuscules, chiffres et tirets uniquement</small>
                        </div>

                        <div class="form-group">
                            <label for="bill-title">Titre *</label>
                            <input type="text" id="bill-title" required maxlength="500">
                        </div>

                        <div class="form-group">
                            <label for="bill-summary">Résumé *</label>
                            <textarea id="bill-summary" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="bill-url">Lien vers le texte complet</label>
                            <input type="url" id="bill-url" placeholder="https://...">
                        </div>

                        <div class="form-group">
                            <label for="bill-level">Niveau *</label>
                            <select id="bill-level" required>
                                <option value="eu">Union Européenne</option>
                                <option value="france">France</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="bill-chamber">Chambre *</label>
                            <input type="text" id="bill-chamber" required
                                   placeholder="ex: European Parliament, Assemblée Nationale">
                        </div>

                        <div class="form-group">
                            <label for="bill-datetime">Date et heure du vote *</label>
                            <input type="datetime-local" id="bill-datetime" required>
                        </div>

                        <div class="form-group">
                            <label for="bill-status">Statut</label>
                            <select id="bill-status">
                                <option value="upcoming">À venir (upcoming)</option>
                                <option value="voting_now">En cours (voting_now)</option>
                                <option value="completed">Terminé (completed)</option>
                            </select>
                        </div>

                        <div class="action-buttons">
                            <button type="button" onclick="closeModal()" class="btn btn-secondary">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const ADMIN_PASSWORD = '<?php echo ADMIN_PASSWORD; ?>';

        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Ajouter un projet de loi';
            document.getElementById('bill-action').value = 'create';
            document.getElementById('bill-form').reset();
            document.getElementById('bill-id').readOnly = false;
            document.getElementById('bill-modal').classList.add('active');
        }

        function editBill(bill) {
            document.getElementById('modal-title').textContent = 'Modifier le projet de loi';
            document.getElementById('bill-action').value = 'update';
            document.getElementById('bill-id').value = bill.id;
            document.getElementById('bill-id').readOnly = true;
            document.getElementById('bill-title').value = bill.title;
            document.getElementById('bill-summary').value = bill.summary;
            document.getElementById('bill-url').value = bill.full_text_url || '';
            document.getElementById('bill-level').value = bill.level;
            document.getElementById('bill-chamber').value = bill.chamber;
            document.getElementById('bill-datetime').value = bill.vote_datetime.replace(' ', 'T');
            document.getElementById('bill-status').value = bill.status;
            document.getElementById('bill-modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('bill-modal').classList.remove('active');
        }

        async function submitBill(event) {
            event.preventDefault();

            const action = document.getElementById('bill-action').value;
            const bill = {
                id: document.getElementById('bill-id').value.trim(),
                title: document.getElementById('bill-title').value.trim(),
                summary: document.getElementById('bill-summary').value.trim(),
                full_text_url: document.getElementById('bill-url').value.trim() || null,
                level: document.getElementById('bill-level').value,
                chamber: document.getElementById('bill-chamber').value.trim(),
                vote_datetime: document.getElementById('bill-datetime').value.replace('T', ' ') + ':00',
                status: document.getElementById('bill-status').value
            };

            try {
                const response = await fetch('/api/add-bill.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        admin_password: ADMIN_PASSWORD,
                        bill: bill,
                        action: action
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Erreur: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur lors de la communication avec le serveur');
            }
        }

        async function deleteBill(billId, billTitle) {
            if (!confirm(`Êtes-vous sûr de vouloir supprimer "${billTitle}" ?\n\nCette action est irréversible et supprimera également tous les votes associés.`)) {
                return;
            }

            try {
                const response = await fetch('/api/add-bill.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        admin_password: ADMIN_PASSWORD,
                        bill: { id: billId },
                        action: 'delete'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Erreur: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur lors de la communication avec le serveur');
            }
        }

        // Close modal on outside click
        document.getElementById('bill-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'bill-modal') {
                closeModal();
            }
        });
    </script>
</body>
</html>
