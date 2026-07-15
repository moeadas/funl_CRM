<!-- Footer -->
<footer style="padding: 20px; text-align: center; color: #9ca3af; font-size: 13px; border-top: 1px solid #f0f0f0; margin-top: 40px;">
    <p><?php echo htmlspecialchars(getAppName()); ?> &copy; <?= date('Y') ?><?php echo __('.&nbsp;All rights reserved.'); ?></p>
</footer>
<?php
/**
 * Page-specific scripts.
 *
 * Pages declare their scripts as e.g. $js = ['settings']; but nothing ever
 * loaded them, so those files were dead. Emit them here, guarded by
 * file_exists() so the pages that declare a script with no matching file
 * (contacts, tasks, deals, ...) don't produce 404s.
 */
if (!empty($js) && is_array($js)) {
    foreach ($js as $script) {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', (string)$script);
        if ($safe === '') continue;
        $rel  = '/assets/js/' . $safe . '.js';
        $abs  = __DIR__ . '/..' . $rel;
        if (is_file($abs)) {
            echo '<script src="' . $rel . '?v=' . (int)@filemtime($abs) . '" defer></script>' . "\n";
        }
    }
}
?>
