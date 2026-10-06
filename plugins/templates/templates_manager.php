<?php
/**
 * Email Templates & Attachments Management Page
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage templates
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'plugins/templates/templates_data.php');

$msg = null;
$editTpl = null;

// Handle delete template
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    tpl_delete_template(trim($_GET['id']));
    $msg = _("Template deleted successfully.");
}

// Handle delete single attachment from template
if (isset($_GET['action']) && $_GET['action'] === 'del_att' && isset($_GET['id']) && isset($_GET['att_idx'])) {
    tpl_delete_attachment(trim($_GET['id']), intval($_GET['att_idx']));
    $msg = _("Attachment removed from template.");
}

// Handle save template (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $id = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (!empty($title)) {
        $tplData = array(
            'id'       => $id,
            'title'    => $title,
            'category' => $category,
            'subject'  => $subject,
            'body'     => $body
        );

        $savedId = tpl_save_template($tplData, isset($_FILES['attachments']) ? $_FILES['attachments'] : null);
        $msg = sprintf(_("Template '%s' saved successfully!"), htmlspecialchars($title));
    }
}

// Check if editing specific template
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $all = tpl_load_templates();
    if (isset($all[$_GET['edit']])) {
        $editTpl = $all[$_GET['edit']];
    }
}

$templates = tpl_load_templates();

displayPageHeader($color, 'None');
?>
<style>
.tm-container {
    max-width: 960px;
    margin: 24px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #202124;
}
.tm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dadce0;
}
.tm-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.tm-card {
    background: #ffffff;
    border: 1px solid #dadce0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.tm-card-title {
    font-size: 16px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tm-input, .tm-select, .tm-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 14px;
    border: 1px solid #dadce0;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.tm-input:focus, .tm-select:focus, .tm-textarea:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 2px rgba(26,115,232,0.2);
}
.tm-row-2 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.tm-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #3c4043;
    margin-bottom: 6px;
}
.tm-btn-primary {
    padding: 10px 22px;
    background: #1a73e8;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
}
.tm-btn-primary:hover {
    background: #1557b0;
}
.tm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}
.tm-item-card {
    border: 1px solid #dadce0;
    border-radius: 10px;
    padding: 16px;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: box-shadow 0.15s;
}
.tm-item-card:hover {
    box-shadow: 0 4px 12px rgba(60,64,67,0.12);
}
.tm-category-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    background: #e8f0fe;
    color: #1a73e8;
    margin-bottom: 8px;
}
.tm-att-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: #f1f3f4;
    border-radius: 6px;
    font-size: 12px;
    color: #3c4043;
    margin-right: 6px;
    margin-top: 4px;
}
</style>

<div class="tm-container">
    <div class="tm-header">
        <h2>
            <span>📋</span>
            <span><?php echo _("Email Templates &amp; Attachments"); ?></span>
        </h2>
        <a href="#template-form" class="tm-btn-primary" onclick="resetForm()">+ <?php echo _("New Template"); ?></a>
    </div>

    <?php if ($msg): ?>
    <div style="background: #e6f4ea; color: #137333; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ceead6;">
        ✅ <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- LIST OF TEMPLATES -->
    <div class="tm-card">
        <h3 class="tm-card-title">📚 <?php echo _("Available Templates"); ?></h3>
        <div class="tm-grid">
            <?php foreach ($templates as $tplId => $tpl): ?>
            <div class="tm-item-card">
                <div>
                    <span class="tm-category-badge"><?php echo htmlspecialchars($tpl['category'] ?? 'General'); ?></span>
                    <h4 style="margin: 0 0 6px; font-size: 15px; color: #202124;"><?php echo htmlspecialchars($tpl['title']); ?></h4>
                    <div style="font-size: 12px; color: #5f6368; margin-bottom: 8px;">
                        <?php echo !empty($tpl['subject']) ? '<strong>Subject:</strong> ' . htmlspecialchars($tpl['subject']) : '<em>(No default subject)</em>'; ?>
                    </div>
                    <div style="font-size: 13px; color: #3c4043; line-height: 1.4; white-space: pre-wrap; max-height: 70px; overflow: hidden; text-overflow: ellipsis; background: #fafbfc; padding: 8px; border-radius: 6px;">
                        <?php echo htmlspecialchars(substr($tpl['body'], 0, 160)) . '...'; ?>
                    </div>

                    <!-- ATTACHMENTS LIST -->
                    <?php if (!empty($tpl['attachments'])): ?>
                    <div style="margin-top: 10px;">
                        <span style="font-size: 11px; font-weight: 700; color: #5f6368; text-transform: uppercase;">📎 Attached Files:</span>
                        <div style="display: flex; flex-wrap: wrap; margin-top: 4px;">
                            <?php foreach ($tpl['attachments'] as $attIdx => $att): ?>
                            <span class="tm-att-chip">
                                <span>📄</span>
                                <strong><?php echo htmlspecialchars($att['filename']); ?></strong>
                                <small style="color: #70757a;">(<?php echo round($att['size']/1024); ?> KB)</small>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid #f1f3f4;">
                    <a href="templates_manager.php?edit=<?php echo urlencode($tplId); ?>#template-form" style="font-size: 13px; color: #1a73e8; text-decoration: none; font-weight: 500;">
                        ✏️ <?php echo _("Edit"); ?>
                    </a>
                    <a href="templates_manager.php?action=delete&id=<?php echo urlencode($tplId); ?>" style="font-size: 13px; color: #d93025; text-decoration: none;" onclick="return confirm('Delete this template and its attachments?')">
                        ✕ <?php echo _("Delete"); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CREATE / EDIT TEMPLATE FORM -->
    <div class="tm-card" id="template-form">
        <h3 class="tm-card-title">
            <span>✏️</span>
            <span><?php echo $editTpl ? _("Edit Template") : _("Create New Template with Attachments"); ?></span>
        </h3>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editTpl['id'] ?? ''); ?>">

            <div class="tm-row-2">
                <div>
                    <label class="tm-label"><?php echo _("Template Name"); ?></label>
                    <input type="text" name="title" class="tm-input" placeholder="e.g. Sales Quote & Brochure" required value="<?php echo htmlspecialchars($editTpl['title'] ?? ''); ?>">
                </div>
                <div>
                    <label class="tm-label"><?php echo _("Category"); ?></label>
                    <input type="text" name="category" class="tm-input" placeholder="e.g. Sales, Support, Meetings" value="<?php echo htmlspecialchars($editTpl['category'] ?? 'General'); ?>">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label class="tm-label"><?php echo _("Default Subject Line"); ?> <small style="font-weight: normal; color: #70757a;">(Optional)</small></label>
                <input type="text" name="subject" class="tm-input" placeholder="e.g. Follow up on our meeting - {subject}" value="<?php echo htmlspecialchars($editTpl['subject'] ?? ''); ?>">
            </div>

            <div style="margin-bottom: 16px;">
                <label class="tm-label"><?php echo _("Template Body"); ?></label>
                <textarea name="body" class="tm-textarea" rows="8" placeholder="Type your template text here..."><?php echo htmlspecialchars($editTpl['body'] ?? ''); ?></textarea>
                <div style="font-size: 12px; color: #5f6368; margin-top: 4px;">
                    Placeholders available: <code>{name}</code>, <code>{email}</code>, <code>{date}</code>, <code>{subject}</code>, <code>{my_name}</code>, <code>{my_email}</code>.
                </div>
            </div>

            <!-- EXISTING ATTACHMENTS (if editing) -->
            <?php if ($editTpl && !empty($editTpl['attachments'])): ?>
            <div style="margin-bottom: 16px;">
                <label class="tm-label"><?php echo _("Current Template Attachments"); ?></label>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    <?php foreach ($editTpl['attachments'] as $idx => $att): ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #dadce0;">
                        <span>📄 <strong><?php echo htmlspecialchars($att['filename']); ?></strong> <small style="color: #70757a;">(<?php echo round($att['size']/1024); ?> KB)</small></span>
                        <a href="templates_manager.php?action=del_att&id=<?php echo urlencode($editTpl['id']); ?>&att_idx=<?php echo $idx; ?>" style="color: #d93025; font-size: 12px; text-decoration: none;" onclick="return confirm('Remove this file from the template?')">
                            ✕ <?php echo _("Remove"); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ATTACH NEW FILES -->
            <div style="margin-bottom: 20px;">
                <label class="tm-label">📎 <?php echo _("Add Attachments to this Template"); ?> <small style="font-weight: normal; color: #70757a;">(PDFs, documents, price lists, images, etc.)</small></label>
                <input type="file" name="attachments[]" multiple style="display: block; padding: 10px; border: 1px dashed #dadce0; border-radius: 6px; width: 100%; box-sizing: border-box; background: #fafbfc;">
                <div style="font-size: 12px; color: #5f6368; margin-top: 4px;">
                    When this template is selected in Compose or Reply, all attached files will automatically attach to your email!
                </div>
            </div>

            <div>
                <button type="submit" name="save_template" value="1" class="tm-btn-primary">
                    💾 <?php echo _("Save Template"); ?>
                </button>
                <?php if ($editTpl): ?>
                    <a href="templates_manager.php" style="margin-left: 12px; color: #5f6368; font-size: 13px; text-decoration: none;"><?php echo _("Cancel Edit"); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    var form = document.querySelector('#template-form form');
    if (form) form.reset();
}
</script>
<?php
echo "</body></html>\n";
