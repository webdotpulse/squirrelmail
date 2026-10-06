<?php
/**
 * Spam Buttons Plugin Setup & Hooks
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage spam_buttons
 */

function squirrelmail_plugin_init_spam_buttons()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['read_body_header_right']['spam_buttons']
        = 'sb_read_body_header_right';

    $squirrelmail_plugin_hooks['template_construct_message_list_controls.tpl']['spam_buttons']
        = 'sb_message_list_controls';

    $squirrelmail_plugin_hooks['optpage_register_block']['spam_buttons']
        = 'sb_optpage_register_block';
}

function spam_buttons_info()
{
    return array(
        'english_name'           => 'Spam Buttons & AI Learning',
        'version'                => '2.0.0',
        'summary'                => 'Report Spam & Not Spam buttons with intelligent AI learning and automatic folder moving.',
        'details'                => 'Move spam emails to Junk with one click while automatically training Gemini AI model heuristics, sender reputation scores, and keyword filters.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function spam_buttons_version()
{
    $info = spam_buttons_info();
    return $info['version'];
}

function sb_read_body_header_right(&$links)
{
    global $passed_id, $mailbox;

    $uid = intval($passed_id);
    $isJunkFolder = (stripos($mailbox, 'junk') !== false || stripos($mailbox, 'spam') !== false);

    if ($isJunkFolder) {
        $url = SM_PATH . 'plugins/spam_buttons/action.php?type=ham&mailbox=' . urlencode($mailbox) . '&passed_id=' . $uid;
        $btn = '<a href="' . $url . '" class="btn btn-secondary" '
             . 'onclick="return confirm(\'' . _("Restore this email to Inbox and train AI that it is legitimate?") . '\')" '
             . 'title="' . _("Mark as Not Spam and train AI model") . '" '
             . 'style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; text-decoration: none; background: #e6f4ea; color: #137333; border: 1px solid #ceead6;">'
             . '<span>✅</span> <span>' . _("Not Spam") . '</span>'
             . '</a>';
    } else {
        $url = SM_PATH . 'plugins/spam_buttons/action.php?type=spam&mailbox=' . urlencode($mailbox) . '&passed_id=' . $uid;
        $btn = '<a href="' . $url . '" class="btn btn-secondary" '
             . 'onclick="return confirm(\'' . _("Move this email to Junk and train AI spam model?") . '\')" '
             . 'title="' . _("Report as Spam and train AI model") . '" '
             . 'style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; text-decoration: none; background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf;">'
             . '<span>🚫</span> <span>' . _("Report Spam") . '</span>'
             . '</a>';
    }

    if (is_array($links)) {
        $links[] = $btn;
    }
    return $links;
}

function sb_message_list_controls()
{
    global $mailbox;
    $isJunkFolder = (stripos($mailbox, 'junk') !== false || stripos($mailbox, 'spam') !== false);

    $actionUrl = SM_PATH . 'plugins/spam_buttons/action.php';

    ob_start();
    ?>
    <span style="margin: 0 4px;">
        <?php if ($isJunkFolder): ?>
            <button type="button" class="btn btn-secondary" onclick="sbBatchAction('ham')" style="padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; border: 1px solid #ceead6; background: #e6f4ea; color: #137333; cursor: pointer;">
                ✅ <?php echo _("Not Spam"); ?>
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-secondary" onclick="sbBatchAction('spam')" style="padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; border: 1px solid #fad2cf; background: #fce8e6; color: #c5221f; cursor: pointer;">
                🚫 <?php echo _("Spam"); ?>
            </button>
        <?php endif; ?>
    </span>

    <script>
    function sbBatchAction(type) {
        var form = document.querySelector('form[name="messageListForm"]') || document.querySelector('form[action*="mailbox_display"]');
        if (!form) return;

        var checkboxes = form.querySelectorAll('input[type="checkbox"][name^="msg["]:checked, input[type="checkbox"][name^="check["]:checked');
        if (checkboxes.length === 0) {
            alert('<?php echo _("Please select at least one email message first."); ?>');
            return;
        }

        var promptMsg = (type === 'spam')
            ? '<?php echo _("Move selected email(s) to Junk and train AI spam model?"); ?>'
            : '<?php echo _("Move selected email(s) to Inbox and train AI ham model?"); ?>';

        if (!confirm(promptMsg)) return;

        var uids = [];
        checkboxes.forEach(function(cb) {
            var val = cb.value;
            if (val) uids.push(val);
        });

        var formData = new FormData();
        formData.append('ajax', 1);
        formData.append('type', type);
        formData.append('mailbox', '<?php echo htmlspecialchars($mailbox, ENT_QUOTES); ?>');
        uids.forEach(function(u) {
            formData.append('passed_id[]', u);
        });

        fetch('<?php echo $actionUrl; ?>', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to process messages.'));
            }
        });
    }
    </script>
    <?php
    $html = ob_get_clean();
    return array('message_list_controls_buttons' => $html);
}

function sb_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("Spam Buttons &amp; AI Learning"),
        'url'  => SM_PATH . 'plugins/spam_buttons/options.php',
        'desc' => _("View spam training stats, learned AI keywords, sender whitelists/blacklists, and Gemini spam heuristics."),
        'js'   => false
    );
}
