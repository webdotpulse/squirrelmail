<?php
/**
 * compose_buttons.tpl
 *
 * Modern Semantic Compose Toolbar & Options (No layout tables)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
  <section class="sm-compose-toolbar" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--sm-border); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
    <!-- Options: Receipts -->
    <div class="sm-compose-meta-options" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
      <?php if ($notifications_enabled): ?>
      <div style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--sm-text-secondary);">
        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
          <input type="checkbox" name="request_mdn" id="request_mdn" value="1" <?php if ($read_receipt) echo ' checked="checked"'; ?> <?php if ($accesskey_compose_on_read != 'NONE') echo 'accesskey="' . $accesskey_compose_on_read . '" '; ?>/>
          <span><?php echo _("Read Receipt"); ?></span>
        </label>
        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
          <input type="checkbox" name="request_dr" id="request_dr" value="1" <?php if ($delivery_receipt) echo ' checked="checked"'; ?> <?php if ($accesskey_compose_on_delivery != 'NONE') echo 'accesskey="' . $accesskey_compose_on_delivery . '" '; ?>/>
          <span><?php echo _("Delivery Receipt"); ?></span>
        </label>
      </div>
      <?php endif; ?>
    </div>

    <!-- Actions: Signature, Addressbook, Draft, Send -->
    <div class="sm-compose-main-actions" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
      <input type="submit" name="sigappend" class="sm-btn sm-btn-secondary" <?php if ($accesskey_compose_signature != 'NONE') echo 'accesskey="' . $accesskey_compose_signature . '" '; ?>value="<?php echo _("Signature"); ?>" />
      <?php if (!empty($address_book_button)): ?>
        <?php echo str_replace('class="', 'class="sm-btn sm-btn-secondary ', $address_book_button); ?>
      <?php endif; ?>
      <?php if ($drafts_enabled): ?>
      <input type="submit" name="draft" class="sm-btn sm-btn-secondary" <?php if ($accesskey_compose_save_draft != 'NONE') echo 'accesskey="' . $accesskey_compose_save_draft . '" '; ?>value="<?php echo _("Save Draft"); ?>" />
      <?php endif; ?>
      <input type="submit" class="sm-btn sm-btn-primary" <?php if (!unique_widget_name('send', TRUE) && $accesskey_compose_send != 'NONE') echo 'accesskey="' . $accesskey_compose_send . '" '; ?>name="<?php echo unique_widget_name('send'); ?>" value="<?php echo _("Send"); ?>" />
      <?php if (!empty($plugin_output['compose_button_row'])) echo $plugin_output['compose_button_row']; ?>
    </div>
  </section>
