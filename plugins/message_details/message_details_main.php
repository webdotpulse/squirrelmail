<?php

/**
 * Message Details plugin - main page
 *
 * Plugin to view the RFC822 raw message output and the bodystructure of a message
 *
 * @author Marc Groot Koerkamp
 * @copyright 2002 Marc Groot Koerkamp, The Netherlands
 * @copyright 2002-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package plugins
 * @subpackage message_details
 */

/**
 * Include the SquirrelMail initialization file.
 */
require('../../include/init.php');
require(SM_PATH . 'functions/forms.php');

displayHtmlHeader( _("Message Details"), '', FALSE );

sqgetGlobalVar('mailbox', $mailbox, SQ_GET);
sqgetGlobalVar('passed_id', $passed_id, SQ_GET, NULL, SQ_TYPE_BIGINT);
if (!sqgetGlobalVar('passed_ent_id', $passed_ent_id, SQ_GET))
    $passed_ent_id = 0;

echo '<body style="margin: 0; padding: 16px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #f8fafc; color: #1e293b;">';
echo '<div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
echo '<div style="font-weight: 600; font-size: 16px;">' . _("Message Details") . '</div>';
echo '<div style="display: flex; align-items: center; gap: 8px;">';
echo addForm(SM_PATH . 'src/download.php', 'GET', '', '', '', 'style="display:inline; margin:0;"');
echo addHidden('mailbox', $mailbox);
echo addHidden('passed_id', $passed_id);
echo addHidden('ent_id', $passed_ent_id);
echo addHidden('absolute_dl', 'true');
echo '<input type="button" value="' . _("Print") . '" onclick="window.print()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; cursor: pointer; font-size: 13px; font-weight: 500;" /> ';
echo '<input type="button" value="' . _("Close Window") . '" onclick="window.close()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; cursor: pointer; font-size: 13px; font-weight: 500;" /> ';
echo '<input type="submit" value="' . _("Save Message") . '" style="padding: 6px 12px; border-radius: 6px; border: none; background: #2563eb; color: #ffffff; cursor: pointer; font-size: 13px; font-weight: 500;" />';
echo '</form>';
echo '</div></div>';

echo '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
$_GET['get_message_details'] = 'yes';
$md_action = 'yes';
require_once(SM_PATH . 'plugins/message_details/message_details_bottom.php');
echo '</div></body></html>';
