<?php
/**
 * White Label CRM - Settings Page
 * Premium design, tabbed layout, logo/favicon uploads, twilio/smtp setup, and custom fields CRUD
 */
require_once __DIR__ . '/../includes/auth.php';
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
            'timezone', 'date_format', 'records_per_page',
            'voip_enabled', 'voip_recording_enabled', 'twilio_account_sid', 'twilio_auth_token', 'twilio_phone_number', 'twilio_twiml_app_sid',
            'whatsapp_enabled', 'whatsapp_from_number', 'whatsapp_sandbox_mode',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
            'email_from_name', 'email_from_address', 'email_reply_to', 'email_batch_size', 'email_batch_delay',
            'tracking_head_code', 'tracking_body_code', 'preloader_code'
        ];

        $pdo->beginTransaction();
        try {
            foreach ($keysToSave as $key) {
                $value = $_POST[$key] ?? '';
                
                // Convert toggles to 1/0
                if (in_array($key, ['voip_enabled', 'voip_recording_enabled', 'whatsapp_enabled', 'whatsapp_sandbox_mode'])) {
                    $value = ($value === 'on' || $value === '1') ? '1' : '0';
                }

                // H-4 fix: encrypt sensitive fields on write. If the field is empty
                // (user didn't change it), preserve the existing value.
                if ($key === 'smtp_password' && $value === '') {
                    continue; // don't overwrite the existing password
                }
                if (in_array($key, ['smtp_password', 'twilio_auth_token'])) {
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



<div class="settings-container">
    <div class="page-header">
        <h1 class="page-title"><?php echo htmlspecialchars(__('Settings')); ?></h1>
    </div>
    <p class="settings-subtitle"><?php echo htmlspecialchars(__('Configure application settings, custom fields, VoIP integrations, and SMTP credentials')); ?></p>
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
        <div class="tab-link" data-tab="voip" onclick="switchTab('voip')"><?php echo htmlspecialchars(__('VoIP & WhatsApp')); ?></div>
        <div class="tab-link" data-tab="smtp" onclick="switchTab('smtp')"><?php echo htmlspecialchars(__('SMTP & Email')); ?></div>
        <div class="tab-link" data-tab="tracking" onclick="switchTab('tracking')"><?php echo htmlspecialchars(__('Pixels & Tracking')); ?></div>
        <div class="tab-link" data-tab="custom_fields" onclick="switchTab('custom_fields')"><?php echo htmlspecialchars(__('Custom Lead Fields')); ?></div>
        <div class="tab-link" data-tab="subscription" onclick="switchTab('subscription')"><?php echo htmlspecialchars(__('Subscription')); ?></div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="settingsForm">
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
                            <label class="form-label"><?php echo htmlspecialchars(__('Support Email *')); ?></label>
                            <input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Support Phone')); ?></label>
                            <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
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
                            <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" placeholder="587">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Username')); ?></label>
                            <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="postmaster@mg.funl.online">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Password')); ?></label>
                            <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="••••••••••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('SMTP Encryption')); ?></label>
                            <select name="smtp_encryption" class="form-control">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('TLS (Recommended)')); ?></option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('SSL')); ?></option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('None')); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Default From Name')); ?></label>
                            <input type="text" name="email_from_name" class="form-control" value="<?php echo htmlspecialchars($settings['email_from_name'] ?? ''); ?>" placeholder="FunL Team">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Default From Email')); ?></label>
                            <input type="email" name="email_from_address" class="form-control" value="<?php echo htmlspecialchars($settings['email_from_address'] ?? ''); ?>" placeholder="info@funl.online">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Default Reply-To Email')); ?></label>
                            <input type="email" name="email_reply_to" class="form-control" value="<?php echo htmlspecialchars($settings['email_reply_to'] ?? ''); ?>" placeholder="info@funl.online">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Marketing Batch Size')); ?></label>
                            <input type="number" name="email_batch_size" class="form-control" value="<?php echo htmlspecialchars($settings['email_batch_size'] ?? '50'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars(__('Delay Between Batches (seconds)')); ?></label>
                            <input type="number" name="email_batch_delay" class="form-control" value="<?php echo htmlspecialchars($settings['email_batch_delay'] ?? '2'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Pixels & Tracking -->
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
        
        <!-- Submit Button Panel -->
        <div style="margin-top:24px;display:flex;justify-content:flex-end;" id="settingsSubmitBtn">
            <button type="submit" class="btn btn-primary btn-lg"><?php echo htmlspecialchars(__('Save Settings')); ?></button>
        </div>
    </form>
</div>

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
