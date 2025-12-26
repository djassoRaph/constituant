/**
 * Constituant - Simplified Mobile-First App
 */

// App State
const AppState = {
    bills: [],
    currentTab: 'active',
    loading: false
};

/**
 * Initialize app
 */
function initializeApp() {
    console.log('🏛️ Constituant initializing...');
    loadBills();
}

/**
 * Load bills from API
 */
async function loadBills() {
    try {
        AppState.loading = true;
        showLoading();

        const response = await fetch('/api/get-votes.php?level=all');
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load bills');
        }

        AppState.bills = data.bills || [];
        console.log(`✓ Loaded ${AppState.bills.length} bills`);

        renderApp();

    } catch (error) {
        console.error('Error loading bills:', error);
        showError(error.message);
    } finally {
        AppState.loading = false;
    }
}

/**
 * Render the entire app
 */
function renderApp() {
    hideLoading();
    hideError();

    // Separate active and past bills
    const now = new Date();
    const activeBills = AppState.bills.filter(b => new Date(b.vote_datetime) >= now);
    const pastBills = AppState.bills.filter(b => new Date(b.vote_datetime) < now);

    // Update tab counts
    document.getElementById('active-count').textContent = activeBills.length;
    document.getElementById('past-count').textContent = pastBills.length;

    // Show tabs
    document.getElementById('tabs-container').classList.remove('hidden');

    // Render current tab
    renderBills(AppState.currentTab === 'active' ? activeBills : pastBills);
}

/**
 * Render bills list
 */
function renderBills(bills) {
    const container = document.getElementById('bills-grid');
    
    if (!container) return;

    if (bills.length === 0) {
        showEmpty();
        return;
    }

    hideEmpty();
    document.getElementById('bills-container').classList.remove('hidden');

    // Sort by vote date (soonest first)
    bills.sort((a, b) => new Date(a.vote_datetime) - new Date(b.vote_datetime));

    container.innerHTML = bills.map(bill => createBillCard(bill)).join('');

    // Setup event listeners
    setupReadMoreButtons();
}



/**
 * Toggle bill details (expand/collapse)
 */
function toggleBillDetails(billId, event) {
    // Prevent default button behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const details = document.getElementById(`details-${billId}`);
    const btn = event ? event.currentTarget : document.querySelector(`[onclick*="toggleBillDetails('${billId}')"]`);

    if (!details || !btn) return;

    const isExpanded = btn.getAttribute('aria-expanded') === 'true';

    if (isExpanded) {
        // Collapse
        details.style.display = 'none';
        btn.setAttribute('aria-expanded', 'false');
        btn.querySelector('.expand-text').style.display = '';
        btn.querySelector('.collapse-text').style.display = 'none';
    } else {
        // Expand
        details.style.display = 'block';
        btn.setAttribute('aria-expanded', 'true');
        btn.querySelector('.expand-text').style.display = 'none';
        btn.querySelector('.collapse-text').style.display = '';
    }
}

/**
 * Create HTML for a single bill card
 */
function createBillCard(bill) {
    const isPast = new Date(bill.vote_datetime) < new Date();
    const userVoted = bill.user_voted;
    const hasVoted = userVoted !== null;

    // Format date
    const voteDate = new Date(bill.vote_datetime);
    const dateStr = formatDate(voteDate);

    // Flag emoji
    const flag = bill.level === 'eu' ? '🇪🇺' : '🇫🇷';
    const levelLabel = bill.level === 'eu' ? 'UE' : 'France';

    // Get first sentence of AI summary (max 20 words for preview)
    let shortSummary = bill.ai_summary || bill.summary || 'Aucun résumé disponible';
    const sentences = shortSummary.split(/[.!?]/);
    shortSummary = sentences[0] + (sentences[0] ? '.' : '');
    
    // Truncate to ~20 words
    const words = shortSummary.split(' ');
    if (words.length > 20) {
        shortSummary = words.slice(0, 20).join(' ') + '...';
    }

    // Full summary for expanded view
    const fullSummary = bill.ai_summary || bill.summary || '';

    // Parse AI data
    const aiData = bill.ai_data || {};
    const hasAiData = aiData && (aiData.pour || aiData.contre || aiData.concerne);

    return `
        <article class="bill-card" data-bill-id="${bill.id}">
            <!-- Share Button -->
            <div class="share-btn-wrapper">
                <button type="button" class="share-btn" onclick="event.preventDefault(); event.stopPropagation(); openShareModal('${bill.id}', event);" aria-label="Partager cette loi">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                </button>
            </div>

            <div class="bill-header">
                <div class="bill-meta">
                    <span class="meta-badge ${bill.level}">${flag} ${levelLabel}</span>
                    <span class="meta-badge">⏰ ${dateStr}</span>
                    ${bill.chamber ? `<span class="meta-badge">${bill.chamber}</span>` : ''}
                    ${bill.theme && bill.theme !== 'Sans catégorie' ? `<span class="meta-badge theme-badge">${escapeHtml(bill.theme)}</span>` : ''}
                </div>

                <h3 class="bill-title">
                    ${escapeHtml(bill.title)}
                    ${bill.full_text_url ? `
                        <a href="${escapeHtml(bill.full_text_url)}" target="_blank" rel="noopener noreferrer" class="bill-link" title="Lire le texte complet">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    ` : ''}
                </h3>

                <!-- Short Summary (always visible) -->
                <div class="bill-summary-short">
                    ${escapeHtml(shortSummary)}
                </div>

                <!-- Expand Button -->
                ${hasAiData ? `
                    <button type="button" class="expand-btn" onclick="event.preventDefault(); event.stopPropagation(); toggleBillDetails('${bill.id}', event);" aria-expanded="false" aria-controls="details-${bill.id}">
                        <span class="expand-text">▼ En savoir plus</span>
                        <span class="collapse-text" style="display:none;">▲ Réduire</span>
                    </button>
                ` : ''}

                <!-- Expandable Details (hidden by default) -->
                <div id="details-${bill.id}" class="bill-details" style="display:none;">
                    ${hasAiData ? `
                        <!-- Full Summary -->
                        <div class="detail-section">
                            <h4>📋 Résumé</h4>
                            <p>${escapeHtml(fullSummary)}</p>
                        </div>

                        <!-- Arguments -->
                        ${aiData.pour || aiData.contre ? `
                            <div class="detail-section">
                                <h4>⚖️ Arguments principaux</h4>
                                ${aiData.pour ? `
                                    <div class="argument argument-pour">
                                        <strong>✅ Pour:</strong> ${escapeHtml(aiData.pour)}
                                    </div>
                                ` : ''}
                                ${aiData.contre ? `
                                    <div class="argument argument-contre">
                                        <strong>❌ Contre:</strong> ${escapeHtml(aiData.contre)}
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}

                        <!-- Qui est concerné -->
                        ${aiData.concerne && aiData.concerne.length > 0 ? `
                            <div class="detail-section">
                                <h4>🎯 Qui est concerné?</h4>
                                <ul class="stakeholders-list">
                                    ${aiData.concerne.map(group => `<li>${escapeHtml(group)}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}

                        <!-- Full Text Link -->
                        ${bill.full_text_url ? `
                            <div class="detail-section">
                                <a href="${escapeHtml(bill.full_text_url)}" target="_blank" rel="noopener noreferrer" class="full-text-link">
                                    🔗 Lire le texte complet
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                </a>
                            </div>
                        ` : ''}
                    ` : `
                        <p class="no-ai-data">Résumé détaillé non disponible</p>
                    `}
                </div>
            </div>

            <!-- Vote Buttons / Results (same as before) -->
            ${hasVoted ? `
                <div class="user-voted">
                    ✓ Vous avez voté : <strong>${getVoteLabel(userVoted)}</strong>
                </div>
            ` : !isPast ? `
                <div class="vote-actions">
                    <button type="button" class="vote-btn for" onclick="event.preventDefault(); event.stopPropagation(); initiateVote('${bill.id}', 'for', event);">
                        <span class="vote-btn-icon">👍</span>
                        <span>Pour</span>
                    </button>
                    <button type="button" class="vote-btn against" onclick="event.preventDefault(); event.stopPropagation(); initiateVote('${bill.id}', 'against', event);">
                        <span class="vote-btn-icon">👎</span>
                        <span>Contre</span>
                    </button>
                    <button type="button" class="vote-btn abstain" onclick="event.preventDefault(); event.stopPropagation(); initiateVote('${bill.id}', 'abstain', event);">
                        <span class="vote-btn-icon">🤷</span>
                        <span>Abs.</span>
                    </button>
                </div>
            ` : ''}

            ${(hasVoted || isPast) && bill.votes.total > 0 ? `
                <div class="vote-results">
                    <div class="vote-results-title">Résultats (${bill.votes.total} votes)</div>
                    
                    <div class="vote-stat for">
                        <div class="vote-stat-label">
                            <span>👍 Pour</span>
                            <span>${bill.votes.for} (${bill.percentages.for}%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${bill.percentages.for}%"></div>
                        </div>
                    </div>

                    <div class="vote-stat against">
                        <div class="vote-stat-label">
                            <span>👎 Contre</span>
                            <span>${bill.votes.against} (${bill.percentages.against}%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${bill.percentages.against}%"></div>
                        </div>
                    </div>

                    <div class="vote-stat abstain">
                        <div class="vote-stat-label">
                            <span>🤷 Abstention</span>
                            <span>${bill.votes.abstain} (${bill.percentages.abstain}%)</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${bill.percentages.abstain}%"></div>
                        </div>
                    </div>
                </div>
            ` : ''}
        </article>
    `;
}

/**
 * Switch tab
 */
function switchTab(tab, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    if (AppState.loading) return;

    AppState.currentTab = tab;

    // Update tab buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        const isActive = btn.dataset.tab === tab;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive);
    });

    // Re-render
    renderApp();
}

/**
 * Setup read more buttons
 */
function setupReadMoreButtons() {
    document.querySelectorAll('.read-more-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const billId = this.dataset.bill;
            const summary = document.querySelector(`.summary-short[data-bill="${billId}"]`);
            
            if (summary.classList.contains('expanded')) {
                summary.classList.remove('expanded');
                this.textContent = 'Lire plus';
            } else {
                summary.classList.add('expanded');
                this.textContent = 'Réduire';
            }
        });
    });
}

/**
 * Format date for display
 */
function formatDate(date) {
    const now = new Date();
    const diff = date - now;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor(diff / (1000 * 60 * 60));

    if (days === 0 && hours < 24 && hours >= 0) {
        if (hours === 0) return "Aujourd'hui";
        return `Dans ${hours}h`;
    } else if (days === 1) {
        return "Demain";
    } else if (days > 1 && days < 7) {
        return `Dans ${days} jours`;
    } else {
        // Format as date
        const options = { day: 'numeric', month: 'short', year: 'numeric' };
        return date.toLocaleDateString('fr-FR', options);
    }
}

/**
 * Get vote label
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
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show/hide states
 */
function showLoading() {
    document.getElementById('loading')?.classList.remove('hidden');
    document.getElementById('bills-container')?.classList.add('hidden');
    document.getElementById('tabs-container')?.classList.add('hidden');
}

function hideLoading() {
    document.getElementById('loading')?.classList.add('hidden');
}

function showError(message) {
    const errorEl = document.getElementById('error-message');
    if (errorEl) {
        errorEl.querySelector('.error-text').textContent = message;
        errorEl.classList.remove('hidden');
    }
}

function hideError() {
    document.getElementById('error-message')?.classList.add('hidden');
}

function showEmpty() {
    document.getElementById('empty-state')?.classList.remove('hidden');
    document.getElementById('bills-container')?.classList.add('hidden');
}

function hideEmpty() {
    document.getElementById('empty-state')?.classList.add('hidden');
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = `toast ${type}`;
    toast.classList.remove('hidden');

    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}

// Make functions global
window.initializeApp = initializeApp;
window.switchTab = switchTab;
window.loadBills = loadBills;
window.toggleBillDetails = toggleBillDetails;
window.getVoteLabel = getVoteLabel;
window.escapeHtml = escapeHtml;
window.showToast = showToast;