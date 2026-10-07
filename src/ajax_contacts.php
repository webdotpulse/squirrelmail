<?php
/**
 * ajax_contacts.php
 *
 * AJAX Address Book Search & Autocomplete Endpoint
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package squirrelmail
 * @subpackage addressbook
 */

define('PAGE_NAME', 'ajax_contacts');

require('../include/init.php');
require_once(SM_PATH . 'functions/addressbook.php');

header('Content-Type: application/json; charset=utf-8');

$abook = addressbook_init(false, true);

$query = '';
if (isset($_GET['q'])) {
    $query = trim($_GET['q']);
} elseif (isset($_GET['query'])) {
    $query = trim($_GET['query']);
}

$results = array();
if (!empty($query)) {
    $raw = $abook->s_search($query);
} else {
    $raw = $abook->list_addr();
}

$contacts = array();
if (!empty($raw) && is_array($raw)) {
    foreach ($raw as $c) {
        $email = !empty($c['email']) ? trim($c['email']) : '';
        if (empty($email)) {
            continue;
        }

        $name = !empty($c['name']) ? trim($c['name']) : '';
        if (empty($name)) {
            $first = !empty($c['firstname']) ? trim($c['firstname']) : '';
            $last = !empty($c['lastname']) ? trim($c['lastname']) : '';
            $name = trim($first . ' ' . $last);
        }
        $nick = !empty($c['nickname']) ? trim($c['nickname']) : '';

        // Clean quotes from name for safe RFC-822 formatted string
        $clean_name = str_replace(array('"', '<', '>'), '', $name);
        $formatted = !empty($clean_name) ? '"' . $clean_name . '" <' . $email . '>' : $email;

        $contacts[] = array(
            'name'      => $clean_name,
            'email'     => $email,
            'nick'      => $nick,
            'formatted' => $formatted
        );
    }
}

echo json_encode(array(
    'success'  => true,
    'query'    => $query,
    'contacts' => $contacts
));
exit;
