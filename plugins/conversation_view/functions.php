<?php
/**
 * functions.php - Conversation View Core Functions
 *
 * Implements cross-folder discovery of related conversation messages
 * (incoming messages, sent replies, and pending drafts), parses headers,
 * extracts clean body previews, and renders the responsive conversation thread.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage conversation_view
 */

if (!defined('SM_PATH')) {
    define('SM_PATH', '../../');
}

include_once(SM_PATH . 'functions/imap_messages.php');
include_once(SM_PATH . 'functions/mime.php');
include_once(SM_PATH . 'functions/date.php');

/**
 * Extract RFC-822 Message-IDs from header strings (e.g. References, In-Reply-To, Message-ID)
 *
 * @param string $string Raw header string
 * @return array Clean array of Message-ID strings (without angle brackets)
 */
function cv_extract_ids($string)
{
    if (empty($string)) {
        return array();
    }
    preg_match_all('/<([^>]+)>/', $string, $matches);
    if (!empty($matches[1])) {
        $ids = array();
        foreach ($matches[1] as $id) {
            $clean = trim($id);
            if (!empty($clean)) {
                $ids[] = $clean;
            }
        }
        return array_unique($ids);
    }
    $trimmed = trim($string, "<> \t\n\r");
    if (!empty($trimmed) && strpos($trimmed, '@') !== false) {
        return array($trimmed);
    }
    return array();
}

/**
 * Clean and normalize a subject line by stripping Re:/Fwd: prefixes
 *
 * @param string $subject Original subject line
 * @return string Normalized base subject
 */
function cv_clean_subject($subject)
{
    if (empty($subject)) {
        return '';
    }
    $clean = decodeHeader($subject, true, false);
    $prev = '';
    while ($clean !== $prev) {
        $prev = $clean;
        $clean = preg_replace('/^\s*(?:\[[^\]]+\]|(?:re|fwd|fw|aw|antw|rif|sv)(?:\[\d+\])?)\s*[:\-]?\s*/iu', '', $clean);
    }
    return trim($clean);
}

/**
 * Extract a clean, unquoted body snippet for a message
 *
 * @param string $rawText Raw text body of the message
 * @param int $maxLength Maximum character length
 * @return string Clean preview snippet
 */
function cv_clean_body_snippet($rawText, $maxLength = 160)
{
    if (empty($rawText)) {
        return '';
    }
    // Strip HTML tags if HTML
    $text = strip_tags($rawText);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Split lines and discard quoted lines (lines starting with '>')
    $lines = explode("\n", $text);
    $clean_lines = array();
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) {
            continue;
        }
        if (strpos($trimmed, '>') === 0) {
            continue; // Skip quoted reply
        }
        if (preg_match('/^(?:On .+ wrote:|Am .+ schrieb:|Le .+ a écrit:)/i', $trimmed)) {
            continue;
        }
        $clean_lines[] = $trimmed;
    }

    $snippet = implode(' ', $clean_lines);
    $snippet = preg_replace('/\s+/', ' ', $snippet);
    if (mb_strlen($snippet, 'UTF-8') > $maxLength) {
        $snippet = mb_substr($snippet, 0, $maxLength - 3, 'UTF-8') . '...';
    }
    return trim($snippet);
}

/**
 * Parse an email address string into display name and email address
 *
 * @param string $addrStr RFC 822 address string
 * @return array Array with 'name' and 'email'
 */
function cv_format_address($addrStr)
{
    if (empty($addrStr)) {
        return array('name' => _("Unknown"), 'email' => '');
    }
    $addrStr = decodeHeader(trim($addrStr), false, false, true);
    if (preg_match('/^(.*?)\s*<([^>]+)>$/', $addrStr, $m)) {
        $name = trim($m[1], " \t\n\r\0\x0B\"'");
        $email = trim($m[2]);
        if (empty($name)) {
            $name = $email;
        }
        return array('name' => $name, 'email' => $email);
    }
    return array('name' => $addrStr, 'email' => $addrStr);
}

/**
 * Run a quiet UID search on the IMAP connection without displaying error boxes
 *
 * @param resource $imapConnection IMAP stream
 * @param string $searchString Search expression (e.g. HEADER References "...")
 * @return array Array of matching message UIDs
 */
function cv_run_uid_search($imapConnection, $searchString)
{
    if (!$imapConnection || empty($searchString)) {
        return array();
    }
    // Check if query contains 8-bit characters
    if (preg_match('/[\x80-\xFF]/', $searchString)) {
        $query = 'SEARCH CHARSET UTF-8 ' . $searchString;
        $response = '';
        $message = '';
        $readin = sqimap_run_command_list($imapConnection, $query, false, $response, $message, true);
        if (strtoupper((string)$response) === 'OK' && !empty($readin) && is_array($readin)) {
            return parseUidList($readin, 'SEARCH');
        }
        // Fallback: strip 8-bit characters
        $searchString = preg_replace('/[\x80-\xFF]/', '', $searchString);
        if (trim($searchString) === '') {
            return array();
        }
    }

    $query = 'SEARCH ' . $searchString;
    $response = '';
    $message = '';
    $readin = sqimap_run_command_list($imapConnection, $query, false, $response, $message, true);
    if (strtoupper((string)$response) !== 'OK' || empty($readin) || !is_array($readin)) {
        return array();
    }
    return parseUidList($readin, 'SEARCH');
}

/**
 * Flattens mailbox list or mailbox object tree into a flat array of mailbox full names.
 *
 * @param mixed $boxes Array or object tree of mailboxes
 * @return array Flat array of mailbox names
 */
function cv_flatten_mailboxes($boxes)
{
    $list = array();
    if (empty($boxes)) {
        return $list;
    }
    if (is_object($boxes)) {
        if (!empty($boxes->mailboxname_full)) {
            $list[] = $boxes->mailboxname_full;
        }
        if (!empty($boxes->mbxs) && is_array($boxes->mbxs)) {
            foreach ($boxes->mbxs as $child) {
                $list = array_merge($list, cv_flatten_mailboxes($child));
            }
        }
    } elseif (is_array($boxes)) {
        foreach ($boxes as $b) {
            if (is_object($b)) {
                $list = array_merge($list, cv_flatten_mailboxes($b));
            } elseif (is_array($b)) {
                if (!empty($b['unformatted'])) $list[] = $b['unformatted'];
                if (!empty($b['unformatted-dm'])) $list[] = $b['unformatted-dm'];
                if (!empty($b['mailboxname_full'])) $list[] = $b['mailboxname_full'];
            } elseif (is_string($b)) {
                $list[] = $b;
            }
        }
    }
    return array_values(array_unique(array_filter($list)));
}

/**
 * Resolves all actual IMAP mailbox names matching a special folder type ('sent' or 'draft').
 * Tests candidate names against IMAP directly to ensure valid, accessible folders are returned.
 *
 * @param resource $imapConnection IMAP stream
 * @param string $type 'sent' or 'draft'
 * @return array Array of existing mailbox names
 */
function cv_resolve_all_special_folders($imapConnection, $type)
{
    global $sent_folder, $draft_folder, $data_dir, $username;

    $prefFolder = ($type === 'draft')
        ? getPref($data_dir, $username, 'draft_folder')
        : getPref($data_dir, $username, 'sent_folder');

    if (empty($prefFolder)) {
        $prefFolder = ($type === 'draft') ? $draft_folder : $sent_folder;
    }

    $candidates = ($type === 'draft')
        ? array($prefFolder, 'Drafts', 'INBOX.Drafts', 'Draft', 'INBOX/Drafts')
        : array($prefFolder, 'Sent', 'INBOX.Sent', 'Sent Items', 'Sent Messages', 'INBOX/Sent', 'INBOX.Sent Items');

    $candidates = array_values(array_unique(array_filter($candidates)));
    $existing = array();

    // 1. Check cached folder list if available
    $boxes = sqimap_mailbox_list($imapConnection);
    $available = cv_flatten_mailboxes($boxes);

    if (!empty($available)) {
        foreach ($candidates as $cand) {
            foreach ($available as $avail) {
                if (strcasecmp($cand, $avail) === 0) {
                    $existing[] = $avail;
                }
            }
        }

        $targetWord = ($type === 'draft') ? 'draft' : 'sent';
        foreach ($available as $avail) {
            $parts = preg_split('/[\.\/]/', $avail);
            $last = strtolower(end($parts));
            if ($last === $targetWord || strpos($last, $targetWord) === 0) {
                $existing[] = $avail;
            }
        }
    }

    // 2. Direct IMAP test for candidates not found in cached list
    foreach ($candidates as $cand) {
        if (in_array($cand, $existing, true)) {
            continue;
        }
        // Test if mailbox exists via LIST without passing $boxes
        if (sqimap_mailbox_exists($imapConnection, $cand)) {
            $existing[] = $cand;
        }
    }

    $existing = array_values(array_unique($existing));
    if (empty($existing)) {
        $existing[] = ($type === 'draft' ? 'Drafts' : 'Sent');
    }
    return $existing;
}

/**
 * Resolves the primary actual IMAP mailbox name for special folders ('sent' or 'draft').
 *
 * @param resource $imapConnection IMAP stream
 * @param string $type 'sent' or 'draft'
 * @return string The resolved primary folder name
 */
function cv_resolve_special_folder($imapConnection, $type)
{
    $all = cv_resolve_all_special_folders($imapConnection, $type);
    return !empty($all[0]) ? $all[0] : ($type === 'draft' ? 'Drafts' : 'Sent');
}

/**
 * Return summary statistics for the current conversation thread
 *
 * @param resource $imapConnection Active IMAP socket stream
 * @param string $currentMailbox Currently selected mailbox name
 * @param int $currentUid UID of the currently viewed message
 * @param object $currentMessage Message object
 * @return array Array containing total_count, sent_count, draft_count, received_count
 */
function cv_get_thread_summary($imapConnection, $currentMailbox, $currentUid, $currentMessage)
{
    try {
        $threadData = cv_get_conversation_thread($imapConnection, $currentMailbox, $currentUid, $currentMessage);
        return !empty($threadData['stats']) ? $threadData['stats'] : array(
            'total_count'    => 0,
            'sent_count'     => 0,
            'draft_count'    => 0,
            'received_count' => 0,
        );
    } catch (\Throwable $e) {
        error_log('conversation_view cv_get_thread_summary error: ' . $e->getMessage());
        return array(
            'total_count'    => 0,
            'sent_count'     => 0,
            'draft_count'    => 0,
            'received_count' => 0,
        );
    }
}

/**
 * Core engine: Discovers and aggregates conversation messages across mailboxes
 *
 * @param resource $imapConnection Active IMAP stream
 * @param string $currentMailbox Mailbox name of the viewed message
 * @param int $currentUid UID of the viewed message
 * @param object $currentMessage SquirrelMail Message object
 * @return array Structured conversation data
 */
function cv_get_conversation_thread($imapConnection, $currentMailbox, $currentUid, $currentMessage)
{
    static $requestCache = array();
    $cacheKey = $currentMailbox . '_' . $currentUid;
    if (isset($requestCache[$cacheKey])) {
        return $requestCache[$cacheKey];
    }

    $defaultResult = array(
        'messages' => array(),
        'stats'    => array(
            'total_count'    => 0,
            'sent_count'     => 0,
            'draft_count'    => 0,
            'received_count' => 0,
        ),
        'subject'  => '',
    );

    if (!$imapConnection) {
        return $defaultResult;
    }

    try {
        global $sent_folder, $draft_folder, $data_dir, $username;

        $user_sent = getPref($data_dir, $username, 'sent_folder');
        if (empty($user_sent)) {
            $user_sent = $sent_folder;
        }

        $user_draft = getPref($data_dir, $username, 'draft_folder');
        if (empty($user_draft)) {
            $user_draft = $draft_folder;
        }

        $search_sent = (bool) getPref($data_dir, $username, 'cv_search_sent', 1);
        $search_drafts = (bool) getPref($data_dir, $username, 'cv_search_drafts', 1);
        $search_current = (bool) getPref($data_dir, $username, 'cv_search_current', 1);

        // 1. Gather Message-IDs and normalized Subject from current message
        $rfcHeader = isset($currentMessage->rfc822_header) ? $currentMessage->rfc822_header : null;
        if (!$rfcHeader && isset($currentMessage->header)) {
            $rfcHeader = $currentMessage->header;
        }
        $curr_id = ($rfcHeader && !empty($rfcHeader->message_id)) ? trim($rfcHeader->message_id) : '';
        $in_reply_to = ($rfcHeader && !empty($rfcHeader->in_reply_to)) ? trim($rfcHeader->in_reply_to) : '';
        $references = ($rfcHeader && !empty($rfcHeader->references)) ? trim($rfcHeader->references) : '';
        $raw_subject = ($rfcHeader && !empty($rfcHeader->subject)) ? trim($rfcHeader->subject) : '';
        $flag_reply = ($rfcHeader && !empty($rfcHeader->more_headers['X-SM-Flag-Reply']))
            ? trim($rfcHeader->more_headers['X-SM-Flag-Reply'])
            : ((isset($rfcHeader->x_sm_flag_reply)) ? trim($rfcHeader->x_sm_flag_reply) : '');

        // Resilient fallback: fetch headers directly via IMAP if current message header lacks IDs
        if ((empty($curr_id) || empty($raw_subject)) && !empty($currentUid) && !empty($currentMailbox)) {
            $hdr_cur = sqimap_get_small_header_list($imapConnection, array($currentUid), array('Subject', 'Message-ID', 'In-Reply-To', 'References', 'X-SM-Flag-Reply'));
            if (!empty($hdr_cur) && is_array($hdr_cur)) {
                $c_hdr = reset($hdr_cur);
                if (empty($curr_id) && !empty($c_hdr['message-id'])) $curr_id = trim($c_hdr['message-id']);
                if (empty($in_reply_to) && !empty($c_hdr['in-reply-to'])) $in_reply_to = trim($c_hdr['in-reply-to']);
                if (empty($references) && !empty($c_hdr['references'])) $references = trim($c_hdr['references']);
                if (empty($raw_subject) && !empty($c_hdr['subject'])) $raw_subject = trim($c_hdr['subject']);
                if (empty($flag_reply) && !empty($c_hdr['x-sm-flag-reply'])) $flag_reply = trim($c_hdr['x-sm-flag-reply']);
            }
        }

        $search_ids = array();
        if (!empty($curr_id)) {
            $search_ids = array_merge($search_ids, cv_extract_ids($curr_id));
        }
        if (!empty($in_reply_to)) {
            $search_ids = array_merge($search_ids, cv_extract_ids($in_reply_to));
        }
        if (!empty($references)) {
            $search_ids = array_merge($search_ids, cv_extract_ids($references));
        }
        $search_ids = array_unique(array_filter($search_ids));
        $clean_subject = cv_clean_subject($raw_subject);

        // Check if current message is a draft or reply pointing to an original message UID
        $origReplyUid = 0;
        $origReplyBox = '';
        if (!empty($flag_reply)) {
            $fparts = explode('::', $flag_reply, 3);
            if (!empty($fparts[1]) && is_numeric($fparts[1])) {
                $origReplyUid = (int)$fparts[1];
            }
            if (!empty($fparts[2])) {
                $origReplyBox = trim($fparts[2]);
            }
        }

        // 2. Determine target folders to inspect with resilient candidate discovery
        $folders_to_check = array();
        if ($search_current && !empty($currentMailbox)) {
            $cType = 'received';
            if (isDraftMailbox($currentMailbox)) {
                $cType = 'draft';
            } elseif (isSentMailbox($currentMailbox)) {
                $cType = 'sent';
            }
            $folders_to_check[$currentMailbox] = array('type' => $cType);
        }

        if ($search_sent) {
            $sentFolders = cv_resolve_all_special_folders($imapConnection, 'sent');
            foreach ($sentFolders as $sf) {
                if (!isset($folders_to_check[$sf])) {
                    $folders_to_check[$sf] = array('type' => 'sent');
                }
            }
        }

        if ($search_drafts) {
            $draftFolders = cv_resolve_all_special_folders($imapConnection, 'draft');
            foreach ($draftFolders as $df) {
                if (!isset($folders_to_check[$df])) {
                    $folders_to_check[$df] = array('type' => 'draft');
                }
            }
        }

        // Always check INBOX for original incoming emails
        if (!isset($folders_to_check['INBOX'])) {
            $folders_to_check['INBOX'] = array('type' => 'received');
        }

        // If the reply flag mentions another folder, include that too
        if (!empty($origReplyBox) && !isset($folders_to_check[$origReplyBox])) {
            $folders_to_check[$origReplyBox] = array('type' => 'received');
        }

        $baseUri = sqm_baseuri();
        $acctParam = isset($GLOBALS['iAccount']) ? (int)$GLOBALS['iAccount'] : 0;
        $curType = 'received';
        if (isDraftMailbox($currentMailbox)) {
            $curType = 'draft';
        } elseif (isSentMailbox($currentMailbox)) {
            $curType = 'sent';
        }

        $curFromParsed = ($rfcHeader && !empty($rfcHeader->from)) ? cv_format_address($rfcHeader->from) : array('name' => _("Unknown"), 'email' => '');
        $curToParsed = ($rfcHeader && !empty($rfcHeader->to)) ? cv_format_address($rfcHeader->to) : array('name' => _("Unknown"), 'email' => '');
        $curSubj = !empty($raw_subject) ? decodeHeader($raw_subject, false, false, true) : _("(no subject)");

        $curRawDate = ($rfcHeader && !empty($rfcHeader->date)) ? $rfcHeader->date : '';
        $curTimestamp = !empty($curRawDate) ? strtotime($curRawDate) : 0;
        if ($curTimestamp <= 0) {
            $curTimestamp = time();
        }
        $curDateStr = function_exists('getDateString') ? getDateString($curTimestamp, true) : date('Y-m-d H:i', $curTimestamp);

        $curSnippet = !empty($GLOBALS['messagebody']) ? cv_clean_body_snippet($GLOBALS['messagebody']) : '';

        $curHasAttachment = false;
        if (isset($currentMessage->entities) && count($currentMessage->entities) > 1) {
            $curHasAttachment = true;
        }

        $curViewUrl = $baseUri . 'src/read_body.php?account=' . $acctParam . '&mailbox=' . urlencode($currentMailbox) . '&passed_id=' . (int)$currentUid . '&startMessage=1';
        $curResumeUrl = ($curType === 'draft') ? $curViewUrl : '';
        $curReplyUrl = $baseUri . 'src/compose.php?smaction_reply=1&passed_id=' . (int)$currentUid . '&mailbox=' . urlencode($currentMailbox);

        $currentKey = $currentMailbox . '_' . (int)$currentUid;
        $all_messages = array(
            $currentKey => array(
                'uid'            => (int)$currentUid,
                'mailbox'        => $currentMailbox,
                'type'           => $curType,
                'is_current'     => true,
                'from_name'      => $curFromParsed['name'],
                'from_email'     => $curFromParsed['email'],
                'to_name'        => $curToParsed['name'],
                'to_email'       => $curToParsed['email'],
                'subject'        => $curSubj,
                'date_str'       => $curDateStr,
                'timestamp'      => $curTimestamp,
                'snippet'        => $curSnippet,
                'has_attachment' => $curHasAttachment,
                'view_url'       => $curViewUrl,
                'resume_url'     => $curResumeUrl,
                'reply_url'      => $curReplyUrl,
            )
        );

        // 3. Search and extract related messages from each candidate folder
        foreach ($folders_to_check as $folderName => $fMeta) {
            $defaultType = $fMeta['type'];

            // Ensure this folder is selected on the IMAP stream
            $cur_sel = sqimap_mailbox_select($imapConnection, $folderName, false);
            if (empty($cur_sel) || !isset($cur_sel['EXISTS']) || $cur_sel['EXISTS'] == 0) {
                continue;
            }

            $found_uids = array();

            // If we know the original UID in this folder from reply flag, add it directly!
            if (!empty($origReplyUid) && ($folderName === $origReplyBox || (empty($origReplyBox) && strcasecmp($folderName, 'INBOX') === 0))) {
                $found_uids[] = (int)$origReplyUid;
            }

            // 3a. Search by Message-IDs (References, In-Reply-To, Message-ID)
            if (!empty($search_ids)) {
                $top_ids = array_slice($search_ids, 0, 5);
                foreach ($top_ids as $sid) {
                    $clean_sid = trim($sid, "<> \t\n\r");
                    $clean_sid = trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/', '', $clean_sid));
                    if (strlen($clean_sid) < 3) {
                        continue;
                    }

                    $escSid = addcslashes($clean_sid, '"\\');
                    foreach (array('References', 'In-Reply-To', 'Message-ID') as $field) {
                        $res = cv_run_uid_search($imapConnection, 'HEADER ' . $field . ' "' . $escSid . '"');
                        if (!empty($res) && is_array($res)) {
                            foreach ($res as $u) {
                                if (is_numeric($u)) $found_uids[] = (int)$u;
                            }
                        }
                    }
                }
            }

            // 3b. Search by SquirrelMail reply flag in drafts
            if ($defaultType === 'draft' && !empty($currentUid)) {
                $resFlag = cv_run_uid_search($imapConnection, 'HEADER X-SM-Flag-Reply "::' . addcslashes($currentUid, '"\\') . '"');
                if (!empty($resFlag) && is_array($resFlag)) {
                    foreach ($resFlag as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            // 3c. Search by clean subject
            if (mb_strlen($clean_subject, 'UTF-8') >= 3) {
                $safe_subj = trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/', '', $clean_subject));
                if (strlen($safe_subj) >= 3) {
                    $resSubj = cv_run_uid_search($imapConnection, 'SUBJECT "' . addcslashes($safe_subj, '"\\') . '"');
                    if (!empty($resSubj) && is_array($resSubj)) {
                        foreach ($resSubj as $u) {
                            if (is_numeric($u)) $found_uids[] = (int)$u;
                        }
                    }
                }
            }

            // 3d. In Drafts, inspect all recent drafts (up to 50) to guarantee custom flag detection
            if ($defaultType === 'draft') {
                $resAllDrafts = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($resAllDrafts) && is_array($resAllDrafts)) {
                    if (count($resAllDrafts) > 50) {
                        $resAllDrafts = array_slice($resAllDrafts, -50);
                    }
                    foreach ($resAllDrafts as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            // 3e. In Sent, if zero messages found yet by search, inspect recent sent messages (up to 30)
            if ($defaultType === 'sent' && empty($found_uids)) {
                $resAllSent = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($resAllSent) && is_array($resAllSent)) {
                    if (count($resAllSent) > 30) {
                        $resAllSent = array_slice($resAllSent, -30);
                    }
                    foreach ($resAllSent as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            $found_uids = array_values(array_unique(array_filter($found_uids)));
            if (empty($found_uids)) {
                continue;
            }

            // Limit to most recent 40 messages per folder
            if (count($found_uids) > 40) {
                $found_uids = array_slice($found_uids, -40);
            }

            // Fetch headers including X-SM-Flag-Reply
            $hdr_list = sqimap_get_small_header_list(
                $imapConnection,
                $found_uids,
                array('Date', 'To', 'Cc', 'From', 'Subject', 'Message-ID', 'In-Reply-To', 'References', 'Content-Type', 'X-SM-Flag-Reply'),
                array('FLAGS', 'RFC822.SIZE', 'INTERNALDATE')
            );

            if (empty($hdr_list) || !is_array($hdr_list)) {
                continue;
            }

            foreach ($hdr_list as $uid => $hdr) {
                $uid = (int) $uid;
                $msgKey = $folderName . '_' . $uid;

                // Skip if this is the currently viewed message already registered
                if (strcasecmp($folderName, $currentMailbox) === 0 && $uid === (int)$currentUid) {
                    continue;
                }
                if (isset($all_messages[$msgKey])) {
                    continue;
                }

                // Determine message type
                $type = $defaultType;
                if ($defaultType === 'draft' || stripos($folderName, 'draft') !== false) {
                    $type = 'draft';
                } elseif ($defaultType === 'sent' || stripos($folderName, 'sent') !== false) {
                    $type = 'sent';
                }

                // Verify relevance
                $has_link = false;

                // Check SquirrelMail reply flag (e.g. reply::243::INBOX)
                if (!empty($hdr['x-sm-flag-reply'])) {
                    $flagVal = trim($hdr['x-sm-flag-reply']);
                    $parts = explode('::', $flagVal, 3);
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        $flagOrigUid = (int)$parts[1];
                        if ($flagOrigUid === (int)$currentUid || (!empty($origReplyUid) && $flagOrigUid === (int)$origReplyUid)) {
                            $has_link = true;
                        }
                    }
                    if (!$has_link && strpos($flagVal, '::' . (int)$currentUid) !== false) {
                        $has_link = true;
                    }
                }

                // Check Message-IDs (References, In-Reply-To, Message-ID)
                if (!$has_link && !empty($search_ids)) {
                    $hdr_in_reply = !empty($hdr['in-reply-to']) ? cv_extract_ids($hdr['in-reply-to']) : array();
                    $hdr_refs = !empty($hdr['references']) ? cv_extract_ids($hdr['references']) : array();
                    $hdr_mid = !empty($hdr['message-id']) ? cv_extract_ids($hdr['message-id']) : array();
                    $all_hdr_ids = array_merge($hdr_in_reply, $hdr_refs, $hdr_mid);
                    if (!empty(array_intersect($search_ids, $all_hdr_ids))) {
                        $has_link = true;
                    }
                }

                // Check Original UID pointer
                if (!$has_link && !empty($origReplyUid) && $uid === (int)$origReplyUid) {
                    $has_link = true;
                }

                // Check Subject match
                if (!$has_link && !empty($clean_subject)) {
                    $hdr_clean_subj = cv_clean_subject(!empty($hdr['subject']) ? $hdr['subject'] : '');
                    if (mb_strlen($hdr_clean_subj, 'UTF-8') >= 3 && 
                        mb_strtolower($hdr_clean_subj, 'UTF-8') === mb_strtolower($clean_subject, 'UTF-8')) {
                        $has_link = true;
                    }
                }

                if (!$has_link) {
                    continue;
                }

                // Extract date and timestamp
                $rawDate = !empty($hdr['date']) ? $hdr['date'] : (!empty($hdr['INTERNALDATE']) ? $hdr['INTERNALDATE'] : '');
                $timestamp = !empty($rawDate) ? strtotime($rawDate) : 0;
                if ($timestamp <= 0) {
                    $timestamp = time();
                }
                $dateStr = function_exists('getDateString') ? getDateString($timestamp, true) : date('Y-m-d H:i', $timestamp);

                // Extract Addresses
                $fromStr = !empty($hdr['from']) ? $hdr['from'] : '';
                $toStr = !empty($hdr['to']) ? $hdr['to'] : '';
                $fromParsed = cv_format_address($fromStr);
                $toParsed = cv_format_address($toStr);

                // Extract Subject
                $subj = !empty($hdr['subject']) ? decodeHeader($hdr['subject'], false, false, true) : _("(no subject)");

                // Fetch clean body snippet
                $snippet = '';
                $body_read = sqimap_run_command($imapConnection, "FETCH $uid (BODY.PEEK[TEXT]<0.400>)", false, $resp, $msg, true);
                if (empty($body_read) || !is_array($body_read)) {
                    $body_read = sqimap_run_command($imapConnection, "FETCH $uid (BODY.PEEK[1]<0.400>)", false, $resp, $msg, true);
                }
                if (!empty($body_read) && is_array($body_read)) {
                    $rawBody = implode('', $body_read);
                    $snippet = cv_clean_body_snippet($rawBody);
                }

                // Attachment indicator (safely handling array or string Content-Type)
                $hasAttachment = false;
                if (!empty($hdr['content-type'])) {
                    if (is_array($hdr['content-type'])) {
                        $cTypeStr = implode('/', $hdr['content-type']);
                    } else {
                        $cTypeStr = (string)$hdr['content-type'];
                    }
                    if (stripos($cTypeStr, 'multipart/mixed') !== false || stripos($cTypeStr, 'multipart/related') !== false) {
                        $hasAttachment = true;
                    }
                }

                // URLs
                $viewUrl = $baseUri . 'src/read_body.php?account=' . $acctParam . '&mailbox=' . urlencode($folderName) . '&passed_id=' . $uid . '&startMessage=1';
                $resumeUrl = ($type === 'draft')
                    ? $baseUri . 'src/read_body.php?account=' . $acctParam . '&mailbox=' . urlencode($folderName) . '&passed_id=' . $uid . '&startMessage=1'
                    : '';
                $replyUrl = $baseUri . 'src/compose.php?smaction_reply=1&passed_id=' . $uid . '&mailbox=' . urlencode($folderName);

                $all_messages[$msgKey] = array(
                    'uid'            => $uid,
                    'mailbox'        => $folderName,
                    'type'           => $type,
                    'is_current'     => false,
                    'from_name'      => $fromParsed['name'],
                    'from_email'     => $fromParsed['email'],
                    'to_name'        => $toParsed['name'],
                    'to_email'       => $toParsed['email'],
                    'subject'        => $subj,
                    'date_str'       => $dateStr,
                    'timestamp'      => $timestamp,
                    'snippet'        => $snippet,
                    'has_attachment' => $hasAttachment,
                    'view_url'       => $viewUrl,
                    'resume_url'     => $resumeUrl,
                    'reply_url'      => $replyUrl,
                );
            }
        }

        // Restore original mailbox selection
        if (!empty($currentMailbox)) {
            @sqimap_mailbox_select($imapConnection, $currentMailbox, false);
        }

        // Convert associative map to indexed list
        $msgList = array_values($all_messages);

        // Sort chronologically (oldest to newest)
        usort($msgList, function ($a, $b) {
            if ($a['timestamp'] === $b['timestamp']) {
                return ($a['uid'] <=> $b['uid']);
            }
            return ($a['timestamp'] <=> $b['timestamp']);
        });

        // Compute stats
        $stats = array(
            'total_count'    => count($msgList),
            'sent_count'     => 0,
            'draft_count'    => 0,
            'received_count' => 0,
        );

        foreach ($msgList as $m) {
            if ($m['type'] === 'sent') {
                $stats['sent_count']++;
            } elseif ($m['type'] === 'draft') {
                $stats['draft_count']++;
            } else {
                $stats['received_count']++;
            }
        }

        $result = array(
            'messages' => $msgList,
            'stats'    => $stats,
            'subject'  => $clean_subject,
        );

        $requestCache[$cacheKey] = $result;
        return $result;
    } catch (\Throwable $e) {
        error_log('cv_get_conversation_thread error: ' . $e->getMessage());
        return $defaultResult;
    } finally {
        if (!empty($currentMailbox) && $imapConnection) {
            @sqimap_mailbox_select($imapConnection, $currentMailbox, false);
        }
    }
}

/**
 * Render the interactive Conversation Thread timeline HTML card
 *
 * @param resource $imapConnection Active IMAP socket stream
 * @param string $currentMailbox Mailbox name of the viewed message
 * @param int $currentUid UID of the viewed message
 * @param object $currentMessage Message object
 * @param string $placement 'bottom' or 'top'
 */
function cv_render_thread_view($imapConnection, $currentMailbox, $currentUid, $currentMessage, $placement = 'bottom')
{
    try {
        $threadData = cv_get_conversation_thread($imapConnection, $currentMailbox, $currentUid, $currentMessage);
        $messages = !empty($threadData['messages']) ? $threadData['messages'] : array();
        $stats = !empty($threadData['stats']) ? $threadData['stats'] : array(
            'total_count'    => 0,
            'sent_count'     => 0,
            'draft_count'    => 0,
            'received_count' => 0,
        );
        $cleanSubject = !empty($threadData['subject']) ? $threadData['subject'] : '';

        $baseUri = sqm_baseuri();
        $cssUrl = $baseUri . 'plugins/conversation_view/conversation.css';
        $jsUrl = $baseUri . 'plugins/conversation_view/conversation.js';
        $ajaxUrl = $baseUri . 'plugins/conversation_view/ajax.php';
        $token = function_exists('sm_generate_security_token') ? sm_generate_security_token() : '';

        $replyAllUrl = $baseUri . 'src/compose.php?smaction_reply_all=1&passed_id=' . $currentUid . '&mailbox=' . urlencode($currentMailbox);
        $replyUrl = $baseUri . 'src/compose.php?smaction_reply=1&passed_id=' . $currentUid . '&mailbox=' . urlencode($currentMailbox);

    ?>
    <!-- Conversation View Plugin CSS & JS -->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <script type="text/javascript" src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>

    <div id="cv-conversation-thread" class="cv-thread-container" data-ajax-url="<?php echo htmlspecialchars($ajaxUrl, ENT_QUOTES, 'UTF-8'); ?>" data-token="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <!-- Header -->
        <div class="cv-thread-header">
            <div class="cv-header-left">
                <span class="cv-thread-icon">💬</span>
                <span class="cv-thread-title"><?php echo _("Conversation Thread"); ?></span>
                <span class="cv-badge cv-badge-total"><?php echo count($messages); ?> <?php echo count($messages) === 1 ? _("message") : _("messages"); ?></span>
                
                <?php if ($stats['sent_count'] > 0): ?>
                    <span class="cv-badge cv-badge-sent">📤 <?php echo $stats['sent_count']; ?> <?php echo $stats['sent_count'] === 1 ? _("sent reply") : _("sent replies"); ?></span>
                <?php endif; ?>

                <?php if ($stats['draft_count'] > 0): ?>
                    <span class="cv-badge cv-badge-draft">📝 <?php echo $stats['draft_count']; ?> <?php echo $stats['draft_count'] === 1 ? _("pending draft") : _("pending drafts"); ?></span>
                <?php endif; ?>
            </div>

            <div class="cv-header-actions">
                <a href="<?php echo htmlspecialchars($replyUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cv-btn cv-btn-primary">
                    <span>↩️</span> <?php echo _("Reply"); ?>
                </a>
                <a href="<?php echo htmlspecialchars($replyAllUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cv-btn cv-btn-secondary">
                    <span>👥</span> <?php echo _("Reply All"); ?>
                </a>
            </div>
        </div>

        <?php if (count($messages) <= 1 && empty($stats['sent_count']) && empty($stats['draft_count'])): ?>
            <!-- Single message notice -->
            <div class="cv-thread-empty">
                <span class="cv-empty-icon">ℹ️</span>
                <span><?php echo _("No sent replies or pending drafts found for this message thread yet."); ?></span>
            </div>
        <?php else: ?>
            <!-- Timeline List -->
            <div class="cv-timeline">
                <?php foreach ($messages as $idx => $m):
                    $cardClass = 'cv-card cv-card-' . $m['type'];
                    if ($m['is_current']) {
                        $cardClass .= ' cv-card-current';
                    }
                    $cardId = 'cv-card-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $m['mailbox']) . '-' . $m['uid'];
                ?>
                <div id="<?php echo $cardId; ?>" class="<?php echo $cardClass; ?>" data-mailbox="<?php echo htmlspecialchars($m['mailbox'], ENT_QUOTES, 'UTF-8'); ?>" data-uid="<?php echo $m['uid']; ?>">
                    <div class="cv-card-timeline-indicator">
                        <div class="cv-indicator-dot"></div>
                        <?php if ($idx < count($messages) - 1): ?>
                            <div class="cv-indicator-line"></div>
                        <?php endif; ?>
                    </div>

                    <div class="cv-card-inner">
                        <!-- Card Header Summary -->
                        <div class="cv-card-header" onclick="cvToggleCard('<?php echo $cardId; ?>')">
                            <div class="cv-card-type-pill">
                                <?php if ($m['type'] === 'sent'): ?>
                                    <span class="cv-pill-tag cv-pill-sent">📤 <?php echo _("Sent Reply"); ?></span>
                                <?php elseif ($m['type'] === 'draft'): ?>
                                    <span class="cv-pill-tag cv-pill-draft">📝 <?php echo _("Draft Reply"); ?></span>
                                <?php else: ?>
                                    <span class="cv-pill-tag cv-pill-received">📥 <?php echo htmlspecialchars($m['mailbox'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>

                                <?php if ($m['is_current']): ?>
                                    <span class="cv-pill-tag cv-pill-current">● <?php echo _("Currently Viewing"); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="cv-card-meta">
                                <span class="cv-card-author">
                                    <?php if ($m['type'] === 'sent' || $m['type'] === 'draft'): ?>
                                        <span class="cv-meta-label"><?php echo _("To:"); ?></span> <strong><?php echo htmlspecialchars($m['to_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php else: ?>
                                        <span class="cv-meta-label"><?php echo _("From:"); ?></span> <strong><?php echo htmlspecialchars($m['from_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php endif; ?>
                                </span>
                                <span class="cv-card-date"><?php echo htmlspecialchars($m['date_str'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="cv-card-expand-icon">▼</span>
                            </div>
                        </div>

                        <!-- Card Preview Snippet -->
                        <?php if (!empty($m['snippet'])): ?>
                            <div class="cv-card-snippet">
                                "<?php echo htmlspecialchars($m['snippet'], ENT_QUOTES, 'UTF-8'); ?>"
                            </div>
                        <?php endif; ?>

                        <!-- Full Message Body (Expandable) -->
                        <div class="cv-card-body" style="display: none;">
                            <div class="cv-body-content">
                                <div class="cv-loading-spinner"><?php echo _("Loading message body..."); ?></div>
                            </div>
                        </div>

                        <!-- Card Actions Bar -->
                        <div class="cv-card-footer">
                            <div class="cv-footer-actions">
                                <?php if ($m['type'] === 'draft'): ?>
                                    <a href="<?php echo htmlspecialchars($m['resume_url'], ENT_QUOTES, 'UTF-8'); ?>" class="cv-btn cv-btn-primary cv-btn-sm">
                                        <span>✏️</span> <?php echo _("Resume Draft"); ?>
                                    </a>
                                    <button type="button" class="cv-btn cv-btn-danger cv-btn-sm" onclick="cvDiscardDraft(event, '<?php echo htmlspecialchars($m['mailbox'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $m['uid']; ?>, '<?php echo $cardId; ?>')">
                                        <span>🗑️</span> <?php echo _("Discard Draft"); ?>
                                    </button>
                                <?php elseif (!$m['is_current']): ?>
                                    <a href="<?php echo htmlspecialchars($m['view_url'], ENT_QUOTES, 'UTF-8'); ?>" class="cv-btn cv-btn-outline cv-btn-sm">
                                        <span>👁️</span> <?php echo _("View Message"); ?>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($m['reply_url'], ENT_QUOTES, 'UTF-8'); ?>" class="cv-btn cv-btn-outline cv-btn-sm">
                                        <span>↩️</span> <?php echo _("Reply"); ?>
                                    </a>
                                <?php endif; ?>

                                <button type="button" class="cv-btn cv-btn-ghost cv-btn-sm cv-toggle-btn" onclick="cvToggleCard('<?php echo $cardId; ?>')">
                                    <span>🔍</span> <?php echo _("Toggle Body"); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    } catch (\Throwable $e) {
        error_log('cv_render_thread_view error: ' . $e->getMessage());
    }
}

/**
 * Annotate mailbox message list with draft badges, sent reply indicators,
 * and conversation thread markers.
 *
 * @param array $aMessages Reference to message rows in template
 * @param string $currentMailbox Current mailbox name
 * @param resource $imapConnection IMAP stream
 */
function cv_mailbox_annotate_messages(&$aMessages, $currentMailbox, $imapConnection)
{
    global $data_dir, $username, $draft_folder, $sent_folder;

    if (empty($aMessages) || !is_array($aMessages)) {
        return;
    }

    $badgesEnabled = (int) getPref($data_dir, $username, 'cv_mailbox_badges', 1);
    if (!$badgesEnabled) {
        return;
    }

    $user_draft = getPref($data_dir, $username, 'draft_folder');
    if (empty($user_draft)) {
        $user_draft = $draft_folder;
    }

    $draft_candidates = array_unique(array_filter(array(
        $user_draft,
        $draft_folder,
        'INBOX.Drafts',
        'Drafts',
        'INBOX/Drafts',
    )));

    $baseUri = sqm_baseuri();
    $isDraftsMailbox = false;
    foreach ($draft_candidates as $df) {
        if ($df && strcasecmp($df, $currentMailbox) === 0) {
            $isDraftsMailbox = true;
            break;
        }
    }

    try {
        if ($isDraftsMailbox) {
            // We are looking at the Drafts folder
            // Annotate drafts that are replies to original messages
            $draftUids = array_keys($aMessages);
            if (!empty($draftUids) && $imapConnection) {
                $hdr_list = sqimap_get_small_header_list(
                    $imapConnection,
                    $draftUids,
                    array('Date', 'To', 'From', 'Subject', 'Message-ID', 'In-Reply-To', 'References', 'X-SM-Flag-Reply'),
                    array('FLAGS')
                );
                if (!empty($hdr_list) && is_array($hdr_list)) {
                    foreach ($hdr_list as $duid => $dhdr) {
                        if (!empty($dhdr['x-sm-flag-reply']) && isset($aMessages[$duid]['columns'][SQM_COL_SUBJ]['value'])) {
                            $parts = explode('::', $dhdr['x-sm-flag-reply'], 3);
                            $origBox = isset($parts[2]) ? $parts[2] : '';
                            $origAction = isset($parts[0]) ? $parts[0] : 'reply';
                            $actionLabel = ($origAction === 'forward' || $origAction === 'forward_as_attachment')
                                ? _("Draft Forward")
                                : _("Draft Reply");
                            $boxHint = !empty($origBox) ? ' (' . htmlspecialchars($origBox, ENT_QUOTES, 'UTF-8') . ')' : '';
                            $badge = '<span class="cv-mb-badge cv-mb-badge-reply-to" title="' . sprintf(_("Pending %s to message in %s"), $actionLabel, htmlspecialchars($origBox, ENT_QUOTES, 'UTF-8')) . '">'
                                   . '↩️ ' . $actionLabel . $boxHint . '</span>';
                            $aMessages[$duid]['columns'][SQM_COL_SUBJ]['value'] = $badge . $aMessages[$duid]['columns'][SQM_COL_SUBJ]['value'];
                        }
                    }
                }
            }
            return;
        }

        // We are looking at a regular mailbox (e.g. INBOX)
        // Gather all pending drafts from user's Drafts folder (up to 30 latest drafts)
        $draftsByReplyUid = array();
        $draftsByInReplyTo = array();
        $draftsBySubject = array();

        if ($imapConnection) {
            $draftBoxes = cv_resolve_all_special_folders($imapConnection, 'draft');
            foreach ($draftBoxes as $selectedDraftsBox) {
                if (empty($selectedDraftsBox)) continue;
                $mbx_info = sqimap_mailbox_select($imapConnection, $selectedDraftsBox, false);
                if (empty($mbx_info) || !isset($mbx_info['EXISTS']) || $mbx_info['EXISTS'] <= 0) {
                    continue;
                }

                $draftUids = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($draftUids) && is_array($draftUids)) {
                    if (count($draftUids) > 40) {
                        $draftUids = array_slice($draftUids, -40);
                    }
                    $draftHeaders = sqimap_get_small_header_list(
                        $imapConnection,
                        $draftUids,
                        array('Date', 'To', 'From', 'Subject', 'Message-ID', 'In-Reply-To', 'References', 'X-SM-Flag-Reply'),
                        array('INTERNALDATE')
                    );
                    if (!empty($draftHeaders) && is_array($draftHeaders)) {
                        foreach ($draftHeaders as $duid => $dhdr) {
                            $dInfo = array(
                                'uid'     => (int)$duid,
                                'mailbox' => $selectedDraftsBox,
                                'subject' => !empty($dhdr['subject']) ? decodeHeader($dhdr['subject'], false, false, true) : '',
                            );
                            if (!empty($dhdr['x-sm-flag-reply'])) {
                                $parts = explode('::', $dhdr['x-sm-flag-reply'], 3);
                                if (isset($parts[1]) && is_numeric($parts[1])) {
                                    $draftsByReplyUid[(int)$parts[1]] = $dInfo;
                                }
                            }
                            if (!empty($dhdr['in-reply-to'])) {
                                $ids = cv_extract_ids($dhdr['in-reply-to']);
                                foreach ($ids as $cleanMid) {
                                    $draftsByInReplyTo[$cleanMid] = $dInfo;
                                }
                            }
                            if (!empty($dhdr['subject'])) {
                                $cSubj = cv_clean_subject($dhdr['subject']);
                                $cSubjLower = mb_strtolower($cSubj, 'UTF-8');
                                if (mb_strlen($cSubjLower, 'UTF-8') >= 3 && !isset($draftsBySubject[$cSubjLower])) {
                                    $draftsBySubject[$cSubjLower] = $dInfo;
                                }
                            }
                        }
                    }
                }
            }
            // Always restore current mailbox selection immediately
            if (!empty($currentMailbox)) {
                @sqimap_mailbox_select($imapConnection, $currentMailbox, false);
            }
        }

        // 2. Iterate through messages in the current mailbox view and annotate
        foreach ($aMessages as $uid => &$msg) {
            if (!isset($msg['columns'][SQM_COL_SUBJ]['value'])) {
                continue;
            }

            $badges = '';

            // Check if there is a pending draft for this message
            $matchedDraft = null;
            if (isset($draftsByReplyUid[(int)$uid])) {
                $matchedDraft = $draftsByReplyUid[(int)$uid];
            } elseif (!empty($msg['rfc822_header']->message_id)) {
                $cleanMid = trim($msg['rfc822_header']->message_id, "<> \t\n\r");
                if (isset($draftsByInReplyTo[$cleanMid])) {
                    $matchedDraft = $draftsByInReplyTo[$cleanMid];
                }
            }
            if (!$matchedDraft && !empty($msg['columns'][SQM_COL_SUBJ]['value'])) {
                $rawSubj = $msg['columns'][SQM_COL_SUBJ]['value'];
                $cleanSubj = mb_strtolower(cv_clean_subject(strip_tags($rawSubj)), 'UTF-8');
                if (mb_strlen($cleanSubj, 'UTF-8') >= 3 && isset($draftsBySubject[$cleanSubj])) {
                    $matchedDraft = $draftsBySubject[$cleanSubj];
                }
            }

            if ($matchedDraft) {
                $acctParam = isset($GLOBALS['iAccount']) ? (int)$GLOBALS['iAccount'] : 0;
                $resumeUrl = $baseUri . 'src/read_body.php?account=' . $acctParam . '&mailbox=' . urlencode($matchedDraft['mailbox']) . '&passed_id=' . $matchedDraft['uid'] . '&startMessage=1';
                $escapedResumeUrl = htmlspecialchars($resumeUrl, ENT_QUOTES, 'UTF-8');
                $badges .= '<span class="cv-mb-badge cv-mb-badge-draft" role="button" tabindex="0" data-url="' . $escapedResumeUrl . '" onclick="event.preventDefault(); event.stopPropagation(); window.sqmApp ? window.sqmApp.navigate(this.dataset.url) : (window.location.href=this.dataset.url);" title="' . _("Resume pending draft response") . '">'
                        . '📝 ' . _("Draft") . '</span>';
            }

            // Check if replied / answered
            $isAnswered = false;
            if (isset($msg['columns'][SQM_COL_FLAGS]['value']['answered']) && $msg['columns'][SQM_COL_FLAGS]['value']['answered']) {
                $isAnswered = true;
            } elseif (isset($msg['flags']['\\answered']) && $msg['flags']['\\answered']) {
                $isAnswered = true;
            } elseif (isset($msg['flags']['answered']) && $msg['flags']['answered']) {
                $isAnswered = true;
            }

            if ($isAnswered) {
                $badges .= '<span class="cv-mb-badge cv-mb-badge-sent" title="' . _("You replied to this message") . '">'
                        . '📤 ' . _("Replied") . '</span>';
            }

            if (!empty($badges)) {
                $msg['columns'][SQM_COL_SUBJ]['value'] = $badges . $msg['columns'][SQM_COL_SUBJ]['value'];
            }
        }
        unset($msg);

    } catch (\Throwable $e) {
        error_log('cv_mailbox_annotate_messages error: ' . $e->getMessage());
    } finally {
        if ($imapConnection && !empty($currentMailbox)) {
            @sqimap_mailbox_select($imapConnection, $currentMailbox, false);
        }
    }
}
