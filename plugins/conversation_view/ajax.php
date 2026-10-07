<?php
/**
 * ajax.php - Conversation View Plugin AJAX Handler
 *
 * Handles asynchronous requests:
 * - get_body: Fetches and formats the body of a related message/draft for inline preview.
 * - discard_draft: Deletes a draft message and expunges the Drafts mailbox.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage conversation_view
 */

header('Content-Type: application/json; charset=utf-8');

require('../../include/init.php');
include_once(SM_PATH . 'functions/imap_messages.php');
include_once(SM_PATH . 'functions/mime.php');

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';
$token = isset($_REQUEST['smtoken']) ? trim($_REQUEST['smtoken']) : '';

// Validate security token
if (empty($token) || (function_exists('sm_validate_security_token') && !sm_validate_security_token($token, -1, false))) {
    echo json_encode(array('success' => false, 'error' => _("Invalid security token.")));
    exit;
}

// Ensure user is logged in
if (!sqsession_is_registered('user_is_logged_in') || empty($username)) {
    echo json_encode(array('success' => false, 'error' => _("Session expired. Please log in again.")));
    exit;
}

$imapConnection = sqimap_login($username, false, $imapServerAddress, $imapPort, 0);
if (!$imapConnection) {
    echo json_encode(array('success' => false, 'error' => _("Could not connect to IMAP server.")));
    exit;
}

switch ($action) {
    case 'get_body':
        $mailbox = isset($_REQUEST['mailbox']) ? trim($_REQUEST['mailbox']) : '';
        $uid = isset($_REQUEST['uid']) ? (int)$_REQUEST['uid'] : 0;

        if (empty($mailbox) || $uid <= 0) {
            sqimap_logout($imapConnection);
            echo json_encode(array('success' => false, 'error' => _("Invalid message parameters.")));
            exit;
        }

        $mbx_info = sqimap_mailbox_select($imapConnection, $mailbox, false);
        if (!$mbx_info) {
            sqimap_logout($imapConnection);
            echo json_encode(array('success' => false, 'error' => _("Could not open mailbox.")));
            exit;
        }

        $bodyHtml = '';
        try {
            $msgObj = sqimap_get_message($imapConnection, $uid, $mailbox, 2);
            if ($msgObj && is_object($msgObj)) {
                $ent_ar = $msgObj->findDisplayEntity(array());
                if (!empty($ent_ar)) {
                    global $color, $wrap_at;
                    if (empty($wrap_at)) $wrap_at = 80;
                    foreach ($ent_ar as $entity) {
                        $bodyHtml .= formatBody($imapConnection, $msgObj, $color, $wrap_at, $entity, $uid, $mailbox);
                    }
                }
            }
        } catch (\Throwable $e) {
            $bodyHtml = '';
        }

        // Fallback if formatBody or sqimap_get_message yielded empty result
        if (empty($bodyHtml)) {
            $read = sqimap_run_command($imapConnection, "FETCH $uid (BODY.PEEK[TEXT])", true, $resp, $msg, true);
            if (!empty($read) && is_array($read)) {
                $raw = implode('', $read);
                // Clean header line if any
                $raw = preg_replace('/^\* \d+ FETCH \(.*?\r?\n/s', '', $raw);
                $raw = preg_replace('/\)\s*$/', '', $raw);
                $bodyHtml = '<div style="white-space: pre-wrap; font-family: var(--sm-font-mono, monospace); font-size: 13px; line-height: 1.5;">'
                    . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8')
                    . '</div>';
            }
        }

        sqimap_logout($imapConnection);

        if (empty($bodyHtml)) {
            $bodyHtml = '<em>' . _("(Empty message body)") . '</em>';
        }

        echo json_encode(array('success' => true, 'body' => $bodyHtml));
        exit;

    case 'discard_draft':
        $mailbox = isset($_REQUEST['mailbox']) ? trim($_REQUEST['mailbox']) : '';
        $uid = isset($_REQUEST['uid']) ? (int)$_REQUEST['uid'] : 0;

        if (empty($mailbox) || $uid <= 0) {
            sqimap_logout($imapConnection);
            echo json_encode(array('success' => false, 'error' => _("Invalid draft parameters.")));
            exit;
        }

        sqimap_mailbox_select($imapConnection, $mailbox, false);
        $del = sqimap_msgs_list_delete($imapConnection, $mailbox, $uid, true);
        sqimap_mailbox_expunge($imapConnection, $mailbox, true);
        sqimap_logout($imapConnection);

        echo json_encode(array('success' => true, 'message' => _("Draft discarded successfully.")));
        exit;

    default:
        sqimap_logout($imapConnection);
        echo json_encode(array('success' => false, 'error' => _("Unknown action.")));
        exit;
}
