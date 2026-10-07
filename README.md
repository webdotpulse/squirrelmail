# SquirrelMail 1.5.41

A modernized, responsive edition of SquirrelMail engineered for PHP 7.0 through 8.2+ with a Single-Page Application (SPA) interface, modern UI styling, and extensive feature enhancements.

---

## 🚀 Key Features & Enhancements

- **Modern Single-Page Application (SPA) Interface**:
  - Dynamic client-side routing via `assets/js/app.js` with instant workspace navigation, preserving session state and CSRF tokens.
  - Curated modern design system in `assets/css/app.css` with CSS custom properties (`--sm-*`).
  - Native Light and Dark mode toggle with persistent preferences.
  - Crisp, modern SVG iconography throughout the application.
- **Signature Creator & Templates Studio** (`plugins/signature_creator`):
  - Interactive visual designer with 6 ready-made professional HTML signature templates (Modern Clean, Corporate Two-Column, Minimalist Chic, Executive Classic, Tech & Developer, Creative Card).
  - Dynamic live real-time preview updating on keystroke.
  - Custom brand color picker, photo/logo embeds, social badges, and direct saving to SquirrelMail identity signatures (`html_signature` & plain text).
- **Email Templates & Attachments with Full HTML Creation & Preview** (`plugins/templates`):
  - Rich WYSIWYG editor with formatting toolbar (headings, bold, italic, colors, links, lists) and quick-insert chips for dynamic placeholders (`{name}`, `{email}`, `{date}`, etc.).
  - Live real-time HTML preview pane with simulated recipient placeholder data.
  - Interactive HTML preview modal for all saved templates directly in the template manager and compose insertion dialog.
- **Spam Buttons & AI Learning Dashboard** (`plugins/spam_buttons`):
  - "Learn from Junk/Spam folder" scanner to batch-train keywords, reputations, and AI heuristics on existing junk mail.
  - Normalized sender reputation extraction, preventing double-escaped HTML entity corruption (`&#32;`, `&lt;`, `&gt;`) and cleanly managing whitelists/blacklists.
- **Rich HTML Signatures** (`plugins/html_mail`):
  - Full HTML & plaintext signature support configurable per identity in Personal Options.
- **Message Labels & Categorization** (`plugins/message_labels`):
  - Gmail-style color-coded tags, customizable labels, and sidebar badge integration.
- **Automated Message Filters & Background Cron** (`plugins/filters`):
  - Automatic routing of incoming emails to specific folders based on customizable criteria (Sender, Recipient, Subject, Headers, Body text).
  - Continuous background server-side execution via shared cronjob (`plugins/ai_agent/cron.php`) ensuring emails are organized even when logged out.
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

### Version 1.5.41
- **AI Agent Email Summarizer & Body Text Extraction Fix**:
  - Resolved `Uncaught NotSupportedError: Failed to execute 'cloneNode' on 'Node': ShadowRoot nodes are not clonable` in `plugins/ai_agent/ai_agent.php` (`getEmailBodyText()`).
  - Replaced the illegal `shadowHost.shadowRoot.cloneNode(true)` call with safe `innerHTML` serialization into a temporary DOM element with fallback child node cloning.
  - Strips out scoped `<style>`, `<script>`, and `<noscript>` elements before extracting clean body text for Gemini AI summarization, phishing analysis, and translation.
- **Version Bump**: Incremented version from `1.5.40` to `1.5.41`.

### Version 1.5.40
- **Message Labels Pagination & Labeled Mail Filter Fix**:
  - **IMAP Header Fetch Pipeline Filtering**: Resolved a critical issue in `functions/mailbox_display.php` (`fetchMessageHeaders()`) where SquirrelMail sliced the first 25 messages of the unfiltered mailbox before labels were evaluated. Only labeled emails that happened to fall within that initial slice were displayed, leaving other labeled messages invisible.
  - **UID Filtering by Label Before Slicing**: Implemented `ml_get_labeled_uids()` in `plugins/message_labels/labels.php` and integrated it into `fetchMessageHeaders()` to filter `$aMailbox['UIDSET']` by the active label *prior* to calculating the result count and slicing messages. Now all labeled messages in the active mailbox are properly loaded from IMAP.
  - **Accurate Paginator Count & Slices**: Fixed mailbox message count calculations so the paginator accurately reflects the number of labeled messages (e.g. `Viewing Messages: 1 to 5 (5 total)` rather than the total count of all mailbox messages). If labeled messages exceed the page size, messages are correctly partitioned across pages.
  - **Preserved `label_filter` Across Navigation Links**: Updated `functions/template/paginator_util.php` (`get_paginator_link()`, `get_paginator_str()`, `get_compact_paginator_str()`), `functions/mailbox_display.php` (`$baseurl`), and message list controls so that all paginator links (`Previous`, `Next`, numeric pages, `Show All`, `Paginate`), thread view links, column header sort links, and form actions maintain the active `&label_filter=...` parameter.
  - **Isolated Mailbox Session Cache**: Updated `sqm_api_mailbox_select()` and `src/right_main.php` to include `_lbl_<label_filter>` in the session `mailbox_cache` key, preventing cached UID sets of normal and filtered views from colliding or overwriting each other.
  - **Sidebar Label Link Improvements**: Updated `plugins/message_labels/setup.php` (`ml_left_main()`) to dynamically link to the current mailbox if it contains messages with that label (falling back to `INBOX` if only `INBOX` has them), using standard SPA `sqm_baseuri()` paths.
  - **Zero-Message Graceful Display**: Updated `templates/default/message_list.tpl` to display a clean, centered notice when no messages match the selected label, while retaining the label banner and "✕ Clear Filter" button.
- **Version Bump**: Incremented version from `1.5.39` to `1.5.40`.

### Version 1.5.39
- **Removed Obsolete Plugins (`squirrelspell`, `spamcop`, `info`)**:
  - Removed `squirrelspell` plugin: purged obsolete server-side aspell/pspell dictionary routines and compose toolbar buttons in favor of modern client-side browser spell checking.
  - Removed `spamcop` plugin: removed legacy third-party SpamCop reporting code in favor of the modern AI Spam & Reputation Dashboard (`plugins/spam_buttons` & `plugins/ai_agent`).
  - Removed `info` plugin: purged legacy 2002 IMAP CAPABILITY inspection tool.
  - Completely deleted the `plugins/squirrelspell/`, `plugins/spamcop/`, and `plugins/info/` directories.
  - Removed references from `config/config.php`, `config/config_default.php`, and `install.php`.
  - Rebuilt `config/plugin_hooks.php` to unregister all obsolete hooks.
- **Version Bump**: Incremented version from `1.5.38` to `1.5.39`.

### Version 1.5.38
- **Administrator Plugin Activation & Installer Whitelist Management**:
  - Activated the `administrator` plugin ([plugins/administrator](file:///home/koen/Git/squirrelmail/plugins/administrator)) in `config/config.php` and `config/config_default.php`.
  - Added dedicated Web Administration Access configuration in the Web Installer ([install.php](file:///home/koen/Git/squirrelmail/install.php)) Step 4 (Plugins) and Step 5 (Review & Install Summary), allowing administrators to configure authorized login usernames and email addresses during installation.
  - Automatically reads, preloads, and writes the whitelist to [config/admins](file:///home/koen/Git/squirrelmail/config/admins) upon installation.
  - Updated [plugins/administrator/auth.php](file:///home/koen/Git/squirrelmail/plugins/administrator/auth.php) with case-insensitive matching supporting both usernames (`koen`) and full email addresses (`koen@thechargegrid.com`), comments, and fallback to POSIX ownership.
  - Hardened [plugins/administrator/options.php](file:///home/koen/Git/squirrelmail/plugins/administrator/options.php) to strictly save changes on `POST` requests, validated CSRF security tokens (`smtoken`), enforced `sqm_baseuri()` routing, and added immediate status feedback alerts.
  - Rebuilt plugin hooks in `config/plugin_hooks.php` registering `administrator` and `signature_creator`.
- **Version Bump**: Incremented version from `1.5.37` to `1.5.38`.

### Version 1.5.37
- **Removed Obsolete DNSBL (DNS Blackhole List) Spam Filtering Code**:
  - Removed archaic, synchronous DNS lookup routines (`spam_filters`, `filters_spam_check_site`, `filters_SaveCache`, `filters_LoadCache`, and `filters_bulkquery`) from `plugins/filters/filters.php` that queried defunct 20-year-old blacklists and introduced severe webmail and cron latency.
  - Deleted legacy 2002 C binary directory `plugins/filters/bulkquery/`.
  - Streamlined `plugins/filters/filters.php` to focus strictly on user message sorting rules (Sender, Recipient, Subject, Headers, Body) with clean backwards-compatibility shims.
  - Replaced `plugins/filters/spamoptions.php` with an automatic, safe redirection to the modern AI Spam & Reputation Dashboard (`plugins/spam_buttons/options.php`).
  - Removed obsolete DNSBL checks and globals from `plugins/ai_agent/cron.php`, accelerating automated background message filtering.
  - Cleaned DNSBL configurations from `plugins/filters/config_default.php` and updated documentation.
- **Version Bump**: Incremented version from `1.5.36` to `1.5.37`.

### Version 1.5.36
- **Email Templates & Attachments - Full HTML Creation, Editing, & Preview**:
  - Upgraded `plugins/templates/templates_manager.php` with a rich visual WYSIWYG editor and formatting toolbar (headings, bold, italic, underline, strikethrough, text and background colors, lists, alignment, link and image insertion, divider lines, and clean formatting).
  - Added interactive tag insertion chips for `{name}`, `{first_name}`, `{full_name}`, `{email}`, `{subject}`, `{date}`, `{my_name}`, and `{my_email}`.
  - Built a live HTML preview card with a toggle for simulated recipient placeholder data that renders in real-time as the user types.
  - Added an interactive modal preview dialog to preview any template in rich HTML before editing or using it.
  - Enhanced the compose window template selector modal (`plugins/templates/setup.php`) with an inline "👁️ Preview HTML" viewer and automatic switching to rich HTML mode when inserting HTML templates.
- **Signature Creator & Templates Studio (`plugins/signature_creator`)**:
  - Built a brand-new visual HTML email signature designer with 6 ready-made responsive templates: Modern Clean, Corporate Two-Column, Minimalist Chic, Executive Classic, Tech & Developer, and Creative Card.
  - Dynamic live preview updating in real-time with brand accent color picker, profile photo/logo embedding, contact fields, and social media badges.
  - Seamless bidirectional integration with SquirrelMail identities: saves directly to `setHtmlSig` and `setSig`, auto-enables signatures (`use_signature = 1`), and provides "Copy HTML" and "Copy Plain Text" clipboard exports.
  - Registered in SquirrelMail Options (`optpage_register_block`) and added a launcher button in Personal Options (`include/options/personal.php`).
- **Fixed Blacklisted/Whitelisted Senders Entity Mangling in `plugins/spam_buttons`**:
  - Fixed an issue where `decodeHeader()` returned HTML-safe entities (converting spaces into `&#32;` and brackets into `&lt;` and `&gt;`), which were stored raw and then double-encoded by `htmlspecialchars()` into distorted strings like `finance&#32;xplained...`.
  - Implemented `sb_clean_email_address()` to decode entities, strip tags, and reliably extract clean email addresses or domains (`noreply@mailing.financeexplained.be`).
  - Automatically sanitized and normalized existing training data on load.
  - Added quick-remove buttons for whitelisted and blacklisted senders on the dashboard.
- **Learn from Junk/Spam Folder in Spam Buttons & AI Learning Dashboard**:
  - Added `sb_learn_from_junk_folder()` in `plugins/spam_buttons/spam_learn.php` and an action card with a "Learn from Junk/Spam Folder" button in `plugins/spam_buttons/options.php`.
  - Discovers the Junk/Spam IMAP folder, batch-scans existing messages, extracts clean sender addresses into the blacklist, updates spam keyword frequencies, and triggers Gemini AI heuristic learning.
- **Version Bump**: Incremented version from `1.5.35` to `1.5.36`.

### Version 1.5.35
- **Fix Mailbox View Message Priority Persistence & Display**:
  - **Undefined Mailbox Variable in `prepareMessageList()`**: Resolved a bug in `functions/mailbox_display.php` where line 664 called `sqm_get_effective_priority($mailbox, $iUid, $rawPrio)` using an undefined `$mailbox` variable (which was `null`, while the active mailbox name was held in `$sMailbox`). This caused priority lookups to fail and constantly fall back to original header priority (Normal / 3) upon mailbox refresh.
  - **Key Normalization & Fallbacks in Priority Engine**: Updated `sqm_get_priority_key()` and `sqm_get_effective_priority()` in `functions/priority.php` to normalize mailbox names (including case-insensitive `INBOX` handling), clean up legacy unprefixed keys, ensure directory creation before writes, and safely fallback to session/global username and data directory.
  - **Explicit `target_priority` in AJAX Toggle**: Updated `assets/js/app.js` and `src/priority_ajax.php` to transmit explicit `target_priority` (1 for High, 3 for Normal) based on current row state, eliminating state desynchronization when toggling messages with original RFC822 priority headers.
  - **Fallback Mailbox Resolution in Mailbox Template**: Enhanced `templates/default/message_list.tpl` to ensure `$curMbx` is always resolved with proper fallbacks before binding one-click toggle events.
- **Version Bump**: Incremented version from `1.5.34` to `1.5.35`.

### Version 1.5.34
- **Fix HTTP 500 Fatal Error on Message Filters Rule Creation / Edit (`options.php?action=add`)**:
  - **TypeError in `sqimap_mailbox_option_array()`**: Resolved a fatal `TypeError: in_array(): Argument #2 ($haystack) must be of type array, string given` triggered on line 774 of `functions/imap_mailbox.php`. In PHP 8+, string-to-integer comparison changed (`'INBOX' != 0` evaluates to true instead of false), causing `$folder_skip` passed as a string (`'INBOX'`) to enter `in_array($box, $folder_skip)`.
  - **Array Normalization**: Updated `sqimap_mailbox_option_array()` in `functions/imap_mailbox.php` to normalize `$folder_skip` into a lowercase string array, perform case-insensitive skips (`in_array(strtolower($box), $folder_skip)`), and safely avoid querying mailboxes if `$imap_stream` is not an active stream resource.
  - **TypeError in `sqimap_mailbox_option_list()`**: Normalized `$show_selected` to an array so scalar/string arguments (`$show_selected = 'Archive'`) never crash `foreach ($show_selected as $val)`.
  - **Safe Delimiter Detection**: Updated `sqimap_get_delimiter()` in `functions/imap_general.php` to verify `$imap_stream` is a valid resource (`is_resource($imap_stream)`) before issuing IMAP namespace commands or socket writes, returning a safe `'/'` fallback instead of throwing fatal `TypeError` on `fputs()`.
  - **Non-Fatal IMAP Login & Resource Lifecycle**: Hardened `plugins/filters/options.php` and `plugins/filters/spamoptions.php` to call `sqimap_login()` with `$hide = 2`, keep `$imapConnection` open during folder list generation, pass arrays for `$selectedBox` and `$folder_skip`, and gracefully close the IMAP stream after rendering.
- **Version Bump**: Incremented version from `1.5.33` to `1.5.34`.

### Version 1.5.33
- **Fix "Call to undefined function getTimeStamp()" on Calendar Appointment Import**:
  - **Missing Date Helper Dependency**: Fixed fatal error on clicking "Add to Calendar" where `sqimap_get_message()` invokes `Rfc822Header->parseHeader()` to parse message headers including the `Date:` field, which calls `getTimeStamp()`. Because `functions/date.php` was not loaded in standalone endpoints, importing appointments failed with `Call to undefined function getTimeStamp()`.
  - **Endpoint Includes**: Added explicit `include_once(SM_PATH . 'functions/date.php')` to `plugins/calendar/ajax.php`, `plugins/calendar/calendar.php`, and `plugins/calendar/calendar_data.php`.
  - **IMAP Module & Header Parser Autoloading**: Included `functions/date.php` in `functions/imap.php` and added resilient on-demand loading guards in `class/mime/Rfc822Header.class.php` and `class/mime/Message.class.php`.
- **Version Bump**: Incremented version from `1.5.32` to `1.5.33`.

### Version 1.5.32
- **Fix HTTP 500 Fatal Error on Webmail Login**:
  - **Duplicate Function Redeclaration Fix**: Resolved a fatal compile error (`Fatal error: Cannot redeclare function filters_optpage_register_block()`) triggered immediately upon login when `src/webmail.php` executed the `webmail_top` hook (`start_filters_hook`), which dynamically included `plugins/filters/filters.php` after `plugins/filters/setup.php` had already loaded on plugin initialization.
  - **Function Existence Guards**: Wrapped `filters_optpage_register_block()` in both `plugins/filters/setup.php` and `plugins/filters/filters.php` with `if (!function_exists('filters_optpage_register_block'))`.
  - **Folder Status Hook Synchronization**: Implemented and guarded `filters_folder_status()` in both `plugins/filters/setup.php` and `plugins/filters/filters.php` to prevent undefined function crashes or duplicate declarations during folder status queries.
  - **Login Hook Registration**: Registered `ai_agent_login_verified` for the `login_verified` hook in `config/plugin_hooks.php` to ensure consistent credentials capture on authentication.
- **Version Bump**: Incremented version from `1.5.31` to `1.5.32`.

### Version 1.5.31
- **iCalendar (.ics) / .calendar MIME Recognition & Parsing Overhaul**:
  - **Extension & Filename Normalization**: Fixed issue where emails with `.ics` meeting invites (e.g. from Microsoft Outlook, Exchange, Google Calendar) were assigned `.calendar` extensions (`untitled-[id].calendar`), causing operating systems and mail clients to fail to associate the file with calendar applications. Updated `class/mime/Message.class.php`, `src/download.php`, and `functions/mime.php` (`buildAttachmentArray`) to always resolve `text/calendar` and `application/ics` parts to `.ics` and default untitled parts to `invite.ics`.
  - **VEVENT Strict Block Isolation (VTIMEZONE Pollution Elimination)**: Fixed critical parsing bug where `calendar_parse_ics()` in `plugins/calendar/calendar_data.php` split blocks incorrectly, causing Outlook/Exchange `VTIMEZONE` definitions (`DTSTART:16011028T030000`) to be captured instead of the event's actual `DTSTART`, setting meeting dates to year **1601**. Implemented strict `BEGIN:VEVENT...END:VEVENT` regex isolation and helper `calendar_parse_dt()` handling UTC conversion, TZID, and all-day events.
  - **Organizer vs Attendee Extraction**: Fixed regex capture in `calendar_parse_ics()` where attendee names (`ATTENDEE;CN="Koen"`) were wrongly captured as the meeting organizer. Enforced strict property-line parsing for `ORGANIZER;CN=...`.
  - **Missing Calendar Plugin Hooks Registration**: Added missing hooks `template_construct_read_message_body.tpl`, `read_body_top`, `attachment text/calendar`, `attachment application/ics`, and `attachment */*` into `config/plugin_hooks.php` and `plugins/calendar/setup.php`, enabling the interactive meeting invitation banner card and inline `📅 Add to Calendar` actions on emails with calendar attachments.
  - **Reading View Fallback & Structured Presentation**: Fixed issue in `src/read_body.php` where users with plain text preference (`show_html_default=0`) received blank email bodies on HTML/calendar-only invites. Added fallback in `findDisplayEntity()` and modernized `functions/mime.php` (`formatBody`) and `src/view_text.php` to render structured meeting appointment cards with one-click "Add to Calendar" and collapsible raw ICS source viewing.
- **Version Bump**: Incremented version from `1.5.30` to `1.5.31`.

### Version 1.5.30
- **Calendar Email Appointment Import HTTP 500 Fix**:
  - **Undefined IMAP & MIME Function Resolution**: Fixed fatal errors (`Call to undefined function sqimap_login()`, `sqimap_get_message()`, `sqimap_mailbox_select()`, `mime_fetch_body()`, `decodeBody()`, and `decodeHeader()`) in `plugins/calendar/ajax.php`, `plugins/calendar/calendar.php`, and `plugins/calendar/calendar_data.php`. Added explicit includes for `functions/imap.php`, `functions/imap_mailbox.php`, and `functions/mime.php`.
  - **Non-Fatal IMAP Error Handling**: Updated `sqimap_login()` and `sqimap_get_message()` calls in `ajax.php` and `calendar.php` to pass `$hide = 2`, preventing SquirrelMail from terminating script execution with HTML error boxes on AJAX calls and instead returning structured error handling.
  - **Robust Exception Handling**: Wrapped appointment extraction and parsing in `ajax.php` and `calendar.php` with `try { ... } catch (\Throwable $e)` to cleanly catch unexpected exceptions, safely release IMAP stream sockets, and deliver structured JSON error responses with `success: false` rather than HTTP 500 crashes.
  - **iCalendar Extraction Resilience**: Enhanced `calendar_collect_ics_entities()` with null-safety and exception guards around `$msg->getFilename()`, and added automatic mailbox selection and regex-delimited `BEGIN:VCALENDAR...END:VCALENDAR` extraction in `calendar_extract_vcalendar_from_message()`.
- **Version Bump**: Incremented version from `1.5.29` to `1.5.30`.

### Version 1.5.29
- **Message Filters Options Visibility & Background Cron Automation**:
  - **Options Page Visibility**: Fixed Message Filters missing from Options (`src/options.php`). Registered `filters_optpage_register_block` hook in `config/plugin_hooks.php`, added `filters` to `config/config_default.php`, enabled explicit global scope binding for `$optpage_blocks` in `src/options.php`, and added `filters_optpage_register_block()` directly into `plugins/filters/setup.php` to ensure the block is immediately available without requiring the full filter processing engine to load.
  - **Installer Integration**: Added the `filters` plugin to the installer's recommended plugin checkbox grid and default plugins array in `install.php`, ensuring installation and configuration updates never omit filter hooks.
  - **Background Filtering via AI Cron**: Integrated user message filtering directly into the server cron automation script (`plugins/ai_agent/cron.php`). When the cron runs every 5 minutes (or on demand), it connects to each user's IMAP mailbox and evaluates their saved message filter rules, automatically moving matched emails from `INBOX` to destination folders even when the user is completely logged out.
  - **Non-Fatal AI Dependency**: Updated `plugins/ai_agent/cron.php` so that absence of a Gemini API key no longer aborts the cron script. The cron gracefully continues processing user Message Filters across all accounts, and added a `--filter-only` CLI option.
  - **Cron Automation Card in Filter Options**: Added an interactive Background Automation status card to `plugins/filters/options.php` displaying the shared crontab command, continuous filtering status badge, and the timestamp and excerpt of the most recent background cron execution.
  - **Plugin Requirement Fix**: Resolved an unhandled fatal error in `cron.php` (`Call to undefined function do_hook()`) by ensuring `functions/plugin.php` and `config/plugin_hooks.php` are properly loaded.
- **Version Bump**: Incremented version from `1.5.28` to `1.5.29`.

### Version 1.5.28
- **Conditional Unified Inbox Display (Multi-Account)**:
  - Configured `multi_account_left_main_do()` in `plugins/multi_account/multi_account.php` to conditionally render the Unified Inbox container widget in the sidebar only when there is more than 1 connected/enabled mail account (Primary account + at least 1 enabled secondary account).
  - When only the primary account is active, the sidebar container and button are suppressed, keeping the folder tree clean and uncluttered.
  - Skipped unneeded IMAP unread count queries when only a single account is active, improving folder list render speed.
  - Applied the same multi-account check to `multi_account_page_header_do()` for the menuline link.
- **Version Bump**: Incremented version from `1.5.27` to `1.5.28`.

### Version 1.5.27
- **Multi-Account Sidebar Unread Badge Single-Line Alignment**:
  - Fixed an issue where the unread message count badge on secondary accounts (e.g. "The Charge Grid") wrapped down to a new line beneath the mailbox name in the sidebar widget.
  - Enforced single-row flex layout (`.sqm-account-link { display: flex !important; flex-direction: row !important; justify-content: space-between !important; flex-wrap: nowrap !important; width: 100% !important; }`) and text truncation (`.sqm-acc-info`, `.sqm-acc-name`) in `plugins/multi_account/multi_account.php` and `assets/css/app.css`.
  - Styled `.sqm-acc-badge` to match the Unified Inbox unread pill (`background: var(--sm-primary); color: #ffffff;`), anchoring it to the far right on the exact same line as the mailbox info.
  - Added per-account element IDs (`sqm-multi-badge-*`) and dynamic count updates to `sqmRefreshMultiCounts()` for real-time badge polling.
- **Version Bump**: Incremented version from `1.5.26` to `1.5.27`.

### Version 1.5.26
- **Message Filter Plugin & Automation Engine**:
  - **Incoming Mail Auto-Filtering**: Enabled and modernized the `filters` plugin (`plugins/filters/`), allowing users to create custom rules to automatically organize incoming email from `INBOX` into destination folders based on criteria (`From`, `To`, `Cc`, `To or Cc`, `Subject`, `Header`, `Message Body`, or `Header and Body`).
  - **Pre-Mailbox Selection Hook**: Added the `right_main_before_select` hook in `src/right_main.php` to execute filtering before `sqm_api_mailbox_select()` runs. This eliminates folder cache corruption, avoids ghost messages, and guarantees that mailbox counts and listings are 100% synchronized and up-to-date upon viewing.
  - **Modern Options & Management Interface**: Re-engineered `plugins/filters/options.php` with a sleek, responsive card-and-table interface matching the SquirrelMail Modern Responsive design system (`--sm-*` variables, dark mode support, and crisp badge chips for rule criteria and destination folders).
  - **Instant Filter Execution**: Added a dedicated "⚡ Run Filters Now" button in Options to execute filter rules against `INBOX` on demand with instant feedback reporting the exact number of messages moved.
  - **SPA-Compatible Routing & CSRF Protection**: Enforced `sqm_baseuri()` on all plugin URLs, links, and forms to eliminate SPA router breakage, and integrated CSRF security token validation (`smtoken`) across rule creation, edits, and deletions.
  - **IMAP Search & Character Set Resilience**: Modernized `filter_search_and_delete()` in `plugins/filters/filters.php` to use `sqimap_run_command_list()` with RFC 3501 compliant charset fallback (UTF-8 -> US-ASCII -> unencoded search) and safe IMAP argument quoting.
  - **Enabled by Default**: Added `filters` to the active `$plugins` array in `config/config.php`.
- **Version Bump**: Incremented version from `1.5.25` to `1.5.26`.

### Version 1.5.25
- **VCALENDAR & iCalendar Appointment Adding to Calendar**:
  - **Toolbar "Add to Calendar" Fix**: Fixed the "Add to Calendar" button in the email reading toolbar (`plugins/calendar/setup.php`), which previously linked to `calendar.php?action=new` without passing email identifiers and without importing the appointment.
  - **iCalendar / VCALENDAR Parser Enhancements**: Implemented complete RFC 5545/2445 iCalendar (`.ics`/VCALENDAR) appointment parsing in `plugins/calendar/calendar_data.php`, including line unfolding, character unescaping, `DTSTART`/`DTEND`/`DURATION`, categories, locations, organizer extraction, and deterministic UID-based deduplication to prevent duplicate events on repeated clicks.
  - **Automatic VCALENDAR Discovery & Extraction**: Added `calendar_extract_vcalendar_from_message()` to extract VCALENDAR content from emails, supporting `text/calendar` and `application/ics` MIME parts, `.ics` attachments, and inline `BEGIN:VCALENDAR` blocks.
  - **Instant Asynchronous & Direct Adding**: Added asynchronous instant-add execution (`sqmAddCalendarAppointment`) via `plugins/calendar/ajax.php?action=import_email` with inline visual status (`✓ Added to Calendar`), toast confirmation notifications, and direct calendar view navigation.
  - **Interactive Calendar Appointment Card**: Rendered an appointment invitation banner at the top of emails with calendar invites (`template_construct_read_message_body.tpl`), displaying the event date badge, time, location, organizer, and one-click "Add to Calendar" button with Light and Dark mode styling.
  - **Attachment Actions Integration**: Hooked calendar MIME types in `attachment text/calendar`, `attachment application/ics`, and generic `.ics` attachments, providing an inline `📅 Add to Calendar` action alongside Download and View in the attachments list.
  - **Calendar Focus & Highlighting**: Added deep integration in `plugins/calendar/calendar.php` to handle `action=import_email`, automatically focus the calendar on the appointment's month/year, display a success banner, and highlight newly added appointments with a glowing pulse animation.
  - **Message Class Null Safety**: Added null-safety check in `class/mime/Message.class.php` (`Message::getFilename()`) when headers are uninitialized.
- **Version Bump**: Incremented version from `1.5.24` to `1.5.25`.

### Version 1.5.24
- **Topbar Calendar Action Button**:
  - Added a dedicated Calendar SVG icon button in the header toolbar (`templates/default/webmail.tpl`) situated between "Addresses" and "Folders", linking directly to `plugins/calendar/calendar.php` and integrating seamlessly with SPA workspace routing.
- **Sidebar Header Clean-Up & Action Bar Streamlining**:
  - Removed the redundant `<div class="sm-sidebar-header-title">` ("Folders") and the duplicate `sm-folder-add-btn` ("Manage Folders" `+` button) from `templates/default/left_main.tpl`.
  - Polished `.sm-sidebar-header-actions` and `.sm-folder-refresh-btn` styling in `assets/css/app.css` to create a centered, full-width "Check Mail" refresh action bar and refresh timestamp above the folder hierarchy.
- **Version Bump**: Incremented version from `1.5.23` to `1.5.24`.

### Version 1.5.23
- **Topbar & Direct URL Mailbox Search CSRF Fix**:
  - **Untrusted Source Error Resolution**: Fixed an issue where searching from the topbar search bar or navigating directly to `src/search.php` resulted in `ERROR: The current page request appears to have originated from an untrusted source` and forcefully logged the user out.
  - **Read-Only vs State-Changing Token Validation**: Updated `src/search.php` so that strict CSRF token validation with fatal session exit is only enforced on state-changing operations that modify persistent preferences (such as saving/forgetting recent searches or deleting saved criteria). Idempotent, read-only search queries now execute cleanly without logging the user out when tokens are absent or refreshed.
  - **Topbar Search Form Enhancements**: Updated `templates/default/webmail.tpl` to include `smtoken` security tokens, normalized the "All Folders" selector to match `src/search.php`, and switched default search scope to `TEXT` (comprehensive search across Subject, From, To, and Body).
  - **Query & Submitter Handling**: Added support for `submit_display` in `src/search.php` and automated search query triggering when `what` or `where` criteria are provided via GET.
- **Version Bump**: Incremented version from `1.5.22` to `1.5.23`.

### Version 1.5.22
- **AI Agent Background Cron Auto-Draft & Labeling Delivery Fix**:
  - **IMAP Auto-Draft Generation**: Fixed fatal script crashes (`Fatal error: Call to undefined method MessageHeader::getAddr_s()` and undefined `encodeHeader()`) during background AI draft generation in `plugins/ai_agent/cron.php`. Added missing requirements (`functions/mime.php`, `class/mime/AddressStructure.class.php`, `class/mime/Rfc822Header.class.php`, and `functions/prefs.php`), and instantiated drafts with properly typed `Rfc822Header` objects and parsed recipient/sender address structures.
  - **Draft Folder Discovery & IMAP Appending**: Integrated user draft folder preference resolution (`draft_folder`), with dynamic fallbacks to `INBOX.Drafts` or `Drafts` and automatic mailbox creation, ensuring smart reply drafts generated by Gemini 3.8 are reliably delivered to the user's IMAP Drafts folder via `Deliver_IMAP`.
  - **Message Labels Persistent Categorization**: Implemented `cron_assign_message_label()` in `plugins/ai_agent/cron.php`, bridging server-side AI categorization (`Work`, `Finance`, `Personal`, `Urgent`, `Newsletter`, etc.) with `plugins/message_labels`. Labels are now saved to disk (`$username.labels.json`) and synchronized across user formats (including full email addresses and short usernames) so colorful chips immediately render on the message subject line in the mailbox.
  - **Immediate Cron State Persistence**: Updated `cron.php` to immediately append and save processed message UIDs to `plugins/ai_agent/data/state.json` on each analyzed message, preventing duplicate Gemini analysis passes and ensuring cron resilience against intermittent network timeouts.
- **Version Bump**: Incremented version from `1.5.21` to `1.5.22`.

### Version 1.5.21
- **Message View Reply, Reply All, and Forward Actions Fix**:
  - Fixed an issue where clicking the Reply, Reply All, or Forward buttons in the email reading view (`read_body.php`) did not navigate to `src/compose.php` and appeared non-functional.
  - Resolved root cause in `assets/js/app.js`: accessing the DOM property `submitter.formAction` without an explicit `formaction` attribute caused browsers to return `document.baseURI` (`read_body.php?passed_id=...`), which overrode the form's `action="src/compose.php"` attribute and caused GET submissions to reload the current message.
  - Replaced with `submitter.getAttribute('formaction')`, tracked `lastClickedSubmitter` to reliably capture clicked submit buttons across all browser versions, and merged `URLSearchParams(formData)` directly onto target URL objects for GET forms.
  - Updated `templates/default/read_menubar_buttons.tpl` to include explicit `formaction` attributes and added `pointer-events: none` on inner SVG icons to prevent target bubbling quirks.
- **Message View AI Summarize Integration Fix**:
  - Fixed an issue where clicking "✨ AI Summarize" in the email reading view failed with a JavaScript error or "Could not extract email body to summarize."
  - Resolved Shadow DOM encapsulation in `plugins/ai_agent/ai_agent.php`: updated `getEmailBodyText()` to inspect `#sm-email-shadow-host`'s open `shadowRoot` (extracting text content stripped of style tags), fallback to `<template class="sm-email-raw-template">`, and fall back to semantic containers (`.sm-read-body-wrapper`, `div.readBody`, `pre`).
  - Added subject (`.field_Subject .fieldValue`), sender (`.field_From .fieldValue`), and full RFC header extraction for improved Gemini prompt context.
  - Resolved relative URL mismatch: changed AJAX endpoint URLs from `SM_PATH` (`../`) to `sqm_baseuri() . 'plugins/ai_agent/ajax.php'`, ensuring proper resolution regardless of SPA routing depth.
  - Added a dedicated, styled **✨ AI Summarize** action button directly in the reading menubar alongside Reply, Reply All, and Forward with `.sm-btn-ai-summarize` styling for both light and dark themes.
  - Replaced browser alert popups with smooth in-context banner notifications (`#ai-read-banner`), including a direct one-click link to configure the Gemini API key in Options if not yet set.
- **Version Bump**: Incremented version from `1.5.20` to `1.5.21`.

### Version 1.5.20
- **AI Agent Banner Close Button Fix in Message View**:
  - Fixed an issue where the standalone `×` close button (`<button class="ai-read-close">`) was always displayed at the top of emails when viewing messages.
  - Set inline `style="display: none;"` directly on `<div id="ai-read-banner">` in `plugins/ai_agent/ai_agent.php` so the container remains strictly hidden by default and only appears when an AI action (Summarize, Scam Check, or Translate) is activated.
  - Integrated full design system styles for `.ai-read-card`, `.ai-read-close`, `.ai-summary-card`, and `.ai-scam-card-*` into `assets/css/app.css` with native dark mode support.
- **Spam & Not Spam Button Integration in Message View**:
  - Added dedicated **🚫 Spam** (and **✅ Not Spam** when in Junk/Spam folders) action buttons directly in the message reading action menubar (`templates/default/read_menubar_buttons.tpl`) alongside Delete and Move.
  - Registered and implemented `template_construct_read_menubar_buttons.tpl` hook (`sb_read_menubar_buttons`) in `plugins/spam_buttons/setup.php` and `config/plugin_hooks.php`.
  - Enhanced `templates/default/read_toolbar.tpl` to properly support raw HTML button strings and structured items (`html` key), fixing missing buttons in the Options toolbar (both Spam and Calendar actions).
  - Updated `plugins/spam_buttons/action.php` to include redirect destination in JSON responses, and updated `assets/js/app.js` to smoothly navigate back to the mailbox and refresh unread badges upon reporting spam or ham.
- **Version Bump**: Incremented version from `1.5.19` to `1.5.20`.

### Version 1.5.19
- **Folder Unread Badges Contrast & Text Color Fix**:
  - Fixed an issue where the text on unread count badges next to the Inbox and subfolders was unreadable due to theme stylesheets overriding the badge text color with `var(--sm-primary) !important`, producing low/zero-contrast text (primary blue on primary blue).
  - Resolved in `css/modern_responsive/default.css`, `css/modern_responsive_dark/default.css`, `css/modern_responsive_emerald/default.css`, and all corresponding alternate template stylesheets by enforcing `#ffffff !important` with bold typography.
  - Enhanced badge selectors and styles in `assets/css/app.css` (`.sm-sidebar-folders-wrapper .leftunseen`, `.sm-sidebar-tree-container .leftrecent`, `.sm-unread-badge`) with high specificity, subtle depth shadow, and explicit link styling to guarantee crisp, legible text across all themes and dark mode.
  - Added semantic `.sm-badge` and `.sm-unread-badge` classes to the unread count wrapper in `templates/default/left_main.tpl`.
- **Version Bump**: Incremented version from `1.5.18` to `1.5.19`.

### Version 1.5.18
- **Sidebar Multi-Account Widget Streamlining**:
  - Removed the redundant `[⚙️ Manage Accounts]` action link and footer border from the sidebar widget in `plugins/multi_account/multi_account.php`.
  - Account configuration remains directly accessible via SquirrelMail Options (`src/options.php` -> Multi-Account Manager) and on the Unified Inbox view (`plugins/multi_account/unified_inbox.php`).
- **Version Bump**: Incremented version from `1.5.17` to `1.5.18`.

### Version 1.5.17
- **Removed Skin "Advanced Modus" (`default_advanced`)**:
  - Completely purged the legacy `templates/default_advanced` skin directory and obsolete template files.
  - Removed template set registration in `config/config.php` and `config/config_default.php`, retaining exclusively the modern standard `default` template set.
  - Cleanly eliminated the Skin selection control from Display Preferences (`include/options/display.php`) and the web installer (`install.php`), falling back seamlessly to `default`.
- **Modern Responsive Theme Suite & New Variants**:
  - Removed all obsolete, legacy color themes (Gmail clone, Blue Options, Classic Default, and 20+ legacy CSS stylesheets), focusing entirely on the **Modern Responsive** design language.
  - Created two brand-new theme variants:
    - **Modern Responsive Dark**: A sophisticated dark theme with deep navy/charcoal surfaces (`#0b1120`, `#111827`), refined borders (`#1f2937`), crisp high-contrast typography, and electric cyan accents (`#38bdf8`).
    - **Modern Responsive Emerald**: A clean, vibrant theme with fresh mint background tones (`#f0fdf4`), pure white content cards, and rich emerald accents (`#059669`).
  - Added standalone theme definition files (`themes/modern_responsive_dark.php`, `themes/modern_responsive_emerald.php`), alternate template stylesheets, and CSS theme directories (`css/modern_responsive_dark/default.css`, `css/modern_responsive_emerald/default.css`).
  - Enhanced theme stylesheet loading order in `functions/page_header.php` so user theme CSS custom properties take priority across both the outer SPA shell and inner workspace.
  - Updated the Web Installer (`install.php`) Step 4 with modern color previews for the 3 Modern Responsive themes.
- **Version Bump**: Incremented version from `1.5.16` to `1.5.17`.

### Version 1.5.16
- **Removed Redundant Priority Controls from Compose Page**:
  - Completely removed the redundant and cluttering Priority selector rows and dropdowns from `templates/default/compose_header.tpl` and `templates/default/compose_buttons.tpl`.
  - Preserved background priority handling via a hidden form input (`mailprio`) so email deliveries and replies proceed seamlessly without cluttering the compose interface.
- **Version Bump**: Incremented version from `1.5.15` to `1.5.16`.

### Version 1.5.15
- **Sidebar Folder Header & Actions Modernization**:
  - Completely redesigned the sidebar folder header in `templates/default/left_main.tpl` and `templates/default_advanced/left_main.tpl`, replacing legacy ASCII bracketed links (`[ Check Mail ]`) and raw text characters (`+`) with sleek, modern UI action controls.
  - Eliminated awkward multi-line text wrapping caused by cramped flex rows in the 260px sidebar pane.
  - **Sleek Action Buttons**: Introduced `.sm-sidebar-action-btn` buttons for **Manage Folders** (crisp SVG plus icon) and **Check Mail** (SVG sync icon with hover micro-rotation and instant feedback).
  - **Dedicated Refresh Timestamp**: Moved the folder refresh clock metadata (`src/left_main.php`) to a dedicated subtitle row below the header actions with a subtle clock icon, ensuring clean scannability without wrapping or truncation.
  - **Animated Folder Refresh**: Enhanced `assets/js/app.js` and `assets/css/app.css` with a smooth `.sm-spin` loading animation on the refresh icon during folder tree AJAX synchronization.
- **Version Bump**: Incremented version from `1.5.14` to `1.5.15`.

### Version 1.5.14
- **Email Reading Action Buttons (Reply, Reply All, Forward)**:
  - Modernized menubar action controls in `templates/default/read_menubar_buttons.tpl` and `templates/default_advanced/read_menubar_buttons.tpl` into responsive `.sm-btn` elements with inline SVG icons.
  - Eliminated legacy JavaScript popup blockers and `onsubmit="return false"` handlers in `src/read_body.php` that prevented Reply, Reply All, and Forward buttons from triggering within modern browsers and SPA navigation.
  - Converted `composeForm` to a clean GET form with explicit hidden fields (`passed_id`, `mailbox`, `startMessage`, `passed_ent_id`), ensuring accurate routing to `src/compose.php`.
- **Mailbox Overview Email Forwarding**:
  - Fixed single-message Forwarding from mailbox message listings in `src/right_main.php`: single selected messages are now forwarded inline with full quoted text, original sender, date, and attachments populated automatically in compose.
  - Fixed batch multi-message Forwarding: attached `.eml` entities are preloaded into compose with an itemized summary in the compose body.
  - Resolved missing body issue in `src/compose.php`: ensured fallback UID resolution from `fwduid`, default mailbox assignment (`INBOX`), and automatic conversion to `forward_as_attachment` when the "As Attachment" checkbox is checked.
- **SPA Router URI Sanitization**:
  - Enhanced `assets/js/app.js` to automatically sanitize HTML entity ampersands (`&amp;` -> `&`) in intercepted form actions and links, preventing URL query parameter mangling (e.g., `amp;mailbox`).
  - Styled message reading menubars cleanly and hid redundant `#page_header` navigation bars inside the SPA workspace.
- **Version Bump**: Incremented version from `1.5.13` to `1.5.14`.

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
