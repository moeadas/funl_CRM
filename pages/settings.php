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
            // Theme / Color Scheme
            'theme_sidebar_bg', 'theme_bg', 'theme_surface', 'theme_border',
            'theme_accent', 'theme_accent_hover',
            'theme_text', 'theme_text_secondary',
            'theme_success', 'theme_warning', 'theme_danger', 'theme_info',
            // Fonts
            'theme_font_heading', 'theme_font_body', 'theme_font_mono',
            'theme_font_heading_ar', 'theme_font_body_ar'
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
                    <div class="form-grid-2">
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
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Color Scheme')); ?></h3></div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:20px;font-size:13px;"><?php echo htmlspecialchars(__('Customize the colors of your CRM. Changes apply instantly across the app.')); ?></p>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Sidebar Background')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_sidebar_bg" value="<?php echo htmlspecialchars($settings['theme_sidebar_bg'] ?? '#FDF8F1'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_sidebar_bg_text" value="<?php echo htmlspecialchars($settings['theme_sidebar_bg'] ?? '#FDF8F1'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Main Background')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_bg" value="<?php echo htmlspecialchars($settings['theme_bg'] ?? '#FBF3EA'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_bg_text" value="<?php echo htmlspecialchars($settings['theme_bg'] ?? '#FBF3EA'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Card/Surface Background')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_surface" value="<?php echo htmlspecialchars($settings['theme_surface'] ?? '#FDF8F1'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_surface_text" value="<?php echo htmlspecialchars($settings['theme_surface'] ?? '#FDF8F1'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Border Color')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_border" value="<?php echo htmlspecialchars($settings['theme_border'] ?? '#EBDFCE'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_border_text" value="<?php echo htmlspecialchars($settings['theme_border'] ?? '#EBDFCE'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Accent Colors')); ?></h4>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Primary Accent (Buttons, Links)')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_accent" value="<?php echo htmlspecialchars($settings['theme_accent'] ?? '#E89BB8'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_accent_text" value="<?php echo htmlspecialchars($settings['theme_accent'] ?? '#E89BB8'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Accent Hover Color')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_accent_hover" value="<?php echo htmlspecialchars($settings['theme_accent_hover'] ?? '#D2729A'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_accent_hover_text" value="<?php echo htmlspecialchars($settings['theme_accent_hover'] ?? '#D2729A'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Text Colors')); ?></h4>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Primary Text')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_text" value="<?php echo htmlspecialchars($settings['theme_text'] ?? '#1F1714'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_text_text" value="<?php echo htmlspecialchars($settings['theme_text'] ?? '#1F1714'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Secondary Text')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_text_secondary" value="<?php echo htmlspecialchars($settings['theme_text_secondary'] ?? '#6F5C54'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_text_secondary_text" value="<?php echo htmlspecialchars($settings['theme_text_secondary'] ?? '#6F5C54'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <h4 style="font-size:14px;font-weight:600;margin-bottom:16px;color:var(--color-text);"><?php echo htmlspecialchars(__('Status Colors')); ?></h4>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Success')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_success" value="<?php echo htmlspecialchars($settings['theme_success'] ?? '#5E8259'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_success_text" value="<?php echo htmlspecialchars($settings['theme_success'] ?? '#5E8259'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Warning')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_warning" value="<?php echo htmlspecialchars($settings['theme_warning'] ?? '#9A7A2C'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_warning_text" value="<?php echo htmlspecialchars($settings['theme_warning'] ?? '#9A7A2C'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Danger')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_danger" value="<?php echo htmlspecialchars($settings['theme_danger'] ?? '#C97A47'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_danger_text" value="<?php echo htmlspecialchars($settings['theme_danger'] ?? '#C97A47'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Info')); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="color" name="theme_info" value="<?php echo htmlspecialchars($settings['theme_info'] ?? '#4F7787'); ?>" class="color-picker" style="width:48px;height:40px;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;">
                            <input type="text" name="theme_info_text" value="<?php echo htmlspecialchars($settings['theme_info'] ?? '#4F7787'); ?>" class="form-control color-hex" style="max-width:120px;font-family:var(--font-mono,monospace);" oninput="this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <div style="display:flex;gap:12px;align-items:center;">
                    <button type="button" class="btn btn-outline" onclick="resetThemeDefaults()"><?php echo htmlspecialchars(__('Reset to Defaults')); ?></button>
                    <span class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars(__('Click Save Changes below to apply.')); ?></span>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Fonts')); ?></h3></div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:20px;font-size:13px;"><?php echo htmlspecialchars(__('Choose fonts for headings and body text. Supports English and Arabic Google Fonts.')); ?></p>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Heading / Title Font (English)')); ?></label>
                        <select name="theme_font_heading" class="form-control font-select">
                            <?php
                            $headingFonts = ['Plus Jakarta Sans','Roboto','Open Sans','Poppins','Inter','Montserrat','Nunito','DM Sans','Manrope','Sora','Outfit','Space Grotesk','Fraunces','Playfair Display','Lora','Merriweather','PT Serif','Crimson Pro','Libre Baskerville','Source Serif Pro'];
                            $currentHeadingFont = $settings['theme_font_heading'] ?? 'Plus Jakarta Sans';
                            foreach ($headingFonts as $f) {
                                $sel = ($f === $currentHeadingFont) ? ' selected' : '';
                                echo "\u003coption value=\"" . htmlspecialchars($f) . "\"" . $sel . ">" . htmlspecialchars($f) . "\u003c/option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Body / Text Font (English)')); ?></label>
                        <select name="theme_font_body" class="form-control font-select">
                            <?php
                            $bodyFonts = ['Plus Jakarta Sans','Open Sans','Inter','Roboto','DM Sans','Manrope','Nunito','Poppins','Montserrat','Outfit','Space Grotesk','Sora','Source Sans Pro','PT Sans','Fira Sans','Work Sans','Karla','Mulish','Hind','Asap'];
                            $currentBodyFont = $settings['theme_font_body'] ?? 'Plus Jakarta Sans';
                            foreach ($bodyFonts as $f) {
                                $sel = ($f === $currentBodyFont) ? ' selected' : '';
                                echo "\u003coption value=\"" . htmlspecialchars($f) . "\"" . $sel . ">" . htmlspecialchars($f) . "\u003c/option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Heading / Title Font (Arabic)')); ?></label>
                        <select name="theme_font_heading_ar" class="form-control font-select">
                            <?php
                            $arabicHeadingFonts = ['Default (follows English)','Cairo','Tajawal','Almarai','IBM Plex Sans Arabic','Noto Sans Arabic','Readex Pro','El Messiri','Lateef','Amiri','Reem Kufi','Scheherazade New','Markazi Text','Vazirmatn'];
                            $currentArHeadingFont = $settings['theme_font_heading_ar'] ?? 'Default (follows English)';
                            foreach ($arabicHeadingFonts as $f) {
                                $sel = ($f === $currentArHeadingFont) ? ' selected' : '';
                                echo "\u003coption value=\"" . htmlspecialchars($f) . "\"" . $sel . ">" . htmlspecialchars($f) . "\u003c/option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo htmlspecialchars(__('Body / Text Font (Arabic)')); ?></label>
                        <select name="theme_font_body_ar" class="form-control font-select">
                            <?php
                            $arabicBodyFonts = ['Default (follows English)','Cairo','Tajawal','Almarai','IBM Plex Sans Arabic','Noto Sans Arabic','Readex Pro','El Messiri','Lateef','Amiri','Reem Kufi','Scheherazade New','Markazi Text','Vazirmatn'];
                            $currentArBodyFont = $settings['theme_font_body_ar'] ?? 'Default (follows English)';
                            foreach ($arabicBodyFonts as $f) {
                                $sel = ($f === $currentArBodyFont) ? ' selected' : '';
                                echo "\u003coption value=\"" . htmlspecialchars($f) . "\"" . $sel . ">" . htmlspecialchars($f) . "\u003c/option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid var(--color-border);margin:24px 0;">

                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars__('Monospace / Label Font'); ?></label>
                    <select name="theme_font_mono" class="form-control font-select">
                        <?php
                        $monoFonts = ['JetBrains Mono','Fira Code','Source Code Pro','IBM Plex Mono','Roboto Mono','Space Mono','Ubuntu Mono','Cascadia Code','Inconsolata','PT Mono'];
                        $currentMonoFont = $settings['theme_font_mono'] ?? 'JetBrains Mono';
                        foreach ($monoFonts as $f) {
                            $sel = ($f === $currentMonoFont) ? ' selected' : '';
                            echo "\u003coption value=\"" . htmlspecialchars($f) . "\"" . $sel . ">" . htmlspecialchars($f) . "\u003c/option>";
                        }
                        ?>
                    </select>
                    <p class="text-muted" style="font-size:11px;margin-top:4px;"><?php echo htmlspecialchars__('Used for badges, labels, numbers, and code.'); ?></p>
                </div>
            </div>
        </div>

        <script>
        function resetThemeDefaults() {
            var defaults = {
                theme_sidebar_bg: '#FDF8F1', theme_bg: '#FBF3EA', theme_surface: '#FDF8F1',
                theme_border: '#EBDFCE', theme_accent: '#E89BB8', theme_accent_hover: '#D2729A',
                theme_text: '#1F1714', theme_text_secondary: '#6F5C54',
                theme_success: '#5E8259', theme_warning: '#9A7A2C', theme_danger: '#C97A47', theme_info: '#4F7787'
            };
            Object.keys(defaults).forEach(function(key) {
                var el = document.querySelector('[name="' + key + '"]');
                if (el) { el.value = defaults[key]; var txt = document.querySelector('[name="' + key + '_text"]'); if (txt) txt.value = defaults[key]; }
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

                    <div class="form-grid-2">
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
                    <div class="form-grid-2">
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
                    <div class="form-grid-2">
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
                        <div class="form-grid-2">
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
