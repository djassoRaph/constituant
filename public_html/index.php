<?php
/**
 * Constituant - Modern Landing Page
 * French Government Design + Social Media Friendly
 */

require_once __DIR__ . '/../cron/lib/config/config.php';
require_once __DIR__ . '/../cron/lib/config/database.php';

// Check if a specific bill is being shared
$billId = $_GET['bill'] ?? null;
$billData = null;

if ($billId) {
    try {
        $stmt = dbQuery("SELECT id, title, summary, ai_summary FROM bills WHERE id = ?", [$billId]);
        $billData = $stmt->fetch();
    } catch (Exception $e) {
        error_log('Error fetching bill for OG tags: ' . $e->getMessage());
    }
}

// Set meta tag values
if ($billData) {
    $pageTitle = htmlspecialchars($billData['title'], ENT_QUOTES, 'UTF-8');
    $pageDescription = htmlspecialchars($billData['ai_summary'] ?: $billData['summary'], ENT_QUOTES, 'UTF-8');
    $pageUrl = SITE_URL . '/?bill=' . urlencode($billData['id']);
} else {
    $pageTitle = SITE_NAME . ' - ' . SITE_TAGLINE;
    $pageDescription = 'Exprimez votre opinion sur les lois en cours de débat';
    $pageUrl = SITE_URL;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Constituant - Votez sur les lois débattues à l'Assemblée nationale et au Parlement européen">
    <meta name="keywords" content="démocratie, vote, législation, assemblée nationale, parlement européen">

    <!-- Open Graph / Twitter Card -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Constituant">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.png">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $pageDescription; ?>">
    <meta name="twitter:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.png">

    <title><?php echo SITE_NAME; ?> - <?php echo SITE_TAGLINE; ?></title>

    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏛️</text></svg>">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo SITE_VERSION; ?>">
    
    <!-- Preconnect -->
    <link rel="preconnect" href="https://api.mistral.ai">
</head>
<body>
    <!-- Header -->
    <header class="site-header" id="site-header">
        <button type="button" class="header-toggle" id="header-toggle" onclick="toggleHeader()" aria-label="Masquer/Afficher l'en-tête">
            <span class="toggle-icon-hide">▲</span>
            <span class="toggle-icon-show" style="display: none;">▼</span>
        </button>
        <div class="header-content" id="header-content">
            <div class="container">
                <div class="logo">
                    <h1>
                        <span class="logo-icon">🏛️</span>
                        <span class="logo-text"><?php echo SITE_NAME; ?></span>
                    </h1>
                    <p class="tagline"><?php echo SITE_TAGLINE; ?></p>
                    <p>Une plateforme citoyenne pour exprimer votre opinion sur les lois débattues à l'Assemblée nationale et plus tard au Parlement européen.</p>
                    <p>⚠️En version alpha expérimentale. Utilisez-la sans risques et partagez vos retours !⚠️ o2switch</p>
                </div>
                <nav class="header-nav">
                    <a href="#about" class="nav-link">À propos</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            
            <!-- Loading State -->
            <div id="loading" class="loading-state">
                <div class="spinner"></div>
                <p>Chargement des lois en cours...</p>
            </div>

            <!-- Error State -->
            <div id="error-state" class="error-state hidden">
                <p class="error-message"></p>
                <button type="button" onclick="loadBills()" class="btn-retry">Réessayer</button>
            </div>

            <!-- Tabs -->
            <div id="tabs-container" class="tabs-container hidden">
                <button type="button" class="tab active" data-tab="active" onclick="event.preventDefault(); event.stopPropagation(); switchTab('active', event);">
                    <span class="tab-icon">📋</span>
                    <span class="tab-label">En cours</span>
                    <span class="tab-badge" id="active-count">0</span>
                </button>
                <button type="button" class="tab" data-tab="past" onclick="event.preventDefault(); event.stopPropagation(); switchTab('past', event);">
                    <span class="tab-icon">📜</span>
                    <span class="tab-label">Terminés</span>
                    <span class="tab-badge" id="past-count">0</span>
                </button>
            </div>

            <!-- Theme Filters -->
            <div id="filter-container" class="filter-container"></div>

            <!-- Bills Grid -->
            <div id="bills-container" class="bills-container hidden">
                <div id="bills-grid" class="bills-grid">
                    <!-- Bills loaded by JavaScript -->
                </div>
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="empty-state hidden">
                <div class="empty-icon">📭</div>
                <p class="empty-title">Aucune loi disponible</p>
                <p class="empty-subtitle">Revenez bientôt pour voter sur les prochains projets de loi</p>
            </div>

        </div>
    </main>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="about-header">
                <h2>À propos de Constituant</h2>
                <p class="about-lead">
                    Une plateforme citoyenne pour exprimer votre opinion sur les lois débattues 
                    à l'Assemblée nationale et au Parlement européen.
                </p>
            </div>

            <div class="about-grid">
                <div class="about-card">
                    <div class="about-card-icon">🔓</div>
                    <h3>Transparence</h3>
                    <p>Code ouvert, données publiques, résultats en temps réel</p>
                </div>
                <div class="about-card">
                    <div class="about-card-icon">🔒</div>
                    <h3>Anonymat</h3>
                    <p>Votes anonymes, aucune donnée personnelle collectée</p>
                </div>
                <div class="about-card">
                    <div class="about-card-icon">⚖️</div>
                    <h3>Neutralité</h3>
                    <p>Indépendant de tout parti ou gouvernement</p>
                </div>
                <div class="about-card">
                    <div class="about-card-icon">🤝</div>
                    <h3>Participation</h3>
                    <p>Ouvert à tous, contribution collective bienvenue</p>
                </div>
            </div>

            <div class="about-cta">
                <p><strong>Version Alpha</strong> - Projet expérimental et indépendant</p>
                <div class="about-links">
                    <a href="mailto:contact@constituant.fr" class="btn-outline">Contact</a>
                    <a href="https://github.com/djassoRaph/constituant" class="btn-outline" target="_blank">GitHub</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Constituant. Projet open source et indépendant.</p>
            <p class="footer-disclaimer">
                Les votes sont indicatifs et ne représentent pas les votes officiels des institutions.
            </p>
        </div>
    </footer>

    <!-- Vote Modal -->
    <div id="vote-modal" class="modal hidden">
        <div class="modal-overlay" onclick="event.preventDefault(); event.stopPropagation(); closeVoteModal(event);"></div>
        <div class="modal-content">
            <h3 id="modal-title">Confirmer votre vote</h3>
            <p id="modal-message"></p>
            <div class="modal-actions">
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); closeVoteModal(event);" class="btn-secondary">Annuler</button>
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); confirmVote(event);" class="btn-primary" id="confirm-vote-btn">Confirmer</button>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="share-modal" class="modal hidden">
        <div class="modal-overlay" onclick="event.preventDefault(); event.stopPropagation(); closeShareModal(event);"></div>
        <div class="modal-content share-modal-content">
            <button type="button" class="modal-close" onclick="event.preventDefault(); event.stopPropagation(); closeShareModal(event);">×</button>
            <h3>Partager cette loi</h3>
            <p id="share-title" class="share-bill-title"></p>

            <div class="share-buttons">
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); shareOnTwitter(event);" class="share-btn twitter">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    Twitter
                </button>
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); shareOnFacebook(event);" class="share-btn facebook">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Facebook
                </button>
                <button type="button" onclick="event.preventDefault(); event.stopPropagation(); copyShareLink(event);" class="share-btn copy">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    Copier le lien
                </button>
            </div>

            <input type="text" id="share-url" class="share-url" readonly>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast hidden"></div>

    <!-- Scripts -->
    <script src="/assets/js/app.js?v=<?php echo SITE_VERSION; ?>"></script>
    <script src="/assets/js/voting.js?v=<?php echo SITE_VERSION; ?>"></script>
    <script src="/assets/js/share.js?v=<?php echo SITE_VERSION; ?>"></script>

    <script>
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeApp);
        } else {
            initializeApp();
        }
    </script>

    <noscript>
        <div class="noscript">
            <p><strong>JavaScript requis</strong></p>
            <p>Cette application nécessite JavaScript pour fonctionner.</p>
        </div>
    </noscript>
</body>
</html>