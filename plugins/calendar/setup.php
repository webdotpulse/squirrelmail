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

    $squirrelmail_plugin_hooks['read_body_top']['calendar']
        = 'calendar_read_body_top';

    $squirrelmail_plugin_hooks['template_construct_read_message_body.tpl']['calendar']
        = 'calendar_read_message_body';

    $squirrelmail_plugin_hooks['attachment text/calendar']['calendar']
        = 'calendar_attachment_hook';

    $squirrelmail_plugin_hooks['attachment application/ics']['calendar']
        = 'calendar_attachment_hook';

    $squirrelmail_plugin_hooks['attachment */*']['calendar']
        = 'calendar_attachment_generic_hook';

    $squirrelmail_plugin_hooks['optpage_register_block']['calendar']
        = 'calendar_optpage_register_block';
}

function calendar_info()
{
    return array(
        'english_name'           => 'Modern Calendar & Scheduling',
        'version'                => '3.2.0',
        'summary'                => 'Full-featured web calendar with month/agenda views, Google Calendar live sync, iCalendar (.ics) export/import, and email meeting scheduling.',
        'details'                => 'Modern, responsive Google Calendar-styled scheduling for SquirrelMail with live Google Calendar subscription feeds, category badges, interactive event modals, and email event integration.',
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
    global $passed_id, $mailbox, $message, $imapConnection;
    include_once(SM_PATH . 'plugins/calendar/calendar_data.php');

    $subject = '';
    if (isset($message) && isset($message->rfc822_header) && isset($message->rfc822_header->subject)) {
        $subject = rawurlencode($message->rfc822_header->subject);
    }

    $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');
    $vcalInfo = calendar_get_message_vcal($message, $imapConnection, $passed_id, $mailbox);
    $hasVcal = (!empty($vcalInfo) && !empty($vcalInfo['has_vcal']));

    $entParam = ($hasVcal && !empty($vcalInfo['ent_id'])) ? '&ent_id=' . urlencode($vcalInfo['ent_id']) : '';

    if (!empty($passed_id)) {
        $url = $baseUri . 'plugins/calendar/calendar.php?action=import_email&passed_id=' . urlencode($passed_id) . '&mailbox=' . urlencode($mailbox) . $entParam;
    } else {
        $url = $baseUri . 'plugins/calendar/calendar.php?action=new&title=' . $subject;
    }

    $calViewUrl = $baseUri . 'plugins/calendar/calendar.php';

    // Check if the event is already in the calendar
    $alreadyAdded = false;
    if ($hasVcal && !empty($vcalInfo['first_event']['id'])) {
        $userEvents = calendar_load_events();
        if (isset($userEvents[$vcalInfo['first_event']['id']])) {
            $alreadyAdded = true;
        }
    }

    static $jsRendered = false;
    $jsScript = '';
    if (!$jsRendered) {
        $jsRendered = true;
        $ajaxUrl = $baseUri . 'plugins/calendar/ajax.php';
        ob_start();
        ?>
        <script>
        function sqmAddCalendarAppointment(e, btn, passedId, mailbox, entId) {
            if (e) e.preventDefault();
            if (!btn || btn.dataset.loading === '1') return;

            var originalHtml = btn.innerHTML;
            btn.dataset.loading = '1';
            btn.innerHTML = '<span class="cal-btn-icon">⏳</span> <span><?php echo _("Adding..."); ?></span>';
            btn.style.opacity = '0.75';

            var formData = new FormData();
            formData.append('action', 'import_email');
            formData.append('passed_id', passedId);
            formData.append('mailbox', mailbox);
            if (entId) formData.append('ent_id', entId);

            var ajaxEndpoint = '<?php echo $ajaxUrl; ?>';
            if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.getBaseUri === 'function') {
                ajaxEndpoint = window.sqmApp.getBaseUri() + 'plugins/calendar/ajax.php';
            }

            fetch(ajaxEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.dataset.loading = '0';
                btn.style.opacity = '1';
                if (data.success) {
                    var successText = '<?php echo _("Added to Calendar!"); ?>';
                    btn.style.background = '#e6f4ea';
                    btn.style.color = '#137333';
                    btn.style.borderColor = '#ceead6';
                    btn.innerHTML = '<span>✓</span> <span>' + successText + '</span>';
                    btn.onclick = function(ev) {
                        ev.preventDefault();
                        var targetCalUrl = '<?php echo $calViewUrl; ?>';
                        if (data.event && data.event.date) {
                            var parts = data.event.date.split('-');
                            if (parts.length === 3) {
                                targetCalUrl += '?year=' + parts[0] + '&month=' + parseInt(parts[1], 10);
                            }
                        }
                        if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.navigate === 'function') {
                            window.sqmApp.navigate(targetCalUrl, false);
                        } else {
                            window.location.href = targetCalUrl;
                        }
                    };
                    // Update any companion banner buttons
                    var bannerBtn = document.getElementById('sm-vcal-banner-add-btn');
                    if (bannerBtn && bannerBtn !== btn) {
                        bannerBtn.style.background = '#e6f4ea';
                        bannerBtn.style.color = '#137333';
                        bannerBtn.style.borderColor = '#ceead6';
                        bannerBtn.innerHTML = '<span>✓</span> <span>' + successText + '</span>';
                    }
                    sqmShowCalendarToast(data.message || '<?php echo _("Appointment added to your calendar!"); ?>');
                } else {
                    btn.innerHTML = originalHtml;
                    alert(data.error || '<?php echo _("Could not add appointment to calendar."); ?>');
                }
            })
            .catch(function(err) {
                btn.dataset.loading = '0';
                btn.style.opacity = '1';
                // Fallback to direct navigation
                if (btn.href) {
                    if (typeof window.sqmApp !== 'undefined' && typeof window.sqmApp.navigate === 'function') {
                        window.sqmApp.navigate(btn.href, false);
                    } else {
                        window.location.href = btn.href;
                    }
                }
            });
        }

        function sqmShowCalendarToast(msg) {
            var existing = document.getElementById('sm-cal-toast');
            if (existing) existing.remove();

            var toast = document.createElement('div');
            toast.id = 'sm-cal-toast';
            toast.style.position = 'fixed';
            toast.style.bottom = '24px';
            toast.style.right = '24px';
            toast.style.background = '#202124';
            toast.style.color = '#ffffff';
            toast.style.padding = '12px 20px';
            toast.style.borderRadius = '8px';
            toast.style.boxShadow = '0 4px 16px rgba(0,0,0,0.24)';
            toast.style.fontSize = '14px';
            toast.style.fontWeight = '500';
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.gap = '10px';
            toast.style.zIndex = '99999';
            toast.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            toast.innerHTML = '<span style="color:#81c995; font-size:16px;">✓</span> <span>' + msg + '</span>'
                            + '<a href="<?php echo $calViewUrl; ?>" style="color:#8ab4f8; text-decoration:none; margin-left:8px; font-weight:600;"><?php echo _("View"); ?> &rarr;</a>';

            document.body.appendChild(toast);
            setTimeout(function() {
                if (toast && toast.parentNode) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(10px)';
                    setTimeout(function() { if (toast && toast.parentNode) toast.remove(); }, 350);
                }
            }, 5000);
        }
        </script>
        <?php
        $jsScript = ob_get_clean();
    }

    $escapedPassedId = htmlspecialchars($passed_id ?? '', ENT_QUOTES);
    $escapedMailbox = htmlspecialchars($mailbox ?? '', ENT_QUOTES);
    $escapedEntId = htmlspecialchars($vcalInfo['ent_id'] ?? '', ENT_QUOTES);

    if ($alreadyAdded) {
        $link = '<a href="' . htmlspecialchars($calViewUrl) . '" class="btn btn-secondary cal-add-btn" '
              . 'title="' . _("This appointment is already in your calendar. Click to view.") . '" '
              . 'style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 4px; text-decoration: none; background: #e6f4ea; color: #137333; border: 1px solid #ceead6;">'
              . '<span>✓</span> <span>' . _("In Calendar") . '</span>'
              . '</a>' . $jsScript;
    } else {
        $link = '<a href="' . htmlspecialchars($url) . '" class="btn btn-secondary cal-add-btn" '
              . 'id="sm-cal-add-header-btn" '
              . 'title="' . ($hasVcal ? _("Add this appointment to your calendar") : _("Add this email to your calendar")) . '" '
              . 'onclick="sqmAddCalendarAppointment(event, this, \'' . $escapedPassedId . '\', \'' . $escapedMailbox . '\', \'' . $escapedEntId . '\');" '
              . 'style="display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; font-size: 12px; font-weight: 500; border-radius: 4px; text-decoration: none; background: #f8f9fa; color: #1a73e8; border: 1px solid #dadce0;">'
              . '<span class="cal-btn-icon">📅</span> <span>' . _("Add to Calendar") . '</span>'
              . '</a>' . $jsScript;
    }

    if (is_array($links)) {
        $links[] = array(
            'URL'   => $url,
            'Text'  => ($alreadyAdded ? '✓ ' . _("In Calendar") : '📅 ' . _("Add to Calendar")),
            'html'  => $link
        );
    }
    return $links;
}

/**
 * Display an interactive Calendar Appointment Invitation Card at the top of the message
 */
function calendar_read_message_body($args = null)
{
    global $passed_id, $mailbox, $message, $imapConnection;
    include_once(SM_PATH . 'plugins/calendar/calendar_data.php');

    $vcalInfo = calendar_get_message_vcal($message, $imapConnection, $passed_id, $mailbox);
    if (empty($vcalInfo) || empty($vcalInfo['has_vcal']) || empty($vcalInfo['first_event'])) {
        return array();
    }

    $ev = $vcalInfo['first_event'];
    $title = !empty($ev['title']) ? $ev['title'] : _("Meeting Invitation");
    $dateStr = $ev['date'];
    $dt = strtotime($dateStr);
    $monthName = $dt ? date('M', $dt) : 'CAL';
    $dayNum = $dt ? date('j', $dt) : '1';
    $weekday = $dt ? date('l', $dt) : '';
    $formattedDate = $dt ? date('D, M j, Y', $dt) : $dateStr;

    $timeFormatted = '';
    if (!empty($ev['all_day'])) {
        $timeFormatted = _("All Day");
    } else {
        $timeFormatted = (!empty($ev['time']) ? $ev['time'] : '09:00')
                       . (!empty($ev['end_time']) ? ' – ' . $ev['end_time'] : '');
    }

    $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');
    $entParam = !empty($vcalInfo['ent_id']) ? '&ent_id=' . urlencode($vcalInfo['ent_id']) : '';
    $importUrl = $baseUri . 'plugins/calendar/calendar.php?action=import_email&passed_id=' . urlencode($passed_id) . '&mailbox=' . urlencode($mailbox) . $entParam;
    $calViewUrl = $baseUri . 'plugins/calendar/calendar.php';
    if ($dt) {
        $calViewUrl .= '?year=' . date('Y', $dt) . '&month=' . date('n', $dt) . '&day=' . date('j', $dt);
    }

    $userEvents = calendar_load_events();
    $alreadyAdded = !empty($ev['id']) && isset($userEvents[$ev['id']]);

    $escapedPassedId = htmlspecialchars($passed_id ?? '', ENT_QUOTES);
    $escapedMailbox = htmlspecialchars($mailbox ?? '', ENT_QUOTES);
    $escapedEntId = htmlspecialchars($vcalInfo['ent_id'] ?? '', ENT_QUOTES);

    ob_start();
    ?>
    <div class="sm-vcal-invite-box" style="margin: 0 0 16px 0; padding: 16px 20px; background: linear-gradient(135deg, #f8fafd 0%, #edf2fa 100%); border: 1px solid #c2e7ff; border-left: 5px solid #1a73e8; border-radius: 10px; box-shadow: 0 2px 8px rgba(26,115,232,0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; gap: 14px; align-items: flex-start; min-width: 260px; flex: 1;">
                <div style="width: 50px; height: 54px; background: #ffffff; border: 1px solid #c2e7ff; border-radius: 8px; overflow: hidden; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.06); flex-shrink: 0;">
                    <div style="background: #1a73e8; color: #ffffff; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2px 0; letter-spacing: 0.5px;"><?php echo htmlspecialchars($monthName); ?></div>
                    <div style="font-size: 21px; font-weight: 700; color: #1a73e8; line-height: 30px;"><?php echo htmlspecialchars($dayNum); ?></div>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: #c2e7ff; color: #004a77; padding: 2px 8px; border-radius: 10px;">📅 <?php echo _("Calendar Appointment"); ?></span>
                        <span style="font-size: 12px; color: #5f6368;"><?php echo htmlspecialchars($weekday); ?></span>
                    </div>
                    <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: #202124; line-height: 1.3;"><?php echo htmlspecialchars($title); ?></h3>
                    <div style="font-size: 13px; color: #3c4043; display: flex; flex-direction: column; gap: 3px;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span>🕒</span> <strong><?php echo _("When:"); ?></strong> <span><?php echo htmlspecialchars($formattedDate . ' • ' . $timeFormatted); ?></span>
                        </div>
                        <?php if (!empty($ev['location'])): ?>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span>📍</span> <strong><?php echo _("Where:"); ?></strong> <span><?php echo htmlspecialchars($ev['location']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($ev['organizer'])): ?>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span>👤</span> <strong><?php echo _("Organizer:"); ?></strong> <span><?php echo htmlspecialchars($ev['organizer']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; align-self: center; flex-wrap: wrap;">
                <?php if ($alreadyAdded): ?>
                <a href="<?php echo htmlspecialchars($calViewUrl); ?>" class="sm-btn" id="sm-vcal-banner-add-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; text-decoration: none; background: #e6f4ea; color: #137333; border: 1px solid #ceead6;">
                    <span>✓</span> <span><?php echo _("In Calendar"); ?></span>
                </a>
                <?php else: ?>
                <a href="<?php echo htmlspecialchars($importUrl); ?>" class="sm-btn" id="sm-vcal-banner-add-btn" onclick="sqmAddCalendarAppointment(event, this, '<?php echo $escapedPassedId; ?>', '<?php echo $escapedMailbox; ?>', '<?php echo $escapedEntId; ?>');" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; text-decoration: none; background: #1a73e8; color: #ffffff; border: 1px solid #1a73e8; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                    <span>📅</span> <span><?php echo _("Add to Calendar"); ?></span>
                </a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($calViewUrl); ?>" class="sm-btn" style="display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px; font-size: 13px; font-weight: 500; border-radius: 6px; text-decoration: none; background: #ffffff; color: #3c4043; border: 1px solid #dadce0;">
                    <span>🗓️</span> <span><?php echo _("View Calendar"); ?></span>
                </a>
            </div>
        </div>
    </div>
    <?php
    $cardHtml = ob_get_clean();

    if (is_array($args) && isset($args[0]) && is_array($args[0])) {
        if (!isset($args[0]['read_body_top'])) {
            $args[0]['read_body_top'] = '';
        }
        $args[0]['read_body_top'] .= $cardHtml;
    }

    return array('read_body_top' => $cardHtml);
}

/**
 * Direct hook for read_body_top
 */
function calendar_read_body_top(&$args = null)
{
    static $rendered = false;
    if ($rendered) {
        return array();
    }
    $res = calendar_read_message_body($args);
    if (!empty($res['read_body_top']) && empty($GLOBALS['oTemplate'])) {
        $rendered = true;
        echo $res['read_body_top'];
    }
    return $res;
}

/**
 * Hook for calendar attachments (.ics / text/calendar / application/ics)
 */
function calendar_attachment_hook(&$Args)
{
    $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');
    $id = isset($Args[2]) ? $Args[2] : '';
    $urlMailbox = isset($Args[3]) ? $Args[3] : 'INBOX';
    $ent = isset($Args[4]) ? $Args[4] : '';

    $calUrl = $baseUri . 'plugins/calendar/calendar.php?action=import_email&passed_id=' . urlencode($id) . '&mailbox=' . urlencode($urlMailbox) . '&ent_id=' . urlencode($ent);

    if (isset($Args[0]) && is_array($Args[0])) {
        $Args[0]['calendar'] = array(
            'href' => $calUrl,
            'text' => '📅 ' . _("Add to Calendar")
        );
    }

    // Direct click to calendar importer
    $Args[5] = $calUrl;

    // Normalize filename display
    if (isset($Args[6])) {
        if (substr(strtolower($Args[6]), -9) === '.calendar') {
            $Args[6] = substr($Args[6], 0, -9) . '.ics';
        }
        if (empty($Args[6]) || substr($Args[6], 0, 9) === 'untitled-') {
            $Args[6] = 'invite.ics';
        }
    }
}

/**
 * Generic attachment hook checking for .ics extension
 */
function calendar_attachment_generic_hook(&$Args)
{
    $filename = isset($Args[6]) ? strtolower($Args[6]) : '';
    $type0 = isset($Args[9]) ? strtolower($Args[9]) : '';
    $type1 = isset($Args[10]) ? strtolower($Args[10]) : '';

    if (substr($filename, -4) === '.ics' || substr($filename, -9) === '.calendar' || ($type0 === 'text' && ($type1 === 'calendar' || $type1 === 'x-vcalendar')) || ($type0 === 'application' && ($type1 === 'ics' || $type1 === 'calendar'))) {
        calendar_attachment_hook($Args);
    }
}

function calendar_optpage_register_block()
{
    global $optpage_blocks;

    $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');
    $optpage_blocks[] = array(
        'name' => _("Calendar &amp; Scheduling"),
        'url'  => $baseUri . 'plugins/calendar/calendar.php',
        'desc' => _("Manage personal events, meetings, reminders, and import/export iCalendar (.ics) files."),
        'js'   => false
    );
}
