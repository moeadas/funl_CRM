<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    echo '<div style="padding:40px;text-align:center;font-family:system-ui">Form not found.</div>';
    exit;
}

$form = $db->query("SELECT * FROM web_forms WHERE form_slug = ? AND is_active = 1", [$slug])->fetch();
if (!$form) {
    echo '<div style="padding:40px;text-align:center;font-family:system-ui">This form is not available.</div>';
    exit;
}

$fields = json_decode($form['fields_config'] ?? '[]', true);
$style = json_decode($form['styling'] ?? '{}', true);
$primaryColor = $style['primary_color'] ?? '#2563eb';
$bgColor = $style['bg_color'] ?? '#ffffff';
$textColor = $style['text_color'] ?? '#1f2937';
$borderRadius = $style['border_radius'] ?? '8';
$fontFamily = $style['font_family'] ?? 'system-ui, -apple-system, sans-serif';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['title'] ?? 'Contact Form') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: <?= $fontFamily ?>;
            background: <?= $bgColor ?>;
            color: <?= $textColor ?>;
            line-height: 1.5;
            padding: 24px;
        }
        .form-container { max-width: 480px; margin: 0 auto; }
        .form-title { font-size: 22px; font-weight: 600; margin-bottom: 8px; }
        .form-description { font-size: 14px; color: #6b7280; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
        .form-label .required { color: #dc2626; margin-left: 2px; }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db;
            border-radius: <?= $borderRadius ?>px; font-size: 14px;
            font-family: inherit; color: <?= $textColor ?>; background: white;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus {
            outline: none; border-color: <?= $primaryColor ?>;
            box-shadow: 0 0 0 3px <?= $primaryColor ?>20;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px;
        }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .btn-submit {
            width: 100%; padding: 12px; background: <?= $primaryColor ?>;
            color: white; border: none; border-radius: <?= $borderRadius ?>px;
            font-size: 15px; font-weight: 600; cursor: pointer; transition: opacity 0.15s;
        }
        .btn-submit:hover { opacity: 0.9; }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        .success-message { text-align: center; padding: 40px 20px; }
        .success-message h2 { font-size: 20px; color: #16a34a; margin-bottom: 12px; }
        .success-message p { color: #6b7280; font-size: 14px; }
        .error-message {
            background: #fef2f2; color: #dc2626; padding: 10px 12px;
            border-radius: 6px; font-size: 13px; margin-bottom: 16px; display: none;
        }
        .error-message.show { display: block; }
        .powered-by { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="form-container" id="form-container">
        <?php if ($form['title']): ?>
            <h1 class="form-title"><?= htmlspecialchars($form['title']) ?></h1>
        <?php endif; ?>
        <?php if ($form['description']): ?>
            <p class="form-description"><?= nl2br(htmlspecialchars($form['description'])) ?></p>
        <?php endif; ?>
        
        <div class="error-message" id="error-message"></div>
        
        <form id="lead-form" onsubmit="submitForm(event)">
            <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label class="form-label">
                        <?= htmlspecialchars($field['label'] ?? $field['name']) ?>
                        <?php if (!empty($field['required'])): ?><span class="required">*</span><?php endif; ?>
                    </label>
                    <?php if ($field['type'] === 'textarea'): ?>
                        <textarea name="<?= htmlspecialchars($field['name']) ?>" class="form-control"
                            <?php if (!empty($field['required'])) echo 'required'; ?>
                            placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                        ></textarea>
                    <?php elseif ($field['type'] === 'select'): ?>
                        <select name="<?= htmlspecialchars($field['name']) ?>" class="form-control" <?php if (!empty($field['required'])) echo 'required'; ?>>
                            <option value="">Select...</option>
                            <?php foreach ($field['options'] ?? [] as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($field['type'] ?? 'text') ?>" 
                            name="<?= htmlspecialchars($field['name']) ?>" 
                            class="form-control"
                            <?php if (!empty($field['required'])) echo 'required'; ?>
                            placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                        >
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn-submit" id="submit-btn">Submit</button>
        </form>
    </div>
    
    <div class="powered-by">Powered by White Label CRM</div>
    
    <script>
        const FORM_SLUG = <?= json_encode($slug) ?>;
        const API_URL = '/api/form-submit.php';
        
        function submitForm(e) {
            e.preventDefault();
            const btn = document.getElementById('submit-btn');
            const errorDiv = document.getElementById('error-message');
            errorDiv.classList.remove('show');
            errorDiv.textContent = '';
            
            btn.disabled = true;
            btn.textContent = 'Submitting...';
            
            const formData = new FormData(e.target);
            const data = {};
            formData.forEach((value, key) => { data[key] = value; });
            
            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ form_slug: FORM_SLUG, data: data })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    document.getElementById('form-container').innerHTML = `
                        <div class="success-message">
                            <h2>✓ Thank You!</h2>
                            <p>${resp.message || 'We will contact you soon.'}</p>
                        </div>
                    `;
                    if (resp.redirect_url) {
                        setTimeout(() => { window.location.href = resp.redirect_url; }, 2000);
                    }
                } else {
                    errorDiv.textContent = resp.message || 'Something went wrong. Please try again.';
                    errorDiv.classList.add('show');
                    btn.disabled = false;
                    btn.textContent = 'Submit';
                }
            })
            .catch(() => {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.classList.add('show');
                btn.disabled = false;
                btn.textContent = 'Submit';
            });
        }
    </script>
</body>
</html>
