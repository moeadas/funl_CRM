<?php
require_once __DIR__ . '/functions.php';
if (!isset($pageTitle)) $pageTitle = getAppName();
$_appName = getAppName();
$_companyLogo = getCompanyLogo();
$_companyFavicon = getCompanyFavicon();
?>
<?php
// Load active users for admin switch-user dropdown
$_switchableUsers = [];
$_isAdminOrImpersonating = hasRole('Admin') || isImpersonating();
if ($_isAdminOrImpersonating) {
    try {
        $_suDb = Database::getInstance()->getConnection();
        $_myCompanyId = $_SESSION['company_id'] ?? null;
        if ($_myCompanyId) {
            $stmt = $_suDb->prepare("SELECT user_id, full_name, role FROM users WHERE status = 'Active' AND company_id = ? ORDER BY full_name");
            $stmt->execute([$_myCompanyId]);
            $_switchableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $_switchableUsers = [];
        }
    } catch (Exception $e) { /* ignore */ }
}
?>
<?php
$_userLocale = $_SESSION['language'] ?? 'en';
$_userDir = ($_userLocale === 'ar') ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_userLocale); ?>" dir="<?php echo $_userDir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="<?php echo $_companyFavicon; ?>">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/modal-system.css">
    <?php if ($_userLocale === 'ar'): ?>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/rtl.css">
    <?php endif; ?>
    <script src="/assets/js/main.js" defer></script>
    <script>
    window.CRM_TRANSLATIONS = <?php 
        $lang = $_SESSION['language'] ?? 'en';
        if (!in_array($lang, ['en', 'ar'])) { $lang = 'en'; }
        
        $translations = [];
        $langFile = __DIR__ . "/languages/{$lang}.php";
        if (file_exists($langFile)) {
            $translations = include $langFile;
        }
        
        $enTranslations = [];
        $enFile = __DIR__ . "/languages/en.php";
        if (file_exists($enFile)) {
            $enTranslations = include $enFile;
        }
        
        echo json_encode([
            'lang' => $lang,
            'dictionary' => $translations,
            'fallback' => $enTranslations
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>;

    function __(key, defaultVal) {
        if (!key) return '';
        var translations = window.CRM_TRANSLATIONS || { lang: 'en', dictionary: {}, fallback: {} };
        
        // Normalize key: e.g. "Save Changes *" -> "save_changes"
        var cleanKey = key.trim().toLowerCase();
        // strip trailing asterisks, colons, question marks, exclamation marks, spaces
        cleanKey = cleanKey.replace(/[*:\-?! ]+$/, '');
        cleanKey = cleanKey.replace(/[^a-z0-9]+/g, '_');
        cleanKey = cleanKey.replace(/^_+|_+$/g, '');
        
        // 1. Try clean key in current language
        if (translations.dictionary[cleanKey] !== undefined) {
            return translations.dictionary[cleanKey];
        }
        // 2. Try original key in current language
        if (translations.dictionary[key] !== undefined) {
            return translations.dictionary[key];
        }
        
        // Fallback to English dictionary
        if (translations.lang !== 'en') {
            if (translations.fallback[cleanKey] !== undefined) {
                return translations.fallback[cleanKey];
            }
            if (translations.fallback[key] !== undefined) {
                return translations.fallback[key];
            }
        }
        
        if (defaultVal !== undefined) {
            return defaultVal;
        }
        
        // If it looks like a snake_case key, format it nicely
        if (key.indexOf(' ') === -1 && key.indexOf('_') !== -1) {
            var parts = key.split('_');
            for (var i = 0; i < parts.length; i++) {
                parts[i] = parts[i].charAt(0).toUpperCase() + parts[i].slice(1);
            }
            return parts.join(' ');
        }

        return key;
    }

    // ─── Switch User (admin impersonation) ───
    function handleSwitchUser(userId) {
        if (!userId) return;
        if (!confirm('Switch to this user? You can switch back anytime.')) {
            // Reset the dropdown to its placeholder
            var sel = document.getElementById('switchUserSelect');
            if (sel) sel.value = '';
            return;
        }
        var csrfToken = document.getElementById('globalCsrfToken') ? document.getElementById('globalCsrfToken').value : '';
        fetch('/api/switch-user.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'switch', user_id: parseInt(userId, 10), csrf_token: csrfToken })
        }).then(function (r) { return r.json(); })
          .then(function (j) {
              if (j && j.success) {
                  window.location.reload();
              } else {
                  alert((j && j.message) || 'Could not switch user.');
                  var sel = document.getElementById('switchUserSelect');
                  if (sel) sel.value = '';
              }
          }).catch(function () {
              alert('Network error. Please try again.');
              var sel = document.getElementById('switchUserSelect');
              if (sel) sel.value = '';
          });
    }

    function handleSwitchBack() {
        var csrfToken = document.getElementById('globalCsrfToken') ? document.getElementById('globalCsrfToken').value : '';
        fetch('/api/switch-user.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'switch_back', csrf_token: csrfToken })
        }).then(function (r) { return r.json(); })
          .then(function (j) {
              if (j && j.success) {
                  window.location.reload();
              } else {
                  alert((j && j.message) || 'Could not switch back.');
              }
          }).catch(function () {
              alert('Network error. Please try again.');
          });
    }
    </script>
    <?php echo getSetting('tracking_head_code'); ?>
</head>
<body>
<?php echo getSetting('tracking_body_code'); ?>
<?php require_once __DIR__ . '/preloader.php'; ?>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleMobileSidebar()" aria-label="Toggle menu">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 5h12M3 9h12M3 13h12"/></svg>
    </button>

    <!-- Sidebar backdrop for mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="<?php echo $_companyLogo; ?>" alt="<?php echo htmlspecialchars($_appName); ?>" class="sidebar-logo-img" style="max-height:40px;max-width:100%;">
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="/pages/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span><?php echo __('dashboard'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/leads.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span><?php echo __('leads'); ?></span>
                </a>
            </li>
            <!-- CRM Core -->
            <li class="nav-item">
                <a href="/pages/contacts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span><?php echo __('contacts'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/deals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'deals.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
                    <span><?php echo __('pipeline'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/tasks.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span><?php echo __('tasks'); ?></span>
                </a>
            </li>
            <!-- REMOVED: Quotes tab replaced by Proposals -->
            <li class="nav-item">
                <a href="/pages/proposals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'proposals.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span><?php echo __('proposals'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/tickets.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <span><?php echo __('support'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/interactions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'interactions.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span><?php echo __('interactions'); ?></span>
                </a>
            </li>
            <?php if (hasRole('Sales Manager')): ?>
            <li class="nav-item">
                <a href="/pages/reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <span><?php echo __('reports'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/export.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'export.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span><?php echo __('export_data'); ?></span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasRole('Sales Manager')): ?>
            <li><hr class="nav-divider"></li>
            <li class="nav-section-label"><?php echo __('email_marketing'); ?></li>
            <li class="nav-item">
                <a href="/pages/email-campaigns.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email-campaigns.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    <span><?php echo __('email_campaigns'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/email-templates.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email-templates.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    <span><?php echo __('templates'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/email-lists.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email-lists.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span><?php echo __('email_audiences'); ?></span>
                </a>
            </li>
            <?php endif; ?>

            <li><hr class="nav-divider"></li>
            <li class="nav-section-label"><?php echo __('communications'); ?></li>
            <li class="nav-item">
                <a href="/pages/webforms.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'webforms.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    <span><?php echo __('web_forms'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/voip-dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'voip-dashboard.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span><?php echo __('voip_calls'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/whatsapp-dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'whatsapp-dashboard.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <span><?php echo __('whatsapp'); ?></span>
                </a>
            </li>

            <li><hr class="nav-divider"></li>
            <li class="nav-section-label"><?php echo __('business'); ?></li>
            <li class="nav-item">
                <a href="/pages/products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    <span><?php echo __('products'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/pages/automation.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'automation.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <span><?php echo __('automation'); ?></span>
                </a>
            </li>
            <li><hr class="nav-divider"></li>
            <li class="nav-section-label"><?php echo __('knowledge_hub'); ?></li>
            <li class="nav-item">
                <a href="/pages/documents.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quick-guides.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <span><?php echo __('documents'); ?></span>
                </a>
            </li>

            <?php if (isSuperAdmin()): ?>
            <li class="nav-item">
                <a href="/pages/super-admin.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'super-admin.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <span><?php echo __('platform_admin'); ?></span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasRole('Admin')): ?>
                <li><hr class="nav-divider"></li>
                <li class="nav-item">
                    <a href="/pages/users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span><?php echo __('users'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/pages/settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span><?php echo __('settings'); ?></span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($_isAdminOrImpersonating && !empty($_switchableUsers)): ?>
            <li><hr class="nav-divider"></li>
            <li class="nav-section-label"><?php echo __('switch_user'); ?></li>
            <li class="nav-item" style="padding:0 12px 4px;">
                <select id="switchUserSelect" class="form-control" style="font-size:12px;padding:6px 8px;width:100%;"
                        onchange="handleSwitchUser(this.value)">
                    <option value=""><?php echo htmlspecialchars(__('view_as_user')); ?></option>
                    <?php foreach ($_switchableUsers as $_su): ?>
                        <?php if ($_su['user_id'] == $_SESSION['user_id']) continue; ?>
                        <option value="<?php echo $_su['user_id']; ?>">
                            <?php echo htmlspecialchars($_su['full_name']); ?> (<?php echo htmlspecialchars($_su['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </li>
            <?php endif; ?>

            <li class="nav-spacer"><hr class="nav-divider"></li>
            <li class="nav-item">
                <a href="/pages/profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span><?php echo __('profile'); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/logout.php" class="nav-link" style="color:var(--color-danger);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span><?php echo __('logout'); ?></span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar"><?php echo getInitials($_SESSION['full_name']); ?></div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Hidden CSRF for switch-user JS -->
    <input type="hidden" name="csrf_token" id="globalCsrfToken" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">

    <!-- Main Content -->
    <main class="main-content">
    <?php if (isImpersonating()): ?>
        <?php $_origAdmin = getOriginalAdmin(); ?>
        <div id="impersonationBanner" style="background:linear-gradient(135deg,#ff9500,#ff6b00);color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;font-size:13px;font-weight:500;border-radius:8px;margin:0 0 16px 0;">
            <span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:middle;margin-right:6px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <?php echo __('viewing_as'); ?> <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong> (<?php echo htmlspecialchars($_SESSION['role']); ?>)
                &mdash; <?php echo __('logged_in_as'); ?> <?php echo htmlspecialchars($_origAdmin['full_name']); ?>
            </span>
            <button onclick="handleSwitchBack()" style="background:#fff;color:#ff6b00;border:none;padding:5px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                <?php echo __('switch_back'); ?>
            </button>
        </div>
    <?php endif; ?>
