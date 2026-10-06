<?php

/**
 * protocol_header.tpl
 *
 * Template to create the HTML header for each page.
 *
 * The following variables are avilable in this template:
 *      $frames        - boolean value indicating if the page being 
 *                       rendered is a frameset or not
 *      $lang          - string indicating current SM interface language 
 *      $page_title    - current page title string
 *      $header_tags   - string containing text of any tags to be rendered
 *                       in the page header (meta tags, style links,
 *                       javascript links, etc.)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

/* retrieve the template vars */
extract($t);
?>
<!DOCTYPE html>
<?php
if (empty($lang)) {
    ?>
<html lang="en">
    <?php
} else {
    ?>
<html lang="<?php echo $lang; ?>">
    <?php
}
?>
<head>
<script type="text/javascript">
(function() {
  try {
    var stored = localStorage.getItem('sm_theme');
    if (!stored) {
      var match = document.cookie.match(/(?:^|;\s*)sm_theme=([^;]*)/);
      if (match) stored = decodeURIComponent(match[1]);
    }
    var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
  } catch(e) {}
})();
</script>
<?php
if (!empty($page_title)) {
    ?>
<title><?php echo $page_title; ?></title>
    <?php
}
?>
<?php
if (!empty($header_tags)) {
    echo $header_tags;
} 
?>
</head>


