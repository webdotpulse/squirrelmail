<?php
/**
 * AI Agent AJAX Controller
 *
 * Handles Web UI requests for AI compose, reply, summarize, translate, and scam check.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

require('../../include/init.php');
include_once(SM_PATH . 'plugins/ai_agent/config.php');
include_once(SM_PATH . 'plugins/ai_agent/gemini_client.php');

header('Content-Type: application/json; charset=utf-8');

// Ensure user is authenticated
global $username;
if (empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in to SquirrelMail.']);
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$client = new SquirrelMailGeminiClient();

if (empty($client->getApiKey())) {
    echo json_encode([
        'success' => false,
        'error'   => 'Gemini API key is not configured. Please go to Options -> AI Agent & Assistant to set your Google Gemini API key.'
    ]);
    exit;
}

switch ($action) {
    case 'compose':
        $prompt  = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';
        $tone    = isset($_POST['tone']) ? trim($_POST['tone']) : 'professional';
        $context = isset($_POST['context']) ? trim($_POST['context']) : '';

        if (empty($prompt)) {
            echo json_encode(['success' => false, 'error' => 'Prompt instruction is required.']);
            exit;
        }

        $res = $client->generateCompose($prompt, $tone, $context);
        echo json_encode($res);
        break;

    case 'reply':
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $body    = isset($_POST['body']) ? trim($_POST['body']) : '';
        $sender  = isset($_POST['sender']) ? trim($_POST['sender']) : '';
        $intent  = isset($_POST['intent']) ? trim($_POST['intent']) : 'Acknowledge and reply professionally';

        if (empty($body) && empty($subject)) {
            echo json_encode(['success' => false, 'error' => 'Original email content is required to generate a reply.']);
            exit;
        }

        $res = $client->generateReply($subject, $body, $intent, $sender);
        echo json_encode($res);
        break;

    case 'summarize':
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $from    = isset($_POST['from']) ? trim($_POST['from']) : '';
        $body    = isset($_POST['body']) ? trim($_POST['body']) : '';

        if (empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Email body is empty. Nothing to summarize.']);
            exit;
        }

        $res = $client->summarizeEmail($subject, $from, $body);
        echo json_encode($res);
        break;

    case 'translate':
        $body       = isset($_POST['body']) ? trim($_POST['body']) : '';
        $targetLang = isset($_POST['target_lang']) ? trim($_POST['target_lang']) : 'English';

        if (empty($body)) {
            echo json_encode(['success' => false, 'error' => 'Email body is empty. Nothing to translate.']);
            exit;
        }

        $res = $client->translateEmail($body, $targetLang);
        echo json_encode($res);
        break;

    case 'scam_check':
        $sender  = isset($_POST['sender']) ? trim($_POST['sender']) : '';
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $headers = isset($_POST['headers']) ? trim($_POST['headers']) : '';
        $body    = isset($_POST['body']) ? trim($_POST['body']) : '';

        $res = $client->checkScamPhishing($sender, $subject, $headers, $body);
        echo json_encode($res);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action: ' . htmlspecialchars($action)]);
        break;
}
