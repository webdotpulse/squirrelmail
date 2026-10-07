<?php
/**
 * Priority Helper Functions
 *
 * Provides persistent storage and retrieval for user-managed email priorities
 * across INBOX and all subfolders.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package squirrelmail
 * @subpackage functions
 */

if (!function_exists('getHashedFile') && defined('SM_PATH')) {
    include_once(SM_PATH . 'functions/prefs.php');
}

/**
 * Get the storage path for priority overrides
 *
 * @return string File path
 */
function sqm_get_priority_file()
{
    global $username, $data_dir;
    if (empty($username) && function_exists('sqgetGlobalVar')) {
        sqgetGlobalVar('username', $username, SQ_SESSION);
    }
    if (empty($data_dir) && isset($GLOBALS['data_dir'])) {
        $data_dir = $GLOBALS['data_dir'];
    }
    if (function_exists('getHashedFile')) {
        return getHashedFile($username, $data_dir, "$username.priority.json");
    }
    return rtrim($data_dir, '/\\') . '/' . "$username.priority.json";
}

/**
 * Load priority overrides for current user
 *
 * @param bool $refresh Force cache reload
 * @return array Array of key => priority_int
 */
function sqm_load_priority_data($refresh = false)
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $file = sqm_get_priority_file();
    $data = array();

    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    $cache = $data;
    return $data;
}

/**
 * Save priority overrides for current user
 *
 * @param array $data Array of key => priority_int
 * @return bool True on success
 */
function sqm_save_priority_data($data)
{
    $file = sqm_get_priority_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $res = @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    sqm_load_priority_data(true); // reload
    return ($res !== false);
}

/**
 * Compute unique message key from mailbox and UID
 *
 * @param string $mailbox Mailbox name
 * @param int $uid Message UID
 * @return string Unique key
 */
function sqm_get_priority_key($mailbox, $uid)
{
    if (empty($mailbox)) {
        $mailbox = 'INBOX';
    }
    $mb = (strtolower($mailbox) === 'inbox') ? 'INBOX' : $mailbox;
    return rawurlencode($mb) . ':' . intval($uid);
}

/**
 * Get the effective priority of an email, taking user overrides into account
 *
 * @param string $mailbox Mailbox name
 * @param int $uid Message UID
 * @param int $headerPriority Priority from email RFC822 header (default 3)
 * @return int 1 (High), 3 (Normal), or 5 (Low)
 */
function sqm_get_effective_priority($mailbox, $uid, $headerPriority = 3)
{
    $data = sqm_load_priority_data();
    $key = sqm_get_priority_key($mailbox, $uid);
    if (isset($data[$key])) {
        return (int)$data[$key];
    }
    // Also check fallbacks for INBOX or un-prefixed keys
    if (empty($mailbox) || strtolower($mailbox) === 'inbox') {
        if (isset($data['INBOX:' . intval($uid)])) {
            return (int)$data['INBOX:' . intval($uid)];
        }
        if (isset($data['inbox:' . intval($uid)])) {
            return (int)$data['inbox:' . intval($uid)];
        }
        if (isset($data[':' . intval($uid)])) {
            return (int)$data[':' . intval($uid)];
        }
    } else {
        $rawKey = rawurlencode($mailbox) . ':' . intval($uid);
        if (isset($data[$rawKey])) {
            return (int)$data[$rawKey];
        }
        $lowerKey = rawurlencode(strtolower($mailbox)) . ':' . intval($uid);
        if (isset($data[$lowerKey])) {
            return (int)$data[$lowerKey];
        }
    }
    return ($headerPriority) ? (int)$headerPriority : 3;
}

/**
 * Set the priority of a message
 *
 * @param string $mailbox Mailbox name
 * @param int $uid Message UID
 * @param int $priority Priority value (1 = High, 3 = Normal)
 * @param resource $imapConnection Optional active IMAP stream
 * @return int New priority value
 */
function sqm_set_message_priority($mailbox, $uid, $priority, $imapConnection = null)
{
    $data = sqm_load_priority_data();
    $key = sqm_get_priority_key($mailbox, $uid);
    $priority = (int)$priority;
    $data[$key] = $priority;

    // Normalize canonical INBOX key and cleanup legacy
    if (empty($mailbox) || strtolower($mailbox) === 'inbox') {
        unset($data[':' . intval($uid)]);
        unset($data['inbox:' . intval($uid)]);
        $data['INBOX:' . intval($uid)] = $priority;
    }

    sqm_save_priority_data($data);

    if ($imapConnection && function_exists('sqimap_toggle_flag')) {
        $isHigh = ($priority === 1 || $priority === 2);
        @sqimap_toggle_flag($imapConnection, array(intval($uid)), '$HighPriority', $isHigh, false);
    }

    return $priority;
}

/**
 * Toggle the High priority status of a message
 *
 * @param string $mailbox Mailbox name
 * @param int $uid Message UID
 * @param int $headerPriority Original header priority
 * @param resource $imapConnection Optional active IMAP stream
 * @return array Result array
 */
function sqm_toggle_message_priority($mailbox, $uid, $headerPriority = 3, $imapConnection = null)
{
    $current = sqm_get_effective_priority($mailbox, $uid, $headerPriority);
    // If currently High (1 or 2), toggle to Normal (3)
    // If currently Normal (3) or Low (5), toggle to High (1)
    $newPriority = ($current === 1 || $current === 2) ? 3 : 1;
    sqm_set_message_priority($mailbox, $uid, $newPriority, $imapConnection);

    return array(
        'success'  => true,
        'mailbox'  => $mailbox,
        'uid'      => (int)$uid,
        'priority' => $newPriority,
        'is_high'  => ($newPriority === 1)
    );
}

/**
 * Batch set priority on multiple messages in a mailbox
 *
 * @param string $mailbox Mailbox name
 * @param array|string $uids Array or comma-delimited string of UIDs
 * @param int $priority Priority value (1 = High, 3 = Normal)
 * @param resource $imapConnection Optional active IMAP stream
 * @return array Result array
 */
function sqm_batch_set_priority($mailbox, $uids, $priority, $imapConnection = null)
{
    if (!is_array($uids)) {
        $uids = explode(',', $uids);
    }
    $uids = array_filter(array_map('intval', (array)$uids));
    if (empty($uids)) {
        return array('success' => false, 'error' => 'No message UIDs provided');
    }

    $data = sqm_load_priority_data();
    $priority = (int)$priority;
    $isInbox = (empty($mailbox) || strtolower($mailbox) === 'inbox');

    foreach ($uids as $uid) {
        $key = sqm_get_priority_key($mailbox, $uid);
        $data[$key] = $priority;
        if ($isInbox) {
            unset($data[':' . intval($uid)]);
            unset($data['inbox:' . intval($uid)]);
            $data['INBOX:' . intval($uid)] = $priority;
        }
    }
    sqm_save_priority_data($data);

    if ($imapConnection && function_exists('sqimap_toggle_flag')) {
        $isHigh = ($priority === 1 || $priority === 2);
        @sqimap_toggle_flag($imapConnection, $uids, '$HighPriority', $isHigh, false);
    }

    return array(
        'success'  => true,
        'mailbox'  => $mailbox,
        'uids'     => array_values($uids),
        'priority' => $priority,
        'is_high'  => ($priority === 1)
    );
}
