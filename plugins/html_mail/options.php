<?php
/**
 * HTML Mail Plugin - Options Page
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage html_mail
 */

require('../../include/init.php');

global $data_dir, $username, $color;

// Security check: Must be logged in
if (empty($username)) {
    exit;
}

$updated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_html_mail_options'])) {
    $mode = isset($_POST['html_mail_default']) && $_POST['html_mail_default'] === '1' ? '1' : '0';
    $font = isset($_POST['html_mail_font']) ? trim($_POST['html_mail_font']) : 'sans-serif';
    $size = isset($_POST['html_mail_size']) ? trim($_POST['html_mail_size']) : '14px';

    setPref($data_dir, $username, 'html_mail_default', $mode);
    setPref($data_dir, $username, 'html_mail_font', $font);
    setPref($data_dir, $username, 'html_mail_size', $size);
    $updated = true;
}

$current_mode = getPref($data_dir, $username, 'html_mail_default', '1');
$current_font = getPref($data_dir, $username, 'html_mail_font', 'sans-serif');
$current_size = getPref($data_dir, $username, 'html_mail_size', '14px');

displayPageHeader($color, 'None');
?>

<div style="max-width: 650px; margin: 20px auto; padding: 24px; background: #ffffff; border: 1px solid #dadce0; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ebebeb; padding-bottom: 14px; margin-bottom: 20px;">
        <h2 style="margin: 0; font-size: 20px; color: #202124; display: flex; align-items: center; gap: 8px;">
            <span>🎨</span> HTML Mail Settings
        </h2>
        <a href="../../src/options.php" style="color: #1a73e8; text-decoration: none; font-size: 13px;">&larr; Return to Options</a>
    </div>

    <?php if ($updated): ?>
        <div style="background: #e6f4ea; color: #137333; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border: 1px solid #ceead6;">
            ✓ Your HTML Mail preferences have been updated successfully!
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #3c4043;">
                Default Compose Mode
            </label>
            <div style="display: flex; gap: 20px; margin-top: 8px;">
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="html_mail_default" value="1" <?php if ($current_mode === '1') echo 'checked'; ?>>
                    <span><strong>Rich HTML</strong> (WYSIWYG editor with formatting toolbar)</span>
                </label>
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="html_mail_default" value="0" <?php if ($current_mode === '0') echo 'checked'; ?>>
                    <span><strong>Plain Text</strong> (Traditional textarea)</span>
                </label>
            </div>
            <div style="font-size: 12px; color: #70757a; margin-top: 4px;">
                You can always toggle dynamically between Rich HTML and Plain Text modes directly in the compose window.
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="html_mail_font" style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #3c4043;">
                Default Editor Font Family
            </label>
            <select name="html_mail_font" id="html_mail_font" style="width: 100%; max-width: 320px; padding: 8px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px;">
                <option value="sans-serif" <?php if ($current_font === 'sans-serif') echo 'selected'; ?>>Modern Sans-Serif (System font)</option>
                <option value="Arial, Helvetica, sans-serif" <?php if ($current_font === 'Arial, Helvetica, sans-serif') echo 'selected'; ?>>Arial</option>
                <option value="'Roboto', sans-serif" <?php if ($current_font === "'Roboto', sans-serif") echo 'selected'; ?>>Roboto</option>
                <option value="'Segoe UI', Tahoma, sans-serif" <?php if ($current_font === "'Segoe UI', Tahoma, sans-serif") echo 'selected'; ?>>Segoe UI</option>
                <option value="Georgia, serif" <?php if ($current_font === 'Georgia, serif') echo 'selected'; ?>>Georgia (Serif)</option>
                <option value="'Courier New', Courier, monospace" <?php if ($current_font === "'Courier New', Courier, monospace") echo 'selected'; ?>>Courier New (Monospace)</option>
            </select>
        </div>

        <div style="margin-bottom: 24px;">
            <label for="html_mail_size" style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #3c4043;">
                Default Editor Font Size
            </label>
            <select name="html_mail_size" id="html_mail_size" style="width: 100%; max-width: 320px; padding: 8px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px;">
                <option value="13px" <?php if ($current_size === '13px') echo 'selected'; ?>>Small (13px)</option>
                <option value="14px" <?php if ($current_size === '14px') echo 'selected'; ?>>Standard (14px)</option>
                <option value="16px" <?php if ($current_size === '16px') echo 'selected'; ?>>Medium (16px)</option>
                <option value="18px" <?php if ($current_size === '18px') echo 'selected'; ?>>Large (18px)</option>
            </select>
        </div>

        <div style="border-top: 1px solid #ebebeb; padding-top: 16px; display: flex; gap: 12px;">
            <input type="submit" name="save_html_mail_options" value="Save Preferences" style="background: #1a73e8; color: #ffffff; border: none; padding: 10px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px;">
            <a href="../../src/options.php" style="background: #f1f3f4; color: #3c4043; border: 1px solid #dadce0; padding: 9px 18px; border-radius: 4px; font-weight: 500; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center;">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>
