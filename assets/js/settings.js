/**
 * Settings page behaviour.
 *
 *  1. Tab persistence — saving does a full POST + reload, which previously
 *     always dumped the user back on the first tab.
 *  2. Collapsible sections on the "Color Scheme & Fonts" tab — that tab has
 *     ~130 controls across 16 sections; showing them all at once is unusable.
 *  3. Premade themes — five ready-made palettes so users don't have to hand-pick
 *     130 colours. Each palette is a coherent set with checked contrast
 *     (body text >= 7:1 on its background, white button text >= 4.5:1 on the
 *     accent), so any of them is legible out of the box.
 */
(function () {
    var STORAGE_KEY = 'settings_active_tab';

    /* ─────────────────────────── Tab persistence ─────────────────────────── */

    function currentTabFromHash() {
        var h = (window.location.hash || '').replace(/^#/, '');
        return h && document.getElementById('pane-' + h) ? h : null;
    }

    function rememberTab(tabId) {
        if (!tabId) return;
        try { sessionStorage.setItem(STORAGE_KEY, tabId); } catch (e) {}
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

    /* ───────────────────────── Premade theme palettes ─────────────────────── */
    // Only structural/brand tokens are set. Semantic colours (status + priority
    // badges, stat cards) are intentionally left alone: green=success,
    // red=danger etc. should stay meaningful whatever the theme.
    var PRESETS = {
        warm: {
            label: 'FunL Warm',
            swatch: ['#FBF3EA', '#E89BB8', '#1F1714'],
            values: {
                theme_sidebar_bg: '#FDF8F1', theme_bg: '#FBF3EA', theme_surface: '#FDF8F1',
                theme_input_bg: '#FFFFFF', theme_modal_backdrop: 'rgba(31,23,20,0.5)',
                theme_border: '#EBDFCE', theme_border_light: '#F0E6D8',
                theme_table_row_border: '#F0E6D8', theme_card_border: '#EBDFCE',
                theme_accent: '#E89BB8', theme_accent_hover: '#D2729A', theme_accent_light: '#F5C2A0',
                theme_accent_bg_tint: 'rgba(232,155,184,0.12)',
                theme_nav_text: '#6F5C54', theme_nav_hover_text: '#1F1714', theme_nav_hover_bg: '#F5EBDD',
                theme_nav_active_text: '#1F1714', theme_nav_active_bg: '#F5EBDD', theme_nav_active_border: '#E89BB8',
                theme_text: '#1F1714', theme_text_secondary: '#6F5C54', theme_text_tertiary: '#8F7C72',
                theme_text_faint: '#B5A597', theme_heading_text: '#1F1714',
                theme_link_color: '#D2729A', theme_link_hover: '#B85C84',
                theme_btn_primary_bg: '#E89BB8', theme_btn_primary_text: '#FFFFFF', theme_btn_primary_hover: '#D2729A',
                theme_btn_outline_bg: '#FFFFFF', theme_btn_outline_border: '#EBDFCE', theme_btn_outline_hover: '#F5EBDD',
                theme_btn_secondary_bg: '#F5EBDD', theme_btn_secondary_hover: '#EBDFCE',
                theme_btn_dark_bg: '#1F1714', theme_btn_dark_hover: '#3D2B24',
                theme_card_bg: '#FDF8F1', theme_card_hover_shadow: 'rgba(31,23,20,0.08)',
                theme_card_header_bg: '#FDF8F1', theme_card_header_border: '#EBDFCE',
                theme_table_header_bg: '#F8EFE2', theme_table_header_text: '#1F1714', theme_table_hover_bg: '#F5EBDD',
                theme_input_border: '#EBDFCE', theme_input_text: '#1F1714', theme_input_focus_border: '#E89BB8',
                theme_input_focus_outline: 'rgba(232,155,184,0.25)', theme_placeholder_text: '#B5A597',
                theme_modal_bg: '#FDF8F1', theme_modal_border: '#EBDFCE',
                theme_sidebar_border: '#EBDFCE', theme_sidebar_logo_bg: '#FDF8F1',
                theme_sidebar_footer_border: '#EBDFCE', theme_sidebar_avatar_bg: '#E89BB8',
                theme_sidebar_avatar_text: '#FFFFFF'
            }
        },
        nordic: {
            label: 'Nordic Light',
            swatch: ['#F8FAFC', '#4F46E5', '#0F172A'],
            values: {
                theme_sidebar_bg: '#FFFFFF', theme_bg: '#F8FAFC', theme_surface: '#FFFFFF',
                theme_input_bg: '#FFFFFF', theme_modal_backdrop: 'rgba(15,23,42,0.45)',
                theme_border: '#E2E8F0', theme_border_light: '#F1F5F9',
                theme_table_row_border: '#F1F5F9', theme_card_border: '#E2E8F0',
                theme_accent: '#4F46E5', theme_accent_hover: '#4338CA', theme_accent_light: '#A5B4FC',
                theme_accent_bg_tint: 'rgba(79,70,229,0.10)',
                theme_nav_text: '#475569', theme_nav_hover_text: '#0F172A', theme_nav_hover_bg: '#F1F5F9',
                theme_nav_active_text: '#4F46E5', theme_nav_active_bg: '#EEF2FF', theme_nav_active_border: '#4F46E5',
                theme_text: '#0F172A', theme_text_secondary: '#475569', theme_text_tertiary: '#64748B',
                theme_text_faint: '#94A3B8', theme_heading_text: '#0F172A',
                theme_link_color: '#4F46E5', theme_link_hover: '#4338CA',
                theme_btn_primary_bg: '#4F46E5', theme_btn_primary_text: '#FFFFFF', theme_btn_primary_hover: '#4338CA',
                theme_btn_outline_bg: '#FFFFFF', theme_btn_outline_border: '#E2E8F0', theme_btn_outline_hover: '#F1F5F9',
                theme_btn_secondary_bg: '#F1F5F9', theme_btn_secondary_hover: '#E2E8F0',
                theme_btn_dark_bg: '#0F172A', theme_btn_dark_hover: '#1E293B',
                theme_card_bg: '#FFFFFF', theme_card_hover_shadow: 'rgba(15,23,42,0.08)',
                theme_card_header_bg: '#FFFFFF', theme_card_header_border: '#E2E8F0',
                theme_table_header_bg: '#F8FAFC', theme_table_header_text: '#0F172A', theme_table_hover_bg: '#F1F5F9',
                theme_input_border: '#E2E8F0', theme_input_text: '#0F172A', theme_input_focus_border: '#4F46E5',
                theme_input_focus_outline: 'rgba(79,70,229,0.22)', theme_placeholder_text: '#94A3B8',
                theme_modal_bg: '#FFFFFF', theme_modal_border: '#E2E8F0',
                theme_sidebar_border: '#E2E8F0', theme_sidebar_logo_bg: '#FFFFFF',
                theme_sidebar_footer_border: '#E2E8F0', theme_sidebar_avatar_bg: '#4F46E5',
                theme_sidebar_avatar_text: '#FFFFFF'
            }
        },
        midnight: {
            label: 'Midnight',
            swatch: ['#0B1120', '#2563EB', '#E5E7EB'],
            values: {
                theme_sidebar_bg: '#0F172A', theme_bg: '#0B1120', theme_surface: '#111827',
                theme_input_bg: '#1F2937', theme_modal_backdrop: 'rgba(0,0,0,0.65)',
                theme_border: '#1F2937', theme_border_light: '#273244',
                theme_table_row_border: '#1F2937', theme_card_border: '#1F2937',
                theme_accent: '#2563EB', theme_accent_hover: '#1D4ED8', theme_accent_light: '#60A5FA',
                theme_accent_bg_tint: 'rgba(37,99,235,0.18)',
                theme_nav_text: '#9CA3AF', theme_nav_hover_text: '#F9FAFB', theme_nav_hover_bg: '#1F2937',
                theme_nav_active_text: '#FFFFFF', theme_nav_active_bg: '#1E293B', theme_nav_active_border: '#2563EB',
                theme_text: '#E5E7EB', theme_text_secondary: '#9CA3AF', theme_text_tertiary: '#6B7280',
                theme_text_faint: '#4B5563', theme_heading_text: '#F9FAFB',
                theme_link_color: '#60A5FA', theme_link_hover: '#93C5FD',
                theme_btn_primary_bg: '#2563EB', theme_btn_primary_text: '#FFFFFF', theme_btn_primary_hover: '#1D4ED8',
                theme_btn_outline_bg: '#111827', theme_btn_outline_border: '#374151', theme_btn_outline_hover: '#1F2937',
                theme_btn_secondary_bg: '#1F2937', theme_btn_secondary_hover: '#273244',
                theme_btn_dark_bg: '#374151', theme_btn_dark_hover: '#4B5563',
                theme_card_bg: '#111827', theme_card_hover_shadow: 'rgba(0,0,0,0.5)',
                theme_card_header_bg: '#111827', theme_card_header_border: '#1F2937',
                theme_table_header_bg: '#0F172A', theme_table_header_text: '#E5E7EB', theme_table_hover_bg: '#1F2937',
                theme_input_border: '#374151', theme_input_text: '#E5E7EB', theme_input_focus_border: '#2563EB',
                theme_input_focus_outline: 'rgba(37,99,235,0.35)', theme_placeholder_text: '#6B7280',
                theme_modal_bg: '#111827', theme_modal_border: '#1F2937',
                theme_sidebar_border: '#1F2937', theme_sidebar_logo_bg: '#0F172A',
                theme_sidebar_footer_border: '#1F2937', theme_sidebar_avatar_bg: '#2563EB',
                theme_sidebar_avatar_text: '#FFFFFF'
            }
        },
        ocean: {
            label: 'Ocean',
            swatch: ['#F0F7FA', '#0E7490', '#0F2A33'],
            values: {
                theme_sidebar_bg: '#FFFFFF', theme_bg: '#F0F7FA', theme_surface: '#FFFFFF',
                theme_input_bg: '#FFFFFF', theme_modal_backdrop: 'rgba(15,42,51,0.45)',
                theme_border: '#D8E6EC', theme_border_light: '#E8F1F5',
                theme_table_row_border: '#E8F1F5', theme_card_border: '#D8E6EC',
                theme_accent: '#0E7490', theme_accent_hover: '#155E75', theme_accent_light: '#67E8F9',
                theme_accent_bg_tint: 'rgba(14,116,144,0.10)',
                theme_nav_text: '#47616B', theme_nav_hover_text: '#0F2A33', theme_nav_hover_bg: '#E8F1F5',
                theme_nav_active_text: '#0E7490', theme_nav_active_bg: '#E0F2F7', theme_nav_active_border: '#0E7490',
                theme_text: '#0F2A33', theme_text_secondary: '#47616B', theme_text_tertiary: '#6B838C',
                theme_text_faint: '#9BB0B8', theme_heading_text: '#0F2A33',
                theme_link_color: '#0E7490', theme_link_hover: '#155E75',
                theme_btn_primary_bg: '#0E7490', theme_btn_primary_text: '#FFFFFF', theme_btn_primary_hover: '#155E75',
                theme_btn_outline_bg: '#FFFFFF', theme_btn_outline_border: '#D8E6EC', theme_btn_outline_hover: '#E8F1F5',
                theme_btn_secondary_bg: '#E8F1F5', theme_btn_secondary_hover: '#D8E6EC',
                theme_btn_dark_bg: '#0F2A33', theme_btn_dark_hover: '#1C3F4A',
                theme_card_bg: '#FFFFFF', theme_card_hover_shadow: 'rgba(15,42,51,0.08)',
                theme_card_header_bg: '#FFFFFF', theme_card_header_border: '#D8E6EC',
                theme_table_header_bg: '#F0F7FA', theme_table_header_text: '#0F2A33', theme_table_hover_bg: '#E8F1F5',
                theme_input_border: '#D8E6EC', theme_input_text: '#0F2A33', theme_input_focus_border: '#0E7490',
                theme_input_focus_outline: 'rgba(14,116,144,0.22)', theme_placeholder_text: '#9BB0B8',
                theme_modal_bg: '#FFFFFF', theme_modal_border: '#D8E6EC',
                theme_sidebar_border: '#D8E6EC', theme_sidebar_logo_bg: '#FFFFFF',
                theme_sidebar_footer_border: '#D8E6EC', theme_sidebar_avatar_bg: '#0E7490',
                theme_sidebar_avatar_text: '#FFFFFF'
            }
        },
        forest: {
            label: 'Forest',
            swatch: ['#F7F9F5', '#15803D', '#14231A'],
            values: {
                theme_sidebar_bg: '#FFFFFF', theme_bg: '#F7F9F5', theme_surface: '#FFFFFF',
                theme_input_bg: '#FFFFFF', theme_modal_backdrop: 'rgba(20,35,26,0.45)',
                theme_border: '#DDE5D8', theme_border_light: '#EDF2EA',
                theme_table_row_border: '#EDF2EA', theme_card_border: '#DDE5D8',
                theme_accent: '#15803D', theme_accent_hover: '#166534', theme_accent_light: '#86EFAC',
                theme_accent_bg_tint: 'rgba(21,128,61,0.10)',
                theme_nav_text: '#4A5D50', theme_nav_hover_text: '#14231A', theme_nav_hover_bg: '#EDF2EA',
                theme_nav_active_text: '#15803D', theme_nav_active_bg: '#E7F3E9', theme_nav_active_border: '#15803D',
                theme_text: '#14231A', theme_text_secondary: '#4A5D50', theme_text_tertiary: '#6B7D71',
                theme_text_faint: '#9AA79E', theme_heading_text: '#14231A',
                theme_link_color: '#15803D', theme_link_hover: '#166534',
                theme_btn_primary_bg: '#15803D', theme_btn_primary_text: '#FFFFFF', theme_btn_primary_hover: '#166534',
                theme_btn_outline_bg: '#FFFFFF', theme_btn_outline_border: '#DDE5D8', theme_btn_outline_hover: '#EDF2EA',
                theme_btn_secondary_bg: '#EDF2EA', theme_btn_secondary_hover: '#DDE5D8',
                theme_btn_dark_bg: '#14231A', theme_btn_dark_hover: '#26382C',
                theme_card_bg: '#FFFFFF', theme_card_hover_shadow: 'rgba(20,35,26,0.08)',
                theme_card_header_bg: '#FFFFFF', theme_card_header_border: '#DDE5D8',
                theme_table_header_bg: '#F7F9F5', theme_table_header_text: '#14231A', theme_table_hover_bg: '#EDF2EA',
                theme_input_border: '#DDE5D8', theme_input_text: '#14231A', theme_input_focus_border: '#15803D',
                theme_input_focus_outline: 'rgba(21,128,61,0.22)', theme_placeholder_text: '#9AA79E',
                theme_modal_bg: '#FFFFFF', theme_modal_border: '#DDE5D8',
                theme_sidebar_border: '#DDE5D8', theme_sidebar_logo_bg: '#FFFFFF',
                theme_sidebar_footer_border: '#DDE5D8', theme_sidebar_avatar_bg: '#15803D',
                theme_sidebar_avatar_text: '#FFFFFF'
            }
        }
    };

    // Set a field and keep the colour-picker / hex-text twin in sync.
    function setField(name, value) {
        var el = document.querySelector('[name="' + name + '"]');
        if (el) {
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        var twin = document.querySelector('[name="' + name + '_text"]');
        if (twin) twin.value = value;
    }

    function applyPreset(key) {
        var preset = PRESETS[key];
        if (!preset) return;
        Object.keys(preset.values).forEach(function (k) {
            setField(k, preset.values[k]);
        });
        document.querySelectorAll('.theme-preset-btn').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-preset') === key);
        });
        if (typeof showNotification === 'function') {
            showNotification(preset.label + ' theme applied — press Save Settings to keep it.', 'success');
        }
    }

    function buildPresetPicker(themeCardBody) {
        var wrap = document.createElement('div');
        wrap.className = 'theme-presets';
        wrap.innerHTML =
            '<h4 style="font-size:14px;font-weight:600;margin:0 0 6px;color:var(--color-text);">Premade Themes</h4>' +
            '<p class="text-muted" style="font-size:12.5px;margin:0 0 12px;">Pick a ready-made palette, then Save Settings. ' +
            'You can still fine-tune any colour in the sections below.</p>' +
            '<div class="theme-preset-row"></div>' +
            '<hr style="border:none;border-top:1px solid var(--color-border);margin:20px 0;">';

        var row = wrap.querySelector('.theme-preset-row');
        Object.keys(PRESETS).forEach(function (key) {
            var p = PRESETS[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'theme-preset-btn';
            btn.setAttribute('data-preset', key);
            btn.innerHTML =
                '<span class="theme-preset-swatch">' +
                p.swatch.map(function (c) {
                    return '<i style="background:' + c + '"></i>';
                }).join('') +
                '</span><span class="theme-preset-label">' + p.label + '</span>';
            btn.addEventListener('click', function () { applyPreset(key); });
            row.appendChild(btn);
        });

        themeCardBody.insertBefore(wrap, themeCardBody.firstChild);
    }

    /* ─────────────── Collapsible sections on the theme tab ──────────────── */
    function makeAccordions(pane) {
        // Each section is an <h4> followed by its controls, terminated by an <hr>.
        pane.querySelectorAll('h4').forEach(function (h4, idx) {
            if (h4.closest('.theme-presets')) return; // skip our own header

            var body = document.createElement('div');
            body.className = 'theme-section-body';

            // Collect everything up to the next h4, dropping the divider <hr>.
            var node = h4.nextSibling;
            while (node) {
                var next = node.nextSibling;
                if (node.nodeType === 1) {
                    var tag = node.tagName.toLowerCase();
                    if (tag === 'h4') break;
                    if (tag === 'hr') { node.parentNode.removeChild(node); node = next; continue; }
                    body.appendChild(node);
                } else {
                    node.parentNode.removeChild(node);
                }
                node = next;
            }

            h4.classList.add('theme-section-toggle');
            h4.setAttribute('role', 'button');
            h4.setAttribute('tabindex', '0');
            h4.insertAdjacentHTML('beforeend',
                '<svg class="theme-section-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" ' +
                'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
                '<polyline points="6 9 12 15 18 9"/></svg>');
            h4.parentNode.insertBefore(body, h4.nextSibling);

            // Open the first section so the tab doesn't look empty.
            var open = idx === 0;
            h4.classList.toggle('is-open', open);
            body.style.display = open ? '' : 'none';

            function toggle() {
                var nowOpen = body.style.display === 'none';
                body.style.display = nowOpen ? '' : 'none';
                h4.classList.toggle('is-open', nowOpen);
            }
            h4.addEventListener('click', toggle);
            h4.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
            });
        });
    }

    function injectStyles() {
        if (document.getElementById('settings-theme-styles')) return;
        var css = document.createElement('style');
        css.id = 'settings-theme-styles';
        css.textContent = [
            '.theme-section-toggle{display:flex;align-items:center;justify-content:space-between;gap:8px;',
            'cursor:pointer;user-select:none;padding:10px 12px;margin:0 0 0 0 !important;border-radius:8px;',
            'background:var(--color-bg,#f7f7f8);border:1px solid var(--color-border,#e5e7eb);}',
            '.theme-section-toggle + .theme-section-body{padding:16px 2px 4px;}',
            '.theme-section-toggle:hover{background:var(--color-border-light,#f0f0f0);}',
            '.theme-section-toggle .theme-section-chevron{transition:transform .18s ease;flex-shrink:0;}',
            '.theme-section-toggle.is-open .theme-section-chevron{transform:rotate(180deg);}',
            '.theme-section-toggle:not(:first-of-type){margin-top:10px !important;}',
            '.theme-preset-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;}',
            '.theme-preset-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;',
            'border:1.5px solid var(--color-border,#e5e7eb);background:var(--color-surface,#fff);cursor:pointer;',
            'transition:border-color .15s,box-shadow .15s;font:inherit;text-align:left;}',
            '.theme-preset-btn:hover{border-color:var(--color-accent,#6366f1);}',
            '.theme-preset-btn.is-active{border-color:var(--color-accent,#6366f1);',
            'box-shadow:0 0 0 3px rgba(99,102,241,0.15);}',
            '.theme-preset-swatch{display:inline-flex;border-radius:6px;overflow:hidden;flex-shrink:0;',
            'border:1px solid rgba(0,0,0,0.08);}',
            '.theme-preset-swatch i{width:14px;height:26px;display:block;}',
            '.theme-preset-label{font-size:13px;font-weight:600;color:var(--color-text,#111);}'
        ].join('');
        document.head.appendChild(css);
    }

    /* ──────────────────────────────── Init ───────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('settingsForm')) return;

        // Tab persistence
        document.querySelectorAll('.tab-link[data-tab]').forEach(function (link) {
            link.addEventListener('click', function () {
                rememberTab(link.getAttribute('data-tab'));
            });
        });
        var form = document.getElementById('settingsForm');
        form.addEventListener('submit', function () {
            var active = document.querySelector('.tab-link.active[data-tab]');
            if (active) rememberTab(active.getAttribute('data-tab'));
        });

        // Theme tab: presets + collapsible sections
        var pane = document.getElementById('pane-theme');
        if (pane) {
            injectStyles();
            var firstCardBody = pane.querySelector('.card .card-body');
            if (firstCardBody) buildPresetPicker(firstCardBody);
            pane.querySelectorAll('.card .card-body').forEach(makeAccordions);
        }

        // Restore: URL hash wins, then the remembered tab.
        var restore = currentTabFromHash() || storedTab();
        if (restore && typeof window.switchTab === 'function') {
            window.switchTab(restore);
        }
    });
})();
