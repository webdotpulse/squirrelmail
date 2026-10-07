<?php

/**
 * SquirrelMail Plugin Hook Registration File
 * Auto-generated
 */
global $squirrelmail_plugin_hooks;
$squirrelmail_plugin_hooks = array();

$squirrelmail_plugin_hooks['template_construct_compose_buttons.tpl']['html_mail'] = 'html_mail_compose_buttons';
$squirrelmail_plugin_hooks['template_construct_compose_buttons.tpl']['ai_agent'] = 'ai_agent_compose_buttons';
$squirrelmail_plugin_hooks['template_construct_compose_buttons.tpl']['templates'] = 'tpl_compose_buttons';
$squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['html_mail'] = 'html_mail_compose_close';
$squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['ai_agent'] = 'ai_agent_compose_close';
$squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['multi_account'] = 'multi_account_compose_close';
$squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['templates'] = 'tpl_compose_close';
$squirrelmail_plugin_hooks['compose_send']['html_mail'] = 'html_mail_compose_send';
$squirrelmail_plugin_hooks['optpage_register_block']['html_mail'] = 'html_mail_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['ai_agent'] = 'ai_agent_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['multi_account'] = 'multi_account_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['abook_import_export'] = 'abook_ie_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['calendar'] = 'calendar_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['autoresponder'] = 'autoresponder_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['message_labels'] = 'ml_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['newmail_notify'] = 'newmail_notify_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['spam_buttons'] = 'sb_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['templates'] = 'tpl_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['filters'] = 'filters_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['signature_creator'] = 'signature_creator_optpage_register_block';
$squirrelmail_plugin_hooks['optpage_register_block']['conversation_view'] = 'conversation_view_optpage_register_block';
$squirrelmail_plugin_hooks['read_body_header_right']['ai_agent'] = 'ai_agent_read_toolbar';
$squirrelmail_plugin_hooks['read_body_header_right']['calendar'] = 'calendar_read_body_header_right';
$squirrelmail_plugin_hooks['read_body_header_right']['message_labels'] = 'ml_read_body_header_right';
$squirrelmail_plugin_hooks['read_body_header_right']['spam_buttons'] = 'sb_read_body_header_right';
$squirrelmail_plugin_hooks['read_body_header_right']['message_details'] = 'show_message_details';
$squirrelmail_plugin_hooks['read_body_header_right']['conversation_view'] = 'conversation_view_read_body_header_right';
$squirrelmail_plugin_hooks['read_body_top']['ai_agent'] = 'ai_agent_read_top';
$squirrelmail_plugin_hooks['read_body_top']['calendar'] = 'calendar_read_body_top';
$squirrelmail_plugin_hooks['read_body_top']['message_labels'] = 'ml_read_body_top';
$squirrelmail_plugin_hooks['read_body_top']['conversation_view'] = 'conversation_view_read_body_top';
$squirrelmail_plugin_hooks['read_body_bottom']['conversation_view'] = 'conversation_view_read_body_bottom';
$squirrelmail_plugin_hooks['login_verified']['ai_agent'] = 'ai_agent_login_verified';
$squirrelmail_plugin_hooks['template_construct_left_main.tpl']['multi_account'] = 'multi_account_left_main';
$squirrelmail_plugin_hooks['template_construct_left_main.tpl']['message_labels'] = 'ml_left_main';
$squirrelmail_plugin_hooks['template_construct_page_header.tpl']['multi_account'] = 'multi_account_page_header';
$squirrelmail_plugin_hooks['template_construct_page_header.tpl']['calendar'] = 'calendar_page_header';
$squirrelmail_plugin_hooks['template_construct_page_header.tpl']['autoresponder'] = 'autoresponder_page_header';
$squirrelmail_plugin_hooks['template_construct_page_header.tpl']['message_labels'] = 'ml_page_header';
$squirrelmail_plugin_hooks['template_construct_page_header.tpl']['newmail_notify'] = 'newmail_notify_page_header';
$squirrelmail_plugin_hooks['template_construct_page_header.tpl']['conversation_view'] = 'conversation_view_page_header';
$squirrelmail_plugin_hooks['template_construct_addressbook_list.tpl']['abook_import_export'] = 'abook_ie_addressbook_list';
$squirrelmail_plugin_hooks['template_construct_read_message_body.tpl']['calendar'] = 'calendar_read_message_body';
$squirrelmail_plugin_hooks['template_construct_read_message_body.tpl']['conversation_view'] = 'conversation_view_read_body_top';
$squirrelmail_plugin_hooks['attachment text/calendar']['calendar'] = 'calendar_attachment_hook';
$squirrelmail_plugin_hooks['attachment application/ics']['calendar'] = 'calendar_attachment_hook';
$squirrelmail_plugin_hooks['attachment */*']['calendar'] = 'calendar_attachment_generic_hook';
$squirrelmail_plugin_hooks['template_construct_message_list.tpl']['message_labels'] = 'ml_message_list';
$squirrelmail_plugin_hooks['template_construct_message_list.tpl']['conversation_view'] = 'conversation_view_message_list';
$squirrelmail_plugin_hooks['template_construct_message_list_controls.tpl']['message_labels'] = 'ml_message_list_controls';
$squirrelmail_plugin_hooks['template_construct_message_list_controls.tpl']['spam_buttons'] = 'sb_message_list_controls';
$squirrelmail_plugin_hooks['template_construct_read_menubar_buttons.tpl']['spam_buttons'] = 'sb_read_menubar_buttons';
$squirrelmail_plugin_hooks['webmail_top']['filters'] = 'start_filters_hook';
$squirrelmail_plugin_hooks['right_main_before_select']['filters'] = 'start_filters_hook';
$squirrelmail_plugin_hooks['left_main_before']['filters'] = 'start_filters_hook';
$squirrelmail_plugin_hooks['right_main_after_header']['filters'] = 'start_filters_hook';
$squirrelmail_plugin_hooks['rename_or_delete_folder']['filters'] = 'update_for_folder_hook';
$squirrelmail_plugin_hooks['template_construct_login_webmail.tpl']['filters'] = 'start_filters_hook';
$squirrelmail_plugin_hooks['folder_status']['filters'] = 'filters_folder_status';
