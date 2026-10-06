<?php
/**
 * Multi-Account Plugin for SquirrelMail
 *
 * Provides a unified multi-account inbox, account switcher, and identity integration.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage multi_account
 */

function squirrelmail_plugin_init_multi_account()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_left_main.tpl']['multi_account']
        = 'multi_account_left_main';

    $squirrelmail_plugin_hooks['template_construct_page_header.tpl']['multi_account']
        = 'multi_account_page_header';

    $squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['multi_account']
        = 'multi_account_compose_close';

    $squirrelmail_plugin_hooks['optpage_register_block']['multi_account']
        = 'multi_account_optpage_register_block';
}

function multi_account_info()
{
    return array(
        'english_name'           => 'Unified Multi-Account Manager & Inbox',
        'version'                => '2.5.0',
        'summary'                => 'Unified inbox aggregating messages across multiple email accounts with distinct color badges.',
        'details'                => 'Connect multiple IMAP mail accounts (e.g. Work, Combell, Gmail, Personal) into a single unified multi-account inbox with real-time unread badges, filter tabs, and identity management.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function multi_account_version()
{
    $info = multi_account_info();
    return $info['version'];
}

function multi_account_left_main()
{
    include_once(SM_PATH . 'plugins/multi_account/multi_account.php');
    return multi_account_left_main_do();
}

function multi_account_page_header()
{
    include_once(SM_PATH . 'plugins/multi_account/multi_account.php');
    return multi_account_page_header_do();
}

function multi_account_compose_close()
{
    include_once(SM_PATH . 'plugins/multi_account/multi_account.php');
    return multi_account_compose_close_do();
}

function multi_account_optpage_register_block()
{
    include_once(SM_PATH . 'plugins/multi_account/multi_account.php');
    return multi_account_optpage_register_block_do();
}
