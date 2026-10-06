<?php

/**
 * footer.tpl
 *
 * Template for viewing the footer
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

/* retrieve the template vars */
extract($t);

displayErrors();
if ((!function_exists('sqm_is_ajax') || !sqm_is_ajax()) && empty($GLOBALS['in_webmail_shell'])) {
?>
<!-- end of generated html -->
</body>
</html>
<?php
}

