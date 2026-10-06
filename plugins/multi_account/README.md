# Unified Multi-Account Manager Plugin for SquirrelMail

The **multi_account** plugin adds full support for a unified multi-account inbox, account switcher, and identity management to SquirrelMail.

---

## Features

1. **📬 Unified Inbox View** (`unified_inbox.php`):
   - Aggregates messages across your primary SquirrelMail account and all secondary IMAP accounts (e.g. Combell, Gmail, Outlook, cPanel).
   - Real-time client-side instant search filter (sender, subject, snippet).
   - Account filter pills (`All Inboxes`, `Primary`, and per-account badges).
   - "Unread Only" toggle to quickly review pending messages across all inboxes.
   - Distinct color badges for each account so you can identify the origin at a glance.
   - Quick actions to mark as read/unread or view messages.

2. **⚙️ Multi-Account Manager & Options** (`options.php`):
   - Add, edit, test, and delete secondary IMAP email accounts.
   - Pre-configured provider presets (Combell, Gmail, Outlook/Office365, Custom).
   - Instant "⚡ Test IMAP Connection" button with real-time AJAX socket diagnostics.
   - Badge color palette picker (Google Blue, Combell Orange, Emerald Green, Purple, Red, Amber, Cyan).
   - AES-128 encrypted credential storage.

3. **📥 Dedicated Secondary Message Viewer** (`view_message.php`):
   - Reads RFC822 messages directly from secondary accounts.
   - Decodes MIME multipart structures (HTML and plain text bodies) and MIME headers (`=?UTF-8?...?=`).
   - Automatically marks message as `\Seen` on the server.
   - "Reply" button that launches SquirrelMail compose with matching identity and recipient pre-selected.

4. **🔄 Seamless SquirrelMail Integration**:
   - **Left Folder Pane Widget** (`template_construct_left_main.tpl`): Injects a prominent Unified Inbox card with total unread badge, account list, and periodic AJAX unread count polling.
   - **Top Navigation Bar** (`template_construct_page_header.tpl`): Adds "📬 Unified Inbox" link with dynamic unread counter badge.
   - **Compose Screen** (`template_construct_compose_form_close.tpl`): Automatically synchronizes and selects the matching secondary account identity when replying.

---

## Installation & Activation

1. The plugin is located in `plugins/multi_account/`.
2. Add `'multi_account'` to your `$plugins` array in `config/config.php` (or select it during installation in `install.php`):
   ```php
   $plugins[] = 'multi_account';
   ```
3. Navigate to **Options** &rarr; **Multi-Account Manager** to connect your secondary email accounts.
