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
    $squirrelmail_plugin_hooks['special_mailbox']['filters'] = 'filters_special_mailbox';
    $squirrelmail_plugin_hooks['rename_or_delete_folder']['filters'] = 'update_for_folder_hook';
    $squirrelmail_plugin_hooks['template_construct_login_webmail.tpl']['filters'] = 'start_filters_hook';
    $squirrelmail_plugin_hooks['folder_status']['filters'] = 'filters_folder_status';
}

/**
 * Report spam folder as special mailbox
 * @param string $mb variable used by hook
 * @return string spam folder name
 * @access private
 */
function filters_special_mailbox( $mb ) {
    global $data_dir, $username;
    return( $mb == getPref($data_dir, $username, 'filters_spam_folder', 'na' ) );
}

/**
 * Register option blocks for Message Filters and SPAM Filters
 * @access public
 */
function filters_optpage_register_block() {
    global $optpage_blocks, $AllowSpamFilters;

    $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');

    $optpage_blocks[] = array(
        'name' => _("Message Filters"),
        'url'  => $baseUri . 'plugins/filters/options.php',
        'desc' => _("Automatically sort incoming email into specific folders based on sender, recipient, subject, headers, or body content (runs in webmail and in background cron)."),
        'js'   => false
    );

    if (!isset($AllowSpamFilters) || $AllowSpamFilters) {
        $optpage_blocks[] = array(
            'name' => _("SPAM Filters"),
            'url'  => $baseUri . 'plugins/filters/spamoptions.php',
            'desc' => _("SPAM filters allow you to select from various DNS based blacklists to detect junk email in your INBOX and move it to another folder (like Trash)."),
            'js'   => false
        );
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
