<?php

/**
 * Administrator plugin - Authentication routines
 *
 * Checks if the current authenticated user has administrative privileges
 * based on config/admins whitelist, plugins/administrator/admins, or posix ownership.
 *
 * @author Philippe Mingo
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package plugins
 * @subpackage administrator
 */

/**
 * Check if user has access to administrative functions
 *
 * @return boolean
 */
function adm_check_user() {
    global $plugins;

    /* Fail if the plugin is not enabled */
    if (!is_array($plugins) || !in_array('administrator', $plugins, true)) {
        return false;
    }

    if (!sqgetGlobalVar('username', $username, SQ_SESSION)) {
        $username = '';
    }

    $username = trim($username);
    if ($username === '') {
        return false;
    }

    $basePath = defined('SM_PATH') ? SM_PATH : './';

    /* Candidate whitelist files */
    $admin_files = array(
        $basePath . 'config/admins',
        $basePath . 'plugins/administrator/admins',
    );

    $auths = array();
    $found_file = false;

    foreach ($admin_files as $file) {
        if (file_exists($file) && is_readable($file)) {
            $found_file = true;
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Skip empty lines and comments
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    $auths[] = strtolower($line);
                }
            }
        }
    }

    $user_lower = strtolower($username);
    $user_parts = explode('@', $user_lower, 2);
    $user_local = $user_parts[0];

    // If an admins whitelist file was located, use it
    if ($found_file) {
        if (!empty($auths)) {
            if (in_array($user_lower, $auths, true) || in_array($user_local, $auths, true)) {
                return true;
            }
        }
        return false;
    }

    /* Fallback to POSIX file ownership check if no admins file exists */
    $cfg_file = $basePath . 'config/config.php';
    if (file_exists($cfg_file) && function_exists('posix_getpwuid') && function_exists('fileowner')) {
        $adm_id = fileowner($cfg_file);
        if ($adm_id !== false) {
            $adm = posix_getpwuid($adm_id);
            if (!empty($adm['name']) && ($user_lower === strtolower($adm['name']) || $user_local === strtolower($adm['name']))) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Removes whitespace from array values
 * @param string $value array value that has to be trimmed
 * @param string $key array key
 * @since 1.5.1 and 1.4.5
 * @access private
 */
function adm_array_trim(&$value, $key) {
    $value = trim($value);
}
