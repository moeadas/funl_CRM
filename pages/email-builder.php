<?php
/**
 * Pinpoint CRM — Enhanced Visual Email Builder
 * Sections + Blocks with Drag & Drop
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$pageTitle = 'Email Builder';
$currentPage = 'email-campaigns';
$csrf_token = generateCSRFToken();

// Get campaign/template data
$db = Database::getInstance()->getConnection();
$existingJson = '{}';
$campaignId = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$templateId = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;


$mode = $_GET['mode'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$campaignId = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : ($mode === 'campaign' ? $id : 0);
$templateId = isset($_GET['template_id']) ? intval($_GET['template_id']) : ($mode === 'template' ? $id : 0);
$isTemplate = $templateId > 0 || $mode === 'template';

$companyId = $_SESSION['company_id'] ?? null;

if ($campaignId > 0) {
    if ($companyId) {
        $stmt = $db->prepare("SELECT content_json FROM email_campaigns WHERE campaign_id = ? AND company_id = ?");
        $stmt->execute([$campaignId, $companyId]);
    } else {
        $stmt = $db->prepare("SELECT content_json FROM email_campaigns WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
    }
    $stmt->execute([$campaignId, $_SESSION['company_id']]);
    $row = $stmt->fetch();
    if ($row && $row['content_json']) $existingJson = $row['content_json'];
} elseif ($templateId > 0) {
    if ($companyId) {
        $stmt = $db->prepare("SELECT content_json FROM email_templates WHERE template_id = ? AND company_id = ?");
        $stmt->execute([$templateId, $companyId]);
    } else {
        $stmt = $db->prepare("SELECT content_json FROM email_templates WHERE template_id = ?");
        $stmt->execute([$templateId]);
    }
    $stmt->execute([$templateId, $_SESSION['company_id']]);
    $row = $stmt->fetch();
    if ($row && $row['content_json']) $existingJson = $row['content_json'];
}

require_once '../includes/header.php';
?>
<style>
  .builder-wrap { display:flex; height:calc(100vh - 60px); gap:0; }
  .builder-palette { width:220px; background:#f8f9fa; border-right:1px solid #ddd; padding:16px; overflow-y:auto; }
  .builder-palette h3 { font-size:13px; text-transform:uppercase; letter-spacing:1px; color:#666; margin:0 0 12px; }
  .palette-section { margin-bottom:20px; }
  .palette-item { display:flex; align-items:center; gap:8px; padding:10px 12px; background:#fff; border:1px solid #ddd; border-radius:6px; margin-bottom:8px; cursor:grab; transition:.15s; }
  .palette-item:hover { border-color:#D91C48; background:#fff0f3; }
  .palette-item .icon { width:20px; height:20px; color:#D91C48; }
  .palette-item .label { font-size:13px; color:#333; }
  .builder-canvas { flex:1; background:#e9ecef; overflow-y:auto; padding:20px; }
  #email-canvas { max-width:800px; margin:0 auto; min-height:400px; }
  .builder-properties { width:320px; background:#fff; border-left:1px solid #ddd; overflow-y:auto; padding:16px; }
  .prop-tabs { display:flex; border-bottom:1px solid #ddd; margin-bottom:16px; }
  .tab-btn { flex:1; padding:10px; border:none; background:none; cursor:pointer; font-size:13px; color:#666; border-bottom:2px solid transparent; }
  .tab-btn.active { color:#D91C48; border-bottom-color:#D91C48; font-weight:600; }
  .tab-content { display:none; }
  .tab-content.active { display:block; }
  .email-section { background:#fff; margin-bottom:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
  .section-header { display:none; padding:8px 12px; background:#f8f9fa; border-bottom:1px dashed #ddd; cursor:grab; }
  .email-section:hover .section-header { display:block; }
  .email-column { min-height:60px; padding:8px; border:1px dashed transparent; transition:border-color .2s; }
  .email-column:hover { border-color:#D91C48; }
  .email-block { padding:8px; position:relative; }
  .email-block:hover { outline:1px dashed #D91C48; }
  .sortable-ghost { opacity:.3; background:#f0f0f0; }
  .prop-field label { display:block; font-size:12px; color:#666; margin-bottom:4px; }
  .prop-field input,.prop-field select,.prop-field textarea { width:100%; padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:13px; }
  .merge-tags { margin-bottom:12px; }
  .merge-tag-btn { font-size:11px; padding:3px 8px; margin:2px; border:1px solid #ddd; background:#f8f9fa; border-radius:3px; cursor:pointer; }
  .merge-tag-btn:hover { background:#D91C48; color:#fff; border-color:#D91C48; }
  .btn-save { background:#D91C48; color:#fff; border:none; padding:12px 24px; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
  .btn-save:hover { background:#b0173a; }
  .builder-toolbar { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; background:#fff; border-bottom:1px solid #ddd; }
  .builder-toolbar h2 { margin:0; font-size:18px; }
  .preview-sizes { display:flex; gap:8px; }
  .preview-btn { padding:6px 12px; border:1px solid #ddd; background:#fff; border-radius:4px; cursor:pointer; font-size:12px; }
  .preview-btn.active { background:#D91C48; color:#fff; border-color:#D91C48; }
  .block-actions { display:none; position:absolute; top:2px; right:2px; gap:4px; }
  .email-block:hover .block-actions { display:flex; }
</style>

<div class="builder-toolbar">
  <div>
    <h2>📧 Email Builder</h2>
    <?php if ($campaignId): ?>
      <span style="font-size:13px;color:#666;">Campaign #<?php echo $campaignId; ?></span>
    <?php elseif ($templateId): ?>
      <span style="font-size:13px;color:#666;">Template #<?php echo $templateId; ?></span>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:12px;align-items:center;">
    <button class="preview-btn active" onclick="EmailBuilder.setPreviewSize('desktop');">💻 Desktop</button>
    <button class="preview-btn" onclick="EmailBuilder.setPreviewSize('mobile');">📱 Mobile</button>
    <button class="btn-save" onclick="saveBuilder()">💾 Save & Export HTML</button>
  </div>
</div>

<div class="builder-wrap">
  <!-- Left Palette -->
  <div class="builder-palette">
    <div class="palette-section">
      <h3>📐 Sections</h3>
      <div class="palette-item" data-section="1" draggable="true">
        <span class="icon">▌</span>
        <span class="label">1 Column</span>
      </div>
      <div class="palette-item" data-section="2" draggable="true">
        <span class="icon">▌▌</span>
        <span class="label">2 Columns</span>
      </div>
      <div class="palette-item" data-section="3" draggable="true">
        <span class="icon">▌▌▌</span>
        <span class="label">3 Columns</span>
      </div>
      <div class="palette-item" data-section="4" draggable="true">
        <span class="icon">▌▌▌▌</span>
        <span class="label">4 Columns</span>
      </div>
    </div>

    <div class="palette-section">
      <h3>🧱 Blocks</h3>
      <div class="palette-item" data-type="text" draggable="true">
        <span class="icon">T</span>
        <span class="label">Text</span>
      </div>
      <div class="palette-item" data-type="image" draggable="true">
        <span class="icon">🖼</span>
        <span class="label">Image</span>
      </div>
      <div class="palette-item" data-type="button" draggable="true">
        <span class="icon">🔘</span>
        <span class="label">Button</span>
      </div>
      <div class="palette-item" data-type="divider" draggable="true">
        <span class="icon">—</span>
        <span class="label">Divider</span>
      </div>
      <div class="palette-item" data-type="spacer" draggable="true">
        <span class="icon">⇳</span>
        <span class="label">Spacer</span>
      </div>
      <div class="palette-item" data-type="html" draggable="true">
        <span class="icon">&lt;/&gt;</span>
        <span class="label">HTML</span>
      </div>
      <div class="palette-item" data-type="social" draggable="true">
        <span class="icon">@→</span>
        <span class="label">Social</span>
      </div>
    </div>
  </div>

  <!-- Center Canvas -->
  <div class="builder-canvas">
    <div id="email-canvas"></div>
  </div>

  <!-- Right Properties -->
  <div class="builder-properties">
    <div class="prop-tabs">
      <button class="tab-btn active" data-tab="properties" onclick="EmailBuilder.switchTab('properties')">⚙️ Properties</button>
      <button class="tab-btn" data-tab="preview" onclick="EmailBuilder.switchTab('preview')">👁 Preview</button>
      <button class="tab-btn" data-tab="body" onclick="EmailBuilder.switchTab('body')">📄 Body</button>
    </div>
    <div id="tab-properties" class="tab-content active">
      <div id="properties-panel">
        <p style="color:#999;text-align:center;padding:20px;">Select a section or block to edit its properties</p>
      </div>
    </div>
    <div id="tab-preview" class="tab-content">
      <iframe id="preview-frame" style="width:100%;height:500px;border:1px solid #ddd;border-radius:4px;"></iframe>
    </div>
    <div id="tab-body" class="tab-content">
      <div id="body-panel"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="/assets/js/email-builder.js"></script>
<script>
  var CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;
  var EXISTING_JSON = <?php echo json_encode($existingJson); ?>;
  var CAMPAIGN_ID = <?php echo json_encode($campaignId); ?>;
  var TEMPLATE_ID = <?php echo json_encode($templateId); ?>;
  var IS_TEMPLATE = <?php echo json_encode($isTemplate); ?>;

  document.addEventListener('DOMContentLoaded', function() {
    EmailBuilder.init({
      csrfToken: CSRF_TOKEN,
      existingJson: (EXISTING_JSON && EXISTING_JSON !== '{}') ? EXISTING_JSON : null
    });

    // Palette section drop onto canvas
    var $canvas = document.getElementById('email-canvas');
    $canvas.addEventListener('dragover', function(e) { e.preventDefault(); });
    $canvas.addEventListener('drop', function(e) {
      e.preventDefault();
      var sectionType = e.dataTransfer.getData('sectionType');
      if (sectionType) {
        EmailBuilder.addSection(parseInt(sectionType));
      }
    });
  });

  function saveBuilder() {
    var contentJson = EmailBuilder.getJSON();
    var htmlOutput = EmailBuilder.getHTML();
    var data = {
      csrf_token: CSRF_TOKEN,
      content_json: contentJson,
      html_content: htmlOutput
    };

    var url = '/api/email-campaigns.php';
    if (CAMPAIGN_ID) {
      data.campaign_id = CAMPAIGN_ID;
      data.action = 'update_content';
    } else if (TEMPLATE_ID) {
      data.template_id = TEMPLATE_ID;
      url = '/api/email-templates.php';
    }

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
      if (resp.success) {
        alert('✅ Saved successfully!');
      } else {
        alert('❌ Save failed: ' + (resp.message || 'Unknown error'));
      }
    })
    .catch(function(err) {
      console.error(err);
      // Fallback: download as file
      var blob = new Blob([htmlOutput], { type: 'text/html' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'email.html';
      a.click();
    });
  }
</script>

<?php require_once '../includes/footer.php'; ?>
