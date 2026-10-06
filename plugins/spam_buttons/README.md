# Spam Buttons & AI Learning Plugin for SquirrelMail

## Overview
Adds "Report Spam" and "Not Spam" buttons to message viewing headers and bulk mailbox action bars, while continuously training the Gemini AI model and local bayesian filters based on user feedback.

## Features
- **Contextual Buttons**: Displays "Report Spam" in standard folders, and "Not Spam" inside Junk/Spam folders.
- **Bulk Mailbox Actions**: Report multiple selected emails as Spam or Not Spam at once.
- **AI Feedback & Learning**:
  - Automatically moves messages between Inbox and Junk/Trash folders.
  - Updates sender reputations, whitelisting trusted contacts and blacklisting spam domains.
  - Extracts spam keywords and invokes Google Gemini 3.8 to generate heuristic filtering rules.
- **Options Dashboard**: View spam/ham counts, whitelisted senders, learned rules, and reset training memory.
