<?php
/**
 * User Autoresponder & Mail Forwarder Options Page
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage autoresponder
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'plugins/autoresponder/autoresponder.php');

$config = autoresponder_get_config();
$savedMsg = false;

// Handle Turn Off Now action from top banner
if (isset($_GET['action']) && $_GET['action'] === 'turn_off') {
    $config['vacation_enabled'] = 0;
    autoresponder_save_config($config);
    $savedMsg = _("Out of Office autoresponder has been turned off.");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $config['vacation_enabled']   = !empty($_POST['vacation_enabled']) ? 1 : 0;
    $config['vacation_start']     = trim($_POST['vacation_start'] ?? date('Y-m-d'));
    $config['vacation_end']       = trim($_POST['vacation_end'] ?? date('Y-m-d', strtotime('+7 days')));
    $config['vacation_subject']   = trim($_POST['vacation_subject'] ?? 'Out of Office: {subject}');
    $config['vacation_body']      = trim($_POST['vacation_body'] ?? '');
    $config['vacation_interval']  = intval($_POST['vacation_interval'] ?? 1);

    $config['forward_enabled']    = !empty($_POST['forward_enabled']) ? 1 : 0;
    $config['forward_addresses']  = trim($_POST['forward_addresses'] ?? '');
    $config['forward_keep_copy']  = !empty($_POST['forward_keep_copy']) ? 1 : 0;

    autoresponder_save_config($config);
    $savedMsg = _("Autoresponder and forwarding settings saved successfully!");
}

$isVacationActive = autoresponder_is_vacation_active($config);
$sieveScript = autoresponder_generate_sieve($config);

displayPageHeader($color, 'None');
?>
<style>
.ar-container {
    max-width: 860px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #202124;
}
.ar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dadce0;
}
.ar-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ar-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.ar-badge.active { background: #fef7e0; color: #b06000; border: 1px solid #feefc3; }
.ar-badge.inactive { background: #f1f3f4; color: #5f6368; }

.ar-card {
    background: #ffffff;
    border: 1px solid #dadce0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.ar-card-title {
    font-size: 16px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #202124;
}
.ar-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.ar-col {
    flex: 1;
    min-width: 220px;
}
.ar-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #3c4043;
    margin-bottom: 6px;
}
.ar-input, .ar-select, .ar-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 14px;
    border: 1px solid #dadce0;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.ar-input:focus, .ar-select:focus, .ar-textarea:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 2px rgba(26,115,232,0.2);
}
.ar-toggle-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f1f3f4;
}
.ar-toggle-label {
    font-size: 15px;
    font-weight: 600;
    color: #202124;
    cursor: pointer;
}
.ar-hint {
    font-size: 12px;
    color: #5f6368;
    margin-top: 4px;
    line-height: 1.4;
}
.ar-btn-save {
    padding: 10px 24px;
    background: #1a73e8;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    transition: background 0.15s;
}
.ar-btn-save:hover {
    background: #1557b0;
}
.ar-code-box {
    background: #202124;
    color: #e8eaed;
    border-radius: 8px;
    padding: 14px;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    line-height: 1.5;
    overflow-x: auto;
    white-space: pre-wrap;
}
</style>

<div class="ar-container">
    <div class="ar-header">
        <h2>
            <span>🏖️</span>
            <span><?php echo _("Autoresponder &amp; Mail Forwarding"); ?></span>
        </h2>
        <div>
            <?php if ($isVacationActive): ?>
                <span class="ar-badge active">● <?php echo _("Out of Office Active"); ?></span>
            <?php else: ?>
                <span class="ar-badge inactive"><?php echo _("Autoresponder Off"); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($savedMsg): ?>
    <div style="background: #e6f4ea; color: #137333; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ceead6;">
        ✅ <?php echo htmlspecialchars($savedMsg); ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <!-- CARD 1: VACATION AUTORESPONDER -->
        <div class="ar-card">
            <h3 class="ar-card-title">
                <span>🏖️</span>
                <span><?php echo _("Vacation / Out of Office Autoresponder"); ?></span>
            </h3>

            <div class="ar-toggle-wrap">
                <input type="checkbox" id="vacation_enabled" name="vacation_enabled" value="1" <?php if (!empty($config['vacation_enabled'])) echo 'checked'; ?> style="transform: scale(1.3); cursor: pointer;">
                <label for="vacation_enabled" class="ar-toggle-label"><?php echo _("Enable Out of Office Autoresponder"); ?></label>
            </div>

            <div class="ar-row">
                <div class="ar-col">
                    <label class="ar-label"><?php echo _("Start Date"); ?></label>
                    <input type="date" name="vacation_start" class="ar-input" value="<?php echo htmlspecialchars($config['vacation_start']); ?>">
                    <div class="ar-hint"><?php echo _("Autoresponder will start sending replies on this date."); ?></div>
                </div>
                <div class="ar-col">
                    <label class="ar-label"><?php echo _("End Date (Inclusive)"); ?></label>
                    <input type="date" name="vacation_end" class="ar-input" value="<?php echo htmlspecialchars($config['vacation_end']); ?>">
                    <div class="ar-hint"><?php echo _("Autoresponder will automatically deactivate after this date."); ?></div>
                </div>
            </div>

            <div class="ar-row">
                <div class="ar-col" style="flex: 2;">
                    <label class="ar-label"><?php echo _("Response Subject"); ?></label>
                    <input type="text" name="vacation_subject" class="ar-input" value="<?php echo htmlspecialchars($config['vacation_subject']); ?>">
                    <div class="ar-hint"><?php echo _("Supports <code>{subject}</code> placeholder for the incoming email subject."); ?></div>
                </div>
                <div class="ar-col" style="flex: 1;">
                    <label class="ar-label"><?php echo _("Reply Frequency"); ?></label>
                    <select name="vacation_interval" class="ar-select">
                        <option value="1" <?php if ($config['vacation_interval'] == 1) echo 'selected'; ?>><?php echo _("Once per day per sender"); ?></option>
                        <option value="3" <?php if ($config['vacation_interval'] == 3) echo 'selected'; ?>><?php echo _("Once every 3 days"); ?></option>
                        <option value="7" <?php if ($config['vacation_interval'] == 7) echo 'selected'; ?>><?php echo _("Once per week per sender"); ?></option>
                    </select>
                    <div class="ar-hint"><?php echo _("Prevents repetitive replies and mailing list loops."); ?></div>
                </div>
            </div>

            <div>
                <label class="ar-label"><?php echo _("Auto-Reply Message Body"); ?></label>
                <textarea name="vacation_body" class="ar-textarea" rows="7"><?php echo htmlspecialchars($config['vacation_body']); ?></textarea>
                <div class="ar-hint"><?php echo _("Placeholders available: <code>{sender}</code> (sender email), <code>{date}</code> (current date), <code>{subject}</code> (original subject)."); ?></div>
            </div>
        </div>

        <!-- CARD 2: MAIL FORWARDER -->
        <div class="ar-card">
            <h3 class="ar-card-title">
                <span>📨</span>
                <span><?php echo _("Mail Forwarder"); ?></span>
            </h3>

            <div class="ar-toggle-wrap">
                <input type="checkbox" id="forward_enabled" name="forward_enabled" value="1" <?php if (!empty($config['forward_enabled'])) echo 'checked'; ?> style="transform: scale(1.3); cursor: pointer;">
                <label for="forward_enabled" class="ar-toggle-label"><?php echo _("Enable Mail Forwarding"); ?></label>
            </div>

            <div style="margin-bottom: 16px;">
                <label class="ar-label"><?php echo _("Forward Emails To"); ?></label>
                <input type="text" name="forward_addresses" class="ar-input" placeholder="colleague@example.com, personal@gmail.com" value="<?php echo htmlspecialchars($config['forward_addresses']); ?>">
                <div class="ar-hint"><?php echo _("Enter one or more email addresses separated by commas."); ?></div>
            </div>

            <div>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                    <input type="checkbox" name="forward_keep_copy" value="1" <?php if (!empty($config['forward_keep_copy'])) echo 'checked'; ?>>
                    <span><?php echo _("Keep a copy of forwarded messages in this mailbox"); ?></span>
                </label>
            </div>
        </div>

        <!-- CARD 3: SIEVE SCRIPT PREVIEW -->
        <div class="ar-card" style="background: #fafbfc;">
            <h3 class="ar-card-title" style="font-size: 14px; color: #5f6368;">
                <span>⚙️</span>
                <span><?php echo _("Server Sieve Script (RFC 5230 Vacation)"); ?></span>
            </h3>
            <p style="font-size: 12px; color: #5f6368; margin-top: 0;">
                <?php echo _("If your mail server runs Dovecot ManageSieve, this script can be directly synchronized to handle auto-replies at the server level:"); ?>
            </p>
            <div class="ar-code-box"><?php echo htmlspecialchars($sieveScript); ?></div>
        </div>

        <div style="margin-bottom: 30px;">
            <button type="submit" name="save_settings" value="1" class="ar-btn-save">
                💾 <?php echo _("Save Settings"); ?>
            </button>
        </div>
    </form>
</div>
<?php
echo "</body></html>\n";
