<?php
/**
 * AI Agent Server-Side Cronjob Script
 *
 * Automates email processing on the server:
 * 1. AI Spam & Phishing filtering (moves scam/spam to Trash or Junk)
 * 2. AI Email Categorization & Auto-labeling (Work, Finance, Urgent, Newsletter)
 * 3. AI Auto-Draft Generation (crafts intelligent reply drafts in Drafts folder)
 *
 * Usage via CLI crontab:
 *   * /5 * * * * php /path/to/squirrelmail/plugins/ai_agent/cron.php >> /path/to/squirrelmail/plugins/ai_agent/data/cron.log 2>&1
 *
 * Options:
 *   --user=username      Process only specific user
 *   --limit=10           Limit number of messages to process
 *   --dry-run            Analyze without moving or writing to IMAP
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

// Define environment
if (!defined('SM_PATH')) {
    define('SM_PATH', dirname(dirname(dirname(__FILE__))) . '/');
}

// Ensure error reporting for CLI
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '1');

// Load core SquirrelMail configuration and functions
require_once(SM_PATH . 'include/constants.php');
require_once(SM_PATH . 'functions/global.php');
if (file_exists(SM_PATH . 'config/config.php')) {
    require_once(SM_PATH . 'config/config.php');
} else {
    require_once(SM_PATH . 'config/config_default.php');
}
require_once(SM_PATH . 'functions/strings.php');
require_once(SM_PATH . 'include/languages.php');
require_once(SM_PATH . 'functions/imap_general.php');
require_once(SM_PATH . 'functions/imap_mailbox.php');
require_once(SM_PATH . 'functions/imap_messages.php');
require_once(SM_PATH . 'functions/file_prefs.php');
require_once(SM_PATH . 'class/mime/Message.class.php');
require_once(SM_PATH . 'class/mime/MessageHeader.class.php');
require_once(SM_PATH . 'class/mime/ContentType.class.php');
require_once(SM_PATH . 'class/deliver/Deliver.class.php');
require_once(SM_PATH . 'class/deliver/Deliver_IMAP.class.php');

// Load AI Agent config & Gemini Client
require_once(SM_PATH . 'plugins/ai_agent/config.php');
require_once(SM_PATH . 'plugins/ai_agent/gemini_client.php');

// Parse CLI arguments
$options = getopt('', ['user:', 'limit:', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo "SquirrelMail AI Agent Cron Automation (Gemini 3.8)\n";
    echo "Usage: php cron.php [options]\n";
    echo "  --user=<name>    Inspect specific user account\n";
    echo "  --limit=<n>      Maximum unseen messages to process (default 20)\n";
    echo "  --dry-run        Simulate analysis without moving or writing messages\n";
    exit(0);
}

$cli_user   = isset($options['user']) ? trim($options['user']) : '';
$scan_limit = isset($options['limit']) ? intval($options['limit']) : ($cron_scan_limit ?: 20);
$dry_run    = isset($options['dry-run']);

// Prepare data directory for state and logs
$data_folder = SM_PATH . 'plugins/ai_agent/data/';
if (!is_dir($data_folder)) {
    @mkdir($data_folder, 0755, true);
}

$state_file = $data_folder . 'state.json';
$state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
if (!is_array($state)) $state = [];

function cron_log($msg) {
    global $data_folder;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    @file_put_contents($data_folder . 'cron.log', $line, FILE_APPEND);
}

cron_log("=== SquirrelMail AI Agent Cron Started (Gemini Model: $gemini_model) ===");
if ($dry_run) cron_log("[INFO] Running in DRY-RUN mode. No emails will be moved or modified.");

// Initialize Gemini Client
$gemini = new SquirrelMailGeminiClient();
if (empty($gemini->getApiKey())) {
    cron_log("[ERROR] Gemini API Key is missing! Please configure \$gemini_api_key in plugins/ai_agent/config.php or Options.");
    exit(1);
}

// Determine target accounts
$accounts_to_process = [];
if (!empty($cron_accounts) && is_array($cron_accounts)) {
    $accounts_to_process = $cron_accounts;
} else {
    // If no explicit accounts configured, check data_dir for existing users
    if (!empty($cli_user)) {
        $accounts_to_process[] = ['username' => $cli_user];
    } else {
        $pref_files = glob($data_dir . '*.pref');
        if (!empty($pref_files)) {
            foreach ($pref_files as $pf) {
                $u = basename($pf, '.pref');
                if ($u !== 'default' && $u !== 'default_pref') {
                    $accounts_to_process[] = ['username' => $u];
                }
            }
        }
    }
}

if (empty($accounts_to_process)) {
    cron_log("[NOTICE] No accounts configured in \$cron_accounts and no active users found in $data_dir.");
    cron_log("To monitor specific mailboxes, add credentials to \$cron_accounts in plugins/ai_agent/config.php.");
    exit(0);
}

// Process each account
foreach ($accounts_to_process as $acc) {
    $user = $acc['username'];
    if (!empty($cli_user) && $user !== $cli_user) continue;

    cron_log("--- Processing Account: $user ---");

    $host = !empty($acc['host']) ? $acc['host'] : $imapServerAddress;
    $port = !empty($acc['port']) ? $acc['port'] : $imapPort;
    $pass = !empty($acc['password']) ? $acc['password'] : '';

    // If password not stored in config, note requirement
    if (empty($pass)) {
        cron_log("[SKIP] No IMAP password specified for account '$user'. Configure \$cron_accounts in config.php with host/user/pass.");
        continue;
    }

    // Connect to IMAP
    global $imap_stream_options;
    $imap_stream = @sqimap_login($user, $pass, $host, $port, 0, $imap_stream_options);
    if (!$imap_stream) {
        cron_log("[ERROR] Failed to connect/authenticate to IMAP server for $user at $host:$port.");
        continue;
    }

    // Select INBOX
    $inbox_info = sqimap_mailbox_select($imap_stream, 'INBOX');
    if (!$inbox_info) {
        cron_log("[ERROR] Unable to select INBOX for $user.");
        sqimap_logout($imap_stream);
        continue;
    }

    // Search for UNSEEN messages
    $unseen_ids = sqimap_run_command($imap_stream, 'SEARCH UNSEEN', true, $response, $message, true);
    $msg_ids = [];
    if (!empty($unseen_ids)) {
        foreach ($unseen_ids as $line) {
            if (preg_match('/^\*\s+SEARCH\s+(.*)$/i', $line, $m)) {
                $parts = preg_split('/\s+/', trim($m[1]));
                foreach ($parts as $p) {
                    if (is_numeric($p) && intval($p) > 0) $msg_ids[] = intval($p);
                }
            }
        }
    }

    if (empty($msg_ids)) {
        cron_log("[INFO] No new/unseen messages in INBOX for $user.");
        sqimap_logout($imap_stream);
        continue;
    }

    // Limit batch
    if (count($msg_ids) > $scan_limit) {
        $msg_ids = array_slice($msg_ids, -$scan_limit);
    }

    cron_log("[INFO] Found " . count($msg_ids) . " unseen messages to inspect.");

    if (!isset($state[$user])) $state[$user] = [];

    $stat_scam = 0;
    $stat_labeled = 0;
    $stat_drafts = 0;

    foreach ($msg_ids as $id) {
        // Check if already processed
        if (in_array($id, $state[$user])) {
            continue;
        }

        // Fetch message header and body
        $header = sqimap_get_small_header($imap_stream, $id, false);
        $subject = isset($header->subject) ? $header->subject : '(No Subject)';
        $from = isset($header->from) ? $header->from : 'Unknown';

        // Fetch snippet of body
        $body_part = sqimap_run_command($imap_stream, "FETCH $id (BODY.PEEK[TEXT]<0.4000>)", true, $resp, $msg, true);
        $body_text = is_array($body_part) ? implode("\n", $body_part) : '';

        cron_log("  -> Analyzing msg #$id: '$subject' from $from");

        // Run Gemini 3.8 analysis
        $ai_res = $gemini->analyzeForServerCron($from, $subject, $body_text);

        if (!$ai_res['success'] || empty($ai_res['data'])) {
            cron_log("     [AI ERROR] " . ($ai_res['error'] ?? 'Classification failed'));
            continue;
        }

        $d = $ai_res['data'];
        $is_spam = !empty($d['is_spam']) && ($d['spam_score'] >= $cron_spam_threshold);
        $category = !empty($d['category']) ? $d['category'] : 'General';
        $needs_reply = !empty($d['needs_reply']);
        $suggested_reply = !empty($d['suggested_reply']) ? $d['suggested_reply'] : '';

        // 1. SPAM / PHISHING FILTER
        if ($is_spam && $cron_spam_filter) {
            $stat_scam++;
            cron_log("     [SPAM DETECTED] Score: {$d['spam_score']}/100, Reason: {$d['spam_reason']}");
            if (!$dry_run) {
                $target_folder = ($cron_spam_action === 'junk') ? 'Junk' : 'Trash';
                if (!sqimap_mailbox_exists($imap_stream, $target_folder)) {
                    $target_folder = 'INBOX.Trash';
                }
                if (sqimap_mailbox_exists($imap_stream, $target_folder)) {
                    sqimap_msgs_list_move($imap_stream, $id, $target_folder);
                    cron_log("     [ACTION] Moved msg #$id to $target_folder.");
                } else {
                    sqimap_toggle_flag($imap_stream, [$id], '\\Flagged', true, true);
                    cron_log("     [ACTION] Flagged msg #$id as Spam.");
                }
            }
            $state[$user][] = $id;
            continue; // Skip further labeling/drafting on spam
        }

        // 2. AUTO-LABELING / CATEGORIZATION
        if ($cron_auto_label) {
            $stat_labeled++;
            cron_log("     [CATEGORY] Labeled as: $category");
            if (!$dry_run) {
                if ($category === 'Urgent') {
                    sqimap_toggle_flag($imap_stream, [$id], '\\Flagged', true, true);
                    cron_log("     [ACTION] Marked msg #$id with \\Flagged (Urgent).");
                }
            }
        }

        // 3. AUTO-DRAFT CREATION
        if ($cron_auto_draft && $needs_reply && !empty($suggested_reply)) {
            $stat_drafts++;
            cron_log("     [AUTO-DRAFT] Generating smart reply draft...");
            if (!$dry_run) {
                // Draft target folder
                $target_drafts = !empty($draft_folder) ? $draft_folder : 'Drafts';
                if (!sqimap_mailbox_exists($imap_stream, $target_drafts)) {
                    $target_drafts = 'INBOX.Drafts';
                }

                if (sqimap_mailbox_exists($imap_stream, $target_drafts)) {
                    $draftMsg = new Message();
                    $rfcHeader = new MessageHeader();
                    $rfcHeader->to = $from;
                    $rfcHeader->from = $user;
                    $rfcHeader->subject = 'Re: ' . preg_replace('/^(Re:\s*)+/i', '', $subject);
                    $rfcHeader->date = time();
                    $rfcHeader->content_type = new ContentType('text/plain');
                    $draftMsg->rfc822_header = $rfcHeader;
                    $draftMsg->body_part = $suggested_reply . "\n\n-- \n[Auto-drafted by Gemini 3.8 AI Assistant. Review before sending.]";

                    $deliver = new Deliver_IMAP();
                    $deliver->mail($draftMsg, $imap_stream, '', '', $imap_stream, $target_drafts);
                    cron_log("     [ACTION] Saved draft response into $target_drafts.");
                }
            }
        }

        // Mark this UID as processed
        $state[$user][] = $id;
    }

    // Keep state file bounded
    if (count($state[$user]) > 1000) {
        $state[$user] = array_slice($state[$user], -500);
    }

    sqimap_mailbox_expunge($imap_stream, 'INBOX');
    sqimap_logout($imap_stream);

    cron_log("Summary for $user: $stat_scam Spam filtered, $stat_labeled Categorized, $stat_drafts Drafts created.");
}

// Save updated state
@file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));
cron_log("=== SquirrelMail AI Agent Cron Finished Successfully ===");
