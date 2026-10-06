<?php
/**
 * Multi-Account Plugin - Account Management & Options Page
 *
 * Allows users to add, edit, test, and manage secondary IMAP accounts.
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

$statusMsg = '';
$statusType = 'success'; // 'success' or 'error'

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $delId = trim($_GET['id']);
    $mgr->deleteAccount($delId);
    $mgr->syncSquirrelMailIdentities();
    sqm_redirect('options.php?msg=deleted');
}

// Handle Save (Add or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account'])) {
    $name = trim($_POST['acc_name'] ?? '');
    $email = trim($_POST['acc_email'] ?? '');
    $host = trim($_POST['acc_host'] ?? '');
    $port = intval($_POST['acc_port'] ?? 993);
    $tls = intval($_POST['acc_tls'] ?? 1);
    $user = trim($_POST['acc_user'] ?? '');
    $pass = $_POST['acc_pass'] ?? '';
    $colorVal = trim($_POST['acc_color'] ?? '#1a73e8');
    $enabled = isset($_POST['acc_enabled']) ? 1 : 0;
    $editId = trim($_POST['acc_id'] ?? '');

    if (empty($name) || empty($email) || empty($host) || empty($user)) {
        $statusMsg = 'Please fill in all required fields (Name, Email, IMAP Host, Username).';
        $statusType = 'error';
    } else {
        $mgr->saveAccount([
            'id'       => $editId,
            'name'     => $name,
            'email'    => $email,
            'host'     => $host,
            'port'     => $port,
            'tls'      => $tls,
            'user'     => $user,
            'password' => $pass,
            'color'    => $colorVal,
            'enabled'  => $enabled
        ]);
        $mgr->syncSquirrelMailIdentities();
        sqm_redirect('options.php?msg=saved');
    }
}

// Check for notice query params
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $statusMsg = 'Account successfully saved and synchronized!';
        $statusType = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $statusMsg = 'Account was successfully removed.';
        $statusType = 'success';
    }
}

// Check if editing a specific account
$editAcc = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
    $editId = trim($_GET['id']);
    foreach ($accounts as $a) {
        if ($a['id'] === $editId) {
            $editAcc = $a;
            break;
        }
    }
}

$accounts = $mgr->getAccounts(); // refresh list

displayPageHeader($color, null);
?>
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
        .mgr-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .mgr-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .mgr-title {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mgr-nav-links {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }
        .mgr-nav-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        .mgr-nav-links a:hover {
            text-decoration: underline;
        }
        /* Alert Message */
        .mgr-alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mgr-alert.success {
            background: #e6f4ea;
            color: #137333;
            border: 1px solid #ceead6;
        }
        .mgr-alert.error {
            background: #fce8e6;
            color: #c5221f;
            border: 1px solid #fad2cf;
        }
        /* Section Cards */
        .mgr-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .mgr-card-title {
            margin: 0 0 16px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        /* Table of configured accounts */
        .mgr-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .mgr-table th {
            background: #f8f9fa;
            color: var(--text-muted);
            font-weight: 600;
            padding: 9px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
        }
        .mgr-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }
        .acc-tag-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 12px;
            color: #ffffff;
            font-weight: 600;
            font-size: 12px;
        }
        .btn-sm {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-dark);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s;
        }
        .btn-sm:hover {
            background: #f1f3f4;
            text-decoration: none;
        }
        .btn-sm-danger {
            color: #d93025;
            border-color: #fad2cf;
        }
        .btn-sm-danger:hover {
            background: #fce8e6;
        }
        /* Form fields */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 18px;
            row-gap: 14px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group.full {
            grid-column: span 2;
        }
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #3c4043;
        }
        .form-input, .form-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 13.5px;
            outline: none;
            box-sizing: border-box;
            background: #ffffff;
            color: var(--text-dark);
            transition: border-color 0.15s;
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(26,115,232,0.15);
        }
        .form-help {
            font-size: 11.5px;
            color: var(--text-muted);
        }
        /* Color presets */
        .color-presets {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }
        .color-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.15s, border-color 0.15s;
        }
        .color-dot:hover {
            transform: scale(1.15);
        }
        .color-dot.active {
            border-color: #202124;
            transform: scale(1.1);
        }
        /* Bottom actions */
        .form-actions {
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid #f1f3f4;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-main {
            padding: 9px 18px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: var(--primary);
            color: #ffffff;
            transition: background 0.15s;
        }
        .btn-main:hover {
            background: var(--primary-hover);
        }
        .btn-outline {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-dark);
            transition: background 0.15s;
        }
        .btn-outline:hover {
            background: #f1f3f4;
        }
        #conn-test-result {
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>

<div class="mgr-container">
    <div class="mgr-header">
        <h1 class="mgr-title">
            <span>⚙️</span> Multi-Account Manager
        </h1>
        <div class="mgr-nav-links">
            <a href="unified_inbox.php">📬 Unified Inbox</a>
            <span>&bull;</span>
            <a href="../../src/options.php">Options</a>
        </div>
    </div>

    <?php if (!empty($statusMsg)): ?>
        <div class="mgr-alert <?php echo $statusType; ?>">
            <span><?php echo ($statusType === 'success' ? '✅' : '⚠️'); ?></span>
            <span><?php echo htmlspecialchars($statusMsg); ?></span>
        </div>
    <?php endif; ?>

    <!-- Configured Accounts List -->
    <div class="mgr-card">
        <div class="mgr-card-title">
            <span>Connected Accounts</span>
            <span style="font-size: 12.5px; font-weight: normal; color: var(--text-muted);">
                <?php echo count($accounts) + 1; ?> account(s) active
            </span>
        </div>

        <table class="mgr-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Email Address</th>
                    <th>IMAP Host</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Primary SquirrelMail Account -->
                <tr>
                    <td>
                        <span class="acc-tag-badge" style="background-color: #1a73e8;">
                            Primary Account
                        </span>
                    </td>
                    <td><code><?php echo htmlspecialchars($username); ?></code></td>
                    <td><em>Default Server</em></td>
                    <td><span style="color: #137333; font-weight: 600;">Active</span></td>
                    <td style="text-align: right;">
                        <span style="color: var(--text-muted); font-size: 11px;">Primary login</span>
                    </td>
                </tr>

                <!-- Secondary Accounts -->
                <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            No secondary accounts added yet. Use the form below to connect your Combell, Gmail, or external mail accounts.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($accounts as $acc): 
                        $accColor = !empty($acc['color']) ? $acc['color'] : '#34a853';
                        $accEnabled = !empty($acc['enabled']);
                    ?>
                    <tr>
                        <td>
                            <span class="acc-tag-badge" style="background-color: <?php echo htmlspecialchars($accColor); ?>;">
                                <?php echo htmlspecialchars($acc['name']); ?>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($acc['email']); ?></strong></td>
                        <td><?php echo htmlspecialchars($acc['host']); ?>:<?php echo intval($acc['port']); ?> (<?php echo ($acc['tls'] ? 'SSL' : 'Plain'); ?>)</td>
                        <td>
                            <?php if ($accEnabled): ?>
                                <span style="color: #137333; font-weight: 600;">Enabled</span>
                            <?php else: ?>
                                <span style="color: #5f6368;">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="options.php?action=edit&id=<?php echo urlencode($acc['id']); ?>" class="btn-sm">
                                ✏️ Edit
                            </a>
                            <button type="button" class="btn-sm" onclick="testAccountRow('<?php echo htmlspecialchars($acc['id']); ?>', this);">
                                ⚡ Test
                            </button>
                            <a href="options.php?action=delete&id=<?php echo urlencode($acc['id']); ?>" class="btn-sm btn-sm-danger" onclick="return confirm('Remove <?php echo htmlspecialchars(addslashes($acc['name'])); ?>?');">
                                🗑️ Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add / Edit Account Form -->
    <div class="mgr-card" id="account-form-section">
        <div class="mgr-card-title">
            <span><?php echo ($editAcc ? 'Edit Email Account' : '➕ Add Secondary Email Account'); ?></span>
            <?php if ($editAcc): ?>
                <a href="options.php" class="btn-sm">&times; Cancel Edit</a>
            <?php endif; ?>
        </div>

        <form method="post" action="options.php" id="multi-account-form">
            <input type="hidden" name="acc_id" id="acc_id" value="<?php echo htmlspecialchars($editAcc['id'] ?? ''); ?>" />

            <div class="form-grid">
                <!-- Preset Dropdown -->
                <div class="form-group full">
                    <label class="form-label">Auto-Configure Preset Provider</label>
                    <select class="form-select" onchange="applyProviderPreset(this.value);">
                        <option value="">-- Choose a Preset Provider (optional) --</option>
                        <option value="combell">Combell (imap.mailprotect.be / smtp-auth.mailprotect.be)</option>
                        <option value="gmail">Google Gmail (imap.gmail.com:993)</option>
                        <option value="outlook">Microsoft Outlook / Office 365 (outlook.office365.com:993)</option>
                        <option value="custom">Custom IMAP Server</option>
                    </select>
                </div>

                <!-- Account Label -->
                <div class="form-group">
                    <label class="form-label" for="acc_name">Account Label *</label>
                    <input type="text" name="acc_name" id="acc_name" class="form-input" placeholder="e.g. Combell Work, Personal" value="<?php echo htmlspecialchars($editAcc['name'] ?? ''); ?>" required />
                    <span class="form-help">Display name shown on badges and unified folder tabs.</span>
                </div>

                <!-- Email Address -->
                <div class="form-group">
                    <label class="form-label" for="acc_email">Email Address *</label>
                    <input type="email" name="acc_email" id="acc_email" class="form-input" placeholder="user@yourdomain.com" value="<?php echo htmlspecialchars($editAcc['email'] ?? ''); ?>" oninput="syncUsernameWithEmail(this.value);" required />
                    <span class="form-help">Sender identity when composing or replying.</span>
                </div>

                <!-- IMAP Host -->
                <div class="form-group">
                    <label class="form-label" for="acc_host">IMAP Server Host *</label>
                    <input type="text" name="acc_host" id="acc_host" class="form-input" placeholder="imap.mailprotect.be" value="<?php echo htmlspecialchars($editAcc['host'] ?? ''); ?>" required />
                </div>

                <!-- IMAP Port & TLS -->
                <div class="form-group">
                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label class="form-label" for="acc_port">Port</label>
                            <input type="number" name="acc_port" id="acc_port" class="form-input" value="<?php echo intval($editAcc['port'] ?? 993); ?>" />
                        </div>
                        <div style="flex: 1.2;">
                            <label class="form-label" for="acc_tls">Encryption</label>
                            <select name="acc_tls" id="acc_tls" class="form-select">
                                <option value="1" <?php if (($editAcc['tls'] ?? 1) == 1) echo 'selected'; ?>>SSL / TLS (993)</option>
                                <option value="0" <?php if (($editAcc['tls'] ?? 1) == 0) echo 'selected'; ?>>None / STARTTLS (143)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- IMAP Username -->
                <div class="form-group">
                    <label class="form-label" for="acc_user">IMAP Username *</label>
                    <input type="text" name="acc_user" id="acc_user" class="form-input" placeholder="user@yourdomain.com" value="<?php echo htmlspecialchars($editAcc['user'] ?? ''); ?>" required />
                </div>

                <!-- IMAP Password -->
                <div class="form-group">
                    <label class="form-label" for="acc_pass">IMAP Password <?php echo ($editAcc ? '(leave blank to keep unchanged)' : '*'); ?></label>
                    <input type="password" name="acc_pass" id="acc_pass" class="form-input" placeholder="••••••••••••" <?php if (!$editAcc) echo 'required'; ?> />
                </div>

                <!-- Badge Color Selection -->
                <div class="form-group full">
                    <label class="form-label">Badge Color Identifier</label>
                    <input type="hidden" name="acc_color" id="acc_color" value="<?php echo htmlspecialchars($editAcc['color'] ?? '#1a73e8'); ?>" />
                    <div class="color-presets" id="color-presets-wrap">
                        <?php 
                        $palette = ['#1a73e8', '#ff7900', '#34a853', '#9334e8', '#ea4335', '#fbbc04', '#12b5cb', '#5f6368'];
                        $curColor = $editAcc['color'] ?? '#1a73e8';
                        foreach ($palette as $hex):
                        ?>
                            <div class="color-dot <?php if ($curColor === $hex) echo 'active'; ?>" style="background-color: <?php echo $hex; ?>;" onclick="selectColorPreset('<?php echo $hex; ?>', this);" title="<?php echo $hex; ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Enabled Checkbox -->
                <div class="form-group full">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; cursor: pointer;">
                        <input type="checkbox" name="acc_enabled" value="1" <?php if (($editAcc['enabled'] ?? true)) echo 'checked'; ?> />
                        <span>Enable this account in the Unified Inbox aggregation</span>
                    </label>
                </div>
            </div>

            <!-- Form Buttons & Test Connection -->
            <div class="form-actions">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn-outline" onclick="testCurrentFormConnection();" id="btn-test-conn">
                        ⚡ Test IMAP Connection
                    </button>
                    <span id="conn-test-result"></span>
                </div>

                <button type="submit" name="save_account" class="btn-main">
                    💾 <?php echo ($editAcc ? 'Update Account' : 'Save & Connect Account'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
const providerPresets = {
    combell: {
        host: 'imap.mailprotect.be',
        port: 993,
        tls: 1,
        color: '#ff7900'
    },
    gmail: {
        host: 'imap.gmail.com',
        port: 993,
        tls: 1,
        color: '#ea4335'
    },
    outlook: {
        host: 'outlook.office365.com',
        port: 993,
        tls: 1,
        color: '#1a73e8'
    }
};

function applyProviderPreset(key) {
    if (!key || !providerPresets[key]) return;
    const p = providerPresets[key];
    document.getElementById('acc_host').value = p.host;
    document.getElementById('acc_port').value = p.port;
    document.getElementById('acc_tls').value = p.tls;
    if (p.color) {
        selectColorPreset(p.color);
    }
}

function syncUsernameWithEmail(emailVal) {
    const userField = document.getElementById('acc_user');
    if (userField && !userField.value) {
        userField.value = emailVal;
    }
}

function selectColorPreset(hex, el) {
    document.getElementById('acc_color').value = hex;
    document.querySelectorAll('#color-presets-wrap .color-dot').forEach(d => d.classList.remove('active'));
    if (el) {
        el.classList.add('active');
    } else {
        document.querySelectorAll('#color-presets-wrap .color-dot').forEach(d => {
            if (d.getAttribute('title') === hex) d.classList.add('active');
        });
    }
}

function testCurrentFormConnection() {
    const host = document.getElementById('acc_host').value.trim();
    const port = document.getElementById('acc_port').value;
    const tls = document.getElementById('acc_tls').value;
    const user = document.getElementById('acc_user').value.trim();
    const pass = document.getElementById('acc_pass').value;
    const accId = document.getElementById('acc_id').value;
    const resultSpan = document.getElementById('conn-test-result');
    const btn = document.getElementById('btn-test-conn');

    if (!host || !user || (!pass && !accId)) {
        resultSpan.innerHTML = '<span style="color:#c5221f;">⚠️ Please enter host, username, and password first.</span>';
        return;
    }

    resultSpan.innerHTML = '<span style="color:#5f6368;">⏳ Connecting to ' + host + ':' + port + '...</span>';
    btn.disabled = true;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax.php?action=test_connection', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            btn.disabled = false;
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        resultSpan.innerHTML = '<span style="color:#137333; font-weight:600;">✅ ' + (res.message || 'Connected successfully!') + '</span>';
                    } else {
                        resultSpan.innerHTML = '<span style="color:#c5221f; font-weight:600;">❌ ' + (res.error || 'Connection failed.') + '</span>';
                    }
                } catch(e) {
                    resultSpan.innerHTML = '<span style="color:#c5221f;">❌ Unexpected server response.</span>';
                }
            } else {
                resultSpan.innerHTML = '<span style="color:#c5221f;">❌ Network error (' + xhr.status + ')</span>';
            }
        }
    };

    const params = 'host=' + encodeURIComponent(host) 
                 + '&port=' + encodeURIComponent(port) 
                 + '&tls=' + encodeURIComponent(tls) 
                 + '&user=' + encodeURIComponent(user) 
                 + '&password=' + encodeURIComponent(pass)
                 + '&account_id=' + encodeURIComponent(accId);

    xhr.send(params);
}

function testAccountRow(accId, btn) {
    const origText = btn.innerHTML;
    btn.innerHTML = '⏳ Testing...';
    btn.disabled = true;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax.php?action=test_connection', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        alert('✅ ' + (res.message || 'IMAP Login Successful!'));
                    } else {
                        alert('❌ ' + (res.error || 'Connection failed.'));
                    }
                } catch(e) {
                    alert('❌ Parse error: ' + xhr.responseText);
                }
            } else {
                alert('❌ Network error: ' + xhr.status);
            }
        }
    };
    xhr.send('account_id=' + encodeURIComponent(accId));
}
</script>

</body>
</html>
