<?php

/**
 * Message Filters Plugin - Legacy Spam Options Redirect
 *
 * The obsolete DNS-based blacklist (DNSBL) filters from 2001 have been replaced
 * by the modern AI-assisted Spam Buttons & Reputation Dashboard (plugins/spam_buttons).
 * This script redirects existing bookmarks and requests to the modern dashboard.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage filters
 */

require('../../include/init.php');

$baseUri = function_exists('sqm_baseuri') ? sqm_baseuri() : (defined('SM_PATH') ? SM_PATH : '../../');
$target = $baseUri . 'plugins/spam_buttons/options.php';

if (!headers_sent()) {
    header('Location: ' . $target);
    exit;
}

echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body>';
echo '<script>window.location.replace(' . json_encode($target) . ');</script>';
echo '<p>' . _("Legacy DNSBL filters have been deprecated. Redirecting to modern Spam & AI Options...") . '</p>';
echo '<p><a href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">' . _("Click here to proceed to Spam & AI Options") . '</a></p>';
echo '</body></html>';
exit;
