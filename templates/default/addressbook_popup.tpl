<?php
/**
 * addressbook_popup.tpl
 *
 * Template for address book search popup
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
<div class="sm-addrbook-popup-container" style="padding: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div class="sm-addrbook-popup-content">
        <?php
        if (file_exists(SM_PATH . 'src/addrbook_search.php')) {
            $show = 'form';
            include(SM_PATH . 'src/addrbook_search.php');
        }
        ?>
    </div>
</div>
