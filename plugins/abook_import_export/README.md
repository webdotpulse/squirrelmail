# Address Book Import & Export Plugin for SquirrelMail

## Overview
Allows users to import and export contact records to and from their SquirrelMail address books in multiple industry-standard formats:
- **CSV**: Compatible with Google Contacts / Gmail, Microsoft Outlook, and Mozilla Thunderbird.
- **vCard 3.0 (.vcf)**: Standard format for Apple Contacts, iOS, Android, and macOS.
- **LDIF**: LDAP Data Interchange Format for enterprise directories.

## Features
- **Intelligent Header Detection**: Automatically identifies columns (First Name, Last Name, Nickname, Email, Notes/Info) regardless of exported software variations.
- **Duplicate & Conflict Resolution**: Choose to skip duplicates, overwrite existing contacts with fresh data, or append unique entries.
- **Multi-Backend Support**: Supports exporting all address books or target specific local or writable backends.
- **Seamless UI Integration**: Direct access via Address Book and SquirrelMail Options menu.
