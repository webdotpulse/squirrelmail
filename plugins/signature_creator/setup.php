<?php
/**
 * Signature Creator & Templates Plugin - Setup
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage signature_creator
 */

function squirrelmail_plugin_init_signature_creator()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['optpage_register_block']['signature_creator']
        = 'signature_creator_optpage_register_block';
}

function signature_creator_info()
{
    return array(
        'english_name'           => 'Signature Creator & Templates',
        'version'                => '1.0.0',
        'summary'                => 'Visual HTML email signature designer with responsive templates, logos, and social badges.',
        'details'                => 'Create and customize professional HTML email signatures using pre-designed templates with live real-time preview, logo embeds, color pickers, and direct identity saving.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function signature_creator_version()
{
    $info = signature_creator_info();
    return $info['version'];
}

function signature_creator_optpage_register_block()
{
    global $optpage_blocks;

    $optUrl = function_exists('sqm_baseuri') ? sqm_baseuri() . 'plugins/signature_creator/options.php' : (defined('SM_PATH') ? SM_PATH . 'plugins/signature_creator/options.php' : '../plugins/signature_creator/options.php');

    $optpage_blocks[] = array(
        'name' => _("Signature Creator &amp; Templates"),
        'url'  => $optUrl,
        'desc' => _("Design rich, professional HTML email signatures with stylish templates, logos, custom colors, and social media links."),
        'js'   => false
    );
}
