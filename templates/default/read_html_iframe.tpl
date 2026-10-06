<?php
/**
 * read_html_iframe.tpl
 *
 * Deprecated iframe template - refactored to use Shadow DOM sandboxing with zero iframes.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
<div class="sm-email-shadow-container" id="sm-email-shadow-host">
    <template class="sm-email-raw-template"><?php echo !empty($html_body) ? $html_body : ''; ?></template>
    <noscript>
        <div class="sm-email-noscript-fallback">
            <?php echo strip_tags(!empty($html_body) ? $html_body : '', '<p><br><b><strong><i><em><u><a><ul><ol><li><div>'); ?>
        </div>
    </noscript>
</div>