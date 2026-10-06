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

// If we are, go ahead to the login page.
header('Location: src/login.php');

