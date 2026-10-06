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

echo json_encode(array('success' => false, 'error' => 'Unknown action.'));
exit;
