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

$isHigh = ($message_priority == 1 || $message_priority == 2);
$mb = $mailbox ?? ($_GET['mailbox'] ?? 'INBOX');
$uid = intval($passed_id ?? ($_GET['passed_id'] ?? 0));
?>
<span id="sm-read-priority-badge" style="display: inline-flex; align-items: center; gap: 8px;">
<?php if ($isHigh): ?>
  <span style="display: inline-flex; align-items: center; gap: 4px; font-weight: 600; color: var(--sm-danger, #d93025);">
    <span>🔴</span>
    <span><?php echo _("High"); ?></span>
  </span>
  <button type="button" class="sm-btn sm-btn-secondary sm-btn-xs" style="font-size: 11px; padding: 2px 8px; cursor: pointer; border-radius: 4px;" onclick="if(typeof window.sqmTogglePriority==='function') window.sqmTogglePriority('<?php echo htmlspecialchars($mb, ENT_QUOTES); ?>', <?php echo $uid; ?>, this);">
    <?php echo _("Remove High Priority"); ?>
  </button>
<?php else: ?>
  <span style="display: inline-flex; align-items: center; gap: 4px; color: var(--sm-text-muted);">
    <span>⚪</span>
    <span><?php echo priorityStr($message_priority); ?></span>
  </span>
  <button type="button" class="sm-btn sm-btn-secondary sm-btn-xs" style="font-size: 11px; padding: 2px 8px; cursor: pointer; border-radius: 4px;" onclick="if(typeof window.sqmTogglePriority==='function') window.sqmTogglePriority('<?php echo htmlspecialchars($mb, ENT_QUOTES); ?>', <?php echo $uid; ?>, this);">
    <?php echo _("Mark as High Priority"); ?>
  </button>
<?php endif; ?>
</span>