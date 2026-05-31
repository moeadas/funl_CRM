<?php
/**
 * White Label CRM - Contact Detail View
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
    ", [$contactId, $_SESSION['company_id'] ?? null])->fetch();
    
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
        WHERE t.contact_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ", [$contactId])->fetchAll();
    
    // Get deals for this contact
    $deals = $db->query("
        SELECT d.*, s.stage_label, s.color as stage_color
        FROM deals d
        LEFT JOIN deal_stages s ON d.stage = s.stage_name AND (s.company_id = d.company_id OR s.company_id = 0)
        WHERE d.contact_id = ?
        ORDER BY d.created_at DESC
        LIMIT 10
    ", [$contactId])->fetchAll();
    
    // Get quotes for this contact
    $quotes = $db->query("
        SELECT q.* FROM quotes q
        WHERE q.contact_id = ?
        ORDER BY q.created_at DESC
        LIMIT 10
    ", [$contactId])->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: contacts.php');
    exit;
}

$pageTitle = htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.contact-detail { max-width: 1200px; margin: 0 auto; padding: 20px; }
.contact-header { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; border: 1px solid #e5e7eb; }
.contact-header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.contact-name { font-size: 24px; font-weight: 700; margin: 0 0 4px; }
.contact-title { color: #6b7280; font-size: 14px; }
.contact-actions { display: flex; gap: 8px; }
.btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.btn-outline { background: white; border: 1px solid #d1d5db; color: #374151; }
.btn-danger { background: #dc2626; color: white; }

.contact-meta { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
.contact-meta-item { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #4b5563; }
.contact-meta-item .label { color: #9ca3af; font-size: 12px; text-transform: uppercase; }

.contact-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
.contact-main { display: flex; flex-direction: column; gap: 20px; }
.contact-sidebar { display: flex; flex-direction: column; gap: 20px; }

.card { background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; }
.card-title { font-size: 16px; font-weight: 600; margin: 0 0 16px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }

.info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
.info-row:last-child { border-bottom: none; }
.info-label { width: 140px; color: #6b7280; font-size: 13px; font-weight: 500; }
.info-value { flex: 1; font-size: 14px; color: #111827; }

.task-item, .deal-item, .quote-item { padding: 12px; border-radius: 8px; background: #f9fafb; margin-bottom: 8px; }
.task-item:hover, .deal-item:hover, .quote-item:hover { background: #f3f4f6; }
.item-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.item-meta { font-size: 12px; color: #6b7280; }

.status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
.status-todo { background: #f3f4f6; color: #6b7280; }
.status-in_progress { background: #dbeafe; color: #2563eb; }
.status-done { background: #d1fae5; color: #059669; }

.empty-state { text-align: center; padding: 40px; color: #9ca3af; font-size: 14px; }
</style>

<div class="contact-detail">
    <div class="contact-header">
        <div class="contact-header-top">
            <div>
                <h1 class="contact-name"><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></h1>
                <?php if ($contact['title']): ?>
                    <div class="contact-title"><?php echo htmlspecialchars($contact['title']); ?></div>
                <?php endif; ?>
            </div>
            <div class="contact-actions">
                <a href="/pages/contacts.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Back to Contacts')); ?></a>
                <button class="btn btn-primary" onclick="editContact(<?php echo $contactId; ?>)"><?php echo htmlspecialchars(__('Edit Contact')); ?></button>
            </div>
        </div>
        <div class="contact-meta">
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
                <?php if ($contact['account_name']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Account')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['account_name']); ?></div>
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
                            if ($contact['state_province']) $addr[] = $contact['state_province'];
                            if ($contact['postal_code']) $addr[] = $contact['postal_code'];
                            if ($contact['country']) $addr[] = $contact['country'];
                            echo htmlspecialchars(implode(', ', $addr));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($contact['website']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Website')); ?></div>
                    <div class="info-value"><a href="<?php echo htmlspecialchars($contact['website']); ?>" target="_blank"><?php echo htmlspecialchars($contact['website']); ?></a></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['birthday']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Birthday')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($contact['birthday']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['lead_source']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Lead Source')); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars(__($contact['lead_source'])); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($contact['notes']): ?>
                <div class="info-row">
                    <div class="info-label"><?php echo htmlspecialchars(__('Notes')); ?></div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($contact['notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Social Links -->
            <?php if ($contact['facebook_url'] || $contact['linkedin_url'] || $contact['twitter_url'] || $contact['instagram_url']): ?>
            <div class="card">
                <h3 class="card-title"><?php echo htmlspecialchars(__('Social Media')); ?></h3>
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <?php if ($contact['facebook_url']): ?>
                        <a href="<?php echo htmlspecialchars($contact['facebook_url']); ?>" target="_blank" class="btn btn-outline">Facebook</a>
                    <?php endif; ?>
                    <?php if ($contact['linkedin_url']): ?>
                        <a href="<?php echo htmlspecialchars($contact['linkedin_url']); ?>" target="_blank" class="btn btn-outline">LinkedIn</a>
                    <?php endif; ?>
                    <?php if ($contact['twitter_url']): ?>
                        <a href="<?php echo htmlspecialchars($contact['twitter_url']); ?>" target="_blank" class="btn btn-outline">Twitter</a>
                    <?php endif; ?>
                    <?php if ($contact['instagram_url']): ?>
                        <a href="<?php echo htmlspecialchars($contact['instagram_url']); ?>" target="_blank" class="btn btn-outline">Instagram</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="contact-sidebar">
            <!-- Tasks -->
            <div class="card">
                <h3 class="card-title"><?php echo htmlspecialchars(__('Tasks')); ?> (<?php echo count($tasks); ?>)</h3>
                <?php if (empty($tasks)): ?>
                    <div class="empty-state"><?php echo htmlspecialchars(__('No tasks for this contact')); ?></div>
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
                    <div class="empty-state"><?php echo htmlspecialchars(__('No deals for this contact')); ?></div>
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
                    <div class="empty-state"><?php echo htmlspecialchars(__('No quotes for this contact')); ?></div>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
