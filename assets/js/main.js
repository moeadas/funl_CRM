/**
 * FUNL CRM — Main JavaScript (Vanilla, no jQuery)
 */

// Override native browser dialogs to keep notifications in-app.
window.alert = function (message) {
    showNotification(message, 'info');
};

// Native confirm() cannot be made truly async, so any remaining synchronous
// confirm() calls are intercepted at the DOM level (see the submit + click
// interceptors below) and routed through the showConfirm() modal instead.
// This stub only exists as a safety net for confirm() calls that are NOT
// wired through an interceptor. It returns false (safe default: do NOT
// perform the action) and surfaces the intended prompt as an in-app modal so
// the user can retry via a properly-wired control. It must never silently
// auto-approve a destructive action.
window.confirm = function (message) {
    console.warn("Native confirm() intercepted; use data-confirm or showConfirm(). Message: " + message);
    showConfirm(message, function () {
        // Nothing to do here — the caller already returned false, so the
        // original action was cancelled. The user must trigger it again;
        // properly-wired controls (data-confirm / interceptors) will run it.
    });
    return false;
};

/**
 * Mobile sidebar toggle.
 *
 * header.php wires both the burger button and the sidebar backdrop to
 * onclick="toggleMobileSidebar()", but the function was never defined — every
 * tap threw "toggleMobileSidebar is not defined" and the menu never opened.
 */
function toggleMobileSidebar() {
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar) return;
    var open = sidebar.classList.toggle('active');
    if (backdrop) backdrop.classList.toggle('active', open);
    // Prevent the page behind the drawer from scrolling while it's open.
    document.body.style.overflow = open ? 'hidden' : '';
}
// Expose for inline onclick handlers.
window.toggleMobileSidebar = toggleMobileSidebar;

document.addEventListener('DOMContentLoaded', function () {
    // ─── Mobile sidebar ───
    const sidebar = document.getElementById('sidebar');
    document.addEventListener('click', function (e) {
        if (sidebar && sidebar.classList.contains('active')) {
            if (!e.target.closest('.sidebar') && !e.target.closest('.mobile-menu-toggle')) {
                sidebar.classList.remove('active');
                var bd = document.getElementById('sidebarBackdrop');
                if (bd) bd.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });

    // ─── Auto-dismiss alerts after 5s (skip hidden ones inside modals) ───
    document.querySelectorAll('.alert').forEach(function (el) {
        // Don't auto-dismiss alerts that are hidden (e.g. inside modals)
        if (el.style.display === 'none' || el.closest('.modal')) return;
        setTimeout(function () {
            el.style.transition = 'opacity 0.3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 5000);
    });

    // ─── Confirm actions (using custom app-level confirm modal) ───
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.dataset.confirmed === 'true') {
                el.dataset.confirmed = 'false'; // Reset flag
                return;
            }
            e.preventDefault();
            showConfirm(el.dataset.confirm, function() {
                el.dataset.confirmed = 'true';
                el.click(); // Re-trigger the click event
            });
        });
    });

    // ─── Global form confirm interceptor (replaces native onsubmit confirm) ───
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.dataset.confirmed === 'true') {
            form.dataset.confirmed = 'false';
            return;
        }

        var onsubmitAttr = form.getAttribute('onsubmit');
        if (onsubmitAttr && onsubmitAttr.indexOf('confirm(') !== -1) {
            e.preventDefault();
            
            // Extract the message from confirm(...)
            var message = 'Are you sure you want to proceed?';
            var match = onsubmitAttr.match(/confirm\(['"](.*?)['"]\)/);
            if (match && match[1]) {
                message = match[1];
            }
            
            showConfirm(message, function () {
                form.dataset.confirmed = 'true';
                form.submit();
            });
        }
    });

    // ─── Global button confirm interceptor (replaces inline onclick confirm) ───
    // Catches <button onclick="return confirm('...')"> that submit a parent form
    // but are NOT inside an onsubmit-guarded form (e.g. super-admin-company,
    // billing "Cancel Subscription"). Without this, the window.confirm stub
    // returns false and the button silently does nothing.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button, a');
        if (!btn) return;
        if (btn.dataset.confirmed === 'true') {
            btn.dataset.confirmed = 'false';
            return;
        }
        var onclickAttr = btn.getAttribute('onclick');
        if (onclickAttr && /confirm\(/.test(onclickAttr) && !/confirmClearLeads|showConfirm/.test(onclickAttr)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var message = 'Are you sure you want to proceed?';
            var match = onclickAttr.match(/confirm\(['"](.*?)['"]\)/);
            if (match && match[1]) message = match[1];
            showConfirm(message, function () {
                btn.dataset.confirmed = 'true';
                // Re-trigger: click the button so it submits its form / runs its handler.
                var form = btn.closest('form');
                if (form) { form.dataset.confirmed = 'true'; form.submit(); }
                else { btn.click(); }
            });
        }
    }, true);

    // ─── Form validation (data-validate) ───
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let valid = true;
            form.querySelectorAll('[required]').forEach(function (field) {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = 'var(--color-danger)';
                } else {
                    field.style.borderColor = '';
                }
            });
            if (!valid) {
                e.preventDefault();
                showNotification('Please fill in all required fields', 'error');
            }
        });
    });
});

/**
 * Show custom app-level confirmation dialog
 */
function showConfirm(message, onConfirm, onCancel) {
    const existing = document.getElementById('app-confirm-modal');
    if (existing) {
        existing.remove();
    }

    const isDanger = message.toLowerCase().includes('delete') || message.toLowerCase().includes('remove') || message.toLowerCase().includes('cancel');
    const btnColor = isDanger ? '#dc2626' : '#2563eb';

    const modalHtml = `
    <div id="app-confirm-modal" style="position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:99999;animation:fadeIn 0.2s ease-out;">
        <div style="background:#fff;border-radius:8px;width:400px;max-width:90%;box-shadow:0 12px 40px rgba(0,0,0,0.15);padding:24px;box-sizing:border-box;animation:scaleIn 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <h3 style="margin:0 0 12px;font-size:16px;font-weight:600;color:#1f2937;font-family:inherit;">Confirm Action</h3>
            <p style="margin:0 0 24px;font-size:14px;color:#4b5563;line-height:1.5;font-family:inherit;">${escapeHtml(message)}</p>
            <div style="display:flex;justify-content:flex-end;gap:12px;">
                <button id="app-confirm-cancel-btn" style="background:#fff;border:1px solid #d1d5db;color:#374151;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition:background 0.2s;font-family:inherit;">Cancel</button>
                <button id="app-confirm-ok-btn" style="background:${btnColor};color:#fff;border:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition:background 0.2s;font-family:inherit;">Confirm</button>
            </div>
        </div>
    </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = modalHtml;
    const modalEl = div.firstElementChild;
    document.body.appendChild(modalEl);

    // Add styles if not present
    if (!document.getElementById('app-confirm-styles')) {
        const style = document.createElement('style');
        style.id = 'app-confirm-styles';
        style.textContent = `
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes scaleIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        `;
        document.head.appendChild(style);
    }

    const cancelBtn = modalEl.querySelector('#app-confirm-cancel-btn');
    const okBtn = modalEl.querySelector('#app-confirm-ok-btn');

    cancelBtn.addEventListener('click', function() {
        modalEl.remove();
        if (typeof onCancel === 'function') onCancel();
    });

    okBtn.addEventListener('click', function() {
        modalEl.remove();
        if (typeof onConfirm === 'function') onConfirm();
    });

    // Close on overlay click
    modalEl.addEventListener('click', function(e) {
        if (e.target === modalEl) {
            modalEl.remove();
            if (typeof onCancel === 'function') onCancel();
        }
    });

    // Escape key handling
    const handleKeydown = function(e) {
        if (e.key === 'Escape') {
            modalEl.remove();
            document.removeEventListener('keydown', handleKeydown);
            if (typeof onCancel === 'function') onCancel();
        }
    };
    document.addEventListener('keydown', handleKeydown);
}

/**
 * Show notification toast
 */
function showNotification(message, type) {
    type = type || 'info';
    var icons = {
        success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    var div = document.createElement('div');
    div.className = 'alert alert-' + type;
    div.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;max-width:380px;animation:modalIn 0.25s ease-out;box-shadow:0 8px 30px rgba(0,0,0,0.12);';
    div.innerHTML = (icons[type] || '') + '<span>' + escapeHtml(message) + '</span>';
    document.body.appendChild(div);
    setTimeout(function () {
        div.style.transition = 'opacity 0.3s';
        div.style.opacity = '0';
        setTimeout(function () { div.remove(); }, 300);
    }, 4000);
}

/**
 * Show alert (alias for backward compat)
 */
function showAlert(message, type) {
    showNotification(message, type);
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

/**
 * Format date
 */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    var d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Delete lead
 */
function deleteLead(leadId, csrfToken) {
    showConfirm('Are you sure you want to delete this lead?', function() {
        fetch('/api/leads.php?action=delete&id=' + leadId, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showNotification('Lead deleted successfully', 'success');
                var row = document.querySelector('tr[data-lead-id="' + leadId + '"]');
                if (row) row.remove();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(function () { showNotification('Failed to delete lead', 'error'); });
    });
}

/**
 * Update lead status
 */
function updateLeadStatus(leadId, status, csrfToken) {
    fetch('/api/leads.php?action=status', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lead_id: leadId, status: status, csrf_token: csrfToken })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            showNotification('Status updated', 'success');
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(function () { showNotification('Failed to update status', 'error'); });
}

/**
 * Export data
 */
function exportToCSV(type) {
    window.location.href = '/api/export.php?type=' + type;
}
