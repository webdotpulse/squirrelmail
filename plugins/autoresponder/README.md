# User Autoresponder & Mail Forwarder Plugin for SquirrelMail

## Overview
Allows users to configure Out of Office (Vacation) auto-replies and email forwarding rules directly through the webmail interface.

## Features
- **Out of Office Autoresponder**:
  - Scheduled date range (Start Date and End Date with automatic expiration).
  - Customizable subject and message body with variable tags: `{sender}`, `{date}`, `{subject}`.
  - Sender reply frequency limit (once every 1, 3, or 7 days) to prevent mail loops.
  - Prominent yet subtle active status banner at the top of webmail with quick "Turn Off Now" button.
- **Mail Forwarding**:
  - Forward emails to one or more external addresses.
  - Toggle to keep a local copy in this mailbox.
- **Sieve Script Generation**:
  - Generates RFC 5230 Sieve script for Dovecot / ManageSieve servers.
