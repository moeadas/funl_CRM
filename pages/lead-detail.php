<?php
/**
 * White Label CRM - Lead Detail View
 * With inline WhatsApp chat history, VoIP call history, expandable interactions
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$companyId = $_SESSION['company_id'] ?? null;
$leadId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$leadId) {
    header('Location: leads.php');
    exit;
}

$db = Database::getInstance();

$lead = $db->query("
    SELECT l.*,
           u1.full_name as assigned_to_name,
           u2.full_name as created_by_name,
           COUNT(DISTINCT i.interaction_id) as total_interactions,
           COUNT(DISTINCT d.document_id) as total_documents
    FROM leads l
    LEFT JOIN users u1 ON l.assigned_to = u1.user_id
    LEFT JOIN users u2 ON l.created_by = u2.user_id
    LEFT JOIN interactions i ON l.lead_id = i.lead_id
    LEFT JOIN documents d ON l.lead_id = d.lead_id
    WHERE l.lead_id = ? AND l.company_id = ?
    GROUP BY l.lead_id
", [$leadId, $companyId])->fetch();

if (!$lead) {
    header('Location: leads.php');
    exit;
}

    
$interactions = $db->query("
    SELECT i.*, u.full_name as user_name
    FROM interactions i
    LEFT JOIN users u ON i.user_id = u.user_id
    WHERE i.lead_id = ?
    ORDER BY i.interaction_date DESC
    LIMIT 20
", [$leadId])->fetchAll();
    
$documents = $db->query("
    SELECT d.*, u.full_name as uploaded_by_name
    FROM documents d
    LEFT JOIN users u ON d.uploaded_by = u.user_id
    WHERE d.lead_id = ?
    ORDER BY d.uploaded_at DESC
", [$leadId])->fetchAll();
    
$activities = $db->query("
    SELECT a.*, u.full_name as user_name
    FROM activity_log a
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.entity_type = 'lead' AND a.entity_id = ?
    ORDER BY a.created_at DESC
    LIMIT 20
", [$leadId])->fetchAll();

// Fetch WhatsApp messages for this lead (ordered by sent_at for proper chronological display)
$waMessages = $db->query("
    SELECT wm.*, u.full_name as user_name
    FROM whatsapp_messages wm
    LEFT JOIN users u ON wm.user_id = u.user_id
    WHERE wm.lead_id = ?
    ORDER BY COALESCE(wm.sent_at, wm.created_at) ASC
", [$leadId])->fetchAll();

// Fetch VoIP call history for this lead
$voipCalls = $db->query("
    SELECT vc.*, u.full_name as user_name
    FROM voip_calls vc
    LEFT JOIN users u ON vc.user_id = u.user_id
    WHERE vc.lead_id = ?
    ORDER BY vc.created_at DESC
    LIMIT 20
", [$leadId])->fetchAll();

// Fetch Email interactions for this lead
$emailInteractions = $db->query("
    SELECT i.*, u.full_name as user_name
    FROM interactions i
    LEFT JOIN users u ON i.user_id = u.user_id
    WHERE i.lead_id = ? AND i.interaction_type = 'Email'
    ORDER BY i.interaction_date DESC
    LIMIT 20
", [$leadId])->fetchAll();
    

$csrf_token = generateCSRFToken();
$isManager = hasRole('Sales Manager');

// Handle delete interaction POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_interaction') {
requireCSRF();
if (!$isManager) {
    $_SESSION['error'] = 'Permission denied.';
} else {
    try {
        $deleteId = intval($_POST['interaction_id']);
        $db->query("DELETE FROM interactions WHERE interaction_id = ?", [$deleteId]);
        logActivity(getCurrentUserId(), 'Delete Interaction', 'Interaction', $deleteId, "Deleted interaction ID $deleteId from lead #$leadId");
        $_SESSION['success'] = "Interaction deleted successfully.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting interaction: " . $e->getMessage();
    }
}
header('Location: lead-detail.php?id=' . $leadId);
exit;
}

$pageTitle = htmlspecialchars($lead['contact_person'] ?: $lead['company_name'] ?: __('Lead #') . $leadId);
include '../includes/header.php';

$statusColors = [
'New Lead' => 'info', 'Contacted' => 'warning', 'Interested' => 'success',
'Not Interested' => 'danger', 'Schedule Call' => 'primary', 'Proposal Sent' => 'warning',
'Negotiation' => 'info', 'Won' => 'success', 'Lost' => 'danger'
];
?>

<!-- Lead Header -->
<div class="lead-header">
<div class="lead-header-left">
    <div class="lead-avatar">
        <?php echo strtoupper(substr($lead['contact_person'] ?: $lead['company_name'] ?: '??', 0, 2)); ?>
    </div>
    <div>
        <h1 class="lead-title"><?php echo htmlspecialchars($lead['contact_person'] ?: $lead['company_name'] ?: __('Lead #') . $leadId); ?></h1>
        <div class="lead-meta">
            <span class="badge badge-<?php echo $statusColors[$lead['lead_status']] ?? 'secondary'; ?>">
                <?php echo htmlspecialchars(__($lead['lead_status'])); ?>
            </span>
            <span class="badge badge-outline"><?php echo htmlspecialchars(__($lead['lead_type'])); ?></span>
            <span class="text-muted"><?php echo htmlspecialchars(($lead['city'] ? $lead['city'] . ', ' : '') . ($lead['country'] ? __($lead['country']) : '')); ?></span>
        </div>
    </div>
</div>
<div class="lead-header-actions">
    <?php if ($lead['lead_status'] !== 'Won'): ?>
        <button onclick="moveLeadToContact(<?php echo $leadId; ?>)" class="btn btn-outline" style="background:#f0fdf4;border-color:#bbf7d0;color:#15803d;display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            <?php echo htmlspecialchars(__('Convert to Contact')); ?>
        </button>
    <?php endif; ?>
    <a href="lead-form.php?id=<?php echo $leadId; ?>" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        <?php echo htmlspecialchars(__('Edit Lead')); ?>
    </a>
    <a href="leads.php" class="btn btn-outline">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <?php echo htmlspecialchars(__('Back')); ?>
    </a>
</div>
</div>

<!-- Quick Stats -->
<div class="grid grid-4 mb-2">
<div class="stat-card">
    <div class="stat-icon bg-gradient-info">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </div>
    <div class="stat-details">
        <div class="stat-value"><?php echo $lead['total_interactions']; ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('Interactions')); ?></div>
    </div>
</div>
<div class="stat-card">
    <div class="stat-icon bg-gradient-warning">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
    </div>
    <div class="stat-details">
        <div class="stat-value"><?php echo count($waMessages); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('WhatsApp Msgs')); ?></div>
    </div>
</div>
<div class="stat-card">
    <div class="stat-icon bg-gradient-success">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
    </div>
    <div class="stat-details">
        <div class="stat-value"><?php echo count($voipCalls); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('VoIP Calls')); ?></div>
    </div>
</div>
<div class="stat-card">
    <div class="stat-icon bg-gradient-primary">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    </div>
    <div class="stat-details">
        <div class="stat-value"><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></div>
        <div class="stat-label"><?php echo htmlspecialchars(__('Created')); ?></div>
    </div>
</div>
</div>

<div class="grid grid-3">
<!-- Left Column (2/3 width) -->
<div class="detail-main">
    <!-- Contact Information -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Contact Information')); ?></h3></div>
        <div class="card-body">
            <div class="grid grid-2">
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Contact Person')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($lead['contact_person'] ?? __('N/A')); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Title / Position')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($lead['title_position'] ?? __('N/A')); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Country')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($lead['country'] ? __($lead['country']) : __('N/A')); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('City')); ?></div>
                    <div class="detail-value"><?php echo htmlspecialchars($lead['city'] ?? __('N/A')); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Email')); ?></div>
                    <div class="detail-value">
                        <?php if ($lead['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($lead['email']); ?>"><?php echo htmlspecialchars($lead['email']); ?></a>
                        <?php else: ?><?php echo htmlspecialchars(__('N/A')); ?><?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Phone')); ?></div>
                    <div class="detail-value">
                        <?php if ($lead['phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($lead['phone']); ?>"><?php echo htmlspecialchars($lead['phone']); ?></a>
                            <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="comm-btn comm-btn-call" onclick="VoIPPhone.call('<?php echo htmlspecialchars($lead['phone']); ?>', <?php echo $leadId; ?>)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    <?php echo htmlspecialchars(__('VoIP Call')); ?>
                                </button>
                                <button class="comm-btn comm-btn-wa" onclick="WhatsAppChat.open(<?php echo $leadId; ?>, '<?php echo htmlspecialchars($lead['phone']); ?>', '<?php echo htmlspecialchars($lead['contact_person'] ?: $lead['company_name']); ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                    <?php echo htmlspecialchars(__('WhatsApp')); ?>
                                </button>
                                <?php if ($lead['email']): ?>
                                <button class="comm-btn" style="background:linear-gradient(135deg,#e63946,#c62828);color:#fff;" onclick="openComposeEmail()">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <?php echo htmlspecialchars(__('Send Email')); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?><?php echo htmlspecialchars(__('N/A')); ?><?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Mobile')); ?></div>
                    <div class="detail-value">
                        <?php if ($lead['mobile']): ?>
                            <?php echo htmlspecialchars($lead['mobile']); ?>
                            <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="comm-btn comm-btn-call" onclick="VoIPPhone.call('<?php echo htmlspecialchars($lead['mobile']); ?>', <?php echo $leadId; ?>)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    <?php echo htmlspecialchars(__('VoIP Call')); ?>
                                </button>
                                <button class="comm-btn comm-btn-wa" onclick="WhatsAppChat.open(<?php echo $leadId; ?>, '<?php echo htmlspecialchars($lead['mobile']); ?>', '<?php echo htmlspecialchars($lead['contact_person'] ?: $lead['company_name']); ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                    <?php echo htmlspecialchars(__('WhatsApp')); ?>
                                </button>
                                <?php if ($lead['email']): ?>
                                <button class="comm-btn" style="background:linear-gradient(135deg,#e63946,#c62828);color:#fff;" onclick="openComposeEmail()">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <?php echo htmlspecialchars(__('Send Email')); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?><?php echo htmlspecialchars(__('N/A')); ?><?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label"><?php echo htmlspecialchars(__('Website')); ?></div>
                    <div class="detail-value">
                        <?php if ($lead['website']): ?>
                            <a href="<?php echo htmlspecialchars($lead['website']); ?>" target="_blank"><?php echo htmlspecialchars($lead['website']); ?></a>
                        <?php else: ?><?php echo htmlspecialchars(__('N/A')); ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
        
    <!-- Location & Address -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Location & Address')); ?></h3></div>
        <div class="card-body">
            <div class="grid grid-2">
                <div class="detail-item"><div class="detail-label"><?php echo htmlspecialchars(__('City')); ?></div><div class="detail-value"><?php echo htmlspecialchars($lead['city'] ?? __('N/A')); ?></div></div>
                <div class="detail-item"><div class="detail-label"><?php echo htmlspecialchars(__('Address')); ?></div><div class="detail-value"><?php echo htmlspecialchars($lead['address'] ?? __('N/A')); ?></div></div>
                <div class="detail-item detail-span-2"><div class="detail-label"><?php echo htmlspecialchars(__('Specialization')); ?></div><div class="detail-value"><?php echo htmlspecialchars($lead['specialization'] ?? __('N/A')); ?></div></div>
            </div>
        </div>
    </div>
        
    <!-- Custom Fields -->
    <?php
    $customFieldValues = getAllCustomFieldValues($leadId);
    if (!empty($customFieldValues)):
    ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Custom Fields')); ?></h3></div>
        <div class="card-body">
            <div class="grid grid-2">
                <?php foreach ($customFieldValues as $cf): ?>
                    <?php if ($cf['field_value']): ?>
                    <div class="detail-item">
                        <div class="detail-label"><?php echo htmlspecialchars($cf['field_label']); ?></div>
                        <div class="detail-value">
                            <?php
                            if ($cf['field_type'] === 'url' && $cf['field_value']):
                                echo '<a href="' . htmlspecialchars($cf['field_value']) . '" target="_blank">' . htmlspecialchars($cf['field_value']) . '</a>';
                            elseif ($cf['field_type'] === 'email' && $cf['field_value']):
                                echo '<a href="mailto:' . htmlspecialchars($cf['field_value']) . '">' . htmlspecialchars($cf['field_value']) . '</a>';
                            elseif ($cf['field_type'] === 'tel' && $cf['field_value']):
                                echo '<a href="tel:' . htmlspecialchars($cf['field_value']) . '">' . htmlspecialchars($cf['field_value']) . '</a>';
                            elseif ($cf['field_type'] === 'checkbox'):
                                echo $cf['field_value'] ? '<span class="badge badge-success">' . htmlspecialchars(__('Yes')) . '</span>' : '<span class="badge badge-info">' . htmlspecialchars(__('No')) . '</span>';
                            else:
                                echo nl2br(htmlspecialchars($cf['field_value']));
                            endif;
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
        
    <!-- Social Media -->
    <?php if ($lead['facebook_url'] || $lead['instagram_url'] || $lead['linkedin_url'] || $lead['twitter_url'] || $lead['youtube_url']): ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Social Media')); ?></h3></div>
        <div class="card-body">
            <div class="social-links-detail">
                <?php if ($lead['facebook_url']): ?>
                    <a href="<?php echo htmlspecialchars($lead['facebook_url']); ?>" target="_blank" class="social-link-btn facebook"><?php echo htmlspecialchars(__('Facebook')); ?></a>
                <?php endif; ?>
                <?php if ($lead['instagram_url']): ?>
                    <a href="<?php echo htmlspecialchars($lead['instagram_url']); ?>" target="_blank" class="social-link-btn instagram"><?php echo htmlspecialchars(__('Instagram')); ?></a>
                <?php endif; ?>
                <?php if ($lead['linkedin_url']): ?>
                    <a href="<?php echo htmlspecialchars($lead['linkedin_url']); ?>" target="_blank" class="social-link-btn linkedin"><?php echo htmlspecialchars(__('LinkedIn')); ?></a>
                <?php endif; ?>
                <?php if ($lead['twitter_url']): ?>
                    <a href="<?php echo htmlspecialchars($lead['twitter_url']); ?>" target="_blank" class="social-link-btn twitter"><?php echo htmlspecialchars(__('Twitter')); ?></a>
                <?php endif; ?>
                <?php if ($lead['youtube_url']): ?>
                    <a href="<?php echo htmlspecialchars($lead['youtube_url']); ?>" target="_blank" class="social-link-btn youtube"><?php echo htmlspecialchars(__('YouTube')); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
        
    <!-- Notes -->
    <?php if ($lead['notes']): ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Notes')); ?></h3></div>
        <div class="card-body"><p><?php echo nl2br(htmlspecialchars($lead['notes'])); ?></p></div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- Email History -->
    <!-- ================================================================ -->
    <div class="card" id="email-history-card">
        <div class="card-header section-toggle-header" onclick="toggleSection('email-history-body', 'email-history-chevron')">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e63946" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <?php echo htmlspecialchars(__('Email History')); ?>
                <span class="badge badge-danger"><?php echo count($emailInteractions); ?></span>
            </h3>
            <div class="card-actions" style="display:flex;align-items:center;gap:8px;">
                <?php if ($lead['email']): ?>
                    <button class="btn btn-sm" style="background:linear-gradient(135deg,#e63946,#c62828);color:#fff;border:none;" onclick="event.stopPropagation();openComposeEmail()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <?php echo htmlspecialchars(__('New Email')); ?>
                    </button>
                <?php endif; ?>
                <svg id="email-history-chevron" class="section-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>
        <div class="card-body" id="email-history-body">
            <?php if (empty($emailInteractions)): ?>
                <div class="comm-empty">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#e63946" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <p><?php echo htmlspecialchars(__('No emails sent yet')); ?></p>
                    <p class="comm-empty-sub"><?php echo htmlspecialchars(__('Click "New Email" to compose and send an email')); ?></p>
                </div>
            <?php else: ?>
                <div class="email-history-list">
                    <?php foreach ($emailInteractions as $email): ?>
                        <?php
                            // Parse notes to extract To, Cc, and body
                            $notes = $email['notes'] ?? '';
                            $emailTo = '';
                            $emailCc = '';
                            $emailBodyText = $notes;
                            $lines = explode("\n", $notes);
                            if (count($lines) >= 1 && strpos($lines[0], 'To:') === 0) {
                                $emailTo = trim(substr($lines[0], 3));
                                if (isset($lines[1]) && strpos($lines[1], 'Cc:') === 0) {
                                    $emailCc = trim(substr($lines[1], 3));
                                    $emailBodyText = implode("\n", array_slice($lines, 3));
                                } else {
                                    $emailBodyText = implode("\n", array_slice($lines, 2));
                                }
                            }
                            $emailBodyText = trim($emailBodyText);
                            $previewText = mb_strlen($emailBodyText) > 120 ? mb_substr($emailBodyText, 0, 120) . '...' : $emailBodyText;
                        ?>
                        <div class="email-history-item" onclick="toggleEmailDetail('email-detail-<?php echo $email['interaction_id']; ?>', this)">
                            <div class="email-history-header">
                                <div class="email-history-left">
                                    <div class="email-history-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e63946" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                    </div>
                                    <div>
                                        <div class="email-history-subject"><?php echo htmlspecialchars($email['subject'] ?: __('(No subject)')); ?></div>
                                        <div class="email-history-meta">
                                            <?php echo htmlspecialchars(__('To:')); ?> <?php echo htmlspecialchars($emailTo); ?>
                                            <?php if ($emailCc): ?> &middot; <?php echo htmlspecialchars(__('Cc:')); ?> <?php echo htmlspecialchars($emailCc); ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="email-history-right">
                                    <span class="email-history-date"><?php echo date('M d, Y H:i', strtotime($email['interaction_date'])); ?></span>
                                    <svg class="section-chevron email-expand-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div class="email-history-preview"><?php echo htmlspecialchars($previewText); ?></div>
                            <div class="email-history-detail" id="email-detail-<?php echo $email['interaction_id']; ?>" style="display:none;">
                                <div class="email-detail-body"><?php echo nl2br(htmlspecialchars($emailBodyText)); ?></div>
                                <div class="email-detail-footer">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?php echo htmlspecialchars(__('Sent by')); ?> <?php echo htmlspecialchars($email['user_name'] ?? __('Unknown')); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- WhatsApp Conversation History (Inline) -->
    <!-- ================================================================ -->
    <div class="card" id="whatsapp-history-card">
        <div class="card-header section-toggle-header" onclick="toggleSection('wa-history-body', 'wa-history-chevron')">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#25D366" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <?php echo htmlspecialchars(__('WhatsApp Conversation')); ?>
                <span class="badge badge-success"><?php echo count($waMessages); ?></span>
            </h3>
            <div class="card-actions" style="display:flex;align-items:center;gap:8px;">
                <?php if ($lead['phone'] || $lead['mobile']): ?>
                    <?php $waNumber = $lead['phone'] ?: $lead['mobile']; ?>
                    <button class="btn btn-sm" style="background:#25D366;color:#fff;border:none;" onclick="event.stopPropagation();WhatsAppChat.open(<?php echo $leadId; ?>, '<?php echo htmlspecialchars($waNumber); ?>', '<?php echo htmlspecialchars($lead['contact_person'] ?: $lead['company_name']); ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        <?php echo htmlspecialchars(__('New Message')); ?>
                    </button>
                <?php endif; ?>
                <svg id="wa-history-chevron" class="section-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>
        <div class="card-body" id="wa-history-body">
            <?php if (empty($waMessages)): ?>
                <div class="comm-empty">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#25D366" stroke-width="1.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <p><?php echo htmlspecialchars(__('No WhatsApp messages yet')); ?></p>
                    <p class="comm-empty-sub"><?php echo htmlspecialchars(__('Click "New Message" to start a conversation')); ?></p>
                </div>
            <?php else: ?>
                <div class="lead-wa-chat" id="wa-chat-scroll">
                    <?php foreach ($waMessages as $msg): ?>
                        <?php $isOut = ($msg['direction'] === 'Outbound'); ?>
                        <div class="wa-msg-row <?php echo $isOut ? 'outbound' : 'inbound'; ?>">
                            <div class="wa-msg-bubble">
                                <?php if (!$isOut): ?>
                                    <div class="wa-msg-sender"><?php echo htmlspecialchars($msg['from_number']); ?></div>
                                <?php else: ?>
                                    <div class="wa-msg-sender" style="color: #0071e3;"><?php echo htmlspecialchars(__('CRM Agent')); ?></div>
                                <?php endif; ?>
                                <div class="wa-text"><?php echo nl2br(htmlspecialchars($msg['message_body'] ?? '')); ?></div>
                                <div class="wa-msg-footer">
                                    <span class="wa-msg-time"><?php echo date('M d, H:i', strtotime($msg['sent_at'] ?? $msg['created_at'])); ?></span>
                                    <?php if ($isOut): ?>
                                        <?php 
                                            $statusClass = strtolower($msg['status'] ?? 'sent');
                                            $statusIcon = match($msg['status']) {
                                                'Delivered' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
                                                'Read' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 6 7 17 2 12"/><polyline points="22 6 11 17 8 14"/></svg>',
                                                'Failed' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                                                default => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>'
                                            };
                                        ?>
                                        <span class="wa-msg-status <?php echo $statusClass; ?>" title="<?php echo htmlspecialchars(__($msg['status'])); ?>">
                                            <?php echo $statusIcon; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="wa-msg-status received" style="color:#25D366;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/><polyline points="15 10 11 14 9 12"/></svg>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VoIP Call History — Card-based layout (not cramped table) -->
    <!-- ================================================================ -->
    <div class="card" id="voip-history-card">
        <div class="card-header section-toggle-header" onclick="toggleSection('voip-history-body', 'voip-history-chevron')">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0071e3" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <?php echo htmlspecialchars(__('VoIP Call History')); ?>
                <span class="badge badge-info"><?php echo count($voipCalls); ?></span>
            </h3>
            <div class="card-actions" style="display:flex;align-items:center;gap:8px;">
                <?php if ($lead['phone'] || $lead['mobile']): ?>
                    <?php $callNumber = $lead['phone'] ?: $lead['mobile']; ?>
                    <button class="btn btn-sm btn-primary" onclick="event.stopPropagation();VoIPPhone.call('<?php echo htmlspecialchars($callNumber); ?>', <?php echo $leadId; ?>)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <?php echo htmlspecialchars(__('New Call')); ?>
                    </button>
                <?php endif; ?>
                <svg id="voip-history-chevron" class="section-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>
        <div class="card-body" id="voip-history-body">
            <?php if (empty($voipCalls)): ?>
                <div class="comm-empty">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0071e3" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <p><?php echo htmlspecialchars(__('No VoIP calls recorded')); ?></p>
                    <p class="comm-empty-sub"><?php echo htmlspecialchars(__('Click "New Call" to initiate a call')); ?></p>
                </div>
            <?php else: ?>
                <div class="voip-call-list">
                    <?php foreach ($voipCalls as $call): ?>
                        <?php
                            $isOutbound = ($call['direction'] ?? 'Outbound') === 'Outbound';
                            $dirClass = $isOutbound ? 'outbound' : 'inbound';
                            $dur = intval($call['duration_seconds'] ?? 0);
                            $durationStr = $dur > 0 ? sprintf('%d:%02d', floor($dur/60), $dur%60) : '--:--';
                            $statusLower = strtolower(str_replace(' ', '-', $call['status'] ?? 'initiated'));
                        ?>
                        <div class="voip-call-card">
                            <div class="voip-call-icon <?php echo $dirClass; ?>">
                                <?php if ($isOutbound): ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/><polyline points="17 2 22 2 22 7"/><line x1="22" y1="2" x2="15" y2="9"/></svg>
                                <?php else: ?>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/><polyline points="15 2 10 2 10 7"/><line x1="10" y1="2" x2="17" y2="9"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="voip-call-info">
                                <div class="voip-call-top">
                                    <span class="voip-call-number"><?php echo htmlspecialchars($call['to_number'] ?? $call['from_number'] ?? 'Unknown'); ?></span>
                                    <span class="voip-call-direction <?php echo $dirClass; ?>"><?php echo htmlspecialchars(__($call['direction'] ?? 'Outbound')); ?></span>
                                    <span class="voip-call-status <?php echo $statusLower; ?>"><?php echo htmlspecialchars(__($call['status'])); ?></span>
                                </div>
                                <div class="voip-call-details">
                                    <span class="voip-call-detail-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <?php echo $durationStr; ?>
                                    </span>
                                    <?php if (!empty($call['outcome'])): ?>
                                    <span class="voip-call-detail-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                        <?php echo htmlspecialchars(__($call['outcome'])); ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="voip-call-detail-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                        <?php echo date('M d, Y  H:i', strtotime($call['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="voip-call-meta">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?php echo htmlspecialchars($call['user_name'] ?? __('System')); ?>
                                </div>
                                <?php if (!empty($call['notes'])): ?>
                                    <div class="voip-call-notes">
                                        <?php echo nl2br(htmlspecialchars($call['notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
        
    <!-- ================================================================ -->
    <!-- Recent Interactions (Expandable) -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header section-toggle-header" onclick="toggleSection('interactions-body', 'interactions-chevron')">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <?php echo htmlspecialchars(__('Recent Interactions')); ?>
                <span class="badge badge-secondary"><?php echo count($interactions); ?></span>
            </h3>
            <div class="card-actions" style="display:flex;align-items:center;gap:8px;">
                <a href="interactions.php?lead_id=<?php echo $leadId; ?>" class="btn btn-sm btn-primary" onclick="event.stopPropagation()"><?php echo htmlspecialchars(__('Log Interaction')); ?></a>
                <svg id="interactions-chevron" class="section-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
        </div>
        <div class="card-body" id="interactions-body">
            <?php if (empty($interactions)): ?>
                <p class="text-muted text-center"><?php echo htmlspecialchars(__('No interactions recorded yet')); ?></p>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($interactions as $idx => $interaction): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="timeline-header interaction-expand-btn" onclick="toggleInteractionDetail(<?php echo $idx; ?>)">
                                    <span class="badge badge-<?php echo strtolower($interaction['interaction_type'] ?? 'note'); ?>">
                                        <?php echo htmlspecialchars(__($interaction['interaction_type'])); ?>
                                    </span>
                                    <span class="timeline-date"><?php echo date('M d, Y H:i', strtotime($interaction['interaction_date'])); ?></span>
                                    <svg class="section-chevron" id="interaction-toggle-<?php echo $idx; ?>" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:8px;"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                                <div class="timeline-subject"><?php echo htmlspecialchars($interaction['subject']); ?></div>
                                <div class="interaction-detail-panel" id="interaction-detail-<?php echo $idx; ?>" style="display:none;">
                                    <?php if ($interaction['notes']): ?>
                                        <div style="margin-bottom:8px;">
                                            <div class="interaction-detail-label"><?php echo htmlspecialchars(__('Notes')); ?></div>
                                            <div><?php echo nl2br(htmlspecialchars($interaction['notes'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($interaction['outcome'] ?? null): ?>
                                        <div style="margin-bottom:8px;">
                                            <div class="interaction-detail-label"><?php echo htmlspecialchars(__('Outcome')); ?></div>
                                            <div><?php echo htmlspecialchars(__($interaction['outcome'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size:12px;color:var(--color-text-tertiary);margin-top:6px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        <?php echo htmlspecialchars($interaction['user_name']); ?>
                                    </div>
                                </div>
                                <div class="timeline-footer">
                                    <?php echo htmlspecialchars($interaction['user_name']); ?>
                                    <?php if ($isManager): ?>
                                    <form method="POST" style="display:inline;margin:0;margin-left:8px;" onsubmit="return confirm('<?php echo htmlspecialchars(__('Delete this interaction?'), ENT_QUOTES); ?>');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete_interaction">
                                        <input type="hidden" name="interaction_id" value="<?php echo $interaction['interaction_id']; ?>">
                                        <button type="submit" class="btn-icon-delete" title="<?php echo htmlspecialchars(__('Delete interaction'), ENT_QUOTES); ?>">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
    
<!-- Right Column - Sidebar (1/3 width) -->
<div>
    <!-- Lead Management -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Lead Management')); ?></h3></div>
        <div class="card-body">
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Assigned To')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($lead['assigned_to_name'] ? $lead['assigned_to_name'] : __('Unassigned')); ?></div></div>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Created By')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($lead['created_by_name'] ? $lead['created_by_name'] : __('Unknown')); ?></div></div>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Lead Source')); ?></div><div class="sidebar-value"><?php echo htmlspecialchars($lead['lead_source'] ? __($lead['lead_source']) : __('N/A')); ?></div></div>
            <?php
            $hasUtm = !empty($lead['utm_source']) || !empty($lead['utm_campaign']) || !empty($lead['utm_medium']) || !empty($lead['utm_content']) || !empty($lead['utm_term']);
            if ($hasUtm): ?>
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--color-border);">
                <div class="sidebar-label" style="margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <strong><?php echo htmlspecialchars(__('UTM Tracking')); ?></strong>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <?php
                    $utmBadges = [
                        'utm_source'   => ['Source',   '#0ea5e9'],
                        'utm_campaign' => ['Campaign', '#8b5cf6'],
                        'utm_medium'   => ['Medium',   '#10b981'],
                        'utm_content'  => ['Content',  '#f59e0b'],
                        'utm_term'     => ['Term',     '#ec4899'],
                    ];
                    foreach ($utmBadges as $field => $meta):
                        if (!empty($lead[$field])):
                            [$label, $color] = $meta;
                    ?>
                    <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
                        <span style="display:inline-block;min-width:60px;padding:2px 6px;background:<?= $color ?>1a;color:<?= $color ?>;border-radius:4px;font-weight:600;font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;text-align:center;"><?= htmlspecialchars($label) ?></span>
                        <span style="color:var(--color-text);font-weight:500;word-break:break-all;"><?= htmlspecialchars($lead[$field]) ?></span>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
                <?php if (!empty($lead['landing_page'])): ?>
                <div style="margin-top:8px;font-size:11px;color:var(--color-text-tertiary);word-break:break-all;">
                    <strong style="color:var(--color-text-secondary);"><?= htmlspecialchars(__('Landing')) ?>:</strong>
                    <a href="<?= htmlspecialchars($lead['landing_page']) ?>" target="_blank" rel="noopener" style="color:var(--color-primary);text-decoration:none;border-bottom:1px dotted var(--color-primary);"><?= htmlspecialchars(mb_strimwidth($lead['landing_page'], 0, 70, '…')) ?></a>
                </div>
                <?php endif; ?>
                <?php if (!empty($lead['referrer'])): ?>
                <div style="margin-top:4px;font-size:11px;color:var(--color-text-tertiary);word-break:break-all;">
                    <strong style="color:var(--color-text-secondary);"><?= htmlspecialchars(__('Referrer')) ?>:</strong>
                    <span><?= htmlspecialchars(mb_strimwidth($lead['referrer'], 0, 70, '…')) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Created')); ?></div><div class="sidebar-value"><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></div></div>
            <div class="sidebar-item"><div class="sidebar-label"><?php echo htmlspecialchars(__('Updated')); ?></div><div class="sidebar-value"><?php echo date('M d, Y', strtotime($lead['updated_at'])); ?></div></div>
        </div>
    </div>
        
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Quick Actions')); ?></h3></div>
        <div class="card-body quick-actions">
            <?php if ($lead['phone'] || $lead['mobile']): ?>
                <?php $callNumber = $lead['phone'] ?: $lead['mobile']; ?>
                <button class="btn btn-block" style="background:linear-gradient(135deg,#0071e3,#5856d6);color:#fff;border:none;box-shadow:0 2px 8px rgba(0,113,227,0.2);" onclick="VoIPPhone.call('<?php echo htmlspecialchars($callNumber); ?>', <?php echo $leadId; ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <?php echo htmlspecialchars(__('VoIP Call')); ?>
                </button>
                <button class="btn btn-block" style="background:linear-gradient(135deg,#25D366,#128C7E);color:#fff;border:none;box-shadow:0 2px 8px rgba(37,211,102,0.2);" onclick="WhatsAppChat.open(<?php echo $leadId; ?>, '<?php echo htmlspecialchars($callNumber); ?>', '<?php echo htmlspecialchars($lead['contact_person'] ?: $lead['company_name']); ?>')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <?php echo htmlspecialchars(__('WhatsApp Message')); ?>
                </button>
            <?php endif; ?>
            <?php if ($lead['email']): ?>
            <button type="button" onclick="openComposeEmail()" class="btn btn-block" style="background:linear-gradient(135deg,#e63946,#c62828) !important;color:#fff !important;border:none;box-shadow:0 2px 8px rgba(230,57,70,0.25);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <?php echo htmlspecialchars(__('Send Email')); ?>
            </button>
            <?php endif; ?>
            <a href="interactions.php?lead_id=<?php echo $leadId; ?>" class="btn btn-block btn-info"><?php echo htmlspecialchars(__('Log Interaction')); ?></a>
            <a href="interactions.php?lead_id=<?php echo $leadId; ?>&type=meeting" class="btn btn-block btn-success"><?php echo htmlspecialchars(__('Schedule Meeting')); ?></a>
            <a href="lead-form.php?id=<?php echo $leadId; ?>" class="btn btn-block btn-warning"><?php echo htmlspecialchars(__('Edit Lead')); ?></a>
        </div>
    </div>
        
    <!-- Documents -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Documents')); ?></h3></div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <p class="text-muted text-center"><?php echo htmlspecialchars(__('No documents uploaded')); ?></p>
            <?php else: ?>
                <div class="document-list">
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-accent)" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <div class="document-info">
                                <div class="document-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                                <div class="document-meta"><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
        
    <!-- Activity Log -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars(__('Activity Log')); ?></h3></div>
        <div class="card-body activity-log-scroll">
            <?php if (empty($activities)): ?>
                <p class="text-muted text-center"><?php echo htmlspecialchars(__('No activities recorded')); ?></p>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-action"><?php echo htmlspecialchars(__($activity['action'])); ?></div>
                            <div class="activity-meta"><?php echo htmlspecialchars($activity['user_name']); ?> &middot; <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Compose Email Modal -->
<div id="composeEmailModal" class="modal" style="display:none;">
<div class="modal-backdrop" onclick="closeComposeEmail()"></div>
<div class="modal-content modal-lg">
    <div class="modal-header" style="background:linear-gradient(135deg,#e63946,#c62828);color:#fff;">
        <h3 style="display:flex;align-items:center;gap:8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <?php echo htmlspecialchars(__('Compose Email')); ?>
        </h3>
        <button type="button" class="btn-close" onclick="closeComposeEmail()" style="color:#fff;filter:brightness(2);">&times;</button>
    </div>
    <form id="composeEmailForm" onsubmit="sendEmail(event)">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('To')); ?></label>
                <input type="email" id="emailTo" class="form-control" value="<?php echo htmlspecialchars($lead['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Cc')); ?> <small class="text-muted"><?php echo htmlspecialchars(__('(optional, comma-separated)')); ?></small></label>
                <input type="text" id="emailCc" class="form-control" placeholder="cc1@example.com, cc2@example.com">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Message')); ?> <span class="required">*</span></label>
                <textarea id="emailBody" class="form-control" rows="10" required placeholder="<?php echo htmlspecialchars(__('Write your email message here...')); ?>" style="min-height:180px;"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    <?php echo htmlspecialchars(__('Attachments')); ?> <small class="text-muted"><?php echo htmlspecialchars(__('(optional, max 10MB each)')); ?></small>
                </label>
                <input type="file" id="emailAttachments" class="form-control" multiple style="padding:8px;">
                <div id="attachmentList" style="margin-top:6px;font-size:12px;color:var(--color-text-secondary);"></div>
            </div>
            <div id="emailError" class="alert alert-error" style="display:none;"></div>
            <div id="emailSuccess" class="alert alert-success" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeComposeEmail()"><?php echo htmlspecialchars(__('Cancel')); ?></button>
            <button type="submit" id="emailSendBtn" class="btn" style="background:linear-gradient(135deg,#e63946,#c62828);color:#fff;border:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <?php echo htmlspecialchars(__('Send Email')); ?>
            </button>
        </div>
    </form>
</div>
</div>

<script>
/** Toggle collapsible sections */
function toggleSection(bodyId, chevronId) {
const body = document.getElementById(bodyId);
if (!body) return;
const isHidden = body.style.display === 'none';
body.style.display = isHidden ? 'block' : 'none';
const chevron = document.getElementById(chevronId);
if (chevron) {
    chevron.classList.toggle('collapsed', !isHidden);
}
}

/** Toggle email history detail panel */
function toggleEmailDetail(detailId, row) {
var detail = document.getElementById(detailId);
if (!detail) return;
var isHidden = detail.style.display === 'none';
// Collapse all other open email details first
document.querySelectorAll('.email-history-detail').forEach(function(el) {
    el.style.display = 'none';
    el.closest('.email-history-item').classList.remove('expanded');
});
if (isHidden) {
    detail.style.display = 'block';
    row.classList.add('expanded');
}
}

/** Toggle individual interaction detail */
function toggleInteractionDetail(idx) {
const detail = document.getElementById('interaction-detail-' + idx);
const toggle = document.getElementById('interaction-toggle-' + idx);
if (!detail) return;
const isHidden = detail.style.display === 'none';
detail.style.display = isHidden ? 'block' : 'none';
if (toggle) {
    toggle.style.transform = isHidden ? 'rotate(180deg)' : '';
}
}

// Auto-scroll WhatsApp chat to bottom
document.addEventListener('DOMContentLoaded', function() {
const waChat = document.getElementById('wa-chat-scroll');
if (waChat) waChat.scrollTop = waChat.scrollHeight;
});

/** Compose Email modal functions */
function openComposeEmail() {
document.getElementById('composeEmailModal').style.display = 'flex';
document.getElementById('emailSubject').focus();
var errDiv = document.getElementById('emailError');
var successDiv = document.getElementById('emailSuccess');
if (errDiv) errDiv.style.display = 'none';
if (successDiv) successDiv.style.display = 'none';
document.getElementById('emailSendBtn').disabled = false;
document.getElementById('emailSendBtn').innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Email';
// Reset attachments
document.getElementById('emailAttachments').value = '';
document.getElementById('attachmentList').innerHTML = '';
}

function closeComposeEmail() {
document.getElementById('composeEmailModal').style.display = 'none';
}

// Show selected file names
document.addEventListener('DOMContentLoaded', function() {
var fileInput = document.getElementById('emailAttachments');
if (fileInput) {
    fileInput.addEventListener('change', function() {
        var list = document.getElementById('attachmentList');
        if (!this.files.length) { list.innerHTML = ''; return; }
        var html = '';
        var totalSize = 0;
        for (var i = 0; i < this.files.length; i++) {
            var f = this.files[i];
            totalSize += f.size;
            var sizeMB = (f.size / 1048576).toFixed(1);
            html += '<div style="display:flex;align-items:center;gap:6px;padding:3px 0;">'
                + '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'
                + '<span>' + f.name + ' (' + sizeMB + ' MB)</span></div>';
        }
        if (totalSize > 25 * 1048576) {
            html += '<div style="color:var(--color-danger);font-weight:600;margin-top:4px;">' + __('Total size exceeds 25MB limit') + '</div>';
        }
        list.innerHTML = html;
    });
}
});

function sendEmail(e) {
e.preventDefault();
var btn = document.getElementById('emailSendBtn');
var errDiv = document.getElementById('emailError');
var successDiv = document.getElementById('emailSuccess');

// Re-create alert divs if they were removed by auto-dismiss
if (!errDiv) {
    errDiv = document.createElement('div');
    errDiv.id = 'emailError';
    errDiv.className = 'alert alert-error';
    errDiv.style.display = 'none';
    document.querySelector('#composeEmailForm .modal-body').appendChild(errDiv);
}
if (!successDiv) {
    successDiv = document.createElement('div');
    successDiv.id = 'emailSuccess';
    successDiv.className = 'alert alert-success';
    successDiv.style.display = 'none';
    document.querySelector('#composeEmailForm .modal-body').appendChild(successDiv);
}

errDiv.style.display = 'none';
successDiv.style.display = 'none';
btn.disabled = true;
btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Sending...';

// Use FormData to support file attachments
var formData = new FormData();
formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ""; ?>');
formData.append('lead_id', <?php echo $leadId; ?>);
formData.append('to', document.getElementById('emailTo').value);
formData.append('cc', document.getElementById('emailCc').value);
formData.append('subject', document.getElementById('emailSubject').value);
formData.append('body', document.getElementById('emailBody').value);

var fileInput = document.getElementById('emailAttachments');
if (fileInput && fileInput.files.length > 0) {
    for (var i = 0; i < fileInput.files.length; i++) {
        formData.append('attachments[]', fileInput.files[i]);
    }
}

fetch('/api/send-email.php', {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
})
.then(function(r) {
    if (!r.ok) {
        return r.text().then(function(txt) {
            try { return JSON.parse(txt); } catch(e) { throw new Error('Server error (' + r.status + '): ' + txt.substring(0, 200)); }
        });
    }
    return r.json();
})
.then(function(data) {
    if (data.success) {
        successDiv.textContent = 'Email sent successfully! It has been logged as an interaction.';
        successDiv.style.display = 'block';
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg> Sent!';
        setTimeout(function() { location.reload(); }, 2000);
    } else {
        errDiv.textContent = data.message || 'Failed to send email';
        errDiv.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Email';
    }
})
.catch(function(err) {
    errDiv.textContent = err.message || 'Network error. Please try again.';
    errDiv.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Email';
});
}

// ── Convert Lead to Contact ──
function moveLeadToContact(leadId) {
showConfirm('Convert this lead to a Contact? It will be removed from leads and appear in contacts.', function() {
    fetch('/api/leads.php?action=move_to_contact', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lead_id: leadId, csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ""; ?>' })
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.success) {
            showNotification('Lead converted to contact successfully!', 'success');
            setTimeout(function() { window.location.href = '/pages/contacts.php'; }, 1500);
        } else {
            showNotification(resp.message || 'Conversion failed', 'error');
        }
    })
    .catch(function() { showNotification('Network error', 'error'); });
});
}

// Close modal on Escape
document.addEventListener('keydown', function(e) {
if (e.key === 'Escape') closeComposeEmail();
});
</script>

<?php include '../includes/footer.php'; ?>
