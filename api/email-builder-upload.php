<?php
/**
 * Pinpoint CRM — Email Builder image upload endpoint
 *
 * Accepts: multipart/form-data with fields:
 *   - image       (file)
 *   - csrf_token  (string)
 *
 * Returns JSON:
 *   { success: true,  url: "/uploads/email-builder/<company>/<file>" }
 *   { success: false, message: "..." }
 *
 * Security:
 *   - Auth required (Sales Manager+).
 *   - CSRF check on POST.
 *   - Validates real MIME type via finfo (not the trust-the-client name).
 *   - Limits to common image types and 8 MB.
 *   - Stores under uploads/email-builder/<company_id>/ for tenant isolation.
 *   - Renames to a random filename so callers can't overwrite each other.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');
header('Content-Type: application/json');
function out($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(false, 'POST required');
}
// CSRF — accept token from either POST field or X-CSRF-Token header
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verifyCSRFToken($token)) {
    http_response_code(403);
    out(false, 'Invalid or expired request token. Please refresh the page.');
}
if (!isset($_FILES['image'])) {
    http_response_code(400);
    out(false, 'No file uploaded (field name must be "image").');
}
$file = $_FILES['image'];
// Basic upload error check
if ($file['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write file',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload',
    ];
    http_response_code(400);
    out(false, $codes[$file['error']] ?? 'Upload error');
}
// Size limit: 8 MB
$MAX_BYTES = 8 * 1024 * 1024;
if ($file['size'] > $MAX_BYTES) {
    http_response_code(400);
    out(false, 'File is too large (max 8 MB).');
}
if ($file['size'] <= 0) {
    http_response_code(400);
    out(false, 'File is empty.');
}
// MIME validation — never trust client-provided type
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']) ?: '';
if (!isset($allowedMime[$mime])) {
    http_response_code(400);
    out(false, 'Unsupported file type. Allowed: JPG, PNG, GIF, WebP, SVG.');
}
$ext = $allowedMime[$mime];
// SVG — strip any <script> tags / on* handlers to prevent XSS in inboxes/preview
if ($mime === 'image/svg+xml') {
    $raw = @file_get_contents($file['tmp_name']);
    if ($raw === false) { http_response_code(500); out(false, 'Could not read uploaded file.'); }
    $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $raw);
    $clean = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|\S+)#i', '', $clean);
    $clean = preg_replace('#xlink:href\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')#i', '', $clean);
    if (file_put_contents($file['tmp_name'], $clean) === false) {
        http_response_code(500);
        out(false, 'Could not sanitize SVG.');
    }
}
// Destination folder: uploads/email-builder/<company_id>/
$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    // Super-admin without a company: bucket under 'shared'
    $bucket = 'shared';
} else {
    $bucket = 'co' . $companyId;
}
$relDir = '/uploads/email-builder/' . $bucket;
$absDir = realpath(__DIR__ . '/..') . $relDir;
if (!is_dir($absDir)) {
    if (!@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        http_response_code(500);
        out(false, 'Could not create upload folder. Check permissions on /uploads.');
    }
}
// Random filename to avoid clobbering and to obscure
try {
    $randomName = bin2hex(random_bytes(10));
} catch (Exception $e) {
    $randomName = substr(md5(uniqid('', true)), 0, 20);
}
$basename = date('Ymd') . '_' . $randomName . '.' . $ext;
$absPath  = $absDir . '/' . $basename;
$relPath  = $relDir . '/' . $basename;
if (!move_uploaded_file($file['tmp_name'], $absPath)) {
    http_response_code(500);
    out(false, 'Could not save uploaded file.');
}
@chmod($absPath, 0644);
// Log
if (function_exists('logActivity')) {
    try {
        logActivity(
            (int)($_SESSION['user_id'] ?? 0),
            'Upload',
            'EmailBuilderAsset',
            null,
            'Uploaded ' . $basename . ' (' . $file['size'] . ' bytes, ' . $mime . ')'
        );
    } catch (Throwable $e) { /* ignore logging errors */ }
}
// Build absolute URL for use in <img src=...> inside the exported email
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? parse_url(defined('APP_URL') ? APP_URL : '', PHP_URL_HOST);
$absUrl = $scheme . '://' . $host . $relPath;
out(true, 'Uploaded', [
    'url'        => $absUrl,
    'path'       => $relPath,
    'size'       => (int)$file['size'],
    'mime'       => $mime,
    'filename'   => $basename,
]);
How to install everything
Replace assets/js/email-builder.js with File 1 from the previous message.
Create assets/css/email-builder.css with File 2 from the previous message.
Replace pages/email-builder.php with File 3 above.
Create api/email-builder-upload.php with File 4 above.
Delete assets/js/email-builder.js.backup from the repo.
Verify that uploads/ exists at the project root and is writable by the web server. The new code will auto-create uploads/email-builder/co<company_id>/ on first upload, so no manual folder creation is needed.
Hard-refresh the browser (Ctrl+Shift+R / Cmd+Shift+R) when you open the builder for the first time, so the new CSS and JS replace the cached old versions. The ?v=2 cache buster already helps with this.
That's the whole install. No database migration is needed. Existing campaigns and templates will load — their JSON is migrated on the fly to the v2 shape and will be re-saved cleanly the next time the user clicks Save (or autosave fires).
Optional: enable the "Send test email" button
The new toolbar includes a ✈ Send test email button. It POSTs to /api/email-campaigns.php?action=send_test. To make it work, your developer can add one small action to api/email-campaigns.php. Below is a copy-paste block they can drop into the existing switch ($action) in that file, just before the default: case:
case 'send_test':
    if ($method !== 'POST') jsonError('POST required');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    // CSRF
    $token = $input['csrf_token'] ?? null;
    if (!verifyCSRFToken($token)) jsonError('Invalid or expired request token', 403);
    $to      = trim($input['to'] ?? '');
    $subject = trim($input['subject'] ?? 'Test from email builder');
    $html    = $input['html'] ?? '';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) jsonError('Invalid recipient email');
    if (!$html) jsonError('Empty email content');
    // Send via Resend if configured; otherwise the developer can wire SMTP here.
    require_once __DIR__ . '/../includes/resend-email.php';
    $service = new ResendEmailService();
    if (!$service->isEnabled()) {
        jsonError('Email service not configured (RESEND_API_KEY missing).');
    }
    // Light merge-tag substitution for tests
    $html = str_replace(
        ['{{first_name}}', '{{last_name}}', '{{full_name}}', '{{email}}', '{{company_name}}', '{{sender_name}}', '{{unsubscribe_url}}', '{{view_in_browser}}'],
        ['Test',           'User',          'Test User',      $to,        'Your Company',     ($currentUser['full_name'] ?? ''), '#', '#'],
        $html
    );
    $result = $service->sendEmail($to, '[TEST] ' . $subject, $html);
    if ($result && (is_array($result) ? !empty($result['success']) : $result === true)) {
        jsonSuccess('Test sent');
    } else {
        jsonError('Failed to send test email. Check the server log.');
    }
    break;
What's new (feature tour)
A quick rundown of what's available now so you and your developer know what to look for:
Editor surface
Three-pane Mailchimp/Zoho-style layout: palette on the left, large editable canvas in the middle, settings panel on the right.
Hover any section to reveal a small toolbar with move up, move down, duplicate, settings, delete.
Hover any block to reveal a smaller toolbar with drag handle, duplicate, delete.
Clicking the empty area selects the body. Clicking a section selects that section. Clicking a block selects it.
The selection is shown with a brand-colored outline.
Drag-and-drop
One system: SortableJS only. Palette items are clones — dragging them into the canvas creates a fresh instance and the palette stays intact.
Drop a section template onto the canvas to add it.
Drop a block onto any column. Empty columns show a "Drag a block here" hint so users always know where targets are.
Reorder sections by dragging them up/down. Reorder blocks within or between columns. Stacking and stack-on-mobile is respected.
Blocks available
Heading (with H1–H4 level switcher and a separate mobile font-size)
Text (rich text with floating inline toolbar)
Image (with link URL, alt text, width %, border radius, border, alignment, padding)
Button (with full MSO/Outlook VML fallback so it renders perfectly in Outlook for Windows)
Divider (style, thickness, width %, alignment)
Spacer (height, optional background)
Social (Facebook, Twitter, LinkedIn, Instagram, YouTube — per-platform enable + URL)
Video (clickable thumbnail linking to YouTube/Vimeo)
HTML (custom block for power users)
Footer (pre-styled with unsubscribe + view-in-browser merge tags)
Pre-built section templates (in the top of the palette)
Hero
Image + Text
3-Feature Grid
Call to Action
Footer + Social
Inline text editor (Mailchimp-style floating toolbar) When you click into a text/heading/footer block, a small dark floating toolbar appears: bold, italic, underline, strikethrough, bulleted list, numbered list, align left/center/right, insert link, text color, font size, clear formatting. You can also insert any merge tag from the right panel at the current cursor position.
Merge tags Click any merge tag in the properties panel to insert it at the caret. Available: {{first_name}}, {{last_name}}, {{full_name}}, {{email}}, {{company_name}}, {{country}}, {{city}}, {{unsubscribe_url}}, {{view_in_browser}}, {{sender_name}}, {{sender_email}}. The button's URL field also accepts merge tags.
Image library / upload Every image picker has an "⬆ Upload from computer" button that posts to api/email-builder-upload.php. Files are stored under uploads/email-builder/co<your_company_id>/, validated by real MIME type, max 8 MB, SVGs are sanitized to strip scripts. The returned absolute URL is dropped into the block automatically.
Preview
Top-right 👁 button toggles a true preview iframe.
The toolbar has Desktop / Tablet / Mobile width buttons that actually resize the preview frame (640 / 480 / 360 px).
Dark-mode preview button simulates how subscribers in dark mode will see your email.
The "{ }" button opens a modal that shows the exact exported HTML, with byte count and a warning if you go over Gmail's 102 KB clipping threshold. Buttons to copy or download as .html.
Email-safe HTML export The exporter is now table-based, with role="presentation", MSO conditional comments for Outlook, VML round-rect fallback for buttons (so buttons render as buttons in Outlook for Windows, not broken images), inline styles everywhere, and a <head> block with the preheader hidden div, dark-mode meta hints, and a small responsive stylesheet that:
Stacks multi-column sections on phones (≤ 480 px)
Honors "Hide on mobile" per-section and per-block
Switches body background to dark when the recipient's client is in dark mode and supports prefers-color-scheme
Undo / Redo / Autosave / Local backup
Ctrl/Cmd + Z = undo. Ctrl/Cmd + Y or Shift + Z = redo. History keeps 60 steps.
Ctrl/Cmd + S = save now.
Ctrl/Cmd + D = duplicate selected block or section.
Delete key = delete selected block or section (won't fire while you're typing).
Autosave fires 4 seconds after the last edit and shows "Saving…" / "Saved" in the status indicator.
Every snapshot is also backed up to localStorage so a browser crash mid-edit doesn't lose work.
Helpful warnings
Images without alt text show a small "Missing alt text" pill — important for accessibility and to avoid spam-folder penalties.
Buttons with poor contrast (WCAG below 4.5:1) show a "Low contrast 3.2:1" warning pill.
HTML size shown next to the View-HTML modal, in red over 102 KB.
RTL behavior (Arabic / Hebrew)
You asked for RTL. Here's exactly what it does:
A toolbar button ⇆ toggles between LTR and RTL on the fly. The same setting is also available in the right-hand body panel as a dropdown ("Text direction").
When RTL is on:
The whole editor flips — palette is now on the right, properties panel on the left.
The canvas gets dir="rtl", so default text alignment flows from the right.
Hint text in empty columns ("Drag a block here") shows in Arabic.
Column gap padding is applied to the right side instead of the left, so 2-column layouts look correct.
The exported HTML's <html dir="rtl" lang="ar"> is set, and the same right-side gap rule is applied in the exported tables — so the email itself renders correctly in Outlook, Gmail, and Apple Mail for Arabic recipients.
The font palette now includes Cairo and Tajawal — two widely-supported Arabic-friendly system stacks. Pick either as the body font when sending to Arabic audiences.
Editing rich text inside RTL works as expected: typing flows right-to-left, but you can still mix Latin and Arabic in the same paragraph and the bidi algorithm sorts it out (browsers handle this natively in contenteditable).
A campaign authored in LTR can be switched to RTL later — the existing content stays put, only the direction flips. The reverse is also true.
Selectable per-email: you can have one tenant sending some campaigns in LTR English and others in RTL Arabic without changing anything globally.
One caveat worth flagging: if a user pastes RTL text into a block whose alignment is set to left, the text aligns left but reads right-to-left — that's correct behavior, just sometimes surprising. The properties panel always shows the explicit alignment so they can change it.
QA checklist — run this after deploying
Have your developer (or yourself) walk through these in order. Each one tests something different.
Open a fresh template. Go to /pages/email-templates.php, click "New", then "Open in builder". You should land on the new builder with a Hero section pre-seeded.
Drag a section template from the palette into the canvas above or below the hero. It should slot in cleanly with a smooth animation.
Drag a Button block from the palette into any column. Click it. The right panel should show button-specific properties (label, URL, colors, padding, alignment).
Set the button URL to a real URL and tap the Preview button (👁). The button in the preview iframe should be clickable.
Add an Image block. Click "⬆ Upload from computer", choose a PNG/JPG, and confirm it uploads and appears in the canvas. Set a link URL — confirm the small "↗" overlay appears in the editor.
Toggle Mobile preview (📱̇). The preview frame should shrink to 360 px.
Enable "Hide on mobile" on a section. In mobile preview, the section disappears. In desktop preview, it returns.
Toggle dark-mode preview (🌗). The preview frame backgrounds shift to dark.
Click the "{ }" button in the toolbar. The HTML modal should show valid HTML with <!doctype html>, MSO comments, and inline styles.
Copy the HTML, paste it into https://www.htmlemailcheck.com/check/ or a similar tool, and confirm there are no critical email-client errors.
Switch direction to RTL with the ⇆ button. The editor flips. Type Arabic text into a heading. Switch back to LTR — content is preserved.
Test undo/redo. Make 5 edits. Ctrl/Cmd+Z four times. Last edit should remain undone. Ctrl/Cmd+Y once. It comes back.
Test autosave. Make an edit. Wait 5 seconds. The status indicator should say "Saving…" then "Saved". Refresh the page. The edit persisted.
Click "✈ Send test email" (if you added the optional endpoint above). Enter your email. Check inbox: button renders as a button, image displays, merge tags are replaced with test values.
Open in two browsers as different tenants (if you have a second test company). Upload an image from each. Confirm files go to uploads/email-builder/co<id>/ separately and one tenant cannot see the other's URL.
Permissions test. Try to access /api/email-builder-upload.php as a Sales Rep (not Sales Manager). Should get 403.
If any of those fail, send me the error message or a screenshot — the codebase is consistent so the fix will usually be one targeted change.