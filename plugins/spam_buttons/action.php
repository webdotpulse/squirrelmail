<?php
/**
 * Spam Buttons - Move & Train Action Handler
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage spam_buttons
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/imap_mailbox.php');
include_once(SM_PATH . 'functions/imap_messages.php');
include_once(SM_PATH . 'plugins/spam_buttons/spam_learn.php');

$isAjax = (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1);
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'spam'; // 'spam' or 'ham'
$mailbox = isset($_REQUEST['mailbox']) ? trim($_REQUEST['mailbox']) : 'INBOX';
$passed_id = isset($_REQUEST['passed_id']) ? $_REQUEST['passed_id'] : '';

$uids = array();
if (is_array($passed_id)) {
    $uids = array_map('intval', $passed_id);
} elseif (!empty($passed_id)) {
    $uids = array(intval($passed_id));
}

if (empty($uids)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'error' => 'No message specified.'));
        exit;
    }
    header('Location: ' . SM_PATH . 'src/right_main.php?mailbox=' . urlencode($mailbox));
    exit;
}

$imapConnection = sqimap_login($username, false, $imapServerAddress, $imapPort, 0);

// Find target mailbox
$boxes = sqimap_mailbox_list($imapConnection);
$boxNames = array();
foreach ($boxes as $b) {
    $boxNames[] = $b['unformatted'];
}

$targetBox = 'INBOX';
if ($type === 'spam') {
    // Look for Junk or Spam folder
    $junkCandidates = array('Junk', 'Spam', 'INBOX.Junk', 'INBOX.Spam', 'Trash');
    $targetBox = 'Trash'; // fallback
    foreach ($junkCandidates as $cand) {
        if (in_array($cand, $boxNames)) {
            $targetBox = $cand;
            break;
        }
    }
} else {
    // Ham moves back to INBOX
    $targetBox = 'INBOX';
}

$processed = 0;
sqimap_mailbox_select($imapConnection, $mailbox);

foreach ($uids as $uid) {
    // 1. Extract message details for AI learning
    $hdr = sqimap_get_small_header($imapConnection, $uid, false);
    $sender = '';
    $subject = '';
    if ($hdr) {
        $sender = !empty($hdr->from) ? decodeHeader($hdr->from) : '';
        $subject = !empty($hdr->subject) ? decodeHeader($hdr->subject) : '';
    }

    // Train AI model
    if ($type === 'spam') {
        sb_learn_spam($sender, $subject, '');
    } else {
        sb_learn_ham($sender, $subject, '');
    }

    // 2. Move message to target mailbox
    if ($mailbox !== $targetBox) {
        sqimap_msgs_list_move($imapConnection, array($uid), $targetBox);
    }
    $processed++;
}

sqimap_logout($imapConnection);

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(array(
        'success'   => true,
        'type'      => $type,
        'count'     => $processed,
        'target'    => $targetBox,
        'message'   => ($type === 'spam')
            ? sprintf(_("%d email(s) moved to %s. AI model trained!"), $processed, $targetBox)
            : sprintf(_("%d email(s) restored to INBOX. Sender whitelisted in AI model!"), $processed)
    ));
    exit;
}

header('Location: ' . SM_PATH . 'src/right_main.php?mailbox=' . urlencode($mailbox));
exit;
