<?php
/**
 * read_toolbar.tpl
 *
 * This template generates the "Options" toolbar while reading a message.
 * 
 * The following variables are available in this template:
 *      
 *      $links - array containing various elements to be displayed in the toolbar.
 *               Each element is an array representing an option that contains the
 *               following elements:
 *          $link['URL']  - URL needed to access the action
 *          $link['Text'] - Text to be displayed for the action.
 *          $link['Target'] - Optional link target
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

/** add required includes **/

/** extract template variables **/
extract($t);

/** Begin template **/
?>
<small>
 <?php
    $totalLinks = count($links);
    $idx = 0;
    foreach ($links as $count=>$link) {
        $idx++;
        if (is_string($link)) {
            if (empty(trim($link))) continue;
            echo $link;
            if ($idx < $totalLinks) {
                echo '&nbsp;|&nbsp;';
            }
            continue;
        }

        if (empty($link['Text']) && empty($link['html']))
            continue;

        if (!empty($link['html'])) {
            echo $link['html'];
        } else {
            ?><a href="<?php echo $link['URL']; ?>"<?php echo (empty($link['Target'])?'':' target="' . $link['Target'] . '"')?> style="white-space: nowrap;"><?php echo $link['Text']; ?></a><?php
        }

        # Spit out a divider between each element
        if ($idx < $totalLinks) {
            ?>&nbsp;|
            <?php
        }
    }
 ?>
</small>
