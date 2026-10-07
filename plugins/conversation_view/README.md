# Conversation View Plugin for SquirrelMail

## Overview
The **conversation_view** plugin integrates full conversation threading into the SquirrelMail message reader view (`src/read_body.php`). By traversing RFC 822 `Message-ID`, `In-Reply-To`, and `References` headers across multiple mailboxes, it automatically displays:
- 📤 **Sent replies** residing in your Sent folder.
- 📝 **Pending drafts** residing in your Drafts folder with a direct **Resume Draft** action.
- 📥 **Other received messages** in the thread.

## Features
- **Cross-Folder Aggregation**: Scans `Sent`, `Drafts`, and the current folder via IMAP headers (`References`, `In-Reply-To`, and fallback subject matching).
- **Interactive Timeline**: Clean chronological timeline connecting each message with type-specific color indicators (emerald for sent replies, amber for drafts, blue for incoming).
- **Inline Message Expansion**: Click any card header to asynchronously fetch and render the full message body without leaving the page.
- **Direct Draft Resumption & Discard**: Resume editing drafts directly in the compose screen, or discard drafts asynchronously with instant DOM removal.
- **Top Toolbar Indicator**: Adds a `💬 Thread (#)` badge in the message header toolbar for quick navigation.
- **User Options**: Configure search depth, mailbox inclusions, and display placement (`bottom`, `top`, or `both`) via the SquirrelMail Options menu.
- **Modern Responsive Design**: Fully styled with CSS custom properties (`--sm-*`), seamless Light/Dark mode support, and mobile optimization.
