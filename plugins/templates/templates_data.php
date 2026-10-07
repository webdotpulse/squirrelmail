<?php
/**
 * Email Templates & Attachments Data Layer
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage templates
 */

if (!function_exists('getHashedFile') && defined('SM_PATH')) {
    include_once(SM_PATH . 'functions/prefs.php');
}

/**
 * Get templates JSON file path
 */
function tpl_get_storage_file()
{
    global $username, $data_dir;
    return getHashedFile($username, $data_dir, "$username.email_templates.json");
}

/**
 * Get templates attachment directory
 */
function tpl_get_attachment_dir($templateId = '')
{
    global $username, $data_dir;
    $base = getHashedDir($username, $data_dir) . '/tpl_attachments';
    if (!is_dir($base)) {
        @mkdir($base, 0700, true);
    }
    if (!empty($templateId)) {
        $cleanId = preg_replace('/[^a-zA-Z0-9_-]/', '', $templateId);
        $target = $base . '/' . $cleanId;
        if (!is_dir($target)) {
            @mkdir($target, 0700, true);
        }
        return $target;
    }
    return $base;
}

/**
 * Starter templates if none exist
 */
function tpl_get_starter_templates()
{
    return array(
        'tpl_starter_1' => array(
            'id'          => 'tpl_starter_1',
            'title'       => 'Quick Meeting Reply & Agenda',
            'category'    => 'Meetings',
            'subject'     => 'Re: Meeting Confirmation - {subject}',
            'body'        => "Hi {name},\n\nThank you for the update. I have confirmed our meeting on my calendar.\nPlease find attached the preliminary agenda and discussion points for our call.\n\nLooking forward to speaking with you!\n\nBest regards,\n{my_name}",
            'attachments' => array()
        ),
        'tpl_starter_2' => array(
            'id'          => 'tpl_starter_2',
            'title'       => 'Client Onboarding & Information Package',
            'category'    => 'Sales & Onboarding',
            'subject'     => 'Welcome to the team - Information Package',
            'body'        => "Dear {name},\n\nWelcome! We are excited to work with you.\n\nAttached to this email you will find our comprehensive onboarding guide and standard intake forms. Please review them at your convenience and let me know if you have any questions.\n\nWarm regards,\n{my_name}",
            'attachments' => array()
        ),
        'tpl_starter_3' => array(
            'id'          => 'tpl_starter_3',
            'title'       => 'Project Update & Status Report',
            'category'    => 'Projects',
            'subject'     => 'Project Progress Update - {date}',
            'body'        => "Hi {name},\n\nHere is our latest project update as of {date}.\n\nEverything is progressing on schedule. Please review the attached summary breakdown for detailed task milestones and deliverables.\n\nBest,\n{my_name}",
            'attachments' => array()
        ),
        'tpl_starter_4' => array(
            'id'          => 'tpl_starter_4',
            'title'       => 'Quick Thank You & Follow-Up',
            'category'    => 'General',
            'subject'     => 'Thank you for your email',
            'body'        => "Hi {name},\n\nThank you for reaching out! I have received your message and will review the details shortly.\n\nBest regards,\n{my_name}",
            'attachments' => array()
        )
    );
}

/**
 * Load all templates for current user
 */
function tpl_load_templates()
{
    $file = tpl_get_storage_file();
    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if (!empty($json)) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data)) {
                return $data;
            }
        }
    }

    $starters = tpl_get_starter_templates();
    tpl_save_templates($starters);
    return $starters;
}

/**
 * Save all templates
 */
function tpl_save_templates($templates)
{
    $file = tpl_get_storage_file();
    return @file_put_contents($file, json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Save single template (add or update) with optional uploaded files
 */
function tpl_save_template($templateData, $uploadedFiles = null)
{
    $templates = tpl_load_templates();
    $id = !empty($templateData['id']) ? $templateData['id'] : 'tpl_' . uniqid() . '_' . random_int(100, 999);
    $templateData['id'] = $id;
    $templateData['is_html'] = isset($templateData['is_html']) ? intval($templateData['is_html']) : (preg_match('/<[a-z][\s\S]*>/i', $templateData['body'] ?? '') ? 1 : 1);

    if (!isset($templateData['attachments']) || !is_array($templateData['attachments'])) {
        $templateData['attachments'] = isset($templates[$id]['attachments']) ? $templates[$id]['attachments'] : array();
    }

    // Process uploaded attachments
    if ($uploadedFiles && isset($uploadedFiles['tmp_name'])) {
        $targetDir = tpl_get_attachment_dir($id);
        $names = is_array($uploadedFiles['name']) ? $uploadedFiles['name'] : array($uploadedFiles['name']);
        $tmps  = is_array($uploadedFiles['tmp_name']) ? $uploadedFiles['tmp_name'] : array($uploadedFiles['tmp_name']);
        $types = is_array($uploadedFiles['type']) ? $uploadedFiles['type'] : array($uploadedFiles['type']);
        $sizes = is_array($uploadedFiles['size']) ? $uploadedFiles['size'] : array($uploadedFiles['size']);
        $errs  = is_array($uploadedFiles['error']) ? $uploadedFiles['error'] : array($uploadedFiles['error']);

        foreach ($names as $i => $origName) {
            if (isset($errs[$i]) && $errs[$i] === UPLOAD_ERR_OK && is_uploaded_file($tmps[$i])) {
                $cleanName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $origName);
                $uniqueFile = time() . '_' . $cleanName;
                $destPath = $targetDir . '/' . $uniqueFile;

                if (move_uploaded_file($tmps[$i], $destPath)) {
                    $templateData['attachments'][] = array(
                        'filename'     => $cleanName,
                        'stored_file'  => $uniqueFile,
                        'content_type' => $types[$i] ?? 'application/octet-stream',
                        'size'         => $sizes[$i] ?? filesize($destPath)
                    );
                }
            }
        }
    }

    $templates[$id] = $templateData;
    tpl_save_templates($templates);
    return $id;
}

/**
 * Delete a template and its stored attachment files
 */
function tpl_delete_template($id)
{
    $templates = tpl_load_templates();
    if (isset($templates[$id])) {
        // Remove attachment files from disk
        $dir = tpl_get_attachment_dir($id);
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) @unlink($f);
            }
            @rmdir($dir);
        }

        unset($templates[$id]);
        tpl_save_templates($templates);
        return true;
    }
    return false;
}

/**
 * Delete single attachment from template
 */
function tpl_delete_attachment($templateId, $fileIndex)
{
    $templates = tpl_load_templates();
    if (isset($templates[$templateId]) && isset($templates[$templateId]['attachments'][$fileIndex])) {
        $att = $templates[$templateId]['attachments'][$fileIndex];
        $dir = tpl_get_attachment_dir($templateId);
        $filePath = $dir . '/' . $att['stored_file'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        unset($templates[$templateId]['attachments'][$fileIndex]);
        $templates[$templateId]['attachments'] = array_values($templates[$templateId]['attachments']);
        tpl_save_templates($templates);
        return true;
    }
    return false;
}

/**
 * Attach template files directly into active SquirrelMail compose session
 *
 * @param string $templateId
 * @param int $composeSession
 * @return array List of attached files added
 */
function tpl_copy_attachments_to_compose($templateId, $composeSession)
{
    global $username, $attachment_dir;

    $templates = tpl_load_templates();
    if (!isset($templates[$templateId])) {
        return array();
    }

    $tpl = $templates[$templateId];
    if (empty($tpl['attachments'])) {
        return array();
    }

    if (!isset($_SESSION['compose_messages'][$composeSession])) {
        return array();
    }

    $composeMessage = &$_SESSION['compose_messages'][$composeSession];
    $hashed_attachment_dir = getHashedDir($username, $attachment_dir);
    $dir = tpl_get_attachment_dir($templateId);

    $attachedList = array();

    foreach ($tpl['attachments'] as $att) {
        $srcPath = $dir . '/' . $att['stored_file'];
        if (file_exists($srcPath)) {
            include_once(SM_PATH . 'functions/attachment_common.php');
            $localfilename = sq_get_attach_tempfile();
            $destPath = $hashed_attachment_dir . '/' . $localfilename;

            if (copy($srcPath, $destPath)) {
                $type = !empty($att['content_type']) ? strtolower($att['content_type']) : 'application/octet-stream';
                $composeMessage->initAttachment($type, $att['filename'], $localfilename);

                $attachedList[] = array(
                    'filename' => $att['filename'],
                    'size'     => $att['size'],
                    'type'     => $type
                );
            }
        }
    }

    return $attachedList;
}
