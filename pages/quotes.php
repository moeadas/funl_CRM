<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;

$pageTitle = 'Quotes';
$js = ['quotes'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$deals = $db->query("SELECT deal_id, deal_name FROM deals WHERE company_id = ? ORDER BY deal_name", [$companyId])->fetchAll();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
?><div class="quotes-page">
    <div class="page-header">
        <h1><?php echo __('Quotes & Proposals'); ?></h1>
        <a href="/pages/quote-new.php" class="btn btn-primary">+ New Quote</a>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quote #</th>
                    <th><?php echo __('Title'); ?></th>
                    <th><?php echo __('Client'); ?></th>
                    <th><?php echo __('Total'); ?></th>
                    <th><?php echo __('Status'); ?></th>
                    <th><?php echo __('Date'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="quotes-tbody">
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af"><?php echo __('Loading...'); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const API = '/api/quotes.php';

let quotes = [];

document.addEventListener('DOMContentLoaded', () => {
    loadQuotes();
});

function loadQuotes() {
    fetch(`${API}?action=list`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                quotes = resp.data || [];
                renderQuotes();
            }
        });
}

function renderQuotes() {
    const tbody = document.getElementById('quotes-tbody');
    if (!quotes.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af">No quotes yet. Create your first quote!</td></tr>';
        return;
    }

    tbody.innerHTML = quotes.map(q => {
        const client = q.account_name || q.lead_name || q.contact_name || '-';
        const statusClass = 'status-' + (q.status || 'draft');
        const currency = q.currency || 'USD';
        const total = new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(q.total || 0);

        return `
        <tr onclick="viewQuote(${q.quote_id})" style="cursor:pointer">
            <td><strong>${escapeHtml(q.quote_number)}</strong></td>
            <td>${escapeHtml(q.quote_title)}</td>
            <td>${escapeHtml(client)}</td>
            <td style="font-weight:600">${total}</td>
            <td><span class="status-badge ${statusClass}">${q.status || 'Draft'}</span></td>
            <td>${formatDate(q.issue_date)}</td>
            <td><button type="button" onclick="event.stopPropagation();deleteQuote(${q.quote_id})" style="background:none;border:none;cursor:pointer;color:#dc2626">🗑️</button></td>
        </tr>`;
    }).join('');
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[<>&"']/g, c => c === '<' ? '<' : c === '>' ? '>' : c === '&' ? '&' : '"');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function viewQuote(quoteId) {
    window.location.href = '/pages/quote-new.php?id=' + quoteId;
}

function deleteQuote(quoteId) {
    showConfirm('Delete this quote?', function() {
        fetch(`${API}?action=delete`, {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ quote_id: quoteId, csrf_token: CSRF_TOKEN })
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                loadQuotes();
                showNotification('Quote deleted', 'success');
            } else {
                showNotification(resp.message || 'Failed', 'error');
            }
        });
    });
}
</script>
