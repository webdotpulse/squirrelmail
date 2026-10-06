<?php
/**
 * compose_attachments.tpl
 *
 * Modern Semantic Compose Attachments with Drag & Drop Dropzone (No layout tables)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
  <section class="sm-compose-attachments-section" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--sm-border);">
    <?php if($max_file_size != -1): ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_file_size; ?>" />
    <?php endif; ?>

    <!-- Interactive Drag-and-Drop Zone -->
    <div class="sm-dropzone">
      <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
        <span style="font-weight: 500; font-size: 14px;"><?php echo _("Drag & drop files here or click to browse"); ?></span>
        <?php if($max_file_size != -1): ?>
          <span style="font-size: 12px; color: var(--sm-text-muted);"><?php echo _("Maximum file size:"); ?> <?php echo humanReadableSize($max_file_size); ?></span>
        <?php endif; ?>
      </div>
      <input type="file" name="attachfile" id="attachfile" style="display: none;" <?php if ($accesskey_compose_attach_browse != 'NONE') echo 'accesskey="' . $accesskey_compose_attach_browse . '" '; ?> />
    </div>

    <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
      <input type="submit" name="attach" class="sm-btn sm-btn-secondary sm-btn-sm" <?php if ($accesskey_compose_attach != 'NONE') echo 'accesskey="' . $accesskey_compose_attach . '" '; ?>value="<?php echo _("Upload Selected Attachment"); ?>" />
      <?php if (!empty($plugin_output['add_attachment_notes'])) echo $plugin_output['add_attachment_notes']; ?>
    </div>

    <?php if (!empty($plugin_output['attachment_inputs'])) echo $plugin_output['attachment_inputs']; ?>

    <!-- Existing Attachments List -->
    <?php if (!empty($attachments) && count($attachments) > 0): ?>
    <div class="sm-attachments-list" style="margin-top: 16px;">
      <h4 style="font-size: 13px; font-weight: 600; color: var(--sm-text-secondary); margin-bottom: 8px;"><?php echo _("Attached Files"); ?>:</h4>
      <div style="display: flex; flex-direction: column; gap: 6px;">
        <?php
        $attachment_count = 1;
        foreach ($attachments as $attach):
        ?>
        <div class="sm-attachment-card" style="display: flex; align-items: center; justify-content: space-between;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" name="delete[]" id="delete<?php echo $attachment_count; ?>" accesskey="<?php echo ($attachment_count % 10); ?>" value="<?php echo $attach['Key']; ?>" />
            <label for="delete<?php echo $attachment_count; ?>" style="cursor: pointer; font-size: 13px;">
              <strong><?php echo sm_encode_html_special_chars($attach['FileName']); ?></strong>
              <span style="color: var(--sm-text-muted); font-size: 12px;">(<?php echo humanReadableSize($attach['Size'], $upload_filesize_divisor); ?>)</span>
            </label>
          </div>
          <span style="font-size: 11px; color: var(--sm-text-muted);"><?php echo sm_encode_html_special_chars($attach['ContentType']); ?></span>
        </div>
        <?php
        $attachment_count++;
        endforeach;
        ?>
      </div>
      <div style="margin-top: 10px;">
        <input type="submit" name="do_delete" class="sm-btn sm-btn-danger sm-btn-sm" <?php if ($accesskey_compose_delete_attach != 'NONE') echo 'accesskey="' . $accesskey_compose_delete_attach . '" '; ?>value="<?php echo _("Delete Selected Attachments"); ?>" />
      </div>
    </div>
    <?php endif; ?>
  </section>
</div> <!-- Close sm-compose-card -->
