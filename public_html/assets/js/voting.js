/**
 * Constituant - Voting Functionality
 *
 * Handles vote submission, confirmation modals, and UI updates.
 */

// Voting state
const VoteState = {
    pendingVote: null,
    isSubmitting: false
};

/**
 * Initiate a vote (shows confirmation modal)
 * @param {string} billId - Bill ID
 * @param {string} voteType - Vote type: 'for', 'against', or 'abstain'
 * @param {Event} event - Optional event object to prevent default behavior
 */
function initiateVote(billId, voteType, event) {
    // Prevent default button behavior and stop propagation
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    // Find the bill in app state
    const bill = AppState.bills.find(b => b.id === billId);

    if (!bill) {
        showToast('Projet de loi introuvable', 'error');
        return;
    }

    // Store pending vote
    VoteState.pendingVote = {
        billId: billId,
        billTitle: bill.title,
        voteType: voteType
    };

    // Show confirmation modal
    showVoteModal(bill.title, voteType);
}

/**
 * Show vote confirmation modal
 * @param {string} billTitle - Bill title
 * @param {string} voteType - Vote type
 */
function showVoteModal(billTitle, voteType) {
    const modal = document.getElementById('vote-modal');
    const modalMessage = document.getElementById('modal-message');

    if (!modal || !modalMessage) return;

    const voteLabel = getVoteLabel(voteType);
    const voteIcon = getVoteIcon(voteType);

    modalMessage.innerHTML = `
        Êtes-vous sûr de vouloir voter <strong>${voteIcon} ${voteLabel}</strong> pour :<br>
        <em>"${escapeHtml(truncateText(billTitle, 100))}"</em>
    `;

    modal.classList.remove('hidden');

    // Set focus on confirm button for accessibility
    setTimeout(() => {
        document.getElementById('confirm-vote-btn')?.focus();
    }, 100);
}

/**
 * Close vote confirmation modal
 */
function closeVoteModal(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const modal = document.getElementById('vote-modal');
    if (modal) {
        modal.classList.add('hidden');
    }

    VoteState.pendingVote = null;
}

/**
 * Confirm and submit the vote
 */
async function confirmVote(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    if (!VoteState.pendingVote || VoteState.isSubmitting) return;

    const { billId, voteType } = VoteState.pendingVote;

    try {
        VoteState.isSubmitting = true;

        // Disable confirm button
        const confirmBtn = document.getElementById('confirm-vote-btn');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Envoi...';
        }

        // Submit vote
        const response = await fetch('/api/cast-vote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                bill_id: billId,
                vote_type: voteType
            })
        });

        const data = await response.json();

        if (data.success) {
            // Success!
            handleVoteSuccess(billId, voteType, data);
        } else {
            // Error from API
            handleVoteError(data.error || 'Erreur lors du vote');
        }

    } catch (error) {
        console.error('Error submitting vote:', error);
        handleVoteError('Erreur de connexion. Veuillez réessayer.');
    } finally {
        VoteState.isSubmitting = false;

        // Re-enable button
        const confirmBtn = document.getElementById('confirm-vote-btn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirmer';
        }
    }
}

/**
 * Handle successful vote submission
 * @param {string} billId - Bill ID
 * @param {string} voteType - Vote type
 * @param {Object} data - Response data
 */
function handleVoteSuccess(billId, voteType, data) {
    // Close modal
    closeVoteModal();

    // Show success toast
    const voteLabel = getVoteLabel(voteType);
    showToast(`Vote enregistré : ${voteLabel}`, 'success');

    // Update bill card
    updateBillCardAfterVote(billId, voteType);

    // Refresh results from server
    setTimeout(() => {
        loadBills();
    }, 500);

    // Store vote in localStorage (for persistence across page loads)
    storeVoteInLocalStorage(billId, voteType);
}

/**
 * Handle vote error
 * @param {string} errorMessage - Error message
 */
function handleVoteError(errorMessage) {
    // Close modal
    closeVoteModal();

    // Show error toast
    showToast(errorMessage, 'error');
}

/**
 * Update bill card UI after successful vote
 * @param {string} billId - Bill ID
 * @param {string} voteType - Vote type
 */
function updateBillCardAfterVote(billId, voteType) {
    const card = document.querySelector(`[data-bill-id="${billId}"]`);
    if (!card) return;

    // Find vote actions section
    const voteActions = card.querySelector('.vote-actions');
    if (!voteActions) return;

    // Replace with vote indicator
    const voteLabel = getVoteLabel(voteType);
    voteActions.outerHTML = `
        <div class="user-vote-indicator">
            Vous avez voté : <strong>${voteLabel}</strong> ✓
        </div>
    `;

    // Add flash animation
    card.style.transition = 'background-color 0.3s';
    card.style.backgroundColor = '#d4edda'; // Light green

    setTimeout(() => {
        card.style.backgroundColor = '';
    }, 300);

    setTimeout(() => {
        card.style.transition = '';
    }, 600);

    // Update bill in AppState
    const bill = AppState.bills.find(b => b.id === billId);
    if (bill) {
        bill.user_voted = voteType;

        // Optimistically update vote count
        bill.votes[voteType]++;
        bill.votes.total++;

        // Recalculate percentages
        const total = bill.votes.total;
        bill.percentages.for = Math.round((bill.votes.for / total) * 100);
        bill.percentages.against = Math.round((bill.votes.against / total) * 100);
        bill.percentages.abstain = Math.round((bill.votes.abstain / total) * 100);
    }
}

/**
 * Store vote in localStorage
 * @param {string} billId - Bill ID
 * @param {string} voteType - Vote type
 */
function storeVoteInLocalStorage(billId, voteType) {
    try {
        const votes = getVotesFromLocalStorage();
        votes[billId] = {
            type: voteType,
            timestamp: new Date().toISOString()
        };
        localStorage.setItem('constituant_votes', JSON.stringify(votes));
    } catch (error) {
        console.error('Error storing vote in localStorage:', error);
    }
}

/**
 * Get votes from localStorage
 * @returns {Object} Votes object
 */
function getVotesFromLocalStorage() {
    try {
        const votesJson = localStorage.getItem('constituant_votes');
        return votesJson ? JSON.parse(votesJson) : {};
    } catch (error) {
        console.error('Error reading votes from localStorage:', error);
        return {};
    }
}

/**
 * Check if user has voted on a bill (from localStorage)
 * @param {string} billId - Bill ID
 * @returns {string|null} Vote type or null
 */
function hasVotedLocally(billId) {
    const votes = getVotesFromLocalStorage();
    return votes[billId]?.type || null;
}

/**
 * Get vote icon
 * @param {string} voteType - Vote type
 * @returns {string} Icon
 */
function getVoteIcon(voteType) {
    const icons = {
        'for': '👍',
        'against': '👎',
        'abstain': '🤷'
    };
    return icons[voteType] || '';
}

/**
 * Handle keyboard shortcuts
 */
document.addEventListener('keydown', (e) => {
    // Escape key closes modal
    if (e.key === 'Escape') {
        const modal = document.getElementById('vote-modal');
        if (modal && !modal.classList.contains('hidden')) {
            closeVoteModal();
        }
    }

    // Enter key confirms vote (when modal is open)
    if (e.key === 'Enter') {
        const modal = document.getElementById('vote-modal');
        if (modal && !modal.classList.contains('hidden')) {
            e.preventDefault();
            confirmVote();
        }
    }
});

/**
 * Handle modal overlay click
 */
document.addEventListener('DOMContentLoaded', () => {
    const modalOverlay = document.querySelector('.modal-overlay');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', closeVoteModal);
    }
});

/**
 * Prevent accidental page navigation during vote submission
 */
window.addEventListener('beforeunload', (e) => {
    if (VoteState.isSubmitting) {
        e.preventDefault();
        e.returnValue = 'Vote en cours d\'envoi...';
        return e.returnValue;
    }
});

// Make functions available globally
window.initiateVote = initiateVote;
window.closeVoteModal = closeVoteModal;
window.confirmVote = confirmVote;
window.getVoteLabel = getVoteLabel;
window.getVoteIcon = getVoteIcon;
window.hasVotedLocally = hasVotedLocally;
