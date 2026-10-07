<?php

/**
 * options_personal.php
 *
 * Displays all options relating to personal information
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 */

/** SquirrelMail required files. */
require_once(SM_PATH . 'include/timezones.php');
require_once(SM_PATH . 'functions/identity.php');

/* Define the group constants for the personal options page. */
define('SMOPT_GRP_CONTACT', 0);
define('SMOPT_GRP_REPLY', 1);
define('SMOPT_GRP_SIG', 2);
define('SMOPT_GRP_TZ', 3);
define('SMOPT_GRP_IDENTITIES', 4);

/**
 * This function builds an array with all the information about
 * the options available to the user, and returns it. The options
 * are grouped by the groups in which they are displayed.
 * For each option, the following information is stored:
 * - name: the internal (variable) name
 * - caption: the description of the option in the UI
 * - type: one of SMOPT_TYPE_*
 * - refresh: one of SMOPT_REFRESH_*
 * - size: one of SMOPT_SIZE_*
 * - save: the name of a function to call when saving this option
 * @return array all option information
 */
function load_optpage_data_personal() {
    global $data_dir, $username, $edit_identity, $edit_name, $edit_reply_to,
           $full_name, $reply_to, $email_address, $signature, $html_signature, $tzChangeAllowed,
           $timeZone, $domain;

    /* Set the values of some global variables. */
    $full_name = getPref($data_dir, $username, 'full_name');
    $reply_to = getPref($data_dir, $username, 'reply_to');
    $email_address  = getPref($data_dir, $username, 'email_address',SMPREF_NONE);
    $signature  = getSig($data_dir, $username, 'g');
    $html_signature = getHtmlSig($data_dir, $username, 'g');
    
    // set email_address to default value, if it is not set in user's preferences
    if ($email_address == SMPREF_NONE) {
        if (preg_match("/(.+)@(.+)/",$username)) {
            $email_address = $username;
        } else {
            $email_address = $username . '@' . $domain ;
        }
    }

    /* Build a simple array into which we will build options. */
    $optgrps = array();
    $optvals = array();

    /******************************************************/
    /* LOAD EACH GROUP OF OPTIONS INTO THE OPTIONS ARRAY. */
    /******************************************************/

    /*** Load the Contact Information Options into the array ***/
    $optgrps[SMOPT_GRP_CONTACT] = _("Name and Address Options (Default Identity)");
    $optvals[SMOPT_GRP_CONTACT] = array();

    if (!isset($edit_identity)) {
        $edit_identity = TRUE;
    }

    if ($edit_identity || $edit_name) {
        $optvals[SMOPT_GRP_CONTACT][] = array(
            'name'    => 'full_name',
            'caption' => _("Full Name"),
            'type'    => SMOPT_TYPE_STRING,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_HUGE
        );
    } else {
        $optvals[SMOPT_GRP_CONTACT][] = array(
            'name'    => 'full_name',
            'caption' => _("Full Name"),
            'type'    => SMOPT_TYPE_COMMENT,
            'refresh' => SMOPT_REFRESH_NONE,
            'comment' => $full_name
        );
    }

    if ($edit_identity) {
        $optvals[SMOPT_GRP_CONTACT][] = array(
            'name'    => 'email_address',
            'caption' => _("E-mail Address"),
            'type'    => SMOPT_TYPE_STRING,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_HUGE
        );
    } else {
        $optvals[SMOPT_GRP_CONTACT][] = array(
            'name'    => 'email_address',
            'caption' => _("E-mail Address"),
            'type'    => SMOPT_TYPE_COMMENT,
            'refresh' => SMOPT_REFRESH_NONE,
            'comment' => sm_encode_html_special_chars($email_address)
        );
    }

    if ($edit_identity || $edit_reply_to) {
        $optvals[SMOPT_GRP_CONTACT][] = array(
            'name'    => 'reply_to',
            'caption' => _("Reply To"),
            'type'    => SMOPT_TYPE_STRING,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_HUGE
        );
    }

    $optvals[SMOPT_GRP_CONTACT][] = array(
        'name'    => 'signature',
        'caption' => _("Signature (Plain Text)"),
        'type'    => SMOPT_TYPE_TEXTAREA,
        'refresh' => SMOPT_REFRESH_NONE,
        'size'    => SMOPT_SIZE_MEDIUM,
        'save'    => 'save_option_signature'
    );

    $optvals[SMOPT_GRP_CONTACT][] = array(
        'name'    => 'html_signature',
        'caption' => _("HTML Signature"),
        'type'    => SMOPT_TYPE_TEXTAREA,
        'refresh' => SMOPT_REFRESH_NONE,
        'size'    => SMOPT_SIZE_LARGE,
        'trailing_text' => _("Rich HTML formatted signature. Use the formatting toolbar or paste raw HTML tags.") . '<br><a href="' . sqm_baseuri() . 'plugins/signature_creator/options.php" class="sm-btn sm-btn-secondary" style="margin-top:6px; display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-weight:600; color:#1a73e8;">✒️ ' . _("Launch Signature Creator &amp; Templates Studio") . '</a>',
        'save'    => 'save_option_html_signature'
    );

    /*** Load Alternate Identities & Signatures ***/
    $all_identities = get_identities();
    $optgrps[SMOPT_GRP_IDENTITIES] = _("Alternate Identities & Signatures");
    $optvals[SMOPT_GRP_IDENTITIES] = array();

    if ($edit_identity) {
        $ident_count = count($all_identities);
        for ($idx = 1; $idx < $ident_count; $idx++) {
            $ident = $all_identities[$idx];
            $GLOBALS['full_name_' . $idx] = $ident['full_name'];
            $GLOBALS['email_address_' . $idx] = $ident['email_address'];
            $GLOBALS['reply_to_' . $idx] = $ident['reply_to'];
            $GLOBALS['signature_' . $idx] = $ident['signature'];
            $GLOBALS['html_signature_' . $idx] = isset($ident['html_signature']) ? $ident['html_signature'] : '';

            $optvals[SMOPT_GRP_IDENTITIES][] = array(
                'name'    => 'ident_header_' . $idx,
                'caption' => '',
                'type'    => SMOPT_TYPE_INFO,
                'refresh' => SMOPT_REFRESH_NONE,
                'comment' => '<div style="font-weight: 700; color: #1a73e8; padding: 10px 0 4px; border-bottom: 2px solid #e8eaed; margin-bottom: 8px; font-size: 14px;">'
                           . sprintf(_("Alternate Identity %d"), $idx) . ' (' . htmlspecialchars($ident['email_address']) . ')</div>'
            );

            $optvals[SMOPT_GRP_IDENTITIES][] = array(
                'name'    => 'full_name_' . $idx,
                'caption' => sprintf(_("Full Name (%d)"), $idx),
                'type'    => SMOPT_TYPE_STRING,
                'refresh' => SMOPT_REFRESH_NONE,
                'size'    => SMOPT_SIZE_HUGE,
                'save'    => 'save_option_identity_field'
            );

            $optvals[SMOPT_GRP_IDENTITIES][] = array(
                'name'    => 'email_address_' . $idx,
                'caption' => sprintf(_("E-mail Address (%d)"), $idx),
                'type'    => SMOPT_TYPE_STRING,
                'refresh' => SMOPT_REFRESH_NONE,
                'size'    => SMOPT_SIZE_HUGE,
                'save'    => 'save_option_identity_field'
            );

            $optvals[SMOPT_GRP_IDENTITIES][] = array(
                'name'    => 'reply_to_' . $idx,
                'caption' => sprintf(_("Reply To (%d)"), $idx),
                'type'    => SMOPT_TYPE_STRING,
                'refresh' => SMOPT_REFRESH_NONE,
                'size'    => SMOPT_SIZE_HUGE,
                'save'    => 'save_option_identity_field'
            );

            $optvals[SMOPT_GRP_IDENTITIES][] = array(
                'name'    => 'signature_' . $idx,
                'caption' => sprintf(_("Signature Plain Text (%d)"), $idx),
                'type'    => SMOPT_TYPE_TEXTAREA,
                'refresh' => SMOPT_REFRESH_NONE,
                'size'    => SMOPT_SIZE_MEDIUM,
                'save'    => 'save_option_identity_field'
            );

            $optvals[SMOPT_GRP_IDENTITIES][] = array(
                'name'    => 'html_signature_' . $idx,
                'caption' => sprintf(_("HTML Signature (%d)"), $idx),
                'type'    => SMOPT_TYPE_TEXTAREA,
                'refresh' => SMOPT_REFRESH_NONE,
                'size'    => SMOPT_SIZE_LARGE,
                'trailing_text' => sprintf(_("Rich HTML formatted signature for Identity %d."), $idx),
                'save'    => 'save_option_identity_field'
            );
        }

        $new_ident_header_val = '<div style="font-weight: 700; color: #1e8e3e; padding: 14px 0 6px; border-bottom: 2px solid #e8eaed; margin-top: 12px; margin-bottom: 8px; font-size: 14px;">'
                               . '+ ' . _("Add New Identity with HTML Signature") . '</div>';
        $GLOBALS['new_ident_header'] = $new_ident_header_val;
        $optvals[SMOPT_GRP_IDENTITIES][] = array(
            'name'          => 'new_ident_header',
            'caption'       => '',
            'type'          => SMOPT_TYPE_INFO,
            'refresh'       => SMOPT_REFRESH_NONE,
            'initial_value' => $new_ident_header_val,
            'comment'       => $new_ident_header_val
        );

        $optvals[SMOPT_GRP_IDENTITIES][] = array(
            'name'    => 'new_ident_name',
            'caption' => _("New Full Name"),
            'type'    => SMOPT_TYPE_STRING,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_HUGE,
            'save'    => 'save_option_new_identity'
        );

        $optvals[SMOPT_GRP_IDENTITIES][] = array(
            'name'    => 'new_ident_email',
            'caption' => _("New E-mail Address"),
            'type'    => SMOPT_TYPE_STRING,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_HUGE,
            'save'    => 'save_option_new_identity'
        );

        $optvals[SMOPT_GRP_IDENTITIES][] = array(
            'name'    => 'new_ident_reply_to',
            'caption' => _("New Reply To"),
            'type'    => SMOPT_TYPE_STRING,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_HUGE,
            'save'    => 'save_option_new_identity'
        );

        $optvals[SMOPT_GRP_IDENTITIES][] = array(
            'name'    => 'new_ident_sig',
            'caption' => _("New Signature (Plain Text)"),
            'type'    => SMOPT_TYPE_TEXTAREA,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_MEDIUM,
            'save'    => 'save_option_new_identity'
        );

        $optvals[SMOPT_GRP_IDENTITIES][] = array(
            'name'    => 'new_ident_html_sig',
            'caption' => _("New HTML Signature"),
            'type'    => SMOPT_TYPE_TEXTAREA,
            'refresh' => SMOPT_REFRESH_NONE,
            'size'    => SMOPT_SIZE_LARGE,
            'trailing_text' => _("Rich HTML formatted signature for new identity."),
            'save'    => 'save_option_new_identity'
        );

        $identities_link_value = '<div style="padding: 10px 0;"><a href="options_identities.php" style="color: #1a73e8; text-decoration: underline; font-weight: 500;">'
                               . _("Advanced Identity Management (Reorder / Delete)")
                               . '</a></div>';
        $optvals[SMOPT_GRP_IDENTITIES][] = array(
            'name'    => 'identities_link',
            'caption' => _("Advanced Management"),
            'type'    => SMOPT_TYPE_COMMENT,
            'refresh' => SMOPT_REFRESH_NONE,
            'comment' =>  $identities_link_value
        );
    }

    if ( $tzChangeAllowed || function_exists('date_default_timezone_set')) {
        $TZ_ARRAY[SMPREF_NONE] = _("Same as server");

        $aTimeZones = sq_get_tz_array();
        unset($message);
        if (! empty($aTimeZones)) {
            // check if current timezone is linked to other TZ and update it
            if ($timeZone != SMPREF_NONE && $timeZone != "" &&
                isset($aTimeZones[$timeZone]['LINK'])) {
                $timeZone = $aTimeZones[$timeZone]['LINK'];
            }

            foreach ($aTimeZones as $TzKey => $TzData) {
                if (! isset($TzData['LINK'])) {
                    $TZ_ARRAY[$TzKey] = $TzKey;
                }
            }
        } else {
            $message = _("Error opening timezone config, contact administrator.");
        }

        if (isset($message)) {
            plain_error_message($message);
            exit;
        }

        $optgrps[SMOPT_GRP_TZ] = _("Timezone Options");
        $optvals[SMOPT_GRP_TZ] = array();

        $optvals[SMOPT_GRP_TZ][] = array(
            'name'    => 'timezone',
            'caption' => _("Your current timezone"),
            'type'    => SMOPT_TYPE_STRLIST,
            'refresh' => SMOPT_REFRESH_NONE,
            'posvals' => $TZ_ARRAY
        );
    }

    /*** Load the Reply Citation Options into the array ***/
    $optgrps[SMOPT_GRP_REPLY] = _("Reply Citation Options");
    $optvals[SMOPT_GRP_REPLY] = array();

    $optvals[SMOPT_GRP_REPLY][] = array(
        'name'    => 'reply_citation_style',
        'caption' => _("Reply Citation Style"),
        'type'    => SMOPT_TYPE_STRLIST,
        'refresh' => SMOPT_REFRESH_NONE,
        'posvals' => array(SMPREF_NONE    => _("No Citation"),
                           'author_said'  => _("AUTHOR Wrote"),
                           'date_time_author' => _("On DATE, AUTHOR Wrote"),
                           'quote_who'    => _("Quote Who XML"),
                           'user-defined' => _("User-Defined"))
    );

    $optvals[SMOPT_GRP_REPLY][] = array(
        'name'    => 'reply_citation_start',
        'caption' => _("User-Defined Citation Start"),
        'type'    => SMOPT_TYPE_STRING,
        'refresh' => SMOPT_REFRESH_NONE,
        'size'    => SMOPT_SIZE_MEDIUM
    );

    $optvals[SMOPT_GRP_REPLY][] = array(
        'name'    => 'reply_citation_end',
        'caption' => _("User-Defined Citation End"),
        'type'    => SMOPT_TYPE_STRING,
        'refresh' => SMOPT_REFRESH_NONE,
        'size'    => SMOPT_SIZE_MEDIUM
    );

    /*** Load the Signature Options into the array ***/
    $optgrps[SMOPT_GRP_SIG] = _("Signature Options");
    $optvals[SMOPT_GRP_SIG] = array();

    $optvals[SMOPT_GRP_SIG][] = array(
        'name'    => 'use_signature',
        'caption' => _("Use Signature"),
        'type'    => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE
    );

    $optvals[SMOPT_GRP_SIG][] = array(
        'name'    => 'prefix_sig',
        'caption' => _("Prefix Signature with '-- ' Line"),
        'type'    => SMOPT_TYPE_BOOLEAN,
        'refresh' => SMOPT_REFRESH_NONE
    );

    /* Assemble all this together and return it as our result. */
    $result = array(
        'grps' => $optgrps,
        'vals' => $optvals
    );
    return ($result);
}

/******************************************************************/
/** Define any specialized save functions for this option page. ***/
/******************************************************************/

/**
 * Saves the plain text signature option.
 */
function save_option_signature($option) {
    global $data_dir, $username;
    setSig($data_dir, $username, 'g', $option->new_value);
}

/**
 * Saves the HTML signature option for default identity.
 */
function save_option_html_signature($option) {
    global $data_dir, $username;
    setHtmlSig($data_dir, $username, 'g', $option->new_value);
}

/**
 * Saves alternate identity fields (name, email, reply-to, signature, html_signature).
 */
function save_option_identity_field($option) {
    global $data_dir, $username;
    if (preg_match('/^([a-z_]+)_(\d+)$/', $option->name, $matches)) {
        $field = $matches[1];
        $idx = (int)$matches[2];
        if ($field === 'signature') {
            setSig($data_dir, $username, $idx, $option->new_value);
        } else if ($field === 'html_signature') {
            setHtmlSig($data_dir, $username, $idx, $option->new_value);
        } else {
            setPref($data_dir, $username, $field . $idx, $option->new_value);
        }
    }
}

/**
 * Saves a newly added identity submitted from the form.
 */
function save_option_new_identity($option) {
    global $data_dir, $username;
    static $saved_new_ident = false;
    if ($saved_new_ident) return;
    $saved_new_ident = true;

    sqgetGlobalVar('new_new_ident_name', $name, SQ_POST);
    sqgetGlobalVar('new_new_ident_email', $email, SQ_POST);
    sqgetGlobalVar('new_new_ident_reply_to', $reply_to, SQ_POST);
    sqgetGlobalVar('new_new_ident_sig', $sig, SQ_POST);
    sqgetGlobalVar('new_new_ident_html_sig', $html_sig, SQ_POST);

    $name = trim($name ?? '');
    $email = trim($email ?? '');
    $reply_to = trim($reply_to ?? '');
    $sig = $sig ?? '';
    $html_sig = $html_sig ?? '';

    if (!empty($name) || !empty($email) || !empty($sig) || !empty($html_sig)) {
        $num_cur = (int) getPref($data_dir, $username, 'identities', 1);
        if ($num_cur < 1) $num_cur = 1;
        $next_idx = $num_cur;

        setPref($data_dir, $username, 'full_name' . $next_idx, $name);
        setPref($data_dir, $username, 'email_address' . $next_idx, $email);
        setPref($data_dir, $username, 'reply_to' . $next_idx, $reply_to);
        setSig($data_dir, $username, $next_idx, $sig);
        setHtmlSig($data_dir, $username, $next_idx, $html_sig);

        setPref($data_dir, $username, 'identities', $num_cur + 1);
    }
}

