<?php
/**
 * compose_body.tpl
 *
 * Modern Semantic Compose Body (No layout tables)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
  <section class="sm-compose-body-section">
    <textarea name="body" id="body" class="sm-compose-textarea" rows="<?php echo $editor_height; ?>" cols="<?php echo $editor_width; ?>" placeholder="<?php echo _("Write your message here..."); ?>" <?php if ($accesskey_compose_body != 'NONE') echo 'accesskey="' . $accesskey_compose_body . '" '; echo $input_onfocus; ?>><?php echo $body; ?></textarea>

    <?php if ($show_bottom_send): ?>
    <div class="sm-compose-bottom-actions" style="margin-top: 12px; display: flex; justify-content: flex-end;">
      <input type="submit" class="sm-btn sm-btn-primary" <?php if (!unique_widget_name('send', TRUE) && $accesskey_compose_send != 'NONE') echo 'accesskey="' . $accesskey_compose_send . '" '; ?>name="<?php echo unique_widget_name('send'); ?>" value="<?php echo _("Send"); ?>" />
    </div>
    <?php endif; ?>
  </section>
