<?php

/**
 * Message and Spam Filter Plugin - Setup
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package plugins
 * @subpackage filters
 */

/**
 * Init plugin
 * @access private
 */
function squirrelmail_plugin_init_filters() {
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['webmail_top']['filters'] = 'start_filters_hook';
    $squirrelmail_plugin_hooks['right_main_before_select']['filters'] = 'start_filters_hook';
    $squirrelmail_plugin_hooks['left_main_before']['filters'] = 'start_filters_hook';
    $squirrelmail_plugin_hooks['right_main_after_header']['filters'] = 'start_filters_hook';
    $squirrelmail_plugin_hooks['optpage_register_block']['filters'] = 'filters_optpage_register_block';
    $squirrelmail_plugin_hooks['rename_or_delete_folder']['filters'] = 'update_for_folder_hook';
    $squirrelmail_plugin_hooks['template_construct_login_webmail.tpl']['filters'] = 'start_filters_hook';
    $squirrelmail_plugin_hooks['folder_status']['filters'] = 'filters_folder_status';
}

if (!function_exists('filters_optpage_register_block')) {
/**
 * Register option blocks for Message Filters
 * @access public
 */
function filters_optpage_register_block() {
    global $optpage_blocks;

    $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');

    $optpage_blocks[] = array(
        'name' => _("Message Filters"),
        'url'  => $baseUri . 'plugins/filters/options.php',
        'desc' => _("Automatically sort incoming email into specific folders based on sender, recipient, subject, headers, or body content (runs in webmail and in background cron)."),
        'js'   => false
    );
}
}

if (!function_exists('filters_folder_status')) {
/**
 * Hook handler for folder status (e.g. INBOX message count)
 * @param array $statusarr
 */
function filters_folder_status($statusarr) {
    global $filter_inbox_count;
    if (empty($filter_inbox_count)) $filter_inbox_count = 0;

    if (isset($statusarr['MAILBOX']) && $statusarr['MAILBOX'] == 'INBOX') {
        if (!empty($statusarr['MESSAGES'])) $filter_inbox_count = $statusarr['MESSAGES'];
    }
}
}

/**
 * Backward compatibility hook alias
 * @access private
 */
function filters_optpage_register_block_hook() {
    filters_optpage_register_block();
}

/**
 * Called by hook to Start Filtering
 * @param mixed $args optional variable passed by hook
 * @access private
 */
function start_filters_hook($args) {
    include_once(SM_PATH . 'plugins/filters/filters.php');
    start_filters ($args);
}

/**
 * Called by hook to Update filters when Folders Change
 * @access private
 */
function update_for_folder_hook($args) {
    include_once(SM_PATH . 'plugins/filters/filters.php');
    update_for_folder ($args);
}
