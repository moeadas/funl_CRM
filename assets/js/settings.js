/**
 * Settings page behaviour.
 *
 * Tab persistence: saving the settings form does a full POST + redirect, which
 * previously always dropped the user back on the first tab ("Company Profile").
 * We remember the active tab and restore it after the reload, so a save keeps
 * you where you were.
 */
(function () {
    var STORAGE_KEY = 'settings_active_tab';

    function currentTabFromHash() {
        var h = (window.location.hash || '').replace(/^#/, '');
        return h && document.getElementById('pane-' + h) ? h : null;
    }

    function rememberTab(tabId) {
        if (!tabId) return;
        try { sessionStorage.setItem(STORAGE_KEY, tabId); } catch (e) {}
        // Keep the URL in sync so a refresh / back-button lands on the same tab.
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + tabId);
        }
    }

    function storedTab() {
        try {
            var t = sessionStorage.getItem(STORAGE_KEY);
            return t && document.getElementById('pane-' + t) ? t : null;
        } catch (e) { return null; }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Only run on the settings page.
        if (!document.getElementById('settingsForm')) return;

        // Record the tab whenever the user switches.
        document.querySelectorAll('.tab-link[data-tab]').forEach(function (link) {
            link.addEventListener('click', function () {
                rememberTab(link.getAttribute('data-tab'));
            });
        });

        // Record the tab at submit time so the post-save reload restores it.
        var form = document.getElementById('settingsForm');
        form.addEventListener('submit', function () {
            var active = document.querySelector('.tab-link.active[data-tab]');
            if (active) rememberTab(active.getAttribute('data-tab'));
        });

        // Restore: URL hash wins, then the remembered tab.
        var restore = currentTabFromHash() || storedTab();
        if (restore && typeof window.switchTab === 'function') {
            window.switchTab(restore);
        }
    });
})();
