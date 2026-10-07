<?php
/**
 * Message Flags & Labels Plugin Setup & Hooks
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage message_labels
 */

function squirrelmail_plugin_init_message_labels()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_left_main.tpl']['message_labels']
        = 'ml_left_main';

    $squirrelmail_plugin_hooks['template_construct_page_header.tpl']['message_labels']
        = 'ml_page_header';

    $squirrelmail_plugin_hooks['template_construct_message_list.tpl']['message_labels']
        = 'ml_message_list';

    $squirrelmail_plugin_hooks['template_construct_message_list_controls.tpl']['message_labels']
        = 'ml_message_list_controls';

    $squirrelmail_plugin_hooks['read_body_header_right']['message_labels']
        = 'ml_read_body_header_right';

    $squirrelmail_plugin_hooks['read_body_top']['message_labels']
        = 'ml_read_body_top';

    $squirrelmail_plugin_hooks['optpage_register_block']['message_labels']
        = 'ml_optpage_register_block';
}

function message_labels_info()
{
    return array(
        'english_name'           => 'Message Flags & Labels (Gmail Style)',
        'version'                => '2.0.0',
        'summary'                => 'Gmail-like colorful message labels, multi-tagging, star flags, and left-pane label filtering.',
        'details'                => 'Assign custom or pre-set colored labels (Work, Personal, Finance, Important, To Do) to messages with quick tagging dropdowns, stars, and sidebar filter counts.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function message_labels_version()
{
    $info = message_labels_info();
    return $info['version'];
}

function ml_left_main()
{
    include_once(SM_PATH . 'plugins/message_labels/labels.php');
    $data = ml_load_data();
    $counts = ml_get_label_counts();
    $activeFilter = isset($_GET['label_filter']) ? trim($_GET['label_filter']) : '';

    $html = '<div class="sm-sidebar-labels-wrapper" style="margin-top: 16px; padding: 0 12px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">'
          . '<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--sm-text-muted, #5f6368); letter-spacing: 0.8px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">'
          . '<span style="display: flex; align-items: center; gap: 6px;">🏷️ ' . _("Labels") . '</span>'
          . '<a href="../plugins/message_labels/options.php" style="color: #1a73e8; text-decoration: none; font-size: 16px; font-weight: bold; line-height: 1;" title="' . _("Manage Labels") . '">+</a>'
          . '</div>'
          . '<div style="display: flex; flex-direction: column; gap: 3px;">';

    foreach ($data['labels'] as $lid => $lDef) {
        $count = isset($counts[$lid]) ? $counts[$lid] : 0;
        $color = $lDef['color'];
        $isActive = ($activeFilter === $lid);
        $url = '../src/right_main.php?mailbox=INBOX&label_filter=' . urlencode($lid);

        $bgStyle = $isActive ? 'background: rgba(26, 115, 232, 0.12); font-weight: 600;' : 'background: transparent;';
        $activeBorder = $isActive ? 'border-left: 3px solid ' . htmlspecialchars($color) . ';' : 'border-left: 3px solid transparent;';

        $html .= '<a href="' . $url . '" class="sm-label-sidebar-link' . ($isActive ? ' active' : '') . '" style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 13px; color: var(--sm-text-primary, #3c4043); transition: all 0.15s ease; ' . $bgStyle . ' ' . $activeBorder . '" onmouseover="if(!this.classList.contains(\'active\')) this.style.background=\'var(--sm-bg-hover, rgba(0,0,0,0.04))\';" onmouseout="if(!this.classList.contains(\'active\')) this.style.background=\'transparent\';">'
               . '<div style="display: flex; align-items: center; gap: 9px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">'
               . '<span style="width: 10px; height: 10px; border-radius: 50%; background: ' . htmlspecialchars($color) . '; flex-shrink: 0; display: inline-block;"></span>'
               . '<span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . htmlspecialchars($lDef['name']) . '</span>'
               . '</div>'
               . ($count > 0 ? '<span style="font-size: 11px; font-weight: 700; color: var(--sm-text-secondary, #5f6368); background: var(--sm-bg-hover, #e8eaed); padding: 1px 7px; border-radius: 10px; flex-shrink: 0; margin-left: 6px;">' . $count . '</span>' : '')
               . '</a>';
    }

    $html .= '</div></div>';
    return array(
        'left_main_after'  => $html,
        'left_main_bottom' => $html
    );
}

function ml_page_header()
{
    ob_start();
    ?>
    <style>
    .ml-tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        margin-right: 6px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .ml-star-icon {
        cursor: pointer;
        font-size: 16px;
        color: #dadce0;
        transition: color 0.15s, transform 0.15s;
    }
    .ml-star-icon.starred {
        color: #fbbc04;
    }
    .ml-dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 6px;
        background: #ffffff;
        border: 1px solid #dadce0;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        z-index: 10000;
        min-width: 200px;
        padding: 8px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .ml-dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 14px;
        font-size: 13px;
        color: #3c4043;
        cursor: pointer;
        transition: background 0.15s;
    }
    .ml-dropdown-item:hover {
        background: #f1f3f4;
    }
    </style>
    <?php
    $css = ob_get_clean();
    return array('page_header_top' => $css);
}

function ml_read_body_header_right(&$links)
{
    global $passed_id, $mailbox;
    include_once(SM_PATH . 'plugins/message_labels/labels.php');

    $uid = intval($passed_id);
    $data = ml_load_data();
    $activeLabels = ml_get_message_labels($mailbox, $uid);
    $isStarred = ml_is_message_starred($mailbox, $uid);

    ob_start();
    ?>
    <div class="ml-dropdown-wrapper" style="position: relative; display: inline-flex; align-items: center;">
        <button type="button" class="sm-btn sm-btn-secondary sm-btn-sm ml-dropdown-btn" onclick="mlToggleDropdown(event)" style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer; white-space: nowrap;">
            <span>🏷️</span> <span><?php echo _("Labels"); ?></span> <span style="font-size: 8px; opacity: 0.7; margin-left: 2px;">▼</span>
        </button>
        <div id="ml-dropdown" class="ml-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 4px); right: 0; min-width: 210px; background: var(--sm-bg-surface, #ffffff); border: 1px solid var(--sm-border, #dadce0); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.16); z-index: 10005; padding: 6px 0;">
            <div class="ml-dropdown-header" style="padding: 6px 14px 4px; font-size: 11px; font-weight: 700; color: var(--sm-text-muted, #5f6368); text-transform: uppercase; letter-spacing: 0.5px;">
                <?php echo _("Label Message As"); ?>
            </div>
            <?php foreach ($data['labels'] as $lid => $lDef):
                $isChecked = isset($activeLabels[$lid]);
            ?>
            <div class="ml-dropdown-item" onclick="mlToggleLabel('<?php echo htmlspecialchars($mailbox, ENT_QUOTES); ?>', <?php echo $uid; ?>, '<?php echo $lid; ?>')" style="display: flex; align-items: center; gap: 10px; padding: 7px 14px; font-size: 13px; font-weight: 500; color: var(--sm-text-primary, #202124); cursor: pointer;">
                <input type="checkbox" id="ml-chk-<?php echo $lid; ?>" <?php if ($isChecked) echo 'checked'; ?> onclick="event.stopPropagation(); mlToggleLabel('<?php echo htmlspecialchars($mailbox, ENT_QUOTES); ?>', <?php echo $uid; ?>, '<?php echo $lid; ?>')" style="margin: 0; cursor: pointer;">
                <span class="ml-label-dot" style="width: 10px; height: 10px; min-width: 10px; min-height: 10px; border-radius: 50%; background: <?php echo htmlspecialchars($lDef['color']); ?>; flex-shrink: 0; display: inline-block;"></span>
                <span style="flex: 1;"><?php echo htmlspecialchars($lDef['name']); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="ml-dropdown-divider" style="border-top: 1px solid var(--sm-border, #dadce0); margin: 4px 0;"></div>
            <div class="ml-dropdown-footer" style="padding: 4px 14px 4px;">
                <a href="<?php echo SM_PATH; ?>plugins/message_labels/options.php" style="font-size: 12px; color: var(--sm-primary, #1a73e8); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 500;">⚙️ <?php echo _("Manage labels..."); ?></a>
            </div>
        </div>
    </div>

    <script>
    function mlToggleDropdown(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        var menu = document.getElementById('ml-dropdown');
        if (!menu) return;
        var isShown = (menu.style.display === 'block' || menu.classList.contains('show'));
        if (isShown) {
            menu.classList.remove('show');
            menu.style.display = 'none';
        } else {
            document.querySelectorAll('.ml-dropdown-menu').forEach(function(m) {
                m.classList.remove('show');
                m.style.display = 'none';
            });
            menu.classList.add('show');
            menu.style.display = 'block';
        }
    }
    document.addEventListener('click', function(e) {
        var menu = document.getElementById('ml-dropdown');
        if (menu && !menu.parentElement.contains(e.target)) {
            menu.classList.remove('show');
            menu.style.display = 'none';
        }
    });
    function mlToggleLabel(mb, uid, lid) {
        var formData = new FormData();
        formData.append('action', 'toggle_label');
        formData.append('mailbox', mb);
        formData.append('uid', uid);
        formData.append('label_id', lid);

        var ajaxUrl = (typeof window.sqmApp !== 'undefined' && window.sqmApp.getBaseUri)
            ? window.sqmApp.getBaseUri() + 'plugins/message_labels/ajax.php'
            : '<?php echo SM_PATH; ?>plugins/message_labels/ajax.php';

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var chk = document.getElementById('ml-chk-' + lid);
                if (chk) chk.checked = data.active;
                if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.navigate === 'function') {
                    window.sqmApp.navigate(window.location.href, false);
                    window.sqmApp.refreshFolders();
                } else {
                    window.location.reload();
                }
            }
        });
    }
    </script>
    <?php
    $btn = ob_get_clean();
    if (is_array($links)) {
        $links[] = $btn;
    }
    return $links;
}

/**
 * Message List Hook: Filter messages by label, prepend chips to subjects, and provide batch labeling controls
 */
function ml_message_list_controls($args = null)
{
    global $oTemplate;
    $tpl = (is_array($args) && isset($args[1]) && is_object($args[1])) ? $args[1] : $oTemplate;
    include_once(SM_PATH . 'plugins/message_labels/labels.php');
    $data = ml_load_data();
    $tplVars = ($tpl && method_exists($tpl, 'get_template_vars')) ? $tpl->get_template_vars() : array();
    $mailbox = $tplVars['mailbox'] ?? ($_GET['mailbox'] ?? 'INBOX');

    ob_start();
    ?>
    <div class="ml-dropdown-wrapper" style="position: relative; display: inline-flex; align-items: center;">
        <button type="button" class="sm-btn sm-btn-secondary sm-btn-sm ml-dropdown-btn" onclick="mlToggleBatchDropdown(event)" title="<?php echo _("Label Selected Messages"); ?>" style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer; white-space: nowrap;">
            <span>🏷️</span>
            <span><?php echo _("Labels"); ?></span>
            <span style="font-size: 8px; opacity: 0.7; margin-left: 2px;">▼</span>
        </button>
        <div id="ml-batch-dropdown" class="ml-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; min-width: 210px; background: var(--sm-bg-surface, #ffffff); border: 1px solid var(--sm-border, #dadce0); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.16), 0 2px 6px rgba(0,0,0,0.08); z-index: 10005; padding: 6px 0;">
            <div class="ml-dropdown-header" style="padding: 6px 14px 4px; font-size: 11px; font-weight: 700; color: var(--sm-text-muted, #5f6368); text-transform: uppercase; letter-spacing: 0.5px;">
                <?php echo _("Label Selected Messages"); ?>
            </div>
            <?php foreach ($data['labels'] as $lid => $lDef): ?>
            <div class="ml-dropdown-item" onclick="mlApplyBatchLabel('<?php echo htmlspecialchars($mailbox, ENT_QUOTES); ?>', '<?php echo $lid; ?>')" style="display: flex; align-items: center; gap: 10px; padding: 7px 14px; font-size: 13px; font-weight: 500; color: var(--sm-text-primary, #202124); cursor: pointer;">
                <span class="ml-label-dot" style="width: 10px; height: 10px; min-width: 10px; min-height: 10px; border-radius: 50%; background: <?php echo htmlspecialchars($lDef['color']); ?>; flex-shrink: 0; display: inline-block;"></span>
                <span style="flex: 1;"><?php echo htmlspecialchars($lDef['name']); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="ml-dropdown-divider" style="border-top: 1px solid var(--sm-border, #dadce0); margin: 4px 0;"></div>
            <div class="ml-dropdown-item" onclick="mlApplyBatchLabel('<?php echo htmlspecialchars($mailbox, ENT_QUOTES); ?>', '__clear__')" style="display: flex; align-items: center; gap: 10px; padding: 7px 14px; font-size: 13px; font-weight: 500; color: var(--sm-danger, #d93025); cursor: pointer;">
                <span style="font-size: 12px; width: 10px; text-align: center; display: inline-block;">✕</span>
                <span><?php echo _("Remove all labels"); ?></span>
            </div>
            <div class="ml-dropdown-divider" style="border-top: 1px solid var(--sm-border, #dadce0); margin: 4px 0;"></div>
            <div class="ml-dropdown-footer" style="padding: 4px 14px 4px;">
                <a href="<?php echo SM_PATH; ?>plugins/message_labels/options.php" style="font-size: 12px; color: var(--sm-primary, #1a73e8); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 500;">⚙️ <?php echo _("Manage labels..."); ?></a>
            </div>
        </div>
    </div>
    <script>
    window.mlToggleBatchDropdown = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        var menu = document.getElementById('ml-batch-dropdown');
        if (!menu) return;
        var isShown = (menu.style.display === 'block' || menu.classList.contains('show'));
        if (isShown) {
            menu.classList.remove('show');
            menu.style.display = 'none';
        } else {
            document.querySelectorAll('.ml-dropdown-menu').forEach(function(m) {
                m.classList.remove('show');
                m.style.display = 'none';
            });
            menu.classList.add('show');
            menu.style.display = 'block';
        }
    };

    if (!window.mlDropdownListenerAttached) {
        window.mlDropdownListenerAttached = true;
        document.addEventListener('click', function(e) {
            var wrapper = document.querySelector('.ml-dropdown-wrapper');
            var menu = document.getElementById('ml-batch-dropdown');
            if (menu && (!wrapper || !wrapper.contains(e.target))) {
                menu.classList.remove('show');
                menu.style.display = 'none';
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var menu = document.getElementById('ml-batch-dropdown');
                if (menu) {
                    menu.classList.remove('show');
                    menu.style.display = 'none';
                }
            }
        });
    }

    window.mlApplyBatchLabel = function(mailbox, labelId) {
        var menu = document.getElementById('ml-batch-dropdown');
        if (menu) {
            menu.classList.remove('show');
            menu.style.display = 'none';
        }

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
            alert("<?php echo _("Please select one or more messages using the checkboxes first."); ?>");
            return;
        }

        var formData = new FormData();
        formData.append('action', 'batch_toggle_label');
        formData.append('mailbox', mailbox);
        formData.append('label_id', labelId);
        formData.append('uids', uids.join(','));

        var ajaxUrl = (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.getBaseUri === 'function')
            ? window.sqmApp.getBaseUri() + 'plugins/message_labels/ajax.php'
            : '<?php echo SM_PATH; ?>plugins/message_labels/ajax.php';

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.navigate === 'function') {
                    window.sqmApp.navigate(window.location.href, false);
                    if (typeof window.sqmApp.refreshFolders === 'function') {
                        window.sqmApp.refreshFolders();
                    }
                } else {
                    window.location.reload();
                }
            } else {
                alert(data.error || 'Failed to apply label');
            }
        })
        .catch(function(err) {
            window.location.reload();
        });
    };
    </script>
    <?php
    $btn = ob_get_clean();
    return array('message_list_controls_buttons' => $btn);
}

/**
 * Message List Hook: Filter messages by label and prepend chips to subjects
 */
function ml_message_list($args = null)
{
    global $oTemplate;
    $tpl = (is_array($args) && isset($args[1]) && is_object($args[1])) ? $args[1] : $oTemplate;
    if (!$tpl) {
        return array();
    }
    $tplVars = method_exists($tpl, 'get_template_vars') ? $tpl->get_template_vars() : (isset($tpl->values) ? $tpl->values : array());
    if (empty($tplVars)) {
        return array();
    }

    include_once(SM_PATH . 'plugins/message_labels/labels.php');
    $data = ml_load_data();
    $mailbox = isset($tplVars['mailbox']) ? $tplVars['mailbox'] : 'INBOX';
    $labelFilter = isset($_GET['label_filter']) ? trim($_GET['label_filter']) : '';
    $output = array();

    // 1. If filtering by label, filter messages in the template
    if (!empty($labelFilter) && isset($data['labels'][$labelFilter])) {
        if (!empty($tplVars['aMessages']) && is_array($tplVars['aMessages'])) {
            $filtered = array();
            foreach ($tplVars['aMessages'] as $uid => $msg) {
                $key = ml_get_message_key($mailbox, $uid);
                if (isset($data['messages'][$key]) && in_array($labelFilter, $data['messages'][$key])) {
                    $filtered[$uid] = $msg;
                }
            }
            $tpl->assign('aMessages', $filtered);
            $tplVars['aMessages'] = $filtered;
        }

        $lDef = $data['labels'][$labelFilter];
        $clearUrl = 'webmail.php?mailbox=' . urlencode($mailbox);
        $banner = '<div style="margin-bottom: 12px; padding: 10px 16px; background: ' . htmlspecialchars($lDef['bg'] ?? '#e8f0fe') . '; border: 1px solid ' . htmlspecialchars($lDef['color']) . '40; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 500; color: #202124;">'
                . '<div style="display: flex; align-items: center; gap: 8px;">'
                . '<span style="font-size: 16px;">🏷️</span>'
                . '<span>' . sprintf(_("Showing messages labeled <strong style=\"color: %s;\">● %s</strong>"), htmlspecialchars($lDef['color']), htmlspecialchars($lDef['name'])) . '</span>'
                . '</div>'
                . '<a href="' . $clearUrl . '" style="color: #5f6368; text-decoration: none; font-size: 12px; font-weight: 600; padding: 4px 10px; background: rgba(255,255,255,0.85); border-radius: 4px; border: 1px solid rgba(0,0,0,0.1);">' . _("✕ Clear Filter") . '</a>'
                . '</div>';
        $output['mailbox_index_before'] = $banner;
    }

    // 2. Prepend colorful label badges to message subjects in message list
    if (!empty($tplVars['aMessages']) && is_array($tplVars['aMessages'])) {
        $msgs = $tplVars['aMessages'];
        foreach ($msgs as $uid => &$msg) {
            $msgLabels = ml_get_message_labels($mailbox, $uid);
            if (!empty($msgLabels) && isset($msg['columns'][SQM_COL_SUBJ]['value'])) {
                $chips = '';
                foreach ($msgLabels as $lid => $mDef) {
                    $chipBg = !empty($mDef['bg']) ? $mDef['bg'] : $mDef['color'] . '25';
                    $chips .= '<span class="ml-tag-chip" style="background: ' . htmlspecialchars($chipBg) . '; color: ' . htmlspecialchars($mDef['color']) . '; font-size: 11px; padding: 1px 7px; border-radius: 4px; font-weight: 600; margin-right: 6px; display: inline-flex; align-items: center; gap: 3px; vertical-align: middle; box-shadow: 0 1px 2px rgba(0,0,0,0.06);">'
                           . '● ' . htmlspecialchars($mDef['name'])
                           . '</span>';
                }
                $msg['columns'][SQM_COL_SUBJ]['value'] = $chips . $msg['columns'][SQM_COL_SUBJ]['value'];
            }
        }
        unset($msg);
        $tpl->assign('aMessages', $msgs);
    }

    return $output;
}

function ml_read_body_top()
{
    global $passed_id, $mailbox;
    include_once(SM_PATH . 'plugins/message_labels/labels.php');

    $uid = intval($passed_id);
    $activeLabels = ml_get_message_labels($mailbox, $uid);

    if (empty($activeLabels)) {
        return array();
    }

    $html = '<div style="margin: 8px 0 12px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">';
    foreach ($activeLabels as $lid => $lDef) {
        $bg = !empty($lDef['bg']) ? $lDef['bg'] : $lDef['color'] . '20';
        $html .= '<span class="ml-tag-chip" style="background: ' . htmlspecialchars($bg) . '; color: ' . htmlspecialchars($lDef['color']) . ';">'
               . '● ' . htmlspecialchars($lDef['name'])
               . '</span>';
    }
    $html .= '</div>';

    return array('read_body_top' => $html);
}

function ml_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("Message Flags &amp; Labels"),
        'url'  => SM_PATH . 'plugins/message_labels/options.php',
        'desc' => _("Create custom colored labels (Gmail style), edit colors, and organize your mailbox tags."),
        'js'   => false
    );
}
