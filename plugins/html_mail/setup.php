<?php
/**
 * HTML Mail Plugin for SquirrelMail
 *
 * Provides a rich WYSIWYG HTML compose editor and RFC-compliant 
 * multipart/alternative MIME message delivery (HTML + plain text fallback).
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage html_mail
 */

/**
 * Register this plugin with SquirrelMail
 */
function squirrelmail_plugin_init_html_mail() 
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_compose_buttons.tpl']['html_mail']
        = 'html_mail_compose_buttons';

    $squirrelmail_plugin_hooks['compose_form']['html_mail']
        = 'html_mail_compose_form';

    $squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['html_mail']
        = 'html_mail_compose_close';

    $squirrelmail_plugin_hooks['compose_send']['html_mail']
        = 'html_mail_compose_send';

    $squirrelmail_plugin_hooks['optpage_register_block']['html_mail']
        = 'html_mail_optpage_register_block';
}

/**
 * Returns info about this plugin
 *
 * @return array An array of plugin information.
 */
function html_mail_info()
{
    return array(
        'english_name'           => 'HTML Mail',
        'version'                => '2.0.0',
        'summary'                => 'Rich WYSIWYG HTML compose editor and dual-format (multipart/alternative) email delivery.',
        'details'                => 'Provides a modern rich-text formatting toolbar in Compose window and builds compliant multipart/alternative MIME emails so recipients view rich HTML with text fallback.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

/**
 * Returns version info about this plugin
 */
function html_mail_version()
{
    $info = html_mail_info();
    return $info['version'];
}

/**
 * Hook wrappers
 */
function html_mail_compose_buttons()
{
    include_once(SM_PATH . 'plugins/html_mail/html_mail.php');
    return html_mail_compose_buttons_do();
}

function html_mail_compose_form()
{
    include_once(SM_PATH . 'plugins/html_mail/html_mail.php');
    return html_mail_compose_form_do();
}

function html_mail_compose_close()
{
    include_once(SM_PATH . 'plugins/html_mail/html_mail.php');
    return html_mail_compose_close_do();
}

function html_mail_compose_send(&$composeMessage)
{
    include_once(SM_PATH . 'plugins/html_mail/html_mail.php');
    return html_mail_compose_send_do($composeMessage);
}

function html_mail_optpage_register_block()
{
    include_once(SM_PATH . 'plugins/html_mail/html_mail.php');
    return html_mail_optpage_register_block_do();
}
