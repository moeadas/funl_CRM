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
            'email_from_name', 'email_from_address', 'email_reply_to', 'email_batch_size', 'email_batch_delay'
        ];

        $pdo->beginTransaction();
        try {
            foreach ($keysToSave as $key) {
                $value = $_POST[$key] ?? '';
                
                // Convert toggles to 1/0
                if (in_array($key, ['voip_enabled', 'voip_recording_enabled', 'whatsapp_enabled', 'whatsapp_sandbox_mode'])) {
                    $value = ($value === 'on' || $value === '1') ? '1' : '0';
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

<style>
.settings-container { max-width: 1100px; margin: 0 auto; padding: 0 20px 40px; }
.tabs-nav { display: flex; border-bottom: 1.5px solid #e5e7eb; gap: 24px; margin-bottom: 24px; }
.tab-link { padding: 12px 4px; font-size: 14px; font-weight: 500; color: #6b7280; text-decoration: none; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.15s; }
.tab-link:hover { color: #1f2937; }
.tab-link.active { color: #D91C48; border-bottom-color: #D91C48; font-weight: 600; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .form-grid-2 { grid-template-columns: 1fr; } }
.branding-preview { display: flex; align-items: center; gap: 16px; margin-top: 12px; }
.branding-preview img { max-height: 48px; border: 1.5px dashed #d1d5db; border-radius: 8px; padding: 6px; background: #f9fafb; }
.switch-container { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 16px; }
.switch-info h4 { margin: 0 0 2px; font-size: 14px; font-weight: 600; color: #1f2937; }
.switch-info p { margin: 0; font-size: 12px; color: #6b7280; }
</style>

<div class="settings-container">
    <div class="page-header">
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Configure application settings, custom fields, VoIP integrations, and SMTP credentials</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="tabs-nav">
        <div class="tab-link active" onclick="switchTab('general')">Company Profile</div>
        <div class="tab-link" onclick="switchTab('branding')">App Branding</div>
        <div class="tab-link" onclick="switchTab('voip')">VoIP &amp; WhatsApp</div>
        <div class="tab-link" onclick="switchTab('smtp')">SMTP &amp; Email</div>
        <div class="tab-link" onclick="switchTab('custom_fields')">Custom Lead Fields</div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="action" value="save_settings">

        <!-- Tab: General -->
        <div class="tab-pane active" id="pane-general">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Company Profile Details</h3></div>
                <div class="card-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Company Name *</label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">App Branding Title</label>
                            <input type="text" name="app_name" class="form-control" value="<?php echo htmlspecialchars($settings['app_name'] ?? 'White Label CRM'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Support Email *</label>
                            <input type="email" name="company_email" class="form-control" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Support Phone</label>
                            <input type="text" name="company_phone" class="form-control" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Website</label>
                            <input type="url" name="company_website" class="form-control" value="<?php echo htmlspecialchars($settings['company_website'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Default Records per Page</label>
                            <input type="number" name="records_per_page" class="form-control" value="<?php echo htmlspecialchars($settings['records_per_page'] ?? '25'); ?>" min="5" max="100">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-control">
                                <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (New York)</option>
                                <option value="America/Chicago" <?php echo ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time (Chicago)</option>
                                <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (Los Angeles)</option>
                                <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>London (GMT/BST)</option>
                                <option value="Europe/Paris" <?php echo ($settings['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date Format</label>
                            <select name="date_format" class="form-control">
                                <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2026-05-31)</option>
                                <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (05/31/2026)</option>
                                <option value="d-M-Y" <?php echo ($settings['date_format'] ?? '') === 'd-M-Y' ? 'selected' : ''; ?>>DD-Mon-YYYY (31-May-2026)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:16px;">
                        <label class="form-label">Company Address</label>
                        <textarea name="company_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Branding -->
        <div class="tab-pane" id="pane-branding">
            <div class="card">
                <div class="card-header"><h3 class="card-title">App Logos &amp; Asset Customization</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Company Logo (Recommended: SVG or PNG, Transparent, max 200x60px)</label>
                        <input type="file" name="company_logo" class="form-control" accept=".png,.jpg,.jpeg,.svg">
                        <div class="branding-preview">
                            <span class="text-muted" style="font-size:12px;">Current logo:</span>
                            <img src="<?php echo getCompanyLogo(); ?>?v=<?php echo time(); ?>" alt="Logo">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:24px;">
                        <label class="form-label">App Favicon (Recommended: 32x32px ICO, PNG, or SVG)</label>
                        <input type="file" name="company_favicon" class="form-control" accept=".ico,.png,.svg">
                        <div class="branding-preview">
                            <span class="text-muted" style="font-size:12px;">Current favicon:</span>
                            <img src="<?php echo getCompanyFavicon(); ?>?v=<?php echo time(); ?>" alt="Favicon" style="max-height:32px;">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: VoIP -->
        <div class="tab-pane" id="pane-voip">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Twilio Integration settings</h3></div>
                <div class="card-body">
                    <div class="switch-container">
                        <div class="switch-info">
                            <h4>Enable Twilio VoIP Calling</h4>
                            <p>Enable client calling and receiving directly inside the browser</p>
                        </div>
                        <input type="checkbox" name="voip_enabled" value="1" <?php echo ($settings['voip_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="switch-container">
                        <div class="switch-info">
                            <h4>Enable Call Recording</h4>
                            <p>Automatically record incoming/outgoing client VoIP calls</p>
                        </div>
                        <input type="checkbox" name="voip_recording_enabled" value="1" <?php echo ($settings['voip_recording_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Twilio Account SID</label>
                            <input type="text" name="twilio_account_sid" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_account_sid'] ?? ''); ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Twilio Auth Token</label>
                            <input type="password" name="twilio_auth_token" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_auth_token'] ?? ''); ?>" placeholder="••••••••••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Twilio VoIP Phone Number</label>
                            <input type="text" name="twilio_phone_number" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_phone_number'] ?? ''); ?>" placeholder="+1234567890">
                        </div>
                        <div class="form-group">
                            <label class="form-label">TwiML App SID</label>
                            <input type="text" name="twilio_twiml_app_sid" class="form-control" value="<?php echo htmlspecialchars($settings['twilio_twiml_app_sid'] ?? ''); ?>" placeholder="APxxxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                    </div>

                    <h3 class="card-title" style="margin-top:32px;margin-bottom:16px;">WhatsApp Integration</h3>
                    <div class="switch-container">
                        <div class="switch-info">
                            <h4>Enable WhatsApp Integration</h4>
                            <p>Send and receive messages directly inside the WhatsApp Dashboard</p>
                        </div>
                        <input type="checkbox" name="whatsapp_enabled" value="1" <?php echo ($settings['whatsapp_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="switch-container">
                        <div class="switch-info">
                            <h4>Sandbox Mode</h4>
                            <p>Use Twilio WhatsApp Sandbox mode (+1 415 523 8886) for testing</p>
                        </div>
                        <input type="checkbox" name="whatsapp_sandbox_mode" value="1" <?php echo ($settings['whatsapp_sandbox_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label">WhatsApp Sender Number</label>
                        <input type="text" name="whatsapp_from_number" class="form-control" value="<?php echo htmlspecialchars($settings['whatsapp_from_number'] ?? ''); ?>" placeholder="+1234567890">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: SMTP -->
        <div class="tab-pane" id="pane-smtp">
            <div class="card">
                <div class="card-header"><h3 class="card-title">SMTP Server &amp; Email Marketing Settings</h3></div>
                <div class="card-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.mailgun.org">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" placeholder="587">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="postmaster@mg.funl.online">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="••••••••••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label">SMTP Encryption</label>
                            <select name="smtp_encryption" class="form-control">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Default From Name</label>
                            <input type="text" name="email_from_name" class="form-control" value="<?php echo htmlspecialchars($settings['email_from_name'] ?? ''); ?>" placeholder="FunL Team">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Default From Email</label>
                            <input type="email" name="email_from_address" class="form-control" value="<?php echo htmlspecialchars($settings['email_from_address'] ?? ''); ?>" placeholder="info@funl.online">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Default Reply-To Email</label>
                            <input type="email" name="email_reply_to" class="form-control" value="<?php echo htmlspecialchars($settings['email_reply_to'] ?? ''); ?>" placeholder="info@funl.online">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Marketing Batch Size</label>
                            <input type="number" name="email_batch_size" class="form-control" value="<?php echo htmlspecialchars($settings['email_batch_size'] ?? '50'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Delay Between Batches (seconds)</label>
                            <input type="number" name="email_batch_delay" class="form-control" value="<?php echo htmlspecialchars($settings['email_batch_delay'] ?? '2'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Fields (Handled dynamically) -->
        <div class="tab-pane" id="pane-custom_fields">
            <div class="card">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <h3 class="card-title">Lead Custom Fields</h3>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openCustomFieldModal()">+ Add Field</button>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Field Label</th>
                                    <th>Code Name</th>
                                    <th>Type</th>
                                    <th>Required</th>
                                    <th>Sort Order</th>
                                    <th>Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="customFieldsList">
                                <tr><td colspan="7" class="text-center text-muted">Loading custom fields...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button Panel -->
        <div style="margin-top:24px;display:flex;justify-content:flex-end;" id="settingsSubmitBtn">
            <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
        </div>
    </form>
</div>

<!-- Custom Field Modal Dialog -->
<div class="modal-overlay" id="fieldModalOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000;opacity:0;transition:opacity 0.2s ease;">
    <div class="modal" style="background:white;padding:28px;border-radius:16px;max-width:480px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,0.15);transform:scale(0.95);transition:transform 0.2s ease;">
        <h3 style="font-size:18px;font-weight:700;margin-bottom:20px;color:#1f2937;" id="fieldModalTitle">Add Custom Field</h3>
        <form id="customFieldForm" onsubmit="saveCustomField(event)">
            <input type="hidden" id="fieldId" name="field_id">
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">Field Label *</label>
                <input type="text" id="fieldLabel" class="form-control" placeholder="e.g. Horse Age" required oninput="generateFieldName(this.value)">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">Field Code Name * (Alphanumeric/Underscore)</label>
                <input type="text" id="fieldName" class="form-control" placeholder="e.g. horse_age" required pattern="^[a-zA-Z0-9_]+$">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">Field Type</label>
                <select id="fieldType" class="form-control">
                    <option value="text">Text Input</option>
                    <option value="textarea">Textarea (Multiline)</option>
                    <option value="number">Number</option>
                    <option value="select">Dropdown List</option>
                    <option value="date">Date</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">Required Field</label>
                <select id="fieldRequired" class="form-control">
                    <option value="0">Optional</option>
                    <option value="1">Required</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label">Sort Order (Lower shows first)</label>
                <input type="number" id="fieldSort" class="form-control" value="0">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" class="btn btn-outline" onclick="closeCustomFieldModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Field</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Update tab header links
    document.querySelectorAll('.tab-link').forEach(link => {
        link.classList.remove('active');
        if (link.textContent.toLowerCase().includes(tabId.replace('_', ' ')) || (tabId === 'general' && link.textContent === 'Company Profile')) {
            link.classList.add('active');
        }
    });

    // Switch panels
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    document.getElementById('pane-' + tabId).classList.add('active');

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
    listBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading custom fields...</td></tr>';
    
    fetch('/api/custom-fields.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                if (data.data.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No custom fields created yet. Click "+ Add Field" to create one.</td></tr>';
                    return;
                }
                
                listBody.innerHTML = data.data.map(field => `
                    <tr>
                        <td><strong>${escapeHtml(field.field_label)}</strong></td>
                        <td><code>${escapeHtml(field.field_name)}</code></td>
                        <td><span class="badge" style="background:#f3f4f6;color:#374151;">${escapeHtml(field.field_type)}</span></td>
                        <td>${field.is_required == 1 ? '<span style="color:#d97706;font-weight:600;">Yes</span>' : 'No'}</td>
                        <td>${field.sort_order}</td>
                        <td>${field.is_active == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-error">Inactive</span>'}</td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button type="button" class="btn btn-xs btn-outline" onclick="editCustomField(${JSON.stringify(field).replace(/"/g, '&quot;')})">Edit</button>
                                <button type="button" class="btn btn-xs btn-outline btn-error" onclick="deleteCustomField(${field.field_id}, '${escapeHtml(field.field_label)}')">Delete</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } else {
                listBody.innerHTML = `<tr><td colspan="7" class="text-center text-error">Failed to load: ${escapeHtml(data.message)}</td></tr>`;
            }
        })
        .catch(err => {
            listBody.innerHTML = '<tr><td colspan="7" class="text-center text-error">An error occurred loading custom fields.</td></tr>';
        });
}

// Dialog helper
const overlay = document.getElementById('fieldModalOverlay');
const modal = overlay.querySelector('.modal');

function openCustomFieldModal() {
    document.getElementById('fieldModalTitle').textContent = 'Add Custom Field';
    document.getElementById('fieldId').value = '';
    document.getElementById('fieldLabel').value = '';
    document.getElementById('fieldName').value = '';
    document.getElementById('fieldName').disabled = false;
    document.getElementById('fieldType').value = 'text';
    document.getElementById('fieldRequired').value = '0';
    document.getElementById('fieldSort').value = '0';
    
    overlay.style.display = 'flex';
    setTimeout(() => {
        overlay.style.opacity = '1';
        modal.style.transform = 'scale(1)';
    }, 10);
}

function closeCustomFieldModal() {
    overlay.style.opacity = '0';
    modal.style.transform = 'scale(0.95)';
    setTimeout(() => {
        overlay.style.display = 'none';
    }, 200);
}

function generateFieldName(val) {
    if (document.getElementById('fieldId').value !== '') return; // Don't auto-modify on edit
    const slug = val.toLowerCase()
        .replace(/[^a-z0-9\s]/g, '') // remove special
        .replace(/\s+/g, '_') // spaces to underscores
        .substring(0, 50);
    document.getElementById('fieldName').value = slug;
}

function editCustomField(field) {
    document.getElementById('fieldModalTitle').textContent = 'Edit Custom Field';
    document.getElementById('fieldId').value = field.field_id;
    document.getElementById('fieldLabel').value = field.field_label;
    document.getElementById('fieldName').value = field.field_name;
    document.getElementById('fieldName').disabled = true; // Code name is key, don't edit
    document.getElementById('fieldType').value = field.field_type;
    document.getElementById('fieldRequired').value = field.is_required;
    document.getElementById('fieldSort').value = field.sort_order;
    
    overlay.style.display = 'flex';
    setTimeout(() => {
        overlay.style.opacity = '1';
        modal.style.transform = 'scale(1)';
    }, 10);
}

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
            showNotification('Custom field saved successfully!', 'success');
        } else {
            alert('Failed to save field: ' + data.message);
        }
    })
    .catch(err => {
        alert('An error occurred while saving custom field.');
    });
}

function deleteCustomField(id, label) {
    showConfirm(`Are you sure you want to delete the custom field "${label}"? This will also remove any data stored in this field for your leads.`, () => {
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
                showNotification('Custom field deleted successfully!', 'success');
            } else {
                alert('Failed to delete field: ' + data.message);
            }
        })
        .catch(err => {
            alert('An error occurred while deleting custom field.');
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
