<?php
/**
 * login.tpl
 *
 * Modern, responsive authentication screen for SquirrelMail
 *
 * The following variables are available to this template:
 *      $logo_str           - string containing HTML to display the org logo
 *      $logo_path          - path to the org logo, in case you want to do
 *                            something else with it.
 *      $sm_attribute_str   - string containing SQM attributes (empty in white-label mode)
 *      $org_name_str       - translated string containing organization's name
 *      $login_field_value  - default value for the user name field
 *      $login_extra        - Some extra form fields needed by SquirrelMail
 *                            for the login.
 *      $plugin_output      - An array of extra output that may be added by plugin(s).
 * 
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

/* retrieve the template vars */
extract($t);

?>
<body class="sm-login-page" onload="squirrelmail_loginpage_onload()">
<script type="text/javascript">
(function() {
  try {
    var savedTheme = localStorage.getItem('sm_theme');
    if (!savedTheme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      savedTheme = 'dark';
    }
    if (savedTheme) {
      document.documentElement.setAttribute('data-theme', savedTheme);
    }
  } catch(e) {}
})();
</script>

<div class="sm-login-wrapper">
  <!-- Subtle ambient decorative glow shapes -->
  <div class="sm-login-glow-1"></div>
  <div class="sm-login-glow-2"></div>

  <!-- Top bar with Theme Switcher -->
  <div class="sm-login-topbar">
    <button type="button" class="sm-login-theme-btn" id="sm-login-theme-toggle" aria-label="<?php echo _("Toggle theme"); ?>">
      <svg class="sm-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
      <svg class="sm-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      <span class="sm-theme-text"><?php echo _("Theme"); ?></span>
    </button>
  </div>

  <div class="sm-login-container">
    <div class="sm-login-card" id="sqm_login">

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
        <h1 class="sm-login-title"><?php echo htmlspecialchars($org_name_str); ?></h1>
        <p class="sm-login-subtitle"><?php echo _("Sign in to your webmail account to continue"); ?></p>
      </div>

      <!-- Main Form -->
      <form name="login_form" id="login_form" class="sm-login-form" action="redirect.php" method="post" onsubmit="document.login_form.js_autodetect_results.value=1">
        <?php if (!empty($plugin_output['login_top'])) echo '<div class="sm-login-plugin-top">' . $plugin_output['login_top'] . '</div>'; ?>

        <!-- Username Input Group -->
        <div class="sm-form-group">
          <label for="login_username" class="sm-form-label">
            <?php echo _("Username or Email"); ?>
          </label>
          <div class="sm-input-wrap">
            <span class="sm-input-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </span>
            <input type="text" 
                   name="<?php global $username_form_name; echo $username_form_name; ?>" 
                   value="<?php echo $login_field_value; ?>" 
                   id="login_username" 
                   class="sm-input" 
                   placeholder="<?php echo _("username or email"); ?>" 
                   autocomplete="username" 
                   autocapitalize="none" 
                   autocorrect="off" 
                   spellcheck="false" 
                   required 
                   onfocus="alreadyFocused=true;" 
                   <?php global $username_form_extra; echo $username_form_extra; ?> />
          </div>
        </div>

        <!-- Password Input Group -->
        <div class="sm-form-group">
          <label for="secretkey" class="sm-form-label">
            <?php echo _("Password"); ?>
          </label>
          <div class="sm-input-wrap">
            <span class="sm-input-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </span>
            <input type="password" 
                   name="<?php global $password_form_name; echo $password_form_name; ?>" 
                   value="" 
                   id="secretkey" 
                   class="sm-input sm-input-pw" 
                   placeholder="<?php echo _("••••••••••••"); ?>" 
                   autocomplete="current-password" 
                   required 
                   onfocus="alreadyFocused=true;" 
                   <?php global $password_form_extra; echo $password_form_extra; ?> />
            <button type="button" class="sm-pw-toggle" id="sm-pw-toggle" aria-label="<?php echo _("Toggle password visibility"); ?>" tabindex="-1">
              <svg class="sm-pw-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              <svg class="sm-pw-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
            </button>
          </div>
        </div>

        <?php echo $login_extra; ?>
        <?php if (!empty($plugin_output['login_form'])) echo '<div class="sm-login-plugin-form">' . $plugin_output['login_form'] . '</div>'; ?>

        <!-- Submit Button -->
        <button type="submit" id="sm-login-btn" class="sm-btn-login">
          <span><?php echo _("Sign In"); ?></span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
      </form>

      <!-- Trust & Security Badge -->
      <div class="sm-login-footer">
        <span class="sm-security-badge">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
          <?php echo _("TLS / SSL Encrypted Session"); ?>
        </span>
      </div>

    </div>
  </div>
</div>

<script type="text/javascript">
(function() {
  // Password Visibility Toggle
  var pwToggle = document.getElementById('sm-pw-toggle');
  var pwInput = document.getElementById('secretkey');
  if (pwToggle && pwInput) {
    pwToggle.addEventListener('click', function(e) {
      e.preventDefault();
      var isPw = pwInput.type === 'password';
      pwInput.type = isPw ? 'text' : 'password';
      var eye = pwToggle.querySelector('.sm-pw-eye');
      var eyeOff = pwToggle.querySelector('.sm-pw-eye-off');
      if (eye && eyeOff) {
        eye.style.display = isPw ? 'none' : 'block';
        eyeOff.style.display = isPw ? 'block' : 'none';
      }
      pwInput.focus();
    });
  }

  // Theme Toggle Button
  var themeToggle = document.getElementById('sm-login-theme-toggle');
  function updateThemeUI(t) {
    if (!themeToggle) return;
    var moon = themeToggle.querySelector('.sm-icon-moon');
    var sun = themeToggle.querySelector('.sm-icon-sun');
    if (moon && sun) {
      moon.style.display = t === 'dark' ? 'none' : 'block';
      sun.style.display = t === 'dark' ? 'block' : 'none';
    }
  }

  var curTheme = document.documentElement.getAttribute('data-theme') || 'light';
  updateThemeUI(curTheme);

  if (themeToggle) {
    themeToggle.addEventListener('click', function(e) {
      e.preventDefault();
      var activeTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', activeTheme);
      updateThemeUI(activeTheme);
      try {
        localStorage.setItem('sm_theme', activeTheme);
        document.cookie = 'sm_theme=' + encodeURIComponent(activeTheme) + '; path=/; max-age=31536000; SameSite=Lax';
      } catch(ex) {}
    });
  }
})();
</script>

<?php if (!empty($plugin_output['login_bottom'])) echo $plugin_output['login_bottom']; ?>
</body></html>
