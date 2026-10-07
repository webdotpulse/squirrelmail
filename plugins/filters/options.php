<?php
/**
 * Message and Spam Filter Plugin - Options & Management Page
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package plugins
 * @subpackage filters
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'functions/imap_general.php');
include_once(SM_PATH . 'functions/imap_mailbox.php');
include_once(SM_PATH . 'functions/imap_messages.php');
include_once(SM_PATH . 'functions/forms.php');
include_once(SM_PATH . 'plugins/filters/filters.php');

$baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../../');
$optUrl  = $baseUri . 'plugins/filters/options.php';
$token   = function_exists('sm_generate_security_token') ? sm_generate_security_token() : '';

$msg   = null;
$error = null;

sqgetGlobalVar('theid', $theid);
sqgetGlobalVar('action', $action, SQ_GET);
sqgetGlobalVar('token', $req_token, SQ_GET);
if (empty($req_token)) {
    sqgetGlobalVar('smtoken', $req_token, SQ_GET);
}

// 1. Handle Run Filters Now action
if (isset($action) && $action === 'run_now') {
    $moved = start_filters(array('force' => true));
    if ($moved > 0) {
        $msg = sprintf(_("Filters executed successfully! %d message(s) moved from INBOX to destination folders."), $moved);
    } else {
        $msg = _("Filters executed! No matching messages in INBOX to move at this time.");
    }
}

// 2. Handle Delete filter
if (isset($action) && $action === 'delete' && isset($theid)) {
    $theid = (int)$theid;
    remove_filter($theid);
    $msg = _("Filter rule deleted successfully.");
    $action = '';
}

// 3. Handle Move Up / Move Down
if (isset($action) && $action === 'move_up' && isset($theid)) {
    $theid = (int)$theid;
    if ($theid > 0) {
        filter_swap($theid, $theid - 1);
        $msg = _("Filter priority updated.");
    }
    $action = '';
}
if (isset($action) && $action === 'move_down' && isset($theid)) {
    $theid = (int)$theid;
    filter_swap($theid, $theid + 1);
    $msg = _("Filter priority updated.");
    $action = '';
}

// 4. Handle Save Scan Type
if (sqgetGlobalVar('user_submit', $user_submit, SQ_POST)) {
    if (!empty($_POST['smtoken']) && function_exists('sm_validate_security_token')) {
        sm_validate_security_token($_POST['smtoken'], -1, false);
    }
    sqgetGlobalVar('filters_user_scan_set', $filters_user_scan_set, SQ_POST);
    setPref($data_dir, $username, 'filters_user_scan', $filters_user_scan_set);
    $msg = _("Scan preference saved successfully.");
}

// 5. Handle Save Rule (Add or Edit)
if (sqgetGlobalVar('filter_submit', $filter_submit, SQ_POST)) {
    if (!empty($_POST['smtoken']) && function_exists('sm_validate_security_token')) {
        sm_validate_security_token($_POST['smtoken'], -1, false);
    }

    $theid = isset($_POST['theid']) ? (int)$_POST['theid'] : 0;
    $filter_where  = trim($_POST['filter_where'] ?? '');
    $filter_what   = trim($_POST['filter_what'] ?? '');
    $filter_folder = trim($_POST['filter_folder'] ?? '');
    $is_editing    = isset($_POST['is_editing']) && $_POST['is_editing'] === '1';

    if (empty($filter_what)) {
        $error = _("Please enter a text search term to match against incoming emails.");
        $action = $is_editing ? 'edit' : 'add';
    } elseif ($filter_where === 'Header' && strpos($filter_what, ':') === false) {
        $error = _("Header filters must be in the format 'Header-Name: Value' (e.g. List-Id: updates).");
        $action = $is_editing ? 'edit' : 'add';
    } elseif (empty($filter_folder)) {
        $error = _("Please select a target destination folder.");
        $action = $is_editing ? 'edit' : 'add';
    } else {
        $safe_what = str_replace(',', '###COMMA###', $filter_what);
        setPref($data_dir, $username, 'filter' . $theid, $filter_where . ',' . $safe_what . ',' . $filter_folder);
        $msg = $is_editing ? _("Filter rule updated successfully!") : _("Filter rule created successfully!");
        $action = '';
    }
}

$filters = load_filters();
$filters_user_scan = getPref($data_dir, $username, 'filters_user_scan', '');

displayPageHeader($color, 'None');
?>
<style>
.sm-filter-wrap {
    max-width: 980px;
    margin: 24px auto;
    font-family: var(--sm-font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
    color: var(--sm-text-primary, #0f172a);
    padding: 0 16px;
}
.sm-filter-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--sm-border, #e2e8f0);
}
.sm-filter-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sm-filter-desc {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: var(--sm-text-secondary, #475569);
}
.sm-filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.sm-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    border-radius: var(--sm-radius-sm, 6px);
    text-decoration: none;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all var(--sm-transition-fast, 0.15s);
}
.sm-btn-primary {
    background: var(--sm-primary, #2563eb);
    color: #ffffff !important;
}
.sm-btn-primary:hover {
    background: var(--sm-primary-hover, #1d4ed8);
}
.sm-btn-secondary {
    background: var(--sm-bg-card, #ffffff);
    color: var(--sm-text-primary, #0f172a) !important;
    border-color: var(--sm-border, #cbd5e1);
}
.sm-btn-secondary:hover {
    background: var(--sm-hover-bg, #f1f5f9);
    border-color: var(--sm-border-hover, #94a3b8);
}
.sm-btn-run {
    background: #059669;
    color: #ffffff !important;
}
.sm-btn-run:hover {
    background: #047857;
}
.sm-card {
    background: var(--sm-bg-card, #ffffff);
    border: 1px solid var(--sm-border, #e2e8f0);
    border-radius: var(--sm-radius-md, 10px);
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: var(--sm-shadow-sm, 0 1px 2px rgba(0,0,0,0.05));
}
.sm-card-title {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sm-alert {
    padding: 12px 16px;
    border-radius: var(--sm-radius-sm, 6px);
    font-size: 13px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sm-alert-success {
    background: var(--sm-success-light, #ecfdf5);
    color: var(--sm-success, #065f46);
    border: 1px solid #a7f3d0;
}
.sm-alert-error {
    background: var(--sm-danger-light, #fef2f2);
    color: var(--sm-danger, #991b1b);
    border: 1px solid #fecaca;
}
.sm-table {
    width: 100%;
    border-collapse: collapse;
}
.sm-table th {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--sm-text-secondary, #64748b);
    padding: 10px 12px;
    border-bottom: 2px solid var(--sm-border, #e2e8f0);
}
.sm-table td {
    padding: 12px;
    border-bottom: 1px solid var(--sm-border, #f1f5f9);
    font-size: 14px;
    vertical-align: middle;
}
.sm-table tr:hover {
    background: var(--sm-hover-bg, #f8fafc);
}
.badge-field {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    background: var(--sm-primary-light, #eff6ff);
    color: var(--sm-primary, #2563eb);
    border: 1px solid var(--sm-primary-border, #bfdbfe);
}
.badge-folder {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.chip-what {
    font-family: var(--sm-font-mono, monospace);
    font-size: 13px;
    background: var(--sm-hover-bg, #f1f5f9);
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--sm-text-primary, #0f172a);
    word-break: break-all;
}
.sm-input, .sm-select {
    padding: 8px 12px;
    border: 1px solid var(--sm-border, #cbd5e1);
    border-radius: var(--sm-radius-sm, 6px);
    font-size: 14px;
    background: var(--sm-bg-surface, #ffffff);
    color: var(--sm-text-primary, #0f172a);
    outline: none;
    box-sizing: border-box;
}
.sm-input:focus, .sm-select:focus {
    border-color: var(--sm-primary, #2563eb);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.sm-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    align-items: end;
}
.sm-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.sm-form-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--sm-text-secondary, #475569);
}
.sm-btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    color: var(--sm-text-secondary, #475569);
    text-decoration: none;
    transition: background 0.15s;
    font-size: 14px;
}
.sm-btn-icon:hover {
    background: var(--sm-hover-bg, #e2e8f0);
    color: var(--sm-text-primary, #0f172a);
}
.sm-btn-del {
    color: var(--sm-danger, #dc2626) !important;
}
.sm-btn-del:hover {
    background: var(--sm-danger-light, #fee2e2) !important;
}
.empty-box {
    text-align: center;
    padding: 36px 20px;
    color: var(--sm-text-secondary, #64748b);
}
.empty-icon {
    font-size: 40px;
    margin-bottom: 12px;
}
</style>

<div class="sm-filter-wrap">
    <!-- Header -->
    <div class="sm-filter-header">
        <div>
            <h1 class="sm-filter-title">⚡ <?php echo _("Message Filters &amp; Automation"); ?></h1>
            <p class="sm-filter-desc"><?php echo _("Automatically sort incoming emails from your Inbox into dedicated folders based on sender, recipient, subject, headers, or body text."); ?></p>
        </div>
        <div class="sm-filter-actions">
            <?php if ($action !== 'add' && $action !== 'edit'): ?>
            <a href="<?php echo $optUrl; ?>?action=add" class="sm-btn sm-btn-primary">➕ <?php echo _("New Filter Rule"); ?></a>
            <?php endif; ?>
            <a href="<?php echo $optUrl; ?>?action=run_now" class="sm-btn sm-btn-run" title="<?php echo _("Apply filters immediately to all eligible messages in INBOX"); ?>">⚡ <?php echo _("Run Filters Now"); ?></a>
            <a href="<?php echo $baseUri; ?>src/options.php" class="sm-btn sm-btn-secondary">← <?php echo _("Options"); ?></a>
        </div>
    </div>

    <!-- Feedback messages -->
    <?php if ($msg): ?>
    <div class="sm-alert sm-alert-success">
        <span>✅</span>
        <span><?php echo htmlspecialchars($msg); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="sm-alert sm-alert-error">
        <span>⚠️</span>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <!-- ADD / EDIT RULE FORM -->
    <?php if ($action === 'add' || $action === 'edit'):
        global $imapServerAddress, $imapPort, $imap_stream_options;
        $imapConnection = sqimap_login($username, false, $imapServerAddress, $imapPort, 0, $imap_stream_options);
        $boxes = sqimap_mailbox_list($imapConnection);
        sqimap_logout($imapConnection);

        $theid = isset($theid) ? (int)$theid : count($filters);
        $currWhere  = isset($filters[$theid]['where']) ? $filters[$theid]['where'] : 'From';
        $currWhat   = isset($filters[$theid]['what']) ? $filters[$theid]['what'] : '';
        $currFolder = isset($filters[$theid]['folder']) ? $filters[$theid]['folder'] : '';
        $isEdit     = ($action === 'edit' && isset($filters[$theid]));
    ?>
    <div class="sm-card" style="border: 2px solid var(--sm-primary, #2563eb);">
        <h2 class="sm-card-title"><?php echo $isEdit ? '✏️ ' . _("Edit Filter Rule") : '➕ ' . _("Create New Filter Rule"); ?></h2>
        <form method="post" action="<?php echo $optUrl; ?>">
            <input type="hidden" name="smtoken" value="<?php echo htmlspecialchars($token); ?>" />
            <input type="hidden" name="theid" value="<?php echo $theid; ?>" />
            <input type="hidden" name="is_editing" value="<?php echo $isEdit ? '1' : '0'; ?>" />

            <div class="sm-form-grid">
                <!-- Condition Field -->
                <div class="sm-form-group">
                    <label for="filter_where"><?php echo _("If message attribute:"); ?></label>
                    <select name="filter_where" id="filter_where" class="sm-select">
                        <option value="From"<?php echo ($currWhere == 'From') ? ' selected="selected"' : ''; ?>><?php echo _("From (Sender)"); ?></option>
                        <option value="To"<?php echo ($currWhere == 'To') ? ' selected="selected"' : ''; ?>><?php echo _("To (Recipient)"); ?></option>
                        <option value="Cc"<?php echo ($currWhere == 'Cc') ? ' selected="selected"' : ''; ?>><?php echo _("Cc (Copy)"); ?></option>
                        <option value="To or Cc"<?php echo ($currWhere == 'To or Cc') ? ' selected="selected"' : ''; ?>><?php echo _("To or Cc"); ?></option>
                        <option value="Subject"<?php echo ($currWhere == 'Subject') ? ' selected="selected"' : ''; ?>><?php echo _("Subject"); ?></option>
                        <option value="Header"<?php echo ($currWhere == 'Header') ? ' selected="selected"' : ''; ?>><?php echo _("Header (e.g. Header: Value)"); ?></option>
                        <option value="Message Body"<?php echo ($currWhere == 'Message Body') ? ' selected="selected"' : ''; ?>><?php echo _("Message Body"); ?></option>
                        <option value="Header and Body"<?php echo ($currWhere == 'Header and Body') ? ' selected="selected"' : ''; ?>><?php echo _("Entire Message (Header &amp; Body)"); ?></option>
                    </select>
                </div>

                <!-- Contains Input -->
                <div class="sm-form-group" style="flex: 2;">
                    <label for="filter_what"><?php echo _("Contains text / keyword:"); ?></label>
                    <input type="text" name="filter_what" id="filter_what" class="sm-input"
                           value="<?php echo sm_encode_html_special_chars($currWhat); ?>"
                           placeholder="<?php echo _("e.g. alerts@github.com, Invoice, Newsletter"); ?>" required />
                </div>

                <!-- Move To Folder -->
                <div class="sm-form-group">
                    <label for="filter_folder"><?php echo _("Move matching email to:"); ?></label>
                    <select name="filter_folder" id="filter_folder" class="sm-select" required>
                        <?php
                        $selectedBox = !empty($currFolder) ? array(strtolower($currFolder)) : 0;
                        echo sqimap_mailbox_option_list(0, $selectedBox, 'INBOX', $boxes);
                        ?>
                    </select>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
                <button type="submit" name="filter_submit" value="1" class="sm-btn sm-btn-primary">
                    💾 <?php echo $isEdit ? _("Update Filter Rule") : _("Save Filter Rule"); ?>
                </button>
                <a href="<?php echo $optUrl; ?>" class="sm-btn sm-btn-secondary"><?php echo _("Cancel"); ?></a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- RULES TABLE CARD -->
    <div class="sm-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h2 class="sm-card-title" style="margin: 0;">🗂️ <?php echo _("Active Message Filter Rules"); ?> (<?php echo count($filters); ?>)</h2>
            <?php if (count($filters) > 0 && $action !== 'add' && $action !== 'edit'): ?>
            <a href="<?php echo $optUrl; ?>?action=add" class="sm-btn sm-btn-secondary" style="font-size: 12px; padding: 4px 10px;">➕ <?php echo _("Add Rule"); ?></a>
            <?php endif; ?>
        </div>

        <?php if (count($filters) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th style="width: 48px; text-align: center;"><?php echo _("Order"); ?></th>
                        <th><?php echo _("Condition / Rule"); ?></th>
                        <th><?php echo _("Destination Folder"); ?></th>
                        <th style="width: 90px; text-align: center;"><?php echo _("Priority"); ?></th>
                        <th style="width: 110px; text-align: right;"><?php echo _("Actions"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $numFilters = count($filters);
                    for ($i = 0; $i < $numFilters; $i++):
                        $ruleWhere  = $filters[$i]['where'];
                        $ruleWhat   = $filters[$i]['what'];
                        $ruleFolder = $filters[$i]['folder'];
                        $displayFolder = (!empty($folder_prefix) && strpos($ruleFolder, $folder_prefix) === 0)
                            ? substr($ruleFolder, strlen($folder_prefix))
                            : $ruleFolder;
                    ?>
                    <tr>
                        <td style="text-align: center; font-weight: 600; color: var(--sm-text-secondary, #64748b);">
                            #<?php echo ($i + 1); ?>
                        </td>
                        <td>
                            If <span class="badge-field"><?php echo htmlspecialchars($ruleWhere); ?></span>
                            contains <span class="chip-what"><?php echo htmlspecialchars($ruleWhat); ?></span>
                        </td>
                        <td>
                            <span class="badge-folder">
                                📁 <?php echo htmlspecialchars(imap_utf7_decode_local($displayFolder)); ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 4px;">
                                <?php if ($i > 0): ?>
                                <a href="<?php echo $optUrl; ?>?theid=<?php echo $i; ?>&amp;action=move_up" class="sm-btn-icon" title="<?php echo _("Move Up (Higher Priority)"); ?>">▲</a>
                                <?php else: ?>
                                <span class="sm-btn-icon" style="opacity: 0.2; cursor: default;">▲</span>
                                <?php endif; ?>

                                <?php if ($i < $numFilters - 1): ?>
                                <a href="<?php echo $optUrl; ?>?theid=<?php echo $i; ?>&amp;action=move_down" class="sm-btn-icon" title="<?php echo _("Move Down (Lower Priority)"); ?>">▼</a>
                                <?php else: ?>
                                <span class="sm-btn-icon" style="opacity: 0.2; cursor: default;">▼</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                <a href="<?php echo $optUrl; ?>?theid=<?php echo $i; ?>&amp;action=edit" class="sm-btn sm-btn-secondary" style="font-size: 12px; padding: 4px 8px;">
                                    ✏️ <?php echo _("Edit"); ?>
                                </a>
                                <a href="<?php echo $optUrl; ?>?theid=<?php echo $i; ?>&amp;action=delete&amp;smtoken=<?php echo urlencode($token); ?>"
                                   class="sm-btn sm-btn-secondary sm-btn-del" style="font-size: 12px; padding: 4px 8px;"
                                   onclick="return confirm('<?php echo _("Delete this filter rule? (Existing emails in folders will not be affected)"); ?>');">
                                    ✕ <?php echo _("Delete"); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-box">
            <div class="empty-icon">🗂️</div>
            <h3 style="margin: 0 0 6px 0;"><?php echo _("No message filters configured yet"); ?></h3>
            <p style="margin: 0 0 16px 0;"><?php echo _("Filters let you automatically organize incoming email into folders based on sender, subject, or keywords."); ?></p>
            <a href="<?php echo $optUrl; ?>?action=add" class="sm-btn sm-btn-primary">➕ <?php echo _("Create Your First Filter"); ?></a>
        </div>
        <?php endif; ?>
    </div>

    <!-- SCAN SETTINGS CARD -->
    <div class="sm-card">
        <h2 class="sm-card-title">⚙️ <?php echo _("Scan Preferences"); ?></h2>
        <form method="post" action="<?php echo $optUrl; ?>" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <input type="hidden" name="smtoken" value="<?php echo htmlspecialchars($token); ?>" />
            <label for="filters_user_scan_set" style="font-size: 13px; font-weight: 500; color: var(--sm-text-secondary, #475569);">
                <?php echo _("When scanning INBOX for filter matches:"); ?>
            </label>
            <select name="filters_user_scan_set" id="filters_user_scan_set" class="sm-select" style="min-width: 200px;">
                <option value=""<?php echo ($filters_user_scan == '') ? ' selected="selected"' : ''; ?>><?php echo _("Scan all messages"); ?></option>
                <option value="new"<?php echo ($filters_user_scan == 'new') ? ' selected="selected"' : ''; ?>><?php echo _("Scan only unread messages"); ?></option>
            </select>
            <button type="submit" name="user_submit" value="1" class="sm-btn sm-btn-secondary">
                💾 <?php echo _("Save Preference"); ?>
            </button>
        </form>
    </div>
</div>

<?php
echo "</body></html>\n";
