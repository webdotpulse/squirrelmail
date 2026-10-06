<?php
/**
 * AI Agent & Assistant Plugin - Options Page
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

require('../../include/init.php');
include_once(SM_PATH . 'plugins/ai_agent/config.php');
include_once(SM_PATH . 'plugins/ai_agent/gemini_client.php');

global $data_dir, $username, $color;

// Security check: Must be authenticated
if (empty($username)) {
    exit;
}

$updated = false;
$test_result = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_ai_agent_options'])) {
        $apiKey    = isset($_POST['ai_agent_api_key']) ? trim($_POST['ai_agent_api_key']) : '';
        $model     = isset($_POST['ai_agent_model']) ? trim($_POST['ai_agent_model']) : 'gemini-3.8-flash';
        $spamFilt  = isset($_POST['ai_agent_spam_filter']) && $_POST['ai_agent_spam_filter'] === '1' ? '1' : '0';
        $spamAct   = isset($_POST['ai_agent_spam_action']) ? trim($_POST['ai_agent_spam_action']) : 'trash';
        $autoLabel = isset($_POST['ai_agent_auto_label']) && $_POST['ai_agent_auto_label'] === '1' ? '1' : '0';
        $autoDraft = isset($_POST['ai_agent_auto_draft']) && $_POST['ai_agent_auto_draft'] === '1' ? '1' : '0';

        setPref($data_dir, $username, 'ai_agent_api_key', $apiKey);
        setPref($data_dir, $username, 'ai_agent_model', $model);
        setPref($data_dir, $username, 'ai_agent_spam_filter', $spamFilt);
        setPref($data_dir, $username, 'ai_agent_spam_action', $spamAct);
        setPref($data_dir, $username, 'ai_agent_auto_label', $autoLabel);
        setPref($data_dir, $username, 'ai_agent_auto_draft', $autoDraft);

        $updated = true;
    }

    // Handle Test Connection
    if (isset($_POST['test_ai_agent'])) {
        $testKey   = isset($_POST['ai_agent_api_key']) ? trim($_POST['ai_agent_api_key']) : '';
        $testModel = isset($_POST['ai_agent_model']) ? trim($_POST['ai_agent_model']) : 'gemini-3.8-flash';
        $client    = new SquirrelMailGeminiClient($testKey, $testModel);

        $testRes = $client->callGemini('Say "Gemini 3.8 AI is active and connected!" in one sentence.');
        if ($testRes['success']) {
            $test_result = [
                'success' => true,
                'message' => 'Connection Successful! Model ' . htmlspecialchars($testModel) . ' responded: "' . htmlspecialchars($testRes['text']) . '"'
            ];
        } else {
            $test_result = [
                'success' => false,
                'message' => 'Connection Test Failed: ' . htmlspecialchars($testRes['error'])
            ];
        }
    }
}

// Current Preferences
$current_key   = getPref($data_dir, $username, 'ai_agent_api_key', $gemini_api_key ?: '');
$current_model = getPref($data_dir, $username, 'ai_agent_model', $gemini_model ?: 'gemini-3.8-flash');
$current_spam  = getPref($data_dir, $username, 'ai_agent_spam_filter', '1');
$current_act   = getPref($data_dir, $username, 'ai_agent_spam_action', 'trash');
$current_label = getPref($data_dir, $username, 'ai_agent_auto_label', '1');
$current_draft = getPref($data_dir, $username, 'ai_agent_auto_draft', '1');

// Read recent cron log lines if available
$log_lines = [];
$log_file = SM_PATH . 'plugins/ai_agent/data/cron.log';
if (file_exists($log_file) && is_readable($log_file)) {
    $raw = @file($log_file);
    if (!empty($raw)) {
        $log_lines = array_slice($raw, -15);
    }
}

displayPageHeader($color, 'None');
?>

<div style="max-width: 720px; margin: 20px auto; padding: 24px; background: #ffffff; border: 1px solid #dadce0; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ebebeb; padding-bottom: 14px; margin-bottom: 20px;">
        <h2 style="margin: 0; font-size: 20px; color: #202124; display: flex; align-items: center; gap: 8px;">
            <span>✨</span> AI Email Agent &amp; Assistant (Gemini 3.8)
        </h2>
        <a href="../../src/options.php" style="color: #1a73e8; text-decoration: none; font-size: 13px;">&larr; Return to Options</a>
    </div>

    <?php if ($updated): ?>
        <div style="background: #e6f4ea; color: #137333; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border: 1px solid #ceead6;">
            ✓ Your AI Agent settings have been saved successfully!
        </div>
    <?php endif; ?>

    <?php if ($test_result): ?>
        <div style="background: <?php echo $test_result['success'] ? '#e6f4ea' : '#fce8e6'; ?>; color: <?php echo $test_result['success'] ? '#137333' : '#c5221f'; ?>; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border: 1px solid <?php echo $test_result['success'] ? '#ceead6' : '#fad2cf'; ?>;">
            <?php echo ($test_result['success'] ? '✓ ' : '✗ ') . $test_result['message']; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <!-- 1. API Configuration -->
        <div style="margin-bottom: 24px; background: #f8fafd; border: 1px solid #e1e3e1; border-radius: 6px; padding: 16px;">
            <h3 style="margin: 0 0 12px 0; font-size: 15px; color: #1a73e8; display: flex; align-items: center; gap: 6px;">
                <span>🔑</span> Google Gemini API Credentials
            </h3>
            
            <div style="margin-bottom: 14px;">
                <label for="ai_agent_api_key" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #3c4043;">
                    Gemini API Key
                </label>
                <input type="password" id="ai_agent_api_key" name="ai_agent_api_key" value="<?php echo htmlspecialchars($current_key); ?>" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #dadce0; border-radius: 4px; font-size: 13px; font-family: monospace;" placeholder="AIzaSy...">
                <div style="font-size: 12px; color: #70757a; margin-top: 4px; display: flex; justify-content: space-between;">
                    <span>Obtain your free API key at <a href="https://aistudio.google.com/" target="_blank" style="color: #1a73e8;">Google AI Studio</a>.</span>
                    <label style="cursor: pointer; user-select: none;">
                        <input type="checkbox" onclick="const f=document.getElementById('ai_agent_api_key'); f.type=this.checked?'text':'password';"> Show Key
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 8px;">
                <label for="ai_agent_model" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #3c4043;">
                    Gemini Model
                </label>
                <select id="ai_agent_model" name="ai_agent_model" style="width: 100%; max-width: 320px; padding: 8px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 13px;">
                    <option value="gemini-3.8-flash" <?php if ($current_model === 'gemini-3.8-flash') echo 'selected'; ?>>Gemini 3.8 Flash (High Speed &amp; Accuracy - Recommended)</option>
                    <option value="gemini-3.8-pro" <?php if ($current_model === 'gemini-3.8-pro') echo 'selected'; ?>>Gemini 3.8 Pro (Maximum Reasoning &amp; Complex Analysis)</option>
                    <option value="gemini-2.5-flash" <?php if ($current_model === 'gemini-2.5-flash') echo 'selected'; ?>>Gemini 2.5 Flash</option>
                </select>
            </div>
            
            <div style="margin-top: 12px;">
                <input type="submit" name="test_ai_agent" value="Test API Connection" style="background: #ffffff; color: #1a73e8; border: 1px solid #1a73e8; padding: 6px 14px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">
            </div>
        </div>

        <!-- 2. Web UI Assistant Capabilities -->
        <div style="margin-bottom: 24px;">
            <h3 style="margin: 0 0 12px 0; font-size: 15px; color: #202124; display: flex; align-items: center; gap: 6px;">
                <span>⚡</span> Interactive Email Assistant Features
            </h3>
            <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #3c4043; line-height: 1.6;">
                <li><strong>Compose with AI:</strong> Generate polite, professional email drafts from short bullet points or instructions directly in Compose.</li>
                <li><strong>Smart Reply:</strong> Draft contextual, custom responses in 1 click when replying to existing conversations.</li>
                <li><strong>Email Summarizer (TL;DR):</strong> Read long email threads in seconds with key takeaways, action items, and deadline alerts.</li>
                <li><strong>Scam &amp; Phishing Checker:</strong> Multi-factor security scan on headers, spoofing risks, and fraud indicators.</li>
                <li><strong>Translator:</strong> Instant translation of incoming emails into Dutch, English, French, German, Spanish, etc.</li>
            </ul>
        </div>

        <!-- 3. Server-Side Cron Automation -->
        <div style="margin-bottom: 24px; background: #fff8e1; border: 1px solid #ffe082; border-radius: 6px; padding: 16px;">
            <h3 style="margin: 0 0 12px 0; font-size: 15px; color: #b06000; display: flex; align-items: center; gap: 6px;">
                <span>🤖</span> Server-Side Background Automation (Cronjob)
            </h3>
            
            <div style="margin-bottom: 12px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                    <input type="checkbox" name="ai_agent_spam_filter" value="1" <?php if ($current_spam === '1') echo 'checked'; ?>>
                    <span>Enable Automated AI Spam &amp; Scam Filtering</span>
                </label>
                <div style="margin-left: 24px; margin-top: 4px; font-size: 12px; color: #5f6368;">
                    When spam/scam is detected:
                    <select name="ai_agent_spam_action" style="margin-left: 6px; padding: 2px 6px; font-size: 12px; border: 1px solid #dadce0; border-radius: 4px;">
                        <option value="trash" <?php if ($current_act === 'trash') echo 'selected'; ?>>Move immediately to Trash</option>
                        <option value="junk" <?php if ($current_act === 'junk') echo 'selected'; ?>>Move to Junk / Spam folder</option>
                        <option value="flag" <?php if ($current_act === 'flag') echo 'selected'; ?>>Mark with Flag / Alert only</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                    <input type="checkbox" name="ai_agent_auto_label" value="1" <?php if ($current_label === '1') echo 'checked'; ?>>
                    <span>Enable AI Auto-Categorization (Work, Finance, Urgent, Newsletters)</span>
                </label>
            </div>

            <div style="margin-bottom: 6px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
                    <input type="checkbox" name="ai_agent_auto_draft" value="1" <?php if ($current_draft === '1') echo 'checked'; ?>>
                    <span>Enable Auto-Draft Replies (Pre-draft answers in Drafts folder for unanswered questions)</span>
                </label>
            </div>
        </div>

        <!-- 4. Cronjob Setup Guide -->
        <div style="margin-bottom: 24px; padding: 14px; background: #f8fafd; border: 1px solid #dadce0; border-radius: 6px;">
            <div style="font-weight: 600; font-size: 13px; color: #3c4043; margin-bottom: 6px;">
                Server Cronjob Configuration:
            </div>
            <div style="font-size: 12px; color: #5f6368; margin-bottom: 8px;">
                Add the following entry to your Linux server crontab (<code>crontab -e</code>) to run the AI automation every 5 minutes:
            </div>
            <pre style="background: #202124; color: #cbf078; padding: 10px 14px; border-radius: 4px; font-size: 12px; overflow-x: auto; margin: 0;">*/5 * * * * php <?php echo realpath(SM_PATH . 'plugins/ai_agent/cron.php'); ?> >> <?php echo realpath(SM_PATH . 'plugins/ai_agent/data'); ?>/cron.log 2>&1</pre>
        </div>

        <?php if (!empty($log_lines)): ?>
            <div style="margin-bottom: 24px;">
                <div style="font-weight: 600; font-size: 13px; color: #3c4043; margin-bottom: 6px;">Recent Cron Activity Log:</div>
                <div style="background: #202124; color: #e8eaed; padding: 10px 14px; border-radius: 4px; font-family: monospace; font-size: 11px; max-height: 160px; overflow-y: auto; line-height: 1.4;">
                    <?php foreach ($log_lines as $l) echo htmlspecialchars($l); ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="border-top: 1px solid #ebebeb; padding-top: 16px; display: flex; gap: 12px;">
            <input type="submit" name="save_ai_agent_options" value="Save AI Settings" style="background: #1a73e8; color: #ffffff; border: none; padding: 10px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px;">
            <a href="../../src/options.php" style="background: #f1f3f4; color: #3c4043; border: 1px solid #dadce0; padding: 9px 18px; border-radius: 4px; font-weight: 500; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center;">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>
