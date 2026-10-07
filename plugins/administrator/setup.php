<?php

/**
 * Administrator plugin - Setup script
 *
 * Plugin allows remote administration.
 *
 * @author Philippe Mingo
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package plugins
 * @subpackage administrator
 */

/**
 * Init the plugin
 * @access private
 */
function squirrelmail_plugin_init_administrator() {
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['optpage_register_block']['administrator'] =
        'squirrelmail_administrator_optpage_register_block';
}

/**
 * Register option block
 * @access private
 */
function squirrelmail_administrator_optpage_register_block() {
    /** add authentication functions */
    include_once(SM_PATH . 'plugins/administrator/auth.php');

    if ( adm_check_user() ) {
        global $optpage_blocks;

        $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');

        $optpage_blocks[] = array(
            'name' => _("Administration"),
            'url'  => $baseUri . 'plugins/administrator/options.php',
            'desc' => _("Manage SquirrelMail primary configuration, mail server settings, folders, and security options remotely from webmail."),
            'js'   => false
        );
    }
}
