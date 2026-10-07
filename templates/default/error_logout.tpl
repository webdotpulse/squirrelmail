<?php
/**
 * error_logout.tpl
 *
 * Modern error/logout screen for SquirrelMail
 *
 * The following variables are available to this template:
 *      $logo_str         - String containing HTML for SQM logo
 *      $sm_attribute_str - String containing SQM attributes to be displayed
 *      $errorMessage     - String containing error to be displayed
 *      $login_link       - Array containing link data for login page
 * 
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */
 
/* Extract template vars */
extract ($t);
?>
<body class="sm-login-page">
<div class="sm-login-wrapper">
  <!-- Subtle ambient decorative glow shapes -->
  <div class="sm-login-glow-1"></div>
  <div class="sm-login-glow-2"></div>

  <div class="sm-login-container">
    <div class="sm-login-card sm-error-logout-card" id="sqm_errorLogout">

      <!-- Header: Logo & Titles -->
      <div class="sm-login-header">
        <div class="sm-login-logo-wrap">
          <?php if (!empty($logo_str)): ?>
            <?php echo $logo_str; ?>
          <?php else: ?>
            <div class="sm-brand-icon sm-login-fallback-icon">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Error / Notice Box -->
      <div class="sm-error-box">
        <div class="sm-error-box-header">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          <span><?php echo _("Notice"); ?></span>
        </div>
        <div class="sm-error-box-message">
          <?php echo nl2br(htmlspecialchars($errorMessage)); ?>
        </div>
        <div style="margin-top: 16px;">
          <a class="sm-btn-login" style="text-decoration:none; display:inline-flex;" href="<?php echo htmlspecialchars($login_link['URI']); ?>"<?php echo (!empty($login_link['FRAME']) ? ' target="'.htmlspecialchars($login_link['FRAME']).'"' : ''); ?>>
            <span><?php echo _("Return to Login"); ?></span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
          </a>
        </div>
      </div>

    </div>
  </div>
</div>
