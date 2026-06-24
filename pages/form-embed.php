<?php
/**
 * Web Form Embed Page
 * 
 * Embedded version of web forms for external websites.
 * Design principles:
 *   - Background is ALWAYS transparent so it blends into the host website
 *   - Modern Google-style form design (clean, minimal, floating labels)
 *   - No "Powered by" branding — forms look native to the host site
 *   - Responsive and accessible
 * 
 * @author Izzy (AI Assistant)
 * @lastupdated 2026-06-24
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$formId = intval($_GET['id'] ?? 0);

if (empty($formId)) {
    echo '<div style="padding:40px;text-align:center;font-family:system-ui,sans-serif;color:#6b7280;">Form ID is missing.</div>';
    exit;
}

$form = $db->query("SELECT * FROM webforms WHERE form_id = ? AND status = 'active'", [$formId])->fetch();
if (!$form) {
    echo '<div style="padding:40px;text-align:center;font-family:system-ui,sans-serif;color:#6b7280;">This form is no longer available.</div>';
    exit;
}

// Fetch form fields ordered by position
$fields = $db->query("SELECT * FROM webform_fields WHERE form_id = ? ORDER BY position ASC", [$formId])->fetchAll();

// Read color customization from form settings (with safe defaults)
$primaryColor = $form['primary_color'] ?? '#2563eb';
$borderRadius = intval($form['border_radius'] ?? 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['form_name'] ?? 'Form'); ?></title>
    <style>
        /* ─── Embed Form Styles — Google-inspired, transparent background ─── */
        /* The body and container are transparent so the form inherits the
           host website's background color and blends in seamlessly. */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        html, body {
            background: transparent !important;
            font-family: 'Google Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1f2937;
            line-height: 1.5;
            padding: 0;
            margin: 0;
        }
        
        /* Form container — no background, no border, no shadow.
           Let the host website's container provide the styling. */
        .form-container {
            max-width: 480px;
            margin: 0 auto;
            background: transparent;
            padding: 0;
        }
        
        .form-title {
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #1f2937;
            letter-spacing: -0.01em;
        }
        
        .form-description {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        
        /* Form group — Google-style floating label inputs */
        .form-group {
            margin-bottom: 18px;
            position: relative;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            color: #374151;
        }
        
        .form-label .required {
            color: #dc2626;
            margin-left: 2px;
        }
        
        /* Input fields — Google Material style */
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: <?php echo $borderRadius ?>px;
            font-size: 15px;
            font-family: inherit;
            color: #1f2937;
            background: rgba(255, 255, 255, 0.9);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            -webkit-appearance: none;
            appearance: none;
        }
        
        .form-control:hover {
            border-color: #9ca3af;
        }
        
        .form-control:focus {
            outline: none;
            border-color: <?php echo $primaryColor ?>;
            box-shadow: 0 0 0 3px <?php echo $primaryColor ?>1a;
            background: #ffffff;
        }
        
        /* Placeholder style */
        .form-control::placeholder {
            color: #9ca3af;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
            line-height: 1.5;
        }
        
        select.form-control {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }
        
        /* Submit button — Google-style filled button */
        .btn-submit {
            width: 100%;
            padding: 12px 20px;
            background: <?php echo $primaryColor ?>;
            color: #ffffff;
            border: none;
            border-radius: <?php echo $borderRadius ?>px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            font-family: inherit;
            letter-spacing: 0.01em;
        }
        
        .btn-submit:hover {
            background: <?php echo $primaryColor ?>dd;
        }
        
        .btn-submit:active {
            transform: scale(0.98);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Success message */
        .success-message {
            text-align: center;
            padding: 48px 20px;
        }
        
        .success-message .check-circle {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: #16a34a15;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .success-message .check-circle svg {
            width: 28px;
            height: 28px;
            color: #16a34a;
        }
        
        .success-message h2 {
            font-size: 20px;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .success-message p {
            color: #6b7280;
            font-size: 14px;
        }
        
        /* Error message */
        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 14px;
            border-radius: <?php echo $borderRadius ?>px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
            border: 1px solid #fecaca;
        }
        
        .error-message.show {
            display: block;
        }
        
        /* No powered-by branding — the form looks native to the host website */
    </style>
</head>
<body>
    <div class="form-container" id="form-container">
        <?php if (!empty($form['form_name'])): ?>
            <h1 class="form-title"><?php echo htmlspecialchars($form['form_name']); ?></h1>
        <?php endif; ?>
        <?php if (!empty($form['description'])): ?>
            <p class="form-description"><?php echo nl2br(htmlspecialchars($form['description'])); ?></p>
        <?php endif; ?>
        
        <div class="error-message" id="error-message"></div>
        
        <!-- Hidden UTM fields — auto-populated from host page URL -->
        <input type="hidden" name="utm_source" id="utm_source" value="">
        <input type="hidden" name="utm_campaign" id="utm_campaign" value="">
        <input type="hidden" name="utm_medium" id="utm_medium" value="">
        <input type="hidden" name="utm_content" id="utm_content" value="">
        <input type="hidden" name="utm_term" id="utm_term" value="">
        <input type="hidden" name="landing_page" id="landing_page" value="">
        <input type="hidden" name="referrer" id="referrer" value="">
        
        <!-- Honeypot field — bots fill it, real users don't see it -->
        <input type="text" name="website_url" id="website_url" value="" 
               style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;" 
               tabindex="-1" autocomplete="off" aria-hidden="true">
        
        <form id="lead-form" onsubmit="submitForm(event)">
            <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label class="form-label" for="field_<?php echo htmlspecialchars($field['crm_field']); ?>">
                        <?php echo htmlspecialchars($field['field_label']); ?>
                        <?php if (!empty($field['required'])): ?><span class="required">*</span><?php endif; ?>
                    </label>
                    <?php if ($field['field_type'] === 'textarea'): ?>
                        <textarea name="<?php echo htmlspecialchars($field['crm_field']); ?>" 
                                  id="field_<?php echo htmlspecialchars($field['crm_field']); ?>"
                                  class="form-control"
                                  <?php if (!empty($field['required'])) echo 'required'; ?>
                                  placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? 'Enter ' . strtolower($field['field_label'])); ?>"
                        ></textarea>
                    <?php elseif ($field['field_type'] === 'select'): ?>
                        <select name="<?php echo htmlspecialchars($field['crm_field']); ?>"
                                id="field_<?php echo htmlspecialchars($field['crm_field']); ?>"
                                class="form-control"
                                <?php if (!empty($field['required'])) echo 'required'; ?>>
                            <option value=""><?php echo htmlspecialchars($field['placeholder'] ?? 'Select...'); ?></option>
                        </select>
                    <?php else: ?>
                        <input type="<?php echo htmlspecialchars($field['field_type'] ?? 'text'); ?>"
                               name="<?php echo htmlspecialchars($field['crm_field']); ?>"
                               id="field_<?php echo htmlspecialchars($field['crm_field']); ?>"
                               class="form-control"
                               <?php if (!empty($field['required'])) echo 'required'; ?>
                               placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? 'Enter ' . strtolower($field['field_label'])); ?>"
                        >
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn-submit" id="submit-btn">
                <?php echo htmlspecialchars($form['submit_button_text'] ?? 'Submit'); ?>
            </button>
        </form>
    </div>
    
    <script>
        const FORM_ID = <?php echo json_encode($formId); ?>;
        const API_URL = '/api/form-submit.php';
        
        // ─── UTM Auto-Capture ───────────────────────────────────────────
        // Reads UTM params from the host page URL (via window.top) or
        // from localStorage/sessionStorage if the funl_utm.js snippet is used.
        (function captureUtm() {
            var urlObj = null;
            try { urlObj = new URL(window.top.location.href); } catch (e) { /* cross-origin iframe */ }
            if (!urlObj) {
                try { urlObj = new URL(window.location.href); } catch (e) { return; }
            }
            var params = urlObj.searchParams;
            var keys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term'];
            keys.forEach(function(k) {
                var fromUrl = params.get(k);
                var fromStorage = null;
                try {
                    fromStorage = localStorage.getItem('funl_' + k) || sessionStorage.getItem('funl_' + k);
                } catch (e) { /* storage may be blocked */ }
                var value = fromUrl || fromStorage || '';
                var el = document.getElementById(k);
                if (el) el.value = value;
            });
            var landingEl = document.getElementById('landing_page');
            if (landingEl) landingEl.value = urlObj.href;
            var refEl = document.getElementById('referrer');
            if (refEl) {
                try { refEl.value = document.referrer || (window.top !== window ? window.top.document.referrer : '') || ''; } catch (e) { refEl.value = document.referrer || ''; }
            }
        })();
        
        // ─── Form Submission ────────────────────────────────────────────
        function submitForm(e) {
            e.preventDefault();
            var btn = document.getElementById('submit-btn');
            var errorDiv = document.getElementById('error-message');
            errorDiv.classList.remove('show');
            errorDiv.textContent = '';
            
            btn.disabled = true;
            btn.textContent = 'Submitting...';
            
            var formData = new FormData(e.target);
            var data = {};
            formData.forEach(function(value, key) { data[key] = value; });
            
            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    form_id: FORM_ID,
                    data: data,
                    utm_source: data.utm_source,
                    utm_campaign: data.utm_campaign,
                    utm_medium: data.utm_medium,
                    utm_content: data.utm_content,
                    utm_term: data.utm_term,
                    landing_page: data.landing_page,
                    referrer: data.referrer,
                    lead_source: data.utm_source ? (data.utm_source + (data.utm_campaign ? ' (' + data.utm_campaign + ')' : '')) : 'Website'
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    document.getElementById('form-container').innerHTML =
                        '<div class="success-message">' +
                            '<div class="check-circle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>' +
                            '<h2>Thank You!</h2>' +
                            '<p>' + (resp.message || 'Your submission has been received.') + '</p>' +
                        '</div>';
                } else {
                    errorDiv.textContent = resp.message || 'Something went wrong. Please try again.';
                    errorDiv.classList.add('show');
                    btn.disabled = false;
                    btn.textContent = '<?php echo htmlspecialchars($form['submit_button_text'] ?? 'Submit'); ?>';
                }
            })
            .catch(function() {
                errorDiv.textContent = 'Network error. Please check your connection and try again.';
                errorDiv.classList.add('show');
                btn.disabled = false;
                btn.textContent = '<?php echo htmlspecialchars($form['submit_button_text'] ?? 'Submit'); ?>';
            });
        }
    </script>
</body>
</html>