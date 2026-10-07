<?php
/**
 * webmail.tpl
 *
 * Master Unified Single-Page Application Shell Template
 * Completely eliminates HTML <frameset>, <frame>, and layout <iframe> tags.
 * Semantic HTML5 structure with responsive desktop & mobile support.
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
global $username, $org_title, $base_uri;

$user_initial = !empty($username) ? strtoupper(substr($username, 0, 1)) : 'U';
$display_user = !empty($username) ? sm_encode_html_special_chars($username) : 'User';
$cur_mailbox = !empty($mailbox) ? sm_encode_html_special_chars($mailbox) : 'INBOX';
$src_base = sqm_baseuri() . 'src/';
?>
<body class="sm-app-body">
<div id="sm-loading-bar"></div>
<div id="sm-app">
  <!-- Unified Semantic Application Header -->
  <header id="sm-header">
    <div class="sm-header-left">
      <button type="button" class="sm-menu-toggle" id="sm-menu-toggle" aria-label="Toggle Folder Navigation Menu" title="<?php echo _("Toggle Menu"); ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </button>
      <a href="<?php echo $src_base; ?>webmail.php" class="sm-brand">
        <div class="sm-brand-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        </div>
        <span class="sm-brand-name"><?php echo !empty($org_title) ? sm_encode_html_special_chars($org_title) : 'SquirrelMail'; ?></span>
      </a>
    </div>

    <!-- Central Search Bar -->
    <div class="sm-header-center">
      <form class="sm-search-bar" action="<?php echo $src_base; ?>search.php" method="get">
        <svg class="sm-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" name="what" class="sm-search-input" placeholder="<?php echo _("Search messages..."); ?>" autocomplete="off" />
        <select name="mailbox" class="sm-search-mailbox-select" aria-label="<?php echo _("Mailbox"); ?>">
          <option value="<?php echo $cur_mailbox; ?>"><?php echo _("Current Folder"); ?></option>
          <option value="INBOX">INBOX</option>
          <option value="All Folders"><?php echo _("All Folders"); ?></option>
        </select>
        <input type="hidden" name="where" value="TEXT" />
        <input type="hidden" name="submit" value="<?php echo _("Search"); ?>" />
        <input type="hidden" name="smtoken" value="<?php echo sm_generate_security_token(); ?>" />
      </form>
    </div>

    <!-- Header Actions, Theme Toggle & User Identity -->
    <div class="sm-header-right">
      <div class="sm-header-actions">
        <a href="<?php echo $src_base; ?>compose.php" class="sm-icon-btn" title="<?php echo _("Compose"); ?>" aria-label="<?php echo _("Compose"); ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
        </a>
        <a href="<?php echo $src_base; ?>webmail.php<?php echo !empty($mailbox) ? '?mailbox=' . urlencode($mailbox) : ''; ?>" class="sm-icon-btn" title="<?php echo _("Refresh"); ?>" aria-label="<?php echo _("Refresh"); ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
        </a>
        <a href="<?php echo $src_base; ?>addressbook.php" class="sm-icon-btn" title="<?php echo _("Addresses"); ?>" aria-label="<?php echo _("Addresses"); ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </a>
        <a href="<?php echo sqm_baseuri(); ?>plugins/calendar/calendar.php" class="sm-icon-btn" title="<?php echo _("Calendar"); ?>" aria-label="<?php echo _("Calendar"); ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </a>
        <a href="<?php echo $src_base; ?>folders.php" class="sm-icon-btn" title="<?php echo _("Folders"); ?>" aria-label="<?php echo _("Folders"); ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
        </a>
        <a href="<?php echo $src_base; ?>options.php" class="sm-icon-btn" title="<?php echo _("Options"); ?>" aria-label="<?php echo _("Options"); ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </a>
        <button type="button" class="sm-icon-btn" id="sm-theme-toggle" title="<?php echo _("Toggle Theme"); ?>" aria-label="<?php echo _("Toggle Theme"); ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        </button>
      </div>

      <!-- User Identity Badge -->
      <div class="sm-user-badge" title="<?php echo $display_user; ?>">
        <div class="sm-avatar"><?php echo $user_initial; ?></div>
        <span><?php echo $display_user; ?></span>
      </div>

      <!-- Logout Button -->
      <a href="<?php echo $src_base; ?>signout.php" class="sm-icon-btn sm-signout-btn" title="<?php echo _("Sign Out"); ?>" aria-label="<?php echo _("Sign Out"); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
    </div>
  </header>

  <!-- Fluid Layout Container -->
  <div id="sm-layout">
    <!-- Off-canvas Mobile Drawer Backdrop -->
    <div id="sm-sidebar-backdrop"></div>

    <!-- Semantic Sidebar -->
    <aside id="sm-sidebar" aria-label="<?php echo _("Folders Sidebar"); ?>">
      <div class="sm-sidebar-top-action">
        <a href="<?php echo $src_base; ?>compose.php" class="sm-btn-compose-main">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          <span><?php echo _("Compose"); ?></span>
        </a>
      </div>

      <div class="sm-sidebar-folders-container">
        <?php echo !empty($sidebar_content) ? $sidebar_content : ''; ?>
      </div>
    </aside>

    <!-- Semantic Workspace Main Pane -->
    <main id="sm-workspace" aria-label="<?php echo _("Workspace"); ?>">
      <div id="sm-workspace-content">
        <?php echo !empty($workspace_content) ? $workspace_content : ''; ?>
      </div>
    </main>
  </div>
</div>
<?php
global $null;
do_hook('webmail_bottom', $null);
?>
</body>
</html>
