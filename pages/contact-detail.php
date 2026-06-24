<?php
/**
 * White Label CRM - Contact Detail View
 * Quick actions: Email, WhatsApp, Call, Log Interaction
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

$contactId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$contactId) {
    $_SESSION['error'] = "Invalid contact ID";
    header('Location: contacts.php');
    exit;
}

try {
    $db = Database::getInstance();
    $companyId = $_SESSION['company_id'] ?? null;
    
    // Auto-recover company_id from DB if session is stale
    if (!$companyId && !empty($_SESSION['user_id'])) {
        $companyId = $db->query("SELECT company_id FROM users WHERE user_id = ?", [$_SESSION['user_id']])->fetchColumn();
        if ($companyId) $_SESSION['company_id'] = (int)$companyId;
    }
    
    $contact = $db->query("
        SELECT c.*, 
               u1.full_name as assigned_to_name,
               u2.full_name as created_by_name,
               a.account_name
        FROM contacts c
        LEFT JOIN users u1 ON c.assigned_to = u1.user_id
        LEFT JOIN users u2 ON c.created_by = u2.user_id
        LEFT JOIN accounts a ON c.account_id = a.account_id
        WHERE c.contact_id = ? AND c.company_id = ?
    ", [$contactId, $companyId])->fetch();
    
    if (!$contact) {
        $_SESSION['error'] = "Contact not found";
        header('Location: contacts.php');
        exit;
    }
    
    
    // Get tasks for this contact
    $tasks = $db->query("
        SELECT t.*, u.full_name as assigned_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.user_id
        WHERE t.contact_id = ? AND t.company_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ", [$contactId, $companyId])->fetchAll();
    
    // Get deals for this contact
    $deals = $db->query("
        SELECT d.*, s.stage_label, s.color as stage_color
        FROM deals d
        LEFT JOIN deal_stages s ON d.stage = s.stage_name AND s.company_id = d.company_id
        WHERE d.contact_id = ? AND d.company_id = ?
        ORDER BY d.created_at DESC
        LIMIT 10
    ", [$contactId, $companyId])->fetchAll();
    
    // Get quotes for this contact
    $quotes = $db->query("
        SELECT q.* FROM quotes q
        WHERE q.contact_id = ? AND q.company_id = ?
        ORDER BY q.created_at DESC
        LIMIT 10
    ", [$contactId, $companyId])->fetchAll();
    
    // Get interactions
    $interactions = $db->query("
        SELECT i.*, u.full_name as user_name
        FROM interactions i
        LEFT JOIN users u ON i.user_id = u.user_id
        WHERE i.contact_id = ? AND i.company_id = ?
        ORDER BY i.interaction_date DESC
        LIMIT 10
    ", [$contactId, $companyId])->fetchAll();

} catch (\Throwable $e) {
    error_log("contact-detail.php error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: contacts.php');
    exit;
}

$pageTitle = htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Removed: .contact-detail wrapper — now uses standard .main-content layout */
.contact-header { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 16px; box-shadow: var(--shadow-xs); }
.contact-header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.contact-detail-name { font-size: 24px; font-weight: 700; color: var(--color-text); margin: 0; }
.contact-title { color: var(--color-text-secondary); font-size: 14px; margin-top: 4px; }
.contact-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.quick-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-border); }
.quick-action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text); transition: all 0.15s; }
.quick-action-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
.quick-action-btn.whatsapp { border-color: #25d366; color: #25d366; }
.quick-action-btn.whatsapp:hover { background: #25d366; color: white; }
.quick-action-btn.email { border-color: var(--color-primary); color: var(--color-primary); }
.quick-action-btn.email:hover { background: var(--color-primary); color: white; }
.quick-action-btn.call { border-color: #6366f1; color: #6366f1; }
.quick-action-btn.call:hover { background: #6366f1; color: white; }
.quick-action-btn.interaction { border-color: #f59e0b; color: #f59e0b; }
.quick-action-btn.interaction:hover { background: #f59e0b; color: white; }
.contact-meta { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-border); }
.contact-meta-item { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--color-text-secondary); }
.contact-meta-item .label { color: var(--color-text-muted); font-size: 12px; text-transform: uppercase; font-weight: 500; }
.contact-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; }
@media (max-width: 768px) { .contact-grid { grid-template-columns: 1fr; } }
.contact-main { display: flex; flex-direction: column; gap: 16px; }
.contact-sidebar { display: flex; flex-direction: column; gap: 16px; }
.info-row { display: flex; padding: 10px 0; border-bottom: 1px solid var(--color-border-light); }
.info-row:last-child { border-bottom: none; }
.info-label { width: 140px; color: var(--color-text-secondary); font-size: 13px; font-weight: 500; }
.info-value { flex: 1; font-size: 14px; color: var(--color-text); }
.task-item, .deal-item, .quote-item, .interaction-item { padding: 12px; border-radius: var(--radius-sm); background: var(--color-bg-secondary); margin-bottom: 8px; }
.task-item:hover, .deal-item:hover, .quote-item:hover, .interaction-item:hover { background: var(--color-bg-tertiary); }
.item-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.item-meta { font-size: 12px; color: var(--color-text-secondary); }
.status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
.status-todo { background: #f3f4f6; color: #6b7280; }
.status-in_progress { background: #dbeafe; color: #2563eb; }
.status-done { background: #d1fae5; color: #059669; }
.status-review { background: #fef3c7; color: #d97706; }
.empty-state { text-align: center; padding: 32px; color: var(--color-text-muted); font-size: 14px; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></h1>
        <?php if ($contact['title']): ?>
            <p class="text-muted" style="margin-top:4px;"><?php echo htmlspecialchars($contact['title']); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact['account_name'])): ?>
                    <div class="contact-title"><?php echo htmlspecialchars($contact['account_name']); ?></div>
                <?php endif; ?>
            </div>
            <div class="contact-actions">
                <a href="/pages/contacts.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Back')); ?></a>
                <button class="btn btn-primary" onclick="editContact(<?php echo $contactId; ?>)"><?php echo htmlspecialchars(__('Edit')); ?></button>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <div class="contact-meta"
            <?php if ($contact['email']): ?>
                <div class="contact-meta-item">
                    <span>📧</span>
                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><?php echo htmlspecialchars($contact['email']); ?></a>
                </div>
            <?php endif; ?>
            <?php if ($contact['phone']): ?>
                <div class="contact-meta-item">
                    <span>📞</span>
                    <a href="tel:<?php echo htmlspecialchars($contact['phone']); ?>"><?php echo htmlspecialchars($contact['phone']); ?></a>
                </div>
            <?php endif; ?>
            <?php if ($contact['mobile']): ?>
                <div class="contact-meta-item">
                    <span>📱</span>
                    <a href="tel:<?php echo htmlspecialchars($contact['mobile']); ?>"><?php echo htmlspecialchars($contact['mobile']); ?></a>
                </div>
            <?php endif; ?>
            <?php if ($contact['assigned_to_name']): ?>
                <div class="contact-meta-item">
                    <span>👤</span>
                    <?php echo htmlspecialchars(__('Assigned to')); ?>: <?php echo htmlspecialchars($contact['assigned_to_name']); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <?php if (!empty($contact['email'])): ?>
                <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" class="quick-action-btn email">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?php echo __('Send Email'); ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($contact['phone']) || !empty($contact['mobile'])): ?>
                <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $contact['mobile'] ?: $contact['phone'])); ?>" target="_blank" class="quick-action-btn whatsapp">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    <?php echo __('WhatsApp'); ?>
                </a>
                <a href="tel:<?php echo htmlspecialchars($contact['phone'] ?: $contact['mobile']); ?>" class="quick-action-btn call">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <?php echo __('Call'); ?>
                </a>
            <?php endif; ?>
            <button onclick="logInteraction(<?php echo $contactId; ?>)" class="quick-action-btn interaction">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <?php echo __('Log Interaction'); ?>
            </button>
            <a href="/pages/task-form.php?contact_id=<?php echo $contactId; ?>" class="quick-action-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <?php echo __('Add Task'); ?>
            </a>
            <a href="/pages/deal-form.php?contact_id=<?php echo $contactId; ?>" class="quick-action-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <?php echo __('Add Deal'); ?>
            </a>
        </div>
        </div>
</div>

<div class="contact-grid">
        <div class="contact-main">
            <!-- Contact Info -->
            <div class="card">
                <h3 class="card-title"><?php echo htmlspecialchars(__('Contact Information')); ?></h3>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Full Name')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')); ?></div>
                </div>
                <?php if ($contact['title']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Title')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['title']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($contact['account_name'])): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Account')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['account_name']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['department']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Department')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['department']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['email']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Email')); ?></div>
                    <div class="info-value"><a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><?php echo htmlspecialchars($contact['email']); ?></a></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['phone']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Phone')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['phone']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['mobile']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Mobile')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['mobile']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['address'] || $contact['city'] || $contact['country']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Address')); ?></div>
                    <div class="info-value">
                        <?php 
                            $addr = [];
                            if ($contact['address']) $addr[] = $contact['address'];
                            if ($contact['city']) $addr[] = $contact['city'];
                            if ($contact['country']) $addr[] = $contact['country'];
                            echo htmlspecialchars(implode(', ', $addr));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($contact['contact_status']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Status')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['contact_status']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['notes']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Notes')); ?></div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($contact['notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Interactions -->
            <div class="card">
                <h3 class="card-title"><?php echo htmlspecialchars(__('Recent Interactions')); ?> (<?php echo count($interactions); ?>)</h3>
                <?php if (empty($interactions)): ?>
                    <div class="empty-state"><?php echo htmlspecialchars(__('No interactions logged yet')); ?></div>
                <?php else: ?>
                    <?php foreach ($interactions as $int): ?>
                        <div class="interaction-item">
                            <div class="item-title"><?php echo htmlspecialchars($int['subject'] ?? $int['interaction_type'] ?? 'Interaction'); ?></div>
                            <div class="item-meta">
                                <span class="status-badge status-todo"><?php echo htmlspecialchars(ucfirst($int['interaction_type'] ?? 'note')); ?></span>
                                <?php if (!empty($int['interaction_date'])): ?> • <?php echo htmlspecialchars($int['interaction_date']); ?><?php endif; ?>
                                <?php if (!empty($int['user_name'])): ?> • <?php echo htmlspecialchars($int['user_name']); ?><?php endif; ?>
                            </div>
                            <?php if (!empty($int['notes'])): ?>
                                <div style="font-size:13px;color:var(--color-text-secondary);margin-top:4px;"><?php echo htmlspecialchars(substr($int['notes'], 0, 150)); ?><?php if(strlen($int['notes']) > 150) echo '...'; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="contact-sidebar">
            <!-- Tasks -->
            <div class="card">
                <h3 class="card-title"><?php echo htmlspecialchars(__('Tasks')); ?> (<?php echo count($tasks); ?>)</h3>
                <?php if (empty($tasks)): ?>
                    <div class="empty-state"><?php echo htmlspecialchars(__('No tasks')); ?></div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-item">
                            <div class="item-title"><?php echo htmlspecialchars($task['title']); ?></div>
                            <div class="item-meta">
                                <span class="status-badge status-<?php echo $task['status']; ?>"><?php echo htmlspecialchars(__(strtolower($task['status']))); ?></span>
                                <?php if ($task['due_date']): ?> • <?php echo htmlspecialchars(__('Due')); ?> <?php echo htmlspecialchars($task['due_date']); ?><?php endif; ?>
                                <?php if ($task['assigned_name']): ?> • <?php echo htmlspecialchars($task['assigned_name']); ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Deals -->
            <div class="card">
                <h3 class="card-title"><?php echo htmlspecialchars(__('Deals')); ?> (<?php echo count($deals); ?>)</h3>
                <?php if (empty($deals)): ?>
                    <div class="empty-state"><?php echo htmlspecialchars(__('No deals')); ?></div>
                <?php else: ?>
                    <?php foreach ($deals as $deal): ?>
                        <div class="deal-item">
                            <div class="item-title"><?php echo htmlspecialchars($deal['deal_name']); ?></div>
                            <div class="item-meta">
                                <span class="status-badge" style="background: <?php echo $deal['stage_color'] ?? '#e5e7eb'; ?>20; color: <?php echo $deal['stage_color'] ?? '#6b7280'; ?>"><?php echo htmlspecialchars(__($deal['stage_label'] ?? $deal['stage'])); ?></span>
                                <?php if ($deal['deal_value']): ?> • $<?php echo number_format($deal['deal_value'], 2); ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quotes -->
            <div class="card">
                <h3 class="card-title"><?php echo htmlspecialchars(__('Quotes')); ?> (<?php echo count($quotes); ?>)</h3>
                <?php if (empty($quotes)): ?>
                    <div class="empty-state"><?php echo htmlspecialchars(__('No quotes')); ?></div>
                <?php else: ?>
                    <?php foreach ($quotes as $quote): ?>
                        <div class="quote-item">
                            <div class="item-title"><?php echo htmlspecialchars($quote['quote_title'] ?? $quote['quote_number']); ?></div>
                            <div class="item-meta">
                                <span class="status-badge status-<?php echo $quote['status']; ?>"><?php echo htmlspecialchars(__(strtolower($quote['status']))); ?></span>
                                <?php if ($quote['total']): ?> • $<?php echo number_format($quote['total'], 2); ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function editContact(contactId) {
    window.location.href = '/pages/contact-form.php?id=' + contactId;
}

function logInteraction(contactId) {
    window.location.href = '/pages/interaction-new.php?contact_id=' + contactId;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>