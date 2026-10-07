<?php
/**
 * Modern Calendar Data and iCal Support Functions
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage calendar
 */

if (defined('SM_PATH')) {
    include_once(SM_PATH . 'functions/prefs.php');
    include_once(SM_PATH . 'functions/imap.php');
    include_once(SM_PATH . 'functions/imap_mailbox.php');
    include_once(SM_PATH . 'functions/mime.php');
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
 * Unescape string from ICS
 */
function calendar_unescape_ics($text)
{
    $text = str_replace(array('\n', '\N'), "\n", $text);
    $text = str_replace('\;', ';', $text);
    $text = str_replace('\,', ',', $text);
    $text = str_replace('\\\\', '\\', $text);
    return trim($text);
}

/**
 * Parse an iCalendar date-time string into an array(date, time, all_day)
 * Converts UTC (Z) to local timezone and handles VALUE=DATE and TZID
 */
function calendar_parse_dt($dtStr, $tzStr = null)
{
    $dtStr = trim($dtStr);
    if (empty($dtStr)) return null;

    // Date-only: YYYYMMDD
    if (preg_match('/^([0-9]{8})$/', $dtStr, $m)) {
        $date = substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2);
        return array('date' => $date, 'time' => '09:00', 'all_day' => 1);
    }

    // Has time: YYYYMMDDTHHMMSS or YYYYMMDDTHHMM, optional Z
    if (preg_match('/^([0-9]{8})T([0-9]{2})([0-9]{2})([0-9]{2})?(Z)?/i', $dtStr, $m)) {
        $year = substr($m[1], 0, 4);
        $month = substr($m[1], 4, 2);
        $day = substr($m[1], 6, 2);
        $hour = $m[2];
        $min = $m[3];
        $sec = !empty($m[4]) ? $m[4] : '00';
        $isUtc = !empty($m[5]);

        try {
            if ($isUtc) {
                $dt = new \DateTime("$year-$month-$day $hour:$min:$sec", new \DateTimeZone('UTC'));
                $targetTz = new \DateTimeZone(date_default_timezone_get() ?: 'UTC');
                $dt->setTimezone($targetTz);
                return array('date' => $dt->format('Y-m-d'), 'time' => $dt->format('H:i'), 'all_day' => 0);
            }
        } catch (\Throwable $e) {}

        return array('date' => "$year-$month-$day", 'time' => "$hour:$min", 'all_day' => 0);
    }

    return null;
}

/**
 * Parse an iCalendar (.ics) string and return array of event arrays without saving
 *
 * @param string $icsContent
 * @return array
 */
function calendar_parse_ics($icsContent)
{
    $events = array();
    if (empty($icsContent) || !is_string($icsContent)) {
        return $events;
    }

    // Unfold RFC 5545 / RFC 2445 lines (CRLF or LF followed by a space or tab)
    $unfolded = preg_replace("/\r\n[ \t]|\r[ \t]|\n[ \t]/", "", $icsContent);

    // Strictly extract VEVENT blocks to prevent VTIMEZONE or other components from polluting event data
    $veventBlocks = array();
    if (preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/is', $unfolded, $veventMatches)) {
        $veventBlocks = $veventMatches[1];
    } else {
        // Fallback for single raw VEVENT block without END:VEVENT
        if (stripos($unfolded, 'BEGIN:VEVENT') !== false) {
            $parts = preg_split('/BEGIN:VEVENT/i', $unfolded, 2);
            if (!empty($parts[1])) {
                $veventBlocks[] = $parts[1];
            }
        }
    }

    foreach ($veventBlocks as $block) {
        $title = 'Untitled Event';
        $date = date('Y-m-d');
        $endDate = $date;
        $time = '09:00';
        $endTime = '10:00';
        $allDay = 0;
        $desc = '';
        $loc = '';
        $cat = 'meeting';
        $uid = '';
        $organizer = '';

        if (preg_match('/SUMMARY(?:;[^:]*)?:([^\r\n]+)/i', $block, $m)) {
            $title = calendar_unescape_ics($m[1]);
        }
        if (preg_match('/DESCRIPTION(?:;[^:]*)?:(.*)/i', $block, $m)) {
            $desc = calendar_unescape_ics($m[1]);
        }
        if (preg_match('/LOCATION(?:;[^:]*)?:([^\r\n]+)/i', $block, $m)) {
            $loc = calendar_unescape_ics($m[1]);
        }

        // Parse ORGANIZER strictly from the ORGANIZER line itself (never picking up ATTENDEE CN)
        if (preg_match('/ORGANIZER((?:;[^:]*)?):([^\r\n]+)/i', $block, $m)) {
            $params = $m[1];
            $organizerMail = preg_replace('/^mailto:/i', '', trim(calendar_unescape_ics($m[2])));
            if (preg_match('/CN="?([^;":]+)"?/i', $params, $cn)) {
                $organizer = trim($cn[1]) . ' (' . $organizerMail . ')';
            } else {
                $organizer = $organizerMail;
            }
        }

        if (preg_match('/UID(?:;[^:]*)?:([^\r\n]+)/i', $block, $m)) {
            $uid = trim($m[1]);
        }
        if (preg_match('/CATEGORIES(?:;[^:]*)?:([^\r\n]+)/i', $block, $m)) {
            $c = strtolower(trim($m[1]));
            if (strpos($c, 'work') !== false) $cat = 'work';
            elseif (strpos($c, 'meet') !== false) $cat = 'meeting';
            elseif (strpos($c, 'urgent') !== false || strpos($c, 'imp') !== false) $cat = 'urgent';
            elseif (strpos($c, 'remind') !== false) $cat = 'reminder';
            elseif (strpos($c, 'personal') !== false) $cat = 'personal';
        }

        // Parse DTSTART
        $dtStartParsed = null;
        if (preg_match('/DTSTART((?:;[^:]*)?):([^\r\n]+)/i', $block, $m)) {
            $tz = null;
            if (preg_match('/TZID="?([^;":]+)"?/i', $m[1], $tzm)) {
                $tz = $tzm[1];
            }
            $dtStartParsed = calendar_parse_dt($m[2], $tz);
        }
        if ($dtStartParsed) {
            $date = $dtStartParsed['date'];
            $time = $dtStartParsed['time'];
            $allDay = $dtStartParsed['all_day'];
            $endDate = $date;
        }

        // Parse DTEND or DURATION
        $dtEndParsed = null;
        if (preg_match('/DTEND((?:;[^:]*)?):([^\r\n]+)/i', $block, $m)) {
            $tz = null;
            if (preg_match('/TZID="?([^;":]+)"?/i', $m[1], $tzm)) {
                $tz = $tzm[1];
            }
            $dtEndParsed = calendar_parse_dt($m[2], $tz);
        }

        if ($dtEndParsed) {
            $endDate = $dtEndParsed['date'];
            $endTime = $dtEndParsed['time'];
        } elseif (preg_match('/DURATION(?:;[^:]*)?:P(?:([0-9]+)D)?(?:T(?:([0-9]+)H)?(?:([0-9]+)M)?)?/i', $block, $dm)) {
            $durDays = !empty($dm[1]) ? intval($dm[1]) : 0;
            $durHours = !empty($dm[2]) ? intval($dm[2]) : 0;
            $durMins = !empty($dm[3]) ? intval($dm[3]) : 0;
            if ($durDays > 0) {
                $endDate = date('Y-m-d', strtotime("$date +$durDays days"));
            } else {
                $endDate = $date;
            }
            if ($allDay) {
                $endTime = '10:00';
            } else {
                $startTs = strtotime("$date $time");
                $endTs = $startTs + ($durHours * 3600) + ($durMins * 60);
                $endTime = date('H:i', $endTs);
                $endDate = date('Y-m-d', $endTs);
            }
        } else {
            $endDate = $date;
            if ($allDay) {
                $endTime = '10:00';
            } else {
                $endTime = date('H:i', strtotime("$time +1 hour"));
            }
        }

        // Deterministic ID based on UID or content hash
        if (!empty($uid)) {
            $id = 'ics_' . substr(md5($uid), 0, 16);
        } else {
            $id = 'ics_' . substr(md5($title . $date . $time . $loc), 0, 16);
        }

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
            'organizer'   => $organizer,
            'uid'         => $uid,
            'reminder'    => 0
        );
    }

    return $events;
}

/**
 * Parse an iCalendar (.ics) string and import events into user store
 *
 * @param string $icsContent
 * @param array &$importedEvents Optional reference to receive imported event arrays
 * @return int Number of imported events
 */
function calendar_import_ics($icsContent, &$importedEvents = array())
{
    $parsed = calendar_parse_ics($icsContent);
    if (empty($parsed)) {
        return 0;
    }

    $events = calendar_load_events();
    $imported = 0;
    $importedEvents = array();

    foreach ($parsed as $evId => $ev) {
        $events[$evId] = $ev;
        $importedEvents[$evId] = $ev;
        $imported++;
    }

    if ($imported > 0) {
        calendar_save_events($events);
    }

    return $imported;
}

/**
 * Recursively find all entities in a Message that represent iCalendar (.ics) or VCALENDAR
 */
function calendar_collect_ics_entities($msg, &$results)
{
    if (!is_object($msg)) return;

    $type0 = strtolower($msg->type0 ?? '');
    $type1 = strtolower($msg->type1 ?? '');
    $fn = '';
    if (is_object($msg) && !empty($msg->header) && method_exists($msg, 'getFilename')) {
        try {
            $fn = strtolower($msg->getFilename());
        } catch (\Throwable $t) {
            $fn = '';
        }
    }

    if (($type0 === 'text' && ($type1 === 'calendar' || $type1 === 'x-vcalendar')) ||
        ($type0 === 'application' && ($type1 === 'ics' || $type1 === 'calendar')) ||
        (substr($fn, -4) === '.ics') || (substr($fn, -9) === '.calendar')) {
        $results[] = $msg;
    }

    if (!empty($msg->entities) && is_array($msg->entities)) {
        foreach ($msg->entities as $subEnt) {
            calendar_collect_ics_entities($subEnt, $results);
        }
    }
}

/**
 * Find the entity ID of a VCALENDAR / .ics part if present
 */
function calendar_find_vcal_entity_id($msg)
{
    if (!is_object($msg)) return null;

    $results = array();
    calendar_collect_ics_entities($msg, $results);
    if (!empty($results)) {
        $first = reset($results);
        return !empty($first->entity_id) ? $first->entity_id : '1';
    }

    return null;
}

/**
 * Extract VCALENDAR content from message via IMAP
 */
function calendar_extract_vcalendar_from_message($imapConnection, $passed_id, $mailbox, $message = null, $ent_id = null)
{
    if (empty($imapConnection) || empty($passed_id)) {
        return null;
    }

    if (!is_object($message)) {
        if (function_exists('sqimap_mailbox_select')) {
            sqimap_mailbox_select($imapConnection, $mailbox, false);
        }
        $message = sqimap_get_message($imapConnection, $passed_id, $mailbox, 2);
    }

    if (!is_object($message)) {
        return null;
    }

    // 1. If explicit entity ID was specified
    if ($ent_id !== null && $ent_id !== '') {
        try {
            $entity = $message->getEntity($ent_id);
            $raw = mime_fetch_body($imapConnection, $passed_id, $ent_id);
            $encoding = (is_object($entity) && isset($entity->header) && isset($entity->header->encoding)) ? $entity->header->encoding : '';
            $body = decodeBody($raw, $encoding);
            if (stripos($body, 'BEGIN:VCALENDAR') !== false) {
                if (preg_match('/(BEGIN:VCALENDAR.*?END:VCALENDAR)/is', $body, $m)) {
                    return $m[1];
                }
                return $body;
            }
        } catch (\Throwable $t) {
            // Continue to fallback discovery
        }
    }

    // 2. Check all discovered ICS entities
    $icsEntities = array();
    calendar_collect_ics_entities($message, $icsEntities);
    foreach ($icsEntities as $ent) {
        try {
            $eid = $ent->entity_id;
            $raw = mime_fetch_body($imapConnection, $passed_id, $eid);
            $encoding = (isset($ent->header) && isset($ent->header->encoding)) ? $ent->header->encoding : '';
            $body = decodeBody($raw, $encoding);
            if (stripos($body, 'BEGIN:VCALENDAR') !== false) {
                if (preg_match('/(BEGIN:VCALENDAR.*?END:VCALENDAR)/is', $body, $m)) {
                    return $m[1];
                }
                return $body;
            }
        } catch (\Throwable $t) {
            continue;
        }
    }

    // 3. Check text parts for embedded VCALENDAR
    if (!empty($message->entities)) {
        foreach ($message->entities as $ent) {
            try {
                $eid = $ent->entity_id;
                $raw = mime_fetch_body($imapConnection, $passed_id, $eid);
                $encoding = (isset($ent->header) && isset($ent->header->encoding)) ? $ent->header->encoding : '';
                $body = decodeBody($raw, $encoding);
                if (stripos($body, 'BEGIN:VCALENDAR') !== false) {
                    if (preg_match('/(BEGIN:VCALENDAR.*?END:VCALENDAR)/is', $body, $m)) {
                        return $m[1];
                    }
                    return $body;
                }
            } catch (\Throwable $t) {
                continue;
            }
        }
    }

    // 4. Check main message body
    try {
        $raw = mime_fetch_body($imapConnection, $passed_id, 0);
        if (!empty($raw) && stripos($raw, 'BEGIN:VCALENDAR') !== false) {
            if (preg_match('/(BEGIN:VCALENDAR.*?END:VCALENDAR)/is', $raw, $m)) {
                return $m[1];
            }
            return $raw;
        }
    } catch (\Throwable $t) {
        // Ignored
    }

    return null;
}

/**
 * Request-level cached accessor for message VCALENDAR info
 */
function calendar_get_message_vcal($message = null, $imapConnection = null, $passed_id = null, $mailbox = null)
{
    static $cache = array();

    if ($passed_id === null && isset($GLOBALS['passed_id'])) {
        $passed_id = $GLOBALS['passed_id'];
    }
    if ($mailbox === null && isset($GLOBALS['mailbox'])) {
        $mailbox = $GLOBALS['mailbox'];
    }
    if ($message === null && isset($GLOBALS['message'])) {
        $message = $GLOBALS['message'];
    }
    if ($imapConnection === null && isset($GLOBALS['imapConnection'])) {
        $imapConnection = $GLOBALS['imapConnection'];
    }

    if (empty($passed_id)) {
        return null;
    }

    $cacheKey = $mailbox . ':' . $passed_id;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!is_object($message)) {
        $cache[$cacheKey] = null;
        return null;
    }

    $vcalEntId = calendar_find_vcal_entity_id($message);
    $hasVcal = ($vcalEntId !== null);

    if (!$hasVcal) {
        if (!empty($message->body) && stripos($message->body, 'BEGIN:VCALENDAR') !== false) {
            $hasVcal = true;
            $vcalEntId = 0;
        } elseif (!empty($message->decoded_body) && stripos($message->decoded_body, 'BEGIN:VCALENDAR') !== false) {
            $hasVcal = true;
            $vcalEntId = 0;
        }
    }

    if (!$hasVcal) {
        $cache[$cacheKey] = null;
        return null;
    }

    $vcalContent = null;
    if (!empty($imapConnection)) {
        $vcalContent = calendar_extract_vcalendar_from_message($imapConnection, $passed_id, $mailbox, $message, $vcalEntId);
    }

    $parsedEvents = array();
    if (!empty($vcalContent)) {
        $parsedEvents = calendar_parse_ics($vcalContent);
    }

    $firstEvent = !empty($parsedEvents) ? reset($parsedEvents) : null;

    $res = array(
        'has_vcal'   => true,
        'ent_id'     => $vcalEntId,
        'content'    => $vcalContent,
        'events'     => $parsedEvents,
        'first_event'=> $firstEvent
    );

    $cache[$cacheKey] = $res;
    return $res;
}
