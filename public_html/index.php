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
            <h3>🏛️ Notre Mission</h3>
            <p>
                <strong>Constituant</strong> est une plateforme citoyenne qui vise à rapprocher les 
                citoyens de leurs institutions démocratiques. Elle permet à chacun d'exprimer son 
                opinion sur les projets de loi débattus au Parlement européen et à l'Assemblée 
                nationale française, de manière simple, anonyme et transparente.
            </p>
            
            <h3>🤔 Pourquoi cette plateforme ?</h3>
            <p>
                Dans une démocratie représentative, les citoyens ne sont consultés qu'occasionnellement 
                lors des élections. Entre-temps, les décisions législatives importantes sont prises 
                sans que l'on puisse exprimer directement notre position sur chaque sujet.
            </p>
            <p>
                <strong>Constituant</strong> propose une approche complémentaire : donner à chaque 
                citoyen la possibilité de voter sur les lois en cours de débat, et ainsi créer une 
                base de données d'opinions citoyennes indépendante et transparente.
            </p>
            
            <div class="mission-box">
                <h3>🎯 Nos Objectifs</h3>
                <ul>
                    <li><strong>Transparence</strong> : Recueillir l'opinion citoyenne de manière ouverte et vérifiable</li>
                    <li><strong>Complémentarité</strong> : Offrir aux élus une vision directe des préoccupations de leurs électeurs</li>
                    <li><strong>Indépendance</strong> : Créer un outil citoyen, libre de toute influence institutionnelle</li>
                    <li><strong>Participation</strong> : Encourager l'engagement civique au-delà des scrutins électoraux</li>
                </ul>
            </div>
            
            <h3>📊 Une Alternative aux Sondages Traditionnels</h3>
            <p>
                Les sondages d'opinion peuvent être influencés par de nombreux facteurs : la formulation 
                des questions, la sélection des répondants, les commanditaires, ou encore l'interprétation 
                des résultats. De plus, les citoyens n'ont généralement pas accès aux méthodologies 
                détaillées ni aux données brutes.
            </p>
            <p>
                <strong>Constituant</strong> adopte une approche différente :
            </p>
            <ul>
                <li>✅ <strong>Questions claires</strong> : Pour ou contre chaque projet de loi, sans ambiguïté</li>
                <li>✅ <strong>Accès libre</strong> : Tout citoyen peut participer, sans sélection préalable</li>
                <li>✅ <strong>Résultats publics</strong> : Les agrégats de votes sont visibles en temps réel</li>
                <li>✅ <strong>Open source</strong> : Le code est ouvert, auditable par tous</li>
                <li>✅ <strong>Indépendance</strong> : Aucun financement institutionnel, aucune influence extérieure</li>
            </ul>
            
            <h3>🚀 Vision à Long Terme</h3>
            <p>
                Cette plateforme a pour ambition de devenir une <strong>association loi 1901</strong>, 
                gérée de manière démocratique par ses adhérents. À terme, les membres de l'association 
                pourront non seulement voter, mais aussi proposer des projets de loi alternatifs, 
                débattre des enjeux législatifs, et créer un espace de réflexion collective sur la 
                gouvernance.
            </p>
            <p>
                L'objectif est de montrer aux élus qu'il existe une demande citoyenne pour une 
                <strong>démocratie plus participative</strong>, où les représentants peuvent prendre 
                en compte l'avis direct de leurs électeurs avant de voter sur des textes qui nous 
                concernent tous.
            </p>
            
            <div class="alpha-notice">
                <strong>⚠️ Version Alpha - Projet Indépendant</strong>
                <p>
                    Ce site est actuellement en phase de développement <span class="italic">version alpha</span>
                    et représente le projet d'une seule personne. Il n'est affilié à aucun parti 
                    politique, aucun gouvernement, ni aucune organisation. 
                </p>
                <p>
                    L'objectif est de tester la faisabilité technique et de mesurer l'intérêt citoyen 
                    avant d'envisager la création d'une association formelle.
                </p>
            </div>
            
            <h3>💡 Principes Fondateurs</h3>
            <div class="principles-grid">
                <div class="principle">
                    <span class="icon">🔓</span>
                    <h4>Transparence</h4>
                    <p>Code ouvert, données publiques, méthode vérifiable</p>
                </div>
                <div class="principle">
                    <span class="icon">⚖️</span>
                    <h4>Neutralité</h4>
                    <p>Aucune influence politique, aucun biais dans les questions</p>
                </div>
                <div class="principle">
                    <span class="icon">🔒</span>
                    <h4>Vie privée</h4>
                    <p>Votes anonymes, pas de collecte de données personnelles</p>
                </div>
                <div class="principle">
                    <span class="icon">🤝</span>
                    <h4>Participation</h4>
                    <p>Ouvert à tous, code modifiable, gouvernance collective</p>
                </div>
            </div>
            
            <h3>🛠️ Comment Participer ?</h3>
            <p>
                Ce projet est <strong>open source</strong> et ouvert à toutes les contributions :
            </p>
            <ul>
                <li><strong>Citoyens</strong> : Votez, partagez, donnez votre avis</li>
                <li><strong>Développeurs</strong> : Contribuez au code, proposez des améliorations</li>
                <li><strong>Juristes</strong> : Aidez à comprendre les textes législatifs</li>
                <li><strong>Communicants</strong> : Faites connaître la plateforme</li>
                <li><strong>Analystes</strong> : Étudiez les données, proposez des visualisations</li>
            </ul>
            
            <div class="participation-cta">
                <h3>Vous voulez participer ?</h3>
                <p>
                    Que vous soyez développeur, designer, juriste, ou simplement citoyen engagé, 
                    votre contribution est la bienvenue !
                </p>
                <div class="cta-buttons">
                    <a href="mailto:contact@constituant.fr" class="btn-primary">
                        📧 Me contacter
                    </a>
                    <a href="https://github.com/constituant" class="btn-secondary" target="_blank" rel="noopener">
                        💻 Voir le code source
                    </a>
                </div>
                <p class="disclaimer">
                    <em>Ce projet est indépendant, non-partisan, et entièrement bénévole.</em>
                </p>
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
                    <a href="https://github.com/djassoRaph/constituant" class="footer-link" target="_blank" rel="noopener">GitHub</a>
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
