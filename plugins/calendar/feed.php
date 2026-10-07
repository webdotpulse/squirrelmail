<?php
/**
 * iCalendar (.ics) Live Subscription Feed
 *
 * Provides a live, token-authenticated iCalendar feed for external calendars
 * such as Google Calendar, Apple Calendar, Microsoft Outlook, and mobile devices.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage calendar
 */

if (!defined('SM_PATH')) {
    define('SM_PATH', '../../');
}

require_once(SM_PATH . 'include/constants.php');
require_once(SM_PATH . 'functions/global.php');
require_once(SM_PATH . 'functions/strings.php');
require_once(SM_PATH . 'functions/plugin.php');
require_once(SM_PATH . 'functions/files.php');

if (file_exists(SM_PATH . 'config/config.php')) {
    require_once(SM_PATH . 'config/config.php');
} else {
    require_once(SM_PATH . 'config/config_default.php');
}

require_once(SM_PATH . 'functions/prefs.php');
require_once(SM_PATH . 'plugins/calendar/calendar_data.php');

// Retrieve parameters
$user = !empty($_GET['user']) ? trim($_GET['user']) : '';
$token = !empty($_GET['token']) ? trim($_GET['token']) : '';

// Validation: prevent directory traversal or empty parameters
if (empty($user) || empty($token) || strpos($user, '..') !== false || strpos($user, '/') !== false || strpos($user, '\\') !== false) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo "400 Bad Request: Missing or invalid user/token parameter.\n";
    exit;
}

// Retrieve user's configured share token
$expectedToken = getPref($data_dir, $user, 'calendar_share_token', '');

// Validate share token securely
if (empty($expectedToken) || !hash_equals($expectedToken, $token)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden: Invalid or revoked calendar share token.\n";
    exit;
}

// Load events for the user
$username = $user;
$events = calendar_load_events($user);

// Export to RFC 5545 iCalendar format
$ics = calendar_export_ics($events);

// Send appropriate headers for iCalendar subscription
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="' . rawurlencode($user) . '_calendar.ics"');
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $ics;
exit;
