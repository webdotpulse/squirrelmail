<?php
/**
 * AI Agent & Assistant Plugin - Configuration
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

global $gemini_api_key, $gemini_model,
       $ai_enable_compose, $ai_enable_reply, $ai_enable_summarize,
       $ai_enable_translate, $ai_enable_scam_check,
       $cron_enabled, $cron_scan_limit, $cron_spam_filter,
       $cron_spam_action, $cron_spam_threshold, $cron_auto_label,
       $cron_auto_draft, $cron_accounts;

// 1. Google Gemini API Credentials
// Get your API key from Google AI Studio: https://aistudio.google.com/
// Can also be set in environment: export GEMINI_API_KEY="your-key-here"
$gemini_api_key = getenv('GEMINI_API_KEY') ?: '';

// 2. Gemini Model Selection
// Supported models: 'gemini-3.8-flash' (recommended), 'gemini-3.8-pro', 'gemini-2.5-flash'
$gemini_model = 'gemini-3.8-flash';

// Load persistent local configuration if exists
if (file_exists(__DIR__ . '/config_local.php')) {
    include(__DIR__ . '/config_local.php');
}

// 3. Web UI Feature Toggles
$ai_enable_compose     = true;
$ai_enable_reply       = true;
$ai_enable_summarize   = true;
$ai_enable_translate   = true;
$ai_enable_scam_check  = true;

// 4. Server-Side Background Cron Automation
// Configure cron job to execute:
// */5 * * * * php /path/to/squirrelmail/plugins/ai_agent/cron.php >> /path/to/squirrelmail/data/ai_cron.log 2>&1
$cron_enabled        = true;
$cron_scan_limit     = 20;             // Max recent unread messages to inspect per run
$cron_spam_filter    = true;           // Automatically detect spam/phishing
$cron_spam_action    = 'trash';        // 'trash', 'junk', or 'flag'
$cron_spam_threshold = 75;             // Confidence percentage threshold (0-100)
$cron_auto_label     = true;           // Categorize and flag (Work, Personal, Finance, Urgent)
$cron_auto_draft     = true;           // Auto-generate smart response drafts in Drafts folder

$cron_accounts = [];
