<?php
/**
 * Theme Override - generates dynamic CSS from saved settings
 * Include this after the main stylesheet to override colors and fonts
 * Call: includeThemeOverride($db, $companyId);
 */

function includeThemeOverride($db, $companyId) {
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE company_id = ? AND setting_key LIKE 'theme_%'");
    $stmt->execute([$companyId]);
    $theme = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (empty($theme)) return;

    // Helper: get value or default
    $g = function($key, $default = '') use ($theme) {
        return isset($theme[$key]) && $theme[$key] !== '' ? $theme[$key] : $default;
    };

    // ── Defaults ──
    $defaults = [
        'theme_sidebar_bg' => '#FDF8F1',
        'theme_bg' => '#FBF3EA',
        'theme_surface' => '#FDF8F1',
        'theme_input_bg' => '#FFFFFF',
        'theme_modal_backdrop' => 'rgba(31,23,20,0.5)',
        'theme_border' => '#EBDFCE',
        'theme_border_light' => '#F0E6D8',
        'theme_table_row_border' => '#F0E6D8',
        'theme_card_border' => '#EBDFCE',
        'theme_accent' => '#E89BB8',
        'theme_accent_hover' => '#D2729A',
        'theme_accent_light' => '#F5C2A0',
        'theme_accent_bg_tint' => 'rgba(232,155,184,0.12)',
        'theme_nav_text' => '#6F5C54',
        'theme_nav_hover_text' => '#1F1714',
        'theme_nav_hover_bg' => '#F5EBDD',
        'theme_nav_active_text' => '#1F1714',
        'theme_nav_active_bg' => '#F5EBDD',
        'theme_nav_active_border' => '#E89BB8',
        'theme_text' => '#1F1714',
        'theme_text_secondary' => '#6F5C54',
        'theme_text_tertiary' => '#8F7C72',
        'theme_text_faint' => '#B5A597',
        'theme_heading_text' => '#1F1714',
        'theme_link_color' => '#E89BB8',
        'theme_link_hover' => '#D2729A',
        'theme_btn_primary_bg' => '#E89BB8',
        'theme_btn_primary_text' => '#FFFFFF',
        'theme_btn_primary_hover' => '#D2729A',
        'theme_btn_outline_bg' => 'transparent',
        'theme_btn_outline_border' => '#EBDFCE',
        'theme_btn_outline_hover' => '#F5EBDD',
        'theme_btn_secondary_bg' => '#F5EBDD',
        'theme_btn_secondary_hover' => '#EBDFCE',
        'theme_btn_danger_bg' => '#C97A47',
        'theme_btn_danger_text' => '#FFFFFF',
        'theme_btn_danger_border' => '#C97A47',
        'theme_btn_danger_hover' => '#B56A3D',
        'theme_btn_dark_bg' => '#1F1714',
        'theme_btn_dark_hover' => '#3D2B24',
        'theme_card_bg' => '#FDF8F1',
        'theme_card_hover_shadow' => 'rgba(31,23,20,0.08)',
        'theme_card_header_bg' => '#FDF8F1',
        'theme_card_header_border' => '#EBDFCE',
        'theme_table_header_bg' => '#F8EFE2',
        'theme_table_header_text' => '#1F1714',
        'theme_table_hover_bg' => '#F5EBDD',
        'theme_badge_active_bg' => '#D4EDDA',
        'theme_badge_active_text' => '#155724',
        'theme_badge_inactive_bg' => '#F0E6D8',
        'theme_badge_inactive_text' => '#6F5C54',
        'theme_badge_dnc_bg' => '#F8D7DA',
        'theme_badge_dnc_text' => '#721C24',
        'theme_badge_prospect_bg' => '#FFF3CD',
        'theme_badge_prospect_text' => '#856404',
        'theme_badge_sent_bg' => '#D1ECF1',
        'theme_badge_sent_text' => '#0C5460',
        'theme_badge_accepted_bg' => '#D4EDDA',
        'theme_badge_accepted_text' => '#155724',
        'theme_badge_rejected_bg' => '#F8D7DA',
        'theme_badge_rejected_text' => '#721C24',
        'theme_badge_expired_bg' => '#E2E3E5',
        'theme_badge_expired_text' => '#383D41',
        'theme_badge_draft_bg' => '#F0E6D8',
        'theme_badge_draft_text' => '#6F5C54',
        'theme_pri_urgent_bg' => '#F8D7DA',
        'theme_pri_urgent_text' => '#721C24',
        'theme_pri_high_bg' => '#FFE5B4',
        'theme_pri_high_text' => '#856404',
        'theme_pri_medium_bg' => '#D1ECF1',
        'theme_pri_medium_text' => '#0C5460',
        'theme_pri_low_bg' => '#D4EDDA',
        'theme_pri_low_text' => '#155724',
        'theme_stat_orange_bg' => '#FFE5D0',
        'theme_stat_orange_text' => '#9A4A00',
        'theme_stat_green_bg' => '#D4EDDA',
        'theme_stat_green_text' => '#155724',
        'theme_stat_blue_bg' => '#D1ECF1',
        'theme_stat_blue_text' => '#0C5460',
        'theme_stat_yellow_bg' => '#FFF3CD',
        'theme_stat_yellow_text' => '#856404',
        'theme_stat_purple_bg' => '#E2D9F3',
        'theme_stat_purple_text' => '#4A2C7A',
        'theme_input_border' => '#EBDFCE',
        'theme_input_text' => '#1F1714',
        'theme_input_focus_border' => '#E89BB8',
        'theme_input_focus_outline' => 'rgba(232,155,184,0.25)',
        'theme_placeholder_text' => '#B5A597',
        'theme_modal_bg' => '#FDF8F1',
        'theme_modal_border' => '#EBDFCE',
        'theme_sidebar_border' => '#EBDFCE',
        'theme_sidebar_logo_bg' => '#FDF8F1',
        'theme_sidebar_footer_border' => '#EBDFCE',
        'theme_sidebar_avatar_bg' => '#E89BB8',
        'theme_sidebar_avatar_text' => '#FFFFFF',
        'theme_success' => '#5E8259',
        'theme_warning' => '#9A7A2C',
        'theme_danger' => '#C97A47',
        'theme_info' => '#4F7787',
        'theme_sidebar_width' => '260',
        'theme_card_radius' => '16',
        'theme_input_radius' => '10',
        'theme_btn_radius' => '10',
        'theme_modal_radius' => '16',
        'theme_font_heading' => 'Plus Jakarta Sans',
        'theme_font_body' => 'Plus Jakarta Sans',
        'theme_font_menu' => 'Plus Jakarta Sans',
        'theme_font_mono' => 'JetBrains Mono',
        'theme_font_italic' => 'Fraunces',
        'theme_font_heading_ar' => 'Default (follows English)',
        'theme_font_body_ar' => 'Default (follows English)',
        'theme_font_menu_ar' => 'Default (follows English)',
        'theme_fs_base' => '14',
        'theme_fs_h1' => '28',
        'theme_fs_h2' => '22',
        'theme_fs_card_title' => '16',
        'theme_fs_nav' => '13',
        'theme_fs_table' => '13',
        'theme_fs_badge' => '11',
        'theme_fw_heading' => '700',
        'theme_fw_body' => '400',
        'theme_fw_nav' => '500',
        'theme_fw_btn' => '600',
    ];

    // Merge with saved values
    $v = [];
    foreach ($defaults as $k => $d) {
        $v[$k] = $g($k, $d);
    }

    $output = "";

    // ── Build Google Fonts URL ──
    $fontFamilies = [];
    $headingFont = $v['theme_font_heading'];
    $bodyFont = $v['theme_font_body'];
    $menuFont = $v['theme_font_menu'];
    $monoFont = $v['theme_font_mono'];
    $italicFont = $v['theme_font_italic'];
    $arHeadingFont = $v['theme_font_heading_ar'];
    $arBodyFont = $v['theme_font_body_ar'];
    $arMenuFont = $v['theme_font_menu_ar'];

    if ($headingFont) $fontFamilies[] = "family=" . urlencode($headingFont) . ":wght@400;500;600;700;800";
    if ($bodyFont && $bodyFont !== $headingFont) $fontFamilies[] = "family=" . urlencode($bodyFont) . ":wght@400;500;600;700";
    if ($menuFont && $menuFont !== $headingFont && $menuFont !== $bodyFont) $fontFamilies[] = "family=" . urlencode($menuFont) . ":wght@400;500;600;700";
    if ($monoFont) $fontFamilies[] = "family=" . urlencode($monoFont) . ":wght@400;500;600;700";
    if ($italicFont && $italicFont !== $headingFont && $italicFont !== $bodyFont) {
        $fontFamilies[] = "family=" . urlencode($italicFont) . ":ital,wght@0,400;0,600;1,400;1,600";
    }
    if ($arHeadingFont && strpos($arHeadingFont, 'Default') === false) {
        $fontFamilies[] = "family=" . urlencode($arHeadingFont) . ":wght@400;500;600;700";
    }
    if ($arBodyFont && strpos($arBodyFont, 'Default') === false && $arBodyFont !== $arHeadingFont) {
    if ($arMenuFont && strpos($arMenuFont, 'Default') === false && $arMenuFont !== $arHeadingFont && $arMenuFont !== $arBodyFont) {
        $fontFamilies[] = "family=" . urlencode($arMenuFont) . ":wght@400;500;600;700";
    }
        $fontFamilies[] = "family=" . urlencode($arBodyFont) . ":wght@400;500;600;700";
    }

    if (!empty($fontFamilies)) {
        $fontUrl = "https://fonts.googleapis.com/css2?" . implode('&', $fontFamilies) . "&display=swap";
        $output .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
        $output .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $output .= '<link href="' . htmlspecialchars($fontUrl) . '" rel="stylesheet">';
    }

    // ── Build CSS ──
    $css = "";

    // :root variables
    $css .= ":root{";
    $css .= "--color-sidebar:" . $v['theme_sidebar_bg'] . ";";
    $css .= "--color-bg:" . $v['theme_bg'] . ";";
    $css .= "--color-surface:" . $v['theme_surface'] . ";";
    $css .= "--color-border:" . $v['theme_border'] . ";";
    $css .= "--color-border-light:" . $v['theme_border_light'] . ";";
    $css .= "--color-text:" . $v['theme_text'] . ";";
    $css .= "--color-text-secondary:" . $v['theme_text_secondary'] . ";";
    $css .= "--color-text-tertiary:" . $v['theme_text_tertiary'] . ";";
    $css .= "--color-text-faint:" . $v['theme_text_faint'] . ";";
    $css .= "--color-accent:" . $v['theme_accent'] . ";";
    $css .= "--color-accent-hover:" . $v['theme_accent_hover'] . ";";
    $css .= "--color-accent-light:" . $v['theme_accent_light'] . ";";
    $css .= "--color-success:" . $v['theme_success'] . ";";
    $css .= "--color-warning:" . $v['theme_warning'] . ";";
    $css .= "--color-danger:" . $v['theme_danger'] . ";";
    $css .= "--color-info:" . $v['theme_info'] . ";";
    $css .= "--color-dark-btn:" . $v['theme_btn_dark_bg'] . ";";
    $css .= "}";

    // Backgrounds & Surfaces
    $css .= ".sidebar{background:" . $v['theme_sidebar_bg'] . ";}";
    $css .= ".main-content{background:" . $v['theme_bg'] . ";}";
    $css .= ".card{background:" . $v['theme_card_bg'] . ";border-color:" . $v['theme_card_border'] . ";}";
    $css .= ".form-control{background:" . $v['theme_input_bg'] . ";border-color:" . $v['theme_input_border'] . ";color:" . $v['theme_input_text'] . ";}";
    $css .= ".form-control:focus{border-color:" . $v['theme_input_focus_border'] . ";outline:2px solid " . $v['theme_input_focus_outline'] . ";}";
    $css .= ".form-control::placeholder{color:" . $v['theme_placeholder_text'] . ";}";
    $css .= "select.form-control{background:" . $v['theme_input_bg'] . ";}";

    // Borders
    $css .= "*,*::before,*::after{border-color:" . $v['theme_border'] . ";}";
    $css .= ".table td,.table th{border-color:" . $v['theme_table_row_border'] . ";}";

    // Accent / CTA
    $css .= ".btn-primary{background:" . $v['theme_btn_primary_bg'] . ";color:" . $v['theme_btn_primary_text'] . ";}";
    $css .= ".btn-primary:hover{background:" . $v['theme_btn_primary_hover'] . ";}";
    $css .= "a{color:" . $v['theme_link_color'] . ";}";
    $css .= "a:hover{color:" . $v['theme_link_hover'] . ";}";

    // Navigation
    $css .= ".nav-link{color:" . $v['theme_nav_text'] . ";}";
    $css .= ".nav-link:hover{color:" . $v['theme_nav_hover_text'] . ";background:" . $v['theme_nav_hover_bg'] . ";}";
    $css .= ".nav-link.active{color:" . $v['theme_nav_active_text'] . ";background:" . $v['theme_nav_active_bg'] . ";border-left-color:" . $v['theme_nav_active_border'] . ";}";

    // Text
    $css .= "body{color:" . $v['theme_text'] . ";}";
    $css .= ".text-muted{color:" . $v['theme_text_secondary'] . ";}";
    $css .= "h1,h2,h3,h4,h5,h6{color:" . $v['theme_heading_text'] . ";}";

    // Buttons
    $css .= ".btn{border-radius:" . $v['theme_btn_radius'] . "px;font-weight:" . $v['theme_fw_btn'] . ";}";
    $css .= ".btn-outline{background:" . $v['theme_btn_outline_bg'] . ";border-color:" . $v['theme_btn_outline_border'] . ";}";
    $css .= ".btn-outline:hover{background:" . $v['theme_btn_outline_hover'] . ";}";
    $css .= ".btn-secondary{background:" . $v['theme_btn_secondary_bg'] . ";}";
    $css .= ".btn-secondary:hover{background:" . $v['theme_btn_secondary_hover'] . ";}";
    $css .= ".btn-error,.btn-danger{background:" . $v['theme_btn_danger_bg'] . ";color:" . $v['theme_btn_danger_text'] . ";border-color:" . $v['theme_btn_danger_border'] . ";}";
    $css .= ".btn-error:hover,.btn-danger:hover{background:" . $v['theme_btn_danger_hover'] . ";}";
    $css .= ".btn-dark{background:" . $v['theme_btn_dark_bg'] . ";}";
    $css .= ".btn-dark:hover{background:" . $v['theme_btn_dark_hover'] . ";}";

    // Cards
    $css .= ".card{border-radius:" . $v['theme_card_radius'] . "px;}";
    $css .= ".card:hover{box-shadow:0 8px 24px " . $v['theme_card_hover_shadow'] . ";}";
    $css .= ".card-header{background:" . $v['theme_card_header_bg'] . ";border-color:" . $v['theme_card_header_border'] . ";}";

    // Tables
    $css .= ".table th{background:" . $v['theme_table_header_bg'] . ";color:" . $v['theme_table_header_text'] . ";font-size:" . $v['theme_fs_table'] . "px;}";
    $css .= ".table tbody tr:hover{background:" . $v['theme_table_hover_bg'] . ";}";

    // Status Badges
    $badgeMap = [
        'active' => 'theme_badge_active', 'inactive' => 'theme_badge_inactive',
        'dnc' => 'theme_badge_dnc', 'prospect' => 'theme_badge_prospect',
        'sent' => 'theme_badge_sent', 'accepted' => 'theme_badge_accepted',
        'rejected' => 'theme_badge_rejected', 'expired' => 'theme_badge_expired',
        'draft' => 'theme_badge_draft',
    ];
    foreach ($badgeMap as $name => $prefix) {
        $bg = $v[$prefix . '_bg'];
        $txt = $v[$prefix . '_text'];
        $css .= ".badge-" . $name . "{background:" . $bg . ";color:" . $txt . ";}";
        $css .= ".badge-" . ucfirst($name) . "{background:" . $bg . ";color:" . $txt . ";}";
    }
    // Also map common status text badges
    $css .= ".badge-success{background:" . $v['theme_badge_active_bg'] . ";color:" . $v['theme_badge_active_text'] . ";}";
    $css .= ".badge-error{background:" . $v['theme_badge_rejected_bg'] . ";color:" . $v['theme_badge_rejected_text'] . ";}";
    $css .= ".badge-warning{background:" . $v['theme_badge_prospect_bg'] . ";color:" . $v['theme_badge_prospect_text'] . ";}";
    $css .= ".badge-info{background:" . $v['theme_badge_sent_bg'] . ";color:" . $v['theme_badge_sent_text'] . ";}";

    // Priority Badges
    $priMap = [
        'urgent' => 'theme_pri_urgent', 'high' => 'theme_pri_high',
        'medium' => 'theme_pri_medium', 'low' => 'theme_pri_low',
    ];
    foreach ($priMap as $name => $prefix) {
        $bg = $v[$prefix . '_bg'];
        $txt = $v[$prefix . '_text'];
        $css .= ".badge-" . $name . "{background:" . $bg . ";color:" . $txt . ";}";
        $css .= ".priority-" . $name . "{background:" . $bg . ";color:" . $txt . ";}";
    }

    // Stat Cards
    $statMap = [
        'orange' => 'theme_stat_orange', 'green' => 'theme_stat_green',
        'blue' => 'theme_stat_blue', 'yellow' => 'theme_stat_yellow',
        'purple' => 'theme_stat_purple',
    ];
    foreach ($statMap as $name => $prefix) {
        $bg = $v[$prefix . '_bg'];
        $txt = $v[$prefix . '_text'];
        $css .= ".stat-card-" . $name . "{background:" . $bg . ";color:" . $txt . ";}";
        $css .= ".stat-" . $name . "{background:" . $bg . ";color:" . $txt . ";}";
    }

    // Modals
    $css .= ".modal-overlay,.modal-backdrop{background:" . $v['theme_modal_backdrop'] . ";}";
    $css .= ".modal{background:" . $v['theme_modal_bg'] . ";border-color:" . $v['theme_modal_border'] . ";border-radius:" . $v['theme_modal_radius'] . "px;}";

    // Sidebar
    $css .= ".sidebar{border-right-color:" . $v['theme_sidebar_border'] . ";width:" . $v['theme_sidebar_width'] . "px;}";
    $css .= ".sidebar-logo{background:" . $v['theme_sidebar_logo_bg'] . ";}";
    $css .= ".sidebar-footer{border-top-color:" . $v['theme_sidebar_footer_border'] . ";}";
    $css .= ".sidebar-avatar{background:" . $v['theme_sidebar_avatar_bg'] . ";color:" . $v['theme_sidebar_avatar_text'] . ";}";

    // Layout
    $css .= ".form-control{border-radius:" . $v['theme_input_radius'] . "px;}";

    // Font Families
    $bodyFamily = $bodyFont ? "'$bodyFont', -apple-system, sans-serif" : null;
    $headingFamily = $headingFont ? "'$headingFont', -apple-system, sans-serif" : null;
    $monoFamily = $monoFont ? "'$monoFont', monospace" : null;
    $italicFamily = $italicFont ? "'$italicFont', serif" : null;

    if ($bodyFamily) {
        $css .= "body{font-family:$bodyFamily;font-size:" . $v['theme_fs_base'] . "px;font-weight:" . $v['theme_fw_body'] . ";}";
        $css .= ".form-control,.btn{font-family:$bodyFamily;}";
                $menuFamily = $menuFont ? "'$menuFont', -apple-system, sans-serif" : $bodyFamily;
        $css .= ".nav-link{font-family:$menuFamily;font-size:" . $v['theme_fs_nav'] . "px;font-weight:" . $v['theme_fw_nav'] . ";}";
    }
    if ($headingFamily) {
        $css .= "h1,h2,h3,h4,h5,h6,.page-title,.card-title,.login-title{font-family:$headingFamily;font-weight:" . $v['theme_fw_heading'] . ";}";
        $css .= "h1{font-size:" . $v['theme_fs_h1'] . "px;}";
        $css .= "h2{font-size:" . $v['theme_fs_h2'] . "px;}";
        $css .= ".card-title{font-size:" . $v['theme_fs_card_title'] . "px;}";
    }
    if ($monoFamily) {
        $css .= ".badge,.stat-label,.table th{font-family:$monoFamily;}";
        $css .= ".badge{font-size:" . $v['theme_fs_badge'] . "px;}";
    }
    if ($italicFamily) {
        $css .= "h1 em,h2 em,h3 em,h4 em,.page-title em,.card-title em{font-family:$italicFamily;font-style:italic;}";
    }

    // Arabic font overrides
    if ($arHeadingFont && strpos($arHeadingFont, 'Default') === false) {
        $css .= "html[lang=\"ar\"] h1,html[lang=\"ar\"] h2,html[lang=\"ar\"] h3,html[lang=\"ar\"] h4,html[lang=\"ar\"] h5,html[lang=\"ar\"] h6,html[lang=\"ar\"] .page-title,html[lang=\"ar\"] .card-title{font-family:'$arHeadingFont',sans-serif;}";
    }
    if ($arBodyFont && strpos($arBodyFont, 'Default') === false) {
        $css .= "html[lang=\"ar\"] body,html[lang=\"ar\"] .form-control,html[lang=\"ar\"] .btn{font-family:'$arBodyFont',sans-serif;}";
        if ($arMenuFont && strpos($arMenuFont, 'Default') === false) {
            $css .= "html[lang=\"ar\"] .nav-link{font-family:'$arMenuFont',sans-serif;}";
        } else {
            $css .= "html[lang=\"ar\"] .nav-link{font-family:'$arBodyFont',sans-serif;}";
        }
    }

    $output .= '<style id="theme-override">' . $css . '</style>';

    echo $output;
}