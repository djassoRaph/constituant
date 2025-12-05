<?php
/**
 * Constituant - Main Landing Page
 *
 * Displays legislative bills from EU and France with voting interface.
 *
 * @package Constituant
 */

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Constituant - Exprimez votre opinion sur les lois débattues au Parlement européen et à l'Assemblée nationale française.">
    <meta name="keywords" content="démocratie, vote, législation, parlement européen, assemblée nationale, france, eu">
    <meta name="author" content="Constituant">

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Constituant - Votre voix sur les lois du jour">
    <meta property="og:description" content="Exprimez votre opinion sur les lois débattues au Parlement européen et à l'Assemblée nationale.">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">

    <title><?php echo SITE_NAME; ?> - <?php echo SITE_TAGLINE; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo SITE_VERSION; ?>">
    <link rel="stylesheet" href="/assets/css/mobile.css?v=<?php echo SITE_VERSION; ?>">

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>
                        <span class="icon" aria-hidden="true">🏛️</span>
                        <span class="site-name"><?php echo SITE_NAME; ?></span>
                    </h1>
                    <p class="tagline"><?php echo SITE_TAGLINE; ?></p>
                </div>
                <nav class="main-nav">
                    <a href="#about" class="nav-link">À propos</a>
                    <a href="/admin/" class="nav-link">Admin</a>
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
                <p>Chargement des votes en cours...</p>
            </div>

            <!-- Error State -->
            <div id="error-message" class="error-state hidden">
                <p class="error-text"></p>
                <button onclick="loadBills()" class="btn btn-secondary">Réessayer</button>
            </div>

            <!-- EU Bills Section -->
            <section id="eu-section" class="bills-section hidden">
                <div class="section-header">
                    <h2>
                        <span class="flag" aria-hidden="true">🇪🇺</span>
                        Union Européenne
                    </h2>
                    <p class="section-description">Votes au Parlement européen</p>
                </div>
                <div id="eu-bills" class="bills-grid">
                    <!-- Bills will be loaded here by JavaScript -->
                </div>
            </section>

            <!-- France Bills Section -->
            <section id="france-section" class="bills-section hidden">
                <div class="section-header">
                    <h2>
                        <span class="flag" aria-hidden="true">🇫🇷</span>
                        France
                    </h2>
                    <p class="section-description">Votes à l'Assemblée nationale</p>
                </div>
                <div id="france-bills" class="bills-grid">
                    <!-- Bills will be loaded here by JavaScript -->
                </div>
            </section>

            <!-- Empty State -->
            <div id="empty-state" class="empty-state hidden">
                <p>Aucun vote en cours actuellement.</p>
                <p class="empty-subtitle">Revenez bientôt pour participer aux prochains votes.</p>
            </div>
        </div>
    </main>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <h2>À propos de Constituant</h2>
            <div class="about-content">
                <p>
                    <strong>Constituant</strong> est une plateforme citoyenne qui vous permet d'exprimer
                    votre opinion sur les lois actuellement débattues au Parlement européen et à
                    l'Assemblée nationale française.
                </p>
                <p>
                    Votre vote est anonyme et sert à mesurer l'opinion publique sur les législations
                    en cours. Les résultats agrégés sont affichés en temps réel.
                </p>
                <div class="about-features">
                    <div class="feature">
                        <span class="feature-icon">🔒</span>
                        <h3>Anonyme</h3>
                        <p>Vos votes sont anonymes et sécurisés</p>
                    </div>
                    <div class="feature">
                        <span class="feature-icon">⚡</span>
                        <h3>Temps réel</h3>
                        <p>Résultats mis à jour instantanément</p>
                    </div>
                    <div class="feature">
                        <span class="feature-icon">🌍</span>
                        <h3>EU & France</h3>
                        <p>Suivez les votes des deux assemblées</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits réservés.</p>
                <nav class="footer-nav">
                    <a href="#about" class="footer-link">À propos</a>
                    <a href="mailto:contact@constituant.fr" class="footer-link">Contact</a>
                    <a href="https://github.com/constituant" class="footer-link" target="_blank" rel="noopener">GitHub</a>
                </nav>
            </div>
            <p class="footer-note">
                Les votes exprimés sur cette plateforme sont indicatifs et ne représentent pas
                les votes officiels des institutions législatives.
            </p>
        </div>
    </footer>

    <!-- Vote Confirmation Modal -->
    <div id="vote-modal" class="modal hidden" role="dialog" aria-labelledby="modal-title" aria-modal="true">
        <div class="modal-overlay" onclick="closeVoteModal()"></div>
        <div class="modal-content">
            <h3 id="modal-title">Confirmer votre vote</h3>
            <p id="modal-message"></p>
            <div class="modal-actions">
                <button onclick="closeVoteModal()" class="btn btn-secondary">Annuler</button>
                <button onclick="confirmVote()" class="btn btn-primary" id="confirm-vote-btn">Confirmer</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast hidden" role="alert" aria-live="polite">
        <span id="toast-message"></span>
    </div>

    <!-- Scripts -->
    <script src="/assets/js/app.js?v=<?php echo SITE_VERSION; ?>"></script>
    <script src="/assets/js/voting.js?v=<?php echo SITE_VERSION; ?>"></script>

    <!-- Initialize app on page load -->
    <script>
        // Load bills when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeApp);
        } else {
            initializeApp();
        }
    </script>

    <!-- No JavaScript Fallback -->
    <noscript>
        <div class="noscript-message">
            <p>
                <strong>JavaScript est désactivé.</strong><br>
                Cette application nécessite JavaScript pour fonctionner.
                Veuillez l'activer dans les paramètres de votre navigateur.
            </p>
        </div>
    </noscript>
</body>
</html>
