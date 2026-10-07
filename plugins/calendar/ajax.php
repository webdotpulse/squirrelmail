<?php
/**
 * Calendar AJAX & Event Operations Handler
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage calendar
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/imap.php');
include_once(SM_PATH . 'functions/imap_mailbox.php');
include_once(SM_PATH . 'functions/mime.php');
include_once(SM_PATH . 'plugins/calendar/calendar_data.php');

header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action === 'save_event') {
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $date = isset($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
    $endDate = isset($_POST['end_date']) && !empty($_POST['end_date']) ? trim($_POST['end_date']) : $date;
    $time = isset($_POST['time']) ? trim($_POST['time']) : '09:00';
    $endTime = isset($_POST['end_time']) ? trim($_POST['end_time']) : '10:00';
    $allDay = !empty($_POST['all_day']) ? 1 : 0;
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'work';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if (empty($title)) {
        echo json_encode(array('success' => false, 'error' => 'Title is required.'));
        exit;
    }

    $event = array(
        'id'          => $id,
        'title'       => $title,
        'date'        => $date,
        'end_date'    => $endDate,
        'time'        => $time,
        'end_time'    => $endTime,
        'all_day'     => $allDay,
        'category'    => $category,
        'location'    => $location,
        'description' => $description,
        'reminder'    => 0
    );

    $savedId = calendar_save_event($event);
    echo json_encode(array('success' => true, 'id' => $savedId, 'event' => $event));
    exit;
}

if ($action === 'delete_event') {
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    if (empty($id)) {
        echo json_encode(array('success' => false, 'error' => 'Invalid event ID.'));
        exit;
    }
    $res = calendar_delete_event($id);
    echo json_encode(array('success' => $res));
    exit;
}

if ($action === 'get_events') {
    $events = calendar_load_events();
    echo json_encode(array('success' => true, 'events' => array_values($events)));
    exit;
}

if ($action === 'import_email') {
    $pId = isset($_REQUEST['passed_id']) ? $_REQUEST['passed_id'] : '';
    $mBox = isset($_REQUEST['mailbox']) ? $_REQUEST['mailbox'] : 'INBOX';
    $eId = (isset($_REQUEST['ent_id']) && $_REQUEST['ent_id'] !== '') ? $_REQUEST['ent_id'] : null;

    if (empty($pId)) {
        echo json_encode(array('success' => false, 'error' => _("Message ID is required.")));
        exit;
    }

    try {
        global $imapServerAddress, $imapPort, $username, $imap_stream_options;
        $imapConn = sqimap_login($username, false, $imapServerAddress, $imapPort, 2, $imap_stream_options);
        if (!$imapConn) {
            echo json_encode(array('success' => false, 'error' => _("Could not connect to mail server.")));
            exit;
        }

        sqimap_mailbox_select($imapConn, $mBox, false);
        $msgObj = sqimap_get_message($imapConn, $pId, $mBox, 2);

        $vcalContent = calendar_extract_vcalendar_from_message($imapConn, $pId, $mBox, $msgObj, $eId);
        sqimap_logout($imapConn);

        if (empty($vcalContent)) {
            // Fallback: If no raw VCALENDAR was detected, create event from email subject/sender
            $subj = '';
            if (is_object($msgObj) && isset($msgObj->rfc822_header) && isset($msgObj->rfc822_header->subject)) {
                $subj = decodeHeader($msgObj->rfc822_header->subject);
            }
            $sender = '';
            if (is_object($msgObj) && isset($msgObj->rfc822_header)) {
                $sender = $msgObj->rfc822_header->getAddr_s('from');
            }
            $fallbackEvent = array(
                'id'          => 'ev_' . uniqid() . '_' . mt_rand(1000, 9999),
                'title'       => $subj ? $subj : _("Email Appointment"),
                'date'        => date('Y-m-d'),
                'end_date'    => date('Y-m-d'),
                'time'        => '09:00',
                'end_time'    => '10:00',
                'all_day'     => 0,
                'category'    => 'work',
                'location'    => '',
                'description' => ($sender ? "From: $sender\n" : "") . ($subj ? "Subject: $subj\n" : ""),
                'reminder'    => 0
            );
            $savedId = calendar_save_event($fallbackEvent);
            echo json_encode(array(
                'success' => true,
                'count'   => 1,
                'event'   => $fallbackEvent,
                'message' => sprintf(_("Added '%s' on %s"), $fallbackEvent['title'], $fallbackEvent['date'])
            ));
            exit;
        }

        $importedEvents = array();
        $cnt = calendar_import_ics($vcalContent, $importedEvents);

        if ($cnt > 0) {
            $firstEv = reset($importedEvents);
            echo json_encode(array(
                'success' => true,
                'count'   => $cnt,
                'event'   => $firstEv,
                'events'  => array_values($importedEvents),
                'message' => sprintf(_("Added '%s' on %s"), $firstEv['title'], $firstEv['date'])
            ));
            exit;
        } else {
            echo json_encode(array('success' => false, 'error' => _("No valid events could be parsed from the calendar appointment.")));
            exit;
        }
    } catch (\Throwable $e) {
        if (isset($imapConn) && $imapConn) {
            @sqimap_logout($imapConn);
        }
        echo json_encode(array('success' => false, 'error' => _("Error importing appointment: ") . $e->getMessage()));
        exit;
    }
}

echo json_encode(array('success' => false, 'error' => 'Unknown action.'));
exit;
