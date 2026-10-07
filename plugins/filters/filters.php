<?php

/**
 * Message Filter Plugin - Filtering Functions
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package plugins
 * @subpackage filters
 */

/**
 * do not allow to call this file directly
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__) {
    header("Location: ../../src/login.php");
    die();
}

/** load globals */
global $UseSeparateImapConnection, $AllowSpamFilters;

/**
 * load required functions. Plugin depends on IMAP functions and they are not
 * loaded in src/webmail.php
 */
include_once (SM_PATH . 'functions/imap.php');

/** load default config */
if (file_exists(SM_PATH . 'plugins/filters/config_default.php')) {
    include_once (SM_PATH . 'plugins/filters/config_default.php');
} else {
    $UseSeparateImapConnection = false;
    $AllowSpamFilters = false;
}

if (file_exists(SM_PATH . 'config/filters_config.php')) {
    include_once (SM_PATH . 'config/filters_config.php');
} elseif (file_exists(SM_PATH . 'plugins/filters/config.php')) {
    include_once (SM_PATH . 'plugins/filters/config.php');
}

if (!function_exists('filters_optpage_register_block')) {
/**
 * Register option blocks
 * @access private
 */
function filters_optpage_register_block() {
    global $optpage_blocks;

    $baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../');

    $optpage_blocks[] = array(
        'name' => _("Message Filters"),
        'url'  => $baseUri . 'plugins/filters/options.php',
        'desc' => _("Automatically sort incoming email into specific folders based on sender, recipient, subject, headers, or body content."),
        'js'   => false
    );
}
}

if (!function_exists('filters_folder_status')) {
/* Receive the status of the folder and do something with it */
function filters_folder_status($statusarr) {
    global $filter_inbox_count;
    if (empty($filter_inbox_count)) $filter_inbox_count = 0;

    if (isset($statusarr['MAILBOX']) && $statusarr['MAILBOX'] == 'INBOX') {
        if (!empty($statusarr['MESSAGES'])) $filter_inbox_count = $statusarr['MESSAGES'];
    }
}
}

/**
 * Starts the filtering process
 * @param array $hook_args (since 1.5.2) hook arguments or options (e.g. array('force' => true))
 * @return int Total number of messages filtered and moved
 * @access public
 */
function start_filters($hook_args = null) {
    global $imapServerAddress, $imapPort, $imap_stream_options, $imap_stream,
           $imapConnection, $UseSeparateImapConnection,
           $filter_inbox_count, $username, $mailbox;

    static $already_filtered = false;

    // Check if forced (e.g. manual run from options page)
    $force = is_array($hook_args) && !empty($hook_args['force']);

    if ($already_filtered && !$force) {
        return 0;
    }

    // If there were filtering errors previously during this session, do not retry unless forced
    sqgetGlobalVar('filters_error', $filters_error, SQ_SESSION, FALSE);
    sqgetGlobalVar('IMAP_FATAL_ERROR_TYPE', $imap_fatal_error, SQ_SESSION, '');
    if (($filters_error || $imap_fatal_error == 'NO') && !$force) {
        return 0;
    }

    if ($force) {
        sqsession_unregister('filters_error');
    }

    $filters = load_filters();

    // No user filters - nothing to do
    if (empty($filters)) {
        $already_filtered = true;
        return 0;
    }

    // Detect if we have already connected to IMAP or not
    $previously_connected = true;
    if ((!isset($imap_stream) && !isset($imapConnection)) || $UseSeparateImapConnection) {
        $stream = sqimap_login($username, false, $imapServerAddress,
                               $imapPort, 10, $imap_stream_options);
        $previously_connected = false;
    } else if (isset($imapConnection)) {
        $stream = $imapConnection;
    } else {
        $stream = $imap_stream;
    }

    if (!$stream) {
        return 0;
    }

    $total_moved = 0;

    $aStatus = sqimap_status_messages($stream, 'INBOX', array('MESSAGES'));
    $filter_inbox_count = !empty($aStatus['MESSAGES']) ? (int)$aStatus['MESSAGES'] : 0;

    if ($filter_inbox_count > 0) {
        sqimap_mailbox_select($stream, 'INBOX');

        // Sort into folders based on user criteria
        $total_moved = user_filters($stream);
    }

    // If stream was already open and user is on a folder other than INBOX, restore selection
    if ($previously_connected && !empty($mailbox) && $mailbox !== 'INBOX') {
        sqimap_mailbox_select($stream, $mailbox);
    }

    if (!$previously_connected) {
        sqimap_logout($stream);
    }

    $already_filtered = true;
    return $total_moved;
}

/**
 * Does the loop through each filter rule
 * @param resource $imap_stream the stream to read from
 * @return int Number of messages moved
 * @access private
 */
function user_filters($imap_stream) {
    global $data_dir, $username;
    $filters = load_filters();
    if (empty($filters)) return 0;
    $filters_user_scan = getPref($data_dir, $username, 'filters_user_scan');

    $expunge = false;
    $total_moved = 0;

    for ($i = 0, $num = count($filters); $i < $num; $i++) {
        $where = isset($filters[$i]['where']) ? $filters[$i]['where'] : '';
        $what = isset($filters[$i]['what']) ? $filters[$i]['what'] : '';
        $folder = isset($filters[$i]['folder']) ? $filters[$i]['folder'] : '';

        if ($what === '' || $folder === '') {
            continue;
        }

        if ($where == 'To or Cc') {
            $m1 = filter_search_and_delete($imap_stream, 'TO', $what, $folder, $filters_user_scan);
            $m2 = filter_search_and_delete($imap_stream, 'CC', $what, $folder, $filters_user_scan);
            $moved = $m1 + $m2;
        } else if ($where == 'Header and Body') {
            $moved = filter_search_and_delete($imap_stream, 'TEXT', $what, $folder, $filters_user_scan);
        } else if ($where == 'Message Body') {
            $moved = filter_search_and_delete($imap_stream, 'BODY', $what, $folder, $filters_user_scan);
        } else {
            $moved = filter_search_and_delete($imap_stream, $where, $what, $folder, $filters_user_scan);
        }

        if ($moved > 0) {
            $total_moved += $moved;
            $expunge = true;
        }
    }

    // Clean out the mailbox if any messages were moved
    if ($expunge) {
        sqimap_mailbox_expunge($imap_stream, 'INBOX');
    }

    return $total_moved;
}

/**
 * Creates and runs the IMAP command to filter messages and move them to destination folder
 * @param resource $imap_stream IMAP socket connection
 * @param string $where Which part of the message to search (TO, CC, SUBJECT, HEADER, etc.)
 * @param string $what String to search for
 * @param string $where_to Folder it will move to
 * @param string $user_scan Whether to search all or just unseen
 * @return int Number of messages moved
 * @access private
 */
function filter_search_and_delete($imap_stream, $where, $what, $where_to, $user_scan = '') {
    global $languages, $squirrelmail_language, $allow_charset_search, $color;

    if (strtolower($where_to) == 'inbox') {
        return 0;
    }

    if ($user_scan == 'new') {
        $category = 'UNSEEN UNDELETED';
    } else {
        $category = 'ALL UNDELETED';
    }

    // Build the search criterion
    if ($where == 'Header') {
        $parts = explode(':', $what, 2);
        $hdr_name = trim($parts[0]);
        $hdr_val = isset($parts[1]) ? trim($parts[1]) : '';
        $criterion = 'HEADER "' . quoteimap($hdr_name) . '" "' . quoteimap($hdr_val) . '"';
    } else {
        $field = strtoupper($where);
        $criterion = $field . ' "' . quoteimap($what) . '"';
    }

    $charset = '';
    if (!empty($allow_charset_search) &&
        isset($languages[$squirrelmail_language]['CHARSET']) &&
        $languages[$squirrelmail_language]['CHARSET']) {
        $charset = strtoupper($languages[$squirrelmail_language]['CHARSET']);
    }

    if (!empty($charset) && $charset !== 'US-ASCII') {
        $query = 'SEARCH CHARSET "' . $charset . '" ' . $category . ' ' . $criterion;
    } else {
        $query = 'SEARCH CHARSET US-ASCII ' . $category . ' ' . $criterion;
    }

    $response = '';
    $message = '';
    $readin = sqimap_run_command_list($imap_stream, $query, false, $response, $message, TRUE);

    // Fallback if IMAP server rejected CHARSET
    if ($response == 'NO' && !empty($charset)) {
        $query = 'SEARCH CHARSET US-ASCII ' . $category . ' ' . $criterion;
        $readin = sqimap_run_command_list($imap_stream, $query, false, $response, $message, TRUE);
        if ($response == 'NO') {
            $query = 'SEARCH ' . $category . ' ' . $criterion;
            $readin = sqimap_run_command_list($imap_stream, $query, false, $response, $message, TRUE);
        }
    }

    $ids = array();
    if (!empty($readin) && is_array($readin)) {
        foreach ($readin as $line) {
            if (is_array($line)) {
                foreach ($line as $subline) {
                    if (preg_match("/^\*\s+SEARCH\s+(.*)$/i", $subline, $regs)) {
                        $parts = explode(' ', trim($regs[1]));
                        foreach ($parts as $p) {
                            $p = trim($p);
                            if ($p !== '' && ctype_digit($p)) {
                                $ids[] = $p;
                            }
                        }
                    }
                }
            } elseif (is_string($line) && preg_match("/^\*\s+SEARCH\s+(.*)$/i", $line, $regs)) {
                $parts = explode(' ', trim($regs[1]));
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p !== '' && ctype_digit($p)) {
                        $ids[] = $p;
                    }
                }
            }
        }
    }
    $ids = array_unique($ids);

    if ($response == 'OK' && count($ids) > 0) {
        if (sqimap_mailbox_exists($imap_stream, $where_to)) {
            if (!sqimap_msgs_list_move($imap_stream, $ids, $where_to, false)) {
                sqsession_register(TRUE, 'filters_error');
                if (function_exists('error_box')) {
                    error_box(_("A problem occurred filtering messages. Check filter settings and account quota."), $color);
                }
                return 0;
            }
            return count($ids);
        }
    }

    return 0;
}

/**
 * Loads the filters from the user preferences
 * @return array All the user filters
 * @access private
 */
function load_filters() {
    global $data_dir, $username;

    $filters = array();
    for ($i = 0; $fltr = getPref($data_dir, $username, 'filter' . $i); $i++) {
        $ary = explode(',', $fltr);
        $filters[$i]['where'] = $ary[0];
        $filters[$i]['what'] = str_replace('###COMMA###', ',', $ary[1]);
        $filters[$i]['folder'] = $ary[2];
    }
    return $filters;
}

/**
 * Removes a User filter
 * @param int $id ID of the filter to remove
 * @access private
 */
function remove_filter ($id) {
    global $data_dir, $username;

    while ($nextFilter = getPref($data_dir, $username, 'filter' . ($id + 1))) {
        setPref($data_dir, $username, 'filter' . $id, $nextFilter);
        $id ++;
    }

    removePref($data_dir, $username, 'filter' . $id);
}

/**
 * Swaps two filters
 * @param int $id1 ID of first filter to swap
 * @param int $id2 ID of second filter to swap
 * @access private
 */
function filter_swap($id1, $id2) {
    global $data_dir, $username;

    $FirstFilter = getPref($data_dir, $username, 'filter' . $id1);
    $SecondFilter = getPref($data_dir, $username, 'filter' . $id2);

    if ($FirstFilter && $SecondFilter) {
        setPref($data_dir, $username, 'filter' . $id2, $FirstFilter);
        setPref($data_dir, $username, 'filter' . $id1, $SecondFilter);
    }
}

/**
 * This updates the filter rules when renaming or deleting folders
 * @param array $args
 * @access private
 */
function update_for_folder ($args) {
    $old_folder = $args[0];
    $new_folder = $args[2];
    $action = $args[1];
    global $data_dir, $username;
    $filters = array();
    $filters = load_filters();
    $filter_count = count($filters);
    $p = 0;
    for ($i = 0; $i < $filter_count; $i++) {
        if (!empty($filters)) {
            if ($old_folder == $filters[$i]['folder']) {
                if ($action == 'rename') {
                    $filters[$i]['folder'] = $new_folder;
                    setPref($data_dir, $username, 'filter'.$i,
                    $filters[$i]['where'].','.$filters[$i]['what'].','.$new_folder);
                }
                elseif ($action == 'delete') {
                    remove_filter($p);
                    $p = $p-1;
                }
            }
        $p++;
        }
    }
}

/**
 * Display formatted error message
 * @param string $string text message
 * @return string html formatted text message
 * @access private
 */
function do_error($string) {
    global $color;
    echo "<p align=\"center\"><font color=\"$color[2]\">";
    echo $string;
    echo "</font></p>\n";
}

/**
 * Obsolete spam filtering shims for backwards compatibility.
 * Replaced by modern AI Spam & Reputation Dashboard (plugins/spam_buttons & plugins/ai_agent).
 */
function spam_filters($imap_stream = null) {
    return 0;
}

function load_spam_filters() {
    return array();
}
