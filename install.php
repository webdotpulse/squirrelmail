<?php
/**
 * SquirrelMail Web Installer & Configuration Assistant
 *
 * A modern, responsive setup wizard to install and configure SquirrelMail,
 * perform environment diagnostics, test mail server connectivity, configure
 * the Gmail theme, and generate the site configuration file.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version 1.5.3
 * @package squirrelmail
 */

define('SM_PATH', './');
define('INSTALLER_VERSION', '1.5.56');

// Ensure core constants (such as SM_DEBUG_MODE_OFF) are loaded before config.php
if (file_exists(SM_PATH . 'include/constants.php')) {
    require_once(SM_PATH . 'include/constants.php');
}

// Session initialization for installer state
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: send JSON response and exit
function send_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// -----------------------------------------------------------------------------
// AJAX Actions (Connection tests, auto-create directories, save configuration)
// -----------------------------------------------------------------------------
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Action: Test IMAP connection
    if ($action === 'test_imap') {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = intval($_POST['port'] ?? 143);
        $tls  = intval($_POST['tls'] ?? 0);
        $timeout = 5;

        $target = ($tls === 1 ? 'ssl://' : '') . $host;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("$target:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            send_json(['success' => false, 'message' => "Connection failed: $errstr ($errno)"]);
        }

        stream_set_timeout($fp, $timeout);
        $banner = @fgets($fp, 1024);
        @fclose($fp);

        send_json([
            'success' => true,
            'message' => 'IMAP connection successful!',
            'banner'  => trim($banner ?: 'Connected successfully (no banner returned).')
        ]);
    }

    // Action: Test SMTP connection
    if ($action === 'test_smtp') {
        $host = trim($_POST['host'] ?? 'localhost');
        $port = intval($_POST['port'] ?? 25);
        $tls  = intval($_POST['tls'] ?? 0);
        $timeout = 5;

        $target = ($tls === 1 ? 'ssl://' : '') . $host;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("$target:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            send_json(['success' => false, 'message' => "Connection failed: $errstr ($errno)"]);
        }

        stream_set_timeout($fp, $timeout);
        $banner = @fgets($fp, 1024);
        @fclose($fp);

        send_json([
            'success' => true,
            'message' => 'SMTP connection successful!',
            'banner'  => trim($banner ?: 'Connected successfully (no banner returned).')
        ]);
    }

    // Action: Auto-create data and attach directories
    if ($action === 'create_dirs') {
        $created = [];
        $errors = [];

        // Data dir
        if (!is_dir('data')) {
            if (@mkdir('data', 0770, true)) {
                $created[] = 'data/';
            } else {
                $errors[] = 'Failed to create data/ directory.';
            }
        }
        if (is_dir('data')) {
            // copy default_pref if available
            if (!file_exists('data/default_pref') && file_exists('config/default_pref')) {
                @copy('config/default_pref', 'data/default_pref');
            }
            // write htaccess
            if (!file_exists('data/.htaccess')) {
                @file_put_contents("data/.htaccess", "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
            }
            if (!file_exists('data/index.php')) {
                @file_put_contents("data/index.php", "<?php header('Location: ../index.php'); exit; ?>");
            }
        }

        // Attach dir
        if (!is_dir('attach')) {
            if (@mkdir('attach', 0730, true)) {
                $created[] = 'attach/';
            } else {
                $errors[] = 'Failed to create attach/ directory.';
            }
        }
        if (is_dir('attach')) {
            if (!file_exists('attach/.htaccess')) {
                @file_put_contents("attach/.htaccess", "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
            }
            if (!file_exists('attach/index.php')) {
                @file_put_contents("attach/index.php", "<?php header('Location: ../index.php'); exit; ?>");
            }
        }

        if (!empty($errors)) {
            send_json(['success' => false, 'message' => implode(' ', $errors)]);
        } else {
            send_json(['success' => true, 'message' => 'Directories created and secured successfully!']);
        }
    }

    // Action: Save configuration
    if ($action === 'install') {
        $res = save_configuration($_POST);
        send_json($res);
    }
}

// -----------------------------------------------------------------------------
// Configuration File Generator Function
// -----------------------------------------------------------------------------
function save_configuration($data) {
    $org_name = trim($data['org_name'] ?? 'SquirrelMail');
    $domain = trim($data['domain'] ?? 'localhost');
    $imap_host = trim($data['imap_host'] ?? 'localhost');
    $imap_port = intval($data['imap_port'] ?? 143);
    $imap_type = trim($data['imap_type'] ?? 'other');
    $imap_tls = intval($data['imap_tls'] ?? 0);
    $imap_delimiter = trim($data['imap_delimiter'] ?? 'detect');
    $folder_prefix = trim($data['folder_prefix'] ?? '');

    $trash_folder = trim($data['trash_folder'] ?? 'INBOX.Trash');
    $sent_folder = trim($data['sent_folder'] ?? 'INBOX.Sent');
    $draft_folder = trim($data['draft_folder'] ?? 'INBOX.Drafts');

    $use_sendmail = !empty($data['use_sendmail']) && $data['use_sendmail'] === '1';
    $sendmail_path = trim($data['sendmail_path'] ?? '/usr/sbin/sendmail');
    $smtp_host = trim($data['smtp_host'] ?? 'localhost');
    $smtp_port = intval($data['smtp_port'] ?? 25);
    $smtp_tls = intval($data['smtp_tls'] ?? 0);
    $smtp_auth = !empty($data['smtp_auth']) && $data['smtp_auth'] === '1' ? 'login' : 'none';

    $data_dir_input = trim($data['data_dir'] ?? 'SM_PATH . \'data/\'');
    $attach_dir_input = trim($data['attach_dir'] ?? 'SM_PATH . \'attach/\'');

    $default_lang = trim($data['default_lang'] ?? 'en_US');
    $default_charset = trim($data['default_charset'] ?? 'utf-8');
    $skin = trim($data['skin'] ?? 'default');
    $chosen_theme = trim($data['default_theme'] ?? 'gmail');

    // Selected plugins
    $selected_plugins = isset($data['plugins']) && is_array($data['plugins']) ? $data['plugins'] : [];

    // Ensure data and attach directories exist
    if (!is_dir('data')) {
        @mkdir('data', 0770, true);
    }
    if (is_dir('data')) {
        if (!file_exists('data/default_pref') && file_exists('config/default_pref')) {
            @copy('config/default_pref', 'data/default_pref');
        }
        if (!file_exists('data/.htaccess')) {
            @file_put_contents("data/.htaccess", "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
        }
        if (!file_exists('data/index.php')) {
            @file_put_contents("data/index.php", "<?php header('Location: ../index.php'); exit; ?>");
        }
    }
    if (!is_dir('attach')) {
        @mkdir('attach', 0730, true);
    }
    if (is_dir('attach')) {
        if (!file_exists('attach/.htaccess')) {
            @file_put_contents("attach/.htaccess", "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
        }
        if (!file_exists('attach/index.php')) {
            @file_put_contents("attach/index.php", "<?php header('Location: ../index.php'); exit; ?>");
        }
    }

    // Format data_dir and attachment_dir PHP code
    $data_dir_code = (strpos($data_dir_input, 'SM_PATH') === 0 || strpos($data_dir_input, '/') === 0)
        ? (strpos($data_dir_input, 'SM_PATH') === 0 ? $data_dir_input : "'$data_dir_input'")
        : "SM_PATH . '$data_dir_input'";

    $attach_dir_code = (strpos($attach_dir_input, 'SM_PATH') === 0 || strpos($attach_dir_input, '/') === 0)
        ? (strpos($attach_dir_input, 'SM_PATH') === 0 ? $attach_dir_input : "'$attach_dir_input'")
        : "SM_PATH . '$attach_dir_input'";

    // Determine default user theme index
    $user_theme_default_idx = 0;
    if ($chosen_theme === 'modern_responsive_dark') {
        $user_theme_default_idx = 1;
    } elseif ($chosen_theme === 'modern_responsive_emerald') {
        $user_theme_default_idx = 2;
    }

    // Plugins code
    $plugins_code = "\$plugins = array();\n";
    foreach ($selected_plugins as $p) {
        $clean_p = preg_replace('/[^a-zA-Z0-9_-]/', '', $p);
        if (!empty($clean_p) && is_dir("plugins/$clean_p")) {
            $plugins_code .= "\$plugins[] = '$clean_p';\n";
        }
    }

    $config_content = <<<PHP
<?php

/**
 * SquirrelMail Configuration File
 * Generated by SquirrelMail Web Installer (install.php)
 * Generated on: %GEN_DATE%
 */

\$config_version = '1.5.0';
\$config_use_color = 2;

\$org_name      = '%ORG_NAME%';
\$org_logo      = SM_PATH . 'images/sm_logo.png';
\$org_logo_width  = '0';
\$org_logo_height = '0';
\$org_title     = '%ORG_NAME%';
\$signout_page  = '';
\$frame_top     = '_top';

\$provider_uri     = '';
\$provider_name    = '';

\$motd = '';

\$squirrelmail_default_language = '%DEFAULT_LANG%';
\$default_charset          = '%DEFAULT_CHARSET%';
\$show_alternative_names   = false;
\$aggressive_decoding   = false;
\$lossy_encoding        = false;

\$domain                 = '%DOMAIN%';
\$imapServerAddress      = '%IMAP_HOST%';
\$imapPort               = %IMAP_PORT%;
\$useSendmail            = %USE_SENDMAIL%;
\$smtpServerAddress      = '%SMTP_HOST%';
\$smtpPort               = %SMTP_PORT%;
\$sendmail_path          = '%SENDMAIL_PATH%';
\$sendmail_args          = '-i -t';
\$pop_before_smtp        = false;
\$pop_before_smtp_host   = '';
\$imap_server_type       = '%IMAP_TYPE%';
\$invert_time            = false;
\$optional_delimiter     = '%IMAP_DELIMITER%';
\$encode_header_key      = '';

\$default_folder_prefix          = '%FOLDER_PREFIX%';
\$trash_folder                   = '%TRASH_FOLDER%';
\$sent_folder                    = '%SENT_FOLDER%';
\$draft_folder                   = '%DRAFT_FOLDER%';
\$default_move_to_trash          = true;
\$default_move_to_sent           = true;
\$default_save_as_draft          = true;
\$show_prefix_option             = false;
\$list_special_folders_first     = true;
\$use_special_folder_color       = true;
\$auto_expunge                   = true;
\$default_sub_of_inbox           = true;
\$show_contain_subfolders_option = false;
\$default_unseen_notify          = 2;
\$default_unseen_type            = 1;
\$auto_create_special            = true;
\$delete_folder                  = false;
\$noselect_fix_enable            = false;

\$data_dir                 = %DATA_DIR_CODE%;
\$attachment_dir           = %ATTACH_DIR_CODE%;
\$dir_hash_level           = 0;
\$default_left_size        = '150';
\$force_username_lowercase = false;
\$default_use_priority     = false;
\$hide_sm_attributions     = false;
\$default_use_mdn          = true;
\$edit_identity            = true;
\$edit_name                = true;
\$edit_reply_to            = true;
\$hide_auth_header         = false;
\$disable_thread_sort      = false;
\$disable_server_sort      = false;
\$allow_charset_search     = true;
\$allow_advanced_search    = 1;

\$time_zone_type           = 1;
\$config_location_base     = '';

\$disable_plugins          = false;
\$disable_plugins_user     = '';

%PLUGINS_CODE%

\$user_theme_default = %USER_THEME_DEF%;

\$user_themes[0]['PATH'] = '../css/modern_responsive/';
\$user_themes[0]['NAME'] = 'Modern Responsive';

\$user_themes[1]['PATH'] = '../css/modern_responsive_dark/';
\$user_themes[1]['NAME'] = 'Modern Responsive Dark';

\$user_themes[2]['PATH'] = '../css/modern_responsive_emerald/';
\$user_themes[2]['NAME'] = 'Modern Responsive Emerald';

\$icon_theme_def = 2;
\$icon_theme_fallback = 2;

\$icon_themes[0]['PATH'] = 'none';
\$icon_themes[0]['NAME'] = 'No Icons';

\$icon_themes[1]['PATH'] = 'template';
\$icon_themes[1]['NAME'] = 'Template Default Icons';

\$icon_themes[2]['PATH'] = '../images/themes/modern/';
\$icon_themes[2]['NAME'] = 'Modern SVG Icons';

\$templateset_default = '%SKIN%';
\$templateset_fallback = 'default';
\$rpc_templateset = 'default_rpc';

\$aTemplateSet[0]['ID'] = 'default';
\$aTemplateSet[0]['NAME'] = 'Default';

\$default_fontsize = '';
\$default_fontset = '';
\$fontsets = array();

\$default_use_javascript_addr_book = false;
\$ldap_server = array();
\$addrbook_dsn = '';
\$addrbook_table = '';
\$prefs_dsn = '';
\$prefs_table = '';
\$prefs_user_field = '';
\$prefs_user_size = 0;
\$prefs_key_field = '';
\$prefs_key_size = 0;
\$prefs_val_field = '';
\$prefs_val_size = 0;
\$addrbook_global_dsn = '';
\$addrbook_global_table = '';
\$addrbook_global_writeable = false;
\$addrbook_global_listing = false;
\$abook_global_file = '';
\$abook_global_file_writeable = false;
\$abook_global_file_listing = false;
\$abook_file_line_length = 2048;
\$no_list_for_subscribe = false;

\$smtp_auth_mech        = '%SMTP_AUTH%';
\$smtp_sitewide_user    = '';
\$smtp_sitewide_pass    = '';
\$imap_auth_mech        = 'login';
\$use_imap_tls          = %IMAP_TLS%;
\$use_smtp_tls          = %SMTP_TLS%;
\$display_imap_login_error = true;
\$session_name          = 'SQMSESSID';
\$only_secure_cookies     = false;
\$disable_security_tokens = false;
\$check_referrer          = '';

\$use_transparent_security_image = true;
\$treat_svg_separate_from_unsafe_images = true;
\$allow_svg_display = false;
\$block_svg_download = true;
\$fix_broken_base64_encoded_messages = true;

\$use_iframe = false;
\$ask_user_info = false;
\$use_icons = true;
\$use_php_recode = false;
\$use_php_iconv = true;
\$buffer_output = true;
\$allow_remote_configtest = true;
\$secured_config = true;
\$sq_https_port = 443;
\$sq_ignore_http_x_forwarded_headers = false;
PHP;

    // Replace template tokens
    $replacements = [
        '%GEN_DATE%'        => date('r'),
        '%ORG_NAME%'        => addslashes($org_name),
        '%DOMAIN%'          => addslashes($domain),
        '%IMAP_HOST%'       => addslashes($imap_host),
        '%IMAP_PORT%'       => $imap_port,
        '%IMAP_TYPE%'       => addslashes($imap_type),
        '%IMAP_TLS%'        => $imap_tls,
        '%IMAP_DELIMITER%'  => addslashes($imap_delimiter),
        '%FOLDER_PREFIX%'   => addslashes($folder_prefix),
        '%TRASH_FOLDER%'    => addslashes($trash_folder),
        '%SENT_FOLDER%'     => addslashes($sent_folder),
        '%DRAFT_FOLDER%'    => addslashes($draft_folder),
        '%USE_SENDMAIL%'    => $use_sendmail ? 'true' : 'false',
        '%SMTP_HOST%'       => addslashes($smtp_host),
        '%SMTP_PORT%'       => $smtp_port,
        '%SMTP_TLS%'        => $smtp_tls,
        '%SMTP_AUTH%'       => addslashes($smtp_auth),
        '%SENDMAIL_PATH%'   => addslashes($sendmail_path),
        '%DATA_DIR_CODE%'   => $data_dir_code,
        '%ATTACH_DIR_CODE%' => $attach_dir_code,
        '%DEFAULT_LANG%'    => addslashes($default_lang),
        '%DEFAULT_CHARSET%' => addslashes($default_charset),
        '%SKIN%'            => addslashes($skin),
        '%USER_THEME_DEF%'  => $user_theme_default_idx,
        '%PLUGINS_CODE%'    => $plugins_code,
    ];

    $final_code = str_replace(array_keys($replacements), array_values($replacements), $config_content);

    // Write to config/config.php
    $target = 'config/config.php';
    if (!is_writable('config') && (!file_exists($target) || !is_writable($target))) {
        return ['success' => false, 'message' => "The directory 'config/' or 'config/config.php' is not writable by the web server."];
    }

    $written = @file_put_contents($target, $final_code);
    if ($written === false) {
        return ['success' => false, 'message' => "Failed to write configuration to $target. Check directory permissions."];
    }

    // Rebuild plugin_hooks.php if plugins are selected
    if (!empty($selected_plugins)) {
        global $squirrelmail_plugin_hooks;
        $squirrelmail_plugin_hooks = array();
        foreach ($selected_plugins as $p) {
            $clean_p = preg_replace('/[^a-zA-Z0-9_-]/', '', $p);
            $setup = "plugins/$clean_p/setup.php";
            if (file_exists($setup)) {
                require_once($setup);
                $fn = "squirrelmail_plugin_init_$clean_p";
                if (function_exists($fn)) {
                    $fn();
                }
            }
        }
        $hook_content = "<?php\n\n/**\n * SquirrelMail Plugin Hook Registration File\n * Auto-generated by install.php\n */\nglobal \$squirrelmail_plugin_hooks;\n\$squirrelmail_plugin_hooks = array();\n\n";
        foreach ($squirrelmail_plugin_hooks as $hook => $hooked) {
            foreach ($hooked as $plug => $func) {
                $hook_content .= "\$squirrelmail_plugin_hooks['" . addslashes($hook) . "']['" . addslashes($plug) . "'] = '" . addslashes($func) . "';\n";
            }
        }
        @file_put_contents('config/plugin_hooks.php', $hook_content);
    }

    // Save administrator whitelist if administrator plugin is active
    if (in_array('administrator', $selected_plugins) && (isset($data['admin_users']) || !file_exists('config/admins'))) {
        $raw_admins = isset($data['admin_users']) ? $data['admin_users'] : '';
        $admin_list = [];
        $split_admins = preg_split('/[\r\n,;]+/', $raw_admins);
        foreach ($split_admins as $admin_entry) {
            $admin_entry = trim($admin_entry);
            if (!empty($admin_entry)) {
                $admin_list[] = $admin_entry;
            }
        }
        $admin_list = array_unique($admin_list);
        if (!empty($admin_list)) {
            $admins_content = "# SquirrelMail Administrator Whitelist\n# Configured via install.php\n" . implode("\n", $admin_list) . "\n";
            @file_put_contents('config/admins', $admins_content);
        } elseif (!file_exists('config/admins')) {
            $admins_content = "# SquirrelMail Administrator Whitelist\n# Configured via install.php\nadmin\n";
            @file_put_contents('config/admins', $admins_content);
        }
    }

    return [
        'success' => true,
        'message' => 'SquirrelMail installed and configured successfully!',
        'config_file' => $target
    ];
}

// -----------------------------------------------------------------------------
// Diagnostics & Pre-flight checks
// -----------------------------------------------------------------------------
$php_version = PHP_VERSION;
$php_ok = version_compare(PHP_VERSION, '5.6.0', '>=');

$req_extensions = [
    'session' => extension_loaded('session'),
    'pcre'    => extension_loaded('pcre'),
    'filter'  => extension_loaded('filter'),
];
$opt_extensions = [
    'mbstring' => extension_loaded('mbstring'),
    'openssl'  => extension_loaded('openssl'),
    'iconv'    => extension_loaded('iconv'),
    'curl'     => extension_loaded('curl'),
    'dom'      => extension_loaded('dom'),
];

$config_writable = is_writable('config') || (file_exists('config/config.php') && is_writable('config/config.php'));
$data_exists = is_dir('data');
$data_writable = $data_exists && is_writable('data');
$attach_exists = is_dir('attach');
$attach_writable = $attach_exists && is_writable('attach');

$is_already_installed = file_exists('config/config.php');
$existing_org = 'SquirrelMail';
$existing_server = 'localhost';
if ($is_already_installed) {
    @include('config/config.php');
    if (isset($org_name)) $existing_org = $org_name;
    if (isset($imapServerAddress)) $existing_server = $imapServerAddress;
}

// Load existing administrator whitelist if available
$existing_admins = '';
if (file_exists('config/admins') && is_readable('config/admins')) {
    $admin_lines = file('config/admins', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $clean_admins = [];
    if ($admin_lines !== false) {
        foreach ($admin_lines as $l) {
            $l = trim($l);
            if ($l !== '' && $l[0] !== '#') {
                $clean_admins[] = $l;
            }
        }
    }
    $existing_admins = implode(', ', $clean_admins);
}
if (empty($existing_admins)) {
    $existing_admins = 'koen, koen@thechargegrid.com, admin, admin@thechargegrid.com';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SquirrelMail Web Installer and Configuration Assistant">
    <title>SquirrelMail Web Installer</title>
    <style>
        :root {
            --primary: #1a73e8;
            --primary-hover: #1557b0;
            --primary-light: #e8f0fe;
            --accent-red: #ea4335;
            --accent-green: #34a853;
            --accent-yellow: #fbbc04;
            --surface: #ffffff;
            --bg: #f6f8fc;
            --card-border: #dadce0;
            --text-main: #202124;
            --text-muted: #5f6368;
            --radius-card: 16px;
            --radius-sm: 8px;
            --radius-pill: 24px;
            --shadow: 0 2px 10px rgba(60,64,67,0.08);
            --shadow-lg: 0 8px 24px rgba(60,64,67,0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Brand Bar */
        .app-bar {
            background-color: var(--surface);
            border-bottom: 1px solid var(--card-border);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #1a73e8, #ea4335);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            box-shadow: 0 2px 6px rgba(26,115,232,0.3);
        }

        .brand-title {
            font-size: 19px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.2px;
        }

        .brand-subtitle {
            font-size: 13px;
            color: var(--text-muted);
        }

        .version-badge {
            background-color: var(--primary-light);
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
        }

        /* Main Container */
        .main-container {
            max-width: 900px;
            margin: 32px auto;
            padding: 0 20px;
            flex: 1;
            width: 100%;
        }

        /* Notice Banner */
        .alert-banner {
            background-color: #e8f0fe;
            border: 1px solid #c2e7ff;
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .alert-banner.warning {
            background-color: #fef7e0;
            border-color: #feefc3;
            color: #b06000;
        }

        .alert-banner.success {
            background-color: #e6f4ea;
            border-color: #ceead6;
            color: #137333;
        }

        /* Step Navigation Bar */
        .step-nav {
            display: flex;
            background: var(--surface);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-pill);
            padding: 6px;
            margin-bottom: 28px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .step-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            background: transparent;
            border: none;
            border-radius: var(--radius-pill);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .step-btn:hover {
            color: var(--primary);
            background-color: var(--bg);
        }

        .step-btn.active {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(26,115,232,0.3);
        }

        .step-btn.completed {
            color: var(--accent-green);
        }

        .step-number {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            background: rgba(0,0,0,0.06);
        }

        .step-btn.active .step-number {
            background: rgba(255,255,255,0.25);
            color: #ffffff;
        }

        /* Content Card */
        .card {
            background-color: var(--surface);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-card);
            padding: 32px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .card-header {
            margin-bottom: 24px;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 16px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-desc {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Diagnostics Grid */
        .diag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .diag-item {
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            padding: 16px;
            background-color: var(--bg);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .diag-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .diag-name {
            font-weight: 600;
            font-size: 14px;
        }

        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .badge.pass {
            background-color: #e6f4ea;
            color: #137333;
        }

        .badge.warn {
            background-color: #fef7e0;
            color: #b06000;
        }

        .badge.fail {
            background-color: #fce8e6;
            color: #c5221f;
        }

        .diag-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Form Controls */
        .form-section {
            margin-bottom: 24px;
        }

        .form-section-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .field-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        input[type="text"],
        input[type="number"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--text-main);
            background-color: var(--surface);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,115,232,0.18);
        }

        /* Preset Card Selector */
        .preset-select-wrap {
            margin-bottom: 20px;
        }

        /* Theme Cards */
        .theme-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .theme-card {
            border: 2px solid var(--card-border);
            border-radius: var(--radius-sm);
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: var(--surface);
        }

        .theme-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .theme-card.selected {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .theme-card.featured {
            border-color: var(--primary);
        }

        .theme-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--accent-red);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
        }

        .theme-palette {
            display: flex;
            gap: 4px;
            margin: 10px 0;
        }

        .swatch {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .theme-name {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-main);
        }

        .theme-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Plugins Grid */
        .plugins-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .plugin-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px;
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
            cursor: pointer;
        }

        .plugin-check:hover {
            background-color: var(--bg);
        }

        /* Button Row */
        .btn-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--card-border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            border-radius: var(--radius-pill);
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(26,115,232,0.3);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 4px 12px rgba(26,115,232,0.4);
        }

        .btn-secondary {
            background-color: var(--surface);
            color: var(--text-main);
            border-color: var(--card-border);
        }

        .btn-secondary:hover {
            background-color: var(--bg);
            border-color: #b0b4b9;
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }

        .btn-test {
            background-color: var(--primary-light);
            color: var(--primary);
            border: 1px solid #c2e7ff;
        }

        .btn-test:hover {
            background-color: #d2e3fc;
        }

        /* Test Result Toast / Area */
        .test-output {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            display: none;
        }

        .test-output.success {
            display: block;
            background-color: #e6f4ea;
            border: 1px solid #ceead6;
            color: #137333;
        }

        .test-output.error {
            display: block;
            background-color: #fce8e6;
            border: 1px solid #fad2cf;
            color: #c5221f;
        }

        /* Success Screen */
        .success-box {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            width: 72px;
            height: 72px;
            background: #e6f4ea;
            color: #137333;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px auto;
        }

        .success-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .success-desc {
            color: var(--text-muted);
            max-width: 500px;
            margin: 0 auto 24px auto;
        }

        .action-links {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* Responsive */
        @media (max-width: 650px) {
            .form-row, .plugins-grid {
                grid-template-columns: 1fr;
            }
            .step-btn span.step-text {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- App Header -->
    <header class="app-bar">
        <div class="brand">
            <div class="brand-icon">S</div>
            <div>
                <div class="brand-title">SquirrelMail</div>
                <div class="brand-subtitle">Web Installer &amp; Configuration Assistant</div>
            </div>
        </div>
        <div class="version-badge">SquirrelMail <?php echo htmlspecialchars(INSTALLER_VERSION); ?></div>
    </header>

    <main class="main-container">

        <!-- If SquirrelMail is already configured -->
        <?php if ($is_already_installed && empty($_GET['reconfigure'])): ?>
            <div class="alert-banner">
                <div>
                    <strong>SquirrelMail is already installed &amp; configured!</strong>
                    <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                        Organization: <strong><?php echo htmlspecialchars($existing_org); ?></strong> &bull; Mail Server: <strong><?php echo htmlspecialchars($existing_server); ?></strong>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="src/login.php" class="btn btn-primary btn-sm" id="btn-login-installed">Go to Login</a>
                    <a href="src/configtest.php" class="btn btn-secondary btn-sm" id="btn-diag-installed">Config Test</a>
                    <a href="install.php?reconfigure=1" class="btn btn-secondary btn-sm" id="btn-reconfigure">Reconfigure</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Step Navigation Wizard Tabs -->
        <nav class="step-nav" id="step-nav">
            <button type="button" class="step-btn active" data-step="1" id="tab-step-1">
                <span class="step-number">1</span>
                <span class="step-text">System Check</span>
            </button>
            <button type="button" class="step-btn" data-step="2" id="tab-step-2">
                <span class="step-number">2</span>
                <span class="step-text">Mail Server</span>
            </button>
            <button type="button" class="step-btn" data-step="3" id="tab-step-3">
                <span class="step-number">3</span>
                <span class="step-text">Site &amp; Storage</span>
            </button>
            <button type="button" class="step-btn" data-step="4" id="tab-step-4">
                <span class="step-number">4</span>
                <span class="step-text">Theme &amp; Skin</span>
            </button>
            <button type="button" class="step-btn" data-step="5" id="tab-step-5">
                <span class="step-number">5</span>
                <span class="step-text">Install</span>
            </button>
        </nav>

        <!-- Form Wrapper -->
        <form id="install-form" method="POST" action="install.php">

            <!-- STEP 1: System Check -->
            <section class="card wizard-step" id="step-content-1">
                <div class="card-header">
                    <h2 class="card-title">1. System &amp; Environment Diagnostics</h2>
                    <p class="card-desc">Check PHP runtime, extensions, and directory write permissions.</p>
                </div>

                <div class="diag-grid">
                    <!-- PHP Version -->
                    <div class="diag-item">
                        <div class="diag-header">
                            <span class="diag-name">PHP Version</span>
                            <span class="badge <?php echo $php_ok ? 'pass' : 'fail'; ?>"><?php echo $php_ok ? 'OK' : 'Error'; ?></span>
                        </div>
                        <p class="diag-desc">Detected: <strong>PHP <?php echo htmlspecialchars($php_version); ?></strong> (Requires &gt;= 5.6)</p>
                    </div>

                    <!-- Config Directory -->
                    <div class="diag-item">
                        <div class="diag-header">
                            <span class="diag-name">config/ Directory</span>
                            <span class="badge <?php echo $config_writable ? 'pass' : 'fail'; ?>"><?php echo $config_writable ? 'Writable' : 'Not Writable'; ?></span>
                        </div>
                        <p class="diag-desc"><?php echo $config_writable ? 'Ready to write config.php' : 'Please grant write permissions to config/'; ?></p>
                    </div>

                    <!-- Data Directory -->
                    <div class="diag-item" id="diag-data-box">
                        <div class="diag-header">
                            <span class="diag-name">data/ Storage</span>
                            <span class="badge <?php echo $data_writable ? 'pass' : ($data_exists ? 'warn' : 'warn'); ?>" id="badge-data">
                                <?php echo $data_writable ? 'Ready' : ($data_exists ? 'Check Perms' : 'Missing'); ?>
                            </span>
                        </div>
                        <p class="diag-desc" id="desc-data">User preferences storage (auto-created on setup)</p>
                    </div>

                    <!-- Attach Directory -->
                    <div class="diag-item" id="diag-attach-box">
                        <div class="diag-header">
                            <span class="diag-name">attach/ Storage</span>
                            <span class="badge <?php echo $attach_writable ? 'pass' : ($attach_exists ? 'warn' : 'warn'); ?>" id="badge-attach">
                                <?php echo $attach_writable ? 'Ready' : ($attach_exists ? 'Check Perms' : 'Missing'); ?>
                            </span>
                        </div>
                        <p class="diag-desc" id="desc-attach">Temporary attachment uploads storage</p>
                    </div>
                </div>

                <!-- PHP Extensions Check -->
                <div class="form-section">
                    <h3 class="form-section-title">PHP Extensions</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach (array_merge($req_extensions, $opt_extensions) as $ext => $loaded): ?>
                            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: var(--radius-sm); border: 1px solid var(--card-border); font-size: 12px; background: <?php echo $loaded ? '#e6f4ea' : '#fce8e6'; ?>;">
                                <strong style="color: <?php echo $loaded ? '#137333' : '#c5221f'; ?>;"><?php echo $loaded ? '✓' : '✗'; ?></strong> <?php echo htmlspecialchars($ext); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Fix Directories Button -->
                <?php if (!$data_writable || !$attach_writable): ?>
                    <div style="margin-top: 16px; padding: 12px; background: #e8f0fe; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 13px; color: var(--text-main);">Storage directories can be created and secured automatically.</span>
                        <button type="button" class="btn btn-primary btn-sm" id="btn-create-dirs">Auto-Create Directories</button>
                    </div>
                <?php endif; ?>

                <div class="btn-row">
                    <span></span>
                    <button type="button" class="btn btn-primary next-step" data-next="2" id="btn-next-1">Next: Mail Server &rarr;</button>
                </div>
            </section>

            <!-- STEP 2: Mail Server -->
            <section class="card wizard-step" id="step-content-2" style="display: none;">
                <div class="card-header">
                    <h2 class="card-title">2. Mail Server Settings (IMAP &amp; SMTP)</h2>
                    <p class="card-desc">Choose a pre-configured preset or enter your custom mail server host and credentials.</p>
                </div>

                <!-- Server Preset Selector -->
                <div class="preset-select-wrap">
                    <label for="server_preset">Mail Server Preset</label>
                    <select id="server_preset" name="server_preset">
                        <option value="custom">Standard / Custom IMAP &amp; SMTP</option>
                        <option value="gmail" selected>Gmail / Google Workspace (imap.gmail.com / smtp.gmail.com)</option>
                        <option value="combell">Combell Mail (imap.mailprotect.be / smtp-auth.mailprotect.be)</option>
                        <option value="dovecot">Dovecot IMAP Server</option>
                        <option value="courier">Courier IMAP Server</option>
                        <option value="cyrus">Cyrus IMAP Server</option>
                        <option value="exchange">Microsoft Exchange IMAP</option>
                        <option value="uw">University of Washington (UW-IMAP)</option>
                    </select>
                    <div class="field-desc">Selecting a preset automatically pre-fills standard ports, folder prefix, and security settings.</div>
                </div>

                <!-- IMAP Settings -->
                <div class="form-section">
                    <h3 class="form-section-title">IMAP Settings (Incoming Mail)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="imap_host">IMAP Server Address</label>
                            <input type="text" id="imap_host" name="imap_host" value="imap.gmail.com" required>
                        </div>
                        <div class="form-group">
                            <label for="imap_port">IMAP Port</label>
                            <input type="number" id="imap_port" name="imap_port" value="993" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="imap_tls">IMAP Encryption</label>
                            <select id="imap_tls" name="imap_tls">
                                <option value="0">Plain Text (No Encryption)</option>
                                <option value="1" selected>SSL / TLS (imaps - e.g. Port 993)</option>
                                <option value="2">STARTTLS (RFC 2595)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="imap_type">IMAP Server Type</label>
                            <select id="imap_type" name="imap_type">
                                <option value="gmail" selected>Gmail</option>
                                <option value="dovecot">Dovecot</option>
                                <option value="courier">Courier</option>
                                <option value="cyrus">Cyrus</option>
                                <option value="exchange">Exchange</option>
                                <option value="uw">UW-IMAP</option>
                                <option value="other">Other / Standard</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="folder_prefix">Default Folder Prefix</label>
                            <input type="text" id="folder_prefix" name="folder_prefix" value="">
                            <div class="field-desc">E.g. empty for Gmail/Dovecot, 'INBOX.' for Courier, 'mail/' for UW</div>
                        </div>
                        <div class="form-group">
                            <label for="imap_delimiter">Folder Delimiter</label>
                            <select id="imap_delimiter" name="imap_delimiter">
                                <option value="detect" selected>Auto-Detect</option>
                                <option value="/">/ (Slash)</option>
                                <option value=".">. (Dot)</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 8px;">
                        <button type="button" class="btn btn-test btn-sm" id="btn-test-imap">Test IMAP Connection</button>
                        <div class="test-output" id="test-imap-output"></div>
                    </div>
                </div>

                <!-- SMTP Settings -->
                <div class="form-section">
                    <h3 class="form-section-title">SMTP Settings (Outgoing Mail)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Server Address</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="smtp.gmail.com" required>
                        </div>
                        <div class="form-group">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="465" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_tls">SMTP Encryption</label>
                            <select id="smtp_tls" name="smtp_tls">
                                <option value="0">Plain Text (No Encryption)</option>
                                <option value="1" selected>SSL / TLS (ssmtp - e.g. Port 465)</option>
                                <option value="2">STARTTLS (Submission - e.g. Port 587)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="smtp_auth">SMTP Authentication</label>
                            <select id="smtp_auth" name="smtp_auth">
                                <option value="1" selected>Yes (Authenticate using user login credentials)</option>
                                <option value="0">No (Unauthenticated relay)</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 8px;">
                        <button type="button" class="btn btn-test btn-sm" id="btn-test-smtp">Test SMTP Connection</button>
                        <div class="test-output" id="test-smtp-output"></div>
                    </div>
                </div>

                <!-- Special Folders -->
                <div class="form-section">
                    <h3 class="form-section-title">Special Folders</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="trash_folder">Trash Folder</label>
                            <input type="text" id="trash_folder" name="trash_folder" value="[Gmail]/Trash">
                        </div>
                        <div class="form-group">
                            <label for="sent_folder">Sent Folder</label>
                            <input type="text" id="sent_folder" name="sent_folder" value="[Gmail]/Sent Mail">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="draft_folder">Drafts Folder</label>
                        <input type="text" id="draft_folder" name="draft_folder" value="[Gmail]/Drafts">
                    </div>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn btn-secondary prev-step" data-prev="1">&larr; Back</button>
                    <button type="button" class="btn btn-primary next-step" data-next="3" id="btn-next-2">Next: Site Settings &rarr;</button>
                </div>
            </section>

            <!-- STEP 3: Site & Storage Settings -->
            <section class="card wizard-step" id="step-content-3" style="display: none;">
                <div class="card-header">
                    <h2 class="card-title">3. Site Branding &amp; Storage Settings</h2>
                    <p class="card-desc">Configure your webmail title, email domain, and storage paths.</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="org_name">Organization / Webmail Name</label>
                        <input type="text" id="org_name" name="org_name" value="SquirrelMail Webmail" required>
                    </div>
                    <div class="form-group">
                        <label for="domain">Mail Domain</label>
                        <input type="text" id="domain" name="domain" value="gmail.com" required>
                        <div class="field-desc">Default domain for outgoing email addresses.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="data_dir">Preferences Directory (data_dir)</label>
                        <input type="text" id="data_dir" name="data_dir" value="SM_PATH . 'data/'" required>
                        <div class="field-desc">Directory where user address books &amp; preferences are stored.</div>
                    </div>
                    <div class="form-group">
                        <label for="attach_dir">Attachment Directory (attachment_dir)</label>
                        <input type="text" id="attach_dir" name="attach_dir" value="SM_PATH . 'attach/'" required>
                        <div class="field-desc">Directory where temporary attachments are buffered.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="default_lang">Default Interface Language</label>
                        <select id="default_lang" name="default_lang">
                            <option value="en_US" selected>English (en_US)</option>
                            <option value="nl_NL">Nederlands (nl_NL)</option>
                            <option value="de_DE">Deutsch (de_DE)</option>
                            <option value="fr_FR">Français (fr_FR)</option>
                            <option value="es_ES">Español (es_ES)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="default_charset">Default Character Set</label>
                        <select id="default_charset" name="default_charset">
                            <option value="utf-8" selected>UTF-8 (Recommended)</option>
                            <option value="iso-8859-1">ISO-8859-1 (Western)</option>
                        </select>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn btn-secondary prev-step" data-prev="2">&larr; Back</button>
                    <button type="button" class="btn btn-primary next-step" data-next="4" id="btn-next-3">Next: Theme &amp; Skin &rarr;</button>
                </div>
            </section>

            <!-- STEP 4: Theme, Skin & Plugins -->
            <section class="card wizard-step" id="step-content-4" style="display: none;">
                <div class="card-header">
                    <h2 class="card-title">4. Theme, Skin &amp; Plugins</h2>
                    <p class="card-desc">Activate the modern Gmail theme and choose default skins and plugins.</p>
                </div>

                <!-- Theme Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">Select Default Theme</h3>
                    <input type="hidden" id="default_theme" name="default_theme" value="modern_responsive">

                    <div class="theme-cards">
                        <!-- Modern Responsive Theme (Default Blue) -->
                        <div class="theme-card featured selected" data-theme="modern_responsive" id="theme-card-modern_responsive">
                            <span class="theme-badge">DEFAULT &bull; BLUE</span>
                            <div class="theme-name">Modern Responsive</div>
                            <div class="theme-desc">Contemporary clean aesthetic with tailored HSL palette, royal blue accents (#2563eb), and full responsiveness.</div>
                            <div class="theme-palette">
                                <div class="swatch" style="background: #2563eb;" title="Royal Blue"></div>
                                <div class="swatch" style="background: #0f172a;" title="Slate 900"></div>
                                <div class="swatch" style="background: #f8fafc;" title="Slate 50"></div>
                                <div class="swatch" style="background: #ffffff;" title="Clean White"></div>
                                <div class="swatch" style="background: #e2e8f0;" title="Slate 200"></div>
                            </div>
                            <div style="font-size: 11px; color: var(--primary); font-weight: 600; margin-top: 6px;">✓ Active Theme (Mobile Ready)</div>
                        </div>

                        <!-- Modern Responsive Dark -->
                        <div class="theme-card" data-theme="modern_responsive_dark" id="theme-card-modern_responsive_dark">
                            <span class="theme-badge" style="background: #0ea5e9; color: #fff;">DARK &bull; MIDNIGHT</span>
                            <div class="theme-name">Modern Responsive Dark</div>
                            <div class="theme-desc">Deep midnight navy dark mode aesthetic with electric cyan accents (#38bdf8), card surfaces, and high contrast.</div>
                            <div class="theme-palette">
                                <div class="swatch" style="background: #38bdf8;" title="Electric Cyan"></div>
                                <div class="swatch" style="background: #0b1120;" title="Midnight Dark"></div>
                                <div class="swatch" style="background: #111827;" title="Surface Dark"></div>
                                <div class="swatch" style="background: #1e293b;" title="Card Dark"></div>
                                <div class="swatch" style="background: #f8fafc;" title="White Text"></div>
                            </div>
                        </div>

                        <!-- Modern Responsive Emerald -->
                        <div class="theme-card" data-theme="modern_responsive_emerald" id="theme-card-modern_responsive_emerald">
                            <span class="theme-badge" style="background: #059669; color: #fff;">FRESH &bull; EMERALD</span>
                            <div class="theme-name">Modern Responsive Emerald</div>
                            <div class="theme-desc">Fresh mint &amp; vibrant emerald green aesthetic (#059669) with crisp white cards and soft mint-tinted sidebar.</div>
                            <div class="theme-palette">
                                <div class="swatch" style="background: #059669;" title="Emerald Green"></div>
                                <div class="swatch" style="background: #064e3b;" title="Forest Dark"></div>
                                <div class="swatch" style="background: #f0fdf4;" title="Mint Tint"></div>
                                <div class="swatch" style="background: #ffffff;" title="Clean White"></div>
                                <div class="swatch" style="background: #a7f3d0;" title="Mint Border"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Template Set / Skin -->
                <div class="form-section">
                    <h3 class="form-section-title">Skin / Template Engine</h3>
                    <div class="form-group">
                        <label for="skin">Default Skin Set</label>
                        <select id="skin" name="skin">
                            <option value="default" selected>Default (Modern Responsive Single-Page Application)</option>
                        </select>
                        <div class="field-desc">Modern responsive template engine with SPA navigation and mobile support.</div>
                    </div>
                </div>

                <!-- Plugins -->
                <div class="form-section">
                    <h3 class="form-section-title">Recommended Plugins to Enable</h3>
                    <div class="plugins-grid">
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="html_mail" checked>
                            <div>
                                <strong>html_mail</strong>
                                <div class="field-desc">Rich HTML WYSIWYG compose &amp; multipart/alternative delivery.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="ai_agent" checked>
                            <div>
                                <strong>ai_agent</strong>
                                <div class="field-desc">AI Email Assistant (Gemini 3.8: spam filter, auto-label, drafts, scam check).</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="multi_account" checked>
                            <div>
                                <strong>multi_account</strong>
                                <div class="field-desc">Unified multi-account inbox, account switcher, and identity management.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="abook_import_export" checked>
                            <div>
                                <strong>abook_import_export</strong>
                                <div class="field-desc">Import/export contacts (CSV, Google, Outlook, vCard .vcf, LDIF).</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="calendar" checked>
                            <div>
                                <strong>calendar</strong>
                                <div class="field-desc">Modern web calendar (Month/Agenda, iCal .ics sync, email meeting scheduler).</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="autoresponder" checked>
                            <div>
                                <strong>autoresponder</strong>
                                <div class="field-desc">Out of Office vacation responder &amp; email forwarding with date scheduler.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="message_labels" checked>
                            <div>
                                <strong>message_labels</strong>
                                <div class="field-desc">Gmail-style colored flags &amp; labels (Work, Personal, Urgent, custom tags).</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="newmail_notify" checked>
                            <div>
                                <strong>newmail_notify</strong>
                                <div class="field-desc">Desktop push notifications, floating in-app popup cards, and audio chime alerts.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="spam_buttons" checked>
                            <div>
                                <strong>spam_buttons</strong>
                                <div class="field-desc">Report Spam &amp; Not Spam buttons with automatic Gemini AI training and learning.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="templates" checked>
                            <div>
                                <strong>templates</strong>
                                <div class="field-desc">Reply &amp; email templates with automatic attachment support (PDFs, brochures, forms).</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="message_details" checked>
                            <div>
                                <strong>message_details</strong>
                                <div class="field-desc">View full RFC headers and raw message source.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="filters" checked>
                            <div>
                                <strong>filters</strong>
                                <div class="field-desc">Automated message sorting into folders based on criteria and background cron processing.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="signature_creator" checked>
                            <div>
                                <strong>signature_creator</strong>
                                <div class="field-desc">Modern visual HTML email signature designer with 6 pre-designed responsive templates.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="conversation_view" checked>
                            <div>
                                <strong>conversation_view</strong>
                                <div class="field-desc">Message-view conversation threading, showing sent replies and pending drafts with inline preview &amp; resume editing.</div>
                            </div>
                        </label>
                        <label class="plugin-check">
                            <input type="checkbox" name="plugins[]" value="administrator" id="plugin-administrator-check">
                            <div>
                                <strong>administrator</strong>
                                <div class="field-desc">Web-based SquirrelMail configuration management panel for authorized administrators.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Administrator Whitelist Settings -->
                <div class="form-section" id="admin-whitelist-card" style="display: none; margin-top: 24px; padding: 20px; background: var(--bg); border: 1px solid var(--card-border); border-radius: var(--radius-sm);">
                    <h3 class="form-section-title" style="margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                        <span>🔐</span> Web Administration Allowed Logins &amp; Email Addresses
                    </h3>
                    <p class="field-desc" style="margin-bottom: 14px;">
                        Specify login usernames or full email addresses allowed to view and access the <strong>Administration</strong> panel in Webmail Options. Multiple entries can be separated by commas or line breaks. Saved to <code>config/admins</code>.
                    </p>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="admin_users" class="form-label">Authorized Administrator Logins / Email Addresses</label>
                        <textarea name="admin_users" id="admin_users" class="form-control" rows="3" placeholder="e.g. koen, admin@thechargegrid.com, admin"><?php echo htmlspecialchars($existing_admins, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="field-desc">Only users matching these logins or email addresses will see the Administration block in Options.</small>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="button" class="btn btn-secondary prev-step" data-prev="3">&larr; Back</button>
                    <button type="button" class="btn btn-primary next-step" data-next="5" id="btn-next-4">Next: Review &amp; Install &rarr;</button>
                </div>
            </section>

            <!-- STEP 5: Review & Install -->
            <section class="card wizard-step" id="step-content-5" style="display: none;">
                <div class="card-header">
                    <h2 class="card-title">5. Review &amp; Install</h2>
                    <p class="card-desc">Review your configuration before saving to <code>config/config.php</code>.</p>
                </div>

                <div id="install-summary-box" style="background: var(--bg); border: 1px solid var(--card-border); border-radius: var(--radius-sm); padding: 18px; margin-bottom: 24px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                        <div><strong>Webmail Name:</strong> <span id="sum-org">SquirrelMail Webmail</span></div>
                        <div><strong>Mail Domain:</strong> <span id="sum-domain">gmail.com</span></div>
                        <div><strong>IMAP Server:</strong> <span id="sum-imap">imap.gmail.com:993</span></div>
                        <div><strong>SMTP Server:</strong> <span id="sum-smtp">smtp.gmail.com:465</span></div>
                        <div><strong>IMAP Server Type:</strong> <span id="sum-type">gmail</span></div>
                        <div><strong>Active Theme:</strong> <span id="sum-theme" style="color: var(--primary); font-weight: 700;">Gmail Theme</span></div>
                        <div style="grid-column: span 2;"><strong>Admin Whitelist:</strong> <span id="sum-admins" style="color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($existing_admins, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    </div>
                </div>

                <div id="install-error-box" style="display: none; padding: 12px; margin-bottom: 16px; background: #fce8e6; border: 1px solid #fad2cf; color: #c5221f; border-radius: var(--radius-sm); font-size: 13px;"></div>

                <div class="btn-row" id="install-btn-row">
                    <button type="button" class="btn btn-secondary prev-step" data-prev="4">&larr; Back</button>
                    <button type="button" class="btn btn-primary" id="btn-run-install" style="background: var(--accent-green); border-color: var(--accent-green);">
                        ✓ Install SquirrelMail Now
                    </button>
                </div>

                <!-- Success Screen View -->
                <div id="success-screen" style="display: none;" class="success-box">
                    <div class="success-icon">✓</div>
                    <h2 class="success-title">Installation Complete!</h2>
                    <p class="success-desc">
                        SquirrelMail has been successfully installed and configured. The <strong>Gmail theme</strong> has been activated for your installation.
                    </p>
                    <div class="action-links">
                        <a href="src/login.php" class="btn btn-primary" id="btn-go-login">Launch SquirrelMail &rarr;</a>
                        <a href="src/configtest.php" class="btn btn-secondary" id="btn-go-configtest">Run Diagnostics (configtest.php)</a>
                    </div>
                    <div style="margin-top: 24px; padding: 12px; background: #fef7e0; border-radius: var(--radius-sm); font-size: 12px; color: #b06000; max-width: 500px; margin-left: auto; margin-right: auto;">
                        <strong>Security Tip:</strong> In a production environment, restrict or remove <code>install.php</code> to prevent unauthorized reconfiguration.
                    </div>
                </div>
            </section>
        </form>
    </main>

    <script>
        // Preset definitions
        const presets = {
            gmail: {
                imap_host: 'imap.gmail.com',
                imap_port: 993,
                imap_tls: '1',
                imap_type: 'gmail',
                folder_prefix: '',
                imap_delimiter: '/',
                trash_folder: '[Gmail]/Trash',
                sent_folder: '[Gmail]/Sent Mail',
                draft_folder: '[Gmail]/Drafts',
                smtp_host: 'smtp.gmail.com',
                smtp_port: 465,
                smtp_tls: '1',
                smtp_auth: '1',
                domain: 'gmail.com'
            },
            combell: {
                imap_host: 'imap.mailprotect.be',
                imap_port: 993,
                imap_tls: '1',
                imap_type: 'other',
                folder_prefix: '',
                imap_delimiter: 'detect',
                trash_folder: 'Trash',
                sent_folder: 'Sent',
                draft_folder: 'Drafts',
                smtp_host: 'smtp-auth.mailprotect.be',
                smtp_port: 465,
                smtp_tls: '1',
                smtp_auth: '1',
                domain: ''
            },
            dovecot: {
                imap_host: 'localhost',
                imap_port: 143,
                imap_tls: '0',
                imap_type: 'dovecot',
                folder_prefix: '',
                imap_delimiter: '.',
                trash_folder: 'Trash',
                sent_folder: 'Sent',
                draft_folder: 'Drafts',
                smtp_host: 'localhost',
                smtp_port: 25,
                smtp_tls: '0',
                smtp_auth: '0',
                domain: 'localhost'
            },
            courier: {
                imap_host: 'localhost',
                imap_port: 143,
                imap_tls: '0',
                imap_type: 'courier',
                folder_prefix: 'INBOX.',
                imap_delimiter: '.',
                trash_folder: 'Trash',
                sent_folder: 'Sent',
                draft_folder: 'Drafts',
                smtp_host: 'localhost',
                smtp_port: 25,
                smtp_tls: '0',
                smtp_auth: '0',
                domain: 'localhost'
            },
            cyrus: {
                imap_host: 'localhost',
                imap_port: 143,
                imap_tls: '0',
                imap_type: 'cyrus',
                folder_prefix: '',
                imap_delimiter: '.',
                trash_folder: 'INBOX.Trash',
                sent_folder: 'INBOX.Sent',
                draft_folder: 'INBOX.Drafts',
                smtp_host: 'localhost',
                smtp_port: 25,
                smtp_tls: '0',
                smtp_auth: '0',
                domain: 'localhost'
            },
            exchange: {
                imap_host: 'localhost',
                imap_port: 993,
                imap_tls: '1',
                imap_type: 'exchange',
                folder_prefix: '',
                imap_delimiter: 'detect',
                trash_folder: 'INBOX/Deleted Items',
                sent_folder: 'INBOX/Sent Items',
                draft_folder: 'Drafts',
                smtp_host: 'localhost',
                smtp_port: 587,
                smtp_tls: '2',
                smtp_auth: '1',
                domain: 'localhost'
            },
            uw: {
                imap_host: 'localhost',
                imap_port: 143,
                imap_tls: '0',
                imap_type: 'uw',
                folder_prefix: 'mail/',
                imap_delimiter: '/',
                trash_folder: 'Trash',
                sent_folder: 'Sent',
                draft_folder: 'Drafts',
                smtp_host: 'localhost',
                smtp_port: 25,
                smtp_tls: '0',
                smtp_auth: '0',
                domain: 'localhost'
            },
            custom: {
                imap_host: 'localhost',
                imap_port: 143,
                imap_tls: '0',
                imap_type: 'other',
                folder_prefix: '',
                imap_delimiter: 'detect',
                trash_folder: 'INBOX.Trash',
                sent_folder: 'INBOX.Sent',
                draft_folder: 'INBOX.Drafts',
                smtp_host: 'localhost',
                smtp_port: 25,
                smtp_tls: '0',
                smtp_auth: '0',
                domain: 'localhost'
            }
        };

        // Preset Change handler
        document.getElementById('server_preset').addEventListener('change', function() {
            const p = presets[this.value];
            if (!p) return;
            for (const key in p) {
                const el = document.getElementById(key);
                if (el) el.value = p[key];
            }
        });

        // Step Navigation Logic
        function goToStep(stepNum) {
            document.querySelectorAll('.wizard-step').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.step-btn').forEach(btn => btn.classList.remove('active'));

            const targetContent = document.getElementById('step-content-' + stepNum);
            const targetBtn = document.getElementById('tab-step-' + stepNum);
            if (targetContent) targetContent.style.display = 'block';
            if (targetBtn) targetBtn.classList.add('active');

            // If entering review step, update summary
            if (stepNum === 5) {
                document.getElementById('sum-org').textContent = document.getElementById('org_name').value;
                document.getElementById('sum-domain').textContent = document.getElementById('domain').value;
                document.getElementById('sum-imap').textContent = document.getElementById('imap_host').value + ':' + document.getElementById('imap_port').value;
                document.getElementById('sum-smtp').textContent = document.getElementById('smtp_host').value + ':' + document.getElementById('smtp_port').value;
                document.getElementById('sum-type').textContent = document.getElementById('imap_type').value;
                document.getElementById('sum-theme').textContent = document.getElementById('default_theme').value === 'gmail' ? 'Gmail Theme' : document.getElementById('default_theme').value;
                const adminUsersEl = document.getElementById('admin_users');
                if (adminUsersEl && document.getElementById('sum-admins')) {
                    document.getElementById('sum-admins').textContent = adminUsersEl.value.trim() || '(Default: posix owner)';
                }
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.querySelectorAll('.step-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const s = parseInt(this.dataset.step, 10);
                goToStep(s);
            });
        });

        document.querySelectorAll('.next-step').forEach(btn => {
            btn.addEventListener('click', function() {
                const s = parseInt(this.dataset.next, 10);
                goToStep(s);
            });
        });

        document.querySelectorAll('.prev-step').forEach(btn => {
            btn.addEventListener('click', function() {
                const s = parseInt(this.dataset.prev, 10);
                goToStep(s);
            });
        });

        // Theme card selection
        document.querySelectorAll('.theme-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('default_theme').value = this.dataset.theme;
            });
        });

        // Auto-create directories button
        const btnCreateDirs = document.getElementById('btn-create-dirs');
        if (btnCreateDirs) {
            btnCreateDirs.addEventListener('click', function() {
                this.disabled = true;
                this.textContent = 'Creating...';
                const fd = new FormData();
                fd.append('action', 'create_dirs');

                fetch('install.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                            this.disabled = false;
                            this.textContent = 'Auto-Create Directories';
                        }
                    })
                    .catch(err => {
                        alert('Request failed: ' + err);
                        this.disabled = false;
                        this.textContent = 'Auto-Create Directories';
                    });
            });
        }

        // Test IMAP Connection
        document.getElementById('btn-test-imap').addEventListener('click', function() {
            const out = document.getElementById('test-imap-output');
            out.className = 'test-output';
            out.style.display = 'block';
            out.textContent = 'Connecting to IMAP server...';
            this.disabled = true;

            const fd = new FormData();
            fd.append('action', 'test_imap');
            fd.append('host', document.getElementById('imap_host').value);
            fd.append('port', document.getElementById('imap_port').value);
            fd.append('tls', document.getElementById('imap_tls').value);

            fetch('install.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    this.disabled = false;
                    if (data.success) {
                        out.className = 'test-output success';
                        out.textContent = '✓ ' + data.message + ' [' + data.banner + ']';
                    } else {
                        out.className = 'test-output error';
                        out.textContent = '✗ ' + data.message;
                    }
                })
                .catch(err => {
                    this.disabled = false;
                    out.className = 'test-output error';
                    out.textContent = '✗ Connection test request failed: ' + err;
                });
        });

        // Test SMTP Connection
        document.getElementById('btn-test-smtp').addEventListener('click', function() {
            const out = document.getElementById('test-smtp-output');
            out.className = 'test-output';
            out.style.display = 'block';
            out.textContent = 'Connecting to SMTP server...';
            this.disabled = true;

            const fd = new FormData();
            fd.append('action', 'test_smtp');
            fd.append('host', document.getElementById('smtp_host').value);
            fd.append('port', document.getElementById('smtp_port').value);
            fd.append('tls', document.getElementById('smtp_tls').value);

            fetch('install.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    this.disabled = false;
                    if (data.success) {
                        out.className = 'test-output success';
                        out.textContent = '✓ ' + data.message + ' [' + data.banner + ']';
                    } else {
                        out.className = 'test-output error';
                        out.textContent = '✗ ' + data.message;
                    }
                })
                .catch(err => {
                    this.disabled = false;
                    out.className = 'test-output error';
                    out.textContent = '✗ Connection test request failed: ' + err;
                });
        });

        // Run Installation Action
        document.getElementById('btn-run-install').addEventListener('click', function() {
            const form = document.getElementById('install-form');
            const errBox = document.getElementById('install-error-box');
            errBox.style.display = 'none';

            this.disabled = true;
            this.textContent = 'Installing SquirrelMail...';

            const fd = new FormData(form);
            fd.append('action', 'install');

            fetch('install.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('install-summary-box').style.display = 'none';
                        document.getElementById('install-btn-row').style.display = 'none';
                        document.getElementById('success-screen').style.display = 'block';
                        document.getElementById('step-nav').style.display = 'none';
                    } else {
                        this.disabled = false;
                        this.textContent = '✓ Install SquirrelMail Now';
                        errBox.style.display = 'block';
                        errBox.textContent = data.message;
                    }
                })
                .catch(err => {
                    this.disabled = false;
                    this.textContent = '✓ Install SquirrelMail Now';
                    errBox.style.display = 'block';
                    errBox.textContent = 'Installation request failed: ' + err;
                });
        });

        // Toggle admin whitelist settings if administrator plugin checkbox changes
        const adminChk = document.getElementById('plugin-administrator-check');
        const adminCard = document.getElementById('admin-whitelist-card');
        if (adminChk && adminCard) {
            adminChk.addEventListener('change', function() {
                adminCard.style.display = this.checked ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
