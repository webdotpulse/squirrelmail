<?php

/**
 * view_text.php -- View a text attachment
 *
 * Used by attachment_common code.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 */

/** This is the view_text page */
define('PAGE_NAME', 'view_text');

/** SquirrelMail required files. */
include('../include/init.php');
include(SM_PATH . 'functions/imap_general.php');
include(SM_PATH . 'functions/imap_messages.php');
include(SM_PATH . 'functions/mime.php');
include(SM_PATH . 'functions/date.php');
include(SM_PATH . 'functions/url_parser.php');

sqgetGlobalVar('messages',   $messages,     SQ_SESSION);
sqgetGlobalVar('mailbox',    $mailbox,      SQ_GET);
sqgetGlobalVar('ent_id',     $ent_id,       SQ_GET);
sqgetGlobalVar('passed_ent_id', $passed_ent_id, SQ_GET);
sqgetGlobalVar('QUERY_STRING', $QUERY_STRING, SQ_SERVER);
sqgetGlobalVar('passed_id', $passed_id, SQ_GET, NULL, SQ_TYPE_BIGINT);

global $imap_stream_options; // in case not defined in config
$imapConnection = sqimap_login($username, false, $imapServerAddress, $imapPort, 0, $imap_stream_options);
$mbx_response = sqimap_mailbox_select($imapConnection, $mailbox);

$message = &$messages[$mbx_response['UIDVALIDITY']][$passed_id];
if (!is_object($message)) {
    $message = sqimap_get_message($imapConnection, $passed_id, $mailbox);
}
$message_ent = $message->getEntity($ent_id);
if ($passed_ent_id) {
    $message = $message->getEntity($passed_ent_id);
}
$header   = $message_ent->header;
$type0    = $header->type0;
$type1    = $header->type1;
$charset  = $header->getParameter('charset');
$encoding = strtolower($header->encoding);

$msg_url   = 'read_body.php?' . $QUERY_STRING;
$msg_url   = set_url_var($msg_url, 'ent_id', 0);
$dwnld_url = '../src/download.php?' . $QUERY_STRING . '&amp;absolute_dl=true';
$unsafe_url = 'view_text.php?' . $QUERY_STRING;
$unsafe_url = set_url_var($unsafe_url, 'view_unsafe_images', 1);


$body = mime_fetch_body($imapConnection, $passed_id, $ent_id);
$body = decodeBody($body, $encoding);
do_hook('message_body', $body);

if (isset($languages[$squirrelmail_language]['XTRA_CODE']) &&
    function_exists($languages[$squirrelmail_language]['XTRA_CODE'].'_decode')) {
    if (mb_detect_encoding($body) != 'ASCII') {
        $body = call_user_func($languages[$squirrelmail_language]['XTRA_CODE'] . '_decode', $body);
    }
}

if ($type1 == 'html' || (isset($override_type1) &&  $override_type1 == 'html')) {
    $ishtml = TRUE;
    // html attachment with character set information
    if (! empty($charset)) {
        $body = charset_decode($charset,$body,false,true);
    }
    $body = MagicHTML( $body, $passed_id, $message, $mailbox);
} else if ($type1 == 'calendar' || $type1 == 'x-vcalendar' || $type1 == 'ics' || stripos($body, 'BEGIN:VCALENDAR') !== false) {
    $ishtml = TRUE;
    $rawBody = $body;
    $cardHtml = '';
    if (file_exists(SM_PATH . 'plugins/calendar/calendar_data.php')) {
        include_once(SM_PATH . 'plugins/calendar/calendar_data.php');
        $events = calendar_parse_ics($body);
        if (!empty($events)) {
            $ev = reset($events);
            $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');
            $importUrl = $baseUri . 'plugins/calendar/calendar.php?action=import_email&passed_id=' . urlencode($passed_id) . '&mailbox=' . urlencode($mailbox) . '&ent_id=' . urlencode($ent_id);
            $calViewUrl = $baseUri . 'plugins/calendar/calendar.php';
            $title = !empty($ev['title']) ? htmlspecialchars($ev['title']) : _("Meeting Invitation");
            $dateStr = !empty($ev['date']) ? $ev['date'] : '';
            $dt = strtotime($dateStr);
            $formattedDate = $dt ? date('D, M j, Y', $dt) : $dateStr;
            $timeStr = !empty($ev['all_day']) ? _("All Day") : ((!empty($ev['time']) ? $ev['time'] : '') . (!empty($ev['end_time']) ? ' – ' . $ev['end_time'] : ''));
            $loc = !empty($ev['location']) ? htmlspecialchars($ev['location']) : '';
            $org = !empty($ev['organizer']) ? htmlspecialchars($ev['organizer']) : '';
            $desc = !empty($ev['description']) ? nl2br(htmlspecialchars($ev['description'])) : '';

            $cardHtml = '<div style="margin: 0 0 20px 0; padding: 20px 24px; background: linear-gradient(135deg, #f8fafd 0%, #edf2fa 100%); border: 1px solid #c2e7ff; border-left: 5px solid #1a73e8; border-radius: 10px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">'
                      . '<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">'
                      . '<div style="flex: 1; min-width: 240px;">'
                      . '<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #004a77; background: #c2e7ff; display: inline-block; padding: 2px 8px; border-radius: 10px; margin-bottom: 8px;">📅 ' . _("Calendar Appointment") . '</div>'
                      . '<h2 style="margin: 0 0 10px 0; font-size: 18px; color: #202124;">' . $title . '</h2>'
                      . '<div style="font-size: 14px; color: #3c4043; line-height: 1.6;">'
                      . '<div><strong>🕒 ' . _("When:") . '</strong> ' . htmlspecialchars($formattedDate) . ($timeStr ? ' • ' . htmlspecialchars($timeStr) : '') . '</div>'
                      . ($loc ? '<div><strong>📍 ' . _("Where:") . '</strong> ' . $loc . '</div>' : '')
                      . ($org ? '<div><strong>👤 ' . _("Organizer:") . '</strong> ' . $org . '</div>' : '')
                      . '</div>'
                      . ($desc ? '<div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid #e0e0e0; font-size: 13px; color: #444;">' . $desc . '</div>' : '')
                      . '</div>'
                      . '<div style="display: flex; gap: 8px; align-self: center;">'
                      . '<a href="' . htmlspecialchars($importUrl) . '" class="sm-btn" style="padding: 8px 16px; background: #1a73e8; color: #fff; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">📅 ' . _("Add to Calendar") . '</a>'
                      . '<a href="' . htmlspecialchars($calViewUrl) . '" class="sm-btn" style="padding: 8px 14px; background: #fff; color: #3c4043; border: 1px solid #dadce0; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500;">' . _("View Calendar") . '</a>'
                      . '</div>'
                      . '</div></div>';
        }
    }
    $body = $cardHtml . '<details style="margin-top: 10px;"><summary style="cursor: pointer; color: #5f6368; font-size: 13px; font-family: monospace;">' . _("View Raw iCalendar (.ics) Source") . '</summary><pre style="background: #f1f3f4; padding: 12px; border-radius: 6px; overflow: auto; font-size: 12px; margin-top: 8px;">' . htmlspecialchars($rawBody) . '</pre></details>';
} else {
    $ishtml = FALSE;
    translateText($body, $wrap_at, $charset);
}

displayPageHeader($color);

$oTemplate->assign('view_message_href', $msg_url);
$oTemplate->assign('download_href', $dwnld_url);
$oTemplate->assign('view_unsafe_image_href', $ishtml ? $unsafe_url : '');
$oTemplate->assign('body', $body);

$oTemplate->display('view_text.tpl');

$oTemplate->display('footer.tpl');
