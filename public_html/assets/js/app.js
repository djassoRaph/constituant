/**
 * Constituant - Main Application Logic
 *
 * Handles tabs, theme filtering, and bill rendering with France-first ordering.
 */

// Global state
const AppState = {
    bills: [],
    allBills: [],
    activeBills: [],
    pastBills: [],
    currentTab: 'active',
    currentTheme: 'all',
    themes: {},
    loading: false,
    error: null
};

// Legislative themes
const THEMES = [
    'Économie & Finances',
    'Travail & Emploi',
    'Santé',
    'Éducation',
    'Justice',
    'Sécurité & Défense',
    'Environnement & Énergie',
    'Transports & Infrastructures',
    'Agriculture',
    'Culture & Communication',
    'Affaires sociales',
    'Numérique',
    'Affaires européennes',
    'Institutions',
    'Sans catégorie'
];

/**
 * Initialize the application
 */
function initializeApp() {
    console.log('Constituant app initializing...');
    loadBills();

    // Set up periodic refresh (every 30 seconds)
    setInterval(() => {
        if (!AppState.loading) {
            refreshResults();
        }
    }, 30000);
}

/**
 * Load bills from API
 */
async function loadBills() {
    try {
        AppState.loading = true;
        showLoadingState();

        const response = await fetch('/api/get-votes.php?level=all');

        if (!response.ok) {
            throw new Error(`HTTP error ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load bills');
        }

        AppState.allBills = data.bills;
        AppState.error = null;

        // Separate bills by active/past
        separateBillsByStatus();

        // Calculate theme counts
        calculateThemeCounts();

        // Show tabs and render
        showTabsAndContent();
        renderThemeSlider();
        filterAndRenderBills();

    } catch (error) {
        console.error('Error loading bills:', error);
        AppState.error = error.message;
        showErrorState(error.message);
    } finally {
        AppState.loading = false;
    }
}

/**
 * Separate bills into active and past based on vote_datetime
 */
function separateBillsByStatus() {
    const now = new Date();

    AppState.activeBills = AppState.allBills.filter(bill => {
        if (!bill.vote_datetime) return true; // No date = active
        const voteDate = new Date(bill.vote_datetime);
        return voteDate >= now;
    });

    AppState.pastBills = AppState.allBills.filter(bill => {
        if (!bill.vote_datetime) return false;
        const voteDate = new Date(bill.vote_datetime);
        return voteDate < now;
    });

    // Sort both: France first, then EU, by vote_datetime ASC
    AppState.activeBills = sortBillsFranceFirst(AppState.activeBills);
    AppState.pastBills = sortBillsFranceFirst(AppState.pastBills);
}

/**
 * Sort bills with France first, EU last, by vote_datetime
 * @param {Array} bills - Array of bills
 * @returns {Array} Sorted bills
 */
function sortBillsFranceFirst(bills) {
    const franceBills = bills.filter(b => b.level === 'france')
        .sort((a, b) => new Date(a.vote_datetime) - new Date(b.vote_datetime));

    const euBills = bills.filter(b => b.level === 'eu')
        .sort((a, b) => new Date(a.vote_datetime) - new Date(b.vote_datetime));

    return [...franceBills, ...euBills];
}

/**
 * Calculate theme counts for active bills only
 */
function calculateThemeCounts() {
    AppState.themes = { all: AppState.activeBills.length };

    THEMES.forEach(theme => {
        const count = AppState.activeBills.filter(bill => bill.theme === theme).length;
        if (count > 0) {
            AppState.themes[theme] = count;
        }
    });
}

/**
 * Render theme slider
 */
function renderThemeSlider() {
    const slider = document.querySelector('.theme-slider');
    if (!slider) return;

    const themePills = [
        `<button class="theme-pill active" data-theme="all" onclick="filterByTheme('all')" role="radio" aria-checked="true">
            <span>Tous</span>
            <span class="theme-pill-count">${AppState.themes.all || 0}</span>
        </button>`
    ];

    THEMES.forEach(theme => {
        if (AppState.themes[theme]) {
            themePills.push(`
                <button class="theme-pill" data-theme="${escapeHtml(theme)}" onclick="filterByTheme('${escapeHtml(theme)}')" role="radio" aria-checked="false">
                    <span>${escapeHtml(theme)}</span>
                    <span class="theme-pill-count">${AppState.themes[theme]}</span>
                </button>
            `);
        }
    });

    slider.innerHTML = themePills.join('');
}

/**
 * Switch between tabs
 * @param {string} tabName - 'active' or 'past'
 */
function switchTab(tabName) {
    AppState.currentTab = tabName;
    AppState.currentTheme = 'all'; // Reset theme filter when switching tabs

    // Update tab UI
    document.querySelectorAll('.tab-button').forEach(btn => {
        const isActive = btn.dataset.tab === tabName;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive);
    });

    // Show/hide theme slider (only for active tab)
    const themeSliderContainer = document.getElementById('theme-slider-container');
    if (tabName === 'active') {
        themeSliderContainer?.classList.remove('hidden');
    } else {
        themeSliderContainer?.classList.add('hidden');
    }

    // Render bills
    filterAndRenderBills();

    // Announce to screen readers
    announceToScreenReader(`Onglet ${tabName === 'active' ? 'Lois en cours' : 'Votes passés'} sélectionné`);
}

/**
 * Filter by theme
 * @param {string} theme - Theme name or 'all'
 */
function filterByTheme(theme) {
    AppState.currentTheme = theme;

    // Update theme pill UI
    document.querySelectorAll('.theme-pill').forEach(pill => {
        const isActive = pill.dataset.theme === theme;
        pill.classList.toggle('active', isActive);
        pill.setAttribute('aria-checked', isActive);
    });

    // Render filtered bills
    filterAndRenderBills();

    // Announce to screen readers
    if (theme === 'all') {
        announceToScreenReader('Affichage de toutes les lois');
    } else {
        announceToScreenReader(`Filtre appliqué : ${theme}`);
    }
}

/**
 * Filter and render bills based on current tab and theme
 */
function filterAndRenderBills() {
    let billsToShow = AppState.currentTab === 'active' ? AppState.activeBills : AppState.pastBills;

    // Apply theme filter (only for active tab)
    if (AppState.currentTab === 'active' && AppState.currentTheme !== 'all') {
        billsToShow = billsToShow.filter(bill => bill.theme === AppState.currentTheme);
    }

    AppState.bills = billsToShow;
    renderBills();
}

/**
 * Refresh vote results without full reload
 */
async function refreshResults() {
    try {
        const response = await fetch('/api/get-votes.php?level=all');

        if (!response.ok) return;

        const data = await response.json();

        if (data.success && data.bills) {
            // Update state
            AppState.allBills = data.bills;
            separateBillsByStatus();
            calculateThemeCounts();

            // Update tab counts
            updateTabCounts();

            // Update vote counts in UI
            data.bills.forEach(bill => {
                updateBillResults(bill);
            });
        }
    } catch (error) {
        console.error('Error refreshing results:', error);
    }
}

/**
 * Update tab counts
 */
function updateTabCounts() {
    const activeCountEl = document.getElementById('active-count');
    const pastCountEl = document.getElementById('past-count');

    if (activeCountEl) activeCountEl.textContent = AppState.activeBills.length;
    if (pastCountEl) pastCountEl.textContent = AppState.pastBills.length;
}

/**
 * Update bill results in the UI
 * @param {Object} bill - Bill data
 */
function updateBillResults(bill) {
    const card = document.querySelector(`[data-bill-id="${bill.id}"]`);
    if (!card) return;

    // Update vote statistics
    updateVoteStat(card, 'for', bill.votes.for, bill.percentages.for);
    updateVoteStat(card, 'against', bill.votes.against, bill.percentages.against);
    updateVoteStat(card, 'abstain', bill.votes.abstain, bill.percentages.abstain);

    // Update total count
    const totalEl = card.querySelector('.vote-results-title');
    if (totalEl) {
        totalEl.textContent = `Résultats (${bill.votes.total} votes)`;
    }
}

/**
 * Update a single vote statistic
 * @param {HTMLElement} card - Bill card element
 * @param {string} type - Vote type (for, against, abstain)
 * @param {number} count - Vote count
 * @param {number} percentage - Vote percentage
 */
function updateVoteStat(card, type, count, percentage) {
    const valueEl = card.querySelector(`.vote-stat.${type} .vote-stat-value`);
    const fillEl = card.querySelector(`.vote-stat.${type} .progress-fill`);

    if (valueEl) {
        valueEl.textContent = `${count} votes (${percentage}%)`;
    }

    if (fillEl) {
        fillEl.style.width = `${percentage}%`;
    }
}

/**
 * Render bills to the DOM
 */
function renderBills() {
    hideLoadingState();
    hideErrorState();

    const container = document.getElementById('bills-grid');

    if (!container) return;

    if (AppState.bills.length === 0) {
        showEmptyState();
        document.getElementById('bills-container')?.classList.add('hidden');
        return;
    }

    hideEmptyState();
    document.getElementById('bills-container')?.classList.remove('hidden');

    container.innerHTML = AppState.bills.map(bill => createBillCard(bill)).join('');

    // Set up event listeners for "Read more" buttons
    setupReadMoreListeners();
}

/**
 * Create HTML for a bill card
 * @param {Object} bill - Bill data
 * @returns {string} HTML string
 */
function createBillCard(bill) {
    const urgencyClass = `urgency-${bill.urgency.urgency}`;
    const userVoted = bill.user_voted;
    const hasVoted = userVoted !== null;
    const isVoteEnded = AppState.currentTab === 'past';
    const levelFlag = bill.level === 'eu' ? '🇪🇺' : '🇫🇷';
    const levelLabel = bill.level === 'eu' ? 'UE' : 'France';

    return `
        <article class="bill-card ${bill.level} ${isVoteEnded ? 'vote-ended' : ''}" data-bill-id="${bill.id}">
            <div class="bill-header">
                ${bill.theme ? `
                    <span class="theme-badge" data-theme="${escapeHtml(bill.theme)}">
                        ${escapeHtml(bill.theme)}
                    </span>
                ` : ''}

                ${isVoteEnded ? `
                    <div class="vote-ended-badge">
                        ⏱️ Vote terminé
                    </div>
                ` : ''}

                <div class="bill-meta">
                    <span class="bill-meta-item">
                        ${levelFlag} ${levelLabel}
                    </span>
                    <span class="bill-meta-item">
                        ⏰ ${escapeHtml(bill.vote_datetime_formatted)}
                    </span>
                    ${bill.urgency.is_soon && !isVoteEnded ? `
                        <span class="urgency-badge ${urgencyClass}">
                            ${escapeHtml(bill.urgency.label)}
                        </span>
                    ` : ''}
                </div>

                <h3 class="bill-title">${escapeHtml(bill.title)}</h3>

                ${bill.ai_summary ? `
                    <div class="bill-summary">
                        <div class="summary-short" data-bill="${bill.id}">
                            ${escapeHtml(truncateText(bill.ai_summary, 200))}
                        </div>
                        <div class="summary-full" data-bill="${bill.id}">
                            ${escapeHtml(bill.ai_summary)}
                        </div>
                    </div>
                    <button class="read-more-btn" data-bill="${bill.id}" onclick="toggleSummary('${bill.id}')">
                        Lire plus
                    </button>
                ` : bill.summary ? `
                    <div class="bill-summary">
                        <div class="summary-short" data-bill="${bill.id}">
                            ${escapeHtml(truncateText(bill.summary, 150))}
                        </div>
                        <div class="summary-full" data-bill="${bill.id}">
                            ${escapeHtml(bill.summary)}
                        </div>
                    </div>
                    <button class="read-more-btn" data-bill="${bill.id}" onclick="toggleSummary('${bill.id}')">
                        Lire plus
                    </button>
                ` : ''}

                ${bill.full_text_url ? `
                    <a href="${escapeHtml(bill.full_text_url)}"
                       class="bill-link"
                       target="_blank"
                       rel="noopener noreferrer">
                        📄 Lire le texte complet
                    </a>
                ` : ''}
            </div>

            <div class="vote-results">
                <h4 class="vote-results-title">Résultats (${bill.votes.total} votes)</h4>

                <div class="vote-stat for">
                    <div class="vote-stat-header">
                        <span class="vote-stat-label">👍 Pour</span>
                        <span class="vote-stat-value">${bill.votes.for} votes (${bill.percentages.for}%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill for" style="width: ${bill.percentages.for}%"></div>
                    </div>
                </div>

                <div class="vote-stat against">
                    <div class="vote-stat-header">
                        <span class="vote-stat-label">👎 Contre</span>
                        <span class="vote-stat-value">${bill.votes.against} votes (${bill.percentages.against}%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill against" style="width: ${bill.percentages.against}%"></div>
                    </div>
                </div>

                <div class="vote-stat abstain">
                    <div class="vote-stat-header">
                        <span class="vote-stat-label">🤷 Abstention</span>
                        <span class="vote-stat-value">${bill.votes.abstain} votes (${bill.percentages.abstain}%)</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill abstain" style="width: ${bill.percentages.abstain}%"></div>
                    </div>
                </div>
            </div>

            ${hasVoted ? `
                <div class="user-vote-indicator">
                    Vous avez voté : <strong>${getVoteLabel(userVoted)}</strong> ✓
                </div>
            ` : isVoteEnded ? `
                <div class="vote-actions">
                    <button class="vote-btn for" disabled aria-label="Vote terminé">
                        👍 Pour
                    </button>
                    <button class="vote-btn against" disabled aria-label="Vote terminé">
                        👎 Contre
                    </button>
                    <button class="vote-btn abstain" disabled aria-label="Vote terminé">
                        🤷 Abstention
                    </button>
                </div>
            ` : `
                <div class="vote-actions">
                    <button class="vote-btn for"
                            onclick="initiateVote('${bill.id}', 'for')"
                            aria-label="Voter pour">
                        👍 Pour
                    </button>
                    <button class="vote-btn against"
                            onclick="initiateVote('${bill.id}', 'against')"
                            aria-label="Voter contre">
                        👎 Contre
                    </button>
                    <button class="vote-btn abstain"
                            onclick="initiateVote('${bill.id}', 'abstain')"
                            aria-label="S'abstenir">
                        🤷 Abstention
                    </button>
                </div>
            `}
        </article>
    `;
}

/**
 * Toggle summary expansion
 * @param {string} billId - Bill ID
 */
function toggleSummary(billId) {
    const shortSummary = document.querySelector(`.summary-short[data-bill="${billId}"]`);
    const fullSummary = document.querySelector(`.summary-full[data-bill="${billId}"]`);
    const button = document.querySelector(`.read-more-btn[data-bill="${billId}"]`);

    if (!shortSummary || !fullSummary || !button) return;

    const isExpanded = fullSummary.classList.contains('active');

    if (isExpanded) {
        // Collapse
        fullSummary.classList.remove('active');
        shortSummary.style.display = 'block';
        button.textContent = 'Lire plus';
    } else {
        // Expand
        fullSummary.classList.add('active');
        shortSummary.style.display = 'none';
        button.textContent = 'Lire moins';
    }
}

/**
 * Set up event listeners for read more buttons
 */
function setupReadMoreListeners() {
    // Event delegation handled by onclick in HTML
    // This function is here for future enhancements
}

/**
 * Show tabs and content containers
 */
function showTabsAndContent() {
    document.getElementById('tabs-container')?.classList.remove('hidden');
    document.getElementById('theme-slider-container')?.classList.remove('hidden');
    document.getElementById('bills-container')?.classList.remove('hidden');

    // Update tab counts
    updateTabCounts();
}

/**
 * Show loading state
 */
function showLoadingState() {
    document.getElementById('loading')?.classList.remove('hidden');
    document.getElementById('tabs-container')?.classList.add('hidden');
    document.getElementById('theme-slider-container')?.classList.add('hidden');
    document.getElementById('bills-container')?.classList.add('hidden');
    document.getElementById('empty-state')?.classList.add('hidden');
    document.getElementById('error-message')?.classList.add('hidden');
}

/**
 * Hide loading state
 */
function hideLoadingState() {
    document.getElementById('loading')?.classList.add('hidden');
}

/**
 * Show error state
 * @param {string} message - Error message
 */
function showErrorState(message) {
    const errorEl = document.getElementById('error-message');
    const errorText = errorEl?.querySelector('.error-text');

    if (errorEl && errorText) {
        errorText.textContent = message || 'Une erreur est survenue lors du chargement.';
        errorEl.classList.remove('hidden');
    }

    hideLoadingState();
    document.getElementById('tabs-container')?.classList.add('hidden');
    document.getElementById('theme-slider-container')?.classList.add('hidden');
    document.getElementById('bills-container')?.classList.add('hidden');
    document.getElementById('empty-state')?.classList.add('hidden');
}

/**
 * Hide error state
 */
function hideErrorState() {
    document.getElementById('error-message')?.classList.add('hidden');
}

/**
 * Show empty state
 */
function showEmptyState() {
    document.getElementById('empty-state')?.classList.remove('hidden');
}

/**
 * Hide empty state
 */
function hideEmptyState() {
    document.getElementById('empty-state')?.classList.add('hidden');
}

/**
 * Get vote label in French
 * @param {string} voteType - Vote type
 * @returns {string} French label
 */
function getVoteLabel(voteType) {
    const labels = {
        'for': 'Pour',
        'against': 'Contre',
        'abstain': 'Abstention'
    };
    return labels[voteType] || voteType;
}

/**
 * Truncate text to specified length
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} Truncated text
 */
function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;

    const truncated = text.substr(0, maxLength);
    const lastSpace = truncated.lastIndexOf(' ');

    return truncated.substr(0, lastSpace) + '...';
}

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show toast notification
 * @param {string} message - Message to display
 * @param {string} type - Type: 'success', 'error', or default
 */
function showToast(message, type = '') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');

    if (!toast || !toastMessage) return;

    toastMessage.textContent = message;

    toast.className = 'toast';
    if (type) {
        toast.classList.add(type);
    }

    toast.classList.remove('hidden');

    // Auto-hide after 4 seconds
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 4000);
}

/**
 * Announce message to screen readers
 * @param {string} message - Message to announce
 */
function announceToScreenReader(message) {
    const liveRegion = document.getElementById('bills-grid');
    if (liveRegion) {
        liveRegion.setAttribute('aria-label', message);
    }
}

// Make functions available globally for onclick handlers
window.initializeApp = initializeApp;
window.loadBills = loadBills;
window.switchTab = switchTab;
window.filterByTheme = filterByTheme;
window.toggleSummary = toggleSummary;
window.showToast = showToast;
