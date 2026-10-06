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

    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
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
function sb_learn_spam($sender, $subject, $body = '', $headers = array())
{
    $data = sb_load_training_data();
    $data['spam_count']++;

    $senderClean = strtolower(trim($sender));
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
            if (!empty($domain) && !in_array($domain, $data['blacklist'])) {
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

    // AI Agent Integration (if Gemini is configured in plugins/ai_agent)
    sb_trigger_ai_learning('spam', $senderClean, $subject, $body, $data);

    sb_save_training_data($data);
    return true;
}

/**
 * Learn from NOT SPAM (HAM) feedback
 */
function sb_learn_ham($sender, $subject, $body = '')
{
    $data = sb_load_training_data();
    $data['ham_count']++;

    $senderClean = strtolower(trim($sender));
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
 * Trigger AI Agent Gemini learning when available
 */
function sb_trigger_ai_learning($type, $sender, $subject, $body, &$data)
{
    $geminiClientFile = SM_PATH . 'plugins/ai_agent/gemini_client.php';
    if (!file_exists($geminiClientFile)) return;

    include_once($geminiClientFile);
    if (!function_exists('ai_gemini_is_configured') || !ai_gemini_is_configured()) return;

    // Build pattern summary for AI model
    if ($type === 'spam') {
        $prompt = "A user just marked the following email as SPAM:\n"
                . "From: $sender\n"
                . "Subject: $subject\n"
                . "Body: " . substr($body, 0, 500) . "\n\n"
                . "Identify 1 to 3 specific rule indicators (e.g. sender pattern, suspicious urgency keyword, spoofing signal) for email filtering. "
                . "Respond with a single concise sentence summarizing the learned heuristic.";

        $res = ai_gemini_generate_text($prompt);
        if (!empty($res) && !empty($res['text'])) {
            $rule = trim($res['text']);
            if (!in_array($rule, $data['learned_rules'])) {
                $data['learned_rules'][] = $rule;
                $data['learned_rules'] = array_slice($data['learned_rules'], -20);
            }
        }
    }
}
