<?php
/**
 * Spam Buttons & AI Learning Dashboard
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage spam_buttons
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'plugins/spam_buttons/spam_learn.php');

$msg = null;
$msg_type = 'success';

// Handle Reset Training Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_training') {
    $token = isset($_POST['smtoken']) ? $_POST['smtoken'] : '';
    if (!empty($token) && function_exists('sm_validate_security_token') && !sm_validate_security_token($token, -1, false)) {
        $msg = _("Invalid security token.");
        $msg_type = 'error';
    } else {
        @unlink(sb_get_learning_file());
        $msg = _("AI spam training data and learning memory have been reset.");
        $msg_type = 'success';
    }
}

// Handle Learn from Junk/Spam folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'learn_junk') {
    $token = isset($_POST['smtoken']) ? $_POST['smtoken'] : '';
    if (!empty($token) && function_exists('sm_validate_security_token') && !sm_validate_security_token($token, -1, false)) {
        $msg = _("Invalid security token.");
        $msg_type = 'error';
    } else {
        $limit = isset($_POST['scan_limit']) ? intval($_POST['scan_limit']) : 100;
        if ($limit < 10) $limit = 50;
        $res = sb_learn_from_junk_folder($limit);
        if (!empty($res['success'])) {
            $msg = $res['message'];
            $msg_type = 'success';
        } else {
            $msg = !empty($res['error']) ? $res['error'] : _("Failed to scan Junk folder.");
            $msg_type = 'error';
        }
    }
}

// Handle Remove Sender from Whitelist / Blacklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_sender') {
    $token = isset($_POST['smtoken']) ? $_POST['smtoken'] : '';
    if (!empty($token) && function_exists('sm_validate_security_token') && !sm_validate_security_token($token, -1, false)) {
        $msg = _("Invalid security token.");
        $msg_type = 'error';
    } else {
        $list = isset($_POST['list']) ? trim($_POST['list']) : '';
        $sender = isset($_POST['sender']) ? trim($_POST['sender']) : '';
        $data = sb_load_training_data();
        if ($list === 'blacklist') {
            $idx = array_search($sender, $data['blacklist']);
            if ($idx !== false) {
                unset($data['blacklist'][$idx]);
                $data['blacklist'] = array_values($data['blacklist']);
                sb_save_training_data($data);
                $msg = sprintf(_("Removed '%s' from blacklist."), htmlspecialchars($sender));
                $msg_type = 'success';
            }
        } elseif ($list === 'whitelist') {
            $idx = array_search($sender, $data['whitelist']);
            if ($idx !== false) {
                unset($data['whitelist'][$idx]);
                $data['whitelist'] = array_values($data['whitelist']);
                sb_save_training_data($data);
                $msg = sprintf(_("Removed '%s' from whitelist."), htmlspecialchars($sender));
                $msg_type = 'success';
            }
        }
    }
}

$data = sb_load_training_data();

displayPageHeader($color, 'None');
?>
<style>
.sb-container {
    max-width: 860px;
    margin: 24px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #202124;
}
.sb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dadce0;
}
.sb-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.sb-stat-card {
    background: #ffffff;
    border: 1px solid #dadce0;
    border-radius: 10px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 1px 2px rgba(60,64,67,0.08);
}
.sb-stat-val {
    font-size: 28px;
    font-weight: 700;
    color: #1a73e8;
    margin-bottom: 4px;
}
.sb-stat-val.spam { color: #d93025; }
.sb-stat-val.ham  { color: #1e8e3e; }
.sb-stat-title {
    font-size: 13px;
    color: #5f6368;
}
.sb-card {
    background: #ffffff;
    border: 1px solid #dadce0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.sb-card-title {
    font-size: 16px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sb-tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.sb-keyword-chip {
    padding: 4px 10px;
    background: #fce8e6;
    color: #c5221f;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}
.sb-rule-box {
    background: #f8f9fa;
    border-left: 3px solid #1a73e8;
    padding: 10px 14px;
    border-radius: 0 6px 6px 0;
    margin-bottom: 8px;
    font-size: 13px;
    color: #3c4043;
}
.sb-rep-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 8px;
    border-radius: 4px;
    background: #fafbfc;
    margin-bottom: 4px;
}
.sb-rep-item:hover {
    background: #f1f3f4;
}
</style>

<div class="sb-container">
    <div class="sb-header">
        <h2>
            <span>🧠</span>
            <span><?php echo _("Spam Buttons &amp; AI Learning Dashboard"); ?></span>
        </h2>
    </div>

    <?php if ($msg): ?>
    <div style="background: <?php echo ($msg_type === 'error' ? '#fce8e6' : '#e6f4ea'); ?>; color: <?php echo ($msg_type === 'error' ? '#c5221f' : '#137333'); ?>; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid <?php echo ($msg_type === 'error' ? '#fad2cf' : '#ceead6'); ?>;">
        <?php echo ($msg_type === 'error' ? '⚠️ ' : '✅ '); ?><?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- STATS GRID -->
    <div class="sb-stats-grid">
        <div class="sb-stat-card">
            <div class="sb-stat-val spam"><?php echo intval($data['spam_count']); ?></div>
            <div class="sb-stat-title"><?php echo _("Spam Emails Trained"); ?></div>
        </div>
        <div class="sb-stat-card">
            <div class="sb-stat-val ham"><?php echo intval($data['ham_count']); ?></div>
            <div class="sb-stat-title"><?php echo _("Not Spam / Ham Trained"); ?></div>
        </div>
        <div class="sb-stat-card">
            <div class="sb-stat-val"><?php echo count($data['whitelist']); ?></div>
            <div class="sb-stat-title"><?php echo _("Whitelisted Senders"); ?></div>
        </div>
        <div class="sb-stat-card">
            <div class="sb-stat-val" style="color: #ea8600;"><?php echo count($data['blacklist']); ?></div>
            <div class="sb-stat-title"><?php echo _("Blacklisted Senders"); ?></div>
        </div>
    </div>

    <!-- SCAN & LEARN FROM JUNK FOLDER -->
    <div class="sb-card" style="border-left: 4px solid #1a73e8; background: linear-gradient(to right, #f8fafd, #ffffff);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h3 class="sb-card-title" style="margin-bottom: 6px;">📥 <?php echo _("Learn from Junk / Spam Folder"); ?></h3>
                <p style="font-size: 13px; color: #5f6368; margin: 0; max-width: 580px;">
                    <?php echo _("Automatically analyze and train your spam filters on emails already sitting in your Junk/Spam folder. This updates your spam keywords, blacklists repeat spam senders, and refines AI heuristics without moving any emails."); ?>
                </p>
            </div>
            <form method="post" id="learn-junk-form" onsubmit="var b=document.getElementById('learn-junk-btn'); if(b){b.disabled=true; b.innerHTML='⏳ Scanning Junk...';}">
                <input type="hidden" name="action" value="learn_junk">
                <input type="hidden" name="smtoken" value="<?php echo function_exists('sm_generate_security_token') ? sm_generate_security_token() : ''; ?>">
                <button type="submit" id="learn-junk-btn" style="padding: 10px 18px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; background: #1a73e8; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                    <span>📥</span>
                    <span><?php echo _("Learn from Junk/Spam Folder"); ?></span>
                </button>
            </form>
        </div>
    </div>

    <!-- AI LEARNED HEURISTICS -->
    <?php if (!empty($data['learned_rules'])): ?>
    <div class="sb-card">
        <h3 class="sb-card-title">🤖 <?php echo _("AI Gemini Learned Heuristics"); ?></h3>
        <p style="font-size: 13px; color: #5f6368; margin-top: 0;">
            <?php echo _("Rules extracted by Google Gemini 3.8 from your reported spam emails:"); ?>
        </p>
        <?php foreach ($data['learned_rules'] as $rule): ?>
            <div class="sb-rule-box">💡 <?php echo htmlspecialchars($rule); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- TOP SPAM KEYWORDS -->
    <?php if (!empty($data['spam_keywords'])):
        arsort($data['spam_keywords']);
        $topKeywords = array_slice($data['spam_keywords'], 0, 30);
    ?>
    <div class="sb-card">
        <h3 class="sb-card-title">🔍 <?php echo _("Learned Spam Keywords &amp; Tokens"); ?></h3>
        <div class="sb-tag-cloud">
            <?php foreach ($topKeywords as $kw => $freq): ?>
                <span class="sb-keyword-chip">
                    <?php echo htmlspecialchars($kw); ?> <small style="opacity: 0.8;">(<?php echo $freq; ?>)</small>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- WHITELIST & BLACKLIST SUMMARY -->
    <div class="sb-card">
        <h3 class="sb-card-title">🛡️ <?php echo _("Sender Reputations"); ?></h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4 style="margin: 0 0 8px; color: #137333; font-size: 14px;">✅ <?php echo _("Whitelisted (Trusted)"); ?> (<?php echo count($data['whitelist']); ?>)</h4>
                <?php if (empty($data['whitelist'])): ?>
                    <div style="font-size: 12px; color: #9aa0a6;"><?php echo _("No whitelisted senders yet."); ?></div>
                <?php else: ?>
                    <div style="max-height: 240px; overflow-y: auto; padding-right: 4px;">
                        <?php foreach (array_slice($data['whitelist'], 0, 50) as $w): ?>
                            <div class="sb-rep-item">
                                <code style="font-size: 12px; color: #137333;"><?php echo htmlspecialchars($w); ?></code>
                                <form method="post" style="display: inline; margin: 0;">
                                    <input type="hidden" name="action" value="remove_sender">
                                    <input type="hidden" name="list" value="whitelist">
                                    <input type="hidden" name="sender" value="<?php echo htmlspecialchars($w); ?>">
                                    <input type="hidden" name="smtoken" value="<?php echo function_exists('sm_generate_security_token') ? sm_generate_security_token() : ''; ?>">
                                    <button type="submit" title="<?php echo _("Remove from Whitelist"); ?>" style="background: none; border: none; color: #9aa0a6; cursor: pointer; padding: 0 4px; font-size: 12px;" onclick="return confirm('Remove <?php echo htmlspecialchars(addslashes($w)); ?> from whitelist?');">✕</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <h4 style="margin: 0 0 8px; color: #c5221f; font-size: 14px;">🚫 <?php echo _("Blacklisted (Spam)"); ?> (<?php echo count($data['blacklist']); ?>)</h4>
                <?php if (empty($data['blacklist'])): ?>
                    <div style="font-size: 12px; color: #9aa0a6;"><?php echo _("No blacklisted senders yet."); ?></div>
                <?php else: ?>
                    <div style="max-height: 240px; overflow-y: auto; padding-right: 4px;">
                        <?php foreach (array_slice($data['blacklist'], 0, 50) as $b): ?>
                            <div class="sb-rep-item">
                                <code style="font-size: 12px; color: #c5221f;"><?php echo htmlspecialchars($b); ?></code>
                                <form method="post" style="display: inline; margin: 0;">
                                    <input type="hidden" name="action" value="remove_sender">
                                    <input type="hidden" name="list" value="blacklist">
                                    <input type="hidden" name="sender" value="<?php echo htmlspecialchars($b); ?>">
                                    <input type="hidden" name="smtoken" value="<?php echo function_exists('sm_generate_security_token') ? sm_generate_security_token() : ''; ?>">
                                    <button type="submit" title="<?php echo _("Remove from Blacklist"); ?>" style="background: none; border: none; color: #9aa0a6; cursor: pointer; padding: 0 4px; font-size: 12px;" onclick="return confirm('Remove <?php echo htmlspecialchars(addslashes($b)); ?> from blacklist?');">✕</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RESET TRAINING -->
    <div style="margin-top: 24px; text-align: right;">
        <form method="post" onsubmit="return confirm('<?php echo sm_encode_html_special_chars(addslashes(_("Reset all learned spam and ham AI models?"))); ?>');">
            <input type="hidden" name="action" value="reset_training">
            <input type="hidden" name="smtoken" value="<?php echo function_exists('sm_generate_security_token') ? sm_generate_security_token() : ''; ?>">
            <button type="submit" style="padding: 8px 16px; background: #ffffff; border: 1px solid #fad2cf; color: #d93025; border-radius: 6px; font-size: 13px; cursor: pointer;">
                🗑️ <?php echo _("Reset AI Training Memory"); ?>
            </button>
        </form>
    </div>
</div>
<?php
echo "</body></html>\n";
