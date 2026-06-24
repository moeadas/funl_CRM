<?php
/**
 * White Label CRM - Proposals List
 * 
 * Shows all proposals for the current company with View, Edit, and Download actions.
 * Data is scoped to the user's company_id to prevent cross-tenant access.
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Proposals';
$currentPage = 'proposals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Proposals / Estimates</h1>
        <p class="text-muted"><?php echo __('Create and manage client proposals'); ?></p>
    </div>
    <div class="header-actions">
        <a href="/pages/proposal-form.php" class="btn btn-primary btn-sm"><?php echo __('New Proposal'); ?></a>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table" id="proposalsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo __('Date'); ?></th>
                    <th><?php echo __('Customer'); ?></th>
                    <th><?php echo __('Contact'); ?></th>
                    <th><?php echo __('Total'); ?></th>
                    <th><?php echo __('Status'); ?></th>
                    <th><?php echo __('Actions'); ?></th>
                </tr>
            </thead>
            <tbody id="proposalsBody">
                <tr><td colspan="7" class="text-center text-muted" style="padding:40px;"><?php echo __('Loading...'); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const CSRF_TOKEN = "";
let currentPage = 1;

/**
 * Load proposals from the API (scoped to current company)
 */
function loadProposals() {
    fetch("/api/proposals.php?action=list&page=" + currentPage)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { document.getElementById("proposalsBody").innerHTML = "<tr><td colspan=7 class=text-center>" + (data.message || window.__("Error")) + "</td></tr>"; return; }
            renderProposals(data.proposals);
        })
        .catch(() => document.getElementById("proposalsBody").innerHTML = "<tr><td colspan=7 class=text-center>" + window.__("Error") + "</td></tr>");
}

/**
 * Render proposal rows with View, Download, and Edit buttons
 */
function renderProposals(proposals) {
    const tbody = document.getElementById("proposalsBody");
    if (!proposals || proposals.length === 0) {
        tbody.innerHTML = "<tr><td colspan=7 class=text-center>" + window.__("No proposals found") + "</td></tr>";
        return;
    }
    const statusColors = {Draft: "secondary", Sent: "primary", Accepted: "success", Declined: "danger"};
    tbody.innerHTML = proposals.map(p => `<tr>
        <td>${p.estimate_number || "-"}</td>
        <td>${p.proposal_date || "-"}</td>
        <td>${(p.customer_company || "").replace(/</g, "&lt;")}</td>
        <td>${(p.contact_name || "-").replace(/</g, "&lt;")}</td>
        <td>$${parseFloat(p.total || 0).toFixed(2)}</td>
        <td><span class="badge badge-${statusColors[p.status] || "secondary"}">${p.status}</span></td>
        <td style="white-space:nowrap;">
            <a href="/pages/proposal-view.php?id=${p.proposal_id}" class="btn btn-sm btn-outline" title="${window.__('View')}">👁</a>
            <a href="/pages/proposal-view.php?id=${p.proposal_id}&print=1" target="_blank" class="btn btn-sm btn-outline" title="${window.__('Download PDF')}">⬇</a>
            <a href="/pages/proposal-form.php?id=${p.proposal_id}" class="btn btn-sm btn-outline">${window.__('Edit')}</a>
        </td>
    </tr>`).join("");
}

loadProposals();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>