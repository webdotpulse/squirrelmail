<?php
/**
 * Multi-Account Plugin - Core Hook Implementations
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage multi_account
 */

require_once(SM_PATH . 'plugins/multi_account/account_manager.php');

/**
 * Hook: Injects Unified Inbox entry & account switcher into the left folder pane
 */
function multi_account_left_main_do()
{
    global $data_dir, $username, $icon_theme_path;

    if (empty($username)) {
        return [];
    }

    $mgr = new MultiAccountManager($data_dir, $username);
    $accounts = $mgr->getAccounts();
    $unreadData = $mgr->getUnreadCounts();
    $totalUnread = $unreadData['total_unread'] ?? 0;

    $baseUri = sqm_baseuri();
    $inboxUrl = $baseUri . 'plugins/multi_account/unified_inbox.php';
    $optionsUrl = $baseUri . 'plugins/multi_account/options.php';

    ob_start();
    ?>
    <style>
        .sqm-multi-account-container {
            margin: 6px 8px 12px 8px;
            padding: 10px;
            background: var(--sm-bg-card, #ffffff);
            border: 1px solid var(--sm-border, #dcdfe4);
            border-radius: var(--sm-radius-md, 8px);
            font-family: inherit;
            box-shadow: var(--sm-shadow-sm, 0 1px 3px rgba(0,0,0,0.05));
            text-align: left;
        }
        .sqm-multi-unified-header {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: nowrap !important;
            white-space: nowrap !important;
            gap: 6px;
            padding: 6px 8px;
            border-radius: var(--sm-radius-sm, 6px);
            background: var(--sm-primary-light, #e8f0fe);
            color: var(--sm-primary, #1a73e8);
            text-decoration: none !important;
            font-weight: 600;
            font-size: 12.5px;
            transition: all var(--sm-transition-fast, 0.15s ease);
            box-sizing: border-box;
            width: 100%;
        }
        .sqm-multi-unified-header:hover {
            background: var(--sm-selected-bg, #d2e3fc);
            color: var(--sm-primary-hover, #1d4ed8);
            text-decoration: none !important;
        }
        .sqm-unified-title {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px;
            white-space: nowrap !important;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
            flex-shrink: 1;
        }
        .sqm-unread-badge {
            background: var(--sm-primary, #1a73e8);
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 10px;
            min-width: 14px;
            text-align: center;
            flex-shrink: 0 !important;
            white-space: nowrap !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            line-height: 1.3;
        }
        .sqm-unread-badge.zero {
            display: none !important;
        }
        .sqm-accounts-tree {
            margin-top: 6px;
            padding-top: 4px;
            border-top: 1px dashed var(--sm-border, #e8eaed);
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        .sqm-account-item {
            margin: 3px 0;
        }
        .sqm-account-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 6px;
            border-radius: var(--sm-radius-sm, 4px);
            color: var(--sm-text-secondary, #3c4043);
            text-decoration: none;
            font-size: 11.5px;
            transition: background 0.1s ease;
        }
        .sqm-account-link:hover {
            background: var(--sm-hover-bg, #f1f3f4);
            color: var(--sm-text-primary, #0f172a);
            text-decoration: none;
        }
        .sqm-acc-info {
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sqm-acc-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .sqm-acc-badge {
            background: var(--sm-border, #dadce0);
            color: var(--sm-text-primary, #3c4043);
            font-size: 10px;
            font-weight: 600;
            padding: 0 5px;
            border-radius: 8px;
        }
        .sqm-acc-actions {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--sm-border, #f1f3f4);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
        }
        .sqm-acc-action-link {
            color: var(--sm-text-muted, #5f6368);
            text-decoration: none;
        }
        .sqm-acc-action-link:hover {
            color: var(--sm-primary, #1a73e8);
            text-decoration: underline;
        }
    </style>

    <div class="sqm-multi-account-container" id="sqm-multi-account-widget">
        <a href="<?php echo htmlspecialchars($inboxUrl); ?>" class="sqm-multi-unified-header" title="<?php echo _("Open Unified Inbox with all accounts"); ?>">
            <span class="sqm-unified-title">
                <span>📬</span> <?php echo _("Unified Inbox"); ?>
            </span>
            <span class="sqm-unread-badge <?php echo ($totalUnread == 0 ? 'zero' : ''); ?>" id="sqm-multi-total-badge">
                <?php echo intval($totalUnread); ?>
            </span>
        </a>

        <?php if (!empty($accounts)): ?>
            <ul class="sqm-accounts-tree">
                <!-- Primary Account Item -->
                <li class="sqm-account-item">
                    <a href="<?php echo htmlspecialchars($inboxUrl . '?account=primary'); ?>" class="sqm-account-link" title="<?php echo htmlspecialchars($username); ?>">
                        <span class="sqm-acc-info">
                            <span class="sqm-acc-dot" style="background-color: #1a73e8;"></span>
                            <span style="font-weight: 500;"><?php echo _("Primary"); ?></span>
                        </span>
                        <?php 
                        $primUnread = $unreadData['accounts']['primary']['unread'] ?? 0;
                        if ($primUnread > 0): 
                        ?>
                            <span class="sqm-acc-badge"><?php echo intval($primUnread); ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Secondary Accounts -->
                <?php foreach ($accounts as $acc): 
                    if (empty($acc['enabled'])) continue;
                    $accId = $acc['id'];
                    $accUnread = $unreadData['accounts'][$accId]['unread'] ?? 0;
                    $accColor = !empty($acc['color']) ? $acc['color'] : '#34a853';
                ?>
                <li class="sqm-account-item">
                    <a href="<?php echo htmlspecialchars($inboxUrl . '?account=' . urlencode($accId)); ?>" class="sqm-account-link" title="<?php echo htmlspecialchars($acc['name'] . ' (' . $acc['email'] . ')'); ?>">
                        <span class="sqm-acc-info">
                            <span class="sqm-acc-dot" style="background-color: <?php echo htmlspecialchars($accColor); ?>;"></span>
                            <span><?php echo htmlspecialchars($acc['name']); ?></span>
                        </span>
                        <?php if ($accUnread > 0): ?>
                            <span class="sqm-acc-badge"><?php echo intval($accUnread); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="sqm-acc-actions">
            <a href="<?php echo htmlspecialchars($optionsUrl); ?>" class="sqm-acc-action-link">
                ⚙️ <?php echo _("Manage Accounts"); ?>
            </a>
            <a href="javascript:void(0);" onclick="sqmRefreshMultiCounts();" class="sqm-acc-action-link" title="<?php echo _("Refresh unread counters"); ?>">
                🔄
            </a>
        </div>
    </div>

    <script type="text/javascript">
    function sqmRefreshMultiCounts() {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', '<?php echo $baseUri; ?>plugins/multi_account/ajax.php?action=get_unread_counts', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data && typeof data.total_unread !== 'undefined') {
                        const totalBadge = document.getElementById('sqm-multi-total-badge');
                        if (totalBadge) {
                            totalBadge.textContent = data.total_unread;
                            if (data.total_unread > 0) {
                                totalBadge.classList.remove('zero');
                            } else {
                                totalBadge.classList.add('zero');
                            }
                        }
                    }
                } catch(e) {}
            }
        };
        xhr.send();
    }
    // Poll unread counts every 60 seconds
    if (!window.sqmMultiPollInterval) {
        window.sqmMultiPollInterval = setInterval(sqmRefreshMultiCounts, 60000);
    }
    </script>
    <?php
    $html = ob_get_clean();
    return ['left_main_before' => $html];
}

/**
 * Hook: Add "Unified Inbox" link to SquirrelMail top menuline
 */
function multi_account_page_header_do()
{
    global $data_dir, $username, $nbsp;

    if (empty($username)) {
        return [];
    }

    $baseUri = sqm_baseuri();
    $url = $baseUri . 'plugins/multi_account/unified_inbox.php';

    $mgr = new MultiAccountManager($data_dir, $username);
    $accounts = $mgr->getAccounts();
    $unreadData = $mgr->getUnreadCounts();
    $totalUnread = $unreadData['total_unread'] ?? 0;

    $badgeHtml = '';
    if ($totalUnread > 0) {
        $badgeHtml = ' <span style="background: #1a73e8; color: #fff; border-radius: 10px; font-size: 10px; padding: 1px 5px; font-weight: bold;">' . intval($totalUnread) . '</span>';
    }

    $output = '<a href="' . htmlspecialchars($url) . '" style="font-weight: 600; text-decoration: none;">📬 ' . _("Unified Inbox") . $badgeHtml . '</a>'
            . $nbsp . $nbsp;

    return ['menuline' => $output];
}

/**
 * Hook: Enhance compose screen with secondary account sender identities
 */
function multi_account_compose_close_do()
{
    global $data_dir, $username;

    if (empty($username)) {
        return [];
    }

    $mgr = new MultiAccountManager($data_dir, $username);
    $accounts = $mgr->getAccounts();

    if (empty($accounts)) {
        return [];
    }

    // Check if an account was requested via query parameter (e.g. from reply)
    $selectedAccId = $_GET['account_id'] ?? ($_POST['account_id'] ?? '');

    ob_start();
    ?>
    <script type="text/javascript">
    (function() {
        const accounts = <?php echo json_encode(array_values($accounts)); ?>;
        const selectedId = <?php echo json_encode($selectedAccId); ?>;
        
        function setupAccountIdentityPicker() {
            // Find the From row in compose form
            const fromSelect = document.querySelector('select[name="identity"]');
            if (fromSelect && selectedId) {
                for (let i = 0; i < fromSelect.options.length; i++) {
                    const opt = fromSelect.options[i];
                    for (const acc of accounts) {
                        if (acc.id === selectedId && opt.text.indexOf(acc.email) !== -1) {
                            fromSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupAccountIdentityPicker);
        } else {
            setupAccountIdentityPicker();
        }
    })();
    </script>
    <?php
    $output = ob_get_clean();
    return ['compose_bottom' => $output];
}

/**
 * Hook: Register Multi-Account Manager block under SquirrelMail Options
 */
function multi_account_optpage_register_block_do()
{
    global $optpage_blocks;

    $optpage_blocks[] = [
        'name' => _("Multi-Account Manager"),
        'url'  => SM_PATH . 'plugins/multi_account/options.php',
        'desc' => _("Manage multiple IMAP email accounts (e.g. Combell, Gmail, Outlook, Work), test server connections, configure badge colors, and manage the unified inbox."),
        'js'   => false
    ];
}
