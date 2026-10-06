<?php
/**
 * Notify New Mail Popup - Options & Settings
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage newmail_notify
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');

$msg = null;

// Load prefs
$desktop_enabled = getPref($data_dir, $username, 'newmail_desktop_enabled', 1);
$toast_enabled   = getPref($data_dir, $username, 'newmail_toast_enabled', 1);
$sound_enabled   = getPref($data_dir, $username, 'newmail_sound_enabled', 1);
$check_interval  = getPref($data_dir, $username, 'newmail_interval', 30);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $desktop_enabled = !empty($_POST['desktop_enabled']) ? 1 : 0;
    $toast_enabled   = !empty($_POST['toast_enabled']) ? 1 : 0;
    $sound_enabled   = !empty($_POST['sound_enabled']) ? 1 : 0;
    $check_interval  = intval($_POST['check_interval'] ?? 30);

    setPref($data_dir, $username, 'newmail_desktop_enabled', $desktop_enabled);
    setPref($data_dir, $username, 'newmail_toast_enabled', $toast_enabled);
    setPref($data_dir, $username, 'newmail_sound_enabled', $sound_enabled);
    setPref($data_dir, $username, 'newmail_interval', $check_interval);

    $msg = _("Notification preferences saved successfully!");
}

displayPageHeader($color, 'None');
?>
<style>
.nm-container {
    max-width: 760px;
    margin: 24px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #202124;
}
.nm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dadce0;
}
.nm-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.nm-card {
    background: #ffffff;
    border: 1px solid #dadce0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.nm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid #f1f3f4;
}
.nm-row:last-child {
    border-bottom: none;
}
.nm-label-title {
    font-size: 15px;
    font-weight: 600;
    color: #202124;
}
.nm-label-desc {
    font-size: 13px;
    color: #5f6368;
    margin-top: 2px;
}
.nm-btn-primary {
    padding: 10px 22px;
    background: #1a73e8;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.nm-btn-secondary {
    padding: 8px 16px;
    background: #f1f3f4;
    color: #3c4043;
    border: 1px solid #dadce0;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}
</style>

<div class="nm-container">
    <div class="nm-header">
        <h2>
            <span>🔔</span>
            <span><?php echo _("New Mail Notification Popups"); ?></span>
        </h2>
        <button type="button" class="nm-btn-secondary" onclick="testNotification()">
            <span>🔔</span> <span><?php echo _("Test Notification &amp; Sound"); ?></span>
        </button>
    </div>

    <?php if ($msg): ?>
    <div style="background: #e6f4ea; color: #137333; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ceead6;">
        ✅ <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <div class="nm-card">
            <div class="nm-row">
                <div>
                    <div class="nm-label-title"><?php echo _("Desktop Push Notifications"); ?></div>
                    <div class="nm-label-desc"><?php echo _("Show native operating system / browser popups when new mail arrives even if browser is minimized."); ?></div>
                </div>
                <div>
                    <input type="checkbox" name="desktop_enabled" value="1" <?php if ($desktop_enabled) echo 'checked'; ?> style="transform: scale(1.3); cursor: pointer;" onchange="if(this.checked) requestNotificationPermission();">
                </div>
            </div>

            <div class="nm-row">
                <div>
                    <div class="nm-label-title"><?php echo _("In-App Toast Popup"); ?></div>
                    <div class="nm-label-desc"><?php echo _("Display an animated floating card at the top-right corner with sender, subject, and quick 'Open' button."); ?></div>
                </div>
                <div>
                    <input type="checkbox" name="toast_enabled" value="1" <?php if ($toast_enabled) echo 'checked'; ?> style="transform: scale(1.3); cursor: pointer;">
                </div>
            </div>

            <div class="nm-row">
                <div>
                    <div class="nm-label-title"><?php echo _("Audio Chime Alert"); ?></div>
                    <div class="nm-label-desc"><?php echo _("Play a gentle chime when new incoming email is detected."); ?></div>
                </div>
                <div>
                    <input type="checkbox" name="sound_enabled" value="1" <?php if ($sound_enabled) echo 'checked'; ?> style="transform: scale(1.3); cursor: pointer;">
                </div>
            </div>

            <div class="nm-row">
                <div>
                    <div class="nm-label-title"><?php echo _("Check Interval"); ?></div>
                    <div class="nm-label-desc"><?php echo _("How frequently SquirrelMail should check for new incoming emails in the background."); ?></div>
                </div>
                <div>
                    <select name="check_interval" style="padding: 8px 12px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px;">
                        <option value="15" <?php if ($check_interval == 15) echo 'selected'; ?>>15 <?php echo _("seconds"); ?></option>
                        <option value="30" <?php if ($check_interval == 30) echo 'selected'; ?>>30 <?php echo _("seconds"); ?> (Recommended)</option>
                        <option value="60" <?php if ($check_interval == 60) echo 'selected'; ?>>1 <?php echo _("minute"); ?></option>
                        <option value="120" <?php if ($check_interval == 120) echo 'selected'; ?>>2 <?php echo _("minutes"); ?></option>
                        <option value="300" <?php if ($check_interval == 300) echo 'selected'; ?>>5 <?php echo _("minutes"); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div>
            <button type="submit" name="save_settings" value="1" class="nm-btn-primary">
                💾 <?php echo _("Save Preferences"); ?>
            </button>
        </div>
    </form>
</div>

<script>
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission !== 'granted') {
        Notification.requestPermission();
    }
}

function playChime() {
    try {
        var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        var osc1 = audioCtx.createOscillator();
        var osc2 = audioCtx.createOscillator();
        var gain = audioCtx.createGain();

        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, audioCtx.currentTime); // A5
        osc1.frequency.exponentialRampToValueAtTime(1760, audioCtx.currentTime + 0.15); // A6

        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);

        osc1.connect(gain);
        gain.connect(audioCtx.destination);

        osc1.start();
        osc1.stop(audioCtx.currentTime + 0.5);
    } catch (e) {
        console.warn('AudioContext not allowed or supported', e);
    }
}

function testNotification() {
    playChime();

    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification('SquirrelMail: New Email', {
                body: 'From: Sarah Connor\nSubject: Project Kickoff Meeting 2026',
                icon: '<?php echo SM_PATH; ?>images/sm_logo.png'
            });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(function(perm) {
                if (perm === 'granted') {
                    new Notification('SquirrelMail: New Email', {
                        body: 'From: Sarah Connor\nSubject: Project Kickoff Meeting 2026',
                        icon: '<?php echo SM_PATH; ?>images/sm_logo.png'
                    });
                }
            });
        }
    }

    if (window.parent && window.parent.nmShowToast) {
        window.parent.nmShowToast('Sarah Connor', 'Project Kickoff Meeting 2026', 1);
    } else {
        alert('🔔 Notification Test: Chime played! (Desktop permissions: ' + (window.Notification ? Notification.permission : 'unsupported') + ')');
    }
}
</script>
<?php
echo "</body></html>\n";
