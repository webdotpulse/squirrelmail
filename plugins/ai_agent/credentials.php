<?php
/**
 * AI Agent Credential Management & Encryption Service
 *
 * Securely stores, encrypts, and resolves IMAP credentials for background
 * server-side AI cron jobs and multi-account automation.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

if (!defined('SM_PATH')) {
    define('SM_PATH', dirname(dirname(dirname(__FILE__))) . '/');
}

/**
 * Get or generate persistent encryption key for AI agent credentials
 */
function ai_agent_get_enc_key()
{
    global $encode_header_key, $data_dir;

    $keyFile = SM_PATH . 'plugins/ai_agent/data/.enc.key';
    if (file_exists($keyFile) && is_readable($keyFile)) {
        $k = trim(@file_get_contents($keyFile));
        if (!empty($k)) return $k;
    }

    if (!empty($encode_header_key)) {
        $k = md5($encode_header_key . '_ai_agent_cred_2026');
    } else {
        $k = bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16));
    }

    $dir = dirname($keyFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($keyFile, $k);
    @chmod($keyFile, 0600);

    return $k;
}

/**
 * Encrypt plaintext string
 */
function ai_agent_encrypt($plain)
{
    if (empty($plain)) return '';
    $key = substr(ai_agent_get_enc_key(), 0, 16);

    if (function_exists('openssl_encrypt')) {
        $iv = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($plain, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return 'enc:' . base64_encode($iv . $enc);
    }

    return 'b64:' . base64_encode($plain);
}

/**
 * Decrypt cipher string
 */
function ai_agent_decrypt($cipher)
{
    if (empty($cipher)) return '';
    if (strpos($cipher, 'enc:') === 0) {
        $raw = base64_decode(substr($cipher, 4));
        if (strlen($raw) > 16 && function_exists('openssl_decrypt')) {
            $iv = substr($raw, 0, 16);
            $enc = substr($raw, 16);
            $key = substr(ai_agent_get_enc_key(), 0, 16);
            $dec = openssl_decrypt($enc, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($dec !== false) return $dec;
        }
    } elseif (strpos($cipher, 'b64:') === 0) {
        return base64_decode(substr($cipher, 4));
    }
    return $cipher;
}

/**
 * Save user credentials for background cron jobs
 */
function ai_agent_save_account_credentials($username, $password, $host = null, $port = null)
{
    global $data_dir, $imapServerAddress, $imapPort;

    if (empty($username) || empty($password)) return false;

    $host = !empty($host) ? $host : (!empty($imapServerAddress) ? $imapServerAddress : 'localhost');
    $port = !empty($port) ? intval($port) : (!empty($imapPort) ? intval($imapPort) : 993);
    $encPass = ai_agent_encrypt($password);

    // 1. User preferences file
    if (!empty($data_dir) && function_exists('setPref')) {
        setPref($data_dir, $username, 'ai_agent_cron_password', $encPass);
        setPref($data_dir, $username, 'ai_agent_cron_host', $host);
        setPref($data_dir, $username, 'ai_agent_cron_port', $port);
        setPref($data_dir, $username, 'ai_agent_cron_enabled', '1');
    }

    // 2. Central AI Agent Cron Accounts store (protected 0600)
    $storeFile = SM_PATH . 'plugins/ai_agent/data/cron_accounts.json';
    $dir = dirname($storeFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $accounts = [];
    if (file_exists($storeFile)) {
        $raw = @file_get_contents($storeFile);
        if (!empty($raw)) {
            $accounts = json_decode($raw, true) ?: [];
        }
    }

    $accounts[$username] = [
        'username' => $username,
        'password' => $encPass,
        'host'     => $host,
        'port'     => $port,
        'updated'  => time(),
    ];

    @file_put_contents($storeFile, json_encode($accounts, JSON_PRETTY_PRINT));
    @chmod($storeFile, 0600);

    return true;
}

/**
 * Resolve IMAP credentials for a user from all available sources
 */
function ai_agent_get_account_credentials($username)
{
    global $cron_accounts, $data_dir, $imapServerAddress, $imapPort;

    $result = [
        'username' => $username,
        'password' => '',
        'host'     => !empty($imapServerAddress) ? $imapServerAddress : 'localhost',
        'port'     => !empty($imapPort) ? intval($imapPort) : 993,
    ];

    // Source 1: Explicit $cron_accounts from config.php or config_local.php
    if (!empty($cron_accounts) && is_array($cron_accounts)) {
        // Form A: Associative array keyed by username: $cron_accounts['user@domain'] = ['password' => '...'] or 'pass'
        if (isset($cron_accounts[$username])) {
            $val = $cron_accounts[$username];
            if (is_array($val)) {
                if (!empty($val['password'])) $result['password'] = $val['password'];
                if (!empty($val['host']))     $result['host'] = $val['host'];
                if (!empty($val['port']))     $result['port'] = intval($val['port']);
            } elseif (is_string($val)) {
                $result['password'] = $val;
            }
        } else {
            // Form B: Sequential array of account arrays
            foreach ($cron_accounts as $acc) {
                if (is_array($acc) && !empty($acc['username']) && $acc['username'] === $username) {
                    if (!empty($acc['password'])) $result['password'] = $acc['password'];
                    if (!empty($acc['host']))     $result['host'] = $acc['host'];
                    if (!empty($acc['port']))     $result['port'] = intval($acc['port']);
                    break;
                }
            }
        }
    }

    if (!empty($result['password'])) return $result;

    // Source 2: plugins/ai_agent/data/cron_accounts.json
    $storeFile = SM_PATH . 'plugins/ai_agent/data/cron_accounts.json';
    if (file_exists($storeFile) && is_readable($storeFile)) {
        $raw = @file_get_contents($storeFile);
        $accounts = json_decode($raw, true);
        if (is_array($accounts) && isset($accounts[$username])) {
            $acc = $accounts[$username];
            $pw = !empty($acc['password']) ? ai_agent_decrypt($acc['password']) : '';
            if (!empty($pw)) {
                $result['password'] = $pw;
                if (!empty($acc['host'])) $result['host'] = $acc['host'];
                if (!empty($acc['port'])) $result['port'] = intval($acc['port']);
                return $result;
            }
        }
    }

    // Source 3: User Preferences in $data_dir
    if (!empty($data_dir) && function_exists('getPref')) {
        $savedPass = getPref($data_dir, $username, 'ai_agent_cron_password', '');
        if (!empty($savedPass)) {
            $pw = ai_agent_decrypt($savedPass);
            if (!empty($pw)) {
                $result['password'] = $pw;
                $savedHost = getPref($data_dir, $username, 'ai_agent_cron_host', '');
                $savedPort = getPref($data_dir, $username, 'ai_agent_cron_port', '');
                if (!empty($savedHost)) $result['host'] = $savedHost;
                if (!empty($savedPort)) $result['port'] = intval($savedPort);
                return $result;
            }
        }
    }

    // Source 4: plugins/multi_account
    $multiAccountMgr = SM_PATH . 'plugins/multi_account/account_manager.php';
    if (file_exists($multiAccountMgr) && !empty($data_dir)) {
        include_once($multiAccountMgr);
        if (class_exists('MultiAccountManager')) {
            $mgr = new MultiAccountManager($username, $data_dir);
            $accs = $mgr->getAccounts();
            if (!empty($accs) && is_array($accs)) {
                foreach ($accs as $ma) {
                    if (!empty($ma['username']) && $ma['username'] === $username && !empty($ma['password'])) {
                        $pw = $mgr->decrypt($ma['password']);
                        if (!empty($pw)) {
                            $result['password'] = $pw;
                            if (!empty($ma['host'])) $result['host'] = $ma['host'];
                            if (!empty($ma['port'])) $result['port'] = intval($ma['port']);
                            return $result;
                        }
                    }
                }
            }
        }
    }

    // Source 5: Environment variables
    $envPass = getenv('CRON_IMAP_PASSWORD') ?: (getenv('IMAP_PASSWORD') ?: '');
    if (!empty($envPass)) {
        $result['password'] = $envPass;
        return $result;
    }

    return (!empty($result['password'])) ? $result : null;
}

/**
 * Capture credentials from active webmail login session (hook: login_verified)
 */
function ai_agent_capture_login_credentials()
{
    global $username, $login_username, $imapServerAddress, $imapPort;

    $user = !empty($username) ? $username : (!empty($login_username) ? $login_username : '');
    if (empty($user)) return;

    $pass = '';
    if (function_exists('sqauth_read_password')) {
        $pass = sqauth_read_password();
    } elseif (!empty($_SESSION['key']) && !empty($_SESSION['onetimepad']) && function_exists('OneTimePadDecrypt')) {
        $pass = OneTimePadDecrypt($_SESSION['key'], $_SESSION['onetimepad']);
    }

    if (!empty($pass)) {
        ai_agent_save_account_credentials($user, $pass, $imapServerAddress, $imapPort);
    }
}

/**
 * Test IMAP connection with provided credentials
 */
function ai_agent_test_imap_login($username, $password, $host = null, $port = null)
{
    global $imapServerAddress, $imapPort, $imap_stream_options, $use_imap_tls;

    $host = !empty($host) ? trim($host) : (!empty($imapServerAddress) ? $imapServerAddress : 'localhost');
    $port = !empty($port) ? intval($port) : (!empty($imapPort) ? intval($imapPort) : 993);

    // Determine TLS / SSL: port 993 is IMAPS (ssl://), or when use_imap_tls is enabled
    $isSsl = ($port === 993 || (!empty($use_imap_tls) && $use_imap_tls == 1));
    $prefix = $isSsl ? 'ssl://' : '';
    $timeout = 10;

    $contextOptions = !empty($imap_stream_options) && is_array($imap_stream_options) ? $imap_stream_options : [];
    if (!isset($contextOptions['ssl'])) {
        $contextOptions['ssl'] = [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ];
    }
    $context = stream_context_create($contextOptions);

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return [
            'success' => false,
            'message' => sprintf(_("Network error connecting to IMAP server at %s:%d: %s (%d)"), $host, $port, $errstr, $errno)
        ];
    }

    stream_set_timeout($fp, $timeout);
    $banner = @fgets($fp, 1024);

    // Handle STARTTLS on port 143 if configured
    if (!$isSsl && isset($use_imap_tls) && $use_imap_tls == 2 && function_exists('stream_socket_enable_crypto')) {
        @fwrite($fp, "A000 STARTTLS\r\n");
        $tlsResp = @fgets($fp, 1024);
        if ($tlsResp && stripos($tlsResp, 'A000 OK') !== false) {
            @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
    }

    // Send LOGIN command with RFC 3501 escaped characters
    $cleanUser = addcslashes($username, '"\\');
    $cleanPass = addcslashes($password, '"\\');
    @fwrite($fp, "A001 LOGIN \"$cleanUser\" \"$cleanPass\"\r\n");

    $status = 'UNKNOWN';
    $respMsg = '';
    while (!feof($fp)) {
        $line = @fgets($fp, 1024);
        if ($line === false) break;
        $trimmed = trim($line);
        if (preg_match('/^A001\s+(OK|NO|BAD)(?:\s+(.*))?$/i', $trimmed, $m)) {
            $status = strtoupper($m[1]);
            $respMsg = isset($m[2]) ? trim($m[2]) : '';
            break;
        }
    }

    @fwrite($fp, "A002 LOGOUT\r\n");
    @fclose($fp);

    if ($status === 'OK') {
        return [
            'success' => true,
            'message' => sprintf(_("IMAP connection successful! Successfully authenticated user '%s' on %s:%d."), $username, $host, $port)
        ];
    } elseif ($status === 'NO') {
        return [
            'success' => false,
            'message' => sprintf(_("Authentication failed on %s:%d for user '%s': %s"), $host, $port, $username, $respMsg ?: _("Invalid username or password."))
        ];
    } elseif ($status === 'BAD') {
        return [
            'success' => false,
            'message' => sprintf(_("IMAP server returned syntax error on %s:%d: %s"), $host, $port, $respMsg ?: _("Bad request."))
        ];
    } else {
        return [
            'success' => false,
            'message' => sprintf(_("Unexpected response from IMAP server at %s:%d: %s"), $host, $port, $respMsg ?: _("No response received from server."))
        ];
    }
}
