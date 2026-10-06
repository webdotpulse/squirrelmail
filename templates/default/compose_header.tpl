<?php
/**
 * compose_header.tpl
 *
 * Modern Semantic Compose Header (No layout tables)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
<div class="sm-compose-card">
  <section class="sm-compose-header-fields">
    <?php if (count($identities) > 1): ?>
    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="identity"><?php echo _("From"); ?>:</label>
      <select name="identity" <?php if ($accesskey_compose_identity != 'NONE') echo 'accesskey="' . $accesskey_compose_identity . '" '; ?>id="identity" class="sm-compose-select">
        <?php
        foreach ($identities as $id=>$ident) {
            echo '<option value="'.$id.'"'. ($identity_def==$id ? ' selected="selected"' : '') .'>'. $ident .'</option>';
        }
        ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="to"><?php echo _("To"); ?>:</label>
      <input type="text" name="send_to" id="to" class="sm-compose-input" value="<?php echo $to; ?>" placeholder="<?php echo _("Recipients"); ?>" <?php if ($accesskey_compose_to != 'NONE') echo 'accesskey="' . $accesskey_compose_to . '" '; echo $input_onfocus; ?> />
    </div>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="send_to_cc"><?php echo _("Cc"); ?>:</label>
      <input type="text" name="send_to_cc" id="send_to_cc" class="sm-compose-input" value="<?php echo $cc; ?>" placeholder="<?php echo _("Carbon copy"); ?>" <?php if ($accesskey_compose_cc != 'NONE') echo 'accesskey="' . $accesskey_compose_cc . '" '; echo $input_onfocus; ?> />
    </div>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="send_to_bcc"><?php echo _("Bcc"); ?>:</label>
      <input type="text" name="send_to_bcc" id="send_to_bcc" class="sm-compose-input" value="<?php echo $bcc; ?>" placeholder="<?php echo _("Blind carbon copy"); ?>" <?php if ($accesskey_compose_bcc != 'NONE') echo 'accesskey="' . $accesskey_compose_bcc . '" '; echo $input_onfocus; ?> />
    </div>

    <div class="sm-compose-field-row sm-compose-field-subject">
      <label class="sm-compose-label" for="subject"><?php echo _("Subject"); ?>:</label>
      <input type="text" name="subject" id="subject" class="sm-compose-input" value="<?php echo $subject; ?>" placeholder="<?php echo _("Subject line"); ?>" <?php if ($accesskey_compose_subject != 'NONE') echo 'accesskey="' . $accesskey_compose_subject . '" '; echo $input_onfocus; ?> />
    </div>
  </section>
