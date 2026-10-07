<?php
/**
 * read_menubar_buttons.tpl
 *
 * Tempalte for displaying the action buttons, e.g. Reply, Reply All, Forward,
 * etc., while reading a message.  When combined with the read_menubar_nav template,
 * the entire menu bar is displayed.
 * 
 * The following variables are available in this template:
 *    $nav_on_top       - boolean TRUE if the navigation buttons are on top of the
 *                        action buttons generated here.
 *    $prev_href        - URL to move to the previous message.  Empty if not avilable.
 *    $up_href          - URL to move up in the message.  Empty if not available.
 *    $next_href    - URL to move to the next nessage.  Empty when N/A.
 *    $del_prev_href - URL to delete this message and move to the next one.  Empty if N/A.
 *    $del_next_href - URL to delete this message and move to the next one.  Empty if N/A.
 *    $view_msg_href - URL to go back to the main message.  Empty if N/A.
 *    $msg_list_href - URL to go to the message list.
 *    $search_href   - URL to go back to the serach results.  Empty if N/A.
 *    $form_extra    - Extra elements that will be added to the <form> tag verbatim
 *    $form_method   - The value of the <form>'s method attribute (optional, may be blank)
 *    $form_target   - The value of the <form>'s target attribute (optional, may be blank)
 *    $form_onsubmit - The value of the <form>'s onsubmit handler (optional, may be blank)
 *    $compose_href  - Base URL to forward, reply, etc.  Note that a specific action
 *                     must also be given by the form or in this URL.
 *    $button_onclick - Onclick event string for all buttons
 *    $forward_as_attachment_enabled - boolean TRUE if forwarding as attachments
 *                     has been enabled.
 *    $can_resume_draft - boolean TRUE if the "resume draft" is legitimate for
 *                     this message.
 *    $can_edit_as_new - boolean TRUE if the "reasume as new" action is legitimate
 *                     for this message
 *    $mailboxes     - array containing list of mailboxes available for move/copy action.
 *    $can_be_deleted - boolean TRUE if this message can be deleted.
 *    $can_be_moved  - boolean TRUE if this message can be moved.
 *    $cab_be_copied - boolean TRUE if this message can be copied to another folder.
 *    $move_delete_form_action - the value for the ACTION attribute of forms to
 *                     move, copy or delete a message
 *    $delete_form_extra - additional input elements needed by the DELETE form
 *    $move_form_extra - additional input elements needed by the MOVE form.
 *    $last_move_target - the last folder that a message was moved/copied to. 
 *    $accesskey_read_msg_reply        - The accesskey to be used for the Reply button
 *    $accesskey_read_msg_reply_all    - The accesskey to be used for the Reply All button
 *    $accesskey_read_msg_forward      - The accesskey to be used for the Forward button
 *    $accesskey_read_msg_as_attach    - The accesskey to be used for the As Attachment checkbox
 *    $accesskey_read_msg_delete       - The accesskey to be used for the Delete button
 *    $accesskey_read_msg_bypass_trash - The accesskey to be used for the Bypass Trash checkbox
 *    $accesskey_read_msg_move_to      - The accesskey to be used for the folder select list
 *    $accesskey_read_msg_move         - The accesskey to be used for the Move button
 *    $accesskey_read_msg_copy         - The accesskey to be used for the Copy button
 *    
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

/** add required includes **/

/** extract template variables **/
extract($t);

/*FIXME: This is a place where Marc's idea for putting all the buttons and links and other widgets into an array is sorely needed instead of hard-coding everything.  Whomever implements that, PLEASE, PLEASE look at how the preview pane plugin code is used in this same template file for the *default_advanced* set to change some links and buttons and make sure your implementation can support it (tip: it may or may not be OK to let a plugin do the modification of the widgets, since a template set can turn on the needed plugin, but that might not be the most clear way to solve said issue).*/

/** Begin template **/
if ($nav_on_top) {
    $table_class = 'bottom';
    $plugin_hook = 'read_body_menu_buttons_top';
} else {
    $table_class = 'top';
    $plugin_hook = 'read_body_menu_buttons_bottom';
}
?>
<div class="readMenuBar">
<table class="<?php echo $table_class; ?>" cellspacing="0">
 <tr class="buttons">
  <td class="buttons">
   <form name="composeForm" action="<?php echo sqm_baseuri(); ?>src/compose.php" method="get"<?php echo (!empty($form_target) ? ' target="' . $form_target . '"' : ''); ?> style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; margin: 0;">
    <input type="hidden" name="passed_id" value="<?php echo htmlspecialchars((string)$passed_id, ENT_QUOTES); ?>" />
    <input type="hidden" name="mailbox" value="<?php echo htmlspecialchars((string)$mailbox, ENT_QUOTES); ?>" />
    <input type="hidden" name="startMessage" value="<?php echo htmlspecialchars((string)($startMessage ?? 1), ENT_QUOTES); ?>" />
    <?php if (!empty($passed_ent_id)) { ?>
    <input type="hidden" name="passed_ent_id" value="<?php echo htmlspecialchars((string)$passed_ent_id, ENT_QUOTES); ?>" />
    <?php } ?>

    <?php if ($can_resume_draft) { ?>
    <button type="submit" name="smaction" value="draft" class="sm-btn sm-btn-secondary sm-btn-sm" title="<?php echo _("Resume Draft"); ?>">
      <?php echo _("Resume Draft"); ?>
    </button>
    <?php } elseif ($can_edit_as_new) { ?>
    <button type="submit" name="smaction" value="edit_as_new" class="sm-btn sm-btn-secondary sm-btn-sm" title="<?php echo _("Edit Message as New"); ?>">
      <?php echo _("Edit Message as New"); ?>
    </button>
    <?php } ?>

    <button type="submit" name="smaction" value="reply" class="sm-btn sm-btn-secondary sm-btn-sm" <?php if ($accesskey_read_msg_reply != 'NONE') echo 'accesskey="' . $accesskey_read_msg_reply . '" '; ?>title="<?php echo _("Reply"); ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>
      <?php echo _("Reply"); ?>
    </button>
    <button type="submit" name="smaction" value="reply_all" class="sm-btn sm-btn-secondary sm-btn-sm" <?php if ($accesskey_read_msg_reply_all != 'NONE') echo 'accesskey="' . $accesskey_read_msg_reply_all . '" '; ?>title="<?php echo _("Reply All"); ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><polyline points="7 17 2 12 7 7"></polyline><polyline points="12 17 7 12 12 7"></polyline><path d="M22 18v-2a4 4 0 0 0-4-4H7"></path></svg>
      <?php echo _("Reply All"); ?>
    </button>
    <button type="submit" name="smaction" value="forward" class="sm-btn sm-btn-secondary sm-btn-sm" <?php if ($accesskey_read_msg_forward != 'NONE') echo 'accesskey="' . $accesskey_read_msg_forward . '" '; ?>title="<?php echo _("Forward"); ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: -2px;"><polyline points="15 17 20 12 15 7"></polyline><path d="M4 18v-2a4 4 0 0 1 4-4h12"></path></svg>
      <?php echo _("Forward"); ?>
    </button>
    <?php if ($forward_as_attachment_enabled) { ?>
    <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--sm-text-secondary); cursor: pointer; margin-left: 2px;">
      <input type="checkbox" name="smaction_attache" id="smaction_attache" value="1" <?php if ($accesskey_read_msg_as_attach != 'NONE') echo 'accesskey="' . $accesskey_read_msg_as_attach . '" '; ?>/>
      <?php echo _("As Attachment"); ?>
    </label>
    <?php } ?>
   </form>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <?php
    if ($can_be_deleted) {
        ?>
    <form name="deleteMessageForm" action="<?php echo $move_delete_form_action; ?>" method="post" style="display: inline-flex; align-items: center; gap: 6px; margin: 0;">
     <input type="hidden" name="smtoken" value="<?php echo sm_generate_security_token(); ?>" />
     <?php echo $delete_form_extra; ?>
     <input type="submit" name="delete" class="sm-btn sm-btn-danger sm-btn-sm" <?php if ($accesskey_read_msg_delete != 'NONE') echo 'accesskey="' . $accesskey_read_msg_delete . '" '; ?>value="<?php echo _("Delete"); ?>" />
     <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--sm-text-secondary); cursor: pointer;"><input type="checkbox" name="bypass_trash" id="bypass_trash" <?php if ($accesskey_read_msg_bypass_trash != 'NONE') echo 'accesskey="' . $accesskey_read_msg_bypass_trash . '" '; ?>/><?php echo _("Bypass Trash"); ?></label>
    </form>
        <?php
    }
    ?>
   <?php if(!empty($plugin_output[$plugin_hook])) echo $plugin_output[$plugin_hook]; ?>
  </td>
  <td class="move">
   <?php
    if ($can_be_moved) {
        ?>
    <form name="moveMessageForm" action="<?php echo $move_delete_form_action; ?>" method="post" style="display: inline-flex; align-items: center; gap: 6px; margin: 0;">
     <input type="hidden" name="smtoken" value="<?php echo sm_generate_security_token(); ?>" />
     <?php echo $move_form_extra; ?>
     <span style="font-size: 12px; color: var(--sm-text-secondary);"><?php echo _("Move To"); ?>:</span>
     <select class="sm-select sm-select-sm" style="font-size: 12px; padding: 4px 8px;" <?php if ($accesskey_read_msg_move_to != 'NONE') echo 'accesskey="' . $accesskey_read_msg_move_to . '" '; ?>name="targetMailbox">
     <?php
        foreach ($mailboxes as $value=>$option) {
            echo '<option value="'. $value .'"' . ($value==$last_move_target ? ' selected="selected"' : '').'>' . $option .'</option>'."\n";
        }
     ?>
     </select>
     <input type="submit" name="moveButton" class="sm-btn sm-btn-secondary sm-btn-sm" <?php if ($accesskey_read_msg_move != 'NONE') echo 'accesskey="' . $accesskey_read_msg_move . '" '; ?>value="<?php echo _("Move"); ?>" />
     <?php
        if ($can_be_copied) {
            ?>
     <input type="submit" name="copyButton" class="sm-btn sm-btn-secondary sm-btn-sm" <?php if ($accesskey_read_msg_copy != 'NONE') echo 'accesskey="' . $accesskey_read_msg_copy . '" '; ?>value="<?php echo _("Copy"); ?>" />
            <?php
        }
     ?>
    </form>
        <?php
    }
   ?>
  </td>
 </tr>
</table>
</div>
