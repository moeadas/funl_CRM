<?php
/**
 * White Label CRM - Settings Page
 * Premium design, tabbed layout, logo/favicon uploads, twilio/smtp setup, and custom fields CRUD
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . "/../includes/countries.php";
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();
requireRole(['Admin']);

$pageTitle = 'Settings';
$js = ['settings'];

$db = Database::getInstance();
$pdo = $db->getConnection();
$companyId = $_SESSION['company_id'] ?? null;

$success = '';
$error = '';

// Load current settings
$settingsQuery = $companyId 
    ? "SELECT setting_key, setting_value FROM settings WHERE company_id = ? OR company_id IS NULL"
    : "SELECT setting_key, setting_value FROM settings";
$stmt = $pdo->prepare($settingsQuery);
if ($companyId) {
    $stmt->execute([$companyId]);
} else {
    $stmt->execute();
}
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        $keysToSave = [
            'app_name', 'company_name', 'company_email', 'company_phone', 'company_website', 'company_address',
            'timezone', 'date_format', 'records_per_page', 'hidden_tabs',
            'voip_enabled', 'voip_recording_enabled', 'twilio_account_sid', 'twilio_auth_token', 'twilio_phone_number', 'twilio_twiml_app_sid',
            'whatsapp_enabled', 'whatsapp_from_number', 'whatsapp_sandbox_mode',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
            'email_from_name', 'email_from_address', 'email_reply_to', 'email_batch_size', 'email_batch_delay',
            'resend_api_key', 'resend_from_email',
            'ms_client_id', 'ms_tenant_id', 'ms_client_secret',
            'tracking_head_code', 'tracking_body_code', 'preloader_code',
            // Theme / Color Scheme - Backgrounds & Surfaces
            'theme_sidebar_bg', 'theme_bg', 'theme_surface', 'theme_input_bg', 'theme_modal_backdrop',
            // Borders
            'theme_border', 'theme_border_light', 'theme_table_row_border', 'theme_card_border',
            // Accent / CTA
            'theme_accent', 'theme_accent_hover', 'theme_accent_light', 'theme_accent_bg_tint',
            // Navigation
            'theme_nav_text', 'theme_nav_hover_text', 'theme_nav_hover_bg', 'theme_nav_active_text', 'theme_nav_active_bg', 'theme_nav_active_border',
            // Text
            'theme_text', 'theme_text_secondary', 'theme_text_tertiary', 'theme_text_faint', 'theme_heading_text', 'theme_link_color', 'theme_link_hover',
            // Buttons
            'theme_btn_primary_bg', 'theme_btn_primary_text', 'theme_btn_primary_hover', 'theme_btn_outline_bg', 'theme_btn_outline_border', 'theme_btn_outline_hover', 'theme_btn_secondary_bg', 'theme_btn_secondary_hover', 'theme_btn_danger_bg', 'theme_btn_danger_text', 'theme_btn_danger_border', 'theme_btn_danger_hover', 'theme_btn_dark_bg', 'theme_btn_dark_hover',
            // Cards
            'theme_card_bg', 'theme_card_border', 'theme_card_hover_shadow', 'theme_card_header_bg', 'theme_card_header_border',
            // Tables
            'theme_table_header_bg', 'theme_table_header_text', 'theme_table_row_border', 'theme_table_hover_bg',
            // Status Badges
            'theme_badge_active_bg', 'theme_badge_active_text', 'theme_badge_inactive_bg', 'theme_badge_inactive_text', 'theme_badge_dnc_bg', 'theme_badge_dnc_text', 'theme_badge_prospect_bg', 'theme_badge_prospect_text', 'theme_badge_sent_bg', 'theme_badge_sent_text', 'theme_badge_accepted_bg', 'theme_badge_accepted_text', 'theme_badge_rejected_bg', 'theme_badge_rejected_text', 'theme_badge_expired_bg', 'theme_badge_expired_text', 'theme_badge_draft_bg', 'theme_badge_draft_text',
            // Priority Badges
            'theme_pri_urgent_bg', 'theme_pri_urgent_text', 'theme_pri_high_bg', 'theme_pri_high_text', 'theme_pri_medium_bg', 'theme_pri_medium_text', 'theme_pri_low_bg', 'theme_pri_low_text',
            // Stat Cards
            'theme_stat_orange_bg', 'theme_stat_orange_text', 'theme_stat_green_bg', 'theme_stat_green_text', 'theme_stat_blue_bg', 'theme_stat_blue_text', 'theme_stat_yellow_bg', 'theme_stat_yellow_text', 'theme_stat_purple_bg', 'theme_stat_purple_text',
            // Inputs
            'theme_input_bg', 'theme_input_border', 'theme_input_text', 'theme_input_focus_border', 'theme_input_focus_outline', 'theme_placeholder_text',
            // Modals
            'theme_modal_backdrop', 'theme_modal_bg', 'theme_modal_border',
            // Sidebar
            'theme_sidebar_bg', 'theme_sidebar_border', 'theme_sidebar_logo_bg', 'theme_sidebar_footer_border', 'theme_sidebar_avatar_bg', 'theme_sidebar_avatar_text',
            // Status Colors (legacy / general)
            'theme_success', 'theme_warning', 'theme_danger', 'theme_info',
            // Layout
            'theme_sidebar_width', 'theme_card_radius', 'theme_input_radius', 'theme_btn_radius', 'theme_modal_radius',
            // Fonts
            'theme_font_heading', 'theme_font_body', 'theme_font_menu', 'theme_font_mono', 'theme_font_italic',
            'theme_font_heading_ar', 'theme_font_body_ar',
            // Font Sizes
            'theme_fs_base', 'theme_fs_h1', 'theme_fs_h2', 'theme_fs_card_title', 'theme_fs_nav', 'theme_fs_table', 'theme_fs_badge',
            // Font Weights
            'theme_fw_heading', 'theme_fw_body', 'theme_fw_nav', 'theme_fw_btn'
        ];

        $pdo->beginTransaction();
        try {
            foreach ($keysToSave as $key) {
                $value = $_POST[$key] ?? '';
                
                // Convert toggles to 1/0
                if (in_array($key, ['voip_enabled', 'voip_recording_enabled', 'whatsapp_enabled', 'whatsapp_sandbox_mode'])) {
                    $value = ($value === 'on' || $value === '1') ? '1' : '0';
                }
                
                // Handle hidden_tabs: convert array of checkboxes to comma-separated string
                if ($key === 'hidden_tabs') {
                    $tabsArray = $_POST['hidden_tabs'] ?? [];
                    $value = is_array($tabsArray) ? implode(',', $tabsArray) : '';
                }

                // H-4 fix: encrypt sensitive fields on write. If the field is empty
                // (user didn't change it), preserve the existing value.
                if ($key === 'smtp_password' && $value === '') {
                    continue; // don't overwrite the existing password
                }
                if ($key === 'resend_api_key' && $value === '') {
                    continue; // don't overwrite the existing key
                }
                if ($key === 'ms_client_secret' && $value === '') {
                    continue; // don't overwrite the existing secret
                }
                if (in_array($key, ['smtp_password', 'twilio_auth_token', 'resend_api_key', 'ms_client_secret'])) {
                    $value = encryptToken($value);
                }

                // Check if setting exists
                $stmtCheck = $pdo->prepare("SELECT 1 FROM settings WHERE setting_key = ? AND (company_id = ? OR company_id IS NULL)");
                $stmtCheck->execute([$key, $companyId]);
                
                if ($stmtCheck->fetch()) {
                    // Update
                    $stmtUpdate = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND (company_id = ? OR company_id IS NULL)");
                    $stmtUpdate->execute([$value, $key, $companyId]);
                } else {
                    // Insert
                    $stmtInsert = $pdo->prepare("INSERT INTO settings (company_id, setting_key, setting_value, setting_type) VALUES (?, ?, ?, 'text')");
                    $stmtInsert->execute([$companyId, $key, $value]);
                }
            }

            // Handle File Uploads (Logo & Favicon)
            $uploadDir = __DIR__ . '/../uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // 1. Logo Upload
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['company_logo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'svg'])) {
                    $newLogoName = 'logo_' . ($companyId ?? '0') . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . $newLogoName;
                    
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        // Delete old logo
                        $oldLogo = $settings['company_logo'] ?? '';
                        if ($oldLogo && file_exists($uploadDir . $oldLogo)) {
                            @unlink($uploadDir . $oldLogo);
                        }
                        
                        // Save in database
                        $stmtCheck = $pdo->prepare("SELECT 1 FROM settings WHERE setting_key = 'company_logo' AND (company_id = ? OR company_id IS NULL)");
                        $stmtCheck->execute([$companyId]);
                        if ($stmtCheck->fetch()) {
                            $stmtUpdate = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_logo' AND (company_id = ? OR company_id IS NULL)");
                            $stmtUpdate->execute([$newLogoName, $companyId]);
                        } else {
                            $stmtInsert = $pdo->prepare("INSERT INTO settings (company_id, setting_key, setting_value, setting_type) VALUES (?, 'company_logo', ?, 'text')");
                            $stmtInsert->execute([$companyId, $newLogoName]);
                        }
                        $settings['company_logo'] = $newLogoName;
                    }
                }
            }

            // 2. Favicon Upload
            if (isset($_FILES['company_favicon']) && $_FILES['company_favicon']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['company_favicon'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['ico', 'png', 'svg'])) {
                    $newFavName = 'favicon_' . ($companyId ?? '0') . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . $newFavName;
                    
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        // Delete old favicon
                        $oldFav = $settings['company_favicon'] ?? '';
                        if ($oldFav && file_exists($uploadDir . $oldFav)) {
                            @unlink($uploadDir . $oldFav);
                        }
                        
                        // Save in database
                        $stmtCheck = $pdo->prepare("SELECT 1 FROM settings WHERE setting_key = 'company_favicon' AND (company_id = ? OR company_id IS NULL)");
                        $stmtCheck->execute([$companyId]);
                        if ($stmtCheck->fetch()) {
                            $stmtUpdate = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_favicon' AND (company_id = ? OR company_id IS NULL)");
                            $stmtUpdate->execute([$newFavName, $companyId]);
                        } else {
                            $stmtInsert = $pdo->prepare("INSERT INTO settings (company_id, setting_key, setting_value, setting_type) VALUES (?, 'company_favicon', ?, 'text')");
                            $stmtInsert->execute([$companyId, $newFavName]);
                        }
                        $settings['company_favicon'] = $newFavName;
                    }
                }
            }

            $pdo->commit();
            $success = 'Settings saved successfully!';
            
            // Reload settings
            $stmt = $pdo->prepare($settingsQuery);
            if ($companyId) {
                $stmt->execute([$companyId]);
            } else {
                $stmt->execute();
            }
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            logActivity(getCurrentUserId(), 'Update Settings', 'Settings', 1, 'Updated company application settings');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error saving settings: ' . $e->getMessage();
        }
    }
}

$csrfToken = generateCSRFToken();
include __DIR__ . '/../includes/header.php';

?>
<script src="/assets/js/phone-picker.js?v=2"></script>




<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars(__('Settings')); ?></h1>
        <p class="text-muted" style="margin-top:4px;"><?php echo htmlspecialchars(__('Configure application settings, custom fields, VoIP integrations, and SMTP credentials')); ?></p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:20px;"><?php echo htmlspecialchars(__($success)); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars(__($error)); ?></div>
<?php endif; ?>

<div class="tabs-nav">
    <div class="tab-link active" data-tab="general" onclick="switchTab('general')"><?php echo htmlspecialchars(__('Company Profile')); ?></div>
    <div class="tab-link" data-tab="branding" onclick="switchTab('branding')"><?php echo htmlspecialchars(__('App Branding')); ?></div>
    <div class="tab-link" data-tab="theme" onclick="switchTab('theme')"><?php echo htmlspecialchars(__('Color Scheme & Fonts')); ?></div>
    <div class="tab-link" data-tab="voip" onclick="switchTab('voip')"><?php echo htmlspecialchars(__('VoIP & WhatsApp')); ?></div>
    <div class="tab-link" data-tab="smtp" onclick="switchTab('smtp')"><?php echo htmlspecialchars(__('SMTP & Email')); ?></div>
    <div class="tab-link" data-tab="integration" onclick="switchTab('integration')"><?php echo htmlspecialchars(__('Email Integration')); ?></div>
    <?php if (isSuperAdmin()): ?>
    <div class="tab-link" data-tab="tracking" onclick="switchTab('tracking')"><?php echo htmlspecialchars(__('Pixels & Tracking')); ?></div>
    <?php endif; ?>
    <div class="tab-link" data-tab="custom_fields" onclick="switchTab('custom_fields')"><?php echo htmlspecialchars(__('Custom Lead Fields')); ?></div>
    <div class="tab-link" data-tab="subscription" onclick="switchTab('subscription')"><?php echo htmlspecialchars(__('Subscription')); ?></div>
    <div class="tab-link" data-tab="visibility" onclick="switchTab('visibility')"><?php echo htmlspecialchars(__("Tab Visibility")); ?></div>

</div>

<form method="POST" enctype="multipart/form-data" id="settingsForm" onsubmit="var p=document.getElementById('company_phone_full');if(p){var inp=document.getElementById('company_phone');if(inp)inp.value=p.value;}">
    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
    <input type="hidden" name="action" value="save_settings">

    <!-- Tab: General -->
    <div class="tab-pane active" id="pane-general">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Company Profile Details')); ?></h3></div>
            <div class="card-body">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Company Name *')); ?></label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('App Branding Title')); ?></label>
                            <input type="text" name="app_name" class="form-control" value="<?php echo htmlspecialchars($settings['app_name'] ?? 'White Label CRM'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Company Email *')); ?></label>
                            <input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <?php echo renderPhonePicker(['id' => 'company_phone', 'label' => __('Support Phone'), 'value' => $settings['company_phone'] ?? '']); ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Company Website')); ?></label>
                            <input type="url" name="company_website" class="form-control" value="<?php echo htmlspecialchars($settings['company_website'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Default Records per Page')); ?></label>
                            <input type="number" name="records_per_page" class="form-control" value="<?php echo htmlspecialchars($settings['records_per_page'] ?? '25'); ?>" min="5" max="100">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Timezone')); ?></label>
                            <select name="timezone" class="form-control">
                                <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Eastern Time (New York)')); ?></option>
                                <option value="America/Chicago" <?php echo ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Central Time (Chicago)')); ?></option>
                                <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Pacific Time (Los Angeles)')); ?></option>
                                <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('London (GMT/BST)')); ?></option>
                                <option value="Europe/Paris" <?php echo ($settings['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Paris')); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Date Format')); ?></label>
                            <select name="date_format" class="form-control">
                                <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('YYYY-MM-DD (2026-05-31)')); ?></option>
                                <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('MM/DD/YYYY (05/31/2026)')); ?></option>
                                <option value="d-M-Y" <?php echo ($settings['date_format'] ?? '') === 'd-M-Y' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('DD-Mon-YYYY (31-May-2026)')); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Company Address')); ?></label>
                        <textarea name="company_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Branding -->
    <div class="tab-pane" id="pane-branding">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('App Logos & Asset Customization')); ?></h3></div>
            <div class="card-body">
                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Company Logo (Recommended: SVG or PNG, Transparent, max 200x60px)')); ?></label>
                        <input type="file" name="company_logo" class="form-control" accept=".png,.jpg,.jpeg,.svg">
                        <div class="branding-preview">
                            <span class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars(__('Current logo:')); ?></span>
                            <img src="<?php echo getCompanyLogo(); ?>?v=<?php echo time(); ?>" alt="Logo">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:24px;">
                        <label class="form-label"><?php echo htmlspecialchars(__('App Favicon (Recommended: 32x32px ICO, PNG, or SVG)')); ?></label>
                        <input type="file" name="company_favicon" class="form-control" accept=".ico,.png,.svg">
                        <div class="branding-preview">
                            <span class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars(__('Current favicon:')); ?></span>
                            <img src="<?php echo getCompanyFavicon(); ?>?v=<?php echo time(); ?>" alt="Favicon" style="max-height:32px;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:24px;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Preloader Code (HTML/CSS/JS)')); ?></label>
                        <textarea name="preloader_code" class="form-control" rows="12" placeholder="<?php echo htmlspecialchars(__('HTML code for preloader...')); ?>" style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($settings['preloader_code'] ?? ''); ?></textarea>
                        <p class="text-muted" style="font-size:11px; margin-top:4px;"><?php echo htmlspecialchars(__('Customize the HTML, CSS, and JS used for the full-screen preloader. Leave empty to use the default brand preloader.')); ?></p>
                    </div>
                </div>
            </div>
        </div>

    <!-- Tab: Color Scheme & Fonts -->
    <div class="tab-pane" id="pane-theme">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Color Scheme & Fonts')); ?></h3></div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:20px;font-size:13px;"><?php echo htmlspecialchars(__('Customize every color, font, and size of your CRM. Changes apply instantly across the app.')); ?></p>

                <?php
                // Helper to render a color picker + hex text input
                $renderColorField = function($name, $label, $default) use ($settings) {
                    $val = $settings[$name] ?? $default;
                    echo '<div class="form-group">';
                    echo '<label class="form-label">' . htmlspecialchars(__($label)) . '</label>';
                    echo '<div style="display:flex;gap:8px;align-items:center;">';
                    echo '<input type="color" name="' . $name . '" value="' . htmlspecialchars($val) . '" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">';
                    echo '<input type="text" name="' . $name . '_text" value="' . htmlspecialchars($val) . '" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">';
                    echo '</div>';
                    echo '</div>';
                };
                // Helper to render a text/rgba input (no color picker)
                $renderTextField = function($name, $label, $default, $placeholder = '') use ($settings) {
                    $val = $settings[$name] ?? $default;
                    echo '<div class="form-group">';
                    echo '<label class="form-label">' . htmlspecialchars(__($label)) . '</label>';
                    echo '<input type="text" name="' . $name . '" value="' . htmlspecialchars($val) . '" class="form-control" style="max-width:200px;font-family:var(--font-mono,monospace);font-size:12px;" placeholder="' . htmlspecialchars($placeholder) . '">';
                    echo '</div>';
                };
                // Helper to render a number input
                $renderNumberField = function($name, $label, $default, $min = 0, $max = 999, $suffix = 'px') use ($settings) {
                    $val = $settings[$name] ?? $default;
                    echo '<div class="form-group">';
                    echo '<label class="form-label">' . htmlspecialchars(__($label)) . '</label>';
                    echo '<div style="display:flex;gap:8px;align-items:center;">';
                    echo '<input type="number" name="' . $name . '" value="' . htmlspecialchars($val) . '" class="form-control" style="max-width:100px;" min="' . $min . '" max="' . $max . '">';
                    echo '<span class="text-muted" style="font-size:12px;">' . $suffix . '</span>';
                    echo '</div>';
                    echo '</div>';
                };
                // Helper to render a font dropdown
                $renderFontSelect = function($name, $label, $fonts, $default) use ($settings) {
                    $current = $settings[$name] ?? $default;
                    echo '<div class="form-group">';
                    echo '<label class="form-label">' . htmlspecialchars(__($label)) . '</label>';
                    echo '<select name="' . $name . '" class="form-control font-select">';
                    foreach ($fonts as $f) {
                        $sel = ($f === $current) ? ' selected' : '';
                        echo '<option value="' . htmlspecialchars($f) . '"' . $sel . '>' . htmlspecialchars($f) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                };
                // Helper to render a weight dropdown
                $renderWeightSelect = function($name, $label, $default) use ($settings) {
                    $current = $settings[$name] ?? $default;
                    $weights = ['400' => '400 (Regular)', '500' => '500 (Medium)', '600' => '600 (Semibold)', '700' => '700 (Bold)', '800' => '800 (Extrabold)'];
                    echo '<div class="form-group">';
                    echo '<label class="form-label">' . htmlspecialchars(__($label)) . '</label>';
                    echo '<select name="' . $name . '" class="form-control">';
                    foreach ($weights as $w => $label2) {
                        $sel = ($w === $current) ? ' selected' : '';
                        echo '<option value="' . $w . '"' . $sel . '>' . htmlspecialchars($label2) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                };
                ?>

                <!-- 1. Backgrounds & Surfaces -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Backgrounds & Surfaces')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_sidebar_bg', 'Sidebar Background', '#FDF8F1'); ?>
                    <?php $renderColorField('theme_bg', 'Main Content Background', '#FBF3EA'); ?>
                    <?php $renderColorField('theme_surface', 'Card/Surface Background', '#FDF8F1'); ?>
                    <?php $renderColorField('theme_input_bg', 'Input/Field Background', '#FFFFFF'); ?>
                </div>
                <?php $renderTextField('theme_modal_backdrop', 'Modal Backdrop Color (rgba)', 'rgba(31,23,20,0.5)', 'rgba(0,0,0,0.5)'); ?>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 2. Borders -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Borders')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_border', 'Primary Border Color', '#EBDFCE'); ?>
                    <?php $renderColorField('theme_border_light', 'Light Border Color', '#F0E6D8'); ?>
                    <?php $renderColorField('theme_table_row_border', 'Table Row Border', '#F0E6D8'); ?>
                    <?php $renderColorField('theme_card_border', 'Card Border', '#EBDFCE'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 3. Accent / CTA -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Accent / CTA')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_accent', 'Primary Accent (Buttons, Links, Active Nav)', '#E89BB8'); ?>
                    <?php $renderColorField('theme_accent_hover', 'Accent Hover Color', '#D2729A'); ?>
                    <?php $renderColorField('theme_accent_light', 'Accent Light/Focus Color', '#F5C2A0'); ?>
                </div>
                <?php $renderTextField('theme_accent_bg_tint', 'Accent Background Tint (rgba)', 'rgba(232,155,184,0.12)', 'rgba(232,155,184,0.12)'); ?>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 4. Navigation -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Navigation')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_nav_text', 'Nav Link Text Color (Default)', '#6F5C54'); ?>
                    <?php $renderColorField('theme_nav_hover_text', 'Nav Link Hover Text Color', '#1F1714'); ?>
                    <?php $renderColorField('theme_nav_hover_bg', 'Nav Link Hover Background', '#F5EBDD'); ?>
                    <?php $renderColorField('theme_nav_active_text', 'Nav Link Active Text Color', '#1F1714'); ?>
                    <?php $renderColorField('theme_nav_active_bg', 'Nav Link Active Background', '#F5EBDD'); ?>
                    <?php $renderColorField('theme_nav_active_border', 'Nav Link Active Border/Accent', '#E89BB8'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 5. Text -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Text')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_text', 'Primary Text Color', '#1F1714'); ?>
                    <?php $renderColorField('theme_text_secondary', 'Secondary Text Color', '#6F5C54'); ?>
                    <?php $renderColorField('theme_text_tertiary', 'Tertiary/Muted Text Color', '#8F7C72'); ?>
                    <?php $renderColorField('theme_text_faint', 'Faint Text Color', '#B5A597'); ?>
                    <?php $renderColorField('theme_heading_text', 'Heading Text Color', '#1F1714'); ?>
                    <?php $renderColorField('theme_link_color', 'Link Color', '#E89BB8'); ?>
                    <?php $renderColorField('theme_link_hover', 'Link Hover Color', '#D2729A'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 6. Buttons -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Buttons')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_btn_primary_bg', 'Primary Button Background', '#E89BB8'); ?>
                    <?php $renderColorField('theme_btn_primary_text', 'Primary Button Text Color', '#FFFFFF'); ?>
                    <?php $renderColorField('theme_btn_primary_hover', 'Primary Button Hover Background', '#D2729A'); ?>
                    <?php $renderColorField('theme_btn_outline_bg', 'Outline Button Background', '#FFFFFF'); ?>
                    <?php $renderColorField('theme_btn_outline_border', 'Outline Button Border Color', '#EBDFCE'); ?>
                    <?php $renderColorField('theme_btn_outline_hover', 'Outline Button Hover Background', '#F5EBDD'); ?>
                    <?php $renderColorField('theme_btn_secondary_bg', 'Secondary Button Background', '#F5EBDD'); ?>
                    <?php $renderColorField('theme_btn_secondary_hover', 'Secondary Button Hover Background', '#EBDFCE'); ?>
                    <?php $renderColorField('theme_btn_danger_bg', 'Danger Button Background', '#C97A47'); ?>
                    <?php $renderColorField('theme_btn_danger_text', 'Danger Button Text Color', '#FFFFFF'); ?>
                    <?php $renderColorField('theme_btn_danger_border', 'Danger Button Border Color', '#C97A47'); ?>
                    <?php $renderColorField('theme_btn_danger_hover', 'Danger Button Hover Background', '#B56A3D'); ?>
                    <?php $renderColorField('theme_btn_dark_bg', 'Dark Button Background', '#1F1714'); ?>
                    <?php $renderColorField('theme_btn_dark_hover', 'Dark Button Hover Background', '#3D2B24'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 7. Cards -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Cards')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_card_bg', 'Card Background', '#FDF8F1'); ?>
                    <?php $renderColorField('theme_card_border', 'Card Border Color', '#EBDFCE'); ?>
                    <?php $renderColorField('theme_card_header_bg', 'Card Header Background', '#FDF8F1'); ?>
                    <?php $renderColorField('theme_card_header_border', 'Card Header Border Color', '#EBDFCE'); ?>
                </div>
                <?php $renderTextField('theme_card_hover_shadow', 'Card Hover Shadow (rgba)', 'rgba(31,23,20,0.08)', 'rgba(0,0,0,0.08)'); ?>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 8. Tables -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Tables')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_table_header_bg', 'Table Header Background', '#F8EFE2'); ?>
                    <?php $renderColorField('theme_table_header_text', 'Table Header Text Color', '#1F1714'); ?>
                    <?php $renderColorField('theme_table_row_border', 'Table Row Border Color', '#F0E6D8'); ?>
                    <?php $renderColorField('theme_table_hover_bg', 'Table Row Hover Background', '#F5EBDD'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 9. Status Badges -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Status Badges')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_badge_active_bg', 'Active/Open - Background', '#D4EDDA'); ?>
                    <?php $renderColorField('theme_badge_active_text', 'Active/Open - Text', '#155724'); ?>
                    <?php $renderColorField('theme_badge_inactive_bg', 'Inactive/Closed - Background', '#F0E6D8'); ?>
                    <?php $renderColorField('theme_badge_inactive_text', 'Inactive/Closed - Text', '#6F5C54'); ?>
                    <?php $renderColorField('theme_badge_dnc_bg', 'Do Not Contact - Background', '#F8D7DA'); ?>
                    <?php $renderColorField('theme_badge_dnc_text', 'Do Not Contact - Text', '#721C24'); ?>
                    <?php $renderColorField('theme_badge_prospect_bg', 'Prospect - Background', '#FFF3CD'); ?>
                    <?php $renderColorField('theme_badge_prospect_text', 'Prospect - Text', '#856404'); ?>
                    <?php $renderColorField('theme_badge_sent_bg', 'Sent - Background', '#D1ECF1'); ?>
                    <?php $renderColorField('theme_badge_sent_text', 'Sent - Text', '#0C5460'); ?>
                    <?php $renderColorField('theme_badge_accepted_bg', 'Accepted - Background', '#D4EDDA'); ?>
                    <?php $renderColorField('theme_badge_accepted_text', 'Accepted - Text', '#155724'); ?>
                    <?php $renderColorField('theme_badge_rejected_bg', 'Rejected - Background', '#F8D7DA'); ?>
                    <?php $renderColorField('theme_badge_rejected_text', 'Rejected - Text', '#721C24'); ?>
                    <?php $renderColorField('theme_badge_expired_bg', 'Expired - Background', '#E2E3E5'); ?>
                    <?php $renderColorField('theme_badge_expired_text', 'Expired - Text', '#383D41'); ?>
                    <?php $renderColorField('theme_badge_draft_bg', 'Draft - Background', '#F0E6D8'); ?>
                    <?php $renderColorField('theme_badge_draft_text', 'Draft - Text', '#6F5C54'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 10. Priority Badges -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Priority Badges')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_pri_urgent_bg', 'Urgent - Background', '#F8D7DA'); ?>
                    <?php $renderColorField('theme_pri_urgent_text', 'Urgent - Text', '#721C24'); ?>
                    <?php $renderColorField('theme_pri_high_bg', 'High - Background', '#FFE5B4'); ?>
                    <?php $renderColorField('theme_pri_high_text', 'High - Text', '#856404'); ?>
                    <?php $renderColorField('theme_pri_medium_bg', 'Medium - Background', '#D1ECF1'); ?>
                    <?php $renderColorField('theme_pri_medium_text', 'Medium - Text', '#0C5460'); ?>
                    <?php $renderColorField('theme_pri_low_bg', 'Low - Background', '#D4EDDA'); ?>
                    <?php $renderColorField('theme_pri_low_text', 'Low - Text', '#155724'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 11. Stat Cards -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Stat Cards (Dashboard)')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_stat_orange_bg', 'Orange Stat Card - Background', '#FFE5D0'); ?>
                    <?php $renderColorField('theme_stat_orange_text', 'Orange Stat Card - Text', '#9A4A00'); ?>
                    <?php $renderColorField('theme_stat_green_bg', 'Green Stat Card - Background', '#D4EDDA'); ?>
                    <?php $renderColorField('theme_stat_green_text', 'Green Stat Card - Text', '#155724'); ?>
                    <?php $renderColorField('theme_stat_blue_bg', 'Blue Stat Card - Background', '#D1ECF1'); ?>
                    <?php $renderColorField('theme_stat_blue_text', 'Blue Stat Card - Text', '#0C5460'); ?>
                    <?php $renderColorField('theme_stat_yellow_bg', 'Yellow Stat Card - Background', '#FFF3CD'); ?>
                    <?php $renderColorField('theme_stat_yellow_text', 'Yellow Stat Card - Text', '#856404'); ?>
                    <?php $renderColorField('theme_stat_purple_bg', 'Purple Stat Card - Background', '#E2D9F3'); ?>
                    <?php $renderColorField('theme_stat_purple_text', 'Purple Stat Card - Text', '#4A2C7A'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 12. Inputs -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Inputs')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_input_bg', 'Input Background', '#FFFFFF'); ?>
                    <?php $renderColorField('theme_input_border', 'Input Border Color', '#EBDFCE'); ?>
                    <?php $renderColorField('theme_input_text', 'Input Text Color', '#1F1714'); ?>
                    <?php $renderColorField('theme_input_focus_border', 'Input Focus Border Color', '#E89BB8'); ?>
                    <?php $renderColorField('theme_placeholder_text', 'Placeholder Text Color', '#B5A597'); ?>
                </div>
                <?php $renderTextField('theme_input_focus_outline', 'Input Focus Outline (rgba)', 'rgba(232,155,184,0.25)', 'rgba(232,155,184,0.25)'); ?>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 13. Modals -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Modals')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_modal_bg', 'Modal Background', '#FDF8F1'); ?>
                    <?php $renderColorField('theme_modal_border', 'Modal Border Color', '#EBDFCE'); ?>
                </div>
                <?php $renderTextField('theme_modal_backdrop', 'Modal Backdrop Color (rgba)', 'rgba(31,23,20,0.5)', 'rgba(0,0,0,0.5)'); ?>
                <?php $renderNumberField('theme_modal_radius', 'Modal Border Radius', '16', 0, 50, 'px'); ?>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- 14. Sidebar -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Sidebar')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_sidebar_bg', 'Sidebar Background', '#FDF8F1'); ?>
                    <?php $renderColorField('theme_sidebar_border', 'Sidebar Border Color (Right)', '#EBDFCE'); ?>
                    <?php $renderColorField('theme_sidebar_logo_bg', 'Sidebar Logo Area Background', '#FDF8F1'); ?>
                    <?php $renderColorField('theme_sidebar_footer_border', 'Sidebar Footer Border Color', '#EBDFCE'); ?>
                    <?php $renderColorField('theme_sidebar_avatar_bg', 'Sidebar Avatar Background', '#E89BB8'); ?>
                    <?php $renderColorField('theme_sidebar_avatar_text', 'Sidebar Avatar Text Color', '#FFFFFF'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- General Status Colors -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('General Status Colors')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderColorField('theme_success', 'Success', '#5E8259'); ?>
                    <?php $renderColorField('theme_warning', 'Warning', '#9A7A2C'); ?>
                    <?php $renderColorField('theme_danger', 'Danger', '#C97A47'); ?>
                    <?php $renderColorField('theme_info', 'Info', '#4F7787'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- Layout Settings -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Layout Settings')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderNumberField('theme_sidebar_width', 'Sidebar Width', '260', 180, 400, 'px'); ?>
                    <?php $renderNumberField('theme_card_radius', 'Card Border Radius', '16', 0, 50, 'px'); ?>
                    <?php $renderNumberField('theme_input_radius', 'Input Border Radius', '10', 0, 50, 'px'); ?>
                    <?php $renderNumberField('theme_btn_radius', 'Button Border Radius', '10', 0, 50, 'px'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <div style="display:flex;gap:12px;align-items:center;">
                    <button type="button" class="btn btn-outline" onclick="resetThemeDefaults()"><?php echo htmlspecialchars(__('Reset All to Defaults')); ?></button>
                    <span class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars(__('Click Save Changes below to apply.')); ?></span>
                </div>
            </div>
        </div>

        <!-- Fonts Card -->
        <div class="card" style="margin-top:20px;">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Fonts')); ?></h3></div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:20px;font-size:13px;"><?php echo htmlspecialchars(__('Choose fonts for headings, body text, labels, and italic accents. Supports English and Arabic Google Fonts.')); ?></p>

                <?php
                $headingFonts = ['Plus Jakarta Sans','Roboto','Open Sans','Poppins','Inter','Montserrat','Nunito','DM Sans','Manrope','Sora','Outfit','Space Grotesk','Fraunces','Playfair Display','Lora','Merriweather','PT Serif','Crimson Pro','Libre Baskerville','Source Serif Pro','Work Sans','Karla','Mulish','Hind','Asap','Archivo','Bricolage Grotesque','Instrument Sans'];
                $bodyFonts = ['Plus Jakarta Sans','Open Sans','Inter','Roboto','DM Sans','Manrope','Nunito','Poppins','Montserrat','Outfit','Space Grotesk','Sora','Source Sans Pro','PT Sans','Fira Sans','Work Sans','Karla','Mulish','Hind','Asap','Archivo','Bricolage Grotesque','Instrument Sans'];
                $monoFonts = ['JetBrains Mono','Fira Code','Source Code Pro','IBM Plex Mono','Roboto Mono','Space Mono','Ubuntu Mono','Cascadia Code','Inconsolata','PT Mono','DM Mono'];
                $italicFonts = ['Fraunces','Playfair Display','Lora','Merriweather','PT Serif','Crimson Pro','Libre Baskerville','Source Serif Pro','EB Garamond','Spectral'];
                $arabicFonts = ['Default (follows English)','Cairo','Tajawal','Almarai','IBM Plex Sans Arabic','Noto Sans Arabic','Readex Pro','El Messiri','Lateef','Amiri','Reem Kufi','Scheherazade New','Markazi Text','Vazirmatn','Noto Kufi Arabic','Dubai','Changa','Mada','Jomhuria','Lalezar'];
                ?>

                <!-- English Fonts -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('English Fonts')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderFontSelect('theme_font_heading', 'Heading / Title Font', $headingFonts, 'Plus Jakarta Sans'); ?>
                    <?php $renderFontSelect('theme_font_body', 'Body / Text Font', $bodyFonts, 'Plus Jakarta Sans'); ?>
                    <?php $renderFontSelect('theme_font_menu', 'Menu / Nav Link Font', $bodyFonts, 'Plus Jakarta Sans'); ?>
                    <?php $renderFontSelect('theme_font_mono', 'Monospace / Label Font', $monoFonts, 'JetBrains Mono'); ?>
                    <?php $renderFontSelect('theme_font_italic', 'Italic Accent Font (for em in headings)', $italicFonts, 'Fraunces'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- Arabic Fonts -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Arabic Fonts')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderFontSelect('theme_font_heading_ar', 'Heading / Title Font (Arabic)', $arabicFonts, 'Default (follows English)'); ?>
                    <?php $renderFontSelect('theme_font_body_ar', 'Body / Text Font (Arabic)', $arabicFonts, 'Default (follows English)'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- Font Size Settings -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Font Size Settings')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderNumberField('theme_fs_base', 'Base Body Font Size', '14', 10, 24, 'px'); ?>
                    <?php $renderNumberField('theme_fs_h1', 'Heading h1 Font Size', '28', 18, 48, 'px'); ?>
                    <?php $renderNumberField('theme_fs_h2', 'Heading h2 Font Size', '22', 14, 36, 'px'); ?>
                    <?php $renderNumberField('theme_fs_card_title', 'Card Title Font Size', '16', 12, 28, 'px'); ?>
                    <?php $renderNumberField('theme_fs_nav', 'Nav Link Font Size', '13', 10, 18, 'px'); ?>
                    <?php $renderNumberField('theme_fs_table', 'Table Text Font Size', '13', 10, 18, 'px'); ?>
                    <?php $renderNumberField('theme_fs_badge', 'Badge Font Size', '11', 8, 16, 'px'); ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <!-- Font Weight Settings -->
                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Font Weight Settings')); ?></h4>
                <div class="form-grid-3">
                    <?php $renderWeightSelect('theme_fw_heading', 'Heading Font Weight', '700'); ?>
                    <?php $renderWeightSelect('theme_fw_body', 'Body Font Weight', '400'); ?>
                    <?php $renderWeightSelect('theme_fw_nav', 'Nav Link Font Weight', '500'); ?>
                    <?php $renderWeightSelect('theme_fw_btn', 'Button Font Weight', '600'); ?>
                </div>
            </div>
        </div>

        <script>
        function resetThemeDefaults() {
            var defaults = {
                /* Backgrounds */
                theme_sidebar_bg: '#FDF8F1', theme_bg: '#FBF3EA', theme_surface: '#FDF8F1',
                theme_input_bg: '#FFFFFF', theme_modal_backdrop: 'rgba(31,23,20,0.5)',
                /* Borders */
                theme_border: '#EBDFCE', theme_border_light: '#F0E6D8',
                theme_table_row_border: '#F0E6D8', theme_card_border: '#EBDFCE',
                /* Accent */
                theme_accent: '#E89BB8', theme_accent_hover: '#D2729A', theme_accent_light: '#F5C2A0',
                theme_accent_bg_tint: 'rgba(232,155,184,0.12)',
                /* Navigation */
                theme_nav_text: '#6F5C54', theme_nav_hover_text: '#1F1714', theme_nav_hover_bg: '#F5EBDD',
                theme_nav_active_text: '#1F1714', theme_nav_active_bg: '#F5EBDD', theme_nav_active_border: '#E89BB8',
                /* Text */
                theme_text: '#1F1714', theme_text_secondary: '#6F5C54', theme_text_tertiary: '#8F7C72',
                theme_text_faint: '#B5A597', theme_heading_text: '#1F1714',
                theme_link_color: '#E89BB8', theme_link_hover: '#D2729A',
                /* Buttons */
                theme_btn_primary_bg: '#E89BB8', theme_btn_primary_text: '#FFFFFF', theme_btn_primary_hover: '#D2729A',
                theme_btn_outline_bg: '#FFFFFF', theme_btn_outline_border: '#EBDFCE', theme_btn_outline_hover: '#F5EBDD',
                theme_btn_secondary_bg: '#F5EBDD', theme_btn_secondary_hover: '#EBDFCE',
                theme_btn_danger_bg: '#C97A47', theme_btn_danger_text: '#FFFFFF', theme_btn_danger_border: '#C97A47', theme_btn_danger_hover: '#B56A3D',
                theme_btn_dark_bg: '#1F1714', theme_btn_dark_hover: '#3D2B24',
                /* Cards */
                theme_card_bg: '#FDF8F1', theme_card_border: '#EBDFCE', theme_card_hover_shadow: 'rgba(31,23,20,0.08)',
                theme_card_header_bg: '#FDF8F1', theme_card_header_border: '#EBDFCE',
                /* Tables */
                theme_table_header_bg: '#F8EFE2', theme_table_header_text: '#1F1714', theme_table_hover_bg: '#F5EBDD',
                /* Status Badges */
                theme_badge_active_bg: '#D4EDDA', theme_badge_active_text: '#155724',
                theme_badge_inactive_bg: '#F0E6D8', theme_badge_inactive_text: '#6F5C54',
                theme_badge_dnc_bg: '#F8D7DA', theme_badge_dnc_text: '#721C24',
                theme_badge_prospect_bg: '#FFF3CD', theme_badge_prospect_text: '#856404',
                theme_badge_sent_bg: '#D1ECF1', theme_badge_sent_text: '#0C5460',
                theme_badge_accepted_bg: '#D4EDDA', theme_badge_accepted_text: '#155724',
                theme_badge_rejected_bg: '#F8D7DA', theme_badge_rejected_text: '#721C24',
                theme_badge_expired_bg: '#E2E3E5', theme_badge_expired_text: '#383D41',
                theme_badge_draft_bg: '#F0E6D8', theme_badge_draft_text: '#6F5C54',
                /* Priority Badges */
                theme_pri_urgent_bg: '#F8D7DA', theme_pri_urgent_text: '#721C24',
                theme_pri_high_bg: '#FFE5B4', theme_pri_high_text: '#856404',
                theme_pri_medium_bg: '#D1ECF1', theme_pri_medium_text: '#0C5460',
                theme_pri_low_bg: '#D4EDDA', theme_pri_low_text: '#155724',
                /* Stat Cards */
                theme_stat_orange_bg: '#FFE5D0', theme_stat_orange_text: '#9A4A00',
                theme_stat_green_bg: '#D4EDDA', theme_stat_green_text: '#155724',
                theme_stat_blue_bg: '#D1ECF1', theme_stat_blue_text: '#0C5460',
                theme_stat_yellow_bg: '#FFF3CD', theme_stat_yellow_text: '#856404',
                theme_stat_purple_bg: '#E2D9F3', theme_stat_purple_text: '#4A2C7A',
                /* Inputs */
                theme_input_border: '#EBDFCE', theme_input_text: '#1F1714',
                theme_input_focus_border: '#E89BB8', theme_input_focus_outline: 'rgba(232,155,184,0.25)',
                theme_placeholder_text: '#B5A597',
                /* Modals */
                theme_modal_bg: '#FDF8F1', theme_modal_border: '#EBDFCE',
                /* Sidebar */
                theme_sidebar_border: '#EBDFCE', theme_sidebar_logo_bg: '#FDF8F1',
                theme_sidebar_footer_border: '#EBDFCE', theme_sidebar_avatar_bg: '#E89BB8', theme_sidebar_avatar_text: '#FFFFFF',
                /* Status Colors */
                theme_success: '#5E8259', theme_warning: '#9A7A2C', theme_danger: '#C97A47', theme_info: '#4F7787',
                /* Layout */
                theme_sidebar_width: '260', theme_card_radius: '16', theme_input_radius: '10', theme_btn_radius: '10', theme_modal_radius: '16',
                /* Fonts */
                theme_font_heading: 'Plus Jakarta Sans', theme_font_body: 'Plus Jakarta Sans',
                theme_font_menu: 'Plus Jakarta Sans', theme_font_mono: 'JetBrains Mono', theme_font_italic: 'Fraunces',
                theme_font_heading_ar: 'Default (follows English)', theme_font_body_ar: 'Default (follows English)',
                /* Font Sizes */
                theme_fs_base: '14', theme_fs_h1: '28', theme_fs_h2: '22', theme_fs_card_title: '16',
                theme_fs_nav: '13', theme_fs_table: '13', theme_fs_badge: '11',
                /* Font Weights */
                theme_fw_heading: '700', theme_fw_body: '400', theme_fw_nav: '500', theme_fw_btn: '600'
            };
            Object.keys(defaults).forEach(function(key) {
                var el = document.querySelector('[name="' + key + '"]');
                if (el) {
                    el.value = defaults[key];
                    var txt = document.querySelector('[name="' + key + '_text"]');
                    if (txt) txt.value = defaults[key];
                    // Trigger color picker sync
                    if (el.type === 'color' && txt) { el.value = defaults[key]; }
                }
            });
        }
        </script>
    <!-- Tab: VoIP -->
    <div class="tab-pane" id="pane-voip">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Twilio Integration settings')); ?></h3></div>
            <div class="card-body">
                    <div class="switch-container">
                        <div class="switch-info">
                            <h4><?php echo htmlspecialchars(__('Enable Twilio VoIP Calling')); ?></h4>
                            <p><?php echo htmlspecialchars(__('Enable client calling and receiving directly inside the browser')); ?></p>
                        </div>
                        <input type="checkbox" name="voip_enabled" value="1" <?php echo ($settings['voip_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="switch-container">
                        <div class="switch-info">
                            <h4><?php echo htmlspecialchars(__('Enable Call Recording')); ?></h4>
                            <p><?php echo htmlspecialchars(__('Automatically record incoming/outgoing client VoIP calls')); ?></p>
                        </div>
                        <input type="checkbox" name="voip_recording_enabled" value="1" <?php echo ($settings['voip_recording_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Twilio Account SID')); ?></label>
                            <input type="text" name="twilio_account_sid" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_account_sid'] ?? ''); ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Twilio Auth Token')); ?></label>
                            <input type="password" name="twilio_auth_token" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_auth_token'] ?? ''); ?>" placeholder="••••••••••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Twilio VoIP Phone Number')); ?></label>
                            <input type="text" name="twilio_phone_number" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_phone_number'] ?? ''); ?>" placeholder="+1234567890">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('TwiML App SID')); ?></label>
                            <input type="text" name="twilio_twiml_app_sid" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_twiml_app_sid'] ?? ''); ?>" placeholder="APxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                    </div>

                    <h3 class="card-title" style="margin-top:32px;margin-bottom:16px;"><?php echo htmlspecialchars(__('WhatsApp Integration')); ?></h3>
                    <div class="switch-container">
                        <div class="switch-info">
                            <h4><?php echo htmlspecialchars(__('Enable WhatsApp Integration')); ?></h4>
                            <p><?php echo htmlspecialchars(__('Send and receive messages directly inside the WhatsApp Dashboard')); ?></p>
                        </div>
                        <input type="checkbox" name="whatsapp_enabled" value="1" <?php echo ($settings['whatsapp_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="switch-container">
                        <div class="switch-info">
                            <h4><?php echo htmlspecialchars(__('Sandbox Mode')); ?></h4>
                            <p><?php echo htmlspecialchars(__('Use Twilio WhatsApp Sandbox mode (+1 415 523 8886) for testing')); ?></p>
                        </div>
                        <input type="checkbox" name="whatsapp_sandbox_mode" value="1" <?php echo ($settings['whatsapp_sandbox_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('WhatsApp Sender Number')); ?></label>
                        <input type="text" name="whatsapp_from_number" class="form-control" value="<?php echo htmlspecialchars($settings['whatsapp_from_number'] ?? ''); ?>" placeholder="+1234567890">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: SMTP -->
    <div class="tab-pane" id="pane-smtp">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('SMTP Server & Email Marketing Settings')); ?></h3></div>
            <div class="card-body">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Host')); ?></label>
                            <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.mailgun.org">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Port')); ?></label>
                            <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>" placeholder="587">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Username')); ?></label>
                            <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="smtp@example.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Password')); ?></label>
                            <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="••••••••••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Encryption')); ?></label>
                            <select name="smtp_encryption" class="form-control">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('TLS (Recommended)')); ?></option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('SSL')); ?></option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('None')); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Default From Name')); ?></label>
                            <input type="text" name="email_from_name" class="form-control" value="<?php echo htmlspecialchars($settings['email_from_name'] ?? ''); ?>" placeholder="Your Company Name">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Default From Email')); ?></label>
                            <input type="email" name="email_from_address" class="form-control" value="<?php echo htmlspecialchars($settings['email_from_address'] ?? ''); ?>" placeholder="noreply@yourdomain.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Default Reply-To Email')); ?></label>
                            <input type="email" name="email_reply_to" class="form-control" value="<?php echo htmlspecialchars($settings['email_reply_to'] ?? ''); ?>" placeholder="reply@yourdomain.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Marketing Batch Size')); ?></label>
                            <input type="number" name="email_batch_size" class="form-control" value="<?php echo htmlspecialchars($settings['email_batch_size'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Delay Between Batches (seconds)')); ?></label>
                            <input type="number" name="email_batch_delay" class="form-control" value="<?php echo htmlspecialchars($settings['email_batch_delay'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

        <?php if (isSuperAdmin()): ?>
            <div class="card" style="margin-top:24px;">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Resend API (Transactional Email)')); ?></h3></div>
            <div class="card-body">
                    <p style="color:var(--color-text-muted,#6b7280);font-size:14px;margin-bottom:16px;">
                        <?php echo __('Resend handles verification emails, password resets, and other transactional emails. Get your API key at'); ?>
                        <a href="https://resend.com/api-keys" target="_blank" rel="noopener">resend.com/api-keys</a>
                    </p>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Resend API Key')); ?></label>
                            <?php
                                $resendKey = $settings['resend_api_key'] ?? '';
                                $resendMasked = $resendKey ? "API key is configured" : "";
                            ?>
                            <input type="password" name="resend_api_key" class="form-control" value="" placeholder="<?php echo $resendMasked ? htmlspecialchars($resendMasked) : 're_...'; ?>">
                            <?php if ($resendKey): ?>
                                <p style="font-size:12px;color:#10b981;margin-top:4px;">✓ <?php echo __('API key is set'); ?></p>
                            <?php else: ?>
                                <p style="font-size:12px;color:#dc2626;margin-top:4px;">⚠ <?php echo __('No API key set — verification emails will not be sent'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Resend From Email')); ?></label>
                            <input type="text" name="resend_from_email" class="form-control" value="<?php echo htmlspecialchars($settings['resend_from_email'] ?? ''); ?>" placeholder="FunL CRM <noreply@funl.online>">
                            <p style="font-size:12px;color:var(--color-text-muted,#6b7280);margin-top:4px;"><?php echo __('Format: Your Name <noreply@yourdomain.com>'); ?></p>
                        </div>
                    </div>
                    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px 16px;margin-top:8px;font-size:13px;color:#0369a1;">
                        <strong>💡 <?php echo __('How it works'); ?>:</strong> <?php echo __('The API key is encrypted and stored securely. Leave the field blank to keep the current key. Only super admins can view or change this setting.'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <!-- Tab: Email Integration -->
    <div class="tab-pane" id="pane-integration">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Email Integration (Office 365)')); ?></h3></div>
            <div class="card-body">
                    <p style="font-size:13px;color:var(--color-text-muted,#6b7280);margin-bottom:16px;"><?php echo __('Connect your Office 365 account to send emails directly from the CRM. Each user connects their own account.'); ?></p>

                <?php if (isSuperAdmin()): ?>
                    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px;margin-bottom:20px;">
                        <h4 style="margin:0 0 8px;font-size:14px;color:#0369a1;">Microsoft Azure App Configuration</h4>
                        <p style="font-size:12px;color:#0369a1;margin:0 0 12px;">Required: Register an app in Azure Active Directory and enter the credentials below. See <a href="https://learn.microsoft.com/en-us/graph/auth-register-app-v2" target="_blank">Azure App Registration Guide</a>.</p>
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Client ID</label>
                                <input type="text" name="ms_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['ms_client_id'] ?? ''); ?>" placeholder="Azure App Client ID">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tenant ID</label>
                                <input type="text" name="ms_tenant_id" class="form-control" value="<?php echo htmlspecialchars($settings['ms_tenant_id'] ?? ''); ?>" placeholder="common (or your tenant GUID)">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Client Secret</label>
                                <input type="password" name="ms_client_secret" class="form-control" value="" placeholder="<?php echo !empty($settings['ms_client_secret']) ? 'Secret is set' : 'Azure App Client Secret'; ?>">
                            </div>
                        </div>
                        <p style="font-size:11px;color:#6b7280;margin-top:8px;">Redirect URI must be set to: <code><?php echo htmlspecialchars(getenv('APP_URL') ?: 'https://app.funl.online'); ?>/api/microsoft-callback.php</code></p>
                    </div>
                    <hr style="margin:20px 0;border:none;border-top:1px solid #e5e7eb;">
                    <?php endif; ?>

                    <?php
                    $currentUser = getCurrentUser();
                    $msConnected = !empty($currentUser['ms_connected_email']);
                    $csrfToken = generateCSRFToken();

                    // Build OAuth URL using DB settings if available
                    $msClientId = $settings['ms_client_id'] ?? '' ?: (defined('MS_CLIENT_ID') ? MS_CLIENT_ID : '');
                    $msTenantId = $settings['ms_tenant_id'] ?? '' ?: (defined('MS_TENANT_ID') ? MS_TENANT_ID : 'common');
                    $msRedirectUri = (getenv('APP_URL') ?: 'https://app.funl.online') . '/api/microsoft-callback.php';

                    $msAuthUrl = '';
                    if ($msClientId) {
                        $state = bin2hex(random_bytes(16));
                        $_SESSION['ms_oauth_state'] = $state;
                        $msAuthUrl = 'https://login.microsoftonline.com/' . $msTenantId . '/oauth2/v2.0/authorize?' . http_build_query([
                            'client_id'     => $msClientId,
                            'response_type' => 'code',
                            'redirect_uri'  => $msRedirectUri,
                            'response_mode' => 'query',
                            'scope'         => 'https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read offline_access',
                            'state'         => $state,
                        ]);
                    }
                    ?>

                    <h4 style="margin:0 0 12px;font-size:15px;">Your Office 365 Connection</h4>
                    <?php if ($msConnected): ?>
                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin-bottom:16px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:40px;height:40px;border-radius:8px;background:#0078d4;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;">&#127760;</div>
                                <div>
                                    <h4 style="margin:0;font-size:14px;">Office 365 Connected</h4>
                                    <p style="margin:2px 0 0;font-size:13px;color:#6b7280;"><?php echo htmlspecialchars($currentUser['ms_connected_email']); ?></p>
                                </div>
                            </div>
                        </div>
                        <form method="POST" action="/pages/profile.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="disconnect_microsoft">
                            <button type="submit" class="btn btn-outline" style="color:#dc2626;border-color:#fecaca;">Disconnect Office 365</button>
                        </form>
                    <?php else: ?>
                        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:16px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:40px;height:40px;border-radius:8px;background:#9ca3af;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;opacity:0.5;">&#127760;</div>
                                <div>
                                    <h4 style="margin:0;font-size:14px;color:#6b7280;">Not Connected</h4>
                                    <p style="margin:2px 0 0;font-size:13px;color:#9ca3af;">Connect your Office 365 account to send emails from the CRM</p>
                                </div>
                            </div>
                        </div>
                        <?php if ($msAuthUrl): ?>
                            <a href="<?php echo $msAuthUrl; ?>" class="btn btn-primary">Connect Office 365</a>
                        <?php else: ?>
                            <button class="btn btn-primary" disabled style="opacity:0.6;cursor:not-allowed;">Connect Office 365</button>
                        <?php if (isSuperAdmin()): ?><div style="font-size:12px;color:#dc2626;margin-top:8px;">Client ID not configured. Enter your Azure App credentials in the Azure App Configuration section above and save.</div><?php else: ?><div style="font-size:12px;color:#6b7280;margin-top:8px;">Office 365 integration is available once your platform admin configures the Microsoft Azure app.</div><?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div><?php if (isSuperAdmin()): ?>
    <div class="tab-pane" id="pane-tracking">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Tracking Codes & Pixels')); ?></h3></div>
            <div class="card-body">
                    <div class="alert alert-info" style="margin-bottom: 20px; display: flex; align-items: flex-start; gap: 8px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span><?php echo htmlspecialchars(__('Add custom scripts (Google Tag Manager, Google Analytics, Meta Pixel, etc.) to your CRM website header and body tags. Only enter valid HTML scripts/tags.')); ?></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Header Tracking Code (injects in <head> tag on all pages)')); ?></label>
                        <textarea name="tracking_head_code" class="form-control" rows="8" placeholder="&lt;script&gt;&#10;  // Your Google Tag Manager / Analytics / Meta Pixel header code here&#10;&lt;/script&gt;" style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($settings['tracking_head_code'] ?? ''); ?></textarea>
                        <p class="text-muted" style="font-size:11px; margin-top:4px;"><?php echo htmlspecialchars(__('This code will be printed raw in the HTML <head> block of all client-facing pages (dashboard, leads, register, login, etc.).')); ?></p>
                    </div>
                    <div class="form-group" style="margin-top:24px;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Body Tracking Code (injects right after <body> tag on all pages)')); ?></label>
                        <textarea name="tracking_body_code" class="form-control" rows="8" placeholder="&lt;noscript&gt;&#10;  &lt;iframe src=&quot;https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX&quot; height=&quot;0&quot; width=&quot;0&quot; style=&quot;display:none;visibility:hidden&quot;&gt;&lt;/iframe&gt;&#10;&lt;/noscript&gt;" style="font-family: monospace; font-size: 13px;"><?php echo htmlspecialchars($settings['tracking_body_code'] ?? ''); ?></textarea>
                        <p class="text-muted" style="font-size:11px; margin-top:4px;"><?php echo htmlspecialchars(__('This code is commonly used for noscript tracking fallbacks, injected immediately after the opening body tag.')); ?></p>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>

        <!-- Custom Fields (Handled dynamically) -->
    <div class="tab-pane" id="pane-custom_fields">
        <div class="card">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <h3 class="card-title"><?php echo htmlspecialchars(__('Lead Custom Fields')); ?></h3>
                    <a href="/pages/custom-field-new.php?entity=lead" class="btn btn-primary btn-sm">+ <?php echo htmlspecialchars(__('Add Field')); ?></a>
                </div>
            <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars(__('Field Label')); ?></th>
                                    <th><?php echo htmlspecialchars(__('Code Name')); ?></th>
                                    <th><?php echo htmlspecialchars(__('Type')); ?></th>
                                    <th><?php echo htmlspecialchars(__('Required')); ?></th>
                                    <th><?php echo htmlspecialchars(__('Sort Order')); ?></th>
                                    <th><?php echo htmlspecialchars(__('Active')); ?></th>
                                    <th><?php echo htmlspecialchars(__('Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody id="customFieldsList">
                                <tr><td colspan="7" class="text-center text-muted"><?php echo htmlspecialchars(__('Loading custom fields...')); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Subscription Tab ──────────────────────────────────────────── -->
    <div class="tab-pane" id="pane-subscription">
            <?php
            $companySub = getCompany(getCurrentCompanyId());
            $subBadge = $companySub['subscription_status'] ?? 'trial';
            $badgeMap = ['active' => 'badge-active', 'trial' => 'badge-trial', 'past_due' => 'badge-past_due', 'cancelled' => 'badge-cancelled'];
            $badgeClass = $badgeMap[$subBadge] ?? 'badge-cancelled';
            ?>
            <div style="padding: 40px 24px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">💳</div>
                <h2 style="margin-bottom: 12px;">Your Subscription</h2>
                <div style="margin-bottom: 20px;">
                    <span class="badge <?= $badgeClass ?>" style="font-size:14px;padding:6px 16px;">
                        <?= ucfirst($subBadge) ?>
                    </span>
                </div>
                <p style="color:#666;margin-bottom:24px;">
                    <?php if ($subBadge === 'active'): ?>
                        Your plan is active. Manage billing, upgrade, or cancel below.
                    <?php elseif ($subBadge === 'trial'): ?>
                        You're on a trial period. Subscribe to keep your data.
                    <?php elseif ($subBadge === 'past_due'): ?>
                        Payment failed. Please update your payment method.
                    <?php else: ?>
                        Subscription cancelled. Resubscribe to restore access.
                    <?php endif; ?>
                </p>
                <a href="/pages/billing.php" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:#667eea;color:white;border-radius:8px;font-size:15px;font-weight:600;text-decoration:none;">
                    Manage Subscription →
                </a>
            </div>
        </div>
        
        <!-- Tab: Tab Visibility — Control which sidebar tabs are visible to users -->
        <div class="tab-pane" id="pane-visibility">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Tab Visibility</h3></div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--color-text-secondary);margin-bottom:16px;">
                        Hide sidebar tabs that your team doesn't use. Hidden tabs remain accessible via direct URL but won't appear in the navigation menu.
                    </p>
                    <?php
                    // Define all toggleable tabs with their labels
                    $toggleableTabs = [
                        'proposals' => 'Proposals',
                        'pipeline' => 'Pipeline',
                        'tickets' => 'Support',
                        'interactions' => 'Interactions',
                        'export' => 'Export Data',
                        'email-campaigns' => 'Email Campaigns',
                        'email-templates' => 'Templates',
                        'email-lists' => 'Email Audiences',
                        'webforms' => 'Web Forms',
                        'voip-dashboard' => 'VoIP Calls',
                        'whatsapp-dashboard' => 'WhatsApp',
                        'products' => 'Products',
                        'automation' => 'Automation',
                        'documents' => 'Knowledge Hub',
                    ];
                    // Parse current hidden tabs from settings (stored as comma-separated string)
                    $hiddenTabs = array_filter(array_map('trim', explode(',', $settings['hidden_tabs'] ?? '')));
                    ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:12px;">
                        <?php foreach ($toggleableTabs as $tabKey => $tabLabel): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;transition:all 0.15s;">
                            <input type="checkbox" name="hidden_tabs[]" value="<?php echo $tabKey; ?>"
                                <?php echo in_array($tabKey, $hiddenTabs) ? 'checked' : ''; ?>
                                style="width:18px;height:18px;cursor:pointer;">
                            <span style="font-size:14px;"><?php echo htmlspecialchars(__($tabLabel)); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:12px;color:var(--color-text-muted);margin-top:14px;">
                        Checked tabs will be hidden from the sidebar for all users in your company.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Submit Button Panel -->
        <div style="margin-top:24px;display:flex;justify-content:flex-end;" id="settingsSubmitBtn">
            <button type="submit" class="btn btn-primary btn-lg"><?php echo htmlspecialchars(__('Save Settings')); ?></button>
        </div>
    </form>

<script>
function switchTab(tabId) {
    // Update tab header links by data-tab attribute
    document.querySelectorAll('.tab-link').forEach(link => {
        if (link.getAttribute('data-tab') === tabId) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Switch panels by id
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    const target = document.getElementById('pane-' + tabId);
    if (target) target.classList.add('active');

    // Hide or show bottom submit button based on Custom Fields tab
    const submitBtn = document.getElementById('settingsSubmitBtn');
    if (tabId === 'custom_fields') {
        submitBtn.style.display = 'none';
        loadCustomFields();
    } else {
        submitBtn.style.display = 'flex';
    }
}

// Load custom fields via AJAX
function loadCustomFields() {
    const listBody = document.getElementById('customFieldsList');
    listBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">${__('Loading custom fields...')}</td></tr>`;
    
    fetch('/api/custom-fields.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                if (data.data.length === 0) {
                    listBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">${__('No custom fields created yet. Click "+ Add Field" to create one.')}</td></tr>`;
                    return;
                }
                
                listBody.innerHTML = data.data.map(field => `
                    <tr>
                        <td><strong>${escapeHtml(field.field_label)}</strong></td>
                        <td><code>${escapeHtml(field.field_name)}</code></td>
                        <td><span class="badge" style="background:#f3f4f6;color:#374151;">${escapeHtml(__(field.field_type))}</span></td>
                        <td>${field.is_required == 1 ? `<span style="color:#d97706;font-weight:600;">${__('Yes')}</span>` : __('No')}</td>
                        <td>${field.sort_order}</td>
                        <td>${field.is_active == 1 ? `<span class="badge badge-success">${__('Active')}</span>` : `<span class="badge badge-error">${__('Inactive')}</span>`}</td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="/pages/custom-field-new.php?entity=lead&id=${field.field_id}" class="btn btn-xs btn-outline">${__('Edit')}</a>
                                <button type="button" class="btn btn-xs btn-outline btn-error" onclick="deleteCustomField(${field.field_id}, '${escapeHtml(field.field_label)}')">${__('Delete')}</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                listBody.innerHTML = `<tr><td colspan="7" class="text-center text-error">${__('Failed to load')}: ${escapeHtml(data.message)}</td></tr>`;
            }
        })
        .catch(err => {
            listBody.innerHTML = `<tr><td colspan="7" class="text-center text-error">${__('An error occurred loading custom fields.')}</td></tr>`;
        });
}

// Dialog helper
function saveCustomField(e) {
    e.preventDefault();
    const id = document.getElementById('fieldId').value;
    const action = id ? 'update' : 'create';
    
    const bodyData = {
        field_label: document.getElementById('fieldLabel').value,
        field_name: document.getElementById('fieldName').value,
        field_type: document.getElementById('fieldType').value,
        is_required: parseInt(document.getElementById('fieldRequired').value),
        sort_order: parseInt(document.getElementById('fieldSort').value),
        csrf_token: '<?php echo $csrfToken; ?>'
    };
    
    if (id) {
        bodyData.field_id = parseInt(id);
        bodyData.is_active = 1;
    }
    
    fetch('/api/custom-fields.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(bodyData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeCustomFieldModal();
            loadCustomFields();
            showNotification(__('Custom field saved successfully!'), 'success');
        } else {
            alert(__('Failed to save field: ') + data.message);
        }
    })
    .catch(err => {
        alert(__('An error occurred while saving custom field.'));
    });
}

function deleteCustomField(id, label) {
    showConfirm(__('confirm_delete_custom_field', 'Are you sure you want to delete the custom field "{name}"? This will also remove any data stored in this field for your leads.').replace('{name}', label), () => {
        fetch('/api/custom-fields.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                field_id: id,
                csrf_token: '<?php echo $csrfToken; ?>'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadCustomFields();
                showNotification(__('Custom field deleted successfully!'), 'success');
            } else {
                alert(__('Failed to delete field: ') + data.message);
            }
        })
        .catch(err => {
            alert(__('An error occurred while deleting custom field.'));
        });
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
