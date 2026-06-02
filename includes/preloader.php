<?php
/**
 * White Label CRM - Full-Screen Brand Preloader (funl.)
 * Supports custom configuration from Settings -> App Branding -> Preloader Code.
 *
 * Toggle: PRELOADER_ENABLED constant in config/database.php
 * Or set 'preloader_enabled' => '0' in the settings table to disable at runtime.
 *
 * If a custom preloader code is defined, it is loaded and outputted.
 * Otherwise, the default "funl." preloader is executed.
 */
require_once __DIR__ . '/functions.php';

// Check if preloader is disabled
if (!PRELOADER_ENABLED) return;
$dbPreloaderEnabled = getSetting('preloader_enabled');
if ($dbPreloaderEnabled === '0' || $dbPreloaderEnabled === 'false') return;

$customPreloader = getSetting('preloader_code');

if (!empty($customPreloader)) {
    // Render the custom preloader code saved in Settings
    echo $customPreloader;
} else {
    // Render the default "funl." premium preloader
    // Detailed structure comments are provided below for the development team.
    ?>
<!-- =========================================================================
     DEFAULT FUNL PRELOADER TEMPLATE
     -------------------------------------------------------------------------
     STRUCTURE OVERVIEW:
     1. CSS Styles (<style>):
        - Custom Font: "FunlPoppins" loaded via optimized inline Base64 WOFF2.
        - CSS Variables: Defines background, brand magenta, brand grey, anim speeds.
        - Layout: Fixed fullscreen overlay (#funl-preloader-container) with flex centering.
        - Animations:
          - flipin: 3D perspective entry rotate animation for letters.
          - bob: Up and down loop wave for each letter.
          - charge/glowbg: Pulsing radial gradient backdrop glow.
          - shuttle: Gradient scanning progress bar effect.
     2. HTML Layout:
        - Overlay container wrapper.
        - Centered loader wrapper.
        - Wordmark holding character spans with inline delay indices (--i).
        - Simulated infinite shuttle progress bar.
     3. JavaScript Handler (<script>):
        - overflow-hidden: Disables scrolling on html and body tags during load.
        - removeLoader(): Fades out preloader, enables scroll, and deletes DOM nodes.
        - Event listeners: Triggers fade-out on window load (minimum 800ms buffer 
          for visual continuity) and has a maximum 3 seconds safety fallback.
     ========================================================================= -->
<style>
/* 1. CSS STYLES & PRELOADER THEME CONFIG */
@font-face {
  font-family: "FunlPoppins";
  src: url("data:font/woff2;base64,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-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92d37iN/TnEhYCrVGJuFVtTIVtWcyanS0-e92D.fl-bar {
  position: relative; z-index: 1; width: 58%; height: 3px; border-radius: 99px;
  background: rgba(153,153,153,.18); overflow: hidden;
}
.fl-bar::after {
  content: ""; position: absolute; top: 0; left: 0; height: 100%; width: 36%;
  border-radius: 99px;
  background: linear-gradient(90deg, transparent, var(--red), var(--red2), var(--red), transparent);
  box-shadow: 0 0 12px rgba(221,45,74,.7);
  animation: shuttle var(--loop) cubic-bezier(.65,0,.35,1) infinite;
}

/* KEYFRAMES FOR PRELOADER ANIMATIONS */
@keyframes flipin {
  0% { opacity: 0; transform: perspective(420px) rotateX(-92deg) translateY(.35em); }
  60% { opacity: 1; }
  100% { opacity: 1; transform: perspective(420px) rotateX(0) translateY(0); }
}
@keyframes bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-.085em); } }
@keyframes pop { 0%, 42%, 100% { transform: scale(1); } 10% { transform: scale(1.17); } }
@keyframes charge {
  0%, 42%, 100% { filter: brightness(1) drop-shadow(0 0 0 rgba(221,45,74,0)); }
  10% { filter: brightness(1.55) drop-shadow(0 0 .16em rgba(255,98,125,.95)); }
}
@keyframes glowbg { 0%, 100% { opacity: .4; } 28% { opacity: 1; } }
@keyframes shuttle { 0% { transform: translateX(-130%); } 100% { transform: translateX(415%); } }

/* ACCESSIBILITY: REDUCE MOTION PREFERENCE */
@media (prefers-reduced-motion: reduce) {
  #funl-preloader-container .ch,
  #funl-preloader-container .gl,
  #funl-preloader-container .in,
  #funl-preloader-container .funl-loader::before,
  #funl-preloader-container .fl-bar::after { animation: none !important; }
  #funl-preloader-container .in { opacity: 1 !important; transform: none !important; }
  #funl-preloader-container .fl-bar::after { transform: translateX(140%); }
}
</style>

<!-- 2. HTML LAYOUT -->
<div id="funl-preloader-container">
    <div class="funl-loader" role="status" aria-label="Loading">
        <div class="wordmark" aria-hidden="true">
            <!-- Inline style variables (--i) are used to sequence the animation delay for each letter -->
            <span class="ch" style="--i:0"><span class="gl"><span class="in g">f</span></span></span>
            <span class="ch" style="--i:1"><span class="gl"><span class="in r">u</span></span></span>
            <span class="ch" style="--i:2"><span class="gl"><span class="in g">n</span></span></span>
            <span class="ch" style="--i:3"><span class="gl"><span class="in g">l</span></span></span>
            <span class="ch dotwrap" style="--i:4"><span class="gl"><span class="in dot"></span></span></span>
        </div>
        <div class="fl-bar"></div>
    </div>
</div>

<!-- 3. JAVASCRIPT ANIMATION & OVERFLOW CONTROLLERS -->
<script>
(function() {
    // Disable window scrolling on html and body tags while preloader is active
    const htmlEl = document.documentElement;
    const bodyEl = document.body;
    if (htmlEl) htmlEl.style.setProperty("overflow", "hidden", "important");
    if (bodyEl) bodyEl.style.setProperty("overflow", "hidden", "important");

    // Remove preloader node from screen and enable window scroll
    function removeLoader() {
        const loader = document.getElementById("funl-preloader-container");
        if (loader && !loader.classList.contains("fade-out")) {
            loader.classList.add("fade-out");
            if (htmlEl) htmlEl.style.overflow = "";
            if (bodyEl) bodyEl.style.overflow = "";
            setTimeout(function() {
                loader.remove();
            }, 400); // 400ms CSS fadeout transition buffer
        }
    }

    // Trigger fadeout when window has completely loaded
    // (An 800ms buffer is set to guarantee the visual animation runs briefly)
    window.addEventListener("load", function() {
        setTimeout(removeLoader, 800);
    });

    // Fallback safety timeout: guarantee the loader closes after 3 seconds in all cases
    setTimeout(removeLoader, 3000);
})();
</script>
    <?php
}
?>
