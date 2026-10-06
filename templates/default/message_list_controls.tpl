<?php
/**
 * message_list_controls.tpl
 *
 * Modern Semantic Message List Action Controls (No layout tables)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);

if (count($aFormElements)) {
?>
  <div class="sm-message-controls-bar" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0;">
    <div class="sm-message-control-buttons" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
<?php
    foreach ($aFormElements as $widget_name => $widget_attrs) {
        switch ($widget_attrs['type']) {
        case 'submit':
            if ($widget_name != 'moveButton' && $widget_name != 'copyButton' && $widget_name != 'delete' && $widget_name != 'undeleteButton') {
                echo '<input type="submit" name="' . $widget_name . '" value="' . $widget_attrs['value'] . '" class="sm-btn sm-btn-secondary sm-btn-sm"';
                if (isset($widget_attrs['accesskey']) && $widget_attrs['accesskey'] != 'NONE')
                    echo ' accesskey="' . $widget_attrs['accesskey'] . '"';
                if (!empty($widget_attrs['extra_attrs'])) {
                    foreach ($widget_attrs['extra_attrs'] as $attr => $val) {
                        echo ' ' . $attr . '="' . $val . '"';
                    }
                }
                echo ' />';
            }
            break;
        case 'checkbox':
            if ($widget_name != 'bypass_trash') {
                echo '<label style="display: flex; align-items: center; gap: 4px; font-size: 13px; color: var(--sm-text-secondary); cursor: pointer;"><input type="checkbox" name="' . $widget_name . '" id="' . $widget_name . '"';
                if ($widget_attrs['accesskey'] != 'NONE')
                    echo ' accesskey="' . $widget_attrs['accesskey'] . '"';
                if (!empty($widget_attrs['extra_attrs'])) {
                    foreach ($widget_attrs['extra_attrs'] as $attr => $val) {
                        echo ' ' . $attr . '="' . $val . '"';
                    }
                }
                echo ' />' . $widget_attrs['value'] . '</label>';
            }
            break;
        case 'hidden':
            echo '<input type="hidden" name="'.$widget_name.'" value="'. $widget_attrs['value']."\" />";
            break;
        default: break;
        }
    }
?>
    </div>

    <div class="sm-message-control-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
<?php
    if (isset($aFormElements['delete'])) {
        echo '<div style="display: flex; align-items: center; gap: 6px;">';
        echo '<input type="submit" name="delete" value="' . $aFormElements['delete']['value'] . '" class="sm-btn sm-btn-danger sm-btn-sm" ' . ($aFormElements['delete']['accesskey'] != 'NONE' ? 'accesskey="' . $aFormElements['delete']['accesskey'] . '" ' : '') . '/>';
        if (isset($aFormElements['bypass_trash'])) {
            echo '<label style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: var(--sm-text-muted); cursor: pointer;"><input type="checkbox" name="bypass_trash" id="bypass_trash" ' . ($aFormElements['bypass_trash']['accesskey'] != 'NONE' ? 'accesskey="' . $aFormElements['bypass_trash']['accesskey'] . '" ' : '') . '/>' . $aFormElements['bypass_trash']['value'] . '</label>';
        }
        if (isset($aFormElements['undeleteButton'])) {
            echo '<input type="submit" name="undeleteButton" value="' . $aFormElements['undeleteButton']['value'] . '" class="sm-btn sm-btn-secondary sm-btn-sm" ' . ($aFormElements['undeleteButton']['accesskey'] != 'NONE' ? 'accesskey="' . $aFormElements['undeleteButton']['accesskey'] . '" ' : '') . '/>';
        }
        echo '</div>';
    }

    if (isset($aFormElements['moveButton']) || isset($aFormElements['copyButton'])) {
        echo '<div style="display: flex; align-items: center; gap: 6px;">';
?>
        <select name="targetMailbox" class="sm-select sm-select-sm" style="font-size: 12px; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--sm-border); background: var(--sm-bg-surface); color: var(--sm-text-primary);"<?php if ($aFormElements['targetMailbox']['accesskey'] != 'NONE') echo ' accesskey="' . $aFormElements['targetMailbox']['accesskey'] . '"'; ?>>
           <?php echo $aFormElements['targetMailbox']['options_list'];?>
        </select>
<?php
        if (isset($aFormElements['moveButton'])) {
            echo '<input type="submit" name="moveButton" value="' . $aFormElements['moveButton']['value'] . '" class="sm-btn sm-btn-secondary sm-btn-sm" ' . ($aFormElements['moveButton']['accesskey'] != 'NONE' ? 'accesskey="' . $aFormElements['moveButton']['accesskey'] . '" ' : '') . '/>';
        }
        if (isset($aFormElements['copyButton'])) {
            echo '<input type="submit" name="copyButton" value="' . $aFormElements['copyButton']['value'] . '" class="sm-btn sm-btn-secondary sm-btn-sm" ' . ($aFormElements['copyButton']['accesskey'] != 'NONE' ? 'accesskey="' . $aFormElements['copyButton']['accesskey'] . '" ' : '') . '/>';
        }
        echo '</div>';
    }
?>
    </div>
  </div>
<?php 
}
