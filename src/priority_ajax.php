<?php
/**
 * Priority AJAX Handler
 *
 * Provides asynchronous toggling and batch updating of message priority
 * across INBOX and all subfolders.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package squirrelmail
 */

define('PAGE_NAME', 'priority_ajax');
require('../include/init.php');
require_once(SM_PATH . 'functions/priority.php');

header('Content-Type: application/json; charset=utf-8');

$action   = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$mailbox  = isset($_REQUEST['mailbox']) ? trim($_REQUEST['mailbox']) : 'INBOX';
$uid      = isset($_REQUEST['uid']) ? intval($_REQUEST['uid']) : 0;
$priority = isset($_REQUEST['priority']) ? intval($_REQUEST['priority']) : 1;

if ($action === 'toggle') {
    if (empty($uid)) {
        echo json_encode(array('success' => false, 'error' => 'Missing message UID.'));
        exit;
    }
    $headerPrio = isset($_REQUEST['header_priority']) ? intval($_REQUEST['header_priority']) : 3;
    $res = sqm_toggle_message_priority($mailbox, $uid, $headerPrio);
    echo json_encode($res);
    exit;
}

if ($action === 'batch_set') {
    $uids = isset($_REQUEST['uids']) ? $_REQUEST['uids'] : array();
    $res = sqm_batch_set_priority($mailbox, $uids, $priority);
    echo json_encode($res);
    exit;
}

if ($action === 'get') {
    if (empty($uid)) {
        echo json_encode(array('success' => false, 'error' => 'Missing message UID.'));
        exit;
    }
    $prio = sqm_get_effective_priority($mailbox, $uid);
    echo json_encode(array(
        'success'  => true,
        'mailbox'  => $mailbox,
        'uid'      => $uid,
        'priority' => $prio,
        'is_high'  => ($prio === 1 || $prio === 2)
    ));
    exit;
}

echo json_encode(array('success' => false, 'error' => 'Invalid action.'));
exit;
