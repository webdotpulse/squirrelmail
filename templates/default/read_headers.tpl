<?php
/**
 * read_headers.tpl
 *
 * Modern Semantic Message Header Envelope (No layout tables)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
<div class="sm-email-header-card" style="background: var(--sm-bg-surface); border: 1px solid var(--sm-border); border-radius: var(--sm-radius-md); padding: 18px 22px; margin-bottom: 16px; box-shadow: var(--sm-shadow-sm);">
  <div class="sm-email-header-rows" style="display: flex; flex-direction: column; gap: 8px;">
    <?php
    foreach ($headers_to_display as $field_name => $value) {
        if (empty($value)) {
            continue;
        }
        ?>
        <div class="sm-email-header-row field_<?php echo $field_name; ?>" style="display: flex; align-items: baseline; gap: 14px; font-size: 13.5px;">
          <span class="fieldName" style="width: 70px; font-weight: 600; color: var(--sm-text-muted); flex-shrink: 0; text-align: right;"><?php echo $field_name; ?>:</span>
          <span class="fieldValue" style="color: var(--sm-text-primary); word-break: break-word; flex: 1;"><?php echo $value; ?></span>
        </div>
        <?php
    }
    if (!empty($plugin_output['read_body_header'])) echo $plugin_output['read_body_header'];
    ?>
  </div>
</div>
