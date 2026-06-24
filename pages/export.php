<?php
/**
 * White Label CRM - Data Export & Import Page
 * Export leads, interactions, WhatsApp messages, VoIP calls
 * Import leads from CSV / JSON
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

// Only admins and sales managers
if (!hasRole('Admin') && !hasRole('Sales Manager')) {
    $_SESSION['error'] = 'You do not have permission to access data import/export.';
    header('Location: /pages/dashboard.php');
    exit;
}

$db = Database::getInstance();
$companyId = getCurrentCompanyId();

// Get counts for display — scoped by tenant
$counts = [
    'leads'        => $db->prepare("SELECT COUNT(*) FROM leads WHERE company_id = ?"),
    'interactions'  => $db->prepare("SELECT COUNT(*) FROM interactions i JOIN leads l ON i.lead_id = l.lead_id WHERE l.company_id = ?"),
    'whatsapp'      => $db->prepare("SELECT COUNT(*) FROM whatsapp_messages wm JOIN leads l ON wm.lead_id = l.lead_id WHERE l.company_id = ?"),
    'voip'          => $db->prepare("SELECT COUNT(*) FROM voip_calls vc JOIN leads l ON vc.lead_id = l.lead_id WHERE l.company_id = ?"),
];
foreach ($counts as $key => $stmt) {
    $stmt->execute([$companyId]);
    $counts[$key] = $stmt->fetchColumn();
}

$pageTitle = 'Data Export';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo __('Data Import & Export'); ?></h1>
        <p class="text-muted"><?php echo __('Move data in and out of your CRM: export your leads, interactions, WhatsApp conversations, and VoIP call history, or import leads from a spreadsheet.'); ?></p>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;border-bottom:1px solid var(--color-border);margin-bottom:24px;">
    <button type="button" class="ie-tab" data-tab="export" style="padding:10px 20px;border:none;background:transparent;font-size:14px;font-weight:600;color:var(--color-text-secondary);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;" onclick="switchTab('export')">
        <span style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <?php echo __('Export'); ?>
        </span>
    </button>
    <button type="button" class="ie-tab" data-tab="import" style="padding:10px 20px;border:none;background:transparent;font-size:14px;font-weight:600;color:var(--color-text-secondary);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .15s;" onclick="switchTab('import')">
        <span style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <?php echo __('Import'); ?>
        </span>
    </button>
</div>

<!-- Export Tab -->
<div id="tab-export" class="ie-tab-pane">
<!-- Export Stats -->
<div class="grid grid-4 mb-2">
    <div class="stat-card">
        <div class="stat-icon bg-gradient-primary">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($counts['leads']); ?></div>
            <div class="stat-label"><?php echo __('Leads'); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-gradient-info">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($counts['interactions']); ?></div>
            <div class="stat-label"><?php echo __('Interactions'); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-gradient-success">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25D366" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($counts['whatsapp']); ?></div>
            <div class="stat-label"><?php echo __('WhatsApp Messages'); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-gradient-warning">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($counts['voip']); ?></div>
            <div class="stat-label"><?php echo __('VoIP Calls'); ?></div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <!-- Full Export -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?php echo __('Full Database Export'); ?>
            </h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom:16px;color:var(--color-text-secondary);font-size:13px;">
                <?php echo __('Export the entire CRM database including all leads, interactions, WhatsApp conversations, and VoIP call history in a single download.'); ?>
            </p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="/api/export.php?scope=all&format=csv" class="btn btn-primary" style="flex:1;text-align:center;min-width:140px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?php echo __('Download CSV Archive'); ?>
                </a>
                <a href="/api/export.php?scope=all&format=json" class="btn btn-outline" style="flex:1;text-align:center;min-width:140px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?php echo __('Download JSON'); ?>
                </a>
            </div>
            <div style="margin-top:12px;padding:10px;background:var(--color-bg-secondary);border-radius:8px;font-size:12px;color:var(--color-text-secondary);">
                <strong><?php echo __('CSV Archive'); ?></strong> <?php echo __('contains 4 separate CSV files: leads.csv, interactions.csv, whatsapp_messages.csv, voip_calls.csv — ideal for Excel/Google Sheets.'); ?><br>
                <strong>JSON</strong> <?php echo __('is a single file with all data — ideal for backups and programmatic use.'); ?>
            </div>
        </div>
    </div>

    <!-- Individual Exports -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                <?php echo __('Export by Section'); ?>
            </h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom:16px;color:var(--color-text-secondary);font-size:13px;">
                <?php echo __('Export individual sections of the CRM database.'); ?>
            </p>

            <!-- Leads -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--color-border);">
                <div>
                    <strong><?php echo __('Leads'); ?></strong>
                    <span class="badge badge-secondary" style="margin-left:6px;"><?php echo $counts['leads']; ?></span>
                    <div style="font-size:12px;color:var(--color-text-secondary);"><?php echo __('Company info, contacts, status, country'); ?></div>
                </div>
                <div style="display:flex;gap:6px;">
                    <a href="/api/export.php?scope=leads&format=csv" class="btn btn-sm btn-primary">CSV</a>
                    <a href="/api/export.php?scope=leads&format=json" class="btn btn-sm btn-outline">JSON</a>
                </div>
            </div>

            <!-- Interactions -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--color-border);">
                <div>
                    <strong><?php echo __('Interactions'); ?></strong>
                    <span class="badge badge-secondary" style="margin-left:6px;"><?php echo $counts['interactions']; ?></span>
                    <div style="font-size:12px;color:var(--color-text-secondary);"><?php echo __('Calls, meetings, emails, notes'); ?></div>
                </div>
                <div style="display:flex;gap:6px;">
                    <a href="/api/export.php?scope=interactions&format=csv" class="btn btn-sm btn-primary">CSV</a>
                    <a href="/api/export.php?scope=interactions&format=json" class="btn btn-sm btn-outline">JSON</a>
                </div>
            </div>

            <!-- WhatsApp -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--color-border);">
                <div>
                    <strong><?php echo __('WhatsApp Messages'); ?></strong>
                    <span class="badge badge-secondary" style="margin-left:6px;"><?php echo $counts['whatsapp']; ?></span>
                    <div style="font-size:12px;color:var(--color-text-secondary);"><?php echo __('Full conversation history with status'); ?></div>
                </div>
                <div style="display:flex;gap:6px;">
                    <a href="/api/export.php?scope=whatsapp&format=csv" class="btn btn-sm btn-primary">CSV</a>
                    <a href="/api/export.php?scope=whatsapp&format=json" class="btn btn-sm btn-outline">JSON</a>
                </div>
            </div>

            <!-- VoIP -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">
                <div>
                    <strong><?php echo __('VoIP Calls'); ?></strong>
                    <span class="badge badge-secondary" style="margin-left:6px;"><?php echo $counts['voip']; ?></span>
                    <div style="font-size:12px;color:var(--color-text-secondary);"><?php echo __('Call logs, duration, outcomes'); ?></div>
                </div>
                <div style="display:flex;gap:6px;">
                    <a href="/api/export.php?scope=voip&format=csv" class="btn btn-sm btn-primary">CSV</a>
                    <a href="/api/export.php?scope=voip&format=json" class="btn btn-sm btn-outline">JSON</a>
                </div>
            </div>
        </div>
    </div>
</div>
</div><!-- /tab-export -->

<!-- Import Tab -->
<div id="tab-import" class="ie-tab-pane" style="display:none;">
    <div class="grid grid-2" style="gap:16px;align-items:start;">
        <!-- Import Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <?php echo __('Import Leads from File'); ?>
                </h3>
            </div>
            <div class="card-body">
                <!-- Reminder for users about recommended fields (not mandatory) -->
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <div style="font-size:13px;color:#92400e;line-height:1.5;">
                        <strong>Reminder:</strong> For best results, include <strong>Name</strong> (contact_person), <strong>Phone</strong>, and <strong>Email</strong> columns in your file.
                        These are not mandatory, but having them ensures your leads have proper contact information.
                    </div>
                </div>
                
                <!-- Download Template Button -->
                <div style="margin-bottom:16px;">
                    <button type="button" class="btn btn-outline" onclick="downloadTemplate()" style="display:inline-flex;align-items:center;gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download CSV Template
                    </button>
                    <small style="display:block;color:var(--color-text-muted);font-size:11px;margin-top:4px;">Pre-formatted template with all supported columns and a sample row.</small>
                </div>
                
                <form id="importForm" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px;">
                    <input type="hidden" id="importCsrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <div>
                        <label class="form-label"><?php echo __('File (.csv or .json)'); ?></label>
                        <input type="file" id="importFile" name="file" accept=".csv,.json" required class="form-control" style="padding:8px;">
                        <small style="color:var(--color-text-muted);font-size:11px;"><?php echo __('Max 10 MB. CSV must have a header row. JSON must be an array of objects.'); ?></small>
                    </div>

                    <div>
                        <label class="form-label"><?php echo __('Duplicate handling'); ?></label>
                        <select id="importDupe" class="form-control">
                            <option value="skip"><?php echo __('Skip duplicates (match on email or phone)'); ?></option>
                            <option value="update"><?php echo __('Update existing leads (match on email or phone)'); ?></option>
                            <option value="create"><?php echo __('Always create new (allow duplicates)'); ?></option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label"><?php echo __('Default Lead Status'); ?></label>
                        <select id="importStatus" class="form-control">
                            <option value="New Lead">New Lead</option>
                            <option value="Contacted">Contacted</option>
                            <option value="Interested">Interested</option>
                            <option value="Schedule Call">Schedule Call</option>
                            <option value="Call Scheduled">Call Scheduled</option>
                            <option value="Demo Scheduled">Demo Scheduled</option>
                            <option value="Proposal Sent">Proposal Sent</option>
                            <option value="Negotiation">Negotiation</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label"><?php echo __('Default Lead Source (optional)'); ?></label>
                        <input type="text" id="importSource" class="form-control" list="leadSourceSuggestionsImport" placeholder="<?php echo __('e.g. CSV Import, Webform, Manual, etc.'); ?>" value="CSV Import">
                        <datalist id="leadSourceSuggestionsImport">
                            <option value="CSV Import">
                            <option value="Excel Import">
                            <option value="JSON Import">
                            <option value="Manual Entry">
                            <option value="Webform">
                            <option value="Migration">
                        </datalist>
                    </div>

                    <div style="display:flex;gap:8px;align-items:center;">
                        <button type="submit" id="importBtn" class="btn btn-primary" style="flex:1;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <?php echo __('Upload &amp; Import'); ?>
                        </button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('importFile').click()"><?php echo __('Choose File'); ?></button>
                    </div>
                </form>

                <div id="importProgress" style="display:none;margin-top:18px;padding:14px;background:var(--color-bg-secondary);border-radius:8px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <div class="spinner" style="width:16px;height:16px;border:2px solid var(--color-border);border-top-color:var(--color-primary);border-radius:50%;animation:spin 1s linear infinite;"></div>
                        <strong id="importProgressText"><?php echo __('Uploading & parsing...'); ?></strong>
                    </div>
                    <div style="background:#e5e7eb;height:8px;border-radius:4px;overflow:hidden;">
                        <div id="importProgressBar" style="background:var(--color-primary);height:100%;width:0%;transition:width .3s;"></div>
                    </div>
                </div>

                <div id="importResult" style="display:none;margin-top:18px;"></div>
            </div>
        </div>

        <!-- Import Help -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <?php echo __('How to Format Your File'); ?>
                </h3>
            </div>
            <div class="card-body">
                <p style="font-size:13px;color:var(--color-text-secondary);margin-bottom:14px;">
                    <?php echo __('The import accepts CSV (with header row) or JSON (array of objects). The header names below are recognized in any case:'); ?>
                </p>

                <h4 style="font-size:13px;margin:14px 0 8px;"><?php echo __('CSV Example'); ?></h4>
                <pre style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;font-size:11px;overflow-x:auto;line-height:1.6;">company_name,contact_person,email,phone,mobile,country,city,lead_source,lead_status,notes
Acme Corp,John Smith,john@acme.com,+1 555-1234,,USA,New York,Website,New Lead,Follow up
Globex,Jane Doe,jane@globex.com,,+44 20 7946,UK,London,Referral,Contacted,Met at SaaStr</pre>

                <h4 style="font-size:13px;margin:14px 0 8px;"><?php echo __('JSON Example'); ?></h4>
                <pre style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;font-size:11px;overflow-x:auto;line-height:1.6;">[
  {
    "company_name": "Acme Corp",
    "contact_person": "John Smith",
    "email": "john@acme.com",
    "phone": "+1 555-1234",
    "country": "USA",
    "lead_source": "Website"
  }
]</pre>

                <h4 style="font-size:13px;margin:14px 0 8px;"><?php echo __('Recognized Columns / Keys'); ?></h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;">
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">company_name</code> <span style="color:#94a3b8;">(required)</span></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">contact_person</code> <span style="color:#94a3b8;">(required)</span></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">email</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">phone</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">mobile</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">country</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">city</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">address</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">website</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">industry</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">lead_source</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">lead_status</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">priority</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">notes</code></div>
                    <div><code style="background:#f1f5f9;padding:1px 6px;border-radius:3px;">title_position</code></div>
                </div>
                <p style="font-size:12px;color:var(--color-text-muted);margin-top:14px;">
                    <?php echo __('Tip: download your existing leads as CSV first (Export tab) to see the exact column format the import expects.'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.ie-tab.active { color: var(--color-primary) !important; border-bottom-color: var(--color-primary) !important; }
</style>

<script>

/**
 * Generate and download a CSV template with all supported columns + sample row
 * Helps users format their import file correctly
 */
function downloadTemplate() {
    var headers = ["company_name","contact_person","email","phone","mobile","country","city","address","website","industry","lead_source","lead_status","priority","title_position","notes"];
    var sampleRow = ["Acme Corp","John Smith","john@acme.com","+1 5551234567","+1 5559876543","United States","New York","123 Main St","https://acme.com","Technology","Website","New Lead","Medium","CEO","Follow up next week"];
    var csv = headers.join(",") + "\n" + sampleRow.map(function(v) { return /\"/.test(v) ? """ + v.replace(/\"/g, """") + """ : """ + v + """; }).join(",");
    var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "leads_import_template.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function switchTab(name) {
    document.querySelectorAll('.ie-tab-pane').forEach(function(p) { p.style.display = 'none'; });
    document.querySelectorAll('.ie-tab').forEach(function(t) { t.classList.remove('active'); });
    var pane = document.getElementById('tab-' + name);
    var tab = document.querySelector('.ie-tab[data-tab="' + name + '"]');
    if (pane) pane.style.display = '';
    if (tab) tab.classList.add('active');
    // Persist selection in URL hash so refresh keeps the tab
    if (history.replaceState) history.replaceState(null, '', '#' + name);
    else window.location.hash = name;
}
// Activate tab from URL hash on load
(function() {
    var hash = window.location.hash.replace('#', '');
    if (hash === 'import' || hash === 'export') switchTab(hash);
    else document.querySelector('.ie-tab[data-tab="export"]')?.classList.add('active');
})();

// ── Import handler ──
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fileInput = document.getElementById('importFile');
    if (!fileInput.files || !fileInput.files[0]) {
        showNotification('Please choose a file first.', 'error');
        return;
    }
    var file = fileInput.files[0];
    if (file.size > 10 * 1024 * 1024) {
        showNotification('File too large (max 10 MB).', 'error');
        return;
    }
    var fd = new FormData();
    fd.append('file', file);
    fd.append('csrf_token', document.getElementById('importCsrf').value);
    fd.append('duplicate_mode', document.getElementById('importDupe').value);
    fd.append('default_status', document.getElementById('importStatus').value);
    fd.append('default_source', document.getElementById('importSource').value);

    var progressBox = document.getElementById('importProgress');
    var progressBar = document.getElementById('importProgressBar');
    var progressText = document.getElementById('importProgressText');
    var resultBox = document.getElementById('importResult');
    var btn = document.getElementById('importBtn');

    progressBox.style.display = '';
    progressBar.style.width = '5%';
    progressText.textContent = 'Uploading...';
    resultBox.style.display = 'none';
    btn.disabled = true;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/import.php?action=leads');
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            var pct = Math.round((e.loaded / e.total) * 50);
            progressBar.style.width = pct + '%';
        }
    });
    xhr.addEventListener('load', function() {
        try {
            var data = JSON.parse(xhr.responseText);
            progressBar.style.width = '100%';
            progressText.textContent = 'Done.';
            setTimeout(function() { progressBox.style.display = 'none'; }, 800);
            btn.disabled = false;

            if (data.success) {
                var msg = '<div style="padding:14px;background:#dcfce7;color:#166534;border-radius:8px;">' +
                    '<strong>✓ ' + (data.message || 'Import complete') + '</strong><br>' +
                    '<div style="margin-top:8px;font-size:13px;">' +
                    'Imported: ' + (data.data?.imported || 0) + '<br>' +
                    'Updated: ' + (data.data?.updated || 0) + '<br>' +
                    'Skipped (duplicates): ' + (data.data?.skipped || 0) + '<br>' +
                    (data.data?.errors?.length ? 'Errors: ' + data.data.errors.length + '<br>' : '') +
                    '</div></div>';
                if (data.data?.errors?.length) {
                    msg += '<details style="margin-top:10px;font-size:12px;color:var(--color-text-secondary);"><summary>View ' + data.data.errors.length + ' error(s)</summary><pre style="margin-top:6px;max-height:200px;overflow:auto;background:#fef2f2;padding:8px;border-radius:4px;">' +
                        data.data.errors.map(function(e){ return 'Row ' + e.row + ': ' + e.message; }).join('\n') + '</pre></details>';
                }
                resultBox.innerHTML = msg;
                resultBox.style.display = '';
                showNotification(data.message || 'Import complete', 'success');
            } else {
                resultBox.innerHTML = '<div style="padding:14px;background:#fee2e2;color:#dc2626;border-radius:8px;"><strong>✗ Import failed</strong><br>' + (data.message || 'Unknown error') + '</div>';
                resultBox.style.display = '';
                showNotification(data.message || 'Import failed', 'error');
            }
        } catch (err) {
            progressBox.style.display = 'none';
            btn.disabled = false;
            resultBox.innerHTML = '<div style="padding:14px;background:#fee2e2;color:#dc2626;border-radius:8px;">Server returned invalid response: ' + xhr.responseText.substring(0, 200) + '</div>';
            resultBox.style.display = '';
        }
    });
    xhr.addEventListener('error', function() {
        progressBox.style.display = 'none';
        btn.disabled = false;
        showNotification('Network error during upload.', 'error');
    });
    xhr.send(fd);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
