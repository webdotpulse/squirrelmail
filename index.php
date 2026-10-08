<?php

/**
 * index.php
 *
 * Redirects to the login page.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 */

// Are we configured yet?
if( ! file_exists ( 'config/config.php' ) ) {
    if ( file_exists( 'install.php' ) ) {
        header('Location: install.php');
        exit;
    }
    echo '<html><body><p><strong>ERROR:</strong> Config file ' .
        '&quot;<tt>config/config.php</tt>&quot; not found. You need to ' .
        'configure SquirrelMail before you can use it. Please run <a href="install.php">install.php</a>.</p></body></html>';
    exit;
}

// Check if user already has an active authenticated session
define('SM_PATH', './');
if (file_exists('include/constants.php')) {
    require_once('include/constants.php');
}
$session_name = 'squirrelmail_session';
@include('config/config.php');
if (!empty($session_name)) {
    session_name($session_name);
}
if (!empty($_COOKIE[session_name()]) && !empty($_COOKIE['key'])) {
    $sSessionSavePath = ini_get('session.save_path');
    if (empty($sSessionSavePath) || !is_dir($sSessionSavePath) || !is_writable($sSessionSavePath)) {
        $sTempDir = sys_get_temp_dir();
        if (is_dir($sTempDir) && is_writable($sTempDir)) {
            session_save_path($sTempDir);
        }
    }
    @session_start();
    if (!empty($_SESSION['user_is_logged_in'])) {
        header('Location: src/webmail.php');
        exit;
    }
}

// If not logged in, go directly to the login page.
header('Location: src/login.php');
exit;

