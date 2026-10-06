<?php
/**
 * Multi-Account Plugin - Message Viewer for Secondary Accounts
 *
 * Displays full email details, body (HTML / Plain text), headers, and reply actions.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage multi_account
 */

require('../../include/init.php');
require_once(SM_PATH . 'plugins/multi_account/account_manager.php');

global $data_dir, $username, $color;

if (empty($username)) {
    exit;
}

$accountId = $_GET['account_id'] ?? '';
$uid = intval($_GET['uid'] ?? 0);

if (empty($accountId) || $uid <= 0) {
    header('Location: unified_inbox.php');
    exit;
}

$mgr = new MultiAccountManager($data_dir, $username);

// Handle actions (e.g. delete, mark unread)
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'delete') {
        $mgr->deleteMessage($accountId, $uid);
        header('Location: unified_inbox.php');
        exit;
    } elseif ($_GET['action'] === 'mark_unread') {
        $mgr->markSeen($accountId, $uid, false);
        header('Location: unified_inbox.php');
        exit;
    }
}

// Fetch and automatically mark message as seen
$msgData = $mgr->fetchMessage($accountId, $uid);
if (!$msgData || empty($msgData['parsed'])) {
    displayPageHeader($color, 'None');
    echo '<div style="padding: 30px; font-family: sans-serif; text-align: center;">';
    echo '<h3>Message could not be retrieved from the mail server.</h3>';
    echo '<p><a href="unified_inbox.php">&larr; Return to Unified Inbox</a></p>';
    echo '</div>';
    exit;
}

$acc = $msgData['account'];
$parsed = $msgData['parsed'];

$subject = $parsed['subject'] ?: '(No Subject)';
$from = $parsed['from'] ?: 'Unknown';
$to = $parsed['to'] ?: '';
$cc = $parsed['cc'] ?: '';
$date = $parsed['date'] ?: '';

// Format timestamps
$ts = !empty($date) ? strtotime($date) : time();
$dateDisplay = date('D, M j, Y \a\t g:i A', $ts);

// Extract reply email from From header
$replyEmail = '';
if (preg_match('/<([^>]+)>/', $from, $m)) {
    $replyEmail = trim($m[1]);
} else {
    $replyEmail = trim($from);
}

// Prepare Reply and Forward URLs
$replySubject = (stripos($subject, 'Re:') === 0) ? $subject : ('Re: ' . $subject);
$forwardSubject = (stripos($subject, 'Fwd:') === 0) ? $subject : ('Fwd: ' . $subject);

$replyUrl = '../../src/compose.php?send_to=' . urlencode($replyEmail) 
          . '&subject=' . urlencode($replySubject) 
          . '&account_id=' . urlencode($accountId);

$bodyHtml = $parsed['html'] ?? '';
$bodyText = $parsed['text'] ?? '';

// Sanitize HTML body: remove malicious scripts, iframes, and alert handlers
if (!empty($bodyHtml)) {
    $bodyHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $bodyHtml);
    $bodyHtml = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $bodyHtml);
    $bodyHtml = preg_replace('/on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/is', '', $bodyHtml);
}

// Fallback to text if HTML is empty
if (empty($bodyHtml) && !empty($bodyText)) {
    $safeText = htmlspecialchars($bodyText);
    // Auto-link URLs
    $safeText = preg_replace('/(https?:\/\/[^\s<]+)/i', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $safeText);
    $bodyHtml = '<pre style="font-family: inherit; white-space: pre-wrap; word-break: break-word; font-size: 14px; line-height: 1.6; margin: 0;">' . $safeText . '</pre>';
}

displayPageHeader($color, 'None');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($subject); ?> - SquirrelMail</title>
    <style>
        :root {
            --primary: #1a73e8;
            --primary-hover: #1557b0;
            --text-dark: #202124;
            --text-muted: #5f6368;
            --border-color: #dadce0;
            --bg-light: #f8f9fa;
        }
        body {
            background: #ffffff;
            color: var(--text-dark);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 16px 20px;
        }
        .viewer-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        /* Top Navigation Bar */
        .viewer-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 13.5px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .action-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-view-act {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-dark);
            transition: all 0.15s ease;
        }
        .btn-view-act:hover {
            background: #f1f3f4;
            text-decoration: none;
        }
        .btn-view-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        .btn-view-primary:hover {
            background: var(--primary-hover);
            color: #ffffff;
        }
        /* Account Identifier Badge Bar */
        .account-alert-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: #f1f3f4;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 12.5px;
            color: var(--text-muted);
        }
        .account-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 12px;
            color: #ffffff;
            font-weight: 600;
            font-size: 12px;
        }
        /* Message Card */
        .msg-view-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px;
        }
        .msg-subject-title {
            margin: 0 0 16px 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.4;
        }
        /* Headers Table / Grid */
        .msg-meta-box {
            display: grid;
            grid-template-columns: 60px 1fr;
            row-gap: 6px;
            column-gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px solid #ebebeb;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .meta-label {
            color: var(--text-muted);
            font-weight: 600;
        }
        .meta-val {
            color: var(--text-dark);
        }
        /* Body Container */
        .msg-body-frame {
            line-height: 1.6;
            font-size: 14px;
            color: #1f1f1f;
            word-break: break-word;
            min-height: 120px;
        }
    </style>
</head>
<body>

<div class="viewer-container">
    <!-- Top Bar -->
    <div class="viewer-top-bar">
        <a href="unified_inbox.php" class="back-link">
            <span>&larr;</span> Back to Unified Inbox
        </a>

        <div class="action-group">
            <a href="<?php echo htmlspecialchars($replyUrl); ?>" class="btn-view-act btn-view-primary">
                <span>↩️</span> Reply
            </a>
            <a href="view_message.php?account_id=<?php echo urlencode($accountId); ?>&uid=<?php echo $uid; ?>&action=mark_unread" class="btn-view-act">
                <span>📩</span> Mark Unread
            </a>
            <a href="view_message.php?account_id=<?php echo urlencode($accountId); ?>&uid=<?php echo $uid; ?>&action=delete" class="btn-view-act" onclick="return confirm('Delete this message from <?php echo htmlspecialchars(addslashes($acc['name'])); ?>?');">
                <span>🗑️</span> Delete
            </a>
        </div>
    </div>

    <!-- Account Origin Banner -->
    <div class="account-alert-bar">
        <div>
            Received on secondary account:
            <span class="account-pill" style="background-color: <?php echo htmlspecialchars($acc['color']); ?>;">
                <?php echo htmlspecialchars($acc['name']); ?>
            </span>
            <strong>&lt;<?php echo htmlspecialchars($acc['email']); ?>&gt;</strong>
        </div>
        <div>
            Server: <code><?php echo htmlspecialchars($acc['host']); ?></code>
        </div>
    </div>

    <!-- Message Details Card -->
    <div class="msg-view-card">
        <h1 class="msg-subject-title"><?php echo htmlspecialchars($subject); ?></h1>

        <div class="msg-meta-box">
            <span class="meta-label">From:</span>
            <span class="meta-val"><strong><?php echo htmlspecialchars($from); ?></strong></span>

            <span class="meta-label">To:</span>
            <span class="meta-val"><?php echo htmlspecialchars($to ?: '(Undisclosed)'); ?></span>

            <?php if (!empty($cc)): ?>
                <span class="meta-label">Cc:</span>
                <span class="meta-val"><?php echo htmlspecialchars($cc); ?></span>
            <?php endif; ?>

            <span class="meta-label">Date:</span>
            <span class="meta-val"><?php echo htmlspecialchars($dateDisplay); ?></span>
        </div>

        <!-- Email Body -->
        <div class="msg-body-frame">
            <?php echo $bodyHtml; ?>
        </div>
    </div>
</div>

</body>
</html>
