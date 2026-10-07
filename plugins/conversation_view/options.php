<?php
/**
 * options.php - Conversation View Plugin Options Page
 *
 * User configuration for message-view conversation threading,
 * including Sent/Drafts inclusion and placement options.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage conversation_view
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');

$msg = null;
$msg_type = 'success';

// Load user preferences
$cv_enabled     = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
$cv_position    = getPref($data_dir, $username, 'conversation_view_position', 'bottom');
$search_sent    = (int) getPref($data_dir, $username, 'cv_search_sent', 1);
$search_drafts  = (int) getPref($data_dir, $username, 'cv_search_drafts', 1);
$search_current = (int) getPref($data_dir, $username, 'cv_search_current', 1);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $token = isset($_POST['smtoken']) ? $_POST['smtoken'] : '';
    if (!empty($token) && function_exists('sm_validate_security_token') && !sm_validate_security_token($token, -1, false)) {
        $msg = _("Invalid security token.");
        $msg_type = 'error';
    } else {
        $cv_enabled     = !empty($_POST['cv_enabled']) ? 1 : 0;
        $cv_position    = in_array($_POST['cv_position'], array('bottom', 'top', 'both')) ? $_POST['cv_position'] : 'bottom';
        $search_sent    = !empty($_POST['search_sent']) ? 1 : 0;
        $search_drafts  = !empty($_POST['search_drafts']) ? 1 : 0;
        $search_current = !empty($_POST['search_current']) ? 1 : 0;

        setPref($data_dir, $username, 'conversation_view_enabled', $cv_enabled);
        setPref($data_dir, $username, 'conversation_view_position', $cv_position);
        setPref($data_dir, $username, 'cv_search_sent', $search_sent);
        setPref($data_dir, $username, 'cv_search_drafts', $search_drafts);
        setPref($data_dir, $username, 'cv_search_current', $search_current);

        $msg = _("Conversation View preferences saved successfully!");
        $msg_type = 'success';
    }
}

displayPageHeader($color, 'None');
$token = function_exists('sm_generate_security_token') ? sm_generate_security_token() : '';
?>
<style>
.cv-opts-container {
    max-width: 760px;
    margin: 24px auto;
    font-family: var(--sm-font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
    color: var(--sm-text-primary, #0f172a);
}
.cv-opts-card {
    background: var(--sm-bg-card, #ffffff);
    border: 1px solid var(--sm-border, #e2e8f0);
    border-radius: var(--sm-radius-lg, 14px);
    box-shadow: var(--sm-shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
    padding: 24px;
}
.cv-opts-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--sm-border, #e2e8f0);
}
.cv-opts-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.cv-opts-desc {
    font-size: 13.5px;
    color: var(--sm-text-secondary, #64748b);
    line-height: 1.5;
    margin-bottom: 24px;
}
.cv-opts-group {
    display: flex;
    flex-direction: column;
    gap: 18px;
    margin-bottom: 24px;
}
.cv-opts-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--sm-border, #e2e8f0);
    border-radius: var(--sm-radius-md, 10px);
    background: var(--sm-bg-surface, #ffffff);
    transition: all 0.15s ease;
}
.cv-opts-item:hover {
    border-color: var(--sm-border-hover, #94a3b8);
}
.cv-opts-item input[type="checkbox"],
.cv-opts-item input[type="radio"] {
    margin-top: 3px;
    cursor: pointer;
}
.cv-opts-item-content {
    flex: 1;
}
.cv-opts-item-label {
    font-weight: 600;
    font-size: 14px;
    display: block;
    margin-bottom: 3px;
    cursor: pointer;
}
.cv-opts-item-sub {
    font-size: 12.5px;
    color: var(--sm-text-secondary, #64748b);
    line-height: 1.4;
}
.cv-opts-alert {
    padding: 12px 16px;
    border-radius: var(--sm-radius-md, 8px);
    margin-bottom: 20px;
    font-size: 13.5px;
    font-weight: 500;
}
.cv-opts-alert-success {
    background: var(--sm-success-light, #ecfdf5);
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.cv-opts-alert-error {
    background: var(--sm-danger-light, #fef2f2);
    color: var(--sm-danger, #dc2626);
    border: 1px solid rgba(220, 38, 38, 0.3);
}
.cv-opts-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 16px;
    border-top: 1px solid var(--sm-border, #e2e8f0);
}
</style>

<div class="cv-opts-container">
    <div class="cv-opts-card">
        <div class="cv-opts-header">
            <h2 class="cv-opts-title">
                <span>💬</span> <?php echo _("Conversation View & Threading"); ?>
            </h2>
            <a href="<?php echo sqm_baseuri(); ?>src/options.php" class="cv-btn cv-btn-outline cv-btn-sm">&larr; <?php echo _("Options"); ?></a>
        </div>

        <?php if ($msg): ?>
            <div class="cv-opts-alert cv-opts-alert-<?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="cv-opts-desc">
            <?php echo _("Conversation View automatically discovers and unites all messages in an email thread—including your sent replies and saved drafts—so you never lose context when reading incoming mail."); ?>
        </div>

        <form action="<?php echo sqm_baseuri(); ?>plugins/conversation_view/options.php" method="POST">
            <input type="hidden" name="smtoken" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="save_settings" value="1">

            <div class="cv-opts-group">
                <!-- Master Toggle -->
                <div class="cv-opts-item">
                    <input type="checkbox" id="cv_enabled" name="cv_enabled" value="1" <?php if ($cv_enabled) echo 'checked'; ?>>
                    <div class="cv-opts-item-content">
                        <label for="cv_enabled" class="cv-opts-item-label"><?php echo _("Enable Conversation View in Message Reader"); ?></label>
                        <div class="cv-opts-item-sub"><?php echo _("Display the conversation thread timeline and related drafts/replies when viewing an email."); ?></div>
                    </div>
                </div>

                <!-- Search Sent -->
                <div class="cv-opts-item">
                    <input type="checkbox" id="search_sent" name="search_sent" value="1" <?php if ($search_sent) echo 'checked'; ?>>
                    <div class="cv-opts-item-content">
                        <label for="search_sent" class="cv-opts-item-label"><?php echo _("Include Sent Replies (from Sent Folder)"); ?></label>
                        <div class="cv-opts-item-sub"><?php echo _("Scans your Sent folder via IMAP References and In-Reply-To headers to attach your replies to the conversation."); ?></div>
                    </div>
                </div>

                <!-- Search Drafts -->
                <div class="cv-opts-item">
                    <input type="checkbox" id="search_drafts" name="search_drafts" value="1" <?php if ($search_drafts) echo 'checked'; ?>>
                    <div class="cv-opts-item-content">
                        <label for="search_drafts" class="cv-opts-item-label"><?php echo _("Include Pending Drafts (from Drafts Folder)"); ?></label>
                        <div class="cv-opts-item-sub"><?php echo _("Displays any un-sent draft replies for this conversation with a direct 'Resume Draft' button."); ?></div>
                    </div>
                </div>

                <!-- Search Current Folder -->
                <div class="cv-opts-item">
                    <input type="checkbox" id="search_current" name="search_current" value="1" <?php if ($search_current) echo 'checked'; ?>>
                    <div class="cv-opts-item-content">
                        <label for="search_current" class="cv-opts-item-label"><?php echo _("Include Other Incoming Replies in Current Folder"); ?></label>
                        <div class="cv-opts-item-sub"><?php echo _("Discovers prior or subsequent incoming emails in the same folder that belong to the same conversation thread."); ?></div>
                    </div>
                </div>

                <!-- Placement Preference -->
                <div class="cv-opts-item" style="flex-direction: column; gap: 8px;">
                    <div class="cv-opts-item-label"><?php echo _("Thread View Placement"); ?></div>
                    <div style="display: flex; gap: 16px; margin-top: 4px; flex-wrap: wrap;">
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="cv_position" value="bottom" <?php if ($cv_position === 'bottom') echo 'checked'; ?>>
                            <?php echo _("Below message body (Recommended)"); ?>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="cv_position" value="top" <?php if ($cv_position === 'top') echo 'checked'; ?>>
                            <?php echo _("Above message body"); ?>
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                            <input type="radio" name="cv_position" value="both" <?php if ($cv_position === 'both') echo 'checked'; ?>>
                            <?php echo _("Both top and bottom"); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="cv-opts-actions">
                <a href="<?php echo sqm_baseuri(); ?>src/options.php" class="cv-btn cv-btn-secondary"><?php echo _("Cancel"); ?></a>
                <button type="submit" class="cv-btn cv-btn-primary"><?php echo _("Save Preferences"); ?></button>
            </div>
        </form>
    </div>
</div>
<?php
$oTemplate->display('footer.tpl');
