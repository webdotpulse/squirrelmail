<?php
/**
 * read_message_priority.php
 *
 * Template to return the message priority with interactive toggle
 * 
 * The following variables are available in this template:
 *      $message_priority - Priority setting as set in the message.
 *      $mailbox          - Current mailbox name.
 *      $passed_id        - Current message UID.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

include_once(SM_PATH . 'templates/util_read.php');

extract($t);

echo priorityStr($message_priority);
?>