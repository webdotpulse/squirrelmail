<?php
/**
 * Modern Calendar Data and iCal Support Functions
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage calendar
 */

if (!function_exists('getHashedFile') && defined('SM_PATH')) {
    include_once(SM_PATH . 'functions/prefs.php');
}

/**
 * Retrieve calendar data file path for user
 */
function calendar_get_store_file()
{
    global $username, $data_dir;
    return getHashedFile($username, $data_dir, "$username.calendar.json");
}

/**
 * Load all events for user
 * Migrates old .cal files if present
 */
function calendar_load_events()
{
    global $username, $data_dir;
    $file = calendar_get_store_file();

    $events = array();
    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $events = $decoded;
            }
        }
    } else {
        // Check for legacy .cal files for current/recent years
        $currentYear = intval(date('Y'));
        for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
            $legacyFile = getHashedFile($username, $data_dir, "$username.$y.cal");
            if (file_exists($legacyFile)) {
                $fp = @fopen($legacyFile, 'r');
                if ($fp) {
                    while (($fdata = fgetcsv($fp, 4096, '|', '"', '\\')) !== false) {
                        if (count($fdata) >= 6) {
                            $rawDate = $fdata[0]; // MMDDYYYY
                            if (strlen($rawDate) === 8) {
                                $isoDate = substr($rawDate, 4, 4) . '-' . substr($rawDate, 0, 2) . '-' . substr($rawDate, 2, 2);
                                $id = 'legacy_' . md5($rawDate . $fdata[1] . $fdata[4]);
                                $events[$id] = array(
                                    'id'          => $id,
                                    'title'       => str_replace(array('<br />', '<br>'), ' ', $fdata[4]),
                                    'date'        => $isoDate,
                                    'end_date'    => $isoDate,
                                    'time'        => !empty($fdata[1]) ? $fdata[1] : '09:00',
                                    'end_time'    => '10:00',
                                    'all_day'     => empty($fdata[1]),
                                    'category'    => 'work',
                                    'location'    => '',
                                    'description' => str_replace(array('<br />', '<br>'), "\n", $fdata[5]),
                                    'reminder'    => isset($fdata[6]) ? $fdata[6] : 0
                                );
                            }
                        }
                    }
                    fclose($fp);
                }
            }
        }
        if (!empty($events)) {
            calendar_save_events($events);
        }
    }

    return $events;
}

/**
 * Save all events to JSON store
 */
function calendar_save_events($events)
{
    $file = calendar_get_store_file();
    return @file_put_contents($file, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Add or update an event
 */
function calendar_save_event($event)
{
    $events = calendar_load_events();
    if (empty($event['id'])) {
        $event['id'] = 'ev_' . uniqid() . '_' . mt_rand(1000, 9999);
    }
    $events[$event['id']] = $event;
    calendar_save_events($events);
    return $event['id'];
}

/**
 * Delete an event by ID
 */
function calendar_delete_event($id)
{
    $events = calendar_load_events();
    if (isset($events[$id])) {
        unset($events[$id]);
        calendar_save_events($events);
        return true;
    }
    return false;
}

/**
 * Generate iCalendar (.ics) string for events
 */
function calendar_export_ics($events)
{
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//SquirrelMail//Modern Calendar Plugin//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";

    foreach ($events as $ev) {
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $ev['id'] . "@squirrelmail\r\n";
        $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";

        $startDate = str_replace('-', '', $ev['date']);
        if (!empty($ev['all_day'])) {
            $ics .= "DTSTART;VALUE=DATE:" . $startDate . "\r\n";
            $endDate = !empty($ev['end_date']) ? str_replace('-', '', $ev['end_date']) : $startDate;
            $ics .= "DTEND;VALUE=DATE:" . $endDate . "\r\n";
        } else {
            $startTime = !empty($ev['time']) ? str_replace(':', '', $ev['time']) . '00' : '090000';
            $ics .= "DTSTART:" . $startDate . "T" . $startTime . "\r\n";

            $endDate = !empty($ev['end_date']) ? str_replace('-', '', $ev['end_date']) : $startDate;
            $endTime = !empty($ev['end_time']) ? str_replace(':', '', $ev['end_time']) . '00' : '100000';
            $ics .= "DTEND:" . $endDate . "T" . $endTime . "\r\n";
        }

        $ics .= "SUMMARY:" . calendar_escape_ics($ev['title']) . "\r\n";
        if (!empty($ev['description'])) {
            $ics .= "DESCRIPTION:" . calendar_escape_ics($ev['description']) . "\r\n";
        }
        if (!empty($ev['location'])) {
            $ics .= "LOCATION:" . calendar_escape_ics($ev['location']) . "\r\n";
        }
        if (!empty($ev['category'])) {
            $ics .= "CATEGORIES:" . strtoupper($ev['category']) . "\r\n";
        }
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
    }

    $ics .= "END:VCALENDAR\r\n";
    return $ics;
}

/**
 * Escape string for ICS
 */
function calendar_escape_ics($text)
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(';', '\;', $text);
    $text = str_replace(',', '\,', $text);
    $text = str_replace("\r\n", "\\n", $text);
    $text = str_replace("\n", "\\n", $text);
    return $text;
}

/**
 * Parse an iCalendar (.ics) string and import events
 */
function calendar_import_ics($icsContent)
{
    $imported = 0;
    $events = calendar_load_events();

    $vevents = preg_split('/END:VEVENT/i', $icsContent);
    foreach ($vevents as $block) {
        if (!preg_match('/BEGIN:VEVENT/i', $block)) continue;

        $title = 'Untitled Event';
        $date  = date('Y-m-d');
        $endDate = $date;
        $time  = '09:00';
        $endTime = '10:00';
        $allDay = 0;
        $desc  = '';
        $loc   = '';
        $cat   = 'personal';

        if (preg_match('/SUMMARY(?:;[^:]*)?:(.*)/i', $block, $m)) {
            $title = trim(stripslashes(str_replace('\n', ' ', $m[1])));
        }
        if (preg_match('/DESCRIPTION(?:;[^:]*)?:(.*)/i', $block, $m)) {
            $desc = trim(stripslashes(str_replace('\n', "\n", $m[1])));
        }
        if (preg_match('/LOCATION(?:;[^:]*)?:(.*)/i', $block, $m)) {
            $loc = trim(stripslashes(str_replace('\n', ' ', $m[1])));
        }
        if (preg_match('/CATEGORIES(?:;[^:]*)?:(.*)/i', $block, $m)) {
            $c = strtolower(trim($m[1]));
            if (strpos($c, 'work') !== false) $cat = 'work';
            elseif (strpos($c, 'meet') !== false) $cat = 'meeting';
            elseif (strpos($c, 'urgent') !== false || strpos($c, 'imp') !== false) $cat = 'urgent';
            elseif (strpos($c, 'remind') !== false) $cat = 'reminder';
        }

        if (preg_match('/DTSTART(?:;[^:]*)?:(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2}))?/i', $block, $m)) {
            $date = $m[1] . '-' . $m[2] . '-' . $m[3];
            if (!empty($m[4])) {
                $time = $m[4] . ':' . $m[5];
            } else {
                $allDay = 1;
            }
        }

        if (preg_match('/DTEND(?:;[^:]*)?:(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2}))?/i', $block, $m)) {
            $endDate = $m[1] . '-' . $m[2] . '-' . $m[3];
            if (!empty($m[4])) {
                $endTime = $m[4] . ':' . $m[5];
            }
        } else {
            $endDate = $date;
            $endTime = date('H:i', strtotime("$time +1 hour"));
        }

        $id = 'ics_' . uniqid() . '_' . mt_rand(100, 999);
        $events[$id] = array(
            'id'          => $id,
            'title'       => $title,
            'date'        => $date,
            'end_date'    => $endDate,
            'time'        => $time,
            'end_time'    => $endTime,
            'all_day'     => $allDay,
            'category'    => $cat,
            'location'    => $loc,
            'description' => $desc,
            'reminder'    => 0
        );
        $imported++;
    }

    if ($imported > 0) {
        calendar_save_events($events);
    }

    return $imported;
}
