<?php
/**
 * Message Flags & Labels - Core Engine
 *
 * Provides Gmail-like colored labels, multi-label tagging, star flags, and filtering.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage message_labels
 */

if (!function_exists('getHashedFile') && defined('SM_PATH')) {
    include_once(SM_PATH . 'functions/prefs.php');
}

/**
 * Get labels storage file path
 */
function ml_get_storage_file()
{
    global $username, $data_dir;
    return getHashedFile($username, $data_dir, "$username.labels.json");
}

/**
 * Get default label definitions
 */
function ml_get_default_labels()
{
    return array(
        'work' => array(
            'id'    => 'work',
            'name'  => 'Work',
            'color' => '#1a73e8', // Blue
            'bg'    => '#e8f0fe'
        ),
        'personal' => array(
            'id'    => 'personal',
            'name'  => 'Personal',
            'color' => '#1e8e3e', // Green
            'bg'    => '#e6f4ea'
        ),
        'finance' => array(
            'id'    => 'finance',
            'name'  => 'Finance',
            'color' => '#0d9488', // Teal
            'bg'    => '#ccfbf1'
        ),
        'important' => array(
            'id'    => 'important',
            'name'  => 'Important',
            'color' => '#d93025', // Red
            'bg'    => '#fce8e6'
        ),
        'followup' => array(
            'id'    => 'followup',
            'name'  => 'Follow-up',
            'color' => '#ea8600', // Orange
            'bg'    => '#fef7e0'
        ),
        'todo' => array(
            'id'    => 'todo',
            'name'  => 'To Do',
            'color' => '#8e24aa', // Purple
            'bg'    => '#f3e8fd'
        )
    );
}

/**
 * Load labels and message mappings for current user
 */
function ml_load_data()
{
    $file = ml_get_storage_file();
    $data = array(
        'labels'   => ml_get_default_labels(),
        'messages' => array(), // message_key => array of label_ids
        'starred'  => array()  // message_key => timestamp
    );

    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                if (isset($decoded['labels']) && is_array($decoded['labels'])) {
                    $data['labels'] = array_merge(ml_get_default_labels(), $decoded['labels']);
                }
                if (isset($decoded['messages']) && is_array($decoded['messages'])) {
                    $data['messages'] = $decoded['messages'];
                }
                if (isset($decoded['starred']) && is_array($decoded['starred'])) {
                    $data['starred'] = $decoded['starred'];
                }
            }
        }
    }
    return $data;
}

/**
 * Save data to disk
 */
function ml_save_data($data)
{
    $file = ml_get_storage_file();
    return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Compute unique message key from mailbox and UID
 */
function ml_get_message_key($mailbox, $uid)
{
    return rawurlencode($mailbox) . ':' . intval($uid);
}

/**
 * Get labels assigned to a specific message
 */
function ml_get_message_labels($mailbox, $uid)
{
    $data = ml_load_data();
    $key = ml_get_message_key($mailbox, $uid);
    $labelIds = isset($data['messages'][$key]) ? $data['messages'][$key] : array();

    $result = array();
    foreach ($labelIds as $lid) {
        if (isset($data['labels'][$lid])) {
            $result[$lid] = $data['labels'][$lid];
        }
    }
    return $result;
}

/**
 * Check if message is starred
 */
function ml_is_message_starred($mailbox, $uid)
{
    $data = ml_load_data();
    $key = ml_get_message_key($mailbox, $uid);
    return !empty($data['starred'][$key]);
}

/**
 * Toggle label for message
 */
function ml_toggle_message_label($mailbox, $uid, $labelId)
{
    $data = ml_load_data();
    $key = ml_get_message_key($mailbox, $uid);

    if (!isset($data['messages'][$key])) {
        $data['messages'][$key] = array();
    }

    $idx = array_search($labelId, $data['messages'][$key]);
    if ($idx !== false) {
        unset($data['messages'][$key][$idx]);
        $data['messages'][$key] = array_values($data['messages'][$key]);
        $state = false;
    } else {
        $data['messages'][$key][] = $labelId;
        $state = true;
    }

    ml_save_data($data);
    return $state;
}

/**
 * Toggle star for message
 */
function ml_toggle_message_star($mailbox, $uid)
{
    $data = ml_load_data();
    $key = ml_get_message_key($mailbox, $uid);

    if (isset($data['starred'][$key])) {
        unset($data['starred'][$key]);
        $state = false;
    } else {
        $data['starred'][$key] = time();
        $state = true;
    }

    ml_save_data($data);
    return $state;
}

/**
 * Create or update custom label
 */
function ml_save_label($id, $name, $color, $bg = '')
{
    $data = ml_load_data();
    $id = preg_replace('/[^a-z0-9_-]/', '', strtolower($id));
    if (empty($id)) {
        $id = 'lbl_' . substr(md5($name), 0, 8);
    }

    if (empty($bg)) {
        $bg = $color . '20'; // 12% alpha
    }

    $data['labels'][$id] = array(
        'id'    => $id,
        'name'  => $name,
        'color' => $color,
        'bg'    => $bg
    );

    ml_save_data($data);
    return $id;
}

/**
 * Delete a custom label
 */
function ml_delete_label($id)
{
    $data = ml_load_data();
    if (isset($data['labels'][$id])) {
        unset($data['labels'][$id]);
        foreach ($data['messages'] as $k => $lids) {
            $idx = array_search($id, $lids);
            if ($idx !== false) {
                unset($data['messages'][$k][$idx]);
                $data['messages'][$k] = array_values($data['messages'][$k]);
            }
        }
        ml_save_data($data);
        return true;
    }
    return false;
}

/**
 * Count messages per label
 */
function ml_get_label_counts()
{
    $data = ml_load_data();
    $counts = array();
    foreach ($data['labels'] as $lid => $lDef) {
        $counts[$lid] = 0;
    }
    foreach ($data['messages'] as $k => $lids) {
        foreach ($lids as $lid) {
            if (isset($counts[$lid])) {
                $counts[$lid]++;
            }
        }
    }
    return $counts;
}

/**
 * Batch toggle or set label for multiple message UIDs
 *
 * @param string $mailbox
 * @param array $uids
 * @param string $labelId
 * @return bool New active state (true = added, false = removed)
 */
function ml_batch_toggle_label($mailbox, $uids, $labelId)
{
    $data = ml_load_data();
    if (!is_array($uids)) $uids = array($uids);

    if ($labelId === '__clear__') {
        foreach ($uids as $uid) {
            $key = ml_get_message_key($mailbox, $uid);
            if (isset($data['messages'][$key])) {
                unset($data['messages'][$key]);
            }
        }
        ml_save_data($data);
        return false;
    }

    $hasCount = 0;
    foreach ($uids as $uid) {
        $key = ml_get_message_key($mailbox, $uid);
        if (isset($data['messages'][$key]) && in_array($labelId, $data['messages'][$key])) {
            $hasCount++;
        }
    }
    $add = ($hasCount < count($uids));

    foreach ($uids as $uid) {
        $key = ml_get_message_key($mailbox, $uid);
        if (!isset($data['messages'][$key])) {
            $data['messages'][$key] = array();
        }
        $idx = array_search($labelId, $data['messages'][$key]);
        if ($add) {
            if ($idx === false) {
                $data['messages'][$key][] = $labelId;
            }
        } else {
            if ($idx !== false) {
                unset($data['messages'][$key][$idx]);
                $data['messages'][$key] = array_values($data['messages'][$key]);
            }
        }
    }

    ml_save_data($data);
    return $add;
}

/**
 * Get all message UIDs for a mailbox that have a specific label
 *
 * @param string $mailbox
 * @param string $labelId
 * @return array Array of integer UIDs
 */
function ml_get_labeled_uids($mailbox, $labelId)
{
    $data = ml_load_data();
    if (empty($data['messages']) || empty($labelId)) {
        return array();
    }

    $targetPrefix1 = rawurlencode($mailbox) . ':';
    $targetPrefix2 = $mailbox . ':';
    $len1 = strlen($targetPrefix1);
    $len2 = strlen($targetPrefix2);

    $uids = array();
    foreach ($data['messages'] as $key => $labels) {
        if (!is_array($labels) || !in_array($labelId, $labels)) {
            continue;
        }

        $uidStr = null;
        if (strpos($key, $targetPrefix1) === 0) {
            $uidStr = substr($key, $len1);
        } else if (strpos($key, $targetPrefix2) === 0) {
            $uidStr = substr($key, $len2);
        }

        if ($uidStr !== null && is_numeric($uidStr)) {
            $uids[] = intval($uidStr);
        }
    }

    return $uids;
}
