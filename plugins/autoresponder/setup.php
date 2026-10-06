<?php
/**
 * User Autoresponder & Mail Forwarder Plugin Setup
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage autoresponder
 */

function squirrelmail_plugin_init_autoresponder()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_page_header.tpl']['autoresponder']
        = 'autoresponder_page_header';

    $squirrelmail_plugin_hooks['optpage_register_block']['autoresponder']
        = 'autoresponder_optpage_register_block';
}

function autoresponder_info()
{
    return array(
        'english_name'           => 'User Autoresponder & Mail Forwarder',
        'version'                => '2.0.0',
        'summary'                => 'Vacation out-of-office autoresponder and email forwarding rules with date scheduling and anti-loop protection.',
        'details'                => 'Configure scheduled out-of-office auto-replies with customizable templates and sender reply frequency limits, and forward incoming emails to external addresses with local copy retention.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function autoresponder_version()
{
    $info = autoresponder_info();
    return $info['version'];
}

function autoresponder_page_header()
{
    include_once(SM_PATH . 'plugins/autoresponder/autoresponder.php');
    $config = autoresponder_get_config();

    if (autoresponder_is_vacation_active($config)) {
        $optUrl = SM_PATH . 'plugins/autoresponder/options.php';
        $turnOffUrl = SM_PATH . 'plugins/autoresponder/options.php?action=turn_off';
        $endDate = !empty($config['vacation_end']) ? $config['vacation_end'] : _("until disabled");

        $banner = '<div style="background: linear-gradient(135deg, #fef7e0 0%, #fff8e1 100%); color: #7c4a00; border-bottom: 1px solid #feefc3; padding: 8px 16px; font-size: 13px; font-weight: 500; display: flex; align-items: center; justify-content: space-between; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">'
                . '<div style="display: flex; align-items: center; gap: 8px;">'
                . '<span>🏖️</span> '
                . '<span>' . sprintf(_("Out of Office autoresponder is active (scheduled until %s)."), htmlspecialchars($endDate)) . '</span>'
                . '</div>'
                . '<div style="display: flex; gap: 12px; align-items: center;">'
                . '<a href="' . $optUrl . '" style="color: #1a73e8; text-decoration: underline; font-weight: 600;">' . _("Settings") . '</a>'
                . '<span style="color: #dadce0;">|</span>'
                . '<a href="' . $turnOffUrl . '" style="color: #d93025; text-decoration: underline; font-weight: 600;">' . _("Turn Off Now") . '</a>'
                . '</div>'
                . '</div>';

        return array('page_header_top' => $banner);
    }
    return array();
}

function autoresponder_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("Autoresponder &amp; Mail Forwarding"),
        'url'  => SM_PATH . 'plugins/autoresponder/options.php',
        'desc' => _("Configure Out of Office vacation auto-replies, scheduling dates, and automatic mail forwarding rules."),
        'js'   => false
    );
}
