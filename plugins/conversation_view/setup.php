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
        'english_name'           => 'Conversation View (Mailbox Threading & Replies)',
        'version'                => '1.1.0',
        'summary'                => 'Shows sent replies and pending drafts in the mailbox thread view.',
        'details'                => 'Organizes emails in the inbox and subfolders into conversation threads, nesting sent replies and pending drafts directly under the emails with clear indentation and quick actions.',
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
 * Legacy hook: Deprecated / removed in reader view
 */
function conversation_view_read_body_header_right(&$links)
{
    return;
}

/**
 * Legacy hook: Deprecated / removed in reader view
 */
function conversation_view_read_body_top(&$args = null)
{
    return array();
}

/**
 * Legacy hook: Deprecated / removed in reader view
 */
function conversation_view_read_body_bottom()
{
    return;
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
        'desc' => _("Configure mailbox thread view options, including sent replies and pending drafts inclusion."),
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
 * Hook: Handle mailbox message list in thread view (nesting replies & drafts) or annotate in unthread view
 *
 * @param array|null $args Arguments passed from template_construct_message_list.tpl
 * @return array
 */
function conversation_view_message_list($args = null)
{
    global $oTemplate, $imapConnection, $data_dir, $username;

    $enabled = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
    if (!$enabled) {
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
    $sort = isset($tplVars['sort']) ? (int)$tplVars['sort'] : (isset($GLOBALS['aMailbox']['SORT']) ? (int)$GLOBALS['aMailbox']['SORT'] : 0);
    $isThreadView = ($sort & SQSORT_THREAD) || (!empty($_GET['srt']) && ((int)$_GET['srt'] & SQSORT_THREAD));

    try {
        include_once(SM_PATH . 'plugins/conversation_view/functions.php');
        if ($isThreadView) {
            // Build full mailbox thread view with replies & drafts nested under emails
            cv_mailbox_thread_view($messages, $mailbox, $imapConnection, $tpl);
        } else {
            // Unthread view: annotate badges if enabled in preferences
            $badges = (int) getPref($data_dir, $username, 'cv_mailbox_badges', 1);
            if ($badges) {
                cv_mailbox_annotate_messages($messages, $mailbox, $imapConnection);
            }
        }
        $tpl->assign('aMessages', $messages);
    } catch (\Throwable $e) {
        error_log('conversation_view_message_list error: ' . $e->getMessage());
    }

    return array();
}
