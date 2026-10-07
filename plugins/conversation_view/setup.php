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

    $squirrelmail_plugin_hooks['read_body_header_right']['conversation_view']
        = 'conversation_view_read_body_header_right';

    $squirrelmail_plugin_hooks['read_body_top']['conversation_view']
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
}

/**
 * Hook: Display conversation thread above message if positioned at top
 */
function conversation_view_read_body_top()
{
    global $imapConnection, $mailbox, $passed_id, $message, $data_dir, $username;

    $enabled = (int) getPref($data_dir, $username, 'conversation_view_enabled', 1);
    if (!$enabled) {
        return;
    }

    $pos = getPref($data_dir, $username, 'conversation_view_position', 'bottom');
    if ($pos === 'top' || $pos === 'both') {
        include_once(SM_PATH . 'plugins/conversation_view/functions.php');
        cv_render_thread_view($imapConnection, $mailbox, $passed_id, $message, 'top');
    }
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
        include_once(SM_PATH . 'plugins/conversation_view/functions.php');
        cv_render_thread_view($imapConnection, $mailbox, $passed_id, $message, 'bottom');
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
