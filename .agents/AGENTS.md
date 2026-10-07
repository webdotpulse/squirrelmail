# AGENTS.md - Agent Guidelines and Repository Workflows

Welcome, AI Agent! This file defines the mandatory protocols, coding standards, and architectural guidelines for operating in this repository.

---

## 🚨 MANDATORY PROTOCOLS (MUST FOLLOW ON EVERY UPDATE)

On **EVERY** update, change, feature addition, or bug fix:

1. **Update `README.md` Every Time**:
   - You must update [README.md](file:///home/koen/Git/squirrelmail/README.md) to document the latest changes, features, bug fixes, or enhancements.
   - Keep a concise, organized changelog section at the end of `README.md` reflecting the new update.

2. **Increment the SquirrelMail Version Every Time**:
   - You must increment/change the version string in [include/constants.php](file:///home/koen/Git/squirrelmail/include/constants.php#L22):
     ```php
     define('SM_VERSION', 'x.y.z [SVN]');
     ```
   - Update `INSTALLER_VERSION` in [install.php](file:///home/koen/Git/squirrelmail/install.php#L16).
   - Ensure the version number displayed at the top of [README.md](file:///home/koen/Git/squirrelmail/README.md) matches the incremented version.

3. **Validate PHP Syntax on Modified Files**:
   - Always run `php -l <path_to_file>` on every modified or created PHP file before concluding work.

---

## 🏛️ Codebase & Architecture Overview

This project is a modernized, enhanced edition of **SquirrelMail**, engineered for modern PHP (7.0 through 8.2+) with a responsive Single-Page Application (SPA) frontend.

### 1. Frontend & Client-Side Routing
- **SPA Router**: Managed by `assets/js/app.js`. Intercepts standard navigation clicks and form submissions to dynamically fetch and render content into `#sm-workspace`.
- **CSS Architecture**: Defined in `assets/css/app.css` using CSS custom properties (`--sm-*`).
- **Theme Support**: Built-in support for both Light and Dark modes (`data-theme="light"` / `data-theme="dark"`).
- **Icons**: Uses modern SVG icons (`assets/images/icons/` and inline SVGs). Do not revert to legacy bitmap GIF/PNG icons.
- **Plugin URLs vs Core URLs**:
  - SquirrelMail core scripts live in `/src/` (`webmail.php`, `compose.php`, `options.php`, etc.).
  - Plugin scripts live in `/plugins/<plugin_name>/`.
  - **CRITICAL**: When outputting links or form actions in plugins, ALWAYS use `sqm_baseuri() . 'plugins/...'` or root-relative paths. Never output bare relative URLs like `href="my_plugin.php"` in plugin templates, as the SPA router may treat bare filenames as belonging to `/src/`.

### 2. Backend & PHP 8.2+ Compatibility
- **No Deprecated Constructs**: Avoid `each()`, `create_function()`, unparenthesized ternary operations, dynamic property declarations without `#[\AllowDynamicProperties]`, or passing null to non-nullable internal string functions.
- **Security & Sanitization**: Always use `htmlspecialchars()` when displaying user inputs or query parameters. Always check and preserve CSRF tokens (`smtoken`) in forms.
- **Filesystem & Session Access**: Respect `SM_PATH`, `SM_BASE_URI`, and `$data_dir` settings. Do not hardcode absolute server paths.

### 3. Key Plugins
- `plugins/multi_account`: Multi-account management and Unified Inbox aggregation.
- `plugins/templates`: Canned email responses and attachments manager with dynamic variable replacement.
- `plugins/message_labels`: Gmail-style color-coded message tags and sidebar badges.
- `plugins/html_mail`: Rich-text WYSIWYG editing and HTML signatures per identity.
- `plugins/calendar`: Full interactive calendar with month/agenda views and event scheduling.
- `plugins/ai_agent`: AI assistance integration.

---

## 📋 Pre-Flight Checklist Before Responding
- [ ] Has [include/constants.php](file:///home/koen/Git/squirrelmail/include/constants.php) been updated with the new version?
- [ ] Has [install.php](file:///home/koen/Git/squirrelmail/install.php) been updated?
- [ ] Has [README.md](file:///home/koen/Git/squirrelmail/README.md) been updated with the latest changelog and matching version?
- [ ] Has `php -l` passed cleanly on all touched PHP files?
- [ ] Are all UI elements responsive and functioning on both desktop and mobile views?
