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

    // Color overrides
    $overrides = [];
    $colorMap = [
        'theme_bg' => '--color-bg',
        'theme_surface' => '--color-surface',
        'theme_border' => '--color-border',
        'theme_accent' => '--color-accent',
        'theme_accent_hover' => '--color-accent-hover',
        'theme_text' => '--color-text',
        'theme_text_secondary' => '--color-text-secondary',
        'theme_success' => '--color-success',
        'theme_warning' => '--color-warning',
        'theme_danger' => '--color-danger',
        'theme_info' => '--color-info',
    ];

    foreach ($colorMap as $settingKey => $cssVar) {
        if (isset($theme[$settingKey]) && !empty($theme[$settingKey])) {
            $overrides[] = $cssVar . ': ' . $theme[$settingKey] . ';';
        }
    }

    // Sidebar bg
    if (isset($theme['theme_sidebar_bg']) && !empty($theme['theme_sidebar_bg'])) {
        $overrides[] = '--color-sidebar: ' . $theme['theme_sidebar_bg'] . ';';
    }

    // Font overrides
    $headingFont = $theme['theme_font_heading'] ?? '';
    $bodyFont = $theme['theme_font_body'] ?? '';
    $monoFont = $theme['theme_font_mono'] ?? '';

    // Build Google Fonts URL
    $fontFamilies = [];
    if ($headingFont) $fontFamilies[] = "family=" . urlencode($headingFont) . ":wght@400;500;600;700;800";
    if ($bodyFont && $bodyFont !== $headingFont) $fontFamilies[] = "family=" . urlencode($bodyFont) . ":wght@400;500;600;700";
    if ($monoFont) $fontFamilies[] = "family=" . urlencode($monoFont) . ":wght@400;500;600;700";

    // Arabic fonts
    $arHeadingFont = $theme['theme_font_heading_ar'] ?? '';
    $arBodyFont = $theme['theme_font_body_ar'] ?? '';
    if ($arHeadingFont && strpos($arHeadingFont, 'Default') === false) {
        $fontFamilies[] = "family=" . urlencode($arHeadingFont) . ":wght@400;500;600;700";
    }
    if ($arBodyFont && strpos($arBodyFont, 'Default') === false && $arBodyFont !== $arHeadingFont) {
        $fontFamilies[] = "family=" . urlencode($arBodyFont) . ":wght@400;500;600;700";
    }

    $output = "";

    // Google Fonts link
    if (!empty($fontFamilies)) {
        $fontUrl = "https://fonts.googleapis.com/css2?" . implode('&', $fontFamilies) . "&display=swap";
        $output .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
        $output .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $output .= '<link href="' . htmlspecialchars($fontUrl) . '" rel="stylesheet">';
    }

    // CSS overrides
    if (!empty($overrides)) {
        $output .= '<style id="theme-override">:root{' . implode('', $overrides) . '}';

        // Font family overrides
        $bodyFamily = $bodyFont ? "'$bodyFont', -apple-system, sans-serif" : null;
        $headingFamily = $headingFont ? "'$headingFont', -apple-system, sans-serif" : null;
        $monoFamily = $monoFont ? "'$monoFont', monospace" : null;

        if ($bodyFamily) {
            $output .= "body{font-family:$bodyFamily;}";
            $output .= ".form-control,.btn,.nav-link{font-family:$bodyFamily;}";
        }
        if ($headingFamily) {
            $output .= "h1,h2,h3,h4,h5,h6,.page-title,.card-title,.login-title{font-family:$headingFamily;}";
        }
        if ($monoFamily) {
            $output .= ".badge,.stat-label,.table th,.progress-label{font-family:$monoFamily;}";
        }

        // Sidebar bg
        if (isset($theme['theme_sidebar_bg']) && !empty($theme['theme_sidebar_bg'])) {
            $output .= ".sidebar{background:" . $theme['theme_sidebar_bg'] . ";}";
        }

        // Accent bg override
        if (isset($theme['theme_accent']) && !empty($theme['theme_accent'])) {
            $output .= ".btn-primary{background:" . $theme['theme_accent'] . ";}";
            $output .= ".btn-primary:hover{background:" . ($theme['theme_accent_hover'] ?? $theme['theme_accent']) . ";}";
            $output .= ".nav-link.active{color:" . ($theme['theme_accent_hover'] ?? $theme['theme_accent']) . ";}";
        }

        // RTL Arabic font override
        if ($arHeadingFont && strpos($arHeadingFont, 'Default') === false) {
            $output .= "html[lang=\"ar\"] h1,html[lang=\"ar\"] h2,html[lang=\"ar\"] h3,html[lang=\"ar\"] h4,html[lang=\"ar\"] h5,html[lang=\"ar\"] h6,html[lang=\"ar\"] .page-title,html[lang=\"ar\"] .card-title{font-family:'$arHeadingFont',sans-serif;}";
        }
        if ($arBodyFont && strpos($arBodyFont, 'Default') === false) {
            $output .= "html[lang=\"ar\"] body,html[lang=\"ar\"] .form-control,html[lang=\"ar\"] .btn,html[lang=\"ar\"] .nav-link{font-family:'$arBodyFont',sans-serif;}";
        }

        $output .= '</style>';
    }

    echo $output;
}