<?php
/**
 * setup.php - Conversation View Plugin for SquirrelMail
 *
 * Provides a modern conversation thread view within the message reading view,
 * discovering related incoming emails, sent replies, and draft responses across
 * mailboxes (INBOX, Sent, and Drafts).
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage conversation_view
 */

if (!defined('SM_PATH')) {
    define('SM_PATH', '../../');
}

/**
 * Standard SquirrelMail plugin initialization
 */
function squirrelmail_plugin_init_conversation_view()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_page_header.tpl']['conversation_view']
        = 'conversation_view_page_header';

    $squirrelmail_plugin_hooks['template_construct_message_list.tpl']['conversation_view']
        = 'conversation_view_message_list';

    $squirrelmail_plugin_hooks['read_body_header_right']['conversation_view']
        = 'conversation_view_read_body_header_right';

    $squirrelmail_plugin_hooks['read_body_top']['conversation_view']
        = 'conversation_view_read_body_top';

    $squirrelmail_plugin_hooks['template_construct_read_message_body.tpl']['conversation_view']
        = 'conversation_view_read_body_top';

    $squirrelmail_plugin_hooks['read_body_bottom']['conversation_view']
        = 'conversation_view_read_body_bottom';

    $squirrelmail_plugin_hooks['optpage_register_block']['conversation_view']
        = 'conversation_view_optpage_register_block';
}

/**
 * Plugin metadata description
 *
 * @return array
 */
function conversation_view_info()
{
    return array(
        'english_name'           => 'Conversation View (Drafts & Replies)',
        'version'                => '1.0.0',
        'summary'                => 'Shows sent replies and pending drafts within the message thread view.',
        'details'                => 'Discovers and displays the entire conversation history—including sent replies from the Sent folder and pending drafts from the Drafts folder—directly in the message reader with inline preview and resume editing actions.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

/**
 * Return plugin version
 *
 * @return string
 */
function conversation_view_version()
{
    $info = conversation_view_info();
    return $info['version'];
}

/**
 * Hook: Add conversation jump button & count badge to read_body header toolbar
 *
 * @param array $links Reference to toolbar links array
 */
function conversation_view_read_body_header_right(&$links)
{
    global $imapConnection, $mailbox, $passed_id, $message, $data_dir, $username;

    // Check if plugin is disabled in preferences
    $enabled = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
    if (!$enabled) {
        return;
    }

    try {
        include_once(SM_PATH . 'plugins/conversation_view/functions.php');
        $summary = cv_get_thread_summary($imapConnection, $mailbox, $passed_id, $message);

        if (!empty($summary['total_count']) && $summary['total_count'] > 1) {
            $badgeText = _("Thread") . ' (' . $summary['total_count'] . ')';
            $title = sprintf(
                _("%d messages in conversation (%d sent, %d drafts)"),
                $summary['total_count'],
                $summary['sent_count'],
                $summary['draft_count']
            );
            $links[] = array(
                'URL'   => '#cv-conversation-thread',
                'Text'  => '💬 ' . htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8'),
                'Title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            );
        }
    } catch (\Throwable $e) {
        error_log('conversation_view_read_body_header_right error: ' . $e->getMessage());
    }
}

/**
 * Hook: Display conversation thread above message if positioned at top
 */
function conversation_view_read_body_top(&$args = null)
{
    global $imapConnection, $mailbox, $passed_id, $message, $data_dir, $username;
    static $cachedTopHtml = null;

    $enabled = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
    if (!$enabled) {
        return array();
    }

    $pos = getPref($data_dir, $username, 'conversation_view_position', 'bottom');
    if ($pos === 'top' || $pos === 'both') {
        try {
            if ($cachedTopHtml === null) {
                include_once(SM_PATH . 'plugins/conversation_view/functions.php');
                ob_start();
                cv_render_thread_view($imapConnection, $mailbox, $passed_id, $message, 'top');
                $cachedTopHtml = ob_get_clean();
            }

            if (is_array($args) && isset($args[0]) && is_array($args[0])) {
                if (!isset($args[0]['read_body_top'])) {
                    $args[0]['read_body_top'] = '';
                }
                $args[0]['read_body_top'] .= $cachedTopHtml;
            }

            return array('read_body_top' => $cachedTopHtml);
        } catch (\Throwable $e) {
            error_log('conversation_view_read_body_top error: ' . $e->getMessage());
        }
    }
    return array();
}

/**
 * Hook: Display conversation thread below message (default position)
 */
function conversation_view_read_body_bottom()
{
    global $imapConnection, $mailbox, $passed_id, $message, $data_dir, $username;

    $enabled = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
    if (!$enabled) {
        return;
    }

    $pos = getPref($data_dir, $username, 'conversation_view_position', 'bottom');
    if ($pos === 'bottom' || $pos === 'both') {
        try {
            include_once(SM_PATH . 'plugins/conversation_view/functions.php');
            cv_render_thread_view($imapConnection, $mailbox, $passed_id, $message, 'bottom');
        } catch (\Throwable $e) {
            error_log('conversation_view_read_body_bottom error: ' . $e->getMessage());
        }
    }
}

/**
 * Hook: Register in SquirrelMail Options page
 */
function conversation_view_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("Conversation View &amp; Threading"),
        'url'  => SM_PATH . 'plugins/conversation_view/options.php',
        'desc' => _("Configure cross-folder conversation threading, showing sent replies and pending drafts in message view."),
        'js'   => false
    );
}

/**
 * Hook: Inject Conversation View CSS & JS into page header
 *
 * @return array
 */
function conversation_view_page_header()
{
    global $data_dir, $username;
    $enabled = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
    if (!$enabled) {
        return array();
    }

    $baseUri = sqm_baseuri();
    $cssUrl = $baseUri . 'plugins/conversation_view/conversation.css';
    $jsUrl = $baseUri . 'plugins/conversation_view/conversation.js';

    $html = '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n"
          . '<script type="text/javascript" src="' . htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";

    return array('page_header_top' => $html);
}

/**
 * Hook: Annotate mailbox message list with draft badges and reply indicators
 *
 * @param array|null $args Arguments passed from template_construct_message_list.tpl
 * @return array
 */
function conversation_view_message_list($args = null)
{
    global $oTemplate, $imapConnection, $data_dir, $username;

    $enabled = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
    $badges = (int) getPref($data_dir, $username, 'cv_mailbox_badges', 1);
    if (!$enabled || !$badges) {
        return array();
    }

    $tpl = (is_array($args) && isset($args[1]) && is_object($args[1])) ? $args[1] : $oTemplate;
    if (!$tpl) {
        return array();
    }

    $tplVars = method_exists($tpl, 'get_template_vars') ? $tpl->get_template_vars() : (isset($tpl->values) ? $tpl->values : array());
    if (empty($tplVars) || empty($tplVars['aMessages']) || !is_array($tplVars['aMessages'])) {
        return array();
    }

    $mailbox = isset($tplVars['mailbox']) ? $tplVars['mailbox'] : 'INBOX';
    $messages = $tplVars['aMessages'];

    try {
        include_once(SM_PATH . 'plugins/conversation_view/functions.php');
        cv_mailbox_annotate_messages($messages, $mailbox, $imapConnection);
        $tpl->assign('aMessages', $messages);
    } catch (\Throwable $e) {
        error_log('conversation_view_message_list error: ' . $e->getMessage());
    }

    return array();
}
