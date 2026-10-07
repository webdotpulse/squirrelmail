<?php
/**
 * Email Templates & Attachments Management Page
 *
 * Supports creating and editing templates in HTML and Plain Text,
 * with live HTML previews, dynamic variable tags, and file attachments.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage templates
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'functions/identity.php');
include_once(SM_PATH . 'plugins/templates/templates_data.php');

$msg = null;
$msg_type = 'success';
$editTpl = null;
$mgrUrl = sqm_baseuri() . 'plugins/templates/templates_manager.php';

// Handle delete template
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    tpl_delete_template(trim($_GET['id']));
    $msg = _("Template deleted successfully.");
    $msg_type = 'success';
}

// Handle delete single attachment from template
if (isset($_GET['action']) && $_GET['action'] === 'del_att' && isset($_GET['id']) && isset($_GET['att_idx'])) {
    tpl_delete_attachment(trim($_GET['id']), intval($_GET['att_idx']));
    $msg = _("Attachment removed from template.");
    $msg_type = 'success';
}

// Handle save template (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $id       = trim($_POST['id'] ?? '');
    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $subject  = trim($_POST['subject'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $is_html  = isset($_POST['is_html']) ? intval($_POST['is_html']) : 1;

    if (!empty($title)) {
        $tplData = array(
            'id'       => $id,
            'title'    => $title,
            'category' => $category,
            'subject'  => $subject,
            'body'     => $body,
            'is_html'  => $is_html
        );

        $savedId = tpl_save_template($tplData, isset($_FILES['attachments']) ? $_FILES['attachments'] : null);
        $msg = sprintf(_("Template '%s' saved successfully!"), htmlspecialchars($title));
        $msg_type = 'success';
    }
}

// Check if editing specific template
$editId = $_GET['edit'] ?? (isset($_GET['action']) && $_GET['action'] === 'del_att' ? ($_GET['id'] ?? '') : '');
if (!empty($editId)) {
    $all = tpl_load_templates();
    if (isset($all[$editId])) {
        $editTpl = $all[$editId];
    }
}

$templates = tpl_load_templates();

// Identity for preview simulation
$idents = get_identities();
$previewMyName = !empty($idents[0]['full_name']) ? $idents[0]['full_name'] : $username;
$previewMyEmail = !empty($idents[0]['email_address']) ? $idents[0]['email_address'] : $username;

displayPageHeader($color, 'None');
?>
<style>
.tm-container {
    max-width: 1020px;
    margin: 24px auto;
    font-family: var(--sm-font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
    color: var(--sm-text-primary, #202124);
}
.tm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--sm-border, #dadce0);
}
.tm-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--sm-text-primary, #202124);
}
.tm-card {
    background: var(--sm-bg-card, #ffffff);
    border: 1px solid var(--sm-border, #dadce0);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--sm-shadow-sm, 0 1px 3px rgba(60,64,67,0.08));
}
.tm-card-title {
    font-size: 16px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--sm-text-primary, #202124);
}
.tm-input, .tm-select, .tm-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 14px;
    background: var(--sm-bg-surface, #ffffff);
    color: var(--sm-text-primary, #202124);
    border: 1px solid var(--sm-border, #dadce0);
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    font-family: inherit;
}
.tm-input:focus, .tm-select:focus, .tm-textarea:focus {
    border-color: var(--sm-primary, #1a73e8);
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
    color: var(--sm-text-secondary, #3c4043);
    margin-bottom: 6px;
}
.tm-btn-primary,
.tm-btn-primary:link,
.tm-btn-primary:visited,
a.tm-btn-primary,
a.tm-btn-primary:link,
a.tm-btn-primary:visited {
    padding: 10px 22px;
    background: var(--sm-primary, #1a73e8);
    color: #ffffff !important;
    border: 1px solid var(--sm-primary, #1a73e8);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    -webkit-font-smoothing: antialiased;
}
.tm-btn-primary:hover,
.tm-btn-primary:focus,
.tm-btn-primary:active,
a.tm-btn-primary:hover,
a.tm-btn-primary:focus,
a.tm-btn-primary:active {
    background: var(--sm-primary-hover, #1557b0);
    border-color: var(--sm-primary-hover, #1557b0);
    color: #ffffff !important;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.tm-btn-secondary,
.tm-btn-secondary:link,
.tm-btn-secondary:visited,
a.tm-btn-secondary,
a.tm-btn-secondary:link,
a.tm-btn-secondary:visited {
    padding: 6px 12px;
    background: var(--sm-bg-card, #f1f3f4);
    color: var(--sm-text-primary, #3c4043) !important;
    border: 1px solid var(--sm-border, #dadce0);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    transition: all 0.15s ease;
}
.tm-btn-secondary:hover,
.tm-btn-secondary:focus,
.tm-btn-secondary:active,
a.tm-btn-secondary:hover,
a.tm-btn-secondary:focus,
a.tm-btn-secondary:active {
    background: var(--sm-hover-bg, #e8f0fe);
    color: var(--sm-primary, #1a73e8) !important;
    border-color: var(--sm-primary-border, #aecbfa);
    text-decoration: none !important;
}
.tm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
    gap: 16px;
}
.tm-item-card {
    border: 1px solid var(--sm-border, #dadce0);
    border-radius: 10px;
    padding: 16px;
    background: var(--sm-bg-card, #ffffff);
    color: var(--sm-text-primary, #202124);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: box-shadow 0.15s;
}
.tm-item-card:hover {
    box-shadow: var(--sm-shadow-md, 0 4px 12px rgba(60,64,67,0.12));
}
.tm-category-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    background: #e8f0fe;
    color: #1a73e8;
    margin-right: 6px;
}
.tm-html-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    background: #e6f4ea;
    color: #137333;
}
.tm-att-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 8px;
    background: #f1f3f4;
    border-radius: 6px;
    font-size: 11px;
    color: #3c4043;
    margin-right: 4px;
    margin-top: 4px;
}

/* Rich Editor Styling */
.tm-editor-container {
    border: 1px solid #dadce0;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
}
.tm-editor-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 3px;
    padding: 8px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #dadce0;
}
.tm-tb-group {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    border-right: 1px solid #e2e8f0;
    padding-right: 5px;
    margin-right: 4px;
}
.tm-tb-btn {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 0 6px;
    height: 28px;
    min-width: 28px;
    font-size: 12px;
    font-weight: 500;
    color: #334155;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.tm-tb-btn:hover {
    background: #e0f2fe;
    color: #0369a1;
    border-color: #7dd3fc;
}
.tm-tb-select {
    height: 28px;
    padding: 0 6px;
    font-size: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
}
.tm-chips-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12px;
}
.tm-tag-chip {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #2563eb;
    border-radius: 4px;
    padding: 2px 8px;
    font-family: monospace;
    font-size: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.15s;
}
.tm-tag-chip:hover {
    background: #eff6ff;
    border-color: #3b82f6;
}
.tm-wysiwyg-area {
    min-height: 200px;
    max-height: 480px;
    overflow-y: auto;
    padding: 14px 16px;
    outline: none;
    font-size: 14px;
    line-height: 1.6;
    color: #1e293b;
}
.tm-code-area {
    display: none;
    width: 100%;
    min-height: 200px;
    padding: 12px 14px;
    box-sizing: border-box;
    border: none;
    font-family: monospace;
    font-size: 13px;
    outline: none;
    resize: vertical;
    background: #f8fafc;
    color: #0f172a;
}

/* Live Preview Box */
.tm-preview-card {
    background: #f8fafc;
    border: 1px dashed #94a3b8;
    border-radius: 8px;
    padding: 16px;
    margin-top: 16px;
}
.tm-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}
.tm-preview-body {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 16px;
    min-height: 80px;
    color: #1e293b;
    font-size: 14px;
    line-height: 1.5;
}

/* Modal Popup */
.tm-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(2px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}
.tm-modal-content {
    background: var(--sm-bg-card, #ffffff);
    color: var(--sm-text-primary, #0f172a);
    width: 90%;
    max-width: 720px;
    max-height: 85vh;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: tmFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes tmFadeIn {
    from { opacity: 0; transform: scale(0.96) translateY(-10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.tm-modal-header {
    padding: 16px 20px;
    background: var(--sm-bg-surface, #f8fafc);
    border-bottom: 1px solid var(--sm-border, #e2e8f0);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tm-modal-body {
    padding: 20px;
    overflow-y: auto;
    background: var(--sm-bg-card, #ffffff);
    color: var(--sm-text-primary, #0f172a);
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
    <div style="background: <?php echo ($msg_type === 'error' ? '#fce8e6' : '#e6f4ea'); ?>; color: <?php echo ($msg_type === 'error' ? '#c5221f' : '#137333'); ?>; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid <?php echo ($msg_type === 'error' ? '#fad2cf' : '#ceead6'); ?>;">
        <?php echo ($msg_type === 'error' ? '⚠️ ' : '✅ '); ?><?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- LIST OF TEMPLATES -->
    <div class="tm-card">
        <h3 class="tm-card-title">📚 <?php echo _("Available Templates"); ?> (<?php echo count($templates); ?>)</h3>
        <div class="tm-grid">
            <?php foreach ($templates as $tplId => $tpl): ?>
            <div class="tm-item-card">
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <div>
                            <span class="tm-category-badge"><?php echo htmlspecialchars($tpl['category'] ?? 'General'); ?></span>
                            <?php if (!isset($tpl['is_html']) || $tpl['is_html'] == 1 || preg_match('/<[a-z][\s\S]*>/i', $tpl['body'] ?? '')): ?>
                                <span class="tm-html-badge">HTML</span>
                            <?php else: ?>
                                <span style="font-size: 10px; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">Plain Text</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h4 style="margin: 0 0 6px; font-size: 15px; color: #202124;"><?php echo htmlspecialchars($tpl['title']); ?></h4>
                    <div style="font-size: 12px; color: #5f6368; margin-bottom: 8px;">
                        <?php echo !empty($tpl['subject']) ? '<strong>Subject:</strong> ' . htmlspecialchars($tpl['subject']) : '<em>(No default subject)</em>'; ?>
                    </div>
                    <div style="font-size: 13px; color: #3c4043; line-height: 1.4; white-space: pre-wrap; max-height: 70px; overflow: hidden; text-overflow: ellipsis; background: #fafbfc; padding: 8px; border-radius: 6px;">
                        <?php echo htmlspecialchars(substr(strip_tags($tpl['body']), 0, 160)) . '...'; ?>
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

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid #f1f3f4; gap: 8px;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="<?php echo htmlspecialchars($mgrUrl . '?edit=' . urlencode($tplId) . '#template-form'); ?>" style="font-size: 13px; color: #1a73e8; text-decoration: none; font-weight: 500;">
                            ✏️ <?php echo _("Edit"); ?>
                        </a>
                        <button type="button" class="tm-btn-secondary" style="padding: 2px 8px; font-size: 12px;" onclick='openPreviewModal(<?php echo htmlspecialchars(json_encode($tpl), ENT_QUOTES, "UTF-8"); ?>)'>
                            👁️ <?php echo _("Preview HTML"); ?>
                        </button>
                    </div>
                    <a href="<?php echo htmlspecialchars($mgrUrl . '?action=delete&id=' . urlencode($tplId)); ?>" style="font-size: 13px; color: #d93025; text-decoration: none;" onclick="return confirm('Delete this template and its attachments?')">
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
            <span><?php echo $editTpl ? _("Edit Template (HTML)") : _("Create New Template in HTML"); ?></span>
        </h3>

        <form method="post" action="<?php echo htmlspecialchars($mgrUrl); ?>" enctype="multipart/form-data" id="main-template-form">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editTpl['id'] ?? ''); ?>">

            <div class="tm-row-2">
                <div>
                    <label class="tm-label"><?php echo _("Template Name"); ?></label>
                    <input type="text" name="title" id="form-title" class="tm-input" placeholder="e.g. Sales Quote & Brochure" required value="<?php echo htmlspecialchars($editTpl['title'] ?? ''); ?>">
                </div>
                <div>
                    <label class="tm-label"><?php echo _("Category"); ?></label>
                    <input type="text" name="category" id="form-category" class="tm-input" placeholder="e.g. Sales, Support, Meetings" value="<?php echo htmlspecialchars($editTpl['category'] ?? 'General'); ?>">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label class="tm-label"><?php echo _("Default Subject Line"); ?> <small style="font-weight: normal; color: #70757a;">(Optional)</small></label>
                <input type="text" name="subject" id="form-subject" class="tm-input" placeholder="e.g. Follow up on our meeting - {subject}" value="<?php echo htmlspecialchars($editTpl['subject'] ?? ''); ?>">
            </div>

            <!-- HTML FORMAT & RICH EDITOR -->
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label class="tm-label" style="margin-bottom: 0;"><?php echo _("Template Content (HTML / Rich Text)"); ?></label>
                    <label style="font-size: 13px; color: #3c4043; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <input type="checkbox" name="is_html" value="1" id="is_html_checkbox" <?php if (!isset($editTpl) || !isset($editTpl['is_html']) || $editTpl['is_html'] == 1) echo 'checked'; ?> onchange="toggleHtmlMode(this.checked)">
                        <strong>🎨 <?php echo _("HTML Formatting Enabled"); ?></strong>
                    </label>
                </div>

                <div class="tm-editor-container" id="editor-container">
                    <!-- TOOLBAR -->
                    <div class="tm-editor-toolbar" id="editor-toolbar">
                        <div class="tm-tb-group">
                            <select class="tm-tb-select" onchange="execCmd('formatBlock', this.value); this.selectedIndex=0;" title="Text Heading / Style">
                                <option value="">Paragraph</option>
                                <option value="H1">Heading 1</option>
                                <option value="H2">Heading 2</option>
                                <option value="H3">Heading 3</option>
                                <option value="BLOCKQUOTE">Quote</option>
                                <option value="PRE">Code / Pre</option>
                            </select>
                        </div>
                        <div class="tm-tb-group">
                            <button type="button" class="tm-tb-btn" onclick="execCmd('bold')" title="Bold (Ctrl+B)"><strong>B</strong></button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('italic')" title="Italic (Ctrl+I)"><em>I</em></button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('strikeThrough')" title="Strikethrough"><s>S</s></button>
                        </div>
                        <div class="tm-tb-group">
                            <label class="tm-tb-btn" style="cursor: pointer;" title="Text Color">
                                🎨 <input type="color" value="#000000" onchange="execCmd('foreColor', this.value)" style="opacity:0; width:1px; height:1px; position:absolute;">
                            </label>
                            <label class="tm-tb-btn" style="cursor: pointer;" title="Highlight / Background Color">
                                🖌️ <input type="color" value="#ffff00" onchange="execCmd('hiliteColor', this.value)" style="opacity:0; width:1px; height:1px; position:absolute;">
                            </label>
                        </div>
                        <div class="tm-tb-group">
                            <button type="button" class="tm-tb-btn" onclick="execCmd('insertUnorderedList')" title="Bullet List">• List</button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('insertOrderedList')" title="Numbered List">1. List</button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('justifyLeft')" title="Align Left">Left</button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('justifyCenter')" title="Align Center">Center</button>
                        </div>
                        <div class="tm-tb-group">
                            <button type="button" class="tm-tb-btn" onclick="insertLinkPrompt()" title="Insert Link">🔗 Link</button>
                            <button type="button" class="tm-tb-btn" onclick="insertImagePrompt()" title="Insert Image / Logo">🖼️ Image</button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('insertHorizontalRule')" title="Divider Line">—</button>
                            <button type="button" class="tm-tb-btn" onclick="execCmd('removeFormat')" title="Clear Formatting">🧹</button>
                        </div>
                        <div style="margin-left: auto;">
                            <button type="button" class="tm-tb-btn" id="btn-toggle-source" onclick="toggleSourceView()" title="Toggle HTML Source Code View">
                                &lt;/&gt; Source
                            </button>
                        </div>
                    </div>

                    <!-- VARIABLE PLACEHOLDERS INSERTION ROW -->
                    <div class="tm-chips-row">
                        <span style="font-weight: 600; color: #64748b;">Insert Tags:</span>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{name}')" title="Insert Recipient's First Name">{name}</button>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{first_name}')" title="Insert Recipient's First Name">{first_name}</button>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{full_name}')" title="Insert Recipient's Full Name">{full_name}</button>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{email}')" title="Insert Recipient's Email Address">{email}</button>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{subject}')" title="Insert Original Subject Line">{subject}</button>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{date}')" title="Insert Current Date">{date}</button>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{my_name}')" title="Insert Your Sender Name">{my_name}</button>
                        <button type="button" class="tm-tag-chip" onclick="insertTag('{my_email}')" title="Insert Your Sender Email">{my_email}</button>
                    </div>

                    <!-- CONTENT EDITABLE AREA -->
                    <div id="tpl-wysiwyg" class="tm-wysiwyg-area" contenteditable="true"><?php
                        $initBody = $editTpl['body'] ?? '';
                        if (!empty($initBody)) {
                            if (preg_match('/<[a-z][\s\S]*>/i', $initBody)) {
                                echo $initBody;
                            } else {
                                echo nl2br(htmlspecialchars($initBody));
                            }
                        } else {
                            echo '<p>Hi {name},</p><p>Thank you for reaching out! ...</p><p>Best regards,<br>{my_name}</p>';
                        }
                    ?></div>

                    <!-- RAW HTML / TEXTAREA (SYNCED) -->
                    <textarea name="body" id="tpl-body-textarea" class="tm-code-area"><?php echo htmlspecialchars($editTpl['body'] ?? "<p>Hi {name},</p>\n<p>Thank you for reaching out! ...</p>\n<p>Best regards,<br>{my_name}</p>"); ?></textarea>
                </div>

                <!-- LIVE HTML PREVIEW -->
                <div class="tm-preview-card">
                    <div class="tm-preview-header">
                        <span style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                            👁️ Live HTML Preview
                        </span>
                        <label style="font-size: 12px; color: #64748b; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                            <input type="checkbox" id="preview-sim-checkbox" checked onchange="updateLivePreview()">
                            <span>Simulate dynamic placeholders</span>
                        </label>
                    </div>
                    <div class="tm-preview-body" id="live-preview-box">
                        <!-- Preview injected dynamically -->
                    </div>
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
                        <a href="<?php echo htmlspecialchars($mgrUrl . '?action=del_att&id=' . urlencode($editTpl['id']) . '&att_idx=' . $idx . '#template-form'); ?>" style="color: #d93025; font-size: 12px; text-decoration: none;" onclick="return confirm('Remove this file from the template?')">
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
                    <a href="<?php echo htmlspecialchars($mgrUrl); ?>" style="margin-left: 12px; color: #5f6368; font-size: 13px; text-decoration: none;"><?php echo _("Cancel Edit"); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PREVIEW POPUP -->
<div id="preview-modal" class="tm-modal-backdrop" onclick="if(event.target===this) closePreviewModal();">
    <div class="tm-modal-content">
        <div class="tm-modal-header">
            <div>
                <h3 id="modal-tpl-title" style="margin: 0; font-size: 16px; font-weight: 600; color: #0f172a;"></h3>
                <span id="modal-tpl-cat" class="tm-category-badge" style="margin-top: 4px;"></span>
            </div>
            <button type="button" onclick="closePreviewModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;">&times;</button>
        </div>
        <div class="tm-modal-body">
            <div id="modal-tpl-subject" style="font-size: 13px; color: var(--sm-text-secondary, #475569); margin-bottom: 12px; padding: 8px 12px; background: var(--sm-bg-surface, #f8fafc); border-radius: 6px; border: 1px solid var(--sm-border, #e2e8f0);"></div>
            <div style="font-size: 11px; font-weight: 700; color: var(--sm-text-muted, #64748b); text-transform: uppercase; margin-bottom: 6px;">HTML Preview (Simulated):</div>
            <div id="modal-tpl-body" style="padding: 16px; border: 1px solid var(--sm-border, #e2e8f0); border-radius: 8px; background: var(--sm-bg-card, #ffffff); min-height: 120px; font-size: 14px; line-height: 1.6; color: var(--sm-text-primary, #1e293b);"></div>
            <div id="modal-tpl-attachments" style="margin-top: 14px;"></div>
        </div>
        <div style="padding: 12px 20px; background: var(--sm-bg-surface, #f8fafc); border-top: 1px solid var(--sm-border, #e2e8f0); display: flex; justify-content: flex-end; gap: 10px;">
            <a id="modal-edit-link" href="#" class="tm-btn-primary" style="font-size: 13px; padding: 8px 16px;">✏️ <?php echo _("Edit Template"); ?></a>
            <button type="button" class="tm-btn-secondary" onclick="closePreviewModal()"><?php echo _("Close"); ?></button>
        </div>
    </div>
</div>

<script>
(function() {
    var wysiwyg = document.getElementById('tpl-wysiwyg');
    var textarea = document.getElementById('tpl-body-textarea');
    var isSourceMode = false;

    var simulatedData = {
        '{name}': 'Alex',
        '{first_name}': 'Alex',
        '{full_name}': 'Alex Morgan',
        '{email}': 'alex.morgan@example.com',
        '{subject}': 'Product Information & Pricing Inquiry',
        '{date}': '<?php echo date("F j, Y"); ?>',
        '{my_name}': <?php echo json_encode($previewMyName); ?>,
        '{my_email}': <?php echo json_encode($previewMyEmail); ?>
    };

    window.execCmd = function(command, value) {
        if (isSourceMode) return;
        document.execCommand(command, false, value || null);
        syncFromWysiwyg();
        if (wysiwyg) wysiwyg.focus();
    };

    window.insertLinkPrompt = function() {
        if (isSourceMode) return;
        var url = prompt("Enter Web Link / URL (e.g. https://example.com):", "https://");
        if (url && url !== "https://") {
            execCmd("createLink", url);
        }
    };

    window.insertImagePrompt = function() {
        if (isSourceMode) return;
        var url = prompt("Enter Image / Logo URL (e.g. https://example.com/logo.png):", "https://");
        if (url && url !== "https://") {
            execCmd("insertImage", url);
        }
    };

    window.insertTag = function(tag) {
        if (isSourceMode) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var text = textarea.value;
            textarea.value = text.substring(0, start) + tag + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + tag.length, start + tag.length);
            syncFromTextarea();
        } else {
            document.execCommand('insertText', false, tag);
            syncFromWysiwyg();
            wysiwyg.focus();
        }
    };

    window.toggleSourceView = function() {
        var btn = document.getElementById('btn-toggle-source');
        if (isSourceMode) {
            // Switch to Visual
            wysiwyg.innerHTML = textarea.value;
            textarea.style.display = 'none';
            wysiwyg.style.display = 'block';
            if (btn) btn.innerHTML = '&lt;/&gt; Source';
            isSourceMode = false;
        } else {
            // Switch to Source
            textarea.value = wysiwyg.innerHTML;
            wysiwyg.style.display = 'none';
            textarea.style.display = 'block';
            if (btn) btn.innerHTML = '🎨 Visual';
            isSourceMode = true;
        }
        updateLivePreview();
    };

    window.toggleHtmlMode = function(isHtml) {
        var toolbar = document.getElementById('editor-toolbar');
        if (toolbar) toolbar.style.display = isHtml ? 'flex' : 'none';
        updateLivePreview();
    };

    function syncFromWysiwyg() {
        if (textarea && wysiwyg) {
            textarea.value = wysiwyg.innerHTML;
            updateLivePreview();
        }
    }

    function syncFromTextarea() {
        if (textarea && wysiwyg) {
            wysiwyg.innerHTML = textarea.value;
            updateLivePreview();
        }
    }

    window.updateLivePreview = function() {
        var previewBox = document.getElementById('live-preview-box');
        if (!previewBox) return;

        var rawContent = isSourceMode ? textarea.value : (wysiwyg ? wysiwyg.innerHTML : textarea.value);
        var simActive = document.getElementById('preview-sim-checkbox') ? document.getElementById('preview-sim-checkbox').checked : true;

        var rendered = rawContent;
        if (simActive) {
            for (var key in simulatedData) {
                var re = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                rendered = rendered.replace(re, '<span style="background:#e0f2fe; color:#0369a1; border-radius:3px; padding:0 3px; font-weight:600;">' + simulatedData[key] + '</span>');
            }
        }

        previewBox.innerHTML = rendered || '<span style="color:#94a3b8; font-style:italic;">(Empty template preview)</span>';
    };

    // Attach listeners
    if (wysiwyg) {
        wysiwyg.addEventListener('input', syncFromWysiwyg);
        wysiwyg.addEventListener('keyup', syncFromWysiwyg);
    }
    if (textarea) {
        textarea.addEventListener('input', syncFromTextarea);
        textarea.addEventListener('keyup', syncFromTextarea);
    }

    // Form submit ensure textarea has content
    var form = document.getElementById('main-template-form');
    if (form) {
        form.onsubmit = function() {
            if (!isSourceMode && wysiwyg && textarea) {
                textarea.value = wysiwyg.innerHTML;
            }
        };
    }

    window.resetForm = function() {
        if (form) form.reset();
        if (wysiwyg) wysiwyg.innerHTML = '';
        if (textarea) textarea.value = '';
        updateLivePreview();
    };

    // Modal preview for any template card
    window.openPreviewModal = function(tpl) {
        var modal = document.getElementById('preview-modal');
        var title = document.getElementById('modal-tpl-title');
        var cat = document.getElementById('modal-tpl-cat');
        var subj = document.getElementById('modal-tpl-subject');
        var body = document.getElementById('modal-tpl-body');
        var atts = document.getElementById('modal-tpl-attachments');
        var editLink = document.getElementById('modal-edit-link');

        if (!modal) return;

        title.textContent = tpl.title || 'Template Preview';
        cat.textContent = tpl.category || 'General';
        subj.innerHTML = '<strong>Subject:</strong> ' + escapeHtml(tpl.subject || '(None)');

        var rawBody = tpl.body || '';
        // Check if rawBody contains HTML tags or is plain
        var isHtml = !tpl.hasOwnProperty('is_html') || tpl.is_html == 1 || /<[a-z][\s\S]*>/i.test(rawBody);
        var previewHtml = isHtml ? rawBody : rawBody.replace(/\n/g, '<br>');

        // Replace tags with sample data
        for (var k in simulatedData) {
            var re = new RegExp(k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
            previewHtml = previewHtml.replace(re, '<span style="background:#e0f2fe; color:#0369a1; border-radius:3px; padding:0 3px; font-weight:600;">' + simulatedData[k] + '</span>');
        }
        body.innerHTML = previewHtml;

        if (tpl.attachments && tpl.attachments.length > 0) {
            var attHtml = '<div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:4px;">📎 Attached Files:</div><div style="display:flex; flex-wrap:wrap; gap:6px;">';
            tpl.attachments.forEach(function(a) {
                attHtml += '<span class="tm-att-chip">📄 <strong>' + escapeHtml(a.filename) + '</strong> (' + Math.round(a.size/1024) + ' KB)</span>';
            });
            attHtml += '</div>';
            atts.innerHTML = attHtml;
        } else {
            atts.innerHTML = '';
        }

        editLink.href = '<?php echo $mgrUrl; ?>?edit=' + encodeURIComponent(tpl.id) + '#template-form';
        modal.style.display = 'flex';
    };

    window.closePreviewModal = function() {
        var modal = document.getElementById('preview-modal');
        if (modal) modal.style.display = 'none';
    };

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Initialize initial preview
    updateLivePreview();
})();
</script>
<?php
echo "</body></html>\n";
