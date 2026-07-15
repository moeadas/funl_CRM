<?php
/**
 * White Label CRM - Contact Detail View
 * 
 * Uses the same design system as lead-detail.php: grid-3 layout with
 * detail-main (2/3) for content and sidebar (1/3) for management + quick actions.
 * Cards use standard .card / .card-header / .card-body components.
 * Sidebar uses .sidebar-item / .sidebar-label / .sidebar-value classes.
 *
 * @author Izzy (AI Assistant)
 * @lastupdated 2026-06-24
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

$companyId = $_SESSION['company_id'] ?? null;
$contactId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$contactId) {
    $_SESSION['error'] = "Invalid contact ID";
    header('Location: contacts.php');
    exit;
}

$db = Database::getInstance();

// Fetch contact data — scoped to company_id for security
$contact = $db->query("
    SELECT c.*, 
           u1.full_name as assigned_to_name,
           u2.full_name as created_by_name,
           a.account_name,
           a.website as account_website,
           a.industry as account_industry
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

// Get tasks for this contact — scoped to company_id
$tasks = $db->query("
    SELECT t.*, u.full_name as assigned_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.user_id
    WHERE t.contact_id = ? AND t.company_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
", [$contactId, $companyId])->fetchAll();

// Get deals for this contact — scoped to company_id
$deals = $db->query("
    SELECT d.*, s.stage_label, s.color as stage_color
    FROM deals d
    LEFT JOIN deal_stages s ON d.stage = s.stage_name AND s.company_id = d.company_id
    WHERE d.contact_id = ? AND d.company_id = ?
    ORDER BY d.created_at DESC
    LIMIT 10
", [$contactId, $companyId])->fetchAll();

// Get quotes for this contact — scoped to company_id
$quotes = $db->query("
    SELECT q.* FROM quotes q
    WHERE q.contact_id = ? AND q.company_id = ?
    ORDER BY q.created_at DESC
    LIMIT 10
", [$contactId, $companyId])->fetchAll();

// Get interactions — scoped to company_id
$interactions = $db->query("
    SELECT i.*, u.full_name as user_name
    FROM interactions i
    LEFT JOIN users u ON i.user_id = u.user_id
    WHERE i.contact_id = ? AND i.company_id = ?
    ORDER BY i.interaction_date DESC
    LIMIT 10
", [$contactId, $companyId])->fetchAll();

$pageTitle = htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header — matches lead-detail layout -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></h1>
        <?php if ($contact['title']): ?>
            <p class="text-muted" style="margin-top:4px;"><?php echo htmlspecialchars($contact['title']); ?><?php if (!empty($contact['account_name'])): ?> · <?php echo htmlspecialchars($contact['account_name']); ?><?php endif; ?></p>
        <?php elseif (!empty($contact['account_name'])): ?>
            <p class="text-muted" style="margin-top:4px;"><?php echo htmlspecialchars($contact['account_name']); ?></p>
        <?php endif; ?>
    </div>
    <div class="header-actions">
        <a href="/pages/contacts.php" class="btn btn-outline btn-sm"><?php echo htmlspecialchars(__('Back')); ?></a>
        <button class="btn btn-primary btn-sm" onclick="editContact(<?php echo $contactId; ?>)"><?php echo htmlspecialchars(__('Edit')); ?></button>
    </div>
</div>

<!-- Stats Cards — matches lead-detail stat cards -->
<div class="grid grid-4 mb-2">
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0e7ff;color:#4f46e5;">💬</div>
        <div class="stat-value"><?php echo count($interactions); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('Interactions')); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706;">✓</div>
        <div class="stat-value"><?php echo count($tasks); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('Tasks')); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5;color:#059669;">🎯</div>
        <div class="stat-value"><?php echo count($deals); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('Deals')); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fce7f3;color:#db2777;">📄</div>
        <div class="stat-value"><?php echo count($quotes); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('Quotes')); ?></div>
    </div>
</div>

<!-- Main Layout: grid-3 with detail-main (2/3) + sidebar (1/3) -->
<div class="grid grid-3">
    
<!-- Left Column (2/3 width) -->
<div class="detail-main">

    <!-- Contact Information Card — uses standard .card / .card-header / .card-body -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Contact Information')); ?></h3></div>
        <div class="card-body">
            <div class="grid grid-2">
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Full Name')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Title')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($contact['title'] ?: '—'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Account')); ?></div>
                    <div class="detail-value"><?php echo !empty($contact['account_name']) ? htmlspecialchars($contact['account_name']) : '—'; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Department')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($contact['department'] ?: '—'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Email')); ?></div>
                    <div class="detail-value"><?php if ($contact['email']): ?><a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>"><?php echo htmlspecialchars($contact['email']); ?></a><?php else: ?>—<?php endif; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Phone')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($contact['phone'] ?: '—'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Mobile')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($contact['mobile'] ?: '—'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Status')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($contact['contact_status'] ?: '—'); ?></div>
                </div>
            </div>
            <?php if ($contact['address'] || $contact['city'] || $contact['country']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--color-border-light);">
                <div class="detail-label" style="margin-bottom:6px;"><?php echo htmlspecialchars(__('Address')); ?></div>
                <div class="detail-value"><?php
                    $addr = [];
                    if ($contact['address']) $addr[] = $contact['address'];
                    if ($contact['city']) $addr[] = $contact['city'];
                    if ($contact['country']) $addr[] = $contact['country'];
                    echo htmlspecialchars(implode(', ', $addr));
                ?></div>
            </div>
            <?php endif; ?>
            <?php if ($contact['notes']): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--color-border-light);">
                <div class="detail-label" style="margin-bottom:6px;"><?php echo htmlspecialchars(__('Notes')); ?></div>
                <div class="detail-value" style="white-space:pre-wrap;"><?php echo htmlspecialchars($contact['notes']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Interactions — same card style as lead-detail -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Recent Interactions')); ?> (<?php echo count($interactions); ?>)</h3></div>
        <div class="card-body">
            <?php if (empty($interactions)): ?>
                <div class="empty-state"><?php echo htmlspecialchars(__('No interactions logged yet')); ?></div>
            <?php else: ?>
                <?php foreach ($interactions as $int): ?>
                    <div class="interaction-item" style="padding:12px;border-radius:var(--radius-sm);background:var(--color-bg-secondary);margin-bottom:8px;">
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px;"><?php echo htmlspecialchars($int['subject'] ?? $int['interaction_type'] ?? 'Interaction'); ?></div>
                        <div style="font-size:12px;color:var(--color-text-secondary);">
                            <span class="badge badge-secondary"><?php echo htmlspecialchars(ucfirst($int['interaction_type'] ?? 'note')); ?></span>
                            <?php if (!empty($int['interaction_date'])): ?> • <?php echo htmlspecialchars($int['interaction_date']); ?><?php endif; ?>
                            <?php if (!empty($int['user_name'])): ?> • <?php echo htmlspecialchars($int['user_name']); ?><?php endif; ?>
                        </div>
                        <?php if (!empty($int['notes'])): ?>
                            <div style="font-size:13px;color:var(--color-text-secondary);margin-top:4px;"><?php echo htmlspecialchars(mb_strimwidth($int['notes'], 0, 150, '…')); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tasks -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Tasks')); ?> (<?php echo count($tasks); ?>)</h3></div>
        <div class="card-body">
            <?php if (empty($tasks)): ?>
                <div class="empty-state"><?php echo htmlspecialchars(__('No tasks')); ?></div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div style="padding:12px;border-radius:var(--radius-sm);background:var(--color-bg-secondary);margin-bottom:8px;">
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px;"><?php echo htmlspecialchars($task['title']); ?></div>
                        <div style="font-size:12px;color:var(--color-text-secondary);">
                            <span class="badge badge-<?php echo strtolower($task['status']) === 'done' ? 'success' : (strtolower($task['status']) === 'in_progress' ? 'primary' : 'secondary'); ?>"><?php echo htmlspecialchars(__(strtolower($task['status']))); ?></span>
                            <?php if ($task['due_date']): ?> • <?php echo htmlspecialchars(__('Due')); ?> <?php echo htmlspecialchars($task['due_date']); ?><?php endif; ?>
                            <?php if ($task['assigned_name']): ?> • <?php echo htmlspecialchars($task['assigned_name']); ?><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
    
<!-- Right Column - Sidebar (1/3 width) — matches lead-detail sidebar -->
<div>
    <!-- Contact Management — same as lead-detail's Lead Management card -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Contact Management')); ?></h3></div>
        <div class="card-body">
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Assigned To')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($contact['assigned_to_name'] ?: __('Unassigned')); ?></div></div>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Created By')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($contact['created_by_name'] ?: __('Unknown')); ?></div></div>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Account')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($contact['account_name'] ?: '—'); ?></div></div>
            <?php if (!empty($contact['account_industry'])): ?>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Industry')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($contact['account_industry']); ?></div></div>
            <?php endif; ?>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Status')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($contact['contact_status'] ?: '—'); ?></div></div>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Created')); ?></div><div class="sidebar-value"><?php echo date('M d, Y', strtotime($contact['created_at'])); ?></div></div>
        </div>
    </div>

    <!-- Quick Actions — same style as lead-detail -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Quick Actions')); ?></h3></div>
        <div class="card-body quick-actions">
            <?php if (!empty($contact['phone']) || !empty($contact['mobile'])): 
                $callNumber = $contact['phone'] ?: $contact['mobile']; ?>
                <button class="btn btn-block" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;" onclick="VoIPPhone.call('<?php echo htmlspecialchars($callNumber); ?>', 0, <?php echo $contactId; ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <?php echo htmlspecialchars(__('VoIP Call')); ?>
                </button>
                <button class="btn btn-block" style="background:linear-gradient(135deg,#25D366,#128C7E);color:#fff;border:none;" onclick="WhatsAppChat.open(0, '<?php echo htmlspecialchars($callNumber); ?>', '<?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <?php echo htmlspecialchars(__('WhatsApp')); ?>
                </button>
            <?php endif; ?>
            <?php if (!empty($contact['email'])): ?>
                <button type="button" onclick="window.location.href='mailto:<?php echo htmlspecialchars($contact['email']); ?>'" class="btn btn-block" style="background:linear-gradient(135deg,#e63946,#c62828);color:#fff;border:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <?php echo htmlspecialchars(__('Send Email')); ?>
                </button>
            <?php endif; ?>
            <a href="/pages/interaction-new.php?contact_id=<?php echo $contactId; ?>" class="btn btn-block btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <?php echo htmlspecialchars(__('Log Interaction')); ?>
            </a>
            <a href="/pages/task-form.php?contact_id=<?php echo $contactId; ?>" class="btn btn-block btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <?php echo htmlspecialchars(__('Add Task')); ?>
            </a>
            <a href="/pages/proposal-form.php?contact_id=<?php echo $contactId; ?>" class="btn btn-block btn-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <?php echo htmlspecialchars(__('Create Proposal')); ?>
            </a>
        </div>
    </div>

    <!-- Deals -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Deals')); ?> (<?php echo count($deals); ?>)</h3></div>
        <div class="card-body">
            <?php if (empty($deals)): ?>
                <div class="empty-state"><?php echo htmlspecialchars(__('No deals')); ?></div>
            <?php else: ?>
                <?php foreach ($deals as $deal): ?>
                    <div style="padding:12px;border-radius:var(--radius-sm);background:var(--color-bg-secondary);margin-bottom:8px;">
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px;"><?php echo htmlspecialchars($deal['deal_name']); ?></div>
                        <div style="font-size:12px;color:var(--color-text-secondary);">
                            <span class="badge" style="background:<?php echo $deal['stage_color'] ?? '#e5e7eb'; ?>20;color:<?php echo $deal['stage_color'] ?? '#6b7280'; ?>"><?php echo htmlspecialchars(__($deal['stage_label'] ?? $deal['stage'])); ?></span>
                            <?php if ($deal['deal_value']): ?> • $<?php echo number_format($deal['deal_value'], 2); ?><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
    
</div><!-- /grid-3 -->

<script>
function editContact(contactId) {
    window.location.href = '/pages/contact-form.php?id=' + contactId;
}
</script>
