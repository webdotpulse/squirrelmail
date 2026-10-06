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

    $html = '<div style="margin-top: 16px; padding: 0 10px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">'
          . '<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #5f6368; letter-spacing: 0.8px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">'
          . '<span>🏷️ ' . _("Labels") . '</span>'
          . '<a href="../plugins/message_labels/options.php" style="color: #1a73e8; text-decoration: none; font-size: 14px;" title="' . _("Manage Labels") . '">+</a>'
          . '</div>'
          . '<div style="display: flex; flex-direction: column; gap: 2px;">';

    foreach ($data['labels'] as $lid => $lDef) {
        $count = isset($counts[$lid]) ? $counts[$lid] : 0;
        $color = $lDef['color'];
        $url = '../src/right_main.php?mailbox=INBOX&label_filter=' . urlencode($lid);

        $html .= '<a href="' . $url . '" style="display: flex; align-items: center; justify-content: space-between; padding: 5px 8px; border-radius: 6px; text-decoration: none; font-size: 13px; color: #3c4043; transition: background 0.15s;" onmouseover="this.style.background=\'#f1f3f4\'" onmouseout="this.style.background=\'transparent\'">'
               . '<div style="display: flex; align-items: center; gap: 8px;">'
               . '<span style="width: 10px; height: 10px; border-radius: 50%; background: ' . htmlspecialchars($color) . '; display: inline-block;"></span>'
               . '<span>' . htmlspecialchars($lDef['name']) . '</span>'
               . '</div>'
               . ($count > 0 ? '<span style="font-size: 11px; font-weight: 600; color: #5f6368; background: #e8eaed; padding: 1px 6px; border-radius: 10px;">' . $count . '</span>' : '')
               . '</a>';
    }

    $html .= '</div></div>';
    return array('left_main_bottom' => $html);
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
    <div style="position: relative; display: inline-block;">
        <button type="button" class="btn btn-secondary" onclick="mlToggleDropdown()" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 4px; border: 1px solid #dadce0; background: #ffffff; color: #3c4043; cursor: pointer;">
            <span>🏷️</span> <span><?php echo _("Labels"); ?></span> <span>▼</span>
        </button>
        <div id="ml-dropdown" class="ml-dropdown-menu">
            <div style="padding: 4px 14px 8px; font-size: 11px; font-weight: 700; color: #5f6368; text-transform: uppercase;">
                <?php echo _("Label Message As"); ?>
            </div>
            <?php foreach ($data['labels'] as $lid => $lDef):
                $isChecked = isset($activeLabels[$lid]);
            ?>
            <div class="ml-dropdown-item" onclick="mlToggleLabel('<?php echo htmlspecialchars($mailbox, ENT_QUOTES); ?>', <?php echo $uid; ?>, '<?php echo $lid; ?>')">
                <input type="checkbox" id="ml-chk-<?php echo $lid; ?>" <?php if ($isChecked) echo 'checked'; ?> onclick="event.stopPropagation(); mlToggleLabel('<?php echo htmlspecialchars($mailbox, ENT_QUOTES); ?>', <?php echo $uid; ?>, '<?php echo $lid; ?>')">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo htmlspecialchars($lDef['color']); ?>;"></span>
                <span><?php echo htmlspecialchars($lDef['name']); ?></span>
            </div>
            <?php endforeach; ?>
            <div style="border-top: 1px solid #dadce0; margin-top: 6px; padding: 6px 14px 2px;">
                <a href="<?php echo SM_PATH; ?>plugins/message_labels/options.php" style="font-size: 12px; color: #1a73e8; text-decoration: none;">⚙️ <?php echo _("Manage labels..."); ?></a>
            </div>
        </div>
    </div>

    <script>
    function mlToggleDropdown() {
        var menu = document.getElementById('ml-dropdown');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
    document.addEventListener('click', function(e) {
        var menu = document.getElementById('ml-dropdown');
        if (menu && !menu.parentElement.contains(e.target)) {
            menu.style.display = 'none';
        }
    });
    function mlToggleLabel(mb, uid, lid) {
        var formData = new FormData();
        formData.append('action', 'toggle_label');
        formData.append('mailbox', mb);
        formData.append('uid', uid);
        formData.append('label_id', lid);

        fetch('<?php echo SM_PATH; ?>plugins/message_labels/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                var chk = document.getElementById('ml-chk-' + lid);
                if (chk) chk.checked = data.active;
                window.location.reload();
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
