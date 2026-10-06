<?php
/**
 * Addressbook Import-Export Plugin - Core Functions
 *
 * Supports CSV, vCard (.vcf), and LDIF formats with duplicate detection.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage abook_import_export
 */

/**
 * Format contacts as CSV string
 * Compatible with Google Contacts, Microsoft Outlook, and Mozilla Thunderbird
 */
function abook_ie_export_csv($contacts)
{
    $fp = fopen('php://temp', 'r+');
    
    // Header row
    fputcsv($fp, array(
        'First Name',
        'Last Name',
        'Display Name',
        'Nickname',
        'E-mail Address',
        'Notes'
    ));

    foreach ($contacts as $c) {
        $first = isset($c['firstname']) ? $c['firstname'] : '';
        $last  = isset($c['lastname']) ? $c['lastname'] : '';
        $name  = isset($c['name']) ? $c['name'] : trim("$first $last");
        $nick  = isset($c['nickname']) ? $c['nickname'] : '';
        $email = isset($c['email']) ? $c['email'] : '';
        $label = isset($c['label']) ? $c['label'] : '';

        fputcsv($fp, array($first, $last, $name, $nick, $email, $label));
    }

    rewind($fp);
    $csv = stream_get_contents($fp);
    fclose($fp);
    return $csv;
}

/**
 * Format contacts as vCard (vCard 3.0 standard)
 */
function abook_ie_export_vcard($contacts)
{
    $vcf = '';
    foreach ($contacts as $c) {
        $first = isset($c['firstname']) ? $c['firstname'] : '';
        $last  = isset($c['lastname']) ? $c['lastname'] : '';
        $name  = isset($c['name']) ? $c['name'] : trim("$first $last");
        $nick  = isset($c['nickname']) ? $c['nickname'] : '';
        $email = isset($c['email']) ? $c['email'] : '';
        $label = isset($c['label']) ? $c['label'] : '';

        $vcf .= "BEGIN:VCARD\r\n";
        $vcf .= "VERSION:3.0\r\n";
        $vcf .= "N:" . abook_ie_escape_vcard($last) . ";" . abook_ie_escape_vcard($first) . ";;;\r\n";
        $vcf .= "FN:" . abook_ie_escape_vcard($name) . "\r\n";
        if (!empty($nick)) {
            $vcf .= "NICKNAME:" . abook_ie_escape_vcard($nick) . "\r\n";
        }
        if (!empty($email)) {
            $vcf .= "EMAIL;TYPE=PREF,INTERNET:" . abook_ie_escape_vcard($email) . "\r\n";
        }
        if (!empty($label)) {
            $vcf .= "NOTE:" . abook_ie_escape_vcard($label) . "\r\n";
        }
        $vcf .= "END:VCARD\r\n";
    }
    return $vcf;
}

/**
 * Escape text for vCard
 */
function abook_ie_escape_vcard($text)
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(';', '\;', $text);
    $text = str_replace(',', '\,', $text);
    $text = str_replace("\r\n", "\\n", $text);
    $text = str_replace("\n", "\\n", $text);
    return $text;
}

/**
 * Format contacts as LDIF
 */
function abook_ie_export_ldif($contacts)
{
    $ldif = "version: 1\n\n";
    foreach ($contacts as $c) {
        $first = isset($c['firstname']) ? $c['firstname'] : '';
        $last  = isset($c['lastname']) ? $c['lastname'] : '';
        $name  = isset($c['name']) ? $c['name'] : trim("$first $last");
        $email = isset($c['email']) ? $c['email'] : '';
        $label = isset($c['label']) ? $c['label'] : '';

        if (empty($name)) {
            $name = $email;
        }

        $ldif .= "dn: cn=" . $name . ",mail=" . $email . "\n";
        $ldif .= "objectClass: top\n";
        $ldif .= "objectClass: person\n";
        $ldif .= "objectClass: organizationalPerson\n";
        $ldif .= "objectClass: inetOrgPerson\n";
        $ldif .= "cn: " . $name . "\n";
        if (!empty($first)) $ldif .= "givenName: " . $first . "\n";
        if (!empty($last))  $ldif .= "sn: " . $last . "\n";
        if (!empty($email)) $ldif .= "mail: " . $email . "\n";
        if (!empty($label)) $ldif .= "description: " . $label . "\n";
        $ldif .= "\n";
    }
    return $ldif;
}

/**
 * Parse uploaded CSV file into contacts array
 */
function abook_ie_parse_csv($content)
{
    $lines = preg_split("/\r\n|\n|\r/", trim($content));
    if (empty($lines)) return array();

    // Auto-detect delimiter (, or ;)
    $firstLine = $lines[0];
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $content);
    rewind($fp);

    $header = fgetcsv($fp, 0, $delimiter);
    if (!$header) {
        fclose($fp);
        return array();
    }

    // Map column names to known fields
    $map = array(
        'first' => -1,
        'last'  => -1,
        'name'  => -1,
        'nick'  => -1,
        'email' => -1,
        'notes' => -1
    );

    foreach ($header as $idx => $col) {
        $colClean = strtolower(trim($col, " \t\n\r\0\x0B\"'"));
        if (preg_match('/first.*name|given.*name/i', $colClean)) {
            $map['first'] = $idx;
        } elseif (preg_match('/last.*name|family.*name|surname/i', $colClean)) {
            $map['last'] = $idx;
        } elseif (preg_match('/display.*name|full.*name|^name$/i', $colClean)) {
            $map['name'] = $idx;
        } elseif (preg_match('/nick/i', $colClean)) {
            $map['nick'] = $idx;
        } elseif (preg_match('/e-?mail/i', $colClean)) {
            $map['email'] = $idx;
        } elseif (preg_match('/note|info|comment|desc/i', $colClean)) {
            $map['notes'] = $idx;
        }
    }

    // If no email header was detected, guess based on column content
    if ($map['email'] === -1) {
        $map['email'] = 0; // fallback
    }

    $contacts = array();
    while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
        if (empty($row) || (count($row) === 1 && empty($row[0]))) continue;

        $email = ($map['email'] >= 0 && isset($row[$map['email']])) ? trim($row[$map['email']]) : '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check all columns in this row for valid email
            foreach ($row as $val) {
                if (filter_var(trim($val), FILTER_VALIDATE_EMAIL)) {
                    $email = trim($val);
                    break;
                }
            }
        }
        if (empty($email)) continue; // skip entries without valid email

        $first = ($map['first'] >= 0 && isset($row[$map['first']])) ? trim($row[$map['first']]) : '';
        $last  = ($map['last'] >= 0 && isset($row[$map['last']])) ? trim($row[$map['last']]) : '';
        $name  = ($map['name'] >= 0 && isset($row[$map['name']])) ? trim($row[$map['name']]) : '';
        $nick  = ($map['nick'] >= 0 && isset($row[$map['nick']])) ? trim($row[$map['nick']]) : '';
        $notes = ($map['notes'] >= 0 && isset($row[$map['notes']])) ? trim($row[$map['notes']]) : '';

        if (empty($first) && empty($last) && !empty($name)) {
            $parts = explode(' ', $name, 2);
            $first = $parts[0];
            $last  = isset($parts[1]) ? $parts[1] : '';
        } elseif (empty($name)) {
            $name = trim("$first $last");
        }

        if (empty($nick)) {
            $nick = preg_replace('/[^a-zA-Z0-9_-]/', '', explode('@', $email)[0]);
        }

        $contacts[] = array(
            'firstname' => $first,
            'lastname'  => $last,
            'name'      => $name,
            'nickname'  => $nick,
            'email'     => $email,
            'label'     => $notes
        );
    }

    fclose($fp);
    return $contacts;
}

/**
 * Parse vCard (.vcf) string into contacts array
 */
function abook_ie_parse_vcard($content)
{
    $contacts = array();
    $cards = preg_split('/END:VCARD/i', $content);

    foreach ($cards as $card) {
        if (!preg_match('/BEGIN:VCARD/i', $card)) continue;

        $entry = array(
            'firstname' => '',
            'lastname'  => '',
            'name'      => '',
            'nickname'  => '',
            'email'     => '',
            'label'     => ''
        );

        $lines = preg_split("/\r\n|\n|\r/", $card);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^N(?:;[^:]*)?:(.*)$/i', $line, $m)) {
                $parts = explode(';', $m[1]);
                $entry['lastname'] = isset($parts[0]) ? trim(stripslashes($parts[0])) : '';
                $entry['firstname'] = isset($parts[1]) ? trim(stripslashes($parts[1])) : '';
            } elseif (preg_match('/^FN(?:;[^:]*)?:(.*)$/i', $line, $m)) {
                $entry['name'] = trim(stripslashes($m[1]));
            } elseif (preg_match('/^NICKNAME(?:;[^:]*)?:(.*)$/i', $line, $m)) {
                $entry['nickname'] = trim(stripslashes($m[1]));
            } elseif (preg_match('/^EMAIL(?:;[^:]*)?:(.*)$/i', $line, $m)) {
                if (empty($entry['email'])) {
                    $entry['email'] = trim(stripslashes($m[1]));
                }
            } elseif (preg_match('/^NOTE(?:;[^:]*)?:(.*)$/i', $line, $m)) {
                $entry['label'] = trim(stripslashes($m[1]));
            }
        }

        if (empty($entry['email'])) continue;

        if (empty($entry['firstname']) && empty($entry['lastname']) && !empty($entry['name'])) {
            $parts = explode(' ', $entry['name'], 2);
            $entry['firstname'] = $parts[0];
            $entry['lastname']  = isset($parts[1]) ? $parts[1] : '';
        } elseif (empty($entry['name'])) {
            $entry['name'] = trim($entry['firstname'] . ' ' . $entry['lastname']);
        }

        if (empty($entry['nickname'])) {
            $entry['nickname'] = preg_replace('/[^a-zA-Z0-9_-]/', '', explode('@', $entry['email'])[0]);
        }

        $contacts[] = $entry;
    }

    return $contacts;
}

/**
 * Import contact list into SquirrelMail AddressBook
 *
 * @param array $contacts List of contact entries
 * @param string $conflict_mode 'skip' | 'overwrite' | 'add'
 * @param int $backend_id
 * @return array Results summary ['imported' => count, 'skipped' => count, 'errors' => []]
 */
function abook_ie_import_contacts($contacts, $conflict_mode = 'skip', $backend_id = 1)
{
    global $abook;
    if (!isset($abook)) {
        include_once(SM_PATH . 'functions/addressbook.php');
        $abook = addressbook_init(false, true);
    }

    $existing = $abook->list_addr($backend_id);
    if (!is_array($existing)) {
        $existing = array();
    }

    // Index existing by lowercase email and nickname
    $byEmail = array();
    $byNick = array();
    foreach ($existing as $ex) {
        if (!empty($ex['email'])) {
            $byEmail[strtolower(trim($ex['email']))] = $ex;
        }
        if (!empty($ex['nickname'])) {
            $byNick[strtolower(trim($ex['nickname']))] = $ex;
        }
    }

    $imported = 0;
    $skipped  = 0;
    $errors   = array();

    foreach ($contacts as $c) {
        $email = strtolower(trim($c['email']));
        $nick  = strtolower(trim($c['nickname']));

        $isDup = isset($byEmail[$email]) || isset($byNick[$nick]);

        if ($isDup && $conflict_mode === 'skip') {
            $skipped++;
            continue;
        }

        if ($isDup && $conflict_mode === 'overwrite') {
            $targetNick = isset($byEmail[$email]) ? $byEmail[$email]['nickname'] : $c['nickname'];
            $res = $abook->modify($targetNick, $c, $backend_id);
            if ($res) {
                $imported++;
            } else {
                $errors[] = "Failed updating " . htmlspecialchars($c['email']) . ": " . $abook->error;
            }
            continue;
        }

        // Add new contact (adjust nickname if clash)
        if (isset($byNick[$nick])) {
            $nick = $nick . '_' . rand(100, 999);
            $c['nickname'] = $nick;
        }

        $res = $abook->add($c, $backend_id);
        if ($res) {
            $imported++;
            $byEmail[$email] = $c;
            $byNick[$nick]   = $c;
        } else {
            $errors[] = "Failed adding " . htmlspecialchars($c['email']) . ": " . $abook->error;
        }
    }

    return array(
        'imported' => $imported,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'total'    => count($contacts)
    );
}
