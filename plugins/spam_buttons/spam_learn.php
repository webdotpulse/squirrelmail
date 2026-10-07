<?php
/**
 * Spam Buttons & AI Learning Engine
 *
 * Trains the Gemini AI model and local bayesian/rule engine on user spam/ham feedback.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage spam_buttons
 */

if (!function_exists('getHashedFile') && defined('SM_PATH')) {
    include_once(SM_PATH . 'functions/prefs.php');
}

/**
 * Get path to spam learning dataset
 */
function sb_get_learning_file()
{
    global $username, $data_dir;
    return getHashedFile($username, $data_dir, "$username.ai_spam_training.json");
}

/**
 * Clean, decode HTML entities and extract pure email address or domain
 *
 * Prevents corrupted HTML entity strings like:
 * "finance&#32;xplained&#32;academy&#32;&lt;noreply@mailing.financeexplained.be&gt;"
 * and normalizes to:
 * "noreply@mailing.financeexplained.be"
 */
function sb_clean_email_address($sender)
{
    if (empty($sender) || !is_string($sender)) {
        return '';
    }

    // Decode HTML entities (both named & numeric like &#32; &lt; &gt; &amp;)
    $str = html_entity_decode($sender, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $str = str_replace(array('&#32;', '&#160;', '&nbsp;', "\xc2\xa0"), ' ', $str);

    // Look for email inside angle brackets: <user@example.com>
    if (preg_match('/<([^>]+)>/', $str, $matches)) {
        $cand = trim($matches[1]);
        if (filter_var($cand, FILTER_VALIDATE_EMAIL)) {
            return strtolower($cand);
        }
    }

    // Match standard email pattern in string
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $str, $matches)) {
        return strtolower($matches[0]);
    }

    // Match domain-only pattern: @example.com or example.com
    $trimmed = trim($str);
    if (preg_match('/^@?([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})$/', $trimmed, $matches)) {
        return '@' . strtolower($matches[1]);
    }

    // Fallback: strip tags and trim
    return strtolower(trim(strip_tags($str)));
}

/**
 * Load training data
 */
function sb_load_training_data()
{
    $file = sb_get_learning_file();
    $data = array(
        'spam_count'    => 0,
        'ham_count'     => 0,
        'blacklist'     => array(), // sender domains and addresses
        'whitelist'     => array(), // sender domains and addresses
        'spam_keywords' => array(), // word => frequency
        'learned_rules' => array(), // AI extracted heuristics
        'recent_samples'=> array()
    );

    $dirtyFound = false;
    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }

        // Automatically clean and normalize any legacy/strange entries
        if (!empty($data['blacklist']) && is_array($data['blacklist'])) {
            $cleanBlacklist = array();
            foreach ($data['blacklist'] as $b) {
                $c = sb_clean_email_address($b);
                if (!empty($c)) {
                    if ($c !== $b) $dirtyFound = true;
                    $cleanBlacklist[] = $c;
                }
            }
            $data['blacklist'] = array_values(array_unique($cleanBlacklist));
        }

        if (!empty($data['whitelist']) && is_array($data['whitelist'])) {
            $cleanWhitelist = array();
            foreach ($data['whitelist'] as $w) {
                $c = sb_clean_email_address($w);
                if (!empty($c)) {
                    if ($c !== $w) $dirtyFound = true;
                    $cleanWhitelist[] = $c;
                }
            }
            $data['whitelist'] = array_values(array_unique($cleanWhitelist));
        }

        if ($dirtyFound) {
            sb_save_training_data($data);
        }
    }
    return $data;
}

/**
 * Save training data
 */
function sb_save_training_data($data)
{
    $file = sb_get_learning_file();
    return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Learn from SPAM feedback
 */
function sb_learn_spam($sender, $subject, $body = '', $headers = array(), $triggerAi = true, $saveImmediately = true)
{
    $data = sb_load_training_data();
    $data['spam_count']++;

    $senderClean = sb_clean_email_address($sender);
    if (!empty($senderClean)) {
        if (!in_array($senderClean, $data['blacklist'])) {
            $data['blacklist'][] = $senderClean;
        }
        // Remove from whitelist if previously there
        $wIdx = array_search($senderClean, $data['whitelist']);
        if ($wIdx !== false) {
            unset($data['whitelist'][$wIdx]);
            $data['whitelist'] = array_values($data['whitelist']);
        }

        // Domain blacklist tracking
        if (strpos($senderClean, '@') !== false) {
            $domain = substr(strrchr($senderClean, '@'), 1);
            if (!empty($domain) && !in_array('@' . $domain, $data['blacklist'])) {
                // If more than 2 spam emails from this domain, blacklist entire domain
                $domainCount = 0;
                foreach ($data['recent_samples'] as $s) {
                    if (isset($s['sender']) && strpos(strtolower($s['sender']), '@' . $domain) !== false) {
                        $domainCount++;
                    }
                }
                if ($domainCount >= 2) {
                    $data['blacklist'][] = '@' . $domain;
                }
            }
        }
    }

    // Extract keywords
    $text = $subject . ' ' . substr($body, 0, 1000);
    $words = preg_split('/[^a-zA-Z0-9_\-]+/', strtolower($text));
    $stopWords = array('the','and','for','you','that','this','with','have','from','are','was','will','your','about');
    foreach ($words as $w) {
        $w = trim($w);
        if (strlen($w) >= 4 && !in_array($w, $stopWords)) {
            $data['spam_keywords'][$w] = isset($data['spam_keywords'][$w]) ? $data['spam_keywords'][$w] + 1 : 1;
        }
    }

    // Record sample
    $sample = array(
        'type'      => 'spam',
        'sender'    => $senderClean,
        'subject'   => substr($subject, 0, 120),
        'timestamp' => time()
    );
    array_unshift($data['recent_samples'], $sample);
    $data['recent_samples'] = array_slice($data['recent_samples'], 0, 50);

    if ($triggerAi) {
        // AI Agent Integration (if Gemini is configured in plugins/ai_agent)
        sb_trigger_ai_learning('spam', $senderClean, $subject, $body, $data);
    }

    if ($saveImmediately) {
        sb_save_training_data($data);
    }
    return true;
}

/**
 * Learn from NOT SPAM (HAM) feedback
 */
function sb_learn_ham($sender, $subject, $body = '')
{
    $data = sb_load_training_data();
    $data['ham_count']++;

    $senderClean = sb_clean_email_address($sender);
    if (!empty($senderClean)) {
        if (!in_array($senderClean, $data['whitelist'])) {
            $data['whitelist'][] = $senderClean;
        }
        // Remove from blacklist if present
        $bIdx = array_search($senderClean, $data['blacklist']);
        if ($bIdx !== false) {
            unset($data['blacklist'][$bIdx]);
            $data['blacklist'] = array_values($data['blacklist']);
        }
    }

    // Record sample
    $sample = array(
        'type'      => 'ham',
        'sender'    => $senderClean,
        'subject'   => substr($subject, 0, 120),
        'timestamp' => time()
    );
    array_unshift($data['recent_samples'], $sample);
    $data['recent_samples'] = array_slice($data['recent_samples'], 0, 50);

    sb_trigger_ai_learning('ham', $senderClean, $subject, $body, $data);

    sb_save_training_data($data);
    return true;
}

/**
 * Learn from all emails already in the Junk/Spam folder
 *
 * @param int $limit Max messages to scan (default 100)
 * @return array Result summary [success, mailbox, total, scanned, new_senders, new_keywords, message]
 */
function sb_learn_from_junk_folder($limit = 100)
{
    global $username, $imapServerAddress, $imapPort, $imap_stream_options;

    require_once(SM_PATH . 'functions/imap_general.php');
    require_once(SM_PATH . 'functions/imap_mailbox.php');
    require_once(SM_PATH . 'functions/imap_messages.php');
    require_once(SM_PATH . 'functions/mime.php');

    $imapConnection = sqimap_login($username, false, $imapServerAddress, $imapPort, 3, $imap_stream_options);
    if (!is_resource($imapConnection) && !is_object($imapConnection)) {
        return array(
            'success' => false,
            'error'   => _("Could not connect to IMAP server.")
        );
    }

    $boxes = sqimap_mailbox_list($imapConnection);
    $boxNames = array();
    if (is_array($boxes)) {
        foreach ($boxes as $b) {
            if (isset($b['unformatted'])) {
                $boxNames[] = $b['unformatted'];
            }
        }
    }

    $junkCandidates = array('Junk', 'Spam', 'INBOX.Junk', 'INBOX.Spam', 'INBOX/Junk', 'INBOX/Spam', 'Junk E-mail', 'INBOX.Junk E-mail');
    $junkBox = '';
    foreach ($junkCandidates as $cand) {
        if (in_array($cand, $boxNames)) {
            $junkBox = $cand;
            break;
        }
    }
    if (empty($junkBox)) {
        foreach ($boxNames as $cand) {
            if (preg_match('/(junk|spam)/i', $cand)) {
                $junkBox = $cand;
                break;
            }
        }
    }

    if (empty($junkBox)) {
        sqimap_logout($imapConnection);
        return array(
            'success' => false,
            'error'   => _("No Junk or Spam folder could be found in your mailbox.")
        );
    }

    $mbxInfo = sqimap_mailbox_select($imapConnection, $junkBox);
    $totalMessages = isset($mbxInfo['EXISTS']) ? intval($mbxInfo['EXISTS']) : 0;

    if ($totalMessages <= 0) {
        sqimap_logout($imapConnection);
        return array(
            'success'      => true,
            'mailbox'      => $junkBox,
            'total'        => 0,
            'scanned'      => 0,
            'new_senders'  => 0,
            'new_keywords' => 0,
            'message'      => sprintf(_("Your '%s' folder is empty (0 messages found)."), htmlspecialchars($junkBox))
        );
    }

    $start = max(1, $totalMessages - $limit + 1);
    $msgList = range($start, $totalMessages);

    $headers = sqimap_get_small_header_list($imapConnection, $msgList, array('From', 'Subject', 'Date'));

    $data = sb_load_training_data();
    $initialBlacklistCount = count($data['blacklist']);
    $initialKeywordCount = count($data['spam_keywords']);

    $scannedCount = 0;
    $stopWords = array('the','and','for','you','that','this','with','have','from','are','was','will','your','about','with','what','when','where');
    $sampleSubjects = array();

    if (is_array($headers)) {
        foreach ($headers as $hdr) {
            $rawFrom = '';
            $rawSubj = '';
            if (is_object($hdr)) {
                $rawFrom = !empty($hdr->from) ? $hdr->from : '';
                $rawSubj = !empty($hdr->subject) ? $hdr->subject : '';
            } elseif (is_array($hdr)) {
                $rawFrom = !empty($hdr['from']) ? $hdr['from'] : (!empty($hdr['From']) ? $hdr['From'] : '');
                $rawSubj = !empty($hdr['subject']) ? $hdr['subject'] : (!empty($hdr['Subject']) ? $hdr['Subject'] : '');
            }

            $senderDecoded = decodeHeader($rawFrom, true, false);
            $cleanSender = sb_clean_email_address($senderDecoded);
            $subjDecoded = decodeHeader($rawSubj, true, false);

            if (!empty($cleanSender) || !empty($subjDecoded)) {
                $data['spam_count']++;
                $scannedCount++;

                // Sender Blacklist
                if (!empty($cleanSender)) {
                    if (!in_array($cleanSender, $data['blacklist'])) {
                        $data['blacklist'][] = $cleanSender;
                    }
                    $wIdx = array_search($cleanSender, $data['whitelist']);
                    if ($wIdx !== false) {
                        unset($data['whitelist'][$wIdx]);
                        $data['whitelist'] = array_values($data['whitelist']);
                    }

                    if (strpos($cleanSender, '@') !== false) {
                        $domain = substr(strrchr($cleanSender, '@'), 1);
                        if (!empty($domain) && !in_array('@' . $domain, $data['blacklist'])) {
                            $data['blacklist'][] = '@' . $domain;
                        }
                    }
                }

                // Keywords from subject
                if (!empty($subjDecoded)) {
                    $words = preg_split('/[^a-zA-Z0-9_\-]+/', strtolower($subjDecoded));
                    foreach ($words as $w) {
                        $w = trim($w);
                        if (strlen($w) >= 4 && !in_array($w, $stopWords)) {
                            $data['spam_keywords'][$w] = isset($data['spam_keywords'][$w]) ? $data['spam_keywords'][$w] + 1 : 1;
                        }
                    }
                    if (count($sampleSubjects) < 10) {
                        $sampleSubjects[] = "From: $cleanSender | Subject: $subjDecoded";
                    }
                }

                // Add sample
                $sample = array(
                    'type'      => 'spam',
                    'sender'    => $cleanSender,
                    'subject'   => substr($subjDecoded, 0, 120),
                    'timestamp' => time()
                );
                array_unshift($data['recent_samples'], $sample);
            }
        }
    }

    $data['recent_samples'] = array_slice($data['recent_samples'], 0, 50);

    // AI heuristic trigger once for the batch
    if (!empty($sampleSubjects) && function_exists('ai_gemini_is_configured') && ai_gemini_is_configured()) {
        try {
            $prompt = "The user has batch trained their spam filter on recent emails in their Junk/Spam folder:\n"
                    . implode("\n", $sampleSubjects) . "\n\n"
                    . "Identify 1 to 2 specific rule indicators or patterns for email spam filtering. Respond with 1 concise sentence summarizing the learned heuristic.";
            $res = ai_gemini_generate_text($prompt);
            if (!empty($res['success']) && !empty($res['text'])) {
                $rule = trim($res['text']);
                if (!in_array($rule, $data['learned_rules'])) {
                    $data['learned_rules'][] = $rule;
                    $data['learned_rules'] = array_slice($data['learned_rules'], -20);
                }
            }
        } catch (\Throwable $e) {}
    }

    sb_save_training_data($data);
    sqimap_logout($imapConnection);

    $newSenders = count($data['blacklist']) - $initialBlacklistCount;
    $newKeywords = count($data['spam_keywords']) - $initialKeywordCount;

    return array(
        'success'      => true,
        'mailbox'      => $junkBox,
        'total'        => $totalMessages,
        'scanned'      => $scannedCount,
        'new_senders'  => max(0, $newSenders),
        'new_keywords' => max(0, $newKeywords),
        'message'      => sprintf(_("Successfully analyzed and learned from %d email(s) in '%s'. Added %d new spam sender(s)/domain(s) to blacklist and learned %d new spam keyword tokens."), $scannedCount, htmlspecialchars($junkBox), max(0, $newSenders), max(0, $newKeywords))
    );
}

/**
 * Trigger AI Agent Gemini learning when available
 */
function sb_trigger_ai_learning($type, $sender, $subject, $body, &$data)
{
    static $aiLearningTriggered = false;
    if ($aiLearningTriggered) return;

    $geminiClientFile = defined('SM_PATH') ? (SM_PATH . 'plugins/ai_agent/gemini_client.php') : (__DIR__ . '/../ai_agent/gemini_client.php');
    if (!file_exists($geminiClientFile)) return;

    include_once($geminiClientFile);
    if (!class_exists('SquirrelMailGeminiClient')) return;

    try {
        $client = new SquirrelMailGeminiClient();
        if (empty($client->getApiKey())) return;

        // Build pattern summary for AI model
        if ($type === 'spam' && (!empty($sender) || !empty($subject))) {
            $prompt = "A user just marked the following email as SPAM:\n"
                    . "From: $sender\n"
                    . "Subject: $subject\n"
                    . "Body: " . substr((string)$body, 0, 500) . "\n\n"
                    . "Identify 1 to 3 specific rule indicators (e.g. sender pattern, suspicious urgency keyword, spoofing signal) for email filtering. "
                    . "Respond with a single concise sentence summarizing the learned heuristic.";

            $res = $client->callGemini($prompt);
            if (!empty($res) && !empty($res['success']) && !empty($res['text'])) {
                $rule = trim($res['text']);
                if (!in_array($rule, $data['learned_rules'])) {
                    $data['learned_rules'][] = $rule;
                    $data['learned_rules'] = array_slice($data['learned_rules'], -20);
                }
            }
            $aiLearningTriggered = true;
        }
    } catch (\Throwable $e) {
        // Silently continue if AI heuristic generation fails
    }
}

if (!function_exists('ai_gemini_is_configured')) {
    function ai_gemini_is_configured() {
        if (!class_exists('SquirrelMailGeminiClient')) {
            $f = (defined('SM_PATH') ? SM_PATH : '') . 'plugins/ai_agent/gemini_client.php';
            if (file_exists($f)) include_once($f);
        }
        if (class_exists('SquirrelMailGeminiClient')) {
            $c = new SquirrelMailGeminiClient();
            $k = $c->getApiKey();
            return !empty($k);
        }
        return false;
    }
}

if (!function_exists('ai_gemini_generate_text')) {
    function ai_gemini_generate_text($prompt) {
        if (!class_exists('SquirrelMailGeminiClient')) {
            $f = (defined('SM_PATH') ? SM_PATH : '') . 'plugins/ai_agent/gemini_client.php';
            if (file_exists($f)) include_once($f);
        }
        if (class_exists('SquirrelMailGeminiClient')) {
            $c = new SquirrelMailGeminiClient();
            return $c->callGemini($prompt);
        }
        return array('success' => false, 'error' => 'Gemini client not available');
    }
}
