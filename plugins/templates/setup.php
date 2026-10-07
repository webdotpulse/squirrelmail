<?php
/**
 * Email Templates & Attachments Plugin Setup
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage templates
 */

function squirrelmail_plugin_init_templates()
{
    global $squirrelmail_plugin_hooks;

    $squirrelmail_plugin_hooks['template_construct_compose_buttons.tpl']['templates']
        = 'tpl_compose_buttons';

    $squirrelmail_plugin_hooks['template_construct_compose_form_close.tpl']['templates']
        = 'tpl_compose_close';

    $squirrelmail_plugin_hooks['optpage_register_block']['templates']
        = 'tpl_optpage_register_block';
}

function templates_info()
{
    return array(
        'english_name'           => 'Email Templates & Attachments',
        'version'                => '2.0.0',
        'summary'                => 'Reply and compose email templates with automatic file attachments and dynamic variable tags.',
        'details'                => 'Save customizable canned responses and reply templates with attached files (brochures, PDFs, intake forms) that automatically attach to the compose window when selected.',
        'requires_configuration' => 0,
        'requires_source_patch'  => 0,
    );
}

function templates_version()
{
    $info = templates_info();
    return $info['version'];
}

function tpl_compose_buttons()
{
    $html = '<button type="button" class="sm-btn sm-btn-secondary sm-btn-sm tpl-trigger-btn" '
          . 'onclick="if(window.tplOpenModal) window.tplOpenModal();" '
          . 'title="' . _("Insert Reply or Email Template") . '">'
          . '<span>📋</span> <span>' . _("Templates") . '</span>'
          . '</button>';

    return array('compose_button_row' => $html);
}

function tpl_compose_close()
{
    global $session, $action, $send_to, $subject;

    $managerUrl = SM_PATH . 'plugins/templates/templates_manager.php';
    $ajaxUrl = SM_PATH . 'plugins/templates/ajax.php';

    ob_start();
    ?>
    <div id="tpl-modal-backdrop" class="tpl-modal-backdrop" onclick="if(event.target===this) window.tplCloseModal();">
        <div class="tpl-modal-box">
            <div class="tpl-modal-header">
                <h3 style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <span>📋</span> <span><?php echo _("Select Email Template"); ?></span>
                </h3>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="<?php echo $managerUrl; ?>" target="_blank" style="font-size: 12px; color: var(--sm-primary); text-decoration: none;">⚙️ <?php echo _("Manage Templates"); ?></a>
                    <button type="button" onclick="window.tplCloseModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--sm-text-muted);">&times;</button>
                </div>
            </div>

            <div class="tpl-modal-body" id="tpl-list-container">
                <div style="text-align: center; padding: 24px; color: #5f6368;">Loading templates...</div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var composeSession = <?php echo intval($session ?? 0); ?>;
        var ajaxUrl = (typeof window.sqmApp !== 'undefined' && window.sqmApp.getBaseUri)
            ? window.sqmApp.getBaseUri() + 'plugins/templates/ajax.php'
            : '<?php echo $ajaxUrl; ?>';

        window.tplOpenModal = function() {
            var backdrop = document.getElementById('tpl-modal-backdrop');
            if (backdrop) backdrop.style.display = 'flex';
            loadTemplates();
        };

        window.tplCloseModal = function() {
            var backdrop = document.getElementById('tpl-modal-backdrop');
            if (backdrop) backdrop.style.display = 'none';
        };

        function loadTemplates() {
            var container = document.getElementById('tpl-list-container');
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #5f6368;">Loading templates...</div>';

            fetch(ajaxUrl + '?action=get_templates')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.templates) {
                        renderTemplates(data.templates);
                    } else {
                        container.innerHTML = '<div style="color: #d93025; padding: 16px;">Failed to load templates.</div>';
                    }
                })
                .catch(err => {
                    container.innerHTML = '<div style="color: #d93025; padding: 16px;">Error connecting to templates service.</div>';
                });
        }

        function renderTemplates(templates) {
            var container = document.getElementById('tpl-list-container');
            if (!templates || templates.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding: 30px; color:#5f6368;">No templates found. <a href="<?php echo $managerUrl; ?>" target="_blank">Create one now</a>.</div>';
                return;
            }

            container.innerHTML = '';
            templates.forEach(function(tpl) {
                var card = document.createElement('div');
                card.className = 'tpl-card-choice';

                var attHtml = '';
                if (tpl.attachments && tpl.attachments.length > 0) {
                    attHtml = '<div style="margin-top: 6px;">';
                    tpl.attachments.forEach(function(a) {
                        attHtml += '<span class="tpl-att-tag">📎 ' + escapeHtml(a.filename) + ' (' + Math.round(a.size/1024) + ' KB)</span>';
                    });
                    attHtml += '</div>';
                }

                var isHtml = !tpl.hasOwnProperty('is_html') || tpl.is_html == 1 || /<[a-z][\s\S]*>/i.test(tpl.body);
                var htmlBadge = isHtml ? '<span style="font-size:10px; font-weight:700; background:#e6f4ea; color:#137333; padding:1px 5px; border-radius:3px; margin-left:6px;">HTML</span>' : '';

                card.innerHTML =
                    '<div style="display:flex; justify-content:space-between; align-items:center;">' +
                        '<div><strong style="font-size:14px; color:#202124;">' + escapeHtml(tpl.title) + '</strong>' + htmlBadge + '</div>' +
                        '<span style="font-size:11px; padding:2px 8px; border-radius:10px; background:#f1f3f4; color:#5f6368;">' + escapeHtml(tpl.category || 'General') + '</span>' +
                    '</div>' +
                    '<div style="font-size:12px; color:#5f6368; margin-top:4px;">' + (tpl.subject ? '<strong>Subject:</strong> ' + escapeHtml(tpl.subject) : '') + '</div>' +
                    '<div style="font-size:12px; color:#3c4043; margin-top:4px; line-height:1.4; max-height:44px; overflow:hidden; text-overflow:ellipsis;">' + escapeHtml(tpl.body.replace(/<[^>]*>/g, ' ')) + '</div>' +
                    attHtml +
                    '<div style="margin-top:8px; display:flex; justify-content:space-between; align-items:center; padding-top:6px; border-top:1px solid #f1f3f4;">' +
                        '<button type="button" class="tpl-btn-prev" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px; cursor:pointer; color:#1a73e8;">👁️ Preview HTML</button>' +
                        '<span style="font-size:11px; color:#1a73e8; font-weight:600;">Insert Template →</span>' +
                    '</div>' +
                    '<div class="tpl-inline-preview" style="display:none; margin-top:8px; padding:10px 12px; background:#fafbfc; border:1px dashed #cbd5e1; border-radius:6px; font-size:13px; max-height:180px; overflow-y:auto;">' +
                        (isHtml ? tpl.body : escapeHtml(tpl.body).replace(/\n/g, '<br>')) +
                    '</div>';

                var prevBtn = card.querySelector('.tpl-btn-prev');
                if (prevBtn) {
                    prevBtn.onclick = function(e) {
                        e.stopPropagation();
                        var prevBox = card.querySelector('.tpl-inline-preview');
                        if (prevBox) {
                            var isOpen = prevBox.style.display !== 'none';
                            prevBox.style.display = isOpen ? 'none' : 'block';
                            prevBtn.textContent = isOpen ? '👁️ Preview HTML' : '✕ Hide Preview';
                        }
                    };
                }

                card.onclick = function() {
                    applyTemplate(tpl.id);
                };

                container.appendChild(card);
            });
        }

        function applyTemplate(templateId) {
            var sendToInput = document.querySelector('input[name="send_to"], textarea[name="send_to"]');
            var subjInput = document.querySelector('input[name="subject"]');
            var bodyInput = document.querySelector('textarea[name="body"]');

            var recip = sendToInput ? sendToInput.value : '';
            var curSubj = subjInput ? subjInput.value : '';

            var formData = new FormData();
            formData.append('action', 'apply_template');
            formData.append('template_id', templateId);
            formData.append('session', composeSession);
            formData.append('recip_email', recip);
            formData.append('orig_subject', curSubj);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    var isHtmlTpl = data.is_html == 1 || /<[a-z][\s\S]*>/i.test(data.body);

                    // Switch to rich HTML mode if template is HTML and current mode is plain
                    var htmlToggleInput = document.getElementById('html_mail_enabled');
                    if (isHtmlTpl && htmlToggleInput && htmlToggleInput.value === '0' && typeof window.htmlMailToggleMode === 'function') {
                        window.htmlMailToggleMode();
                    }

                    // Update body textarea
                    if (bodyInput) {
                        bodyInput.value = data.body;
                    }

                    // Update WYSIWYG editor if rich text mode is active
                    var wysiwyg = document.getElementById('html-mail-wysiwyg');
                    if (wysiwyg) {
                        if (isHtmlTpl) {
                            wysiwyg.innerHTML = data.body;
                        } else {
                            var paras = data.body.split("\n\n");
                            wysiwyg.innerHTML = paras.map(p => '<p>' + p.replace(/\n/g, '<br>') + '</p>').join('');
                        }
                        if (typeof window.htmlMailUpdateStats === 'function') {
                            window.htmlMailUpdateStats();
                        }
                    }

                    // Update subject if current subject was empty
                    if (subjInput && (!subjInput.value || subjInput.value.trim() === '') && data.subject) {
                        subjInput.value = data.subject;
                    }

                    window.tplCloseModal();

                    var alertMsg = '📋 Template applied successfully!';
                    if (data.attached_count > 0) {
                        alertMsg += '\n📎 ' + data.attached_count + ' attachment(s) from template were attached to this email!';
                        alert(alertMsg);
                        // Submit form to refresh attachments in SquirrelMail
                        var attachForm = document.querySelector('form[name="composeForm"]');
                        if (attachForm) {
                            var hiddenAction = document.createElement('input');
                            hiddenAction.type = 'hidden';
                            hiddenAction.name = 'attach';
                            hiddenAction.value = '1';
                            attachForm.appendChild(hiddenAction);
                            attachForm.submit();
                        }
                    } else {
                        alert(alertMsg);
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to apply template'));
                }
            })
            .catch(err => {
                alert('Request failed: ' + err);
            });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    })();
    </script>
    <?php
    $output = ob_get_clean();
    return array(
        'compose_bottom'     => $output,
        'compose_form_close' => $output
    );
}

function tpl_optpage_register_block()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("Email Templates &amp; Attachments"),
        'url'  => SM_PATH . 'plugins/templates/templates_manager.php',
        'desc' => _("Create and manage reply templates with attached files (brochures, price lists, forms) for instant compose insertion."),
        'js'   => false
    );
}
