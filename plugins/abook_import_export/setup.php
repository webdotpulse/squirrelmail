<?php
/**
 * Addressbook Import-Export Plugin Setup
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage abook_import_export
 */

function squirrelmail_plugin_init_abook_import_export()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_addressbook_list.tpl']['abook_import_export']
        = 'abook_ie_addressbook_list';

    $squirrelmail_plugin_hooks['optpage_register_block']['abook_import_export']
        = 'abook_ie_optpage_register_block';
}

function abook_import_export_info()
{
    return array(
        'english_name'           => 'Address Book Import & Export',
        'version'                => '2.1.0',
        'summary'                => 'Import and export address book contacts in CSV, vCard (.vcf), and LDIF formats.',
        'details'                => 'Seamlessly import contacts from Google Contacts, Microsoft Outlook, or Apple Contacts, and export contacts with duplicate detection and conflict management.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function abook_import_export_version()
{
    $info = abook_import_export_info();
    return $info['version'];
}

function abook_ie_addressbook_list()
{
    $url = SM_PATH . 'plugins/abook_import_export/import_export.php';
    $html = '<div style="margin: 12px 0; display: flex; gap: 10px; justify-content: flex-end;">'
          . '<a href="' . $url . '" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 13px; font-weight: 500; border-radius: 6px; text-decoration: none; background: #e8f0fe; color: #1a73e8; border: 1px solid #c2e7ff;">'
          . '<span>📇</span> <span>' . _("Import / Export Contacts") . '</span>'
          . '</a>'
          . '</div>';
    return array('addressbook_list_top' => $html);
}

function abook_ie_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("Address Book Import / Export"),
        'url'  => SM_PATH . 'plugins/abook_import_export/import_export.php',
        'desc' => _("Import contacts from CSV, vCard (.vcf), or LDIF files, and export address books for Google, Outlook, or Apple Contacts."),
        'js'   => false
    );
}
