/**
 * Constituant - Main Application Logic
 *
 * Handles loading bills, rendering cards, and managing UI state.
 */

// Global state
const AppState = {
    bills: [],
    loading: false,
    error: null
};

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

        AppState.bills = data.bills;
        AppState.error = null;

        renderBills();

    } catch (error) {
        console.error('Error loading bills:', error);
        AppState.error = error.message;
        showErrorState(error.message);
    } finally {
        AppState.loading = false;
    }
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
            AppState.bills = data.bills;

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

    // Separate bills by level
    const euBills = AppState.bills.filter(bill => bill.level === 'eu');
    const franceBills = AppState.bills.filter(bill => bill.level === 'france');

    // Render EU bills
    const euSection = document.getElementById('eu-section');
    const euContainer = document.getElementById('eu-bills');

    if (euBills.length > 0) {
        euContainer.innerHTML = euBills.map(bill => createBillCard(bill)).join('');
        euSection.classList.remove('hidden');
    } else {
        euSection.classList.add('hidden');
    }

    // Render France bills
    const franceSection = document.getElementById('france-section');
    const franceContainer = document.getElementById('france-bills');

    if (franceBills.length > 0) {
        franceContainer.innerHTML = franceBills.map(bill => createBillCard(bill)).join('');
        franceSection.classList.remove('hidden');
    } else {
        franceSection.classList.add('hidden');
    }

    // Show empty state if no bills
    if (euBills.length === 0 && franceBills.length === 0) {
        showEmptyState();
    } else {
        hideEmptyState();
    }

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

    return `
        <article class="bill-card ${bill.level}" data-bill-id="${bill.id}">
            <div class="bill-header">
                <div class="bill-meta">
                    <span class="bill-meta-item">
                        ⏰ ${escapeHtml(bill.vote_datetime_formatted)}
                    </span>
                    ${bill.urgency.is_soon ? `
                        <span class="urgency-badge ${urgencyClass}">
                            ${escapeHtml(bill.urgency.label)}
                        </span>
                    ` : ''}
                </div>

                <h3 class="bill-title">${escapeHtml(bill.title)}</h3>

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
 * Show loading state
 */
function showLoadingState() {
    document.getElementById('loading')?.classList.remove('hidden');
    document.getElementById('eu-section')?.classList.add('hidden');
    document.getElementById('france-section')?.classList.add('hidden');
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
    document.getElementById('eu-section')?.classList.add('hidden');
    document.getElementById('france-section')?.classList.add('hidden');
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
    if (text.length <= maxLength) return text;

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

// Make functions available globally for onclick handlers
window.initializeApp = initializeApp;
window.loadBills = loadBills;
window.toggleSummary = toggleSummary;
window.showToast = showToast;
