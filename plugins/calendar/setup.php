<?php
/**
 * Modern Calendar Plugin Setup & Registration
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage calendar
 */

function squirrelmail_plugin_init_calendar()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_page_header.tpl']['calendar']
        = 'calendar_page_header';

    $squirrelmail_plugin_hooks['read_body_header_right']['calendar']
        = 'calendar_read_body_header_right';

    $squirrelmail_plugin_hooks['optpage_register_block']['calendar']
        = 'calendar_optpage_register_block';
}

function calendar_info()
{
    return array(
        'english_name'           => 'Modern Calendar & Scheduling',
        'version'                => '3.0.0',
        'summary'                => 'Full-featured web calendar with month/agenda views, iCalendar (.ics) export/import, and email meeting scheduling.',
        'details'                => 'Modern, responsive Google Calendar-styled scheduling for SquirrelMail with category badges, interactive event modals, and email event integration.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function calendar_version()
{
    $info = calendar_info();
    return $info['version'];
}

function calendar_page_header()
{
    include_once(SM_PATH . 'plugins/calendar/functions.php');
    return calendar_do();
}

function calendar_read_body_header_right(&$links)
{
    global $passed_id, $mailbox, $message;

    $subject = '';
    if (isset($message) && isset($message->rfc822_header) && isset($message->rfc822_header->subject)) {
        $subject = rawurlencode($message->rfc822_header->subject);
    }

    $url = SM_PATH . 'plugins/calendar/calendar.php?action=new&title=' . $subject;
    $link = '<a href="' . $url . '" class="btn btn-secondary" target="right" '
          . 'title="' . _("Add this email to your calendar") . '" '
          . 'style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 4px; text-decoration: none; background: #f8f9fa; color: #1a73e8; border: 1px solid #dadce0;">'
          . '<span>📅</span> <span>' . _("Add to Calendar") . '</span>'
          . '</a>';

    if (is_array($links)) {
        $links[] = $link;
    }
    return $links;
}

function calendar_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("Calendar &amp; Scheduling"),
        'url'  => SM_PATH . 'plugins/calendar/calendar.php',
        'desc' => _("Manage personal events, meetings, reminders, and import/export iCalendar (.ics) files."),
        'js'   => false
    );
}
