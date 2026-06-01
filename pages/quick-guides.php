<?php
/**
 * White Label CRM - Quick Guides (Knowledge Hub)
 * Card-based page showing training guides and reference materials.
 * All users can access this page.
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();

$pageTitle = 'Quick Guides';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo __('Quick Guides'); ?></h1>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:4px;"><?php echo __('Training materials, sales playbooks, and reference guides for the team.'); ?></p>
    </div>
</div>

<!-- Guide Cards Grid -->
<div class="guides-grid">

    <!-- How to Sell Card -->
    <div class="guide-card" onclick="openGuide('how-to-sell')" style="cursor:pointer;">
        <div class="guide-card-icon" style="background:linear-gradient(135deg, #0F1A2E, #162035);">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#00B8D9" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M16 8l-4 4-4-4"/>
                <path d="M12 16V12"/>
            </svg>
        </div>
        <div class="guide-card-body">
            <h3 class="guide-card-title"><?php echo __('How to Sell'); ?></h3>
            <p class="guide-card-desc"><?php echo __('Complete sales routing guide — match the customer to the right product. Covers WGS Premium, VG Enthusiast, Specialist tests, pricing, upselling modules, and cheat sheet.'); ?></p>
            <div class="guide-card-meta">
                <span class="guide-tag" style="background:#e0f7fa;color:#00838f;"><?php echo __('Sales'); ?></span>
                <span class="guide-tag" style="background:#e8f5e9;color:#2e7d32;"><?php echo __('Products'); ?></span>
                <span class="guide-tag" style="background:#fff3e0;color:#e65100;"><?php echo __('Pricing'); ?></span>
            </div>
        </div>
        <div class="guide-card-arrow">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
    </div>

    <!-- Placeholder for future guides -->
    <div class="guide-card guide-card-empty">
        <div class="guide-card-icon" style="background:var(--color-bg);">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
        </div>
        <div class="guide-card-body">
            <h3 class="guide-card-title" style="color:var(--color-text-muted);"><?php echo __('More guides coming soon'); ?></h3>
            <p class="guide-card-desc" style="color:var(--color-text-muted);"><?php echo __('Additional training materials and quick reference cards will be added here.'); ?></p>
        </div>
    </div>

</div>

<!-- Full-screen Guide Viewer Modal -->
<div id="guideViewerModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeGuide()"></div>
    <div class="guide-viewer-content">
        <div class="guide-viewer-header">
            <h3 id="guideViewerTitle"><?php echo __('Guide'); ?></h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="btn btn-sm btn-outline" onclick="openGuideNewTab()" title="<?php echo __('Open in new tab'); ?>" style="font-size:12px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    <?php echo __('New Tab'); ?>
                </button>
                <button type="button" class="btn-close" onclick="closeGuide()" style="font-size:20px;background:none;border:none;cursor:pointer;color:var(--color-text-secondary);padding:4px 8px;">&times;</button>
            </div>
        </div>
        <iframe id="guideViewerFrame" src="" style="width:100%;height:100%;border:none;flex:1;"></iframe>
    </div>
</div>

<style>
/* ── Guide Cards Grid ── */
.guides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 16px;
    margin-top: 8px;
}

.guide-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    background: var(--color-bg-card, #fff);
    border: 1px solid var(--color-border-light);
    border-radius: 12px;
    transition: all 0.2s ease;
}

.guide-card:not(.guide-card-empty):hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    border-color: #00B8D9;
    transform: translateY(-2px);
}

.guide-card-empty {
    border-style: dashed;
    opacity: 0.6;
    cursor: default;
}

.guide-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.guide-card-body {
    flex: 1;
    min-width: 0;
}

.guide-card-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 4px;
    color: var(--color-text-primary);
}

.guide-card-desc {
    font-size: 12px;
    color: var(--color-text-secondary);
    line-height: 1.5;
    margin-bottom: 8px;
}

.guide-card-meta {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.guide-tag {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.guide-card-arrow {
    flex-shrink: 0;
    color: var(--color-text-muted);
    transition: transform 0.2s;
}

.guide-card:not(.guide-card-empty):hover .guide-card-arrow {
    transform: translateX(4px);
    color: #00B8D9;
}

/* ── Guide Viewer Modal ── */
.guide-viewer-content {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #fff;
    z-index: 10001;
    display: flex;
    flex-direction: column;
}

.guide-viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 24px;
    border-bottom: 1px solid var(--color-border-light);
    background: var(--color-bg-card, #fff);
    flex-shrink: 0;
}

.guide-viewer-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text-primary);
}

/* Responsive */
@media (max-width: 480px) {
    .guides-grid {
        grid-template-columns: 1fr;
    }
    .guide-card {
        padding: 16px;
    }
    .guide-card-icon {
        width: 48px;
        height: 48px;
    }
}
</style>

<script>
// Guide definitions — add new guides here
var guides = {
    'how-to-sell': {
        title: <?php echo json_encode(__('How to Sell')); ?>,
        url: '/pages/knowledge-hub/how-to-sell.html'
    }
};

var currentGuideKey = null;

function openGuide(key) {
    var guide = guides[key];
    if (!guide) return;

    currentGuideKey = key;
    document.getElementById('guideViewerTitle').textContent = guide.title;
    document.getElementById('guideViewerFrame').src = guide.url;
    document.getElementById('guideViewerModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeGuide() {
    document.getElementById('guideViewerModal').style.display = 'none';
    document.getElementById('guideViewerFrame').src = '';
    document.body.style.overflow = '';
    currentGuideKey = null;
}

function openGuideNewTab() {
    if (currentGuideKey && guides[currentGuideKey]) {
        window.open(guides[currentGuideKey].url, '_blank');
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('guideViewerModal').style.display !== 'none') {
        closeGuide();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
