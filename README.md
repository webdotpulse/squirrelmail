# SquirrelMail 1.5.13

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

### Version 1.5.13
- **Direct Login Redirection on Logout (Skip Signout Page)**:
  - Updated `src/signout.php` to immediately redirect to `login.php` (or custom `$signout_page` if defined) upon destroying the session and purging attachments.
  - Eliminated the intermediate "You have been successfully signed out. Click here to log back in." confirmation screen (`signout.tpl`), providing an instantaneous, seamless return to the login interface.
  - Updated configuration documentation in `config/config.php` and `config/config_default.php`.
- **Version Bump**: Incremented version from `1.5.12` to `1.5.13`.

### Version 1.5.12
- **Dynamic Email High Priority Management (Add & Remove)**:
  - Enabled full interactive adding and removing of High priority on emails in the INBOX, all subfolders, and search listings.
  - **One-Click Row Toggle**: Made the Priority column (`SQM_COL_PRIO` in `templates/default/message_list.tpl`) interactive with one-click toggling. High priority messages display 🔴 (with tooltip "High Priority (Click to remove)"), while normal priority messages show an intuitive, subtle hoverable indicator to mark as High Priority, updated instantaneously via AJAX with zero page flicker.
  - **Mailbox Batch Priority Toolbar**: Integrated a dedicated "🔴 Priority ▼" batch dropdown into the mailbox action controls toolbar (`templates/default/message_list_controls.tpl`), enabling users to mark multiple selected emails as High Priority or remove High Priority across any mailbox or subfolder with one action.
  - **Reading View Priority Toggle**: Enhanced the message header Priority indicator (`src/read_body.php` & `templates/default/read_message_priority.tpl`) with an inline action button (`[Remove High Priority]` / `[Mark as High Priority]`) to dynamically change priority while viewing an email.
  - **Persistent Priority Engine & IMAP Keyword Sync**: Implemented `functions/priority.php` with per-user persistent JSON override storage and automatic synchronization with IMAP server keywords (`$HighPriority`), complemented by an asynchronous API endpoint (`src/priority_ajax.php`).
- **Version Bump**: Incremented version from `1.5.11` to `1.5.12`.

### Version 1.5.11
- **Missing `images/blank.png` 404 Resolution**:
  - Restored `images/blank.png` 1x1 transparent PNG in the root `images/` directory, resolving HTTP 404 errors when viewing emails with empty/missing image sources, broken CID attachments, or security-sanitized image blocks.
  - Updated `functions/mime.php` (`sq_fixuri()` and `sq_cid2http()`) to dynamically resolve `blank.png` and `spacer.png` using `sqm_baseuri()`, ensuring proper URL resolution across deep plugin endpoints and the Single-Page Application (SPA) frontend.
- **Version Bump**: Incremented version from `1.5.10` to `1.5.11`.

### Version 1.5.10
- **AI Agent "Test IMAP Connection" Bad Request: Missing '"' Fix**:
  - Fixed an authentication failure where `ai_agent_test_imap_login()` passed a raw plaintext password to `sqimap_login()`, causing `OneTimePadDecrypt()` to treat it as OTP cookie ciphertext, corrupting the password with null bytes and invalid characters that triggered `BAD: Missing '"'` on the IMAP server.
  - Re-implemented `ai_agent_test_imap_login()` in `plugins/ai_agent/credentials.php` to perform a direct, non-terminating socket IMAP test with RFC 3501 escaped credentials, SSL/TLS negotiation, and clean status messages displayed in the UI without crashing the options page.
  - Updated `sqimap_login()` in `functions/imap_general.php` with an `$is_plaintext_password` parameter, allowing background processes (like `cron.php`) to authenticate with explicit plaintext passwords without unintended OTP decryption.
  - Updated `plugins/ai_agent/cron.php` to pass `$hide = 3` and `$is_plaintext_password = true` to `sqimap_login()`, preventing script aborts and logging detailed error strings on failed logins.
- **Version Bump**: Incremented version from `1.5.9` to `1.5.10`.

### Version 1.5.9
- **Spam Buttons Move & AI Training 500 Internal Server Error Fix**:
  - Fixed fatal `Error: Call to undefined function sqimap_login()` and `decodeHeader()` in `plugins/spam_buttons/action.php` by properly requiring `functions/imap_general.php` and `functions/mime.php`.
  - Added robust `try-catch (\Throwable $e)` exception handling and output buffer clearing (`ob_clean()`) in `plugins/spam_buttons/action.php` to prevent unhandled 500 errors and ensure clean, valid JSON responses.
  - Enhanced IMAP login handling with `$hide = 3` to return structured JSON error messages instead of terminating execution with HTML error boxes on AJAX requests.
  - Improved mailbox target resolution with case-insensitive junk/spam folder matching and fallback to the user-configured trash folder.
  - Integrated `SquirrelMailGeminiClient` with deduplicated per-request trigger prevention in `plugins/spam_buttons/spam_learn.php` to keep batch reporting fast and reliable.
  - Added robust fetch response status checks (`r.ok`), error text parsing, and toast notification fallback in `plugins/spam_buttons/setup.php`.
  - Added CSRF security token validation to the reset training action in `plugins/spam_buttons/options.php`.
- **Version Bump**: Incremented version from `1.5.8` to `1.5.9`.

### Version 1.5.8
- **AI Agent Background Cron IMAP Credentials Resolution**:
  - Fixed account skipping in `plugins/ai_agent/cron.php` (`[SKIP] No IMAP password specified`) by implementing a multi-tier credential resolver in `plugins/ai_agent/credentials.php`.
  - Added automatic credential capture on webmail login (`login_verified` hook), encrypting user IMAP passwords with AES-128 for seamless background cron processing without manual setup.
  - Added IMAP server and password configuration fields to the AI Agent Options page (`plugins/ai_agent/options.php`) with an interactive "Test IMAP Connection" validator and automatic session pre-fill.
  - Fixed a configuration bug in `plugins/ai_agent/config.php` where `$cron_accounts` was inadvertently overwritten after local configuration loading.
  - Added CLI credential arguments to `cron.php` (`--password=<pass>` and `--set-pass=<pass> --user=<email>`), allowing administrators to test and store credentials directly from the command line.
- **Version Bump**: Incremented version from `1.5.7` to `1.5.8`.

### Version 1.5.7
- **Priority Selector Dropdown in Compose**:
  - Restored the Priority selector dropdown in the Compose options toolbar (`templates/default/compose_buttons.tpl`) next to Read Receipt and Delivery Receipt, eliminating the previous conditional suppression.
  - Added a dedicated, clean Priority field row in the Compose header (`templates/default/compose_header.tpl`) beneath Subject with visual indicators (🔴 High, ⚪ Normal, 🔵 Low) bidirectionally synced with the toolbar.
  - Restored full-width layout for the Subject input field.
- **Spam & Not Spam Buttons Execution**:
  - Fixed the "🚫 Spam" and "✅ Not Spam" batch buttons in the mailbox controls toolbar (`plugins/spam_buttons/setup.php`). Replaced hardcoded form selector checks with direct checkbox queries across standard and dynamic form names (`FormMsgs*`).
  - Added missing `$imap_stream_options` parameter to `sqimap_login()` in `plugins/spam_buttons/action.php` to prevent SSL/TLS connection failures.
  - Implemented batch IMAP move (`sqimap_msgs_list_move`) and source mailbox expunge (`sqimap_mailbox_expunge`) to immediately remove reported emails from the source mailbox and train local heuristics/Gemini AI model.
  - Integrated SPA router reload, toast notifications, and unread folder count badge refresh.
- **Version Bump**: Incremented version from `1.5.6` to `1.5.7`.

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
