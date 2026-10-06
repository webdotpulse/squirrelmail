<?php
/**
 * read_message_body.tpl
 *
 * Template for displaying the message body with Shadow DOM sandboxing (zero iframes).
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);

if (!empty($plugin_output['read_body_top'])) echo $plugin_output['read_body_top']; 
?>
<div class="readBody sm-read-body-wrapper">
    <div class="sm-email-shadow-container" id="sm-email-shadow-host">
        <template class="sm-email-raw-template"><?php echo $message_body; ?></template>
        <noscript>
            <div class="sm-email-noscript-fallback">
                <?php echo strip_tags($message_body, '<p><br><b><strong><i><em><u><a><ul><ol><li><div><span><table><tr><td><th><tbody>'); ?>
            </div>
        </noscript>
    </div>
</div>
