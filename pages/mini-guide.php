<?php
/**
 * Mini Guide — Step-by-step onboarding journey through the CRM
 * 
 * Shows the complete user journey from setup to closing deals, with
 * interactive step cards, progress tracking, and links to each feature.
 * 
 * @author Izzy (AI Assistant)
 * @lastupdated 2026-06-23
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

$pageTitle = 'Mini Guide';
$currentPage = 'guide';
include __DIR__ . '/../includes/header.php';

// Define the complete CRM journey as step-by-step phases
$phases = [
    [
        'id' => 'setup',
        'icon' => '⚙️',
        'color' => '#3b82f6',
        'title' => 'Phase 1: Setup & Configuration',
        'subtitle' => 'Get your CRM ready in minutes',
        'steps' => [
            ['Configure Company Profile', 'Go to Settings → Company Profile. Enter your company name, email, phone, website, and address. This information appears on proposals and emails.', '/pages/settings.php'],
            ['Customize Branding', 'Upload your company logo and set a custom app name in Settings → App Branding. This personalizes the CRM for your team.', '/pages/settings.php'],
            ['Set Up Email (SMTP)', 'Configure SMTP in Settings → SMTP & Email so the CRM can send campaigns and notifications. Enter your SMTP host, port, username, and password.', '/pages/settings.php'],
            ['Add Team Members', 'Go to Users → Add User. Enter their name, email, and assign a role (Sales Rep, Sales Manager, Admin). They\'ll get a verification email.', '/pages/users.php'],
            ['Create Custom Fields', 'In Settings → Custom Lead Fields, add any extra fields you need (e.g., industry, budget, preferred contact time).', '/pages/settings.php'],
        ],
    ],
    [
        'id' => 'leads',
        'icon' => '📊',
        'color' => '#8b5cf6',
        'title' => 'Phase 2: Capture Leads',
        'subtitle' => 'Start filling your pipeline',
        'steps' => [
            ['Add a Lead Manually', 'Click Leads → Add New Lead. Fill in company name, contact person, email, phone (use the country code picker), country, and lead source.', '/pages/lead-form.php'],
            ['Import Leads in Bulk', 'Go to Export → Import tab. Download the CSV template, fill it with your leads, and upload. Include Name, Phone, and Email for best results.', '/pages/export.php'],
            ['Create a Web Form', 'In Web Forms, create a form and embed it on your website. Form submissions automatically create leads in your CRM.', '/pages/webforms.php'],
            ['Assign Leads', 'Open any lead and use the "Assigned To" dropdown to assign it to a team member. Use bulk assign for multiple leads.', '/pages/leads.php'],
        ],
    ],
    [
        'id' => 'engage',
        'icon' => '💬',
        'color' => '#06b6d4',
        'title' => 'Phase 3: Engage & Qualify',
        'subtitle' => 'Turn leads into qualified prospects',
        'steps' => [
            ['Log Interactions', 'Open a lead and click "Add Interaction". Log calls, emails, meetings, and follow-ups. This creates a complete communication history.', '/pages/interactions.php'],
            ['Use VoIP Calling', 'Configure Twilio in Settings, then use the VoIP Dashboard to make calls directly from the CRM. Calls are logged automatically.', '/pages/voip-dashboard.php'],
            ['Chat on WhatsApp', 'Connect WhatsApp in Settings, then use the WhatsApp Dashboard to message leads. Link incoming messages to leads.', '/pages/whatsapp-dashboard.php'],
            ['Update Lead Status', 'Move leads through statuses: New Lead → Contacted → Interested → Demo Scheduled → Proposal Sent → Won/Lost.', '/pages/leads.php'],
            ['Create Tasks', 'Assign follow-up tasks to team members with due dates and priorities. Track them in the Tasks page.', '/pages/tasks.php'],
        ],
    ],
    [
        'id' => 'convert',
        'icon' => '🎯',
        'color' => '#f59e0b',
        'title' => 'Phase 4: Convert & Close',
        'subtitle' => 'Move deals through the pipeline',
        'steps' => [
            ['Move to Contacts', 'Once a lead is qualified, click "Move to Contacts" to transfer their data to your contact database.', '/pages/leads.php'],
            ['Manage Pipeline', 'Go to Pipeline to see all deals by stage. Drag deals between stages to update their status.', '/pages/deals.php'],
            ['Create Proposals', 'In Proposals, create professional proposals with line items, taxes, and discounts. Send them to clients directly.', '/pages/proposals.php'],
            ['Send Quotes', 'Use Quotes to generate pricing quotes with PDF download. Share with clients for approval.', '/pages/quotes.php'],
            ['Manage Products', 'Add your products/services in the Products page. They\'re available as line items in proposals and quotes.', '/pages/products.php'],
        ],
    ],
    [
        'id' => 'market',
        'icon' => '📧',
        'color' => '#f97316',
        'title' => 'Phase 5: Email Marketing',
        'subtitle' => 'Reach leads at scale',
        'steps' => [
            ['Build Email Templates', 'In Templates, design reusable email templates with merge tags like {{first_name}} for personalization.', '/pages/email-templates.php'],
            ['Create Audience Lists', 'In Email Audiences, build lists from your leads/contacts filtered by status, source, or country.', '/pages/email-lists.php'],
            ['Launch a Campaign', 'In Email Campaigns, create a campaign, select a template, choose an audience, and schedule or send immediately.', '/pages/email-campaigns.php'],
            ['Track Results', 'Open the campaign report to see opens, clicks, bounces, and unsubscribes.', '/pages/email-campaigns.php'],
        ],
    ],
    [
        'id' => 'automate',
        'icon' => '🔄',
        'color' => '#a855f7',
        'title' => 'Phase 6: Automate & Optimize',
        'subtitle' => 'Work smarter, not harder',
        'steps' => [
            ['Create Automation Rules', 'In Automation, set up rules like "When a lead is created from Website, assign to round-robin and send welcome email."', '/pages/automation.php'],
            ['View Reports', 'Check Reports for lead conversion rates, sales by source, team performance, and revenue tracking.', '/pages/reports.php'],
            ['Export Your Data', 'Go to Export to download your leads, interactions, and call history as CSV files for backup or analysis.', '/pages/export.php'],
            ['Share Resources', 'In Knowledge Hub, add link cards to share guides, playbooks, and resources with your team.', '/pages/documents.php'],
        ],
    ],
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Mini Guide</h1>
        <p class="text-muted" style="margin-top:4px;">Your complete CRM journey — from setup to closing deals, step by step</p>
    </div>
</div>

<!-- Quick Start Banner -->
<div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border-radius:12px;padding:24px;margin-bottom:24px;color:white;">
    <div style="display:flex;align-items:center;gap:16px;">
        <div style="font-size:36px;">🚀</div>
        <div>
            <h3 style="margin:0 0 4px;font-size:18px;color:white;">Quick Start</h3>
            <p style="margin:0;font-size:14px;opacity:0.9;">Follow these 6 phases to get the most out of your CRM. Each phase has clear steps with direct links to the relevant pages.</p>
        </div>
    </div>
</div>

<!-- Progress Tracker -->
<div style="margin-bottom:24px;">
    <div style="display:flex;gap:8px;margin-bottom:8px;">
        <?php foreach ($phases as $phase): ?>
        <div style="flex:1;height:6px;border-radius:3px;background:linear-gradient(90deg, <?php echo $phase['color']; ?>, <?php echo $phase['color']; ?>88);" title="<?php echo htmlspecialchars($phase['title']); ?>"></div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;font-size:11px;color:var(--color-text-muted);">
        <?php foreach ($phases as $phase): ?>
        <div style="flex:1;text-align:center;"><?php echo $phase['icon']; ?> <?php echo htmlspecialchars(explode(':', $phase['title'])[0]); ?></div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Phase Cards -->
<?php foreach ($phases as $phase): ?>
<div style="margin-bottom:24px;">
    <!-- Phase Header -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <div style="width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;background:<?php echo $phase['color']; ?>15;flex-shrink:0;">
            <?php echo $phase['icon']; ?>
        </div>
        <div>
            <h2 style="font-size:18px;font-weight:600;margin:0;"><?php echo htmlspecialchars($phase['title']); ?></h2>
            <p style="font-size:13px;color:var(--color-text-muted);margin:2px 0 0;"><?php echo htmlspecialchars($phase['subtitle']); ?></p>
        </div>
    </div>

    <!-- Steps Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:12px;">
        <?php foreach ($phase['steps'] as $stepIdx => $step): 
            $stepNum = $stepIdx + 1;
        ?>
        <div style="border:1px solid var(--color-border);border-radius:10px;padding:16px;transition:all 0.2s;background:var(--color-bg);">
            <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:8px;">
                <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:white;background:<?php echo $phase['color']; ?>;flex-shrink:0;">
                    <?php echo $stepNum; ?>
                </div>
                <h4 style="font-size:14px;font-weight:600;margin:0;line-height:1.4;"><?php echo htmlspecialchars($step[0]); ?></h4>
            </div>
            <p style="font-size:13px;color:var(--color-text-secondary);line-height:1.6;margin:0 0 12px 40px;">
                <?php echo htmlspecialchars($step[1]); ?>
            </p>
            <div style="margin-left:40px;">
                <a href="<?php echo $step[2]; ?>" class="btn btn-sm btn-outline" style="font-size:12px;">
                    Go to page
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- Help Center Link -->
<div style="text-align:center;padding:24px;border:1px solid var(--color-border);border-radius:12px;background:var(--color-bg);">
    <p style="font-size:14px;color:var(--color-text-secondary);margin:0 0 12px;">Need more detailed guides and tutorials?</p>
    <a href="/pages/help.php" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Visit Help Center
    </a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>