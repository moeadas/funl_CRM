<?php
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
        <p class="text-muted">Create and manage client proposals</p>
    </div>
    <div class="header-actions">
        <a href="/pages/proposal-form.php" class="btn btn-primary btn-sm">New Proposal</a>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table" id="proposalsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="proposalsBody">
                <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const CSRF_TOKEN = "";
let currentPage = 1;

function loadProposals() {
    fetch("/api/proposals.php?action=list&page=" + currentPage)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { document.getElementById("proposalsBody").innerHTML = "<tr><td colspan=7 class=text-center>" + (data.message || "Error") + "</td></tr>"; return; }
            renderProposals(data.proposals);
        })
        .catch(() => document.getElementById("proposalsBody").innerHTML = "<tr><td colspan=7 class=text-center>Error</td></tr>");
}

function renderProposals(proposals) {
    const tbody = document.getElementById("proposalsBody");
    if (!proposals || proposals.length === 0) {
        tbody.innerHTML = "<tr><td colspan=7 class=text-center>No proposals found</td></tr>";
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
        <td><a href="/pages/proposal-form.php?id=${p.proposal_id}" class="btn btn-sm btn-outline">Edit</a></td>
    </tr>`).join("");
}

loadProposals();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>