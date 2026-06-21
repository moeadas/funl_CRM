<?php
/**
 * White Label CRM - Log New Interaction (standalone page, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Log Interaction';
$currentPage = 'interactions';
$csrfToken = generateCSRFToken();

$db = Database::getInstance();
$leadId = intval($_GET['lead_id'] ?? 0);
$contactId = intval($_GET['contact_id'] ?? 0);
$interactionId = intval($_GET['id'] ?? 0);
$isEdit = $interactionId > 0;

// Fetch lead data for context
$leadData = null;
if ($leadId) {
    $leadData = $db->findOne('leads', ['lead_id' => $leadId]);
}

// Fetch contact data for context
$contactData = null;
if ($contactId) {
    $contactData = $db->query("SELECT * FROM contacts WHERE contact_id = ? AND company_id = ?", [$contactId, $_SESSION['company_id'] ?? null])->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
$errors = [];
$formData = [
    'interaction_type' => 'Call',
    'interaction_date' => date('Y-m-d H:i'),
    'subject' => '',
    'notes' => '',
    'next_action' => '',
    'next_action_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $formData['interaction_type'] = $_POST['interaction_type'] ?? 'Call';
    $formData['interaction_date'] = $_POST['interaction_date'] ?? date('Y-m-d H:i');
    $formData['subject'] = trim($_POST['subject'] ?? '');
    $formData['notes'] = trim($_POST['notes'] ?? '');
    $formData['next_action'] = trim($_POST['next_action'] ?? '');
    $formData['next_action_date'] = !empty($_POST['next_action_date']) ? $_POST['next_action_date'] : null;
    $postedLeadId = intval($_POST['lead_id'] ?? 0);
    $postedContactId = intval($_POST['contact_id'] ?? 0);

    if (!$postedLeadId && !$postedContactId) $errors[] = 'Lead or contact is required';
    if (!$formData['subject']) $errors[] = 'Subject is required';

    if (empty($errors)) {
        try {
            $data = [
                'lead_id' => $postedLeadId ?: null,
                'contact_id' => $postedContactId ?: null,
                'user_id' => getCurrentUserId(),
                'interaction_type' => $formData['interaction_type'],
                'interaction_date' => str_replace('T', ' ', $formData['interaction_date']) . ':00',
                'subject' => $formData['subject'],
                'notes' => $formData['notes'],
                'next_action' => $formData['next_action'] ?: null,
                'next_action_date' => $formData['next_action_date'] ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $newId = $db->insert('interactions', $data);
            if ($postedLeadId) {
                $db->update('leads', ['updated_at' => date('Y-m-d H:i:s')], ['lead_id' => $postedLeadId]);
            }
            logActivity(getCurrentUserId(), 'Log Interaction', 'Interaction', $newId, "Logged {$data['interaction_type']}");
            $_SESSION['success'] = 'Interaction logged successfully';
            if ($postedContactId) {
                header('Location: /pages/contact-detail.php?id=' . $postedContactId);
            } else {
                header('Location: /pages/lead-detail.php?id=' . $postedLeadId);
            }
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
    $leadId = $postedLeadId;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="<?= $leadId ? '/pages/lead-detail.php?id=' . $leadId : '/pages/interactions.php' ?>" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Log Interaction')); ?></h1>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="max-width:780px;margin-bottom:16px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;">
        <?php foreach ($errors as $e): ?>
            <div><?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($leadData): ?>
    <div class="card" style="max-width:780px;margin-bottom:16px;background:linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);border-color:#bfdbfe;">
        <div class="card-body" style="padding:16px 24px;display:flex;align-items:center;gap:12px;">
            <div style="width:42px;height:42px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0;">
                <?= strtoupper(substr($leadData['contact_person'] ?? $leadData['company_name'] ?? 'L', 0, 1)) ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($leadData['contact_person'] ?: $leadData['company_name'] ?: 'Lead #' . $leadId) ?></strong>
                <?php if ($leadData['company_name'] && $leadData['contact_person']): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($leadData['company_name']) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php elseif ($contactData): ?>
    <div class="card" style="max-width:780px;margin-bottom:16px;background:linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);border-color:#bfdbfe;">
        <div class="card-body" style="padding:16px 24px;display:flex;align-items:center;gap:12px;">
            <div style="width:42px;height:42px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0;">
                <?= strtoupper(substr($contactData['first_name'] ?? 'C', 0, 1)) ?>
            </div>
            <div>
                <strong><?= htmlspecialchars($contactData['first_name'] . ' ' . $contactData['last_name']) ?></strong>
                <?php if ($contactData['email']): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($contactData['email']) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="POST" style="max-width:780px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="lead_id" value="<?= $leadId ?>">
    <input type="hidden" name="contact_id" value="<?= $contactId ?>">

    <div class="card">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Interaction Details')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="row-2">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Type')); ?> *</label>
                    <select name="interaction_type" class="form-control" required style="padding:10px 14px;">
                        <option value="Call" <?= $formData['interaction_type'] === 'Call' ? 'selected' : '' ?>><?php echo htmlspecialchars(__('Call')); ?></option>
                        <option value="Email" <?= $formData['interaction_type'] === 'Email' ? 'selected' : '' ?>><?php echo htmlspecialchars(__('Email')); ?></option>
                        <option value="Meeting" <?= $formData['interaction_type'] === 'Meeting' ? 'selected' : '' ?>><?php echo htmlspecialchars(__('Meeting')); ?></option>
                        <option value="WhatsApp" <?= $formData['interaction_type'] === 'WhatsApp' ? 'selected' : '' ?>><?php echo htmlspecialchars(__('WhatsApp')); ?></option>
                        <option value="Note" <?= $formData['interaction_type'] === 'Note' ? 'selected' : '' ?>><?php echo htmlspecialchars(__('Note')); ?></option>
                        <option value="Demo" <?= $formData['interaction_type'] === 'Demo' ? 'selected' : '' ?>><?php echo htmlspecialchars(__('Demo')); ?></option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Date & Time')); ?> *</label>
                    <input type="datetime-local" name="interaction_date" class="form-control" required
                           value="<?= htmlspecialchars($formData['interaction_date']) ?>"
                           style="padding:10px 14px;">
                </div>
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Subject')); ?> *</label>
                <input type="text" name="subject" class="form-control" required
                       placeholder="<?php echo htmlspecialchars(__('e.g., Discussed pricing')); ?>"
                       value="<?= htmlspecialchars($formData['subject']) ?>"
                       style="padding:10px 14px;">
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Notes')); ?></label>
                <textarea name="notes" class="form-control" rows="5"
                          placeholder="<?php echo htmlspecialchars(__('What was discussed? Key points?')); ?>"
                          style="padding:10px 14px;"><?= htmlspecialchars($formData['notes']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Next Action')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Next step')); ?></label>
                <input type="text" name="next_action" class="form-control"
                       placeholder="<?php echo htmlspecialchars(__('e.g., Send proposal')); ?>"
                       value="<?= htmlspecialchars($formData['next_action']) ?>"
                       style="padding:10px 14px;">
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Due date')); ?></label>
                <input type="date" name="next_action_date" class="form-control"
                       value="<?= htmlspecialchars($formData['next_action_date']) ?>"
                       style="padding:10px 14px;max-width:300px;">
            </div>
        </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px;">
        <a href="<?= $leadId ? '/pages/lead-detail.php?id=' . $leadId : '/pages/interactions.php' ?>" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo htmlspecialchars(__('Save Interaction')); ?>
        </button>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
