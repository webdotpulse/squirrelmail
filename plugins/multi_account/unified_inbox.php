<?php
/**
 * Multi-Account Plugin - Unified Inbox View
 *
 * Aggregates and displays emails from primary and all secondary accounts in one view.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage multi_account
 */

require('../../include/init.php');
require_once(SM_PATH . 'functions/imap.php');
require_once(SM_PATH . 'functions/imap_mailbox.php');
require_once(SM_PATH . 'functions/imap_messages.php');
if (!function_exists('imap_utf7_decode_local') && file_exists(SM_PATH . 'functions/imap_utf7_local.php')) {
    require_once(SM_PATH . 'functions/imap_utf7_local.php');
}
require_once(SM_PATH . 'functions/page_header.php');
require_once(SM_PATH . 'plugins/multi_account/account_manager.php');

global $data_dir, $username, $color;

if (empty($username)) {
    exit;
}

$mgr = new MultiAccountManager($data_dir, $username);
$accounts = $mgr->getAccounts();

// Check if filter by account is requested
$selectedFilter = isset($_GET['account']) ? trim($_GET['account']) : 'all';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 30;
if ($limit <= 0 || $limit > 100) $limit = 30;

// Fetch aggregated messages
$errorNotice = '';
$messages = [];
try {
    $messages = $mgr->fetchUnifiedInbox($limit);
} catch (Throwable $e) {
    $errorNotice = $e->getMessage();
}

$unreadCounts = ['total_unread' => 0, 'accounts' => []];
try {
    $unreadCounts = $mgr->getUnreadCounts(false);
} catch (Throwable $e) {
    // Ignore fallback
}
$totalUnread = $unreadCounts['total_unread'] ?? 0;

displayPageHeader($color, null);
?>
<style>
        :root {
            --primary: #1a73e8;
            --primary-hover: #1557b0;
            --primary-bg: #e8f0fe;
            --text-dark: #202124;
            --text-muted: #5f6368;
            --border-color: #dadce0;
            --bg-light: #f8f9fa;
            --unread-bg: #ffffff;
            --read-bg: #f8f9fa;
        }
        body {
            background: #ffffff;
            color: var(--text-dark);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 16px 20px;
        }
        .uni-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        /* Header Toolbar */
        .uni-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .uni-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .uni-title {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .uni-total-badge {
            background: var(--primary);
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .uni-top-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-dark);
            transition: all 0.15s ease;
        }
        .btn-action:hover {
            background: #f1f3f4;
            color: var(--text-dark);
            text-decoration: none;
        }
        .btn-primary-action {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        .btn-primary-action:hover {
            background: var(--primary-hover);
            color: #ffffff;
        }
        /* Search and Filter Row */
        .uni-toolbar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .uni-filter-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 18px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-dark);
            background: #f1f3f4;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .filter-pill:hover {
            background: #e8eaed;
            text-decoration: none;
        }
        .filter-pill.active {
            background: var(--primary-bg);
            color: var(--primary);
            border-color: #bad3fb;
            font-weight: 600;
        }
        .filter-pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .filter-pill-badge {
            background: rgba(0,0,0,0.1);
            padding: 0 5px;
            border-radius: 10px;
            font-size: 11px;
        }
        .filter-pill.active .filter-pill-badge {
            background: var(--primary);
            color: #fff;
        }
        .uni-search-box {
            position: relative;
            min-width: 240px;
        }
        .uni-search-input {
            width: 100%;
            padding: 6px 12px 6px 30px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 13px;
            outline: none;
            box-sizing: border-box;
            background: #f8f9fa;
            transition: all 0.2s ease;
        }
        .uni-search-input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(26,115,232,0.2);
        }
        .uni-search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            color: var(--text-muted);
            pointer-events: none;
        }
        /* Message List Card */
        .uni-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .uni-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .uni-table th {
            background: #f8f9fa;
            color: var(--text-muted);
            font-weight: 600;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .uni-table tr.uni-row {
            border-bottom: 1px solid #f1f3f4;
            transition: background 0.15s ease;
            cursor: pointer;
        }
        .uni-table tr.uni-row:hover {
            background: #f4f7fd;
        }
        .uni-table tr.uni-row.unread {
            background: #ffffff;
            font-weight: 600;
        }
        .uni-table tr.uni-row.unread .uni-sender,
        .uni-table tr.uni-row.unread .uni-subject {
            color: #1f1f1f;
            font-weight: 700;
        }
        .uni-table tr.uni-row.read {
            background: #fafbfc;
            color: #5f6368;
        }
        .uni-table td {
            padding: 11px 14px;
            vertical-align: middle;
        }
        /* Columns */
        .col-badge {
            width: 120px;
        }
        .col-sender {
            width: 220px;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .col-subject {
            min-width: 280px;
        }
        .col-date {
            width: 110px;
            text-align: right;
            white-space: nowrap;
            font-size: 12px;
            color: var(--text-muted);
        }
        .col-actions {
            width: 80px;
            text-align: center;
        }
        /* Account Badge */
        .acc-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: #ffffff;
            white-space: nowrap;
        }
        /* Sender with Avatar */
        .sender-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sender-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: bold;
            font-size: 11.5px;
            flex-shrink: 0;
            text-transform: uppercase;
        }
        .unread-indicator {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
            display: inline-block;
            margin-right: 4px;
        }
        .unread-indicator.invisible {
            visibility: hidden;
        }
        .msg-link {
            color: inherit;
            text-decoration: none;
            display: block;
        }
        .msg-link:hover {
            color: var(--primary);
        }
        /* Empty State */
        .uni-empty-card {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .uni-empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
        .uni-empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .uni-empty-desc {
            font-size: 13.5px;
            max-width: 440px;
            margin: 0 auto 18px auto;
            line-height: 1.5;
        }
        /* Row Quick Actions */
        .row-action-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
            color: var(--text-muted);
            font-size: 13px;
            transition: background 0.1s;
        }
        .row-action-btn:hover {
            background: #dadce0;
            color: var(--text-dark);
        }
    </style>

<div class="uni-container">
    <!-- Header -->
    <div class="uni-header">
        <div class="uni-title-group">
            <h1 class="uni-title">
                <span>📬</span> Unified Inbox
            </h1>
            <?php if ($totalUnread > 0): ?>
                <span class="uni-total-badge"><?php echo intval($totalUnread); ?> unread</span>
            <?php endif; ?>
        </div>

        <div class="uni-top-actions">
            <a href="../../src/compose.php" class="btn-action btn-primary-action">
                <span>✏️</span> Compose
            </a>
            <button type="button" class="btn-action" onclick="window.location.reload();" title="Refresh all inboxes">
                <span>🔄</span> Refresh
            </button>
            <a href="options.php" class="btn-action" title="Configure multiple accounts">
                <span>⚙️</span> Manage Accounts
            </a>
        </div>
    </div>

    <?php if (!empty($errorNotice)): ?>
        <div style="background: #fde8e8; border: 1px solid #f8b4b4; color: #9b1c1c; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; display: flex; align-items: center; gap: 8px;">
            <span>⚠️</span>
            <span><?php echo htmlspecialchars($errorNotice); ?></span>
        </div>
    <?php endif; ?>

    <!-- Toolbar: Filter Pills & Search Box -->
    <div class="uni-toolbar-row">
        <div class="uni-filter-pills" id="account-filter-pills">
            <!-- All Filter -->
            <button type="button" class="filter-pill <?php echo ($selectedFilter === 'all' ? 'active' : ''); ?>" onclick="filterByAccount('all', this);">
                <span>All Inboxes</span>
                <span class="filter-pill-badge" id="count-all"><?php echo count($messages); ?></span>
            </button>

            <!-- Primary Account -->
            <?php 
            $primUnread = $unreadCounts['accounts']['primary']['unread'] ?? 0;
            ?>
            <button type="button" class="filter-pill <?php echo ($selectedFilter === 'primary' ? 'active' : ''); ?>" onclick="filterByAccount('primary', this);">
                <span class="filter-pill-dot" style="background-color: #1a73e8;"></span>
                <span>Primary</span>
                <?php if ($primUnread > 0): ?>
                    <span class="filter-pill-badge"><?php echo intval($primUnread); ?></span>
                <?php endif; ?>
            </button>

            <!-- Secondary Accounts -->
            <?php foreach ($accounts as $acc): 
                if (empty($acc['enabled'])) continue;
                $accId = $acc['id'];
                $accColor = !empty($acc['color']) ? $acc['color'] : '#34a853';
                $accUnread = $unreadCounts['accounts'][$accId]['unread'] ?? 0;
            ?>
            <button type="button" class="filter-pill <?php echo ($selectedFilter === $accId ? 'active' : ''); ?>" onclick="filterByAccount('<?php echo htmlspecialchars($accId); ?>', this);">
                <span class="filter-pill-dot" style="background-color: <?php echo htmlspecialchars($accColor); ?>;"></span>
                <span><?php echo htmlspecialchars($acc['name']); ?></span>
                <?php if ($accUnread > 0): ?>
                    <span class="filter-pill-badge"><?php echo intval($accUnread); ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>

            <!-- Unread Only Toggle -->
            <button type="button" class="filter-pill" id="filter-unread-toggle" onclick="toggleUnreadOnly(this);">
                <span>⚡ Unread Only</span>
            </button>
        </div>

        <div class="uni-search-box">
            <span class="uni-search-icon">🔍</span>
            <input type="text" class="uni-search-input" id="uni-search-input" placeholder="Search sender, subject..." oninput="handleInstantSearch(this.value)">
        </div>
    </div>

    <!-- Messages Container -->
    <div class="uni-card">
        <?php if (empty($messages)): ?>
            <div class="uni-empty-card">
                <div class="uni-empty-icon">📭</div>
                <div class="uni-empty-title">Your Unified Inbox is empty</div>
                <div class="uni-empty-desc">
                    <?php if (empty($accounts)): ?>
                        No secondary accounts configured yet. Connect your Combell, Gmail, Outlook, or work IMAP accounts to aggregate all emails into one unified stream!
                    <?php else: ?>
                        All caught up! No recent messages found across your accounts.
                    <?php endif; ?>
                </div>
                <?php if (empty($accounts)): ?>
                    <a href="options.php" class="btn-action btn-primary-action">
                        <span>➕</span> Add Email Account
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="uni-table" id="uni-table">
                <thead>
                    <tr>
                        <th style="width: 24px; text-align: center;"></th>
                        <th class="col-badge">Account</th>
                        <th class="col-sender">From</th>
                        <th class="col-subject">Subject</th>
                        <th class="col-date">Date</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody id="uni-table-body">
                    <?php 
                    foreach ($messages as $msg): 
                        $isSeen = !empty($msg['seen']);
                        $accId = $msg['account_id'];
                        $accName = $msg['account_name'];
                        $accColor = !empty($msg['account_color']) ? $msg['account_color'] : '#1a73e8';
                        $isPrimary = !empty($msg['is_primary']);
                        $uid = $msg['uid'];

                        // Read URL
                        if ($isPrimary) {
                            $readUrl = '../../src/read_body.php?mailbox=INBOX&passed_id=' . urlencode($uid);
                        } else {
                            $readUrl = 'view_message.php?account_id=' . urlencode($accId) . '&uid=' . urlencode($uid);
                        }

                        // Sender Display and Initial
                        $senderRaw = $msg['from'] ?? 'Unknown';
                        $cleanSender = MultiAccountManager::decodeHeader($senderRaw);
                        // Extract name or first word
                        $displayName = preg_replace('/<.*?>/', '', $cleanSender);
                        $displayName = trim($displayName) ?: $cleanSender;
                        $firstLetter = mb_strtoupper(mb_substr(trim($displayName, "\" '"), 0, 1, 'UTF-8'), 'UTF-8') ?: '?';

                        // Subject
                        $subject = MultiAccountManager::decodeHeader($msg['subject'] ?? '(No Subject)');

                        // Format Date
                        $ts = $msg['timestamp'] ?? time();
                        if (date('Y-m-d', $ts) === date('Y-m-d')) {
                            $dateStr = date('g:i A', $ts);
                        } elseif (date('Y', $ts) === date('Y')) {
                            $dateStr = date('M j', $ts);
                        } else {
                            $dateStr = date('M j, Y', $ts);
                        }
                    ?>
                    <tr class="uni-row <?php echo ($isSeen ? 'read' : 'unread'); ?>" 
                        data-account="<?php echo htmlspecialchars($accId); ?>"
                        data-seen="<?php echo ($isSeen ? '1' : '0'); ?>"
                        data-uid="<?php echo htmlspecialchars($uid); ?>"
                        data-search="<?php echo htmlspecialchars(strtolower($displayName . ' ' . $subject . ' ' . $accName)); ?>">
                        
                        <!-- Unread Dot -->
                        <td style="text-align: center; padding-right: 0;">
                            <span class="unread-indicator <?php echo ($isSeen ? 'invisible' : ''); ?>"></span>
                        </td>

                        <!-- Account Badge -->
                        <td class="col-badge">
                            <span class="acc-tag" style="background-color: <?php echo htmlspecialchars($accColor); ?>;">
                                <?php echo htmlspecialchars($accName); ?>
                            </span>
                        </td>

                        <!-- Sender -->
                        <td class="col-sender">
                            <a href="<?php echo htmlspecialchars($readUrl); ?>" class="msg-link">
                                <div class="sender-wrap">
                                    <div class="sender-avatar" style="background-color: <?php echo htmlspecialchars($accColor); ?>;">
                                        <?php echo htmlspecialchars($firstLetter); ?>
                                    </div>
                                    <span class="uni-sender" title="<?php echo htmlspecialchars($cleanSender); ?>">
                                        <?php echo htmlspecialchars($displayName); ?>
                                    </span>
                                </div>
                            </a>
                        </td>

                        <!-- Subject -->
                        <td class="col-subject">
                            <a href="<?php echo htmlspecialchars($readUrl); ?>" class="msg-link uni-subject">
                                <?php echo htmlspecialchars($subject); ?>
                            </a>
                        </td>

                        <!-- Date -->
                        <td class="col-date">
                            <?php echo htmlspecialchars($dateStr); ?>
                        </td>

                        <!-- Quick Actions -->
                        <td class="col-actions">
                            <?php if (!$isPrimary): ?>
                                <button type="button" class="row-action-btn" title="Toggle Read/Unread" onclick="event.stopPropagation(); toggleReadStatus('<?php echo htmlspecialchars($accId); ?>', '<?php echo htmlspecialchars($uid); ?>', this);">
                                    <?php echo ($isSeen ? '✉️' : '📩'); ?>
                                </button>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($readUrl); ?>" class="row-action-btn" title="View email">
                                ➔
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
let currentFilter = '<?php echo htmlspecialchars($selectedFilter); ?>';
let unreadOnly = false;
let searchQuery = '';

function filterRows() {
    const rows = document.querySelectorAll('#uni-table-body tr.uni-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowAccount = row.getAttribute('data-account');
        const rowSeen = row.getAttribute('data-seen');
        const rowSearch = row.getAttribute('data-search') || '';

        let matchAcc = (currentFilter === 'all' || rowAccount === currentFilter);
        let matchUnread = (!unreadOnly || rowSeen === '0');
        let matchSearch = (!searchQuery || rowSearch.indexOf(searchQuery) !== -1);

        if (matchAcc && matchUnread && matchSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
}

function filterByAccount(accId, btn) {
    currentFilter = accId;
    document.querySelectorAll('#account-filter-pills .filter-pill').forEach(p => {
        if (p.id !== 'filter-unread-toggle') {
            p.classList.remove('active');
        }
    });
    if (btn) btn.classList.add('active');
    filterRows();
}

function toggleUnreadOnly(btn) {
    unreadOnly = !unreadOnly;
    if (unreadOnly) {
        btn.classList.add('active');
    } else {
        btn.classList.remove('active');
    }
    filterRows();
}

function handleInstantSearch(val) {
    searchQuery = (val || '').trim().toLowerCase();
    filterRows();
}

function toggleReadStatus(accId, uid, btn) {
    const row = btn.closest('tr.uni-row');
    if (!row) return;
    const isSeen = row.getAttribute('data-seen') === '1';
    const newStatus = isSeen ? 0 : 1;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax.php?action=toggle_read', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.success) {
                    row.setAttribute('data-seen', newStatus ? '1' : '0');
                    const dot = row.querySelector('.unread-indicator');
                    if (newStatus === 1) {
                        row.classList.remove('unread');
                        row.classList.add('read');
                        if (dot) dot.classList.add('invisible');
                        btn.textContent = '✉️';
                    } else {
                        row.classList.remove('read');
                        row.classList.add('unread');
                        if (dot) dot.classList.remove('invisible');
                        btn.textContent = '📩';
                    }
                    filterRows();
                }
            } catch(e) {}
        }
    };
    xhr.send('account_id=' + encodeURIComponent(accId) + '&uid=' + encodeURIComponent(uid) + '&seen=' + newStatus);
}

// Initial filter if selected via query param
if (currentFilter !== 'all') {
    filterRows();
}
</script>

</body>
</html>
