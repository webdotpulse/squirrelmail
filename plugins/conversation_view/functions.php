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
    $clean = decodeHeader($subject, false, false, true);
    $prev = '';
    while ($clean !== $prev) {
        $prev = $clean;
        $clean = preg_replace('/^\s*(?:\[[^\]]+\]|(?:re|fwd|fw|aw|antw|rif|sv)(?:\[\d+\])?)\s*[:\-]?\s*/i', '', $clean);
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

        // Resilient fallback: fetch headers directly via IMAP if current message header lacks IDs
        if ((empty($curr_id) || empty($raw_subject)) && !empty($currentUid) && !empty($currentMailbox)) {
            $hdr_cur = sqimap_get_small_header_list($imapConnection, array($currentUid), array('Subject', 'Message-ID', 'In-Reply-To', 'References'));
            if (!empty($hdr_cur) && is_array($hdr_cur)) {
                $c_hdr = reset($hdr_cur);
                if (empty($curr_id) && !empty($c_hdr['message-id'])) $curr_id = trim($c_hdr['message-id']);
                if (empty($in_reply_to) && !empty($c_hdr['in-reply-to'])) $in_reply_to = trim($c_hdr['in-reply-to']);
                if (empty($references) && !empty($c_hdr['references'])) $references = trim($c_hdr['references']);
                if (empty($raw_subject) && !empty($c_hdr['subject'])) $raw_subject = trim($c_hdr['subject']);
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

        // 2. Determine target folders to inspect with resilient candidate discovery
        $folders_to_check = array();
        if ($search_current && !empty($currentMailbox)) {
            $cur_info = sqimap_mailbox_select($imapConnection, $currentMailbox, false);
            if (!empty($cur_info) && isset($cur_info['EXISTS'])) {
                $folders_to_check[$currentMailbox] = array('type' => 'received', 'info' => $cur_info);
            }
        }
        if ($search_sent) {
            $sent_candidates = array_unique(array_filter(array(
                $user_sent,
                $sent_folder,
                'INBOX.Sent',
                'Sent',
                'INBOX/Sent',
                'INBOX.Sent Items',
                'Sent Items',
            )));
            foreach ($sent_candidates as $sf) {
                if (!$sf) continue;
                $mbx_info = sqimap_mailbox_select($imapConnection, $sf, false);
                if (!empty($mbx_info) && isset($mbx_info['EXISTS'])) {
                    $folders_to_check[$sf] = array('type' => 'sent', 'info' => $mbx_info);
                    break;
                }
            }
        }
        if ($search_drafts) {
            $draft_candidates = array_unique(array_filter(array(
                $user_draft,
                $draft_folder,
                'INBOX.Drafts',
                'Drafts',
                'INBOX/Drafts',
            )));
            foreach ($draft_candidates as $df) {
                if (!$df) continue;
                $mbx_info = sqimap_mailbox_select($imapConnection, $df, false);
                if (!empty($mbx_info) && isset($mbx_info['EXISTS'])) {
                    $folders_to_check[$df] = array('type' => 'draft', 'info' => $mbx_info);
                    break;
                }
            }
        }

        $all_messages = array();

        // 3. Search and extract messages from each folder
        foreach ($folders_to_check as $folderName => $fMeta) {
            $defaultType = $fMeta['type'];
            $mbx_info = $fMeta['info'];
            if (empty($mbx_info) || !isset($mbx_info['EXISTS']) || $mbx_info['EXISTS'] == 0) {
                continue;
            }

            // Ensure this folder is selected on the IMAP stream
            $cur_sel = sqimap_mailbox_select($imapConnection, $folderName, false);
            if (empty($cur_sel) || !isset($cur_sel['EXISTS']) || $cur_sel['EXISTS'] == 0) {
                continue;
            }

            $found_uids = array();

            // 3a. Search by Message-IDs (References, In-Reply-To, Message-ID)
            if (!empty($search_ids)) {
                $top_ids = array_slice($search_ids, 0, 4);
                foreach ($top_ids as $sid) {
                    $clean_sid = trim($sid, "<> \t\n\r");
                    $clean_sid = trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/', '', $clean_sid));
                    if (strlen($clean_sid) < 3) {
                        continue;
                    }

                    $qRef = 'HEADER References "' . addcslashes($clean_sid, '"\\') . '"';
                    $resRef = cv_run_uid_search($imapConnection, $qRef);
                    if (!empty($resRef) && is_array($resRef)) {
                        foreach ($resRef as $u) {
                            if (is_numeric($u)) $found_uids[] = (int)$u;
                        }
                    }

                    $qRep = 'HEADER In-Reply-To "' . addcslashes($clean_sid, '"\\') . '"';
                    $resRep = cv_run_uid_search($imapConnection, $qRep);
                    if (!empty($resRep) && is_array($resRep)) {
                        foreach ($resRep as $u) {
                            if (is_numeric($u)) $found_uids[] = (int)$u;
                        }
                    }

                    $qMid = 'HEADER Message-ID "' . addcslashes($clean_sid, '"\\') . '"';
                    $resMid = cv_run_uid_search($imapConnection, $qMid);
                    if (!empty($resMid) && is_array($resMid)) {
                        foreach ($resMid as $u) {
                            if (is_numeric($u)) $found_uids[] = (int)$u;
                        }
                    }
                }
            }

            // 3b. Search by SquirrelMail reply flag (e.g. reply::$currentUid::INBOX) in drafts
            if ($defaultType === 'draft' && !empty($currentUid)) {
                $qFlag = 'HEADER X-SM-Flag-Reply "::' . addcslashes($currentUid, '"\\') . '::"';
                $resFlag = cv_run_uid_search($imapConnection, $qFlag);
                if (!empty($resFlag) && is_array($resFlag)) {
                    foreach ($resFlag as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            // 3c. Search by clean subject (always search in drafts and sent, or as fallback in current mailbox)
            if (($defaultType === 'draft' || $defaultType === 'sent' || empty($found_uids)) && mb_strlen($clean_subject, 'UTF-8') >= 3) {
                $safe_subj = trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/', '', $clean_subject));
                $qSubj = 'SUBJECT "' . addcslashes($safe_subj, '"\\') . '"';
                $resSubj = cv_run_uid_search($imapConnection, $qSubj);
                if (!empty($resSubj) && is_array($resSubj)) {
                    foreach ($resSubj as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            // 3d. In Drafts, if specific searches yielded nothing and draft count is small, inspect recent drafts
            if ($defaultType === 'draft' && empty($found_uids) && $cur_sel['EXISTS'] <= 35) {
                $resAllDrafts = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($resAllDrafts) && is_array($resAllDrafts)) {
                    foreach ($resAllDrafts as $u) {
                        if (is_numeric($u)) $found_uids[] = (int)$u;
                    }
                }
            }

            // Always guarantee the currently viewed message is included when searching current mailbox
            if ($folderName === $currentMailbox && !in_array((int)$currentUid, $found_uids)) {
                $found_uids[] = (int)$currentUid;
            }

            $found_uids = array_unique(array_filter($found_uids));
            if (empty($found_uids)) {
                continue;
            }

            // Limit to most recent 12 messages per folder
            if (count($found_uids) > 12) {
                $found_uids = array_slice($found_uids, -12);
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
                $is_current = ($folderName === $currentMailbox && $uid === (int)$currentUid);

                // Determine message type
                $type = $defaultType;
                if ($defaultType === 'draft' || stripos($folderName, 'draft') !== false) {
                    $type = 'draft';
                } elseif ($defaultType === 'sent' || stripos($folderName, 'sent') !== false) {
                    $type = 'sent';
                }

                // Verify relevance if matched only by loose subject search in draft/sent folder
                $has_id_link = false;
                if (!empty($search_ids)) {
                    $hdr_in_reply = !empty($hdr['in-reply-to']) ? cv_extract_ids($hdr['in-reply-to']) : array();
                    $hdr_refs = !empty($hdr['references']) ? cv_extract_ids($hdr['references']) : array();
                    $hdr_mid = !empty($hdr['message-id']) ? cv_extract_ids($hdr['message-id']) : array();
                    $all_hdr_ids = array_merge($hdr_in_reply, $hdr_refs, $hdr_mid);
                    if (!empty(array_intersect($search_ids, $all_hdr_ids))) {
                        $has_id_link = true;
                    }
                }
                if (!empty($hdr['x-sm-flag-reply']) && strpos($hdr['x-sm-flag-reply'], '::' . $currentUid . '::') !== false) {
                    $has_id_link = true;
                }
                if (!$has_id_link && ($type === 'draft' || $type === 'sent') && !empty($clean_subject)) {
                    $hdr_clean_subj = cv_clean_subject(!empty($hdr['subject']) ? $hdr['subject'] : '');
                    if (strcasecmp($hdr_clean_subj, $clean_subject) !== 0) {
                        continue;
                    }
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
                if ($is_current && !empty($GLOBALS['messagebody'])) {
                    $snippet = cv_clean_body_snippet($GLOBALS['messagebody']);
                } else {
                    // Lightweight text fetch from IMAP
                    $body_read = sqimap_run_command($imapConnection, "FETCH $uid (BODY.PEEK[TEXT]<0.400>)", false, $resp, $msg, true);
                    if (!empty($body_read) && is_array($body_read)) {
                        $rawBody = implode('', $body_read);
                        $snippet = cv_clean_body_snippet($rawBody);
                    }
                }

                // Attachment indicator
                $hasAttachment = false;
                if (!empty($hdr['content-type']) && strpos(strtolower($hdr['content-type']), 'multipart/mixed') !== false) {
                    $hasAttachment = true;
                }

                // URLs
                $baseUri = sqm_baseuri();
                $viewUrl = $baseUri . 'src/read_body.php?mailbox=' . urlencode($folderName) . '&passed_id=' . $uid . '&startMessage=1';
                $resumeUrl = ($type === 'draft')
                    ? $baseUri . 'src/compose.php?smaction_draft=1&passed_id=' . $uid . '&mailbox=' . urlencode($folderName)
                    : '';
                $replyUrl = $baseUri . 'src/compose.php?smaction_reply=1&passed_id=' . $uid . '&mailbox=' . urlencode($folderName);

                $all_messages[] = array(
                    'uid'            => $uid,
                    'mailbox'        => $folderName,
                    'type'           => $type,
                    'is_current'     => $is_current,
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

        // Sort chronologically (oldest to newest)
        usort($all_messages, function ($a, $b) {
            if ($a['timestamp'] === $b['timestamp']) {
                return ($a['uid'] <=> $b['uid']);
            }
            return ($a['timestamp'] <=> $b['timestamp']);
        });

        // Compute stats
        $stats = array(
            'total_count'    => count($all_messages),
            'sent_count'     => 0,
            'draft_count'    => 0,
            'received_count' => 0,
        );

        foreach ($all_messages as $m) {
            if ($m['type'] === 'sent') {
                $stats['sent_count']++;
            } elseif ($m['type'] === 'draft') {
                $stats['draft_count']++;
            } else {
                $stats['received_count']++;
            }
        }

        $result = array(
            'messages' => $all_messages,
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
            $selectedDraftsBox = '';
            foreach ($draft_candidates as $df) {
                if (!$df) continue;
                $mbx_info = sqimap_mailbox_select($imapConnection, $df, false);
                if (!empty($mbx_info) && isset($mbx_info['EXISTS']) && $mbx_info['EXISTS'] > 0) {
                    $selectedDraftsBox = $df;
                    break;
                }
            }

            if (!empty($selectedDraftsBox)) {
                $draftUids = cv_run_uid_search($imapConnection, 'ALL');
                if (!empty($draftUids) && is_array($draftUids)) {
                    if (count($draftUids) > 30) {
                        $draftUids = array_slice($draftUids, -30);
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
                                if (mb_strlen($cSubj, 'UTF-8') >= 3 && !isset($draftsBySubject[$cSubj])) {
                                    $draftsBySubject[$cSubj] = $dInfo;
                                }
                            }
                        }
                    }
                }
                // Restore current mailbox selection immediately
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
                $cleanSubj = cv_clean_subject(strip_tags($rawSubj));
                if (mb_strlen($cleanSubj, 'UTF-8') >= 3 && isset($draftsBySubject[$cleanSubj])) {
                    $matchedDraft = $draftsBySubject[$cleanSubj];
                }
            }

            if ($matchedDraft) {
                $resumeUrl = $baseUri . 'src/compose.php?smaction_draft=1&passed_id=' . $matchedDraft['uid'] . '&mailbox=' . urlencode($matchedDraft['mailbox']);
                $badges .= '<a href="' . htmlspecialchars($resumeUrl, ENT_QUOTES, 'UTF-8') . '" class="cv-mb-badge cv-mb-badge-draft" onclick="event.stopPropagation();" title="' . _("Resume pending draft response") . '">'
                        . '📝 ' . _("Draft") . '</a>';
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
