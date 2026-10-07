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
require_once(SM_PATH . 'functions/imap_general.php');
require_once(SM_PATH . 'functions/imap_mailbox.php');
require_once(SM_PATH . 'functions/imap_messages.php');
require_once(SM_PATH . 'functions/mime.php');
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
$uids = array_values(array_filter($uids, function($v) { return $v > 0; }));

if (empty($uids)) {
    if ($isAjax) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'error' => _("No message specified.")));
        exit;
    }
    header('Location: ' . sqm_baseuri() . 'src/right_main.php?mailbox=' . urlencode($mailbox));
    exit;
}

$token = isset($_REQUEST['smtoken']) ? $_REQUEST['smtoken'] : '';
if (!empty($token) && function_exists('sm_validate_security_token')) {
    if (!sm_validate_security_token($token, -1, false)) {
        if ($isAjax) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => false, 'error' => _("Invalid security token.")));
            exit;
        }
    }
}

try {
    global $username, $imapServerAddress, $imapPort, $imap_stream_options;
    $imapConnection = sqimap_login($username, false, $imapServerAddress, $imapPort, 3, $imap_stream_options);

    if (!is_resource($imapConnection) && !is_object($imapConnection)) {
        $err = is_string($imapConnection) ? $imapConnection : _("Failed to connect to IMAP server.");
        if ($isAjax) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => false, 'error' => $err));
            exit;
        }
        header('Location: ' . sqm_baseuri() . 'src/right_main.php?mailbox=' . urlencode($mailbox));
        exit;
    }

    // Find target mailbox
    $boxes = sqimap_mailbox_list($imapConnection);
    $boxNames = array();
    if (is_array($boxes)) {
        foreach ($boxes as $b) {
            if (isset($b['unformatted'])) {
                $boxNames[] = $b['unformatted'];
            }
        }
    }

    $targetBox = 'INBOX';
    if ($type === 'spam') {
        global $trash_folder;
        $junkCandidates = array('Junk', 'Spam', 'INBOX.Junk', 'INBOX.Spam', 'INBOX/Junk', 'INBOX/Spam', 'Junk E-mail', 'INBOX.Junk E-mail');
        $targetBox = '';
        foreach ($junkCandidates as $cand) {
            if (in_array($cand, $boxNames)) {
                $targetBox = $cand;
                break;
            }
        }
        // Case-insensitive match for junk or spam in mailbox names
        if (empty($targetBox)) {
            foreach ($boxNames as $cand) {
                if (preg_match('/(junk|spam)/i', $cand)) {
                    $targetBox = $cand;
                    break;
                }
            }
        }
        // Fallback to trash folder if no junk/spam folder found
        if (empty($targetBox)) {
            $targetBox = !empty($trash_folder) ? $trash_folder : 'Trash';
            if (!in_array($targetBox, $boxNames) && in_array('INBOX.Trash', $boxNames)) {
                $targetBox = 'INBOX.Trash';
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
        $sender = '';
        $subject = '';
        if (function_exists('sqimap_get_small_header')) {
            $hdr = sqimap_get_small_header($imapConnection, $uid, false);
            if ($hdr) {
                $sender = !empty($hdr->from) ? decodeHeader($hdr->from) : '';
                $subject = !empty($hdr->subject) ? decodeHeader($hdr->subject) : '';
            }
        }

        // Train AI model
        if ($type === 'spam') {
            sb_learn_spam($sender, $subject, '');
        } else {
            sb_learn_ham($sender, $subject, '');
        }
        $processed++;
    }

    // 2. Batch move to target mailbox and expunge source
    if ($mailbox !== $targetBox && !empty($uids) && (in_array($targetBox, $boxNames) || $targetBox === 'INBOX')) {
        sqimap_msgs_list_move($imapConnection, $uids, $targetBox, true, $mailbox);
        sqimap_mailbox_expunge($imapConnection, $mailbox, true);
    }

    sqimap_logout($imapConnection);

    if ($isAjax) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
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

    header('Location: ' . sqm_baseuri() . 'src/right_main.php?mailbox=' . urlencode($mailbox));
    exit;

} catch (\Throwable $e) {
    if (isset($imapConnection) && (is_resource($imapConnection) || is_object($imapConnection))) {
        @sqimap_logout($imapConnection);
    }
    if ($isAjax) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
        exit;
    }
    header('Location: ' . sqm_baseuri() . 'src/right_main.php?mailbox=' . urlencode($mailbox));
    exit;
}
