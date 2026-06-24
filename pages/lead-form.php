<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/countries.php';
startSecureSession();
requireLogin();

$pageTitle = 'Lead';
$currentPage = 'leads';
$leadId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';
?>
<script src="/assets/js/phone-picker.js?v=2"></script>


<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <a href="/pages/leads.php" class="btn btn-outline" style="padding:8px 14px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Leads')); ?>
        </a>
        <h1><?= $leadId ? htmlspecialchars(__('Edit Lead')) : htmlspecialchars(__('New Lead')) ?></h1>
    </div>
    <div class="header-actions" style="display:flex;gap:8px;align-items:center;">
        <?php if ($leadId): ?>
            <a href="/pages/lead-detail.php?id=<?= $leadId ?>" class="btn btn-outline" title="<?php echo htmlspecialchars(__('View detail')); ?>" style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <?php echo htmlspecialchars(__('View')); ?>
            </a>
            <button type="button" id="btnConvertToContact" class="btn btn-outline" style="background:#f0fdf4;border-color:#bbf7d0;color:#15803d;display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                <?php echo htmlspecialchars(__('Convert to Contact')); ?>
            </button>
            <button type="button" id="btnDeleteLead" class="btn btn-outline" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                <?php echo htmlspecialchars(__('Delete')); ?>
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveLead()" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo htmlspecialchars(__('Save Lead')); ?>
        </button>
    </div>
</div>

<?php if ($leadId): ?>
<!-- Quick Actions Bar (visible only on edit) -->
<div class="card" style="margin-top:16px;background:linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);border-color:#bfdbfe;">
    <div class="card-body" style="padding:16px 24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <strong style="color:#1e40af;font-size:13px;display:flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <?php echo htmlspecialchars(__('Quick Actions')); ?>:
        </strong>
        <button type="button" class="btn btn-outline" style="background:#16a34a;border-color:#16a34a;color:#fff;display:inline-flex;align-items:center;gap:6px;padding:6px 14px;" onclick="quickCall()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <?php echo htmlspecialchars(__('Call')); ?>
        </button>
        <button type="button" class="btn btn-outline" style="background:#25D366;border-color:#25D366;color:#fff;display:inline-flex;align-items:center;gap:6px;padding:6px 14px;" onclick="quickWhatsApp()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            <?php echo htmlspecialchars(__('WhatsApp')); ?>
        </button>
        <button type="button" class="btn btn-outline" style="background:#dc2626;border-color:#dc2626;color:#fff;display:inline-flex;align-items:center;gap:6px;padding:6px 14px;" onclick="quickEmail()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <?php echo htmlspecialchars(__('Send Email')); ?>
        </button>
        <button type="button" class="btn btn-outline" style="background:#7c3aed;border-color:#7c3aed;color:#fff;display:inline-flex;align-items:center;gap:6px;padding:6px 14px;" onclick="quickLogInteraction()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <?php echo htmlspecialchars(__('Log Interaction')); ?>
        </button>
    </div>
</div>
<?php endif; ?>

<div style="max-width:1000px;">
    <div class="card">
        <div class="card-header" style="padding: 18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Company Information')); ?></h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <div class="row-2">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Company Name *')); ?></label>
                    <input type="text" id="companyName" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., Acme Corp')); ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Lead Type')); ?></label>
                    <select id="leadType" class="form-control">
                        <option value="Business"><?php echo htmlspecialchars(__('Business')); ?></option>
                        <option value="Individual"><?php echo htmlspecialchars(__('Individual')); ?></option>
                        <option value="Partner"><?php echo htmlspecialchars(__('Partner')); ?></option>
                        <option value="Reseller"><?php echo htmlspecialchars(__('Reseller')); ?></option>
                        <option value="Other"><?php echo htmlspecialchars(__('Other')); ?></option>
                    </select>
                </div>
            </div>
            <div class="row-2" style="margin-top:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Industry')); ?></label>
                    <input type="text" id="industry" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., Technology')); ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Company Size')); ?></label>
                    <select id="companySize" class="form-control">
                        <option value=""><?php echo htmlspecialchars(__('Select...')); ?></option>
                        <option value="1-10"><?php echo htmlspecialchars(__('1-10 employees')); ?></option>
                        <option value="11-50"><?php echo htmlspecialchars(__('11-50 employees')); ?></option>
                        <option value="51-200"><?php echo htmlspecialchars(__('51-200 employees')); ?></option>
                        <option value="201-500"><?php echo htmlspecialchars(__('201-500 employees')); ?></option>
                        <option value="501+"><?php echo htmlspecialchars(__('501+ employees')); ?></option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="padding: 18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Contact Details')); ?></h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <div class="row-2">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Contact Person *')); ?></label>
                    <input type="text" id="contactPerson" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., John Doe')); ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Title / Position')); ?></label>
                    <input type="text" id="titlePosition" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., CEO')); ?>">
                </div>
            </div>
            <div class="row-3" style="margin-top:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Email')); ?></label>
                    <input type="email" id="email" class="form-control" placeholder="john@example.com">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Phone')); ?></label>
                    <?php echo renderPhonePicker(['id' => 'phone', 'label' => '', 'value' => '']); ?>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Mobile')); ?></label>
                    <?php echo renderPhonePicker(['id' => 'mobile', 'label' => '', 'value' => '']); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="padding: 18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Location & Source')); ?></h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <div class="row-3">
                <div class="form-group" style="margin-bottom:0;">
                    <?php echo renderCountrySelect(['id' => 'countryInput', 'label' => __('Country'), 'value' => '']); ?>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('City')); ?></label>
                    <input type="text" id="city" class="form-control" placeholder="<?php echo htmlspecialchars(__('City name')); ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Lead Source')); ?></label>
                    <input type="text" id="leadSource" class="form-control" list="leadSourceSuggestions" placeholder="<?php echo htmlspecialchars(__('Type or pick a source (e.g. TikTok Q2, Webinar, etc.)')); ?>" autocomplete="off" style="width:100%;">
                    <datalist id="leadSourceSuggestions">
                        <option value="Website">
                        <option value="Website - Contact Form">
                        <option value="Website - Chat Widget">
                        <option value="Referral">
                        <option value="Cold Call">
                        <option value="Cold Email">
                        <option value="Email Campaign">
                        <option value="Social Media">
                        <option value="Facebook">
                        <option value="Instagram">
                        <option value="LinkedIn">
                        <option value="Twitter/X">
                        <option value="Google Ads">
                        <option value="Google Organic">
                        <option value="Bing Ads">
                        <option value="YouTube">
                        <option value="TikTok">
                        <option value="Trade Show">
                        <option value="Conference">
                        <option value="Webinar">
                        <option value="Partner">
                        <option value="Affiliate">
                        <option value="Direct">
                        <option value="Other">
                    </datalist>
                    <small class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars(__('Type anything — new sources are remembered for autocomplete.')); ?></small>
                </div>
            </div>

            <!-- UTM tracking (auto-captured from webform submissions) -->
            <div style="margin-top:18px;padding:14px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <strong style="font-size:13px;"><?php echo htmlspecialchars(__('UTM Tracking')); ?></strong>
                    <span style="font-size:11px;color:var(--color-text-tertiary);">— <?php echo htmlspecialchars(__('Auto-captured from webform submissions and the page URL')); ?></span>
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:12px;"><?php echo htmlspecialchars(__('UTM Source')); ?></label>
                        <input type="text" id="utmSource" name="utm_source" class="form-control" placeholder="tiktok, google, newsletter" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:12px;"><?php echo htmlspecialchars(__('UTM Campaign')); ?></label>
                        <input type="text" id="utmCampaign" name="utm_campaign" class="form-control" placeholder="summer_sale_2026" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:12px;"><?php echo htmlspecialchars(__('UTM Medium')); ?></label>
                        <input type="text" id="utmMedium" name="utm_medium" class="form-control" placeholder="cpc, email, social" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:12px;"><?php echo htmlspecialchars(__('UTM Content')); ?></label>
                        <input type="text" id="utmContent" name="utm_content" class="form-control" placeholder="hero_banner_v2" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:12px;"><?php echo htmlspecialchars(__('UTM Term')); ?></label>
                        <input type="text" id="utmTerm" name="utm_term" class="form-control" placeholder="running+shoes" style="font-size:13px;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="padding: 18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Status & Priority')); ?></h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <div class="row-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                    <select id="leadStatus" class="form-control">
                        <option value="New Lead"><?php echo htmlspecialchars(__('New Lead')); ?></option>
                        <option value="Contacted"><?php echo htmlspecialchars(__('Contacted')); ?></option>
                        <option value="Interested"><?php echo htmlspecialchars(__('Interested')); ?></option>
                        <option value="Not Interested"><?php echo htmlspecialchars(__('Not Interested')); ?></option>
                        <option value="Call Scheduled"><?php echo htmlspecialchars(__('Call Scheduled')); ?></option>
                        <option value="Proposal Sent"><?php echo htmlspecialchars(__('Proposal Sent')); ?></option>
                        <option value="Negotiation"><?php echo htmlspecialchars(__('Negotiation')); ?></option>
                        <option value="Won"><?php echo htmlspecialchars(__('Won')); ?></option>
                        <option value="Lost"><?php echo htmlspecialchars(__('Lost')); ?></option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Priority')); ?></label>
                    <select id="priority" class="form-control">
                        <option value="Low"><?php echo htmlspecialchars(__('Low')); ?></option>
                        <option value="Medium" selected><?php echo htmlspecialchars(__('Medium')); ?></option>
                        <option value="High"><?php echo htmlspecialchars(__('High')); ?></option>
                        <option value="Urgent"><?php echo htmlspecialchars(__('Urgent')); ?></option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                    <select id="assignedTo" class="form-control">
                        <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                    </select>
                </div>
            </div>
            <div style="margin-top:20px;">
                <label class="form-label"><?php echo htmlspecialchars(__('Notes')); ?></label>
                <textarea id="notes" class="form-control" rows="4" placeholder="<?php echo htmlspecialchars(__('Additional notes about this lead...')); ?>"></textarea>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const LEAD_ID = <?= $leadId ?>;

// Country dial codes for phone picker
window.COUNTRY_DIAL_CODES = <?php $codes = []; foreach (getCountriesList() as $c) { $codes[] = ["code" => $c[0], "dial" => $c[2]]; } echo json_encode($codes); ?>;

// Parse a phone number string for the phone picker
// Returns {code, dial_code, national} or null
function parsePhoneForPicker(phone) {
    if (!phone) return null;
    phone = String(phone).trim();
    if (phone[0] !== '+') phone = '+' + phone;
    var countries = window.COUNTRY_DIAL_CODES || [];
    // Try longest dial codes first
    var sorted = countries.slice().sort(function(a, b) { return b.dial.length - a.dial.length; });
    for (var i = 0; i < sorted.length; i++) {
        if (phone.indexOf(sorted[i].dial) === 0) {
            var national = phone.substring(sorted[i].dial.length).replace(/^[\s\-()]+/, '');
            return { code: sorted[i].code, dial_code: sorted[i].dial, national: national };
        }
    }
    return null;
}
let currentUsers = [];

document.addEventListener('DOMContentLoaded', function() {
    loadUsers().then(function() {
        if (LEAD_ID) loadLead();
    });
    captureUtmFromUrl();
    loadLeadSourceSuggestions();
});

/**
 * Auto-fill UTM fields from the current page URL (?utm_source=... etc).
 * This way leads created from a webform session will carry UTMs even
 * if the user later edits the lead manually.
 */
function captureUtmFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var keys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term'];
    keys.forEach(function(k) {
        var v = params.get(k);
        if (v) {
            var el = document.getElementById(k);
            if (el && !el.value) el.value = v;
        }
    });
    // Also capture landing page + referrer from document
    var landingEl = document.getElementById('landingPage');
    if (landingEl && !landingEl.value) landingEl.value = window.location.href;
}

/**
 * Load the company's lead source library to populate the datalist.
 * Adds new entries from the DB on top of the hard-coded seed options.
 */
function loadLeadSourceSuggestions() {
    fetch('/api/leads.php?action=sources', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !Array.isArray(data.data)) return;
        var dl = document.getElementById('leadSourceSuggestions');
        if (!dl) return;
        // Mark company-used sources with (×N) hint
        data.data.forEach(function(item) {
            var val = item.value;
            // Avoid duplicates with the existing hard-coded options
            var exists = Array.prototype.some.call(dl.options, function(o) { return o.value === val; });
            if (exists) return;
            var opt = document.createElement('option');
            opt.value = val;
            if (item.use_count > 1) {
                opt.label = '\u00d7' + item.use_count;
            }
            dl.appendChild(opt);
        });
    })
    .catch(function() { /* non-fatal — hard-coded seeds are still there */ });
}

/**
 * Load company users from the API and populate the Assigned To dropdown.
 * API returns: {success: true, data: {users: [...]}}
 * Uses innerHTML append (not +=) to preserve existing options.
 */
function loadUsers() {
    return fetch('/api/users.php?action=list', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        // Handle multiple possible response shapes for robustness
        var users = [];
        if (data && data.success) {
            users = data.users || (data.data && data.data.users) || [];
        }
        var select = document.getElementById('assignedTo');
        if (!select) return;
        
        // Preserve the "Unassigned" default option, append user options
        var defaultOption = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (defaultOption) select.appendChild(defaultOption);
        
        users.forEach(function(u) {
            var opt = document.createElement('option');
            opt.value = u.user_id;
            opt.textContent = u.full_name || u.email || u.username;
            select.appendChild(opt);
        });
        
        // If no users were loaded, show a helpful message
        if (users.length === 0) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'No team members found';
            opt.disabled = true;
            select.appendChild(opt);
        }
    })
    .catch(function(err) {
        console.error('loadUsers error:', err);
    });
}

function loadLead() {
    fetch('/api/leads.php?action=detail&id=' + LEAD_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        var l = (data && data.data && data.data.lead) || (data && data.lead) || null;
        if (l) {
            var fields = ['companyName','leadType','industry','companySize','contactPerson','titlePosition','email','phone','mobile','country','city','leadSource','leadStatus','priority','notes'];
            fields.forEach(function(f) {
                var el = document.getElementById(f === 'country' ? 'countryInput' : f);
                if (el) {
                    var key = f.replace(/([A-Z])/g,'_$1').toLowerCase();
                    if (l[key] !== null && l[key] !== undefined) {
                        if (f === 'phone' || f === 'mobile') {
                            // Phone picker: parse country code and national number
                            var parsed = parsePhoneForPicker(l[key]);
                            if (parsed) {
                                el.value = parsed.national;
                                var hiddenEl = document.getElementById(f + '_full');
                                if (hiddenEl) hiddenEl.value = l[key];
                                // Set the country code dropdown
                                var countrySelect = el.closest('.phone-picker')?.querySelector('.phone-country-select');
                                if (countrySelect && parsed.code) {
                                    for (var i = 0; i < countrySelect.options.length; i++) {
                                        if (countrySelect.options[i].value === parsed.code) {
                                            countrySelect.selectedIndex = i;
                                            if (typeof updatePhonePicker === 'function') updatePhonePicker(countrySelect);
                                            break;
                                        }
                                    }
                                }
                            } else {
                                el.value = l[key];
                            }
                        } else {
                            el.value = l[key];
                        }
                    }
                }
            });
            // Sync country dropdown from country name
            if (l.country) {
                var countrySel = document.getElementById('countryInput');
                if (countrySel && countrySel.tagName === 'SELECT') {
                    for (var i = 0; i < countrySel.options.length; i++) {
                        if (countrySel.options[i].text.indexOf(l.country) !== -1 || countrySel.options[i].value === l.country) {
                            countrySel.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
            // UTM fields
            ['utm_source','utm_campaign','utm_medium','utm_content','utm_term','landing_page','referrer'].forEach(function(k) {
                var id = k.replace(/_([a-z])/g, function(_, c) { return c.toUpperCase(); });
                var el = document.getElementById(id);
                if (el && l[k]) el.value = l[k];
            });
            if (l.assigned_to) document.getElementById('assignedTo').value = l.assigned_to;
        }
    });
}

function saveLead() {
    var companyName = document.getElementById('companyName').value.trim();
    if (!companyName) { showNotification(__('Company name is required'), 'error'); return; }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        lead_id: LEAD_ID || null,

        company_name: companyName,
        lead_type: document.getElementById('leadType').value,
        industry: document.getElementById('industry').value,
        company_size: document.getElementById('companySize').value,
        contact_person: document.getElementById('contactPerson').value,
        title_position: document.getElementById('titlePosition').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone_full')?.value || document.getElementById('phone').value,
        mobile: document.getElementById('mobile_full')?.value || document.getElementById('mobile').value,
        country: document.getElementById('countryInput').value,
        city: document.getElementById('city').value,
        lead_source: document.getElementById('leadSource').value,
        lead_status: document.getElementById('leadStatus').value,
        priority: document.getElementById('priority').value,
        notes: document.getElementById('notes').value,
        assigned_to: document.getElementById('assignedTo').value || null,
        // UTM fields
        utm_source: document.getElementById('utmSource')?.value || null,
        utm_campaign: document.getElementById('utmCampaign')?.value || null,
        utm_medium: document.getElementById('utmMedium')?.value || null,
        utm_content: document.getElementById('utmContent')?.value || null,
        utm_term: document.getElementById('utmTerm')?.value || null,
    };
    
    if (LEAD_ID) {
        fetch('/api/leads.php?action=update&id=' + LEAD_ID, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            showNotification(data.message || (data.success ? __('Lead updated!') : __('Update failed')), data.success ? 'success' : 'error');
            if (data.success) window.location.href = '/pages/leads.php';
        });
    } else {
        fetch('/api/leads.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            showNotification(data.message || (data.success ? __('Lead created!') : __('Create failed')), data.success ? 'success' : 'error');
            if (data.success) window.location.href = '/pages/leads.php?saved=1';
        });
    }
}

function convertToContact() {
    if (!LEAD_ID) return;
    showConfirm('<?php echo htmlspecialchars(__('Convert this lead to a Contact? The lead status will be set to "Won".'), ENT_QUOTES); ?>', function() {
        fetch('/api/leads.php?action=move_to_contact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ lead_id: LEAD_ID, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification('<?php echo htmlspecialchars(__('Lead converted to contact successfully!'), ENT_QUOTES); ?>', 'success');
                setTimeout(function() { window.location.href = '/pages/leads.php'; }, 800);
            } else {
                showNotification(data.message || '<?php echo htmlspecialchars(__('Error converting lead'), ENT_QUOTES); ?>', 'error');
            }
        });
    });
}

function deleteLeadNow() {
    if (!LEAD_ID) return;
    showConfirm('<?php echo htmlspecialchars(__('Delete this lead permanently? This cannot be undone.'), ENT_QUOTES); ?>', function() {
        fetch('/api/leads.php?action=delete&id=' + LEAD_ID, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification('<?php echo htmlspecialchars(__('Lead deleted'), ENT_QUOTES); ?>', 'success');
                setTimeout(function() { window.location.href = '/pages/leads.php'; }, 800);
            } else {
                showNotification(data.message || '<?php echo htmlspecialchars(__('Error deleting lead'), ENT_QUOTES); ?>', 'error');
            }
        });
    });
}

document.getElementById('btnConvertToContact')?.addEventListener('click', convertToContact);
document.getElementById('btnDeleteLead')?.addEventListener('click', deleteLeadNow);

// ── Quick Actions (Call / WhatsApp / Email / Log Interaction) ──
function quickCall() {
    var phone = (document.getElementById('phone')?.value || document.getElementById('mobile')?.value || '').trim();
    if (!phone) { showNotification('<?php echo htmlspecialchars(__('No phone number on this lead'), ENT_QUOTES); ?>', 'error'); return; }
    // Open the dedicated dialer page with the phone pre-filled
    window.location.href = '/pages/voip-dial.php?phone=' + encodeURIComponent(phone);
}

function quickWhatsApp() {
    var phone = (document.getElementById('phone')?.value || document.getElementById('mobile')?.value || '').trim();
    if (!phone) { showNotification('<?php echo htmlspecialchars(__('No phone number on this lead'), ENT_QUOTES); ?>', 'error'); return; }
    // wa.me requires digits only (no +, spaces, dashes)
    var clean = phone.replace(/[^0-9]/g, '');
    window.open('https://wa.me/' + clean, '_blank');
}

function quickEmail() {
    var email = (document.getElementById('email')?.value || '').trim();
    if (!email) { showNotification('<?php echo htmlspecialchars(__('No email on this lead'), ENT_QUOTES); ?>', 'error'); return; }
    var subject = '<?php echo htmlspecialchars(__('Follow up'), ENT_QUOTES); ?>';
    var body = '<?php echo htmlspecialchars(__('Hi'), ENT_QUOTES); ?> ' + (document.getElementById('contactPerson')?.value || '') + ',\n\n';
    window.location.href = 'mailto:' + email + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
}

function quickLogInteraction() {
    window.location.href = '/pages/interactions.php?lead_id=' + LEAD_ID + '&action=new';
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[<>&"]/g, function(c) {
        return c === '<' ? '<' : c === '>' ? '>' : c === '&' ? '&' : '"';
    });
}
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
