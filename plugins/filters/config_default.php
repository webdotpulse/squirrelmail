<?php

/**
 * Message Filters Plugin - Default Configuration
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package plugins
 * @subpackage filters
 */

/**
 * Imap connection control
 *
 * Set this to true if you experience connection problems with your IMAP server.
 * When enabled, filters uses a separate IMAP connection.
 * @global bool $UseSeparateImapConnection
 */
$UseSeparateImapConnection = false;

/**
 * Obsolete DNSBL spam filtering has been removed in favor of modern
 * AI-assisted spam filtering (plugins/spam_buttons & plugins/ai_agent).
 * Kept for backwards compatibility with any legacy third-party plugins.
 * @global bool $AllowSpamFilters
 */
$AllowSpamFilters = false;
