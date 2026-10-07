<?php
/**
 * Message Flags & Labels - AJAX Handler
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage message_labels
 */

require('../../include/init.php');
include_once(SM_PATH . 'plugins/message_labels/labels.php');

header('Content-Type: application/json; charset=utf-8');

$action  = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$mailbox = isset($_REQUEST['mailbox']) ? trim($_REQUEST['mailbox']) : 'INBOX';
$uid     = isset($_REQUEST['uid']) ? intval($_REQUEST['uid']) : 0;

if ($action === 'toggle_label') {
    $labelId = isset($_POST['label_id']) ? trim($_POST['label_id']) : '';
    if (empty($uid) || empty($labelId)) {
        echo json_encode(array('success' => false, 'error' => 'Missing UID or label ID.'));
        exit;
    }
    $state = ml_toggle_message_label($mailbox, $uid, $labelId);
    $activeLabels = ml_get_message_labels($mailbox, $uid);
    echo json_encode(array('success' => true, 'active' => $state, 'labels' => $activeLabels, 'counts' => ml_get_label_counts()));
    exit;
}

if ($action === 'batch_toggle_label') {
    $labelId = isset($_POST['label_id']) ? trim($_POST['label_id']) : '';
    $uids = isset($_POST['uids']) ? $_POST['uids'] : array();
    if (is_string($uids)) {
        $uids = explode(',', $uids);
    }
    $uids = array_filter(array_map('intval', (array)$uids));
    if (empty($uids) || empty($labelId)) {
        echo json_encode(array('success' => false, 'error' => 'Missing message UIDs or label ID.'));
        exit;
    }
    $state = ml_batch_toggle_label($mailbox, $uids, $labelId);
    echo json_encode(array('success' => true, 'active' => $state, 'uids' => $uids, 'counts' => ml_get_label_counts()));
    exit;
}

if ($action === 'toggle_star') {
    if (empty($uid)) {
        echo json_encode(array('success' => false, 'error' => 'Missing UID.'));
        exit;
    }
    $state = ml_toggle_message_star($mailbox, $uid);
    echo json_encode(array('success' => true, 'starred' => $state));
    exit;
}

if ($action === 'add_custom_label') {
    $name  = isset($_POST['name']) ? trim($_POST['name']) : '';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '#1a73e8';
    if (empty($name)) {
        echo json_encode(array('success' => false, 'error' => 'Label name cannot be empty.'));
        exit;
    }
    $id = ml_save_label('', $name, $color);
    echo json_encode(array('success' => true, 'id' => $id, 'name' => $name, 'color' => $color));
    exit;
}

if ($action === 'get_labels') {
    $data = ml_load_data();
    $active = ml_get_message_labels($mailbox, $uid);
    $starred = ml_is_message_starred($mailbox, $uid);
    echo json_encode(array(
        'success' => true,
        'all_labels' => $data['labels'],
        'active_labels' => $active,
        'starred' => $starred
    ));
    exit;
}

echo json_encode(array('success' => false, 'error' => 'Invalid action.'));
exit;
