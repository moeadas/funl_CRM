/*!
 * funl_utm.js — UTM capture for the FunL CRM
 * Paste this before </body> on any page that has a form whose submissions
 * should carry UTM values into the CRM.
 *
 * What it does:
 *   1. Reads utm_source / utm_campaign / utm_medium / utm_content / utm_term
 *      from the current URL.
 *   2. Saves them in localStorage (persists 30 days) and sessionStorage
 *      (persists for the browser session).
 *   3. On every page load, copies the values into any form on the page
 *      that has hidden inputs named utm_source, utm_campaign, etc.
 *      (so existing forms can pick them up automatically)
 *   4. Also copies the values into any form that posts to the
 *      FunL webform endpoint, even if no hidden fields are present.
 *
 * For our own /pages/form-embed.php forms, this isn't needed
 * (the embed script handles UTM internally).
 */
(function () {
    'use strict';
    var KEYS = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term'];
    var STORAGE_PREFIX = 'funl_';
    var TTL_DAYS = 30;

    function readUtmFromUrl() {
        var out = {};
        try {
            var params = new URLSearchParams(window.location.search);
            KEYS.forEach(function (k) {
                var v = params.get(k);
                if (v) out[k] = v;
            });
        } catch (e) { /* no-op */ }
        return out;
    }

    function readUtmFromStorage() {
        var out = {};
        try {
            KEYS.forEach(function (k) {
                var raw = localStorage.getItem(STORAGE_PREFIX + k);
                if (!raw) return;
                var parsed = null;
                try { parsed = JSON.parse(raw); } catch (e) { parsed = null; }
                if (parsed && parsed.value && parsed.expires && parsed.expires > Date.now()) {
                    out[k] = parsed.value;
                } else {
                    localStorage.removeItem(STORAGE_PREFIX + k);
                }
            });
        } catch (e) { /* no-op */ }
        return out;
    }

    function persistUtm(utm) {
        try {
            var expires = Date.now() + (TTL_DAYS * 24 * 60 * 60 * 1000);
            KEYS.forEach(function (k) {
                if (utm[k]) {
                    localStorage.setItem(STORAGE_PREFIX + k, JSON.stringify({ value: utm[k], expires: expires }));
                    try { sessionStorage.setItem(STORAGE_PREFIX + k, utm[k]); } catch (e) { /* no-op */ }
                }
            });
        } catch (e) { /* no-op */ }
    }

    function applyToForms(utm) {
        try {
            var forms = document.querySelectorAll('form');
            forms.forEach(function (form) {
                KEYS.forEach(function (k) {
                    if (!utm[k]) return;
                    // Prefer an existing hidden input
                    var input = form.querySelector('input[name="' + k + '"]');
                    if (input && !input.value) {
                        input.value = utm[k];
                    } else if (!input) {
                        // Inject one
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = k;
                        input.value = utm[k];
                        form.appendChild(input);
                    }
                });
                // Also add landing_page + referrer
                var landing = form.querySelector('input[name="landing_page"]');
                if (!landing) {
                    landing = document.createElement('input');
                    landing.type = 'hidden';
                    landing.name = 'landing_page';
                    form.appendChild(landing);
                }
                if (!landing.value) landing.value = window.location.href;
                var ref = form.querySelector('input[name="referrer"]');
                if (!ref) {
                    ref = document.createElement('input');
                    ref.type = 'hidden';
                    ref.name = 'referrer';
                    form.appendChild(ref);
                }
                if (!ref.value) ref.value = document.referrer || '';
            });
        } catch (e) { /* no-op */ }
    }

    // Merge: URL params win over stored values (most recent click)
    var fromStorage = readUtmFromStorage();
    var fromUrl = readUtmFromUrl();
    var merged = {};
    KEYS.forEach(function (k) {
        merged[k] = fromUrl[k] || fromStorage[k] || '';
    });

    if (Object.keys(fromUrl).length) {
        persistUtm(fromUrl);
    }

    // Apply to any forms already in the DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { applyToForms(merged); });
    } else {
        applyToForms(merged);
    }

    // Re-apply when new forms are added (e.g. AJAX-loaded)
    try {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
                    applyToForms(merged);
                    break;
                }
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) { /* observer not supported */ }

    // Expose for debugging
    window.funlUtm = merged;
})();
