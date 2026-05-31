<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$formId = intval($_GET['id'] ?? 0);

if (empty($formId)) {
    echo '<div style="padding:40px;text-align:center;font-family:system-ui;color:#6b7280;">Form ID is missing.</div>';
    exit;
}

$form = $db->query("SELECT * FROM webforms WHERE form_id = ? AND status = 'active'", [$formId])->fetch();
if (!$form) {
    echo '<div style="padding:40px;text-align:center;font-family:system-ui;color:#6b7280;">This web form is not available or inactive.</div>';
    exit;
}

// Fetch fields from webform_fields table
$fields = $db->query("SELECT * FROM webform_fields WHERE form_id = ? ORDER BY position ASC", [$formId])->fetchAll();

$primaryColor = '#2563eb';
$bgColor = '#ffffff';
$textColor = '#1f2937';
$borderRadius = '6';
$fontFamily = 'system-ui, -apple-system, sans-serif';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['form_name'] ?? 'Web Form') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: <?php echo $fontFamily ?>;
            background: <?php echo $bgColor ?>;
            color: <?php echo $textColor ?>;
            line-height: 1.5;
            padding: 20px;
        }
        .form-container { max-width: 480px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
        .form-title { font-size: 20px; font-weight: 600; margin-bottom: 6px; color: #111827; }
        .form-description { font-size: 13px; color: #6b7280; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #374151; }
        .form-label .required { color: #dc2626; margin-left: 2px; }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db;
            border-radius: <?php echo $borderRadius ?>px; font-size: 14px;
            font-family: inherit; color: <?php echo $textColor ?>; background: white;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus {
            outline: none; border-color: <?php echo $primaryColor ?>;
            box-shadow: 0 0 0 3px <?php echo $primaryColor ?>20;
        }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .btn-submit {
            width: 100%; padding: 12px; background: <?php echo $primaryColor ?>;
            color: white; border: none; border-radius: <?php echo $borderRadius ?>px;
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
        .powered-by { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 20px; text-decoration: none; display: block; }
    </style>
</head>
<body>
    <div class="form-container" id="form-container">
        <?php if ($form['form_name']): ?>
            <h1 class="form-title"><?php echo htmlspecialchars($form['form_name']) ?></h1>
        <?php endif; ?>
        <?php if ($form['description']): ?>
            <p class="form-description"><?php echo nl2br(htmlspecialchars($form['description'])) ?></p>
        <?php endif; ?>
        
        <div class="error-message" id="error-message"></div>
        
        <form id="lead-form" onsubmit="submitForm(event)">
            <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label class="form-label">
                        <?php echo htmlspecialchars($field['field_label']) ?>
                        <?php if (!empty($field['required'])): ?><span class="required">*</span><?php endif; ?>
                    </label>
                    <?php if ($field['field_type'] === 'textarea'): ?>
                        <textarea name="<?php echo htmlspecialchars($field['crm_field']) ?>" class="form-control"
                            <?php if (!empty($field['required'])) echo 'required'; ?>
                            placeholder="Enter <?php echo htmlspecialchars(strtolower($field['field_label'])) ?>..."
                        ></textarea>
                    <?php else: ?>
                        <input type="<?php echo htmlspecialchars($field['field_type'] ?? 'text') ?>" 
                            name="<?php echo htmlspecialchars($field['crm_field']) ?>" 
                            class="form-control"
                            <?php if (!empty($field['required'])) echo 'required'; ?>
                            placeholder="Enter <?php echo htmlspecialchars(strtolower($field['field_label'])) ?>..."
                        >
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn-submit" id="submit-btn">Submit</button>
        </form>
    </div>
    
    <a href="#" class="powered-by" target="_blank">Powered by FunL CRM</a>
    
    <script>
        const FORM_ID = <?php echo json_encode($formId) ?>;
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
                body: JSON.stringify({ form_id: FORM_ID, data: data })
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    document.getElementById('form-container').innerHTML = `
                        <div class="success-message">
                            <h2>✓ Thank You!</h2>
                            <p>${resp.message || 'Your submission has been received.'}</p>
                        </div>
                    `;
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
