<?php
/**
 * AI Agent & Email Assistant Plugin for SquirrelMail
 *
 * Powered by Google Gemini 3.8.
 * Provides compose assistance, smart replies, translation, email summarization,
 * fraud/phishing/scam checking, and server-side cron automation for spam filtering,
 * auto-labeling, and draft generation.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

/**
 * Register this plugin with SquirrelMail
 */
function squirrelmail_plugin_init_ai_agent()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_compose_buttons.tpl']['ai_agent']
        = 'ai_agent_compose_buttons';

    $squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['ai_agent']
        = 'ai_agent_compose_close';

    $squirrelmail_plugin_hooks['read_body_header_right']['ai_agent']
        = 'ai_agent_read_toolbar';

    $squirrelmail_plugin_hooks['read_body_top']['ai_agent']
        = 'ai_agent_read_top';

    $squirrelmail_plugin_hooks['optpage_register_block']['ai_agent']
        = 'ai_agent_optpage_register_block';
}

/**
 * Returns info about this plugin
 *
 * @return array An array of plugin information.
 */
function ai_agent_info()
{
    return array(
        'english_name'           => 'AI Email Agent & Assistant (Gemini 3.8)',
        'version'                => '3.8.0',
        'summary'                => 'AI Assistant for email: compose, reply, summarize, translate, scam detection, and server-side cron automation.',
        'details'                => 'Leverages Google Gemini 3.8 to assist users in webmail (smart compose, contextual replies, translation, summaries, fraud detection) and runs background cron tasks for spam filtering, email categorization/auto-labeling, and auto-draft replies.',
        'requires_configuration' => 1,
        'requires_source_patch'  => 0,
    );
}

/**
 * Returns version info about this plugin
 */
function ai_agent_version()
{
    $info = ai_agent_info();
    return $info['version'];
}

/**
 * Hook wrappers
 */
function ai_agent_compose_buttons()
{
    include_once(SM_PATH . 'plugins/ai_agent/ai_agent.php');
    return ai_agent_compose_buttons_do();
}

function ai_agent_compose_close()
{
    include_once(SM_PATH . 'plugins/ai_agent/ai_agent.php');
    return ai_agent_compose_close_do();
}

function ai_agent_read_toolbar(&$links)
{
    include_once(SM_PATH . 'plugins/ai_agent/ai_agent.php');
    return ai_agent_read_toolbar_do($links);
}

function ai_agent_read_top()
{
    include_once(SM_PATH . 'plugins/ai_agent/ai_agent.php');
    return ai_agent_read_top_do();
}

function ai_agent_optpage_register_block()
{
    include_once(SM_PATH . 'plugins/ai_agent/ai_agent.php');
    return ai_agent_optpage_register_block_do();
}
