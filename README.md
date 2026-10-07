# SquirrelMail 1.5.6

A modernized, responsive edition of SquirrelMail engineered for PHP 7.0 through 8.2+ with a Single-Page Application (SPA) interface, modern UI styling, and extensive feature enhancements.

---

## 🚀 Key Features & Enhancements

- **Modern Single-Page Application (SPA) Interface**:
  - Dynamic client-side routing via `assets/js/app.js` with instant workspace navigation, preserving session state and CSRF tokens.
  - Curated modern design system in `assets/css/app.css` with CSS custom properties (`--sm-*`).
  - Native Light and Dark mode toggle with persistent preferences.
  - Crisp, modern SVG iconography throughout the application.
- **Unified Inbox & Multi-Account Support** (`plugins/multi_account`):
  - Aggregate emails across primary and secondary accounts into a single unified stream.
  - Real-time unread badges and single-line status display in the sidebar.
- **Email Templates & Attachments** (`plugins/templates`):
  - Pre-defined and custom canned responses with dynamic variable tags (`{name}`, `{date}`, `{my_name}`, etc.).
  - Automatic file attachment capability (brochures, PDFs, intake forms) directly from templates into Compose.
- **Rich HTML Signatures** (`plugins/html_mail`):
  - Full HTML & plaintext signature support configurable per identity in Personal Options.
- **Message Labels & Categorization** (`plugins/message_labels`):
  - Gmail-style color-coded tags, customizable labels, and sidebar badge integration.
- **Compose Improvements**:
  - Live contact autocomplete suggestions for `To:`, `Cc:`, and `Bcc:` fields.
  - Modal-based template insertion and rich-text synchronization.
- **Core PHP 8.2+ & Security Upgrades**:
  - Legacy constructors migrated to `__construct`.
  - Deprecated `each()`, `create_function()`, and `mt_rand()` replaced with modern language constructs and cryptographically secure `random_int()`.
  - Cryptographically secure UUIDv5 Message-ID generation.
  - SCRAM authentication support for SMTP and IMAP logins.
  - Modern Content-Security-Policy (CSP) headers.

---

## 🤖 AI Agent Workflow & Protocol

This repository includes an [AGENTS.md](file:///home/koen/Git/squirrelmail/AGENTS.md) rules file. AI coding assistants operating on this codebase must follow mandatory protocols:
1. **Update `README.md` on every update**: Every commit or pull request must document new features or bug fixes.
2. **Increment `SM_VERSION` on every update**: Update `SM_VERSION` in `include/constants.php` and `install.php`.
3. **Syntax Validation**: Run `php -l` on all modified files to ensure strict PHP compatibility.

---

## 📝 Changelog

### Version 1.5.6
- **Folder Management Quick Access**: Integrated convenient shortcuts to the Folder Manager (`src/folders.php`):
  - Added a `+` action button directly in the sidebar **Folders** section header (mirroring the `LABELS +` design).
  - Added a dedicated Folder icon button in the top navigation header (`.sm-header-actions`).
  - Added modern card styling in `assets/css/app.css` for `.dialogbox` and folder manipulation tables (create folder/subfolder, rename, delete, and subscribe).
- **Version Bump**: Incremented version from `1.5.5` to `1.5.6`.

### Version 1.5.5
- **Mailbox Labels Dropdown Positioning & Styling**: Fixed layout issues with the "🏷️ Labels ▼" dropdown where the menu was rendered as an in-flow static block (causing flex wrapping, height expansion, and toolbar distortion). Integrated absolute popup overlay styling (`.ml-dropdown-wrapper`, `.ml-dropdown-menu`, `.ml-dropdown-item`) into `assets/css/app.css` with dark mode support, color indicator dots, outside-click & Escape dismissal, and added a "Remove all labels" action.
- **Version Bump**: Incremented version from `1.5.4` to `1.5.5`.

### Version 1.5.4
- **Sidebar Compose Button**: Fixed oversized styling and eliminated nested inner button rendering (`.sm-btn-compose-main span`), delivering a compact, sleek primary action button.
- **Mailbox Toolbar Labels Dropdown**: Integrated a "🏷️ Labels ▼" dropdown button into the mailbox toolbar (`templates/default/message_list_controls.tpl` & `plugins/message_labels`), enabling one-click labeling, tag removal, and direct navigation to label management for selected emails.
- **Compose Message Priority**: Added a clean priority selector directly on the Compose Subject row (🔴 High, ⚪ Normal, 🔵 Low) with full RFC 822 priority header generation (`X-Priority`, `Priority`, `Importance`).
- **INBOX Subfolder Unread Badges**: Enabled unread message count badges for all subfolders under `INBOX` in the sidebar folder tree (`functions/imap_mailbox.php` and `templates/default/left_main.tpl`) and removed archaic parentheses around badges.
- **Version Bump**: Incremented version from `1.5.3` to `1.5.4`.

### Version 1.5.3
- **Sidebar**: Fixed Unified Inbox header layout to ensure the title and unread badge always stay strictly on one line without wrapping.
- **Templates**: Fixed "File not found." error on template Edit and Delete actions by enforcing absolute root-relative URLs and updating SPA router relative resolution.
- **SPA Router**: Enhanced `app.js` to properly resolve plugin-relative paths and smoothly scroll to hash anchors (such as `#template-form`).
- **AI Agent Rules**: Added `AGENTS.md` and `.agents/AGENTS.md` specifying repository conventions, versioning rules, and update protocols.
- **Version Bump**: Incremented version from `1.5.2` to `1.5.3`.

### Version 1.5.2
- Initial PHP 7.0–8.2 compatibility migration.
- Modernized constructor and loop implementations.
- SCRAM authentication mechanism.
- CSP frame-ancestors support.
