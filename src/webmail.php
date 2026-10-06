<?php
/**
 * webmail.php -- Displays the main frameset
 *
 * This file generates the main frameset. The files that are
 * shown can be given as parameters. If the user is not logged in
 * this file will verify username and password.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 */

/** This is the webmail page */
define('PAGE_NAME', 'webmail');

/**
 * Include the SquirrelMail initialization file.
 */
require('../include/init.php');

if (sqgetGlobalVar('sort', $sort)) {
    $sort = (int) $sort;
}

if (sqgetGlobalVar('startMessage', $startMessage)) {
    $startMessage = (int) $startMessage;
}

if (!sqgetGlobalVar('mailbox', $mailbox)) {
    $mailbox = 'INBOX';
}

sqgetGlobalVar('right_frame', $right_frame, SQ_GET);

if (sqgetGlobalVar('mailtodata', $mailtodata)) {
    $mailtourl = 'mailtodata='.urlencode($mailtodata);
} else {
    $mailtourl = '';
}

// Determine the size of the left frame
$left_size = getPref($data_dir, $username, 'left_size');
if ($left_size == "") {
    if (isset($default_left_size)) {
         $left_size = $default_left_size;
    }
    else {
        $left_size = 200;
    }
}

// Determine where the navigation frame should be
$location_of_bar = getPref($data_dir, $username, 'location_of_bar');
if (isset($languages[$squirrelmail_language]['DIR']) &&
    strtolower($languages[$squirrelmail_language]['DIR']) == 'rtl') {
    $temp_location_of_bar = 'right';
} else {
    $temp_location_of_bar = 'left';
}
if ($location_of_bar == '') {
    $location_of_bar = $temp_location_of_bar;
}

// this value may be changed by a plugin, but initialize
// it first to avoid register_globals headaches
//
$right_frame_url = '';
do_hook('webmail_top', $null);

// Determine the main frame URL
/*
 * There are three ways to call webmail.php
 * 1.  webmail.php
 *      - This just loads the default entry screen.
 * 2.  webmail.php?right_frame=right_main.php&sort=X&startMessage=X&mailbox=XXXX
 *      - This loads the frames starting at the given values.
 * 3.  webmail.php?right_frame=folders.php
 *      - Loads the frames with the Folder options in the right frame.
 *
 * This was done to create a pure HTML way of refreshing the folder list since
 * we would like to use as little Javascript as possible.
 *
 * The test for // should catch any attempt to include off-site webpages into
 * our frameset.
 *
 * Note that plugins are allowed to completely and freely override the URI
 * used for the "right" (content) frame, and they do so by modifying the
 * global variable $right_frame_url.
 *
 */
if (empty($right_frame) || (strpos(urldecode($right_frame), '//') !== false)) {
    $right_frame = '';
}
if ( strpos($right_frame,'?') ) {
    $right_frame_file = substr($right_frame,0,strpos($right_frame,'?'));
} else {
    $right_frame_file = $right_frame;
}
if (empty($right_frame_url)) {
    switch($right_frame) {
        case 'right_main.php':
            $right_frame_url = "right_main.php?mailbox=".urlencode($mailbox)
                           . (!empty($sort)?"&amp;sort=$sort":'')
                           . (!empty($startMessage)?"&amp;startMessage=$startMessage":'');
            break;
        case 'options.php':
            $right_frame_url = 'options.php';
            break;
        case 'folders.php':
            $right_frame_url = 'folders.php';
            break;
        case 'compose.php':
            $right_frame_url = 'compose.php?' . $mailtourl;
            break;
        case '':
            $right_frame_url = 'right_main.php';
            break;
        default:
            $right_frame_url =  urlencode($right_frame);
            break;
    }
}

$GLOBALS['in_webmail_shell'] = true;

// Pre-render the sidebar
ob_start();
include(SM_PATH . 'src/left_main.php');
$sidebar_content = ob_get_clean();

// Pre-render initial workspace view
if (!empty($right_frame) && strpos($right_frame, '?') !== false) {
    parse_str(parse_url($right_frame, PHP_URL_QUERY), $query_params);
    if (!empty($query_params)) {
        foreach ($query_params as $qk => $qv) {
            $_GET[$qk] = $qv;
            ${$qk} = $qv;
        }
    }
}

ob_start();
$target_script = !empty($right_frame_file) ? basename($right_frame_file) : 'right_main.php';
if ($target_script != 'webmail.php' && file_exists(SM_PATH . 'src/' . $target_script)) {
    include(SM_PATH . 'src/' . $target_script);
} else {
    include(SM_PATH . 'src/right_main.php');
}
$workspace_content = ob_get_clean();

$GLOBALS['in_webmail_shell'] = false;

$oErrorHandler->setDelayedErrors(true);

$oTemplate->assign('sidebar_content', $sidebar_content);
$oTemplate->assign('workspace_content', $workspace_content);
$oTemplate->assign('mailbox', $mailbox);
$oTemplate->assign('right_frame_url', $right_frame_url);

displayHtmlHeader($org_title, '', false, false);

$oTemplate->display('webmail.tpl');

