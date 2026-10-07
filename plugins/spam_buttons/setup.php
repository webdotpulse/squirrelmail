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
    $token = function_exists('sm_generate_security_token') ? sm_generate_security_token() : '';

    if ($isJunkFolder) {
        $confirmHam = sm_encode_html_special_chars(_("Restore this email to Inbox and train AI that it is legitimate?"));
        $url = sqm_baseuri() . 'plugins/spam_buttons/action.php?type=ham&mailbox=' . urlencode($mailbox) . '&passed_id=' . $uid . ($token ? '&smtoken=' . urlencode($token) : '');
        $btn = '<a href="' . $url . '" class="sm-btn sm-btn-secondary sm-btn-sm" '
             . 'onclick="return confirm(\'' . addslashes($confirmHam) . '\')" '
             . 'title="' . sm_encode_html_special_chars(_("Mark as Not Spam and train AI model")) . '" '
             . 'style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 4px; text-decoration: none; background: #e6f4ea; color: #137333; border: 1px solid #ceead6;">'
             . '<span>✅</span> <span>' . _("Not Spam") . '</span>'
             . '</a>';
    } else {
        $confirmSpam = sm_encode_html_special_chars(_("Move this email to Junk and train AI spam model?"));
        $url = sqm_baseuri() . 'plugins/spam_buttons/action.php?type=spam&mailbox=' . urlencode($mailbox) . '&passed_id=' . $uid . ($token ? '&smtoken=' . urlencode($token) : '');
        $btn = '<a href="' . $url . '" class="sm-btn sm-btn-secondary sm-btn-sm" '
             . 'onclick="return confirm(\'' . addslashes($confirmSpam) . '\')" '
             . 'title="' . sm_encode_html_special_chars(_("Report as Spam and train AI model")) . '" '
             . 'style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 4px; text-decoration: none; background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf;">'
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

    ob_start();
    ?>
    <span style="display: inline-flex; align-items: center; margin: 0 4px;">
        <?php if ($isJunkFolder): ?>
            <button type="button" class="sm-btn sm-btn-secondary sm-btn-sm" onclick="sbBatchAction('ham')" title="<?php echo _("Mark selected as Not Spam and move to Inbox"); ?>" style="border-color: #ceead6; background: #e6f4ea; color: #137333; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
                <span>✅</span> <span><?php echo _("Not Spam"); ?></span>
            </button>
        <?php else: ?>
            <button type="button" class="sm-btn sm-btn-secondary sm-btn-sm" onclick="sbBatchAction('spam')" title="<?php echo _("Report selected as Spam and move to Junk"); ?>" style="border-color: #fad2cf; background: #fce8e6; color: #c5221f; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
                <span>🚫</span> <span><?php echo _("Spam"); ?></span>
            </button>
        <?php endif; ?>
    </span>

    <script>
    function sbBatchAction(type) {
        var checkedBoxes = document.querySelectorAll(
            'input[type="checkbox"][name^="msg["]:checked, ' +
            'input[type="checkbox"][name^="msg"]:checked, ' +
            'input[type="checkbox"][name^="check["]:checked'
        );
        var uids = [];
        checkedBoxes.forEach(function(cb) {
            if (cb.value && cb.value !== 'on' && !isNaN(cb.value)) {
                uids.push(cb.value);
            }
        });

        if (uids.length === 0) {
            alert(<?php echo json_encode(_("Please select at least one email message first using the checkboxes.")); ?>);
            return;
        }

        var promptMsg = (type === 'spam')
            ? <?php echo json_encode(_("Move selected email(s) to Junk and train AI spam model?")); ?>
            : <?php echo json_encode(_("Restore selected email(s) to Inbox and mark as Not Spam?")); ?>;

        if (!confirm(promptMsg)) return;

        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('type', type);
        formData.append('mailbox', <?php echo json_encode(!empty($mailbox) ? $mailbox : 'INBOX'); ?>);
        uids.forEach(function(u) {
            formData.append('passed_id[]', u);
        });

        var tokenInput = document.querySelector('input[name="smtoken"]');
        if (tokenInput && tokenInput.value) {
            formData.append('smtoken', tokenInput.value);
        }

        var actionUrl = (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.getBaseUri === 'function')
            ? window.sqmApp.getBaseUri() + 'plugins/spam_buttons/action.php'
            : '<?php echo sqm_baseuri(); ?>plugins/spam_buttons/action.php';

        fetch(actionUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(r) {
            if (!r.ok) {
                return r.text().then(function(t) {
                    var errMsg = 'Server error (HTTP ' + r.status + ')';
                    try {
                        var parsed = JSON.parse(t);
                        if (parsed && parsed.error) errMsg = parsed.error;
                    } catch (e) {
                        if (t && t.trim().length > 0) {
                            errMsg += ': ' + t.trim().substring(0, 150);
                        }
                    }
                    throw new Error(errMsg);
                });
            }
            return r.json();
        })
        .then(function(data) {
            if (data && data.success) {
                if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.toast === 'function') {
                    window.sqmApp.toast(data.message || 'Operation successful', 'success');
                } else {
                    alert(data.message);
                }
                if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.navigate === 'function') {
                    window.sqmApp.navigate(window.location.href, false);
                    if (typeof window.sqmApp.refreshFolders === 'function') {
                        window.sqmApp.refreshFolders();
                    }
                } else {
                    window.location.reload();
                }
            } else {
                var errMsg = (data && data.error) ? data.error : 'Failed to process messages.';
                if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.toast === 'function') {
                    window.sqmApp.toast(errMsg, 'error');
                } else {
                    alert('Error: ' + errMsg);
                }
            }
        })
        .catch(function(err) {
            console.error('Spam button error:', err);
            var errMsg = (err && err.message) ? err.message : 'Network or server error while processing request.';
            if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.toast === 'function') {
                window.sqmApp.toast(errMsg, 'error');
            } else {
                alert(errMsg);
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
        'url'  => sqm_baseuri() . 'plugins/spam_buttons/options.php',
        'desc' => _("View spam training stats, learned AI keywords, sender whitelists/blacklists, and Gemini spam heuristics."),
        'js'   => false
    );
}
