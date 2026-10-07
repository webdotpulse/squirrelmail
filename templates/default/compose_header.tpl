<?php
/**
 * compose_header.tpl
 *
 * Modern Semantic Compose Header (No layout tables)
 *
 * @copyright 1999-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * @package squirrelmail
 * @subpackage templates
 */

extract($t);
?>
<div class="sm-compose-card">
  <section class="sm-compose-header-fields">
    <?php if (count($identities) > 1): ?>
    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="identity"><?php echo _("From"); ?>:</label>
      <select name="identity" <?php if ($accesskey_compose_identity != 'NONE') echo 'accesskey="' . $accesskey_compose_identity . '" '; ?>id="identity" class="sm-compose-select">
        <?php
        foreach ($identities as $id=>$ident) {
            echo '<option value="'.$id.'"'. ($identity_def==$id ? ' selected="selected"' : '') .'>'. $ident .'</option>';
        }
        ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="to"><?php echo _("To"); ?>:</label>
      <input type="text" name="send_to" id="to" class="sm-compose-input" value="<?php echo $to; ?>" placeholder="<?php echo _("Recipients"); ?>" autocomplete="off" <?php if ($accesskey_compose_to != 'NONE') echo 'accesskey="' . $accesskey_compose_to . '" '; echo $input_onfocus; ?> />
    </div>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="send_to_cc"><?php echo _("Cc"); ?>:</label>
      <input type="text" name="send_to_cc" id="send_to_cc" class="sm-compose-input" value="<?php echo $cc; ?>" placeholder="<?php echo _("Carbon copy"); ?>" autocomplete="off" <?php if ($accesskey_compose_cc != 'NONE') echo 'accesskey="' . $accesskey_compose_cc . '" '; echo $input_onfocus; ?> />
    </div>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="send_to_bcc"><?php echo _("Bcc"); ?>:</label>
      <input type="text" name="send_to_bcc" id="send_to_bcc" class="sm-compose-input" value="<?php echo $bcc; ?>" placeholder="<?php echo _("Blind carbon copy"); ?>" autocomplete="off" <?php if ($accesskey_compose_bcc != 'NONE') echo 'accesskey="' . $accesskey_compose_bcc . '" '; echo $input_onfocus; ?> />
    </div>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="subject"><?php echo _("Subject"); ?>:</label>
      <input type="text" name="subject" id="subject" class="sm-compose-input" value="<?php echo $subject; ?>" placeholder="<?php echo _("Subject line"); ?>" <?php if (!empty($accesskey_compose_subject) && $accesskey_compose_subject != 'NONE') echo 'accesskey="' . $accesskey_compose_subject . '" '; echo $input_onfocus ?? ''; ?> />
    </div>

    <div class="sm-compose-field-row">
      <label class="sm-compose-label" for="header_mailprio"><?php echo _("Priority"); ?>:</label>
      <div style="display: flex; align-items: center; gap: 8px;">
        <select id="header_mailprio" class="sm-compose-select" onchange="var p=document.getElementById('mailprio'); if(p) p.value=this.value;" style="padding: 5px 12px; font-size: 13px; font-weight: 500; border-radius: var(--sm-radius-sm, 6px); border: 1px solid var(--sm-border); background: var(--sm-bg-surface); color: var(--sm-text-primary); cursor: pointer;">
          <option value="1" <?php if (($mailprio ?? ($current_priority ?? 3)) == 1) echo 'selected="selected"'; ?>>🔴 <?php echo _("High"); ?></option>
          <option value="3" <?php if (($mailprio ?? ($current_priority ?? 3)) == 3 || empty($mailprio)) echo 'selected="selected"'; ?>>⚪ <?php echo _("Normal"); ?></option>
          <option value="5" <?php if (($mailprio ?? ($current_priority ?? 3)) == 5) echo 'selected="selected"'; ?>>🔵 <?php echo _("Low"); ?></option>
        </select>
      </div>
    </div>
  </section>

  <style>
  .sm-abook-dropdown {
      position: absolute;
      display: none;
      z-index: 100005;
      background: var(--sm-bg-surface, #ffffff);
      border: 1px solid var(--sm-border, #dadce0);
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
      max-height: 270px;
      overflow-y: auto;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-width: 320px;
  }
  .sm-abook-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 14px;
      cursor: pointer;
      transition: background 0.15s ease;
      border-bottom: 1px solid var(--sm-border-light, #f1f3f4);
  }
  .sm-abook-item:last-child {
      border-bottom: none;
  }
  .sm-abook-item:hover, .sm-abook-item.selected {
      background: var(--sm-bg-hover, #e8f0fe);
  }
  .sm-abook-avatar {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1a73e8, #1557b0);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 600;
      flex-shrink: 0;
  }
  .sm-abook-info {
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0;
  }
  .sm-abook-name {
      font-size: 13px;
      font-weight: 600;
      color: var(--sm-text-primary, #202124);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
  }
  .sm-abook-email {
      font-size: 11px;
      color: var(--sm-text-muted, #5f6368);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
  }
  </style>

  <script>
  (function() {
      var preloadedContacts = <?php echo json_encode($abook_contacts ?? array()); ?>;
      window.smAbookContacts = preloadedContacts;

      function setupRecipientAutofill() {
          var fields = [
              document.getElementById('to'),
              document.getElementById('send_to_cc'),
              document.getElementById('send_to_bcc')
          ].filter(Boolean);

          if (!fields.length) return;

          var dropdown = document.getElementById('sm-abook-autocomplete-menu');
          if (!dropdown) {
              dropdown = document.createElement('div');
              dropdown.className = 'sm-abook-dropdown';
              dropdown.id = 'sm-abook-autocomplete-menu';
              document.body.appendChild(dropdown);
          }

          var activeField = null;
          var selectedIndex = -1;
          var currentItems = [];

          function getCaretToken(input) {
              var val = input.value;
              var pos = input.selectionStart || val.length;
              var lastComma = val.lastIndexOf(',', pos - 1);
              var lastSemi = val.lastIndexOf(';', pos - 1);
              var start = Math.max(lastComma, lastSemi);
              var token = (start === -1) ? val.substring(0, pos) : val.substring(start + 1, pos);
              return {
                  token: token.trim(),
                  startIdx: start + 1,
                  endIdx: pos
              };
          }

          function replaceToken(input, formattedContact) {
              var val = input.value;
              var pos = input.selectionStart || val.length;
              var lastComma = val.lastIndexOf(',', pos - 1);
              var lastSemi = val.lastIndexOf(';', pos - 1);
              var start = Math.max(lastComma, lastSemi);

              var nextComma = val.indexOf(',', pos);
              var nextSemi = val.indexOf(';', pos);
              var end = (nextComma === -1 && nextSemi === -1) ? val.length : 
                        (nextComma === -1 ? nextSemi : (nextSemi === -1 ? nextComma : Math.min(nextComma, nextSemi)));

              var before = (start === -1) ? '' : val.substring(0, start + 1) + ' ';
              var after = val.substring(end);

              input.value = before + formattedContact + (after.startsWith(',') ? '' : ', ') + after.trimStart();
              input.focus();
              hideMenu();
          }

          function positionDropdown(input) {
              var rect = input.getBoundingClientRect();
              dropdown.style.top = (rect.bottom + window.scrollY + 4) + 'px';
              dropdown.style.left = (rect.left + window.scrollX) + 'px';
              dropdown.style.width = Math.max(rect.width, 340) + 'px';
          }

          function hideMenu() {
              dropdown.style.display = 'none';
              selectedIndex = -1;
              currentItems = [];
          }

          function renderMatches(matches, input) {
              currentItems = matches;
              if (!matches || matches.length === 0) {
                  hideMenu();
                  return;
              }

              dropdown.innerHTML = '';
              matches.slice(0, 8).forEach(function(contact, idx) {
                  var item = document.createElement('div');
                  item.className = 'sm-abook-item' + (idx === 0 ? ' selected' : '');
                  
                  var initial = (contact.name || contact.email || '?').charAt(0).toUpperCase();
                  item.innerHTML = 
                      '<div class="sm-abook-avatar">' + initial + '</div>' +
                      '<div class="sm-abook-info">' +
                          '<div class="sm-abook-name">' + escapeHtml(contact.name || contact.email) + '</div>' +
                          '<div class="sm-abook-email">&lt;' + escapeHtml(contact.email) + '&gt;' + (contact.nick ? ' • ' + escapeHtml(contact.nick) : '') + '</div>' +
                      '</div>';

                  item.onmousedown = function(e) {
                      e.preventDefault();
                      replaceToken(input, contact.formatted);
                  };

                  dropdown.appendChild(item);
              });

              selectedIndex = 0;
              positionDropdown(input);
              dropdown.style.display = 'block';
          }

          function escapeHtml(str) {
              var d = document.createElement('div');
              d.textContent = str || '';
              return d.innerHTML;
          }

          function filterContacts(query, input) {
              if (!query || query.length < 1) {
                  hideMenu();
                  return;
              }

              var q = query.toLowerCase();
              var matches = (window.smAbookContacts || []).filter(function(c) {
                  return (c.name && c.name.toLowerCase().includes(q)) ||
                         (c.email && c.email.toLowerCase().includes(q)) ||
                         (c.nick && c.nick.toLowerCase().includes(q));
              });

              if (matches.length > 0) {
                  renderMatches(matches, input);
              }

              // Query ajax_contacts.php asynchronously
              var baseUri = (typeof window.sqmApp !== 'undefined' && window.sqmApp.getBaseUri) ? window.sqmApp.getBaseUri() : '../';
              var ajaxUrl = baseUri + 'src/ajax_contacts.php?q=' + encodeURIComponent(query);
              fetch(ajaxUrl)
                  .then(function(r) { return r.json(); })
                  .then(function(data) {
                      if (data && data.success && data.contacts) {
                          var seen = {};
                          var merged = [];
                          (matches.concat(data.contacts)).forEach(function(c) {
                              var key = (c.email || '').toLowerCase();
                              if (key && !seen[key]) {
                                  seen[key] = true;
                                  merged.push(c);
                              }
                          });
                          if (activeField === input && merged.length > 0) {
                              renderMatches(merged, input);
                          }
                      }
                  })
                  .catch(function() {});
          }

          fields.forEach(function(field) {
              field.setAttribute('autocomplete', 'off');

              field.addEventListener('input', function() {
                  activeField = field;
                  var info = getCaretToken(field);
                  filterContacts(info.token, field);
              });

              field.addEventListener('focus', function() {
                  activeField = field;
                  var info = getCaretToken(field);
                  if (info.token && info.token.length >= 1) {
                      filterContacts(info.token, field);
                  }
              });

              field.addEventListener('keydown', function(e) {
                  if (dropdown.style.display === 'block' && currentItems.length > 0) {
                      var items = dropdown.querySelectorAll('.sm-abook-item');
                      if (e.key === 'ArrowDown') {
                          e.preventDefault();
                          selectedIndex = (selectedIndex + 1) % items.length;
                          updateSelection(items);
                      } else if (e.key === 'ArrowUp') {
                          e.preventDefault();
                          selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                          updateSelection(items);
                      } else if (e.key === 'Enter' || e.key === 'Tab') {
                          if (selectedIndex >= 0 && selectedIndex < currentItems.length) {
                              e.preventDefault();
                              replaceToken(field, currentItems[selectedIndex].formatted);
                          }
                      } else if (e.key === 'Escape') {
                          hideMenu();
                      }
                  }
              });

              field.addEventListener('blur', function() {
                  setTimeout(hideMenu, 250);
              });
          });

          function updateSelection(items) {
              items.forEach(function(it, idx) {
                  if (idx === selectedIndex) {
                      it.classList.add('selected');
                      it.scrollIntoView({ block: 'nearest' });
                  } else {
                      it.classList.remove('selected');
                  }
              });
          }

          document.addEventListener('click', function(e) {
              if (dropdown && !dropdown.contains(e.target) && !fields.includes(e.target)) {
                  hideMenu();
              }
          });
      }

      if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', setupRecipientAutofill);
      } else {
          setupRecipientAutofill();
      }
  })();
  </script>
