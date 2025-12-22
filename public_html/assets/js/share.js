/**
 * Constituant - Social Sharing
 * Twitter, Facebook, Copy Link
 */

let currentShareBill = null;

/**
 * Open share modal
 */
function openShareModal(billId) {
    const bill = AppState.bills.find(b => b.id === billId);
    if (!bill) return;

    currentShareBill = bill;

    // Update modal content
    document.getElementById('share-title').textContent = truncateText(bill.title, 100);
    
    // Generate share URL
    const shareUrl = `${window.location.origin}/?bill=${encodeURIComponent(billId)}`;
    document.getElementById('share-url').value = shareUrl;

    // Show modal
    document.getElementById('share-modal').classList.remove('hidden');
}

/**
 * Close share modal
 */
function closeShareModal() {
    document.getElementById('share-modal').classList.add('hidden');
    currentShareBill = null;
}

/**
 * Share on Twitter
 */
function shareOnTwitter() {
    if (!currentShareBill) return;

    const shareUrl = `${window.location.origin}/?bill=${encodeURIComponent(currentShareBill.id)}`;
    const summary = currentShareBill.ai_summary || currentShareBill.summary || currentShareBill.title;
    const text = `${truncateText(currentShareBill.title, 100)}

${truncateText(summary, 150)}

Votez sur Constituant 👇`;

    const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(shareUrl)}`;
    
    window.open(twitterUrl, '_blank', 'width=550,height=420');
}

/**
 * Share on Facebook
 */
function shareOnFacebook() {
    if (!currentShareBill) return;

    const shareUrl = `${window.location.origin}/?bill=${encodeURIComponent(currentShareBill.id)}`;
    const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
    
    window.open(facebookUrl, '_blank', 'width=550,height=420');
}

/**
 * Copy share link to clipboard
 */
async function copyShareLink() {
    const input = document.getElementById('share-url');
    
    try {
        // Try modern clipboard API
        await navigator.clipboard.writeText(input.value);
        showToast('Lien copié !', 'success');
        closeShareModal();
    } catch (err) {
        // Fallback to select + copy
        input.select();
        input.setSelectionRange(0, 99999); // For mobile
        
        try {
            document.execCommand('copy');
            showToast('Lien copié !', 'success');
            closeShareModal();
        } catch (err) {
            showToast('Erreur lors de la copie', 'error');
        }
    }
}

/**
 * Check if URL contains a bill ID parameter
 * If yes, scroll to that bill and highlight it
 */
function checkForSharedBill() {
    const urlParams = new URLSearchParams(window.location.search);
    const billId = urlParams.get('bill');
    
    if (billId) {
        // Wait for bills to load
        setTimeout(() => {
            const billCard = document.querySelector(`[data-bill-id="${billId}"]`);
            if (billCard) {
                // Scroll to bill
                billCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight briefly
                billCard.style.outline = '3px solid #6A6AF4';
                billCard.style.outlineOffset = '4px';
                
                setTimeout(() => {
                    billCard.style.outline = 'none';
                }, 3000);
            }
        }, 500);
    }
}

/**
 * Truncate text
 */
function truncateText(text, maxLength) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Close modal on Escape key
 */
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const shareModal = document.getElementById('share-modal');
        if (shareModal && !shareModal.classList.contains('hidden')) {
            closeShareModal();
        }
    }
});

// Check for shared bill on page load
window.addEventListener('DOMContentLoaded', checkForSharedBill);

// Make functions global
window.openShareModal = openShareModal;
window.closeShareModal = closeShareModal;
window.shareOnTwitter = shareOnTwitter;
window.shareOnFacebook = shareOnFacebook;
window.copyShareLink = copyShareLink;
