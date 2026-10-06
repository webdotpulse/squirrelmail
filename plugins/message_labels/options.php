<?php
/**
 * Message Flags & Labels - Management & Options Page
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage message_labels
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'plugins/message_labels/labels.php');

$msg = null;

// Handle create new label
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_label') {
    $name = trim($_POST['label_name'] ?? '');
    $color = trim($_POST['label_color'] ?? '#1a73e8');
    if (!empty($name)) {
        ml_save_label('', $name, $color);
        $msg = sprintf(_("Label '%s' created successfully!"), htmlspecialchars($name));
    }
}

// Handle delete label
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = trim($_GET['id']);
    ml_delete_label($delId);
    $msg = _("Label deleted successfully.");
}

$data = ml_load_data();
$counts = ml_get_label_counts();

displayPageHeader($color, 'None');
?>
<style>
.ml-container {
    max-width: 800px;
    margin: 24px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #202124;
}
.ml-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dadce0;
}
.ml-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ml-card {
    background: #ffffff;
    border: 1px solid #dadce0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.ml-card-title {
    font-size: 16px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ml-table {
    width: 100%;
    border-collapse: collapse;
}
.ml-table th {
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    color: #5f6368;
    padding: 10px 12px;
    border-bottom: 2px solid #e8eaed;
}
.ml-table td {
    padding: 12px;
    border-bottom: 1px solid #f1f3f4;
    font-size: 14px;
    vertical-align: middle;
}
.ml-badge-preview {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.ml-input {
    padding: 8px 12px;
    border: 1px solid #dadce0;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
}
.ml-input:focus {
    border-color: #1a73e8;
}
.ml-btn-primary {
    padding: 8px 18px;
    background: #1a73e8;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}
.ml-btn-del {
    color: #d93025;
    text-decoration: none;
    font-size: 13px;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.15s;
}
.ml-btn-del:hover {
    background: #fce8e6;
}
</style>

<div class="ml-container">
    <div class="ml-header">
        <h2>
            <span>🏷️</span>
            <span><?php echo _("Message Flags &amp; Labels"); ?></span>
        </h2>
    </div>

    <?php if ($msg): ?>
    <div style="background: #e6f4ea; color: #137333; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ceead6;">
        ✅ <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- ADD LABEL CARD -->
    <div class="ml-card">
        <h3 class="ml-card-title">➕ <?php echo _("Create New Label"); ?></h3>
        <form method="post" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="action" value="add_label">
            <input type="text" name="label_name" class="ml-input" placeholder="<?php echo _("Label name (e.g. Clients, Taxes)"); ?>" required style="flex: 2; min-width: 200px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 13px; font-weight: 500;"><?php echo _("Color:"); ?></label>
                <input type="color" name="label_color" value="#1a73e8" style="width: 36px; height: 36px; border: none; border-radius: 50%; cursor: pointer;">
            </div>
            <button type="submit" class="ml-btn-primary"><?php echo _("Add Label"); ?></button>
        </form>
    </div>

    <!-- EXISTING LABELS CARD -->
    <div class="ml-card">
        <h3 class="ml-card-title">🏷️ <?php echo _("Active Labels &amp; Message Counts"); ?></h3>
        <table class="ml-table">
            <thead>
                <tr>
                    <th><?php echo _("Label"); ?></th>
                    <th><?php echo _("Preview"); ?></th>
                    <th><?php echo _("Messages"); ?></th>
                    <th style="text-align: right;"><?php echo _("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['labels'] as $lid => $lDef):
                    $cCount = isset($counts[$lid]) ? $counts[$lid] : 0;
                    $bg = !empty($lDef['bg']) ? $lDef['bg'] : $lDef['color'] . '20';
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($lDef['name']); ?></strong></td>
                    <td>
                        <span class="ml-badge-preview" style="background: <?php echo htmlspecialchars($bg); ?>; color: <?php echo htmlspecialchars($lDef['color']); ?>;">
                            ● <?php echo htmlspecialchars($lDef['name']); ?>
                        </span>
                    </td>
                    <td><span style="color: #5f6368;"><?php echo $cCount; ?> message(s)</span></td>
                    <td style="text-align: right;">
                        <a href="options.php?action=delete&id=<?php echo urlencode($lid); ?>" class="ml-btn-del" onclick="return confirm('Delete this label? (Messages will not be deleted)')">
                            ✕ <?php echo _("Delete"); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
echo "</body></html>\n";
