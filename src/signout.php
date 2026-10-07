<?php

/**
 * signout.php -- cleans up session and logs the user out
 *
 * Cleans up after the user. Resets cookies, terminates session,
 * and redirects directly to the login page (or custom signout_page).
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 */

/** This is the signout page */
define('PAGE_NAME', 'signout');

/**
 * Include the SquirrelMail initialization file.
 */
require('../include/init.php');

/* Erase any lingering attachments */
sqgetGlobalVar('compose_messages',  $compose_messages,  SQ_SESSION);

if (!empty($compose_message) && is_array($compose_messages)) {
    foreach($compose_messages as $composeMessage) {
        $composeMessage->purgeAttachments();
    }
}

if (!isset($frame_top)) {
    $frame_top = '_top';
}

$login_uri = 'login.php';

do_hook('logout', $login_uri);

sqsession_destroy();

// Redirect directly to the login page, skipping the signout confirmation page
$target = !empty($signout_page) ? $signout_page : $login_uri;

if (!headers_sent()) {
    header("Location: $target");
    exit;
} else {
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES) . '"><script>top.location.href=' . json_encode($target) . ';</script></head><body></body></html>';
    exit;
}
