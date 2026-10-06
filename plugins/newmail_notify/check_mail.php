<?php
/**
 * Notify New Mail Popup - Background Checker
 *
 * Lightweight AJAX endpoint returning recent unread messages in INBOX.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage newmail_notify
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/imap_mailbox.php');
include_once(SM_PATH . 'functions/imap_messages.php');

header('Content-Type: application/json; charset=utf-8');

$imapConnection = sqimap_login($username, false, $imapServerAddress, $imapPort, 0);
if (!$imapConnection) {
    echo json_encode(array('success' => false, 'error' => 'IMAP connection failed.'));
    exit;
}

$mailbox = 'INBOX';
$status = sqimap_status_messages($imapConnection, $mailbox);
$unseenCount = isset($status['UNSEEN']) ? intval($status['UNSEEN']) : 0;
$recentCount = isset($status['RECENT']) ? intval($status['RECENT']) : 0;
$uidNext = isset($status['UIDNEXT']) ? intval($status['UIDNEXT']) : 0;

$lastUid = isset($_SESSION['newmail_last_uid']) ? intval($_SESSION['newmail_last_uid']) : 0;
$newMessages = array();

if ($unseenCount > 0) {
    // Search unread messages
    $unseenUids = sqimap_search($imapConnection, 'UNSEEN');
    if (is_array($unseenUids) && !empty($unseenUids)) {
        rsort($unseenUids, SORT_NUMERIC);

        // Check if there are messages newer than lastUid
        $freshUids = array();
        foreach ($unseenUids as $u) {
            if ($lastUid === 0 || $u > $lastUid) {
                $freshUids[] = $u;
            }
        }

        // Limit to latest 3 messages for notifications
        $sampleUids = array_slice($freshUids, 0, 3);
        foreach ($sampleUids as $u) {
            $msgHeader = sqimap_get_small_header($imapConnection, $u, false);
            if ($msgHeader) {
                $fromStr = !empty($msgHeader->from) ? decodeHeader($msgHeader->from) : _("Unknown Sender");
                $subjStr = !empty($msgHeader->subject) ? decodeHeader($msgHeader->subject) : _("(No Subject)");

                $newMessages[] = array(
                    'uid'     => $u,
                    'from'    => $fromStr,
                    'subject' => $subjStr,
                    'date'    => !empty($msgHeader->date) ? date('H:i', $msgHeader->date) : date('H:i')
                );
            }
        }

        // Update session watermark
        if (!empty($unseenUids)) {
            $_SESSION['newmail_last_uid'] = max($unseenUids);
        }
    }
}

sqimap_logout($imapConnection);

echo json_encode(array(
    'success'      => true,
    'unseen_count' => $unseenCount,
    'has_new'      => !empty($newMessages),
    'messages'     => $newMessages
));
exit;
