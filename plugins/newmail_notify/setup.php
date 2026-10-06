<?php
/**
 * Notify New Mail Popup - Plugin Setup & Client-Side Injections
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage newmail_notify
 */

function squirrelmail_plugin_init_newmail_notify()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_page_header.tpl']['newmail_notify']
        = 'newmail_notify_page_header';

    $squirrelmail_plugin_hooks['optpage_register_block']['newmail_notify']
        = 'newmail_notify_optpage_register_block';
}

function newmail_notify_info()
{
    return array(
        'english_name'           => 'Notify New Mail Popup (Desktop & Toast)',
        'version'                => '2.0.0',
        'summary'                => 'Real-time new email desktop notifications, floating in-app popup cards, and audio chime alerts.',
        'details'                => 'Displays modern HTML5 desktop push notifications and sleek animated in-app cards with audio chime when new emails arrive, with configurable background polling.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function newmail_notify_version()
{
    $info = newmail_notify_info();
    return $info['version'];
}

function newmail_notify_page_header()
{
    global $data_dir, $username;

    $desktop_enabled = getPref($data_dir, $username, 'newmail_desktop_enabled', 1);
    $toast_enabled   = getPref($data_dir, $username, 'newmail_toast_enabled', 1);
    $sound_enabled   = getPref($data_dir, $username, 'newmail_sound_enabled', 1);
    $check_interval  = getPref($data_dir, $username, 'newmail_interval', 30);
    $intervalMs      = max(10, intval($check_interval)) * 1000;

    $ajaxUrl = SM_PATH . 'plugins/newmail_notify/check_mail.php';

    ob_start();
    ?>
    <style>
    /* New Mail Toast Popup Container */
    #nm-toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .nm-toast-card {
        pointer-events: auto;
        width: 320px;
        background: #ffffff;
        border: 1px solid #dadce0;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        padding: 14px 16px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        animation: nmSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        transition: transform 0.2s, opacity 0.2s;
    }
    @keyframes nmSlideIn {
        from { transform: translateX(120%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .nm-toast-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1a73e8 0%, #681da8 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 15px;
        flex-shrink: 0;
    }
    .nm-toast-content {
        flex: 1;
        min-width: 0;
    }
    .nm-toast-sender {
        font-size: 13px;
        font-weight: 600;
        color: #202124;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .nm-toast-subject {
        font-size: 12px;
        color: #5f6368;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .nm-toast-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }
    .nm-toast-btn {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
    }
    .nm-toast-btn-open {
        background: #1a73e8;
        color: #ffffff;
    }
    .nm-toast-btn-dismiss {
        background: #f1f3f4;
        color: #5f6368;
    }
    </style>

    <div id="nm-toast-container"></div>

    <script>
    (function() {
        var desktopEnabled = <?php echo $desktop_enabled ? 'true' : 'false'; ?>;
        var toastEnabled   = <?php echo $toast_enabled ? 'true' : 'false'; ?>;
        var soundEnabled   = <?php echo $sound_enabled ? 'true' : 'false'; ?>;
        var intervalMs     = <?php echo $intervalMs; ?>;
        var checkUrl       = '<?php echo $ajaxUrl; ?>';

        function playChime() {
            if (!soundEnabled) return;
            try {
                var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                var osc1 = audioCtx.createOscillator();
                var gain = audioCtx.createGain();

                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(880, audioCtx.currentTime);
                osc1.frequency.exponentialRampToValueAtTime(1760, audioCtx.currentTime + 0.15);

                gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);

                osc1.connect(gain);
                gain.connect(audioCtx.destination);

                osc1.start();
                osc1.stop(audioCtx.currentTime + 0.5);
            } catch (e) {}
        }

        window.nmShowToast = function(from, subject, uid) {
            if (!toastEnabled) return;
            var container = document.getElementById('nm-toast-container');
            if (!container) return;

            var card = document.createElement('div');
            card.className = 'nm-toast-card';

            var initial = (from && from.length > 0) ? from.charAt(0).toUpperCase() : '✉';

            card.innerHTML =
                '<div class="nm-toast-avatar">' + initial + '</div>' +
                '<div class="nm-toast-content">' +
                    '<div class="nm-toast-sender">' + escapeHtml(from) + '</div>' +
                    '<div class="nm-toast-subject">' + escapeHtml(subject) + '</div>' +
                    '<div class="nm-toast-actions">' +
                        '<a href="<?php echo SM_PATH; ?>src/read_body.php?mailbox=INBOX&passed_id=' + uid + '" class="nm-toast-btn nm-toast-btn-open">Read</a>' +
                        '<button type="button" class="nm-toast-btn nm-toast-btn-dismiss" onclick="this.closest(\'.nm-toast-card\').remove()">Dismiss</button>' +
                    '</div>' +
                '</div>';

            container.appendChild(card);

            // Auto-dismiss after 9 seconds
            setTimeout(function() {
                if (card.parentNode) {
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(100%)';
                    setTimeout(function() { card.remove(); }, 250);
                }
            }, 9000);
        };

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function checkNewMail() {
            fetch(checkUrl)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.has_new && data.messages && data.messages.length > 0) {
                        playChime();
                        data.messages.forEach(function(msg) {
                            // In-App Toast
                            window.nmShowToast(msg.from, msg.subject, msg.uid);

                            // Native Desktop Notification
                            if (desktopEnabled && 'Notification' in window && Notification.permission === 'granted') {
                                try {
                                    var n = new Notification('New Email: ' + msg.from, {
                                        body: msg.subject,
                                        icon: '<?php echo SM_PATH; ?>images/sm_logo.png'
                                    });
                                    n.onclick = function() {
                                        window.focus();
                                        window.location.href = '<?php echo SM_PATH; ?>src/read_body.php?mailbox=INBOX&passed_id=' + msg.uid;
                                    };
                                } catch (e) {}
                            }
                        });
                    }
                })
                .catch(function(err) {});
        }

        // Start background polling
        if (intervalMs > 0) {
            setInterval(checkNewMail, intervalMs);
        }
    })();
    </script>
    <?php
    $output = ob_get_clean();
    return array('page_header_bottom' => $output);
}

function newmail_notify_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("New Mail Notification Popups"),
        'url'  => SM_PATH . 'plugins/newmail_notify/options.php',
        'desc' => _("Configure desktop push notification popups, floating in-app cards, sound chime alerts, and background mail polling."),
        'js'   => false
    );
}
