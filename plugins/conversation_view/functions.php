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
 * Detects if a given mailbox is a Sent folder
 *
 * @param string $mailbox Mailbox name
 * @return bool
 */
function cv_is_sent_mailbox($mailbox)
{
    if (empty($mailbox)) {
        return false;
    }
    global $sent_folder, $data_dir, $username;
    $user_sent = getPref($data_dir, $username, 'sent_folder');
    if (!empty($user_sent) && $user_sent !== 'none' && strcasecmp($mailbox, $user_sent) === 0) {
        return true;
    }
    if (!empty($sent_folder) && $sent_folder !== 'none' && strcasecmp($mailbox, $sent_folder) === 0) {
        return true;
    }
    $clean = trim((string)$mailbox);
    $parts = preg_split('/[\.\/]/', $clean);
    $leaf = strtolower(end($parts));
    $sentKeywords = array(
        'sent', 'sent items', 'sent messages', 'sent-mail', 'sentmail',
        'verzonden', 'verzonden items', 'gesendet', 'gesendete elemente',
        'gesendete objekte', 'envoyés', 'elements envoyes', 'éléments envoyés',
        'inviati', 'posta inviata', 'enviados', 'elementos enviados', 'outbox'
    );
    if (in_array($leaf, $sentKeywords, true)) {
        return true;
    }
    $lower = strtolower($clean);
    foreach ($sentKeywords as $kw) {
        if ($lower === $kw || $lower === 'inbox.' . $kw || $lower === 'inbox/' . $kw) {
            return true;
        }
    }
    return false;
}

/**
 * Detects if a given mailbox is a Drafts folder
 *
 * @param string $mailbox Mailbox name
 * @return bool
 */
function cv_is_draft_mailbox($mailbox)
{
    if (empty($mailbox)) {
        return false;
    }
    global $draft_folder, $data_dir, $username;
    $user_draft = getPref($data_dir, $username, 'draft_folder');
    if (!empty($user_draft) && $user_draft !== 'none' && strcasecmp($mailbox, $user_draft) === 0) {
        return true;
    }
    if (!empty($draft_folder) && $draft_folder !== 'none' && strcasecmp($mailbox, $draft_folder) === 0) {
        return true;
    }
    $clean = trim((string)$mailbox);
    $parts = preg_split('/[\.\/]/', $clean);
    $leaf = strtolower(end($parts));
    $draftKeywords = array(
        'draft', 'drafts', 'concepten', 'entwürfe', 'entwuerfe',
        'brouillons', 'bozze', 'borradores', 'rascunhos'
    );
    if (in_array($leaf, $draftKeywords, true)) {
        return true;
    }
    $lower = strtolower($clean);
    foreach ($draftKeywords as $kw) {
        if ($lower === $kw || $lower === 'inbox.' . $kw || $lower === 'inbox/' . $kw) {
            return true;
        }
    }
    return false;
}

/**
 * Extract RFC-822 Message-IDs from header strings (e.g. References, In-Reply-To, Message-ID)
 *
 * @param string $string Raw header string
 * @return array Clean array of normalized lowercase Message-ID strings (without angle brackets)
 */
function cv_extract_ids($string)
{
    if (empty($string)) {
        return array();
    }
    $ids = array();
    preg_match_all('/<([^>]+)>/', $string, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $id) {
            $clean = strtolower(trim($id, "<> \t\n\r\0\x0B"));
            if (!empty($clean)) {
                $ids[] = $clean;
            }
        }
    }
    $tokens = preg_split('/[\s,;]+/', $string);
    foreach ($tokens as $tok) {
        $tok = strtolower(trim($tok, "<> \t\n\r\0\x0B"));
        if (!empty($tok) && strpos($tok, '@') !== false) {
            $ids[] = $tok;
        }
    }
    return array_values(array_unique(array_filter($ids)));
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

    if (empty($prefFolder) || $prefFolder === 'none') {
        $prefFolder = ($type === 'draft') ? $draft_folder : $sent_folder;
    }
    if ($prefFolder === 'none') {
        $prefFolder = '';
    }

    $commonCandidates = ($type === 'draft')
        ? array('Drafts', 'INBOX.Drafts', 'Draft', 'INBOX/Drafts', 'INBOX.Draft', 'Concepten', 'INBOX.Concepten', 'Entwürfe', 'Brouillons', 'Bozze', 'Borradores', 'Rascunhos')
        : array('Sent', 'INBOX.Sent', 'Sent Items', 'INBOX.Sent Items', 'Sent Messages', 'INBOX.Sent Messages', 'INBOX/Sent', 'INBOX/Sent Items', 'Verzonden', 'Verzonden items', 'INBOX.Verzonden', 'Gesendete Elemente', 'Gesendet', 'Envoyés', 'Éléments envoyés', 'Inviati', 'Posta inviata', 'Enviados', 'Elementos enviados', 'Sent-Mail', 'Outbox');

    $candidates = array_merge(array($prefFolder), $commonCandidates);
    $candidates = array_values(array_unique(array_filter($candidates, function($c) {
        return !empty($c) && $c !== 'none';
    })));

    $existing = array();

    // 1. Check cached folder list if available
    $boxes = sqimap_mailbox_list($imapConnection);
    if (!empty($boxes)) {
        // Inspect each box, including RFC 6154 SPECIAL-USE flags and names
        $flagTarget = ($type === 'draft') ? '\\drafts' : '\\sent';
        foreach ($boxes as $b) {
            $name = '';
            $flags = array();
            if (is_array($b)) {
                $name = !empty($b['unformatted']) ? $b['unformatted'] : (!empty($b['mailboxname_full']) ? $b['mailboxname_full'] : '');
                if (!empty($b['flags']) && is_array($b['flags'])) {
                    $flags = array_map('strtolower', $b['flags']);
                }
            } elseif (is_object($b)) {
                $name = !empty($b->mailboxname_full) ? $b->mailboxname_full : (!empty($b->unformatted) ? $b->unformatted : '');
            }
            if (!empty($name) && $name !== 'none') {
                if (in_array($flagTarget, $flags, true)) {
                    $existing[] = $name;
                }
            }
        }

        $available = cv_flatten_mailboxes($boxes);
        if (!empty($available)) {
            foreach ($candidates as $cand) {
                foreach ($available as $avail) {
                    if (strcasecmp($cand, $avail) === 0) {
                        $existing[] = $avail;
                    }
                }
            }

            $targetWords = ($type === 'draft')
                ? array('draft', 'concept', 'entw', 'brouill', 'bozz', 'borrad', 'rascunh')
                : array('sent', 'verzond', 'gesend', 'envoy', 'inviat', 'enviad', 'outbox');

            foreach ($available as $avail) {
                if ($avail === 'none') continue;
                $parts = preg_split('/[\.\/]/', $avail);
                $last = strtolower(end($parts));
                foreach ($targetWords as $tw) {
                    if (strpos($last, $tw) !== false) {
                        $existing[] = $avail;
                        break;
                    }
                }
            }
        }
    }

    // 2. Direct IMAP test for candidates not found in cached list
    foreach ($candidates as $cand) {
        if ($cand === 'none' || in_array($cand, $existing, true)) {
            continue;
        }
        if (sqimap_mailbox_exists($imapConnection, $cand)) {
            $existing[] = $cand;
        }
    }

    $existing = array_values(array_unique(array_filter($existing, function($c) {
        return !empty($c) && $c !== 'none';
    })));

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

        // Resilient fallback: fetch headers directly via IMAP if current message header lacks IDs or reply flag
        if ((empty($curr_id) || empty($raw_subject) || (empty($in_reply_to) && empty($references) && empty($flag_reply))) && !empty($currentUid) && !empty($currentMailbox)) {
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
            if (cv_is_draft_mailbox($currentMailbox)) {
                $cType = 'draft';
            } elseif (cv_is_sent_mailbox($currentMailbox)) {
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
        if (cv_is_draft_mailbox($currentMailbox)) {
            $curType = 'draft';
        } elseif (cv_is_sent_mailbox($currentMailbox)) {
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

            // 3d. Always inspect recent drafts (up to 50) to guarantee custom flag detection
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

            // 3e. Always inspect recent sent messages (up to 50) so sent replies are never missed even if IMAP header search is unsupported
            if ($defaultType === 'sent') {
                $resAllSent = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($resAllSent) && is_array($resAllSent)) {
                    if (count($resAllSent) > 50) {
                        $resAllSent = array_slice($resAllSent, -50);
                    }
                    foreach ($resAllSent as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            // 3f. In INBOX, if current viewed message is a sent reply or draft, inspect recent incoming messages (up to 50)
            if ($defaultType === 'received' && $curType !== 'received' && strcasecmp($folderName, 'INBOX') === 0) {
                $resAllInbox = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($resAllInbox) && is_array($resAllInbox)) {
                    if (count($resAllInbox) > 50) {
                        $resAllInbox = array_slice($resAllInbox, -50);
                    }
                    foreach ($resAllInbox as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            $found_uids = array_values(array_unique(array_filter($found_uids)));
            if (empty($found_uids)) {
                continue;
            }

            // Limit to most recent 50 messages per folder
            if (count($found_uids) > 50) {
                $found_uids = array_slice($found_uids, -50);
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
                if ($defaultType === 'draft' || cv_is_draft_mailbox($folderName)) {
                    $type = 'draft';
                } elseif ($defaultType === 'sent' || cv_is_sent_mailbox($folderName)) {
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
                $all_hdr_ids = array();
                if (!empty($hdr['in-reply-to']) || !empty($hdr['references']) || !empty($hdr['message-id'])) {
                    $hdr_in_reply = !empty($hdr['in-reply-to']) ? cv_extract_ids($hdr['in-reply-to']) : array();
                    $hdr_refs = !empty($hdr['references']) ? cv_extract_ids($hdr['references']) : array();
                    $hdr_mid = !empty($hdr['message-id']) ? cv_extract_ids($hdr['message-id']) : array();
                    $all_hdr_ids = array_merge($hdr_in_reply, $hdr_refs, $hdr_mid);
                    if (!$has_link && !empty($search_ids)) {
                        $common_ids = array_intersect($search_ids, $all_hdr_ids);
                        if (!empty($common_ids)) {
                            $has_link = true;
                        }
                    }
                }

                // Check Original UID pointer
                if (!$has_link && !empty($origReplyUid) && $uid === (int)$origReplyUid) {
                    $has_link = true;
                }

                // Check Subject match
                if (!$has_link && !empty($clean_subject)) {
                    $hdr_clean_subj = cv_clean_subject(!empty($hdr['subject']) ? $hdr['subject'] : '');
                    $s1 = mb_strtolower($hdr_clean_subj, 'UTF-8');
                    $s2 = mb_strtolower($clean_subject, 'UTF-8');
                    if ($s1 !== '' && $s2 !== '') {
                        if ($s1 === $s2) {
                            $has_link = true;
                        } elseif (mb_strlen($s1, 'UTF-8') >= 8 && mb_strlen($s2, 'UTF-8') >= 8) {
                            if (strpos($s1, $s2) === 0 || strpos($s2, $s1) === 0) {
                                $has_link = true;
                            }
                        }
                    }
                }

                if (!$has_link) {
                    continue;
                }

                // Expand search_ids with newly found linked message's IDs for transitive thread expansion
                if (!empty($all_hdr_ids)) {
                    $search_ids = array_values(array_unique(array_merge($search_ids, $all_hdr_ids)));
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
    // Deprecated and removed: conversation thread when opening a mail is disabled per user request
    return;
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

    $baseUri = sqm_baseuri();
    $isDraftsMailbox = cv_is_draft_mailbox($currentMailbox);

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

/**
 * Build interactive Mailbox Thread View with replies and drafts nested under emails.
 *
 * In the inbox and subfolders, groups messages by conversation thread.
 * For each conversation thread, identifies and weaves:
 * - Related sent replies from the user's Sent folder(s)
 * - Related pending drafts from the user's Drafts folder(s)
 * - In-folder reply chains within the current mailbox
 *
 * Formats every child reply and draft as a dedicated row nested directly under
 * its parent message with visual branch connectors (↳), proper links (compose.php for drafts,
 * read_body.php for sent replies), timestamps, senders, and thread indentation.
 *
 * @param array &$aMessages Reference to message rows in template
 * @param string $currentMailbox Current mailbox name
 * @param resource $imapConnection IMAP connection handle
 * @param object|null $tpl Template instance
 */
function cv_mailbox_thread_view(&$aMessages, $currentMailbox, $imapConnection, $tpl = null)
{
    global $data_dir, $username, $iAccount;

    if (empty($aMessages) || !is_array($aMessages) || !$imapConnection) {
        return;
    }

    // Do not cross-pollinate if currently viewing Sent or Drafts folders directly
    if (cv_is_draft_mailbox($currentMailbox) || cv_is_sent_mailbox($currentMailbox)) {
        return;
    }

    $search_sent = (bool) getPref($data_dir, $username, 'cv_search_sent', 1);
    $search_drafts = (bool) getPref($data_dir, $username, 'cv_search_drafts', 1);

    $baseUri = sqm_baseuri();
    $acctParam = isset($iAccount) ? (int)$iAccount : (isset($GLOBALS['account']) ? (int)$GLOBALS['account'] : 0);

    // 1. Gather all message headers and identifiers for page messages
    $pageUids = array();
    foreach ($aMessages as $u => $msgData) {
        if (is_numeric($u)) {
            $pageUids[] = (int)$u;
        }
    }

    if (empty($pageUids)) {
        return;
    }

    try {
        // Ensure we have complete threading headers for all page messages
        $pageHeaders = array();
        if (isset($GLOBALS['aMailbox']['MSG_HEADERS']) && is_array($GLOBALS['aMailbox']['MSG_HEADERS'])) {
            foreach ($pageUids as $u) {
                if (isset($GLOBALS['aMailbox']['MSG_HEADERS'][$u])) {
                    $pageHeaders[$u] = $GLOBALS['aMailbox']['MSG_HEADERS'][$u];
                }
            }
        }

        // Check if any headers lack message-id / in-reply-to / references
        $missingUids = array();
        foreach ($pageUids as $u) {
            if (empty($pageHeaders[$u]) || !isset($pageHeaders[$u]['message-id'])) {
                $missingUids[] = $u;
            }
        }

        if (!empty($missingUids)) {
            $fetched = sqimap_get_small_header_list(
                $imapConnection,
                $missingUids,
                array('Date', 'To', 'From', 'Subject', 'Message-ID', 'In-Reply-To', 'References', 'X-SM-Flag-Reply', 'Content-Type'),
                array('FLAGS', 'RFC822.SIZE', 'INTERNALDATE')
            );
            if (!empty($fetched) && is_array($fetched)) {
                foreach ($fetched as $fu => $fhdr) {
                    $pageHeaders[$fu] = $fhdr;
                    if (isset($GLOBALS['aMailbox']['MSG_HEADERS'])) {
                        $GLOBALS['aMailbox']['MSG_HEADERS'][$fu] = $fhdr;
                    }
                }
            }
        }

        // Map page messages
        $pageNodes = array();
        $midToUid = array();
        $cleanSubjToUids = array();

        foreach ($pageUids as $uid) {
            $hdr = isset($pageHeaders[$uid]) ? $pageHeaders[$uid] : array();
            $rawMid = !empty($hdr['message-id']) ? $hdr['message-id'] : '';
            $mids = cv_extract_ids($rawMid);
            $mid = !empty($mids[0]) ? $mids[0] : '';

            $irts = !empty($hdr['in-reply-to']) ? cv_extract_ids($hdr['in-reply-to']) : array();
            $refs = !empty($hdr['references']) ? cv_extract_ids($hdr['references']) : array();

            $rawSubj = !empty($hdr['subject'])
                ? decodeHeader($hdr['subject'], false, false, true)
                : (isset($aMessages[$uid]['columns'][SQM_COL_SUBJ]['title']) ? $aMessages[$uid]['columns'][SQM_COL_SUBJ]['title'] : '');
            $cleanSubj = mb_strtolower(cv_clean_subject($rawSubj), 'UTF-8');

            $dateStr = !empty($hdr['date']) ? $hdr['date'] : '';
            $timestamp = !empty($dateStr) ? strtotime($dateStr) : (isset($hdr['internaldate']) ? strtotime($hdr['internaldate']) : 0);
            if ($timestamp <= 0) $timestamp = time();

            $flagReply = !empty($hdr['x-sm-flag-reply']) ? trim($hdr['x-sm-flag-reply']) : '';

            $pageNodes[$uid] = array(
                'uid'         => $uid,
                'mid'         => $mid,
                'irts'        => $irts,
                'refs'        => $refs,
                'clean_subj'  => $cleanSubj,
                'raw_subj'    => $rawSubj,
                'timestamp'   => $timestamp,
                'from'        => !empty($hdr['from']) ? $hdr['from'] : '',
                'to'          => !empty($hdr['to']) ? $hdr['to'] : '',
                'flag_reply'  => $flagReply,
                'row'         => $aMessages[$uid],
            );

            if (!empty($mid)) {
                $midToUid[$mid] = $uid;
            }
            if (mb_strlen($cleanSubj, 'UTF-8') >= 3) {
                $cleanSubjToUids[$cleanSubj][] = $uid;
            }
        }

        // 2. Discover related Sent Replies from Sent folder(s)
        $sentRepliesByParent = array();
        if ($search_sent) {
            $sentBoxes = cv_resolve_all_special_folders($imapConnection, 'sent');
            foreach ($sentBoxes as $sentBox) {
                if (empty($sentBox) || strcasecmp($sentBox, $currentMailbox) === 0) continue;
                $sInfo = sqimap_mailbox_select($imapConnection, $sentBox, false);
                if (empty($sInfo) || empty($sInfo['EXISTS']) || $sInfo['EXISTS'] <= 0) continue;

                $candSentUids = array();

                // Recent sent UIDs
                $allSent = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($allSent) && is_array($allSent)) {
                    $sliceSent = (count($allSent) > 60) ? array_slice($allSent, -60) : $allSent;
                    foreach ($sliceSent as $su) {
                        if (is_numeric($su)) $candSentUids[(int)$su] = true;
                    }
                }

                // Search by Message-IDs in References or In-Reply-To
                foreach (array_keys($midToUid) as $pmid) {
                    if (strlen($pmid) < 4) continue;
                    $escPmid = addcslashes($pmid, '"\\');
                    $resIrt = cv_run_uid_search($imapConnection, 'HEADER In-Reply-To "' . $escPmid . '"');
                    if (!empty($resIrt) && is_array($resIrt)) {
                        foreach ($resIrt as $su) if (is_numeric($su)) $candSentUids[(int)$su] = true;
                    }
                    $resRef = cv_run_uid_search($imapConnection, 'HEADER References "' . $escPmid . '"');
                    if (!empty($resRef) && is_array($resRef)) {
                        foreach ($resRef as $su) if (is_numeric($su)) $candSentUids[(int)$su] = true;
                    }
                }

                if (!empty($candSentUids)) {
                    $targetSentUids = array_keys($candSentUids);
                    $sentHdrs = sqimap_get_small_header_list(
                        $imapConnection,
                        $targetSentUids,
                        array('Date', 'To', 'From', 'Subject', 'Message-ID', 'In-Reply-To', 'References', 'Content-Type', 'X-SM-Flag-Reply'),
                        array('FLAGS', 'RFC822.SIZE', 'INTERNALDATE')
                    );

                    if (!empty($sentHdrs) && is_array($sentHdrs)) {
                        foreach ($sentHdrs as $suid => $shdr) {
                            $sRawMid = !empty($shdr['message-id']) ? $shdr['message-id'] : '';
                            $sMids = cv_extract_ids($sRawMid);
                            $sMid = !empty($sMids[0]) ? $sMids[0] : '';
                            $sIrts = !empty($shdr['in-reply-to']) ? cv_extract_ids($shdr['in-reply-to']) : array();
                            $sRefs = !empty($shdr['references']) ? cv_extract_ids($shdr['references']) : array();
                            $sRawSubj = !empty($shdr['subject']) ? decodeHeader($shdr['subject'], false, false, true) : '';
                            $sCleanSubj = mb_strtolower(cv_clean_subject($sRawSubj), 'UTF-8');
                            $sDateStr = !empty($shdr['date']) ? $shdr['date'] : '';
                            $sTimestamp = !empty($sDateStr) ? strtotime($sDateStr) : (isset($shdr['internaldate']) ? strtotime($shdr['internaldate']) : 0);
                            if ($sTimestamp <= 0) $sTimestamp = time();
                            $sFlagReply = !empty($shdr['x-sm-flag-reply']) ? trim($shdr['x-sm-flag-reply']) : '';

                            // Determine which parent message on page this sent reply belongs to
                            $matchedParentUid = null;

                            // Match A: In-Reply-To
                            foreach ($sIrts as $irt) {
                                if (isset($midToUid[$irt])) {
                                    $matchedParentUid = $midToUid[$irt];
                                    break;
                                }
                            }

                            // Match B: References
                            if (!$matchedParentUid) {
                                foreach ($sRefs as $ref) {
                                if (isset($midToUid[$ref])) {
                                    $matchedParentUid = $midToUid[$ref];
                                    break;
                                }
                            }
                        }

                        // Match C: X-SM-Flag-Reply (origAction::origUid::origBox)
                        if (!$matchedParentUid && !empty($sFlagReply)) {
                            $fparts = explode('::', $sFlagReply, 3);
                            if (!empty($fparts[1]) && is_numeric($fparts[1])) {
                                $pCandidate = (int)$fparts[1];
                                $pBox = isset($fparts[2]) ? trim($fparts[2]) : '';
                                if (isset($pageNodes[$pCandidate]) && (empty($pBox) || strcasecmp($pBox, $currentMailbox) === 0)) {
                                    $matchedParentUid = $pCandidate;
                                }
                            }
                        }

                        // Match D: Clean subject match (must be newer than parent)
                        if (!$matchedParentUid && mb_strlen($sCleanSubj, 'UTF-8') >= 3 && isset($cleanSubjToUids[$sCleanSubj])) {
                            $bestCandidate = null;
                            $bestTimeDiff = PHP_INT_MAX;
                            foreach ($cleanSubjToUids[$sCleanSubj] as $candUid) {
                                $pTime = $pageNodes[$candUid]['timestamp'];
                                if ($sTimestamp >= ($pTime - 60)) {
                                    $diff = $sTimestamp - $pTime;
                                    if ($diff < $bestTimeDiff) {
                                        $bestTimeDiff = $diff;
                                        $bestCandidate = $candUid;
                                    }
                                }
                            }
                            if ($bestCandidate !== null) {
                                $matchedParentUid = $bestCandidate;
                            }
                        }

                        if ($matchedParentUid !== null) {
                            $hasAttach = (isset($shdr['content-type']) && is_array($shdr['content-type']) && $shdr['content-type'][0] === 'multipart');
                            $sentRepliesByParent[$matchedParentUid][] = array(
                                'uid'            => (int)$suid,
                                'mailbox'        => $sentBox,
                                'mid'            => $sMid,
                                'raw_subj'       => $sRawSubj,
                                'clean_subj'     => $sCleanSubj,
                                'to'             => !empty($shdr['to']) ? $shdr['to'] : '',
                                'from'           => !empty($shdr['from']) ? $shdr['from'] : '',
                                'timestamp'      => $sTimestamp,
                                'size'           => isset($shdr['rfc822.size']) ? (int)$shdr['rfc822.size'] : 0,
                                'has_attachment' => $hasAttach,
                            );
                        }
                    }
                }
            }
        }
    }

    // 3. Discover related Pending Drafts from Drafts folder(s)
    $draftsByParent = array();
    if ($search_drafts) {
        $draftBoxes = cv_resolve_all_special_folders($imapConnection, 'draft');
        foreach ($draftBoxes as $draftBox) {
            if (empty($draftBox) || strcasecmp($draftBox, $currentMailbox) === 0) continue;
            $dInfo = sqimap_mailbox_select($imapConnection, $draftBox, false);
            if (empty($dInfo) || empty($dInfo['EXISTS']) || $dInfo['EXISTS'] <= 0) continue;

            $allDrafts = cv_run_uid_search($imapConnection, 'ALL');
            if (!empty($allDrafts) && is_array($allDrafts)) {
                $targetDraftUids = (count($allDrafts) > 60) ? array_slice($allDrafts, -60) : $allDrafts;
                $draftHdrs = sqimap_get_small_header_list(
                    $imapConnection,
                    $targetDraftUids,
                    array('Date', 'To', 'From', 'Subject', 'Message-ID', 'In-Reply-To', 'References', 'X-SM-Flag-Reply', 'Content-Type'),
                    array('FLAGS', 'RFC822.SIZE', 'INTERNALDATE')
                );

                if (!empty($draftHdrs) && is_array($draftHdrs)) {
                    foreach ($draftHdrs as $duid => $dhdr) {
                        $dRawMid = !empty($dhdr['message-id']) ? $dhdr['message-id'] : '';
                        $dMids = cv_extract_ids($dRawMid);
                        $dMid = !empty($dMids[0]) ? $dMids[0] : '';
                        $dIrts = !empty($dhdr['in-reply-to']) ? cv_extract_ids($dhdr['in-reply-to']) : array();
                        $dRefs = !empty($dhdr['references']) ? cv_extract_ids($dhdr['references']) : array();
                        $dRawSubj = !empty($dhdr['subject']) ? decodeHeader($dhdr['subject'], false, false, true) : '';
                        $dCleanSubj = mb_strtolower(cv_clean_subject($dRawSubj), 'UTF-8');
                        $dDateStr = !empty($dhdr['date']) ? $dhdr['date'] : '';
                        $dTimestamp = !empty($dDateStr) ? strtotime($dDateStr) : (isset($dhdr['internaldate']) ? strtotime($dhdr['internaldate']) : 0);
                        if ($dTimestamp <= 0) $dTimestamp = time();
                        $dFlagReply = !empty($dhdr['x-sm-flag-reply']) ? trim($dhdr['x-sm-flag-reply']) : '';

                        $matchedParentUid = null;

                        // Match A: X-SM-Flag-Reply
                        if (!empty($dFlagReply)) {
                            $fparts = explode('::', $dFlagReply, 3);
                            if (!empty($fparts[1]) && is_numeric($fparts[1])) {
                                $pCandidate = (int)$fparts[1];
                                $pBox = isset($fparts[2]) ? trim($fparts[2]) : '';
                                if (isset($pageNodes[$pCandidate]) && (empty($pBox) || strcasecmp($pBox, $currentMailbox) === 0)) {
                                    $matchedParentUid = $pCandidate;
                                }
                            }
                        }

                        // Match B: In-Reply-To
                        if (!$matchedParentUid) {
                            foreach ($dIrts as $irt) {
                                if (isset($midToUid[$irt])) {
                                    $matchedParentUid = $midToUid[$irt];
                                    break;
                                }
                            }
                        }

                        // Match C: References
                        if (!$matchedParentUid) {
                            foreach ($dRefs as $ref) {
                                if (isset($midToUid[$ref])) {
                                    $matchedParentUid = $midToUid[$ref];
                                    break;
                                }
                            }
                        }

                        // Match D: Clean subject match
                        if (!$matchedParentUid && mb_strlen($dCleanSubj, 'UTF-8') >= 3 && isset($cleanSubjToUids[$dCleanSubj])) {
                            $matchedParentUid = end($cleanSubjToUids[$dCleanSubj]);
                        }

                        if ($matchedParentUid !== null) {
                            $hasAttach = (isset($dhdr['content-type']) && is_array($dhdr['content-type']) && $dhdr['content-type'][0] === 'multipart');
                            $draftsByParent[$matchedParentUid][] = array(
                                'uid'            => (int)$duid,
                                'mailbox'        => $draftBox,
                                'mid'            => $dMid,
                                'raw_subj'       => $dRawSubj,
                                'clean_subj'     => $dCleanSubj,
                                'to'             => !empty($dhdr['to']) ? $dhdr['to'] : '',
                                'from'           => !empty($dhdr['from']) ? $dhdr['from'] : '',
                                'timestamp'      => $dTimestamp,
                                'size'           => isset($dhdr['rfc822.size']) ? (int)$dhdr['rfc822.size'] : 0,
                                'has_attachment' => $hasAttach,
                            );
                        }
                    }
                }
            }
        }
    }

    // Always restore selection to current mailbox immediately!
    @sqimap_mailbox_select($imapConnection, $currentMailbox, false);

    // 4. Discover in-folder parent-child relationships between page messages
    $inFolderChildren = array();
    $hasInFolderParent = array();

    foreach ($pageNodes as $uid => $node) {
        $parentFound = null;

        // Check In-Reply-To
        foreach ($node['irts'] as $irt) {
            if (isset($midToUid[$irt]) && $midToUid[$irt] !== $uid) {
                $parentFound = $midToUid[$irt];
                break;
            }
        }

        // Check References
        if (!$parentFound) {
            foreach ($node['refs'] as $ref) {
                if (isset($midToUid[$ref]) && $midToUid[$ref] !== $uid) {
                    $parentFound = $midToUid[$ref];
                    break;
                }
            }
        }

        // Check Subject matching (if starts with Re:/Fwd: and newer)
        if (!$parentFound && mb_strlen($node['clean_subj'], 'UTF-8') >= 3 && isset($cleanSubjToUids[$node['clean_subj']])) {
            $isRe = (bool) preg_match('/^\s*(re|fwd|fw|aw|antw|wg)\s*:/i', $node['raw_subj']);
            if ($isRe) {
                foreach ($cleanSubjToUids[$node['clean_subj']] as $candUid) {
                    if ($candUid !== $uid && $pageNodes[$candUid]['timestamp'] < $node['timestamp']) {
                        $parentFound = $candUid;
                        break;
                    }
                }
            }
        }

        if ($parentFound !== null) {
            $inFolderChildren[$parentFound][] = $uid;
            $hasInFolderParent[$uid] = true;
        }
    }

    // 5. Build final flattened thread list with indents and woven replies/drafts
    $newFormattedMessages = array();
    $processedPageUids = array();

    $appendNodeAndChildren = function ($nodeUid, $indent) use (
        &$appendNodeAndChildren,
        &$newFormattedMessages,
        &$processedPageUids,
        &$pageNodes,
        &$inFolderChildren,
        &$sentRepliesByParent,
        &$draftsByParent,
        $baseUri,
        $acctParam,
        $currentMailbox
    ) {
        if (!isset($pageNodes[$nodeUid]) || isset($processedPageUids[$nodeUid])) {
            return;
        }
        $processedPageUids[$nodeUid] = true;
        $node = $pageNodes[$nodeUid];
        $row = $node['row'];

        // Set subject indentation
        if (isset($row['columns'][SQM_COL_SUBJ])) {
            $row['columns'][SQM_COL_SUBJ]['indent'] = $indent;
        }

        $newFormattedMessages[$nodeUid] = $row;

        // Collect all direct children for this node:
        // A) In-folder replies
        // B) Sent replies
        // C) Drafts
        $children = array();

        // In-folder replies
        if (!empty($inFolderChildren[$nodeUid])) {
            foreach ($inFolderChildren[$nodeUid] as $childUid) {
                if (isset($pageNodes[$childUid])) {
                    $children[] = array(
                        'type'      => 'in_folder',
                        'timestamp' => $pageNodes[$childUid]['timestamp'],
                        'uid'       => $childUid,
                    );
                }
            }
        }

        // Sent replies
        if (!empty($sentRepliesByParent[$nodeUid])) {
            foreach ($sentRepliesByParent[$nodeUid] as $sData) {
                $children[] = array(
                    'type'      => 'sent',
                    'timestamp' => $sData['timestamp'],
                    'data'      => $sData,
                );
            }
        }

        // Drafts
        if (!empty($draftsByParent[$nodeUid])) {
            foreach ($draftsByParent[$nodeUid] as $dData) {
                $children[] = array(
                    'type'      => 'draft',
                    'timestamp' => $dData['timestamp'] + 999999, // position active drafts at end of subthread
                    'data'      => $dData,
                );
            }
        }

        // Sort children chronologically
        usort($children, function ($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        $childIndent = min(5, $indent + 1);

        foreach ($children as $child) {
            if ($child['type'] === 'in_folder') {
                $appendNodeAndChildren($child['uid'], $childIndent);
            } elseif ($child['type'] === 'sent') {
                $s = $child['data'];
                $sentKey = 'cv_sent_' . $s['uid'] . '_' . substr(md5($s['mailbox']), 0, 6);

                $readUrl = $baseUri . 'src/read_body.php?account=' . $acctParam . '&mailbox=' . urlencode($s['mailbox']) . '&passed_id=' . (int)$s['uid'] . '&startMessage=1';

                $toStr = !empty($s['to']) ? decodeHeader($s['to'], false, false, true) : _("Recipient");
                $toParsed = cv_format_address($toStr);
                $toName = !empty($toParsed['name']) ? $toParsed['name'] : (!empty($toParsed['email']) ? $toParsed['email'] : $toStr);

                $fromHtml = '<span class="cv-mb-badge cv-mb-badge-sent">📤 ' . _("Sent") . '</span> ' . _("Me") . (!empty($toName) ? ' &rarr; ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') : '');
                $subjTitle = !empty($s['raw_subj']) ? $s['raw_subj'] : _("(no subject)");
                $subjHtml = '<span class="cv-mb-badge cv-mb-badge-sent">📤 ' . _("Sent") . '</span> ' . htmlspecialchars($subjTitle, ENT_QUOTES, 'UTF-8');

                $sentRow = array(
                    'is_cross_folder' => true,
                    'is_sent_reply'   => true,
                    'row_class'       => 'cv-thread-child-row cv-thread-sent-reply',
                    'columns'         => array(
                        SQM_COL_CHECK => array(
                            'value'  => '',
                            'indent' => $childIndent,
                        ),
                        SQM_COL_FROM => array(
                            'value'  => $fromHtml,
                            'title'  => sprintf(_("Sent reply to %s"), $toStr),
                            'link'   => $readUrl,
                        ),
                        SQM_COL_SUBJ => array(
                            'value'  => $subjHtml,
                            'title'  => $subjTitle,
                            'link'   => $readUrl,
                            'indent' => $childIndent,
                        ),
                        SQM_COL_DATE => array(
                            'value' => function_exists('getDateString') ? getDateString($s['timestamp']) : date('Y-m-d H:i', $s['timestamp']),
                            'title' => function_exists('getDateString') ? getDateString($s['timestamp'], true) : date('r', $s['timestamp']),
                        ),
                        SQM_COL_FLAGS => array(
                            'value' => array(
                                'seen'      => true,
                                'deleted'   => false,
                                'answered'  => false,
                                'forwarded' => false,
                                'flagged'   => false,
                                'draft'     => false,
                            ),
                        ),
                        SQM_COL_SIZE => array(
                            'value' => function_exists('show_readable_size') ? show_readable_size($s['size']) : '',
                        ),
                        SQM_COL_ATTACHMENT => array(
                            'value' => $s['has_attachment'],
                        ),
                    ),
                );

                $newFormattedMessages[$sentKey] = $sentRow;

            } elseif ($child['type'] === 'draft') {
                $d = $child['data'];
                $draftKey = 'cv_draft_' . $d['uid'] . '_' . substr(md5($d['mailbox']), 0, 6);

                $resumeUrl = $baseUri . 'src/compose.php?mailbox=' . urlencode($d['mailbox']) . '&passed_id=' . (int)$d['uid'] . '&smaction=draft';

                $subjTitle = !empty($d['raw_subj']) ? $d['raw_subj'] : _("(draft response)");
                $subjHtml = '<span class="cv-mb-badge cv-mb-badge-draft">📝 ' . _("Draft") . '</span> ' . htmlspecialchars($subjTitle, ENT_QUOTES, 'UTF-8');
                $fromHtml = '<span class="cv-mb-badge cv-mb-badge-draft">📝 ' . _("Draft") . '</span>';

                $draftRow = array(
                    'is_cross_folder' => true,
                    'is_draft'        => true,
                    'row_class'       => 'cv-thread-child-row cv-thread-draft',
                    'columns'         => array(
                        SQM_COL_CHECK => array(
                            'value'  => '',
                            'indent' => $childIndent,
                        ),
                        SQM_COL_FROM => array(
                            'value'  => $fromHtml,
                            'title'  => _("Pending draft response (click to resume)"),
                            'link'   => $resumeUrl,
                        ),
                        SQM_COL_SUBJ => array(
                            'value'  => $subjHtml,
                            'title'  => sprintf(_("Click to resume draft: %s"), $subjTitle),
                            'link'   => $resumeUrl,
                            'indent' => $childIndent,
                        ),
                        SQM_COL_DATE => array(
                            'value' => function_exists('getDateString') ? getDateString($d['timestamp']) : date('Y-m-d H:i', $d['timestamp']),
                            'title' => function_exists('getDateString') ? getDateString($d['timestamp'], true) : date('r', $d['timestamp']),
                        ),
                        SQM_COL_FLAGS => array(
                            'value' => array(
                                'seen'      => true,
                                'deleted'   => false,
                                'answered'  => false,
                                'forwarded' => false,
                                'flagged'   => false,
                                'draft'     => true,
                            ),
                        ),
                        SQM_COL_SIZE => array(
                            'value' => function_exists('show_readable_size') ? show_readable_size($d['size']) : '',
                        ),
                        SQM_COL_ATTACHMENT => array(
                            'value' => $d['has_attachment'],
                        ),
                    ),
                );

                $newFormattedMessages[$draftKey] = $draftRow;
            }
        }
    };

    // Process roots in page order
    foreach ($pageUids as $uid) {
        if (empty($hasInFolderParent[$uid]) && !isset($processedPageUids[$uid])) {
            $appendNodeAndChildren($uid, 0);
        }
    }

    // Safety fallback: append any unprocessed messages
    foreach ($pageUids as $uid) {
        if (!isset($processedPageUids[$uid])) {
            $appendNodeAndChildren($uid, 0);
        }
    }

    $aMessages = $newFormattedMessages;
    if ($tpl) {
        $tpl->assign('aMessages', $newFormattedMessages);
    }
    } catch (\Throwable $e) {
        error_log('cv_mailbox_thread_view error: ' . $e->getMessage());
    } finally {
        if ($imapConnection && !empty($currentMailbox)) {
            @sqimap_mailbox_select($imapConnection, $currentMailbox, false);
        }
    }
}
