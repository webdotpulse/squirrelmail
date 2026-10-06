<?php
/**
 * Multi-Account Plugin - AJAX Endpoint Handlers
 *
 * Provides asynchronous connection testing, unread count polling, and flag updating.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage multi_account
 */

header('Content-Type: application/json; charset=utf-8');

require('../../include/init.php');
require_once(SM_PATH . 'functions/imap.php');
require_once(SM_PATH . 'functions/imap_mailbox.php');
require_once(SM_PATH . 'functions/imap_messages.php');
if (!function_exists('imap_utf7_decode_local') && file_exists(SM_PATH . 'functions/imap_utf7_local.php')) {
    require_once(SM_PATH . 'functions/imap_utf7_local.php');
}
require_once(SM_PATH . 'plugins/multi_account/account_manager.php');

global $data_dir, $username;

if (empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$mgr = new MultiAccountManager($data_dir, $username);
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_unread_counts':
        try {
            $data = $mgr->getUnreadCounts(false);
            echo json_encode($data);
        } catch (Throwable $e) {
            echo json_encode(['total_unread' => 0, 'accounts' => [], 'error' => $e->getMessage()]);
        }
        break;

    case 'test_connection':
        $host = trim($_POST['host'] ?? '');
        $port = intval($_POST['port'] ?? 993);
        $tls  = intval($_POST['tls'] ?? 1);
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['password'] ?? '';

        // If password is blank and account_id is provided, try existing account's decrypted password
        if (empty($pass) && !empty($_POST['account_id'])) {
            $accounts = $mgr->getAccounts();
            foreach ($accounts as $a) {
                if ($a['id'] === $_POST['account_id']) {
                    $pass = $mgr->decrypt($a['password']);
                    break;
                }
            }
        }

        if (empty($host) || empty($user) || empty($pass)) {
            echo json_encode(['success' => false, 'error' => 'Host, Username, and Password are required.']);
            exit;
        }

        $res = $mgr->testConnection($host, $port, $tls, $user, $pass);
        echo json_encode($res);
        break;

    case 'toggle_read':
        $accountId = trim($_POST['account_id'] ?? '');
        $uid = intval($_POST['uid'] ?? 0);
        $seen = isset($_POST['seen']) ? (bool)$_POST['seen'] : true;

        if (empty($accountId) || $uid <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
            exit;
        }

        $ok = $mgr->markSeen($accountId, $uid, $seen);
        echo json_encode(['success' => $ok]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
