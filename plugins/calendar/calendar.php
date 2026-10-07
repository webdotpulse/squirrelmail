<?php
/**
 * Modern Full-Featured Calendar Interface
 *
 * Month, Week, Day, and Agenda views with iCalendar (.ics) support
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage calendar
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'plugins/calendar/calendar_data.php');

// Handle .ics export
if (isset($_GET['export']) && $_GET['export'] === 'ics') {
    $events = calendar_load_events();
    $ics = calendar_export_ics($events);
    $filename = "calendar_" . date('Ymd') . ".ics";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($ics));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $ics;
    exit;
}

// Handle .ics import
$importMsg = null;
$highlightEventId = null;
$autoOpenModal = false;
$autoEventData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ics_file']) && $_FILES['ics_file']['error'] === UPLOAD_ERR_OK) {
    $content = file_get_contents($_FILES['ics_file']['tmp_name']);
    $cnt = calendar_import_ics($content);
    $importMsg = sprintf(_("Successfully imported %d event(s) from calendar file."), $cnt);
}

// Handle appointment import from email (e.g. from "Add to Calendar" button in read_body)
if (isset($_GET['action']) && $_GET['action'] === 'import_email' && !empty($_GET['passed_id'])) {
    $pId = $_GET['passed_id'];
    $mBox = isset($_GET['mailbox']) ? $_GET['mailbox'] : 'INBOX';
    $eId = isset($_GET['ent_id']) && !empty($_GET['ent_id']) ? $_GET['ent_id'] : null;

    global $imapServerAddress, $imapPort, $username, $imap_stream_options;
    $imapConn = sqimap_login($username, false, $imapServerAddress, $imapPort, 0, $imap_stream_options);
    if ($imapConn) {
        sqimap_mailbox_select($imapConn, $mBox);
        $msgObj = sqimap_get_message($imapConn, $pId, $mBox);
        $vcalContent = calendar_extract_vcalendar_from_message($imapConn, $pId, $mBox, $msgObj, $eId);
        sqimap_logout($imapConn);

        if (!empty($vcalContent)) {
            $importedEvents = array();
            $cnt = calendar_import_ics($vcalContent, $importedEvents);
            if ($cnt > 0) {
                $firstEv = reset($importedEvents);
                $highlightEventId = $firstEv['id'] ?? null;
                $importMsg = sprintf(_("Successfully added appointment '%s' (%s) to your calendar."), $firstEv['title'], $firstEv['date']);
                if (!empty($firstEv['date']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $firstEv['date'], $dm)) {
                    $_GET['year'] = intval($dm[1]);
                    $_GET['month'] = intval($dm[2]);
                    $_GET['day'] = intval($dm[3]);
                }
            } else {
                $importMsg = _("Could not find any appointments to import in the calendar data.");
            }
        } else {
            // Fallback: If no raw VCALENDAR, prefill event creation modal with email details
            $subj = '';
            if (isset($msgObj) && isset($msgObj->rfc822_header) && isset($msgObj->rfc822_header->subject)) {
                $subj = decodeHeader($msgObj->rfc822_header->subject);
            }
            $sender = '';
            if (isset($msgObj) && isset($msgObj->rfc822_header)) {
                $sender = $msgObj->rfc822_header->getAddr_s('from');
            }
            $autoOpenModal = true;
            $autoEventData = array(
                'title'       => $subj ? $subj : _("Email Appointment"),
                'date'        => date('Y-m-d'),
                'time'        => '09:00',
                'end_time'    => '10:00',
                'category'    => 'work',
                'location'    => '',
                'description' => ($sender ? "From: $sender\n" : "") . ($subj ? "Subject: $subj\n" : "")
            );
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'new') {
    $autoOpenModal = true;
    $autoEventData = array(
        'title'       => isset($_GET['title']) ? trim($_GET['title']) : '',
        'date'        => date('Y-m-d'),
        'time'        => '09:00',
        'end_time'    => '10:00',
        'category'    => 'work',
        'location'    => '',
        'description' => ''
    );
}

// Get requested view, month, year, day
$view  = isset($_GET['view']) && in_array($_GET['view'], array('month', 'week', 'day', 'agenda')) ? $_GET['view'] : 'month';
$year  = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) && is_numeric($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$day   = isset($_GET['day']) && is_numeric($_GET['day']) ? intval($_GET['day']) : intval(date('j'));

$currentDate = mktime(0, 0, 0, $month, $day, $year);
$allEvents = calendar_load_events();

displayPageHeader($color, 'None');
?>
<style>
:root {
    --cal-primary: #1a73e8;
    --cal-primary-hover: #1557b0;
    --cal-bg: #ffffff;
    --cal-border: #dadce0;
    --cal-text: #202124;
    --cal-text-muted: #5f6368;
    --cat-work: #1a73e8;
    --cat-personal: #1e8e3e;
    --cat-meeting: #9334e6;
    --cat-urgent: #d93025;
    --cat-reminder: #f29900;
}

.cal-app {
    max-width: 1280px;
    margin: 16px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--cal-text);
}

/* Header bar */
.cal-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 12px 18px;
    background: #ffffff;
    border: 1px solid var(--cal-border);
    border-radius: 12px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.cal-nav-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.cal-title {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    min-width: 200px;
}
.cal-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid var(--cal-border);
    background: #ffffff;
    color: var(--cal-text);
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s ease;
}
.cal-btn:hover {
    background: #f1f3f4;
    border-color: #c6c9ce;
}
.cal-btn-primary {
    background: var(--cal-primary);
    color: #ffffff;
    border-color: var(--cal-primary);
}
.cal-btn-primary:hover {
    background: var(--cal-primary-hover);
    color: #ffffff;
}

.cal-view-pills {
    display: inline-flex;
    background: #f1f3f4;
    border-radius: 8px;
    padding: 3px;
    gap: 2px;
}
.cal-view-pill {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    color: var(--cal-text-muted);
    transition: all 0.15s;
}
.cal-view-pill.active {
    background: #ffffff;
    color: var(--cal-primary);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    font-weight: 600;
}

/* Month Grid */
.cal-month-grid {
    background: #ffffff;
    border: 1px solid var(--cal-border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f8f9fa;
    border-bottom: 1px solid var(--cal-border);
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    color: var(--cal-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.cal-weekdays > div {
    padding: 10px 4px;
}
.cal-days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: minmax(110px, auto);
}
.cal-day-cell {
    border-right: 1px solid #f1f3f4;
    border-bottom: 1px solid #f1f3f4;
    padding: 6px;
    position: relative;
    background: #ffffff;
    transition: background 0.1s;
    min-height: 100px;
    display: flex;
    flex-direction: column;
}
.cal-day-cell:nth-child(7n) {
    border-right: none;
}
.cal-day-cell.other-month {
    background: #fafbfc;
    color: #9aa0a6;
}
.cal-day-cell.today {
    background: #f8fafd;
}
.cal-day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}
.cal-day-number {
    font-size: 12px;
    font-weight: 600;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.cal-day-cell.today .cal-day-number {
    background: var(--cal-primary);
    color: #ffffff;
}
.cal-day-add-btn {
    opacity: 0;
    background: none;
    border: none;
    font-size: 14px;
    color: var(--cal-primary);
    cursor: pointer;
    padding: 0 4px;
    transition: opacity 0.15s;
}
.cal-day-cell:hover .cal-day-add-btn {
    opacity: 1;
}

/* Event Chips */
.cal-events-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
    overflow-y: auto;
    max-height: 90px;
}
.cal-event-chip {
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    color: #ffffff;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: transform 0.1s, box-shadow 0.1s;
}
.cal-event-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.cal-event-chip.work     { background-color: var(--cat-work); }
.cal-event-chip.personal { background-color: var(--cat-personal); }
.cal-event-chip.meeting  { background-color: var(--cat-meeting); }
.cal-event-chip.urgent   { background-color: var(--cat-urgent); }
.cal-event-chip.reminder { background-color: var(--cat-reminder); }

@keyframes cal-pulse {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(26, 115, 232, 0.7); }
    50% { transform: scale(1.04); box-shadow: 0 0 0 6px rgba(26, 115, 232, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(26, 115, 232, 0); }
}
.cal-event-highlighted {
    animation: cal-pulse 1.2s ease-in-out 3;
    outline: 2px solid #ffffff;
    box-shadow: 0 0 0 2px #1a73e8, 0 4px 12px rgba(26, 115, 232, 0.35) !important;
    font-weight: 600;
}

/* Agenda / List View */
.cal-agenda-view {
    background: #ffffff;
    border: 1px solid var(--cal-border);
    border-radius: 12px;
    padding: 24px;
}
.cal-agenda-date-group {
    margin-bottom: 24px;
}
.cal-agenda-date-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--cal-primary);
    border-bottom: 2px solid #e8f0fe;
    padding-bottom: 6px;
    margin-bottom: 12px;
}
.cal-agenda-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 8px;
    cursor: pointer;
    transition: background 0.15s;
}
.cal-agenda-item:hover {
    background: #f1f3f4;
}
.cal-agenda-time {
    font-size: 12px;
    font-weight: 600;
    color: var(--cal-text-muted);
    min-width: 90px;
}
.cal-agenda-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--cal-text);
    flex: 1;
}

/* Modal */
.cal-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(32,33,36,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}
.cal-modal {
    background: #ffffff;
    width: 90%;
    max-width: 520px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    overflow: hidden;
    animation: calPop 0.18s ease-out;
}
@keyframes calPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
.cal-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--cal-border);
    background: #fafbfc;
}
.cal-modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}
.cal-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--cal-text-muted);
}
.cal-modal-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.cal-input, .cal-select, .cal-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 12px;
    border: 1px solid var(--cal-border);
    border-radius: 6px;
    font-size: 14px;
    outline: none;
}
.cal-input:focus, .cal-select:focus, .cal-textarea:focus {
    border-color: var(--cal-primary);
}
.cal-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.cal-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-top: 1px solid var(--cal-border);
    background: #fafbfc;
}
</style>

<div class="cal-app">
    <?php if ($importMsg): ?>
    <div style="background: #e6f4ea; color: #137333; padding: 12px 18px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #ceead6;">
        ✅ <?php echo htmlspecialchars($importMsg); ?>
    </div>
    <?php endif; ?>

    <!-- Navigation & Toolbar -->
    <div class="cal-topbar">
        <div class="cal-nav-group">
            <button type="button" class="cal-btn cal-btn-primary" onclick="openEventModal()">
                <span>➕</span>
                <span><?php echo _("New Event"); ?></span>
            </button>
            <a href="calendar.php?view=<?php echo $view; ?>&year=<?php echo date('Y'); ?>&month=<?php echo date('n'); ?>&day=<?php echo date('j'); ?>" class="cal-btn">
                <?php echo _("Today"); ?>
            </a>

            <?php
            $prevMonthDate = mktime(0, 0, 0, $month - 1, 1, $year);
            $nextMonthDate = mktime(0, 0, 0, $month + 1, 1, $year);
            ?>
            <a href="calendar.php?view=<?php echo $view; ?>&year=<?php echo date('Y', $prevMonthDate); ?>&month=<?php echo date('n', $prevMonthDate); ?>" class="cal-btn">&larr;</a>
            <a href="calendar.php?view=<?php echo $view; ?>&year=<?php echo date('Y', $nextMonthDate); ?>&month=<?php echo date('n', $nextMonthDate); ?>" class="cal-btn">&rarr;</a>

            <h2 class="cal-title"><?php echo date('F Y', $currentDate); ?></h2>
        </div>

        <div class="cal-nav-group">
            <div class="cal-view-pills">
                <a href="calendar.php?view=month&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="cal-view-pill <?php if ($view==='month') echo 'active'; ?>"><?php echo _("Month"); ?></a>
                <a href="calendar.php?view=agenda&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="cal-view-pill <?php if ($view==='agenda') echo 'active'; ?>"><?php echo _("Agenda"); ?></a>
            </div>

            <a href="calendar.php?export=ics" class="cal-btn" title="<?php echo _("Export to iCalendar (.ics)"); ?>">
                <span>📤</span> <span><?php echo _("Export .ics"); ?></span>
            </a>

            <button type="button" class="cal-btn" onclick="document.getElementById('cal_ics_input').click()" title="<?php echo _("Import iCalendar (.ics)"); ?>">
                <span>📥</span> <span><?php echo _("Import .ics"); ?></span>
            </button>
            <form id="cal_ics_form" method="post" enctype="multipart/form-data" style="display:none;">
                <input type="file" id="cal_ics_input" name="ics_file" accept=".ics" onchange="document.getElementById('cal_ics_form').submit()">
            </form>
        </div>
    </div>

    <!-- MAIN CALENDAR VIEW -->
    <?php if ($view === 'agenda'): ?>
        <!-- Agenda View -->
        <div class="cal-agenda-view">
            <h3 style="margin-top:0; font-size:18px; margin-bottom:16px;">📋 <?php echo _("Upcoming Events & Schedule"); ?></h3>
            <?php
            // Group events by date
            $byDate = array();
            foreach ($allEvents as $ev) {
                $d = $ev['date'];
                if (!isset($byDate[$d])) $byDate[$d] = array();
                $byDate[$d][] = $ev;
            }
            ksort($byDate);

            if (empty($byDate)): ?>
                <div style="text-align: center; padding: 48px; color: var(--cal-text-muted);">
                    <div style="font-size: 40px; margin-bottom: 8px;">📅</div>
                    <div style="font-size: 15px; font-weight: 500;"><?php echo _("No events found in this period."); ?></div>
                    <div style="font-size: 13px; margin-top: 6px;">Click <strong>+ New Event</strong> above to schedule a meeting or reminder.</div>
                </div>
            <?php else:
                foreach ($byDate as $evDate => $evList):
                    $timeStr = strtotime($evDate);
                ?>
                <div class="cal-agenda-date-group">
                    <div class="cal-agenda-date-title"><?php echo date('l, F j, Y', $timeStr); ?></div>
                    <?php
                    foreach ($evList as $ev):
                        $isHigh = (!empty($highlightEventId) && isset($ev['id']) && $ev['id'] === $highlightEventId);
                    ?>
                    <div class="cal-agenda-item<?php echo $isHigh ? ' cal-event-highlighted' : ''; ?>" onclick="openEventEdit(<?php echo htmlspecialchars(json_encode($ev)); ?>)">
                        <span class="cal-event-chip <?php echo htmlspecialchars($ev['category']); ?>" style="padding: 4px 8px;"><?php echo ucfirst(htmlspecialchars($ev['category'])); ?></span>
                        <div class="cal-agenda-time">
                            <?php echo !empty($ev['all_day']) ? _("All Day") : htmlspecialchars($ev['time'] . ' - ' . $ev['end_time']); ?>
                        </div>
                        <div class="cal-agenda-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                        <?php if (!empty($ev['location'])): ?>
                        <div style="font-size: 12px; color: var(--cal-text-muted);">📍 <?php echo htmlspecialchars($ev['location']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach;
            endif; ?>
        </div>
    <?php else: ?>
        <!-- Month View Grid -->
        <div class="cal-month-grid">
            <div class="cal-weekdays">
                <div><?php echo _("Sun"); ?></div>
                <div><?php echo _("Mon"); ?></div>
                <div><?php echo _("Tue"); ?></div>
                <div><?php echo _("Wed"); ?></div>
                <div><?php echo _("Thu"); ?></div>
                <div><?php echo _("Fri"); ?></div>
                <div><?php echo _("Sat"); ?></div>
            </div>

            <div class="cal-days-grid">
                <?php
                $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
                $daysInMonth = intval(date('t', $firstDayOfMonth));
                $startDayOfWeek = intval(date('w', $firstDayOfMonth)); // 0 (Sun) to 6 (Sat)

                // Fill previous month trailing days
                $prevMonthDays = intval(date('t', $prevMonthDate));
                for ($p = $startDayOfWeek - 1; $p >= 0; $p--) {
                    $dayNum = $prevMonthDays - $p;
                    echo '<div class="cal-day-cell other-month"><div class="cal-day-header"><span class="cal-day-number">' . $dayNum . '</span></div></div>';
                }

                $todayStr = date('Y-m-d');

                // Render current month days
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $isoDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $isToday = ($isoDate === $todayStr);

                    echo '<div class="cal-day-cell' . ($isToday ? ' today' : '') . '" id="cell-' . $isoDate . '">';
                    echo '<div class="cal-day-header">';
                    echo '<span class="cal-day-number">' . $d . '</span>';
                    echo '<button type="button" class="cal-day-add-btn" onclick="openEventModal(\'' . $isoDate . '\')" title="' . _("Add Event") . '">+</button>';
                    echo '</div>';

                    echo '<div class="cal-events-list">';
                    // Find events on this date
                    foreach ($allEvents as $ev) {
                        if ($ev['date'] === $isoDate) {
                            $cat = htmlspecialchars($ev['category'] ?? 'work');
                            $isHigh = (!empty($highlightEventId) && isset($ev['id']) && $ev['id'] === $highlightEventId);
                            $evJson = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');
                            echo '<div class="cal-event-chip ' . $cat . ($isHigh ? ' cal-event-highlighted' : '') . '" onclick="openEventEdit(' . $evJson . ')" title="' . htmlspecialchars($ev['title']) . '">';
                            if (empty($ev['all_day']) && !empty($ev['time'])) {
                                echo '<small>' . htmlspecialchars($ev['time']) . '</small> ';
                            }
                            echo htmlspecialchars($ev['title']);
                            echo '</div>';
                        }
                    }
                    echo '</div>'; // cal-events-list

                    echo '</div>'; // cal-day-cell
                }

                // Fill next month leading days to complete the 7-day row
                $totalCells = $startDayOfWeek + $daysInMonth;
                $rem = (7 - ($totalCells % 7)) % 7;
                for ($n = 1; $n <= $rem; $n++) {
                    echo '<div class="cal-day-cell other-month"><div class="cal-day-header"><span class="cal-day-number">' . $n . '</span></div></div>';
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- EVENT CREATE / EDIT MODAL -->
<div id="cal-modal-backdrop" class="cal-modal-backdrop" onclick="if(event.target===this) closeEventModal();">
    <div class="cal-modal">
        <div class="cal-modal-header">
            <h3 id="modal-title">📅 <?php echo _("Add New Event"); ?></h3>
            <button type="button" class="cal-modal-close" onclick="closeEventModal()">&times;</button>
        </div>
        <form id="cal-event-form" onsubmit="saveCalendarEvent(event)">
            <input type="hidden" id="ev-id" name="id" value="">
            <div class="cal-modal-body">
                <div>
                    <label style="font-size:12px; font-weight:600; color:var(--cal-text-muted); display:block; margin-bottom:4px;"><?php echo _("Event Title"); ?></label>
                    <input type="text" id="ev-title" name="title" class="cal-input" placeholder="e.g. Project Review Meeting" required>
                </div>

                <div class="cal-row-2">
                    <div>
                        <label style="font-size:12px; font-weight:600; color:var(--cal-text-muted); display:block; margin-bottom:4px;"><?php echo _("Date"); ?></label>
                        <input type="date" id="ev-date" name="date" class="cal-input" required>
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:600; color:var(--cal-text-muted); display:block; margin-bottom:4px;"><?php echo _("Category"); ?></label>
                        <select id="ev-category" name="category" class="cal-select">
                            <option value="work">💼 <?php echo _("Work (Blue)"); ?></option>
                            <option value="personal">🌱 <?php echo _("Personal (Green)"); ?></option>
                            <option value="meeting">👥 <?php echo _("Meeting (Purple)"); ?></option>
                            <option value="urgent">🔥 <?php echo _("Urgent (Red)"); ?></option>
                            <option value="reminder">🔔 <?php echo _("Reminder (Amber)"); ?></option>
                        </select>
                    </div>
                </div>

                <div class="cal-row-2">
                    <div>
                        <label style="font-size:12px; font-weight:600; color:var(--cal-text-muted); display:block; margin-bottom:4px;"><?php echo _("Start Time"); ?></label>
                        <input type="time" id="ev-time" name="time" class="cal-input" value="09:00">
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:600; color:var(--cal-text-muted); display:block; margin-bottom:4px;"><?php echo _("End Time"); ?></label>
                        <input type="time" id="ev-endtime" name="end_time" class="cal-input" value="10:00">
                    </div>
                </div>

                <div>
                    <label style="font-size:12px; font-weight:600; color:var(--cal-text-muted); display:block; margin-bottom:4px;"><?php echo _("Location / Meeting Link"); ?></label>
                    <input type="text" id="ev-location" name="location" class="cal-input" placeholder="e.g. Conference Room A or Zoom link">
                </div>

                <div>
                    <label style="font-size:12px; font-weight:600; color:var(--cal-text-muted); display:block; margin-bottom:4px;"><?php echo _("Description / Notes"); ?></label>
                    <textarea id="ev-description" name="description" class="cal-textarea" rows="3" placeholder="Notes, agenda, or discussion points..."></textarea>
                </div>
            </div>

            <div class="cal-modal-footer">
                <button type="button" id="btn-ev-delete" class="cal-btn" style="color:#d93025; border-color:#fad2cf; display:none;" onclick="deleteCalendarEvent()"><?php echo _("Delete"); ?></button>
                <div style="display:flex; gap:8px; margin-left:auto;">
                    <button type="button" class="cal-btn" onclick="closeEventModal()"><?php echo _("Cancel"); ?></button>
                    <button type="submit" class="cal-btn cal-btn-primary"><?php echo _("Save Event"); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openEventModal(dateStr) {
    document.getElementById('cal-event-form').reset();
    document.getElementById('ev-id').value = '';
    document.getElementById('modal-title').textContent = '📅 <?php echo _("Add New Event"); ?>';
    document.getElementById('btn-ev-delete').style.display = 'none';
    if (dateStr) {
        document.getElementById('ev-date').value = dateStr;
    } else {
        document.getElementById('ev-date').value = new Date().toISOString().split('T')[0];
    }
    document.getElementById('cal-modal-backdrop').style.display = 'flex';
}

function openEventEdit(ev) {
    document.getElementById('ev-id').value = ev.id || '';
    document.getElementById('ev-title').value = ev.title || '';
    document.getElementById('ev-date').value = ev.date || '';
    document.getElementById('ev-time').value = ev.time || '09:00';
    document.getElementById('ev-endtime').value = ev.end_time || '10:00';
    document.getElementById('ev-category').value = ev.category || 'work';
    document.getElementById('ev-location').value = ev.location || '';
    document.getElementById('ev-description').value = ev.description || '';
    
    document.getElementById('modal-title').textContent = '✏️ <?php echo _("Edit Event"); ?>';
    document.getElementById('btn-ev-delete').style.display = 'inline-flex';
    document.getElementById('cal-modal-backdrop').style.display = 'flex';
}

function closeEventModal() {
    document.getElementById('cal-modal-backdrop').style.display = 'none';
}

function saveCalendarEvent(e) {
    e.preventDefault();
    var form = document.getElementById('cal-event-form');
    var formData = new FormData(form);
    formData.append('action', 'save_event');

    fetch('ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error saving event: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Request failed: ' + err);
    });
}

function deleteCalendarEvent() {
    var id = document.getElementById('ev-id').value;
    if (!id || !confirm('Are you sure you want to delete this event?')) return;

    var formData = new FormData();
    formData.append('action', 'delete_event');
    formData.append('id', id);

    fetch('ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error deleting event: ' + (data.error || 'Unknown error'));
        }
    });
}

<?php if (!empty($autoOpenModal) && !empty($autoEventData)): ?>
document.addEventListener('DOMContentLoaded', function() {
    openEventEdit(<?php echo json_encode($autoEventData); ?>);
});
<?php endif; ?>
</script>
<?php
echo "</body></html>\n";
