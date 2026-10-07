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
require_once(SM_PATH . 'functions/prefs.php');
require_once(SM_PATH . 'functions/mime.php');
require_once(SM_PATH . 'class/mime/Message.class.php');
require_once(SM_PATH . 'class/mime/MessageHeader.class.php');
require_once(SM_PATH . 'class/mime/AddressStructure.class.php');
require_once(SM_PATH . 'class/mime/Rfc822Header.class.php');
require_once(SM_PATH . 'class/mime/ContentType.class.php');
require_once(SM_PATH . 'class/deliver/Deliver.class.php');
require_once(SM_PATH . 'class/deliver/Deliver_IMAP.class.php');
if (file_exists(SM_PATH . 'plugins/message_labels/labels.php')) {
    require_once(SM_PATH . 'plugins/message_labels/labels.php');
}

// Load AI Agent config & Gemini Client
require_once(SM_PATH . 'plugins/ai_agent/config.php');
require_once(SM_PATH . 'plugins/ai_agent/gemini_client.php');
require_once(SM_PATH . 'plugins/ai_agent/credentials.php');

// Parse CLI arguments
$options = getopt('', ['user:', 'limit:', 'dry-run', 'help', 'pass:', 'password:', 'set-pass:', 'host:', 'port:']);
if (isset($options['help'])) {
    echo "SquirrelMail AI Agent Cron Automation (Gemini 3.8)\n";
    echo "Usage: php cron.php [options]\n";
    echo "  --user=<name>         Inspect specific user account (e.g. user@domain.com)\n";
    echo "  --password=<secret>   Specify IMAP password for this execution\n";
    echo "  --set-pass=<secret>   Securely save IMAP password for --user and exit\n";
    echo "  --host=<server>       Override IMAP server host\n";
    echo "  --port=<port>         Override IMAP server port\n";
    echo "  --limit=<n>           Maximum unseen messages to process (default 20)\n";
    echo "  --dry-run             Simulate analysis without moving or writing messages\n";
    exit(0);
}

$cli_user   = isset($options['user']) ? trim($options['user']) : '';
$cli_pass   = isset($options['password']) ? trim($options['password']) : (isset($options['pass']) ? trim($options['pass']) : '');
$scan_limit = isset($options['limit']) ? intval($options['limit']) : ($cron_scan_limit ?: 20);
$dry_run    = isset($options['dry-run']);

// Handle --set-pass command: save credentials from CLI and exit
if (!empty($options['set-pass'])) {
    if (empty($cli_user)) {
        echo "[ERROR] Please specify --user=<username> when saving password with --set-pass.\n";
        exit(1);
    }
    $targetPass = trim($options['set-pass']);
    $targetHost = !empty($options['host']) ? trim($options['host']) : $imapServerAddress;
    $targetPort = !empty($options['port']) ? intval($options['port']) : $imapPort;

    echo "Testing IMAP connection for '$cli_user' on $targetHost:$targetPort...\n";
    $test = ai_agent_test_imap_login($cli_user, $targetPass, $targetHost, $targetPort);
    if (!$test['success']) {
        echo "[WARNING] IMAP connection test failed: " . $test['message'] . "\n";
        echo "Saving credentials anyway...\n";
    } else {
        echo "[SUCCESS] " . $test['message'] . "\n";
    }

    ai_agent_save_account_credentials($cli_user, $targetPass, $targetHost, $targetPort);
    echo "[SUCCESS] Password for '$cli_user' securely encrypted and saved.\n";
    exit(0);
}

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

/**
 * Assign a category label badge to a message in the message_labels store
 */
function cron_assign_message_label($user, $mailbox, $uid, $category) {
    global $data_dir;
    if (!function_exists('ml_load_data')) {
        return;
    }
    $catMap = [
        'work'          => 'work',
        'finance'       => 'finance',
        'personal'      => 'personal',
        'urgent'        => 'important',
        'important'     => 'important',
        'newsletter'    => 'newsletter',
        'notifications' => 'notifications',
        'follow-up'     => 'followup',
        'followup'      => 'followup',
        'todo'          => 'todo',
    ];
    $norm = strtolower(trim($category));
    $lid = isset($catMap[$norm]) ? $catMap[$norm] : preg_replace('/[^a-z0-9_-]/', '', $norm);
    if (empty($lid)) $lid = 'work';

    $usersToUpdate = [$user];
    if (strpos($user, '@') !== false) {
        $base = substr($user, 0, strpos($user, '@'));
        $usersToUpdate[] = $base;
    }

    foreach (array_unique($usersToUpdate) as $u) {
        global $username;
        $username = $u;
        $data = ml_load_data();
        if (!isset($data['labels'][$lid])) {
            $colors = [
                'newsletter'    => ['color' => '#7c3aed', 'bg' => '#f5f3ff'],
                'notifications' => ['color' => '#4b5563', 'bg' => '#f3f4f6'],
                'work'          => ['color' => '#1a73e8', 'bg' => '#e8f0fe'],
                'finance'       => ['color' => '#0d9488', 'bg' => '#ccfbf1'],
                'personal'      => ['color' => '#1e8e3e', 'bg' => '#e6f4ea'],
                'important'     => ['color' => '#d93025', 'bg' => '#fce8e6'],
            ];
            $palette = isset($colors[$lid]) ? $colors[$lid] : ['color' => '#0284c7', 'bg' => '#e0f2fe'];
            $data['labels'][$lid] = [
                'id'    => $lid,
                'name'  => ucfirst($category),
                'color' => $palette['color'],
                'bg'    => $palette['bg']
            ];
        }

        $key = ml_get_message_key($mailbox, $uid);
        if (!isset($data['messages'][$key])) {
            $data['messages'][$key] = [];
        }
        if (!in_array($lid, $data['messages'][$key])) {
            $data['messages'][$key][] = $lid;
        }
        ml_save_data($data);
    }
}

cron_log("=== SquirrelMail AI Agent Cron Started (Gemini Model: $gemini_model) ===");
if ($dry_run) cron_log("[INFO] Running in DRY-RUN mode. No emails will be moved or modified.");

// Initialize Gemini Client
$gemini = new SquirrelMailGeminiClient();
if (empty($gemini->getApiKey())) {
    cron_log("[ERROR] Gemini API Key is missing! Please configure \$gemini_api_key in plugins/ai_agent/config.php or Options.");
    exit(1);
}

// Determine target accounts across all sources
$accounts_to_process = [];

// A. Explicit CLI user
if (!empty($cli_user)) {
    $accounts_to_process[] = [
        'username' => $cli_user,
        'password' => $cli_pass,
        'host'     => !empty($options['host']) ? trim($options['host']) : null,
        'port'     => !empty($options['port']) ? intval($options['port']) : null,
    ];
}

// B. Configured $cron_accounts from config.php or config_local.php
if (!empty($cron_accounts) && is_array($cron_accounts)) {
    foreach ($cron_accounts as $k => $v) {
        if (is_array($v)) {
            $u = !empty($v['username']) ? $v['username'] : (is_string($k) ? $k : '');
            if (!empty($u)) {
                $accounts_to_process[] = array_merge(['username' => $u], $v);
            }
        } elseif (is_string($v) && is_string($k)) {
            $accounts_to_process[] = ['username' => $k, 'password' => $v];
        }
    }
}

// C. Saved accounts from plugins/ai_agent/data/cron_accounts.json
$storeFile = SM_PATH . 'plugins/ai_agent/data/cron_accounts.json';
if (file_exists($storeFile) && is_readable($storeFile)) {
    $savedAccs = json_decode(@file_get_contents($storeFile), true);
    if (!empty($savedAccs) && is_array($savedAccs)) {
        foreach ($savedAccs as $u => $inf) {
            if (!empty($u) && is_array($inf)) {
                $accounts_to_process[] = [
                    'username' => $u,
                    'password' => !empty($inf['password']) ? ai_agent_decrypt($inf['password']) : '',
                    'host'     => $inf['host'] ?? null,
                    'port'     => $inf['port'] ?? null,
                ];
            }
        }
    }
}

// D. Discover accounts from active preference files
if (empty($accounts_to_process) || empty($cli_user)) {
    $pref_files = glob($data_dir . '*.pref');
    if (!empty($pref_files)) {
        foreach ($pref_files as $pf) {
            $u = basename($pf, '.pref');
            if ($u !== 'default' && $u !== 'default_pref') {
                $cred = ai_agent_get_account_credentials($u);
                if ($cred && !empty($cred['password'])) {
                    $accounts_to_process[] = $cred;
                } elseif (!empty($cli_user) && $cli_user === $u) {
                    $accounts_to_process[] = ['username' => $u];
                }
            }
        }
    }
}

// Deduplicate accounts by username
$unique_accounts = [];
foreach ($accounts_to_process as $acc) {
    if (empty($acc['username'])) continue;
    $u = $acc['username'];
    if (!isset($unique_accounts[$u]) || empty($unique_accounts[$u]['password'])) {
        $unique_accounts[$u] = $acc;
    }
}
$accounts_to_process = array_values($unique_accounts);

if (empty($accounts_to_process)) {
    cron_log("[NOTICE] No configured AI Agent accounts found.");
    cron_log("To monitor mailboxes, configure credentials in Options -> AI Agent, via 'php cron.php --user=<email> --set-pass=\"<pass>\"', or in \$cron_accounts.");
    exit(0);
}

// Process each account
foreach ($accounts_to_process as $acc) {
    $user = $acc['username'];
    if (!empty($cli_user) && $user !== $cli_user) continue;

    global $username;
    $username = $user;

    $host = !empty($acc['host']) ? $acc['host'] : $imapServerAddress;
    $port = !empty($acc['port']) ? $acc['port'] : $imapPort;
    $pass = !empty($acc['password']) ? $acc['password'] : '';

    if (!empty($cli_pass)) {
        $pass = $cli_pass;
    }

    // Resolve credentials if password not already in $acc
    if (empty($pass)) {
        $resolved = ai_agent_get_account_credentials($user);
        if ($resolved && !empty($resolved['password'])) {
            $pass = $resolved['password'];
            if (!empty($resolved['host'])) $host = $resolved['host'];
            if (!empty($resolved['port'])) $port = $resolved['port'];
        }
    }

    cron_log("--- Processing Account: $user ---");

    // If password not found, notify with concrete action steps
    if (empty($pass)) {
        cron_log("[SKIP] No IMAP password specified for account '$user'. Configure in Options -> AI Agent, via 'php cron.php --user=$user --set-pass=\"password\"', or in \$cron_accounts in config.php.");
        continue;
    }

    // Connect to IMAP
    global $imap_stream_options;
    $imap_stream = @sqimap_login($user, $pass, $host, $port, 3, $imap_stream_options, true);
    if (!is_resource($imap_stream) && !is_object($imap_stream)) {
        $err = is_string($imap_stream) ? $imap_stream : "Connection or authentication failed";
        cron_log("[ERROR] Failed to connect/authenticate to IMAP server for $user at $host:$port: $err");
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
            @file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));
            continue; // Skip further labeling/drafting on spam
        }

        // 2. AUTO-LABELING / CATEGORIZATION
        if ($cron_auto_label) {
            $stat_labeled++;
            cron_log("     [CATEGORY] Labeled as: $category");
            if (!$dry_run) {
                cron_assign_message_label($user, 'INBOX', $id, $category);
                if ($category === 'Urgent') {
                    @sqimap_toggle_flag($imap_stream, [$id], '\\Flagged', true, true);
                    cron_log("     [ACTION] Marked msg #$id with \\Flagged (Urgent).");
                }
                cron_log("     [ACTION] Stored label badge '$category' for msg #$id.");
            }
        }

        // 3. AUTO-DRAFT CREATION
        if ($cron_auto_draft && $needs_reply && !empty($suggested_reply)) {
            $stat_drafts++;
            cron_log("     [AUTO-DRAFT] Generating smart reply draft...");
            if (!$dry_run) {
                try {
                    // Resolve user draft target folder preference
                    $user_draft_folder = function_exists('getPref') ? getPref($data_dir, $user, 'draft_folder', '') : '';
                    $target_drafts = !empty($user_draft_folder) ? $user_draft_folder : (!empty($draft_folder) ? $draft_folder : 'Drafts');
                    if (!sqimap_mailbox_exists($imap_stream, $target_drafts)) {
                        if (sqimap_mailbox_exists($imap_stream, 'INBOX.Drafts')) {
                            $target_drafts = 'INBOX.Drafts';
                        } elseif (sqimap_mailbox_exists($imap_stream, 'Drafts')) {
                            $target_drafts = 'Drafts';
                        } else {
                            @sqimap_mailbox_create($imap_stream, $target_drafts, '');
                        }
                    }

                    if (sqimap_mailbox_exists($imap_stream, $target_drafts)) {
                        $draftMsg = new Message();
                        $rfcHeader = new Rfc822Header();
                        $rfcHeader->to = $rfcHeader->parseAddress($from, true);
                        $rfcHeader->from = $rfcHeader->parseAddress($user, true);
                        $cleanSubj = preg_replace('/^(Re:\s*)+/i', '', $subject);
                        $rfcHeader->subject = 'Re: ' . $cleanSubj;
                        $rfcHeader->date = time();
                        $rfcHeader->content_type = new ContentType('text/plain');
                        $rfcHeader->content_type->properties['charset'] = 'utf-8';
                        $rfcHeader->encoding = '8bit';
                        $draftMsg->rfc822_header = $rfcHeader;
                        $draftMsg->body_part = $suggested_reply . "\r\n\r\n-- \r\n[Auto-drafted by Gemini 3.8 AI Assistant. Review before sending.]\r\n";

                        $deliver = new Deliver_IMAP();
                        $deliver->mail($draftMsg, $imap_stream, 0, 0, $imap_stream, $target_drafts);
                        cron_log("     [ACTION] Saved draft response into $target_drafts.");
                    } else {
                        cron_log("     [WARNING] Could not locate or create drafts folder '$target_drafts'.");
                    }
                } catch (\Throwable $e) {
                    cron_log("     [ERROR] Failed to save draft for msg #$id: " . $e->getMessage());
                }
            }
        }

        // Mark this UID as processed and immediately persist state
        $state[$user][] = $id;
        @file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));
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
