<?php
/**
 * Multi-Account Manager Core Engine
 *
 * Handles account storage, encryption, IMAP connections, and unified inbox aggregation.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage multi_account
 */

if (!defined('SM_PATH')) {
    define('SM_PATH', '../../');
}
require_once(SM_PATH . 'functions/imap.php');
require_once(SM_PATH . 'functions/imap_mailbox.php');
require_once(SM_PATH . 'functions/imap_messages.php');
if (!function_exists('imap_utf7_decode_local') && file_exists(SM_PATH . 'functions/imap_utf7_local.php')) {
    require_once(SM_PATH . 'functions/imap_utf7_local.php');
}
if (!function_exists('decodeHeader') && file_exists(SM_PATH . 'functions/mime.php')) {
    require_once(SM_PATH . 'functions/mime.php');
}

class MultiAccountManager
{
    private $dataDir;
    private $username;
    private $encKey;

    public function __construct($dataDir = null, $username = null)
    {
        global $data_dir, $username;
        $this->dataDir = $dataDir ?: $data_dir;
        $this->username = $username ?: $username;
        $this->encKey = !empty($_SESSION['key']) ? $_SESSION['key'] : md5($this->username . 'sq_multi_account_salt');
    }

    /**
     * Encrypt a password
     */
    public function encrypt($plain)
    {
        if (empty($plain)) return '';
        $iv = substr(md5($this->encKey . 'iv'), 0, 16);
        if (function_exists('openssl_encrypt')) {
            $enc = openssl_encrypt($plain, 'AES-128-CBC', substr($this->encKey, 0, 16), 0, $iv);
            return 'enc:' . base64_encode($enc);
        }
        return 'b64:' . base64_encode($plain);
    }

    /**
     * Decrypt a password
     */
    public function decrypt($encoded)
    {
        if (empty($encoded)) return '';
        if (strpos($encoded, 'enc:') === 0) {
            $raw = base64_decode(substr($encoded, 4));
            $iv = substr(md5($this->encKey . 'iv'), 0, 16);
            if (function_exists('openssl_decrypt')) {
                return openssl_decrypt($raw, 'AES-128-CBC', substr($this->encKey, 0, 16), 0, $iv);
            }
        } elseif (strpos($encoded, 'b64:') === 0) {
            return base64_decode(substr($encoded, 4));
        }
        return $encoded;
    }

    /**
     * Get all configured accounts for current user
     */
    public function getAccounts()
    {
        if (function_exists('getPref')) {
            $raw = getPref($this->dataDir, $this->username, 'multi_account_list', '[]');
        } else {
            $fallbackFile = rtrim($this->dataDir, '/') . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $this->username) . '.multi_acc';
            $raw = (file_exists($fallbackFile) && is_readable($fallbackFile)) ? file_get_contents($fallbackFile) : '[]';
        }
        $list = json_decode($raw, true);
        if (!is_array($list)) {
            $list = [];
        }
        return $list;
    }

    /**
     * Save accounts list
     */
    public function saveAccounts($accounts)
    {
        if (!is_array($accounts)) $accounts = [];
        $json = json_encode($accounts);
        if (function_exists('setPref')) {
            setPref($this->dataDir, $this->username, 'multi_account_list', $json);
        } else {
            $fallbackFile = rtrim($this->dataDir, '/') . '/' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $this->username) . '.multi_acc';
            @file_put_contents($fallbackFile, $json);
        }
        return true;
    }

    /**
     * Add or update an account
     */
    public function saveAccount($data)
    {
        $accounts = $this->getAccounts();
        $id = !empty($data['id']) ? $data['id'] : ('acc_' . substr(md5(uniqid(mt_rand(), true)), 0, 8));

        $found = false;
        foreach ($accounts as $k => $acc) {
            if ($acc['id'] === $id) {
                // If password is blank on update, preserve old password
                $pw = !empty($data['password']) ? $this->encrypt($data['password']) : $acc['password'];
                $accounts[$k] = [
                    'id'          => $id,
                    'name'        => trim($data['name']),
                    'email'       => trim($data['email']),
                    'host'        => trim($data['host']),
                    'port'        => intval($data['port']),
                    'tls'         => intval($data['tls']),
                    'user'        => trim($data['user']),
                    'password'    => $pw,
                    'color'       => !empty($data['color']) ? $data['color'] : '#1a73e8',
                    'enabled'     => isset($data['enabled']) ? (bool)$data['enabled'] : true,
                    'smtp_host'   => trim($data['smtp_host'] ?? ''),
                    'smtp_port'   => intval($data['smtp_port'] ?? 465),
                    'smtp_tls'    => intval($data['smtp_tls'] ?? 1),
                ];
                $found = true;
                break;
            }
        }

        if (!$found) {
            $accounts[] = [
                'id'          => $id,
                'name'        => trim($data['name']),
                'email'       => trim($data['email']),
                'host'        => trim($data['host']),
                'port'        => intval($data['port']),
                'tls'         => intval($data['tls']),
                'user'        => trim($data['user']),
                'password'    => $this->encrypt($data['password'] ?? ''),
                'color'       => !empty($data['color']) ? $data['color'] : '#1a73e8',
                'enabled'     => isset($data['enabled']) ? (bool)$data['enabled'] : true,
                'smtp_host'   => trim($data['smtp_host'] ?? ''),
                'smtp_port'   => intval($data['smtp_port'] ?? 465),
                'smtp_tls'    => intval($data['smtp_tls'] ?? 1),
            ];
        }

        $this->saveAccounts($accounts);
        return $id;
    }

    /**
     * Delete an account by ID
     */
    public function deleteAccount($id)
    {
        $accounts = $this->getAccounts();
        $updated = [];
        foreach ($accounts as $acc) {
            if ($acc['id'] !== $id) {
                $updated[] = $acc;
            }
        }
        $this->saveAccounts($updated);
        return true;
    }

    /**
     * Test IMAP connection for an account
     */
    public function testConnection($host, $port, $tls, $user, $pass)
    {
        $prefix = ($tls === 1) ? 'ssl://' : '';
        $timeout = 6;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            return ['success' => false, 'error' => "Network socket error: $errstr ($errno)"];
        }

        stream_set_timeout($fp, $timeout);
        $banner = fgets($fp, 1024);

        // IMAP Login command
        $cleanUser = addcslashes($user, '"\\');
        $cleanPass = addcslashes($pass, '"\\');
        fwrite($fp, "A001 LOGIN \"$cleanUser\" \"$cleanPass\"\r\n");

        $loginOk = false;
        $resp = '';
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            $resp .= $line;
            if (preg_match('/^A001\s+(OK|NO|BAD)/i', $line, $m)) {
                $status = strtoupper($m[1]);
                $loginOk = ($status === 'OK');
                break;
            }
        }

        // Logout
        fwrite($fp, "A002 LOGOUT\r\n");
        fclose($fp);

        if ($loginOk) {
            return ['success' => true, 'message' => 'IMAP login authenticated successfully!'];
        } else {
            return ['success' => false, 'error' => 'IMAP authentication failed: ' . trim($resp)];
        }
    }

    /**
     * Connect to an IMAP socket and login
     */
    private function openImapStream($host, $port, $tls, $user, $pass)
    {
        $prefix = ($tls === 1) ? 'ssl://' : '';
        $timeout = 8;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) return false;

        stream_set_timeout($fp, $timeout);
        fgets($fp, 1024); // banner

        $cleanUser = addcslashes($user, '"\\');
        $cleanPass = addcslashes($pass, '"\\');
        fwrite($fp, "A001 LOGIN \"$cleanUser\" \"$cleanPass\"\r\n");

        $ok = false;
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            if ($line === false) {
                break;
            }
            if (preg_match('/^A001\s+OK/i', $line)) {
                $ok = true;
                break;
            } elseif (preg_match('/^A001\s+(NO|BAD)/i', $line)) {
                break;
            }
        }

        if (!$ok) {
            fclose($fp);
            return false;
        }

        return $fp;
    }

    /**
     * Send command and return output lines until tagged response
     */
    private function imapCommand($fp, $tag, $cmd)
    {
        fwrite($fp, "$tag $cmd\r\n");
        $lines = [];
        while (!feof($fp)) {
            $line = fgets($fp, 2048);
            if ($line === false) break;
            $lines[] = rtrim($line, "\r\n");
            if (preg_match("/^$tag\\s+(OK|NO|BAD)/i", $line)) {
                break;
            }
        }
        return $lines;
    }

    /**
     * Fetch unread counts across all accounts
     */
    public function getUnreadCounts($useCache = true)
    {
        if ($useCache && !empty($_SESSION['multi_acc_unread_cache']) && !empty($_SESSION['multi_acc_unread_time'])) {
            if (time() - $_SESSION['multi_acc_unread_time'] < 30) {
                return $_SESSION['multi_acc_unread_cache'];
            }
        }

        $counts = [
            'total_unread' => 0,
            'accounts'     => []
        ];

        // 1. Primary Account (from SquirrelMail session/cache if available)
        global $imapServerAddress, $imapPort, $use_imap_tls, $username;
        $primaryUnread = 0;
        if (!empty($_SESSION['mailbox_cache'])) {
            foreach ($_SESSION['mailbox_cache'] as $box) {
                if (isset($box['NAME']) && $box['NAME'] === 'INBOX' && isset($box['UNSEEN'])) {
                    $primaryUnread = intval($box['UNSEEN']);
                    break;
                }
            }
        }
        $counts['accounts']['primary'] = [
            'name'   => 'Primary Account',
            'unread' => $primaryUnread,
            'color'  => '#1a73e8'
        ];
        $counts['total_unread'] += $primaryUnread;

        // 2. Secondary Accounts
        $accounts = $this->getAccounts();
        foreach ($accounts as $acc) {
            if (empty($acc['enabled'])) continue;
            $unseen = 0;
            $fp = null;
            try {
                $pass = $this->decrypt($acc['password']);
                $fp = $this->openImapStream($acc['host'], $acc['port'], $acc['tls'], $acc['user'], $pass);
                if ($fp) {
                    $lines = $this->imapCommand($fp, 'T01', 'STATUS INBOX (UNSEEN)');
                    foreach ($lines as $l) {
                        if (preg_match('/UNSEEN\s+(\d+)/i', $l, $m)) {
                            $unseen = intval($m[1]);
                            break;
                        }
                    }
                    $this->imapCommand($fp, 'T02', 'LOGOUT');
                    @fclose($fp);
                }
            } catch (Throwable $e) {
                if ($fp) @fclose($fp);
            }
            $counts['accounts'][$acc['id']] = [
                'name'   => $acc['name'],
                'unread' => $unseen,
                'color'  => $acc['color']
            ];
            $counts['total_unread'] += $unseen;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['multi_acc_unread_cache'] = $counts;
            $_SESSION['multi_acc_unread_time'] = time();
        }

        return $counts;
    }

    /**
     * Fetch unified inbox: aggregates messages from primary and all secondary accounts
     *
     * @param int $limitPerAccount Number of recent messages to fetch per mailbox
     * @return array Array of normalized email summaries sorted newest first
     */
    public function fetchUnifiedInbox($limitPerAccount = 25)
    {
        $unified = [];

        // 1. Fetch Primary Account messages (via SquirrelMail IMAP connection)
        global $imapConnection, $imapServerAddress, $imapPort, $imap_stream_options, $username;
        $stream = $imapConnection;
        $shouldLogout = false;

        try {
            if (!$stream && !empty($_SESSION['onetimepad']) && function_exists('sqimap_login')) {
                $stream = @sqimap_login($username, false, $imapServerAddress, $imapPort, 2, $imap_stream_options);
                if ($stream) {
                    $shouldLogout = true;
                }
            }

            if ($stream) {
                sqimap_mailbox_select($stream, 'INBOX');
                $r = '';
                $m = '';
                $ids = sqimap_run_command($stream, "SEARCH ALL", true, $r, $m, true);
                $numIds = [];
                if (!empty($ids)) {
                    foreach ($ids as $line) {
                        if (preg_match('/^\*\s+SEARCH\s+(.*)$/i', $line, $match)) {
                            $parts = preg_split('/\s+/', trim($match[1]));
                            foreach ($parts as $p) {
                                if (is_numeric($p) && intval($p) > 0) $numIds[] = intval($p);
                            }
                        }
                    }
                }

                if (!empty($numIds)) {
                    // Take newest $limitPerAccount
                    $slice = array_slice($numIds, -$limitPerAccount);
                    $msgs = sqimap_get_small_header_list($stream, $slice);
                    if (is_array($msgs)) {
                        foreach ($msgs as $id => $header) {
                            $seen = isset($header['FLAGS']['\\seen']);
                            $flagged = isset($header['FLAGS']['\\flagged']);

                            $subject = !empty($header['subject']) ? self::decodeHeader($header['subject']) : '(No Subject)';
                            $from = !empty($header['from']) ? self::decodeHeader($header['from']) : 'Unknown';
                            $date = !empty($header['date']) ? $header['date'] : '';

                            $unified[] = [
                                'account_id'    => 'primary',
                                'account_name'  => 'Primary',
                                'account_color' => '#1a73e8',
                                'account_email' => $username,
                                'uid'           => $id,
                                'subject'       => $subject,
                                'from'          => $from,
                                'date'          => $date,
                                'timestamp'     => !empty($date) ? strtotime($date) : time(),
                                'seen'          => $seen,
                                'flagged'       => $flagged,
                                'is_primary'    => true
                            ];
                        }
                    }
                }

                if ($shouldLogout) {
                    sqimap_logout($stream);
                }
            }
        } catch (Throwable $e) {
            // Gracefully catch any primary mailbox errors
            if ($shouldLogout && $stream) {
                @sqimap_logout($stream);
            }
        }

        // 2. Fetch Secondary Accounts messages
        $accounts = $this->getAccounts();
        foreach ($accounts as $acc) {
            if (empty($acc['enabled'])) continue;

            $fp = null;
            try {
                $pass = $this->decrypt($acc['password']);
                $fp = $this->openImapStream($acc['host'], $acc['port'], $acc['tls'], $acc['user'], $pass);
                if (!$fp) continue;

                // Select INBOX
                $this->imapCommand($fp, 'T01', 'SELECT INBOX');

                // Search ALL
                $searchLines = $this->imapCommand($fp, 'T02', 'SEARCH ALL');
                $accIds = [];
                foreach ($searchLines as $sl) {
                    if (preg_match('/^\*\s+SEARCH\s+(.*)$/i', $sl, $sm)) {
                        $parts = preg_split('/\s+/', trim($sm[1]));
                        foreach ($parts as $p) {
                            if (is_numeric($p) && intval($p) > 0) $accIds[] = intval($p);
                        }
                    }
                }

                if (!empty($accIds)) {
                    $slice = array_slice($accIds, -$limitPerAccount);
                    $idList = implode(',', $slice);

                    // Fetch envelope & flags
                    $fetchLines = $this->imapCommand($fp, 'T03', "FETCH $idList (FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE)])");
                    
                    $currentMsg = null;
                    foreach ($fetchLines as $fl) {
                        if (preg_match('/^\*\s+(\d+)\s+FETCH\s+\((.*)/i', $fl, $fm)) {
                            if ($currentMsg && isset($currentMsg['uid'])) {
                                $unified[] = $currentMsg;
                            }
                            $msgId = intval($fm[1]);
                            $details = $fm[2];
                            $seen = (stripos($details, '\\Seen') !== false);
                            $flagged = (stripos($details, '\\Flagged') !== false);
                            
                            $currentMsg = [
                                'account_id'    => $acc['id'],
                                'account_name'  => $acc['name'],
                                'account_color' => $acc['color'],
                                'account_email' => $acc['email'],
                                'uid'           => $msgId,
                                'subject'       => '(No Subject)',
                                'from'          => 'Unknown',
                                'date'          => '',
                                'timestamp'     => time(),
                                'seen'          => $seen,
                                'flagged'       => $flagged,
                                'is_primary'    => false
                            ];
                        } elseif ($currentMsg) {
                            if (preg_match('/^Subject:\s*(.*)$/i', $fl, $sm)) {
                                $currentMsg['subject'] = self::decodeHeader(trim($sm[1]));
                            } elseif (preg_match('/^From:\s*(.*)$/i', $fl, $fm)) {
                                $currentMsg['from'] = self::decodeHeader(trim($fm[1]));
                            } elseif (preg_match('/^Date:\s*(.*)$/i', $fl, $dm)) {
                                $currentMsg['date'] = trim($dm[1]);
                                $currentMsg['timestamp'] = strtotime($currentMsg['date']) ?: time();
                            }
                        }
                    }
                    if ($currentMsg && isset($currentMsg['uid'])) {
                        $unified[] = $currentMsg;
                    }
                }

                $this->imapCommand($fp, 'T04', 'LOGOUT');
                @fclose($fp);
            } catch (Throwable $e) {
                if ($fp) @fclose($fp);
            }
        }

        // 3. Sort chronologically: newest emails first
        usort($unified, function($a, $b) {
            return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
        });

        return $unified;
    }

    /**
     * Mark a message as seen or unseen on a secondary IMAP account
     */
    public function markSeen($accountId, $uid, $seen = true)
    {
        $accounts = $this->getAccounts();
        $target = null;
        foreach ($accounts as $acc) {
            if ($acc['id'] === $accountId) {
                $target = $acc;
                break;
            }
        }
        if (!$target) return false;

        $pass = $this->decrypt($target['password']);
        $fp = $this->openImapStream($target['host'], $target['port'], $target['tls'], $target['user'], $pass);
        if (!$fp) return false;

        $flagCmd = $seen ? '+FLAGS (\Seen)' : '-FLAGS (\Seen)';
        $this->imapCommand($fp, 'T01', 'SELECT INBOX');
        $this->imapCommand($fp, 'T02', "STORE $uid $flagCmd");
        $this->imapCommand($fp, 'T03', 'LOGOUT');
        fclose($fp);
        return true;
    }

    /**
     * Delete an email message from a secondary IMAP account
     */
    public function deleteMessage($accountId, $uid)
    {
        $accounts = $this->getAccounts();
        $target = null;
        foreach ($accounts as $acc) {
            if ($acc['id'] === $accountId) {
                $target = $acc;
                break;
            }
        }
        if (!$target) return false;

        $pass = $this->decrypt($target['password']);
        $fp = $this->openImapStream($target['host'], $target['port'], $target['tls'], $target['user'], $pass);
        if (!$fp) return false;

        $this->imapCommand($fp, 'T01', 'SELECT INBOX');
        $this->imapCommand($fp, 'T02', "STORE $uid +FLAGS (\Deleted)");
        $this->imapCommand($fp, 'T03', 'EXPUNGE');
        $this->imapCommand($fp, 'T04', 'LOGOUT');
        fclose($fp);
        return true;
    }

    /**
     * Decode MIME encoded headers (e.g. =?UTF-8?B?...?= or =?ISO-8859-1?Q?...?=)
     */
    public static function decodeHeader($str)
    {
        if (empty($str)) return '';
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($str);
        }
        if (function_exists('iconv_mime_decode')) {
            return iconv_mime_decode($str, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        }
        return preg_replace_callback('/=\?([^?]+)\?([BQbq])\?([^?]+)\?=/i', function($m) {
            $charset = $m[1];
            $type = strtoupper($m[2]);
            $data = $m[3];
            $decoded = ($type === 'B') ? base64_decode($data) : quoted_printable_decode(str_replace('_', ' ', $data));
            if (function_exists('mb_convert_encoding')) {
                return @mb_convert_encoding($decoded, 'UTF-8', $charset);
            }
            return $decoded;
        }, $str);
    }

    /**
     * Parse raw RFC822 email text into structured headers, HTML and plain text body
     */
    public function parseRfc822($rawRfc)
    {
        // Separate headers and body
        $pos = strpos($rawRfc, "\r\n\r\n");
        if ($pos === false) {
            $pos = strpos($rawRfc, "\n\n");
            $hdrLen = ($pos !== false) ? 2 : 0;
        } else {
            $hdrLen = 4;
        }

        if ($pos === false) {
            $rawHeaders = $rawRfc;
            $rawBody = '';
        } else {
            $rawHeaders = substr($rawRfc, 0, $pos);
            $rawBody = substr($rawRfc, $pos + $hdrLen);
        }

        // Unfold headers
        $rawHeaders = preg_replace("/\r\n[ \t]+/", ' ', $rawHeaders);
        $rawHeaders = preg_replace("/\n[ \t]+/", ' ', $rawHeaders);
        $headerLines = explode("\n", $rawHeaders);

        $headers = [];
        foreach ($headerLines as $hl) {
            $hl = trim($hl);
            if (empty($hl)) continue;
            if (preg_match('/^([^:]+):\s*(.*)$/', $hl, $m)) {
                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
                $headers[$key] = $val;
            }
        }

        $subject = self::decodeHeader($headers['subject'] ?? '(No Subject)');
        $from    = self::decodeHeader($headers['from'] ?? 'Unknown');
        $to      = self::decodeHeader($headers['to'] ?? '');
        $cc      = self::decodeHeader($headers['cc'] ?? '');
        $date    = $headers['date'] ?? '';

        $contentType = $headers['content-type'] ?? 'text/plain';
        $contentTransfer = strtolower(trim($headers['content-transfer-encoding'] ?? '7bit'));

        $textBody = '';
        $htmlBody = '';

        // Check if multipart
        if (preg_match('/multipart\/[a-z0-9_-]+;\s*boundary="?([^";]+)"?/i', $contentType, $bm)) {
            $boundary = trim($bm[1]);
            $parts = explode('--' . $boundary, $rawBody);
            foreach ($parts as $p) {
                $p = trim($p);
                if (empty($p) || $p === '--') continue;
                $subPos = strpos($p, "\r\n\r\n");
                if ($subPos === false) {
                    $subPos = strpos($p, "\n\n");
                    $sHdrLen = ($subPos !== false) ? 2 : 0;
                } else {
                    $sHdrLen = 4;
                }

                $subHdr = ($subPos !== false) ? substr($p, 0, $subPos) : '';
                $subContent = ($subPos !== false) ? substr($p, $subPos + $sHdrLen) : $p;

                $subHdr = preg_replace("/\r\n[ \t]+/", ' ', $subHdr);
                $subHdr = preg_replace("/\n[ \t]+/", ' ', $subHdr);
                $subType = 'text/plain';
                $subEncoding = '7bit';
                $subCharset = 'utf-8';

                if (preg_match('/Content-Type:\s*([^;\r\n]+)/i', $subHdr, $ctm)) {
                    $subType = strtolower(trim($ctm[1]));
                }
                if (preg_match('/charset="?([^";\r\n]+)"?/i', $subHdr, $csm)) {
                    $subCharset = strtolower(trim($csm[1]));
                }
                if (preg_match('/Content-Transfer-Encoding:\s*([^;\r\n]+)/i', $subHdr, $tem)) {
                    $subEncoding = strtolower(trim($tem[1]));
                }

                // Decode body part
                if ($subEncoding === 'base64') {
                    $subDecoded = base64_decode($subContent);
                } elseif ($subEncoding === 'quoted-printable') {
                    $subDecoded = quoted_printable_decode($subContent);
                } else {
                    $subDecoded = $subContent;
                }

                if ($subCharset !== 'utf-8' && function_exists('mb_convert_encoding')) {
                    $subDecoded = @mb_convert_encoding($subDecoded, 'UTF-8', $subCharset);
                }

                if ($subType === 'text/html' && empty($htmlBody)) {
                    $htmlBody = $subDecoded;
                } elseif ($subType === 'text/plain' && empty($textBody)) {
                    $textBody = $subDecoded;
                }
            }
        } else {
            // Single part
            $decoded = $rawBody;
            if ($contentTransfer === 'base64') {
                $decoded = base64_decode($rawBody);
            } elseif ($contentTransfer === 'quoted-printable') {
                $decoded = quoted_printable_decode($rawBody);
            }

            if (preg_match('/charset="?([^";\r\n]+)"?/i', $contentType, $csm)) {
                $charset = strtolower(trim($csm[1]));
                if ($charset !== 'utf-8' && function_exists('mb_convert_encoding')) {
                    $decoded = @mb_convert_encoding($decoded, 'UTF-8', $charset);
                }
            }

            if (stripos($contentType, 'text/html') !== false) {
                $htmlBody = $decoded;
            } else {
                $textBody = $decoded;
            }
        }

        return [
            'subject'  => $subject,
            'from'     => $from,
            'to'       => $to,
            'cc'       => $cc,
            'date'     => $date,
            'text'     => trim($textBody),
            'html'     => trim($htmlBody),
            'headers'  => $headers,
        ];
    }

    /**
     * Fetch and parse complete message details from a secondary account
     */
    public function fetchMessage($accountId, $uid)
    {
        $accounts = $this->getAccounts();
        $target = null;
        foreach ($accounts as $acc) {
            if ($acc['id'] === $accountId) {
                $target = $acc;
                break;
            }
        }
        if (!$target) return false;

        $pass = $this->decrypt($target['password']);
        $fp = $this->openImapStream($target['host'], $target['port'], $target['tls'], $target['user'], $pass);
        if (!$fp) return false;

        $this->imapCommand($fp, 'T01', 'SELECT INBOX');
        // Automatically mark as \Seen upon viewing
        $this->imapCommand($fp, 'T02', "STORE $uid +FLAGS (\\Seen)");
        $lines = $this->imapCommand($fp, 'T03', "FETCH $uid (FLAGS RFC822)");
        $this->imapCommand($fp, 'T04', 'LOGOUT');
        fclose($fp);

        $rawRfc = implode("\r\n", $lines);
        $parsed = $this->parseRfc822($rawRfc);

        return [
            'account' => $target,
            'uid'     => $uid,
            'raw'     => $rawRfc,
            'parsed'  => $parsed,
        ];
    }

    /**
     * Sync secondary accounts into SquirrelMail identities so they appear in compose/reply
     */
    public function syncSquirrelMailIdentities()
    {
        if (!function_exists('get_identities') || !function_exists('save_identities')) {
            return;
        }

        $idents = get_identities();
        $accounts = $this->getAccounts();
        $primary = $idents[0] ?? [
            'full_name'     => '',
            'email_address' => $this->username,
            'reply_to'      => '',
            'signature'     => '',
            'index'         => 0,
        ];

        $synced = [$primary];
        $idx = 1;
        foreach ($accounts as $acc) {
            if (empty($acc['enabled'])) continue;
            // Check if identity already exists by email
            $synced[] = [
                'full_name'     => $acc['name'],
                'email_address' => $acc['email'],
                'reply_to'      => $acc['email'],
                'signature'     => '',
                'index'         => $idx++,
            ];
        }

        save_identities($synced);
    }
}

