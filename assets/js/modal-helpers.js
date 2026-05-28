/**
 * Global Modal Helpers
 * Enhanced modal functionality with focus trapping and accessibility
 */

function openModal(overlayId) {
    const el = document.getElementById(overlayId);
    if (!el) return;
    el.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Focus first input after animation
    setTimeout(() => {
        const first = el.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
        if (first) first.focus();
    }, 220);
    
    // Trap focus within modal
    trapFocus(el);
}

function closeModal(overlayId) {
    const el = document.getElementById(overlayId);
    if (!el) return;
    el.classList.remove('active');
    document.body.style.overflow = '';
    
    // Return focus to trigger button if stored
    if (el._triggerButton) {
        el._triggerButton.focus();
        el._triggerButton = null;
    }
}

function trapFocus(modalElement) {
    const focusableElements = modalElement.querySelectorAll(
        'button:not([disabled]), [href], input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    
    if (focusableElements.length === 0) return;
    
    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];
    
    modalElement.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    e.preventDefault();
                    lastFocusable.focus();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    e.preventDefault();
                    firstFocusable.focus();
                }
            }
        }
    });
}

// Close on backdrop click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeModal(e.target.id);
    }
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => closeModal(m.id));
    }
});

// Store trigger button for focus return
document.addEventListener('click', function(e) {
    const trigger = e.target.closest('[data-modal-trigger]');
    if (trigger) {
        const modalId = trigger.dataset.modalTrigger;
        const modal = document.getElementById(modalId);
        if (modal) modal._triggerButton = trigger;
    }
});
