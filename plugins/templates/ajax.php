<?php
/**
 * Email Templates - AJAX Handler
 *
 * Provides template retrieval, variable substitution, and dynamic compose attachment injection.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage templates
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/identity.php');
include_once(SM_PATH . 'plugins/templates/templates_data.php');

header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action === 'get_templates') {
    $templates = tpl_load_templates();
    echo json_encode(array('success' => true, 'templates' => array_values($templates)));
    exit;
}

if ($action === 'apply_template') {
    $templateId = isset($_POST['template_id']) ? trim($_POST['template_id']) : '';
    $session    = isset($_POST['session']) ? intval($_POST['session']) : 0;
    $recipName  = isset($_POST['recip_name']) ? trim($_POST['recip_name']) : '';
    $recipEmail = isset($_POST['recip_email']) ? trim($_POST['recip_email']) : '';
    $origSubject= isset($_POST['orig_subject']) ? trim($_POST['orig_subject']) : '';

    $templates = tpl_load_templates();
    if (!isset($templates[$templateId])) {
        echo json_encode(array('success' => false, 'error' => 'Template not found.'));
        exit;
    }

    $tpl = $templates[$templateId];

    // Identity details
    $idents = get_identities();
    $myName = !empty($idents[0]['full_name']) ? $idents[0]['full_name'] : $username;
    $myEmail = !empty($idents[0]['email_address']) ? $idents[0]['email_address'] : $username;

    // First name extraction
    $firstName = $recipName;
    if (empty($firstName) && !empty($recipEmail)) {
        $firstName = ucfirst(explode('@', $recipEmail)[0]);
    }
    if (!empty($firstName)) {
        $parts = explode(' ', $firstName);
        $firstName = $parts[0];
    }
    if (empty($firstName)) {
        $firstName = 'there';
    }

    $replacements = array(
        '{name}'       => $firstName,
        '{first_name}' => $firstName,
        '{full_name}'  => !empty($recipName) ? $recipName : $firstName,
        '{email}'      => $recipEmail,
        '{subject}'    => $origSubject,
        '{date}'       => date('F j, Y'),
        '{my_name}'    => $myName,
        '{my_email}'   => $myEmail
    );

    $body = str_replace(array_keys($replacements), array_values($replacements), $tpl['body']);
    $subj = str_replace(array_keys($replacements), array_values($replacements), $tpl['subject']);

    // Attach template files to compose session if session provided
    $attachedFiles = array();
    if ($session > 0 && !empty($tpl['attachments'])) {
        $attachedFiles = tpl_copy_attachments_to_compose($templateId, $session);
    }

    echo json_encode(array(
        'success'          => true,
        'subject'          => $subj,
        'body'             => $body,
        'is_html'          => !empty($tpl['is_html']) ? 1 : (preg_match('/<[a-z][\s\S]*>/i', $tpl['body']) ? 1 : 0),
        'attachments'      => $tpl['attachments'],
        'attached_count'   => count($attachedFiles),
        'attached_files'   => $attachedFiles
    ));
    exit;
}

echo json_encode(array('success' => false, 'error' => 'Invalid action.'));
exit;
