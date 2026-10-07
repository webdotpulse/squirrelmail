<?php
/**
 * left_main.tpl
 *
 * Basic template to the left main window.  
 * 
 * The following variables are avilable in this template:
 *      $clock           - formatted string containing last refresh
 *      $settings        - Array containing user perferences needed by this
 *                         template.  Indexes are as follows:
 *          $settings['templateID'] - contains the ID of the current
 *                         template set.  This may be needed by third
 *                         party packages that don't integrate easily.
 *          $settings['unreadNotificationEnabled'] - Boolean TRUE if the user
 *                         wants to see unread message count on mailboxes
 *          $settings['unreadNotificationCummulative'] - Boolean TRUE if the
 *                         user has enabled cummulative message counts.
 *          $settings['unreadNotificationAllFolders'] - Boolean TRUE if the
 *                         user wants to see unread message count on ALL
 *                         folders or just the Inbox.
 *          $settings['unreadNotificationDisplayTotal'] - Boolean TRUE if the
 *                         user wants to see the total number of messages in
 *                         addition to the unread message count.
 *          $settings['collapsableFoldersEnabled'] - Boolean TRUE if the user
 *                         has enabled collapsable folders.
 *          $settings['useSpecialFolderColor'] - Boolean TRUE if the use has
 *                         chosen to tag "Special" folders in a different color
 *          $settings['messageRecyclingEnabled'] - Boolean TRUE if messages
 *                         that get deleted go to the Trash folder.  FALSE if
 *                          they are permanently deleted.
 *
 *      $mailboxes       - Associative array of current mailbox structure.
 *                         Provided so template authors know what they have to
 *                         work with when building a custom mailbox tree.
 *                         Array contains the following elements:
 *          $a['MailboxName']   = String containing the name of the mailbox
 *          $a['MailboxFullName'] = String containing full IMAP name of mailbox
 *          $a['MessageCount']  = integer of all messages in the mailbox
 *          $a['UnreadCount']   = integer of unseen message in the mailbox
 *          $a['ViewLink']      = array containing elements needed to view the
 *                                mailbox.  Elements are:
 *                                  'Target' = target frame for link
 *                                  'URL'    = target URL for link
 *          $a['IsRecent']      = boolean TRUE if the mailbox is tagged "recent"
 *          $a['IsSpecial']     = boolean TRUE if the mailbox is tagged "special"
 *          $a['IsRoot']        = boolean TRUE if the mailbox is the root mailbox
 *          $a['IsNoSelect']    = boolean TRUE if the mailbox is tagged "noselect"
 *          $a['IsCollapsed']   = boolean TRUE if the mailbox is currently collapsed
 *          $a['CollapseLink']  = array containg elements needed to expand/collapse
 *                                the mailbox.  Elements are:
 *                                  'Target' = target frame for link
 *                                  'URL'    = target URL for link
 *                                  'Icon'   = the icon to use, based on user prefs
 *          $a['ChildBoxes']    = array containing this same data structure for
 *                                each child folder/mailbox of the current
 *                                mailbox.
 *          $a['CummulativeMessageCount']   = integer of total messages in all
 *                                            folders in this mailbox, exlcuding
 *                                            trash folders.
 *          $a['CummulativeUnreadCount']    = integer of total unseen messages
 *                                            in all folders in this mailbox,
 *                                            excluding trash folders.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 * @author Steve Brown
 */


/*
 * Recursively parse the mailbox structure to build the navigation tree.
 *
 * @since 1.5.2
 */
if (!function_exists('buildMailboxTree')) {
function buildMailboxTree ($box, $settings, $icon_theme_path, $indent_factor=0) {
    // stop condition
    if (empty($box)) {
        return '';
    }

    $pre = '<span style="white-space: nowrap;">';
    $end = '';
    $indent = str_repeat('&nbsp;&nbsp;',$indent_factor);

    // Get unseeen/total message info if needed
    $unseen_str = '';
    if ($settings['unreadNotificationEnabled'])   {
        // We only display the unread count if we on the Inbox or we are told
        // to display it on all folders AND there is more than 1 unread message
        $mbxLower = strtolower($box['MailboxFullName']);
        $delimiter = $_SESSION['delimiter'] ?? '';
        $isInboxOrSub = ($mbxLower === 'inbox' || str_starts_with($mbxLower, 'inbox.') || str_starts_with($mbxLower, 'inbox/') || (!empty($delimiter) && str_starts_with($mbxLower, 'inbox' . $delimiter)));
        if ( $settings['unreadNotificationAllFolders'] ||
             (!$settings['unreadNotificationAllFolders'] && $isInboxOrSub)
           )  {
            $unseen = $settings['unreadNotificationCummulative'] ?
                            $box['CummulativeUnreadCount'] :
                            $box['UnreadCount'];
            
            if (!$box['IsNoSelect'] && ($unseen > 0 || $settings['unreadNotificationDisplayTotal'])) {
                $unseen_str = $unseen;
    
                // Add the total messages if desired
                if ($settings['unreadNotificationDisplayTotal'])    {
                    $unseen_str .= '/' . ($settings['unreadNotificationCummulative'] ?
                                            $box['CummulativeMessageCount'] :
                                            $box['MessageCount']);
                }

                $unseen_str = '<span class="sm-badge sm-unread-badge '.
                              ($box['IsRecent'] ? 'leftrecent' : 'leftunseen') .
                              '">' . $unseen_str .
                              '</span>';
            }
        }
    }

    /*
     * If the box has any children, and collapsable folders have been enabled
     * we need to output the expand/collapse link.
     */
    if (sizeof($box['ChildBoxes'])>0 && $settings['collapsableFoldersEnabled'])    {
        $link = $indent .
                '<a href="'.$box['CollapseLink']['URL'].'" ' .
                (!empty($box['CollapseLink']['Target']) ? 'target="'.$box['CollapseLink']['Target'].'" ' : '') .
                'style="text-decoration:none" ' .
                '>' .
                $box['CollapseLink']['Icon'] .
                '</a>';
        $pre .= $link;
    } else {
        $pre .= $indent . '&nbsp;&nbsp;';
    }

    /**
     * Add folder icon.  Template authors may choose to display a different
     * image based on whatever logic they see fit here.
     */
    $folder_icon = '';
    if (!is_null($icon_theme_path)) {
        $isJunkBox = !empty($box['IsJunk']) || (function_exists('isJunkMailbox') && isJunkMailbox($box['MailboxFullName'])) || preg_match('/^(junk|spam|bulk|junk\s*e-?mail|bulk\s*mail)$/i', trim($box['MailboxName']));
        switch (true) {
            case $box['IsInbox']:
                $folder_icon = getIcon($icon_theme_path, 'inbox.svg', '', $box['MailboxName']);
                break; 
            case $box['IsSent']:
                $folder_icon = getIcon($icon_theme_path, 'senti.svg', '', $box['MailboxName']);
                break; 
            case $box['IsTrash']:
                $folder_icon = getIcon($icon_theme_path, 'delitem.svg', '', $box['MailboxName']);
                break; 
            case $box['IsDraft']:
                $folder_icon = getIcon($icon_theme_path, 'draft.svg', '', $box['MailboxName']);
                break; 
            case $isJunkBox:
                $folder_icon = getIcon($icon_theme_path, 'junk.svg', '', $box['MailboxName']);
                break; 
            case $box['IsNoInferiors']:
                $folder_icon = getIcon($icon_theme_path, 'folder_noinf.svg', '', $box['MailboxName']);
                break;
            default: 
                $folder_icon = getIcon($icon_theme_path, 'folder.svg', '', $box['MailboxName']);
                break;
        }
        if (!empty($folder_icon)) {
            $folder_icon = '<span class="sm-folder-icon' . ($isJunkBox ? ' sm-folder-icon-junk' : '') . '">' . $folder_icon . '</span>&nbsp;';
        }
    }
    $pre .= $folder_icon;

    // calculate if access key is needed
    //
    if ($box['IsInbox']) {
        global $accesskey_folders_inbox;
        $accesskey = $accesskey_folders_inbox;
    }
    else $accesskey = '';
    
    /*
     * The Trash folder should only be displayed if message recycling has
     * been enabled, i.e. when deleted is a message moved to the trash or
     * deleted forever?
     */
    $isJunkBox = !empty($isJunkBox);
    $folder_type_class = $box['IsInbox'] ? ' sm-folder-inbox' : ($box['IsSent'] ? ' sm-folder-sent' : ($box['IsTrash'] ? ' sm-folder-trash' : ($box['IsDraft'] ? ' sm-folder-draft' : ($isJunkBox ? ' sm-folder-junk' : ''))));
    $view_link = '<a href="'.$box['ViewLink']['URL'].'" ' .
                 ($accesskey == '' ? '' : 'accesskey="' . $accesskey . '" ') .
                 (!empty($box['ViewLink']['Target']) ? 'target="'.$box['ViewLink']['Target'].'" ' : '') .
                 'class="sm-folder-link' . ($box['IsSpecial'] ? ' sm-folder-special' : '') . $folder_type_class . '" ' .
                 'title="'.$box['MailboxName'].'" ' .
                 'style="text-decoration:none">';

    if ($settings['messageRecyclingEnabled'] && $box['IsTrash']) {
        $pre .= $view_link;

        // Boxes with unread messages should be emphasized
        if ($box['UnreadCount'] > 0) {
            $pre .= '<em>';
            $end .= '</em>';
        }
        $end .= '</a>';

        // Print unread info
        if ($box['MessageCount'] > 0 || count($box['ChildBoxes'])) {
            if (!empty($unseen_str)) {
                $end .= '&nbsp;<span class="sm-unread-badge-wrap">' . $unseen_str . '</span>';
            }
            $end .= "\n<small>" .
                    '&nbsp;&nbsp;[<a href="' . sqm_baseuri() . 'src/empty_trash.php?smtoken=' . sm_generate_security_token() . '">'. _("Purge").'</a>]' .
                    '</small>';
        }
    } else {
        // Add a few other things for all other folders...
        if (!$box['IsNoSelect']) {
            $pre .= $view_link;

            // Boxes with unread messages should be emphasized
            if ($box['UnreadCount'] > 0) {
                $pre .= '<em>';
                $end .= '</em>';
            }
            $end .= '</a>';
        }

        // Display unread info...
        if (!empty($unseen_str)) {
            $end .= '&nbsp;<span class="sm-unread-badge-wrap">' . $unseen_str . '</span>';
        }
    }

    // Add any extra output that may have been added by plugins, etc
    //
    if (!empty($box['ExtraOutput']))
        $end .= $box['ExtraOutput'];

    $span = '';
    $spanend = '';
    if ($settings['useSpecialFolderColor'] && $box['IsSpecial']) {
        $span = '<span class="leftspecial">';
        $spanend = '</span>';
    } elseif ( $box['IsNoSelect'] ) {
        $span = '<span class="leftnoselect">';
        $spanend = '</span>';
    }

    $end .= '</span>';

    $out = '';
    if (!$box['IsRoot']) {
        $out = $span . $pre .
               str_replace(
                    array(' ','<','>'),
                    array('&nbsp;','&lt;','&gt;'),
                    $box['MailboxName']) .
               $end . $spanend . '<br />' . "\n";
        $indent_factor++;
    }

    if (!$box['IsCollapsed'] || $box['IsRoot']) {
        for ($i = 0; $i<sizeof($box['ChildBoxes']); $i++) {
            $out .= buildMailboxTree($box['ChildBoxes'][$i], $settings, $icon_theme_path, $indent_factor);
        }
    }

    return $out;
}
}

// Retrieve the template vars
extract($t);

?>
<?php if (empty($GLOBALS['in_webmail_shell'])) { ?>
<body class="sqm_leftMain">
<?php } ?>
<div class="sqm_leftMain">
<?php if (!empty($plugin_output['left_main_before'])) echo $plugin_output['left_main_before']; ?>
<div class="sm-sidebar-folders-wrapper">
 <div class="sm-sidebar-folders-header">
  <div class="sm-sidebar-header-main">
   <div class="sm-sidebar-header-actions">
    <a href="<?php echo sqm_baseuri(); ?>src/left_main.php" <?php if ($accesskey_folders_refresh != 'NONE') echo 'accesskey="' . $accesskey_folders_refresh . '" '; ?>class="sm-sidebar-action-btn sm-folder-refresh-btn" title="<?php echo _("Check Mail"); ?>" aria-label="<?php echo _("Check Mail"); ?>">
     <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
     <span><?php echo _("Check Mail"); ?></span>
    </a>
   </div>
  </div>
  <?php
    $display_clock = !empty($clock_time) ? $clock_time : '';
    if (empty($display_clock) && !empty($clock)) {
        $clean_clk = trim(strip_tags(str_replace(array('&nbsp;', '<br />', '<br>'), array(' ', ' ', ' '), $clock)));
        $display_clock = trim(preg_replace('/^.*?:\s*/', '', $clean_clk));
    }
    if (!empty($display_clock)) {
  ?>
  <div class="sm-sidebar-refresh-time">
   <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
   <span><?php echo _("Last Refresh"); ?>: <?php echo $display_clock; ?></span>
  </div>
  <?php } ?>
 </div>
 <div class="sm-sidebar-tree-container">
  <?php echo buildMailboxTree($mailboxes, $settings, $icon_theme_path); ?>
 </div>
</div>
<?php if (!empty($plugin_output['left_main_after'])) echo $plugin_output['left_main_after']; ?>
</div>
<?php if (empty($GLOBALS['in_webmail_shell'])) { ?>
</body>
<?php } ?>
