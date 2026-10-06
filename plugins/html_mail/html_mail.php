<?php
/**
 * HTML Mail Plugin - Main implementation
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage html_mail
 */

/**
 * Hook: Add format toggle button to compose buttons row
 */
function html_mail_compose_buttons_do()
{
    global $data_dir, $username;
    $default_mode = getPref($data_dir, $username, 'html_mail_default', '1');

    $html = '<button type="button" id="html-mail-toggle-btn" class="btn btn-secondary html-mail-mode-btn" '
          . 'onclick="if(window.htmlMailToggleMode) window.htmlMailToggleMode();" '
          . 'title="' . _("Toggle between Rich HTML and Plain Text compose mode") . '" '
          . 'style="margin-left: 6px; padding: 4px 10px; font-size: 12px; font-weight: 500; cursor: pointer; border-radius: 4px; border: 1px solid #c4c7c5; background: #f0f4f9; color: #1f1f1f; display: inline-flex; align-items: center; gap: 4px;">'
          . '<span id="html-mail-btn-icon">🎨</span> <span id="html-mail-btn-text">' . ($default_mode === '1' ? _("Mode: Rich HTML") : _("Mode: Plain Text")) . '</span>'
          . '</button>';

    return array('compose_button_row' => $html);
}

/**
 * Hook: Injects editor styling, toolbar, and container into the compose form
 */
function html_mail_compose_form_do()
{
    global $data_dir, $username, $editor_height, $editor_width;

    $default_mode = getPref($data_dir, $username, 'html_mail_default', '1');
    $default_font = getPref($data_dir, $username, 'html_mail_font', 'sans-serif');
    $default_size = getPref($data_dir, $username, 'html_mail_size', '14px');

    ?>
    <style>
        .html-mail-wrapper {
            margin: 8px 0;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            font-family: <?php echo htmlspecialchars($default_font); ?>, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .html-mail-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            background: #f8fafd;
            border: 1px solid #dadce0;
            border-bottom: none;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .html-mail-toolbar .tb-group {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            border-right: 1px solid #e0e2e5;
            padding-right: 6px;
            margin-right: 4px;
        }
        .html-mail-toolbar .tb-group:last-child {
            border-right: none;
        }
        .html-mail-toolbar button, .html-mail-toolbar select {
            background: #ffffff;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 5px 8px;
            font-size: 13px;
            cursor: pointer;
            color: #3c4043;
            transition: all 0.15s ease;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .html-mail-toolbar button:hover, .html-mail-toolbar select:hover {
            background: #e8f0fe;
            color: #1a73e8;
            border-color: #aecbfa;
        }
        .html-mail-toolbar button.active {
            background: #c2e7ff;
            color: #001d35;
            border-color: #7fcfff;
        }
        .html-mail-color-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .html-mail-color-wrap input[type="color"] {
            opacity: 0;
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .html-mail-editor-container {
            position: relative;
            border: 1px solid #dadce0;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        #html-mail-wysiwyg {
            min-height: 280px;
            padding: 16px;
            outline: none;
            overflow-y: auto;
            color: #1f1f1f;
            line-height: 1.5;
            font-size: <?php echo htmlspecialchars($default_size); ?>;
        }
        #html-mail-wysiwyg:focus {
            box-shadow: inset 0 0 0 1px #1a73e8;
        }
        #html-mail-source {
            width: 100%;
            min-height: 280px;
            padding: 14px;
            box-sizing: border-box;
            border: none;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            background: #202124;
            color: #e8eaed;
            outline: none;
            display: none;
            resize: vertical;
        }
        .html-mail-status {
            display: flex;
            justify-content: space-between;
            padding: 4px 12px;
            font-size: 11px;
            color: #70757a;
            background: #f8fafd;
            border-top: 1px solid #f1f3f4;
        }
    </style>

    <script>
    (function() {
        let isHtmlMode = <?php echo ($default_mode === '1' ? 'true' : 'false'); ?>;
        let isSourceMode = false;

        window.htmlMailToggleMode = function() {
            const rawTextarea = document.getElementById('body');
            const wrapper = document.getElementById('html-mail-wrapper');
            const btnText = document.getElementById('html-mail-btn-text');
            const btnIcon = document.getElementById('html-mail-btn-icon');
            const hiddenFlag = document.getElementById('html_mail_enabled');
            const wysiwyg = document.getElementById('html-mail-wysiwyg');

            isHtmlMode = !isHtmlMode;
            if (hiddenFlag) hiddenFlag.value = isHtmlMode ? '1' : '0';

            if (isHtmlMode) {
                // Switching TO HTML Mode
                if (btnText) btnText.textContent = "<?php echo _("Mode: Rich HTML"); ?>";
                if (btnIcon) btnIcon.textContent = "🎨";
                if (rawTextarea) rawTextarea.style.display = 'none';
                if (wrapper) wrapper.style.display = 'block';

                // Transfer text to HTML if wysiwyg is empty or plain
                if (wysiwyg && rawTextarea) {
                    if (!wysiwyg.innerHTML.trim() || wysiwyg.innerText.trim() === rawTextarea.value.trim()) {
                        const paras = rawTextarea.value.split("\n\n");
                        wysiwyg.innerHTML = paras.map(p => '<p>' + p.replace(/\n/g, '<br>') + '</p>').join('');
                    }
                }
            } else {
                // Switching TO Plain Text Mode
                if (btnText) btnText.textContent = "<?php echo _("Mode: Plain Text"); ?>";
                if (btnIcon) btnIcon.textContent = "📝";
                if (wrapper) wrapper.style.display = 'none';
                if (rawTextarea) {
                    rawTextarea.style.display = 'block';
                    if (wysiwyg) {
                        rawTextarea.value = wysiwyg.innerText || wysiwyg.textContent || '';
                    }
                }
            }
        };

        window.htmlMailExec = function(command, value = null) {
            document.execCommand(command, false, value);
            const wysiwyg = document.getElementById('html-mail-wysiwyg');
            if (wysiwyg) wysiwyg.focus();
            window.htmlMailUpdateStats();
        };

        window.htmlMailFormatBlock = function(tag) {
            if (!tag) return;
            document.execCommand('formatBlock', false, tag);
            const wysiwyg = document.getElementById('html-mail-wysiwyg');
            if (wysiwyg) wysiwyg.focus();
        };

        window.htmlMailInsertLink = function() {
            const url = prompt("Enter URL (e.g. https://example.com):", "https://");
            if (url && url !== "https://") {
                document.execCommand('createLink', false, url);
            }
        };

        window.htmlMailToggleSource = function() {
            const wysiwyg = document.getElementById('html-mail-wysiwyg');
            const source = document.getElementById('html-mail-source');
            const btn = document.getElementById('btn-html-source');

            isSourceMode = !isSourceMode;
            if (isSourceMode) {
                source.value = wysiwyg.innerHTML;
                wysiwyg.style.display = 'none';
                source.style.display = 'block';
                source.focus();
                if (btn) btn.classList.add('active');
            } else {
                wysiwyg.innerHTML = source.value;
                source.style.display = 'none';
                wysiwyg.style.display = 'block';
                wysiwyg.focus();
                if (btn) btn.classList.remove('active');
            }
            window.htmlMailUpdateStats();
        };

        window.htmlMailUpdateStats = function() {
            const wysiwyg = document.getElementById('html-mail-wysiwyg');
            const statWords = document.getElementById('html-mail-stat-words');
            if (!wysiwyg || !statWords) return;
            const text = (wysiwyg.innerText || wysiwyg.textContent || '').trim();
            const words = text ? text.split(/\s+/).length : 0;
            const chars = text.length;
            statWords.textContent = words + ' words, ' + chars + ' chars';
        };

        // Initialize editor upon DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            const rawTextarea = document.getElementById('body');
            if (!rawTextarea) return;

            // Create wrapper element
            const wrapper = document.createElement('div');
            wrapper.id = 'html-mail-wrapper';
            wrapper.className = 'html-mail-wrapper';

            wrapper.innerHTML = `
                <div class="html-mail-toolbar">
                    <div class="tb-group">
                        <select onchange="htmlMailFormatBlock(this.value); this.selectedIndex=0;" title="Text Style">
                            <option value="">Normal Text</option>
                            <option value="H1">Heading 1</option>
                            <option value="H2">Heading 2</option>
                            <option value="H3">Heading 3</option>
                            <option value="BLOCKQUOTE">Quote</option>
                            <option value="PRE">Code Block</option>
                        </select>
                    </div>
                    <div class="tb-group">
                        <button type="button" onclick="htmlMailExec('bold')" title="Bold (Ctrl+B)"><strong>B</strong></button>
                        <button type="button" onclick="htmlMailExec('italic')" title="Italic (Ctrl+I)"><em>I</em></button>
                        <button type="button" onclick="htmlMailExec('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
                        <button type="button" onclick="htmlMailExec('strikeThrough')" title="Strikethrough"><s>S</s></button>
                    </div>
                    <div class="tb-group">
                        <div class="html-mail-color-wrap" title="Text Color">
                            <button type="button" style="position:relative;">
                                <span>A</span><span style="font-size:10px;margin-left:2px;">▼</span>
                                <input type="color" onchange="htmlMailExec('foreColor', this.value)">
                            </button>
                        </div>
                        <div class="html-mail-color-wrap" title="Highlight Color">
                            <button type="button" style="position:relative;">
                                <span style="background:#ffeb3b;padding:0 3px;">🖍</span>
                                <input type="color" value="#ffff00" onchange="htmlMailExec('hiliteColor', this.value)">
                            </button>
                        </div>
                    </div>
                    <div class="tb-group">
                        <button type="button" onclick="htmlMailExec('insertUnorderedList')" title="Bullet List">• List</button>
                        <button type="button" onclick="htmlMailExec('insertOrderedList')" title="Numbered List">1. List</button>
                        <button type="button" onclick="htmlMailExec('indent')" title="Indent">→</button>
                        <button type="button" onclick="htmlMailExec('outdent')" title="Outdent">←</button>
                    </div>
                    <div class="tb-group">
                        <button type="button" onclick="htmlMailExec('justifyLeft')" title="Align Left">Left</button>
                        <button type="button" onclick="htmlMailExec('justifyCenter')" title="Align Center">Center</button>
                        <button type="button" onclick="htmlMailExec('justifyRight')" title="Align Right">Right</button>
                    </div>
                    <div class="tb-group">
                        <button type="button" onclick="htmlMailInsertLink()" title="Insert Link">🔗 Link</button>
                        <button type="button" onclick="htmlMailExec('unlink')" title="Remove Link">⛓️ Unlink</button>
                        <button type="button" onclick="htmlMailExec('insertHorizontalRule')" title="Divider Line">—</button>
                    </div>
                    <div class="tb-group">
                        <button type="button" id="btn-html-source" onclick="htmlMailToggleSource()" title="View HTML Source">&lt;/&gt; Source</button>
                        <button type="button" onclick="htmlMailExec('removeFormat')" title="Clear Formatting">🧹 Clean</button>
                    </div>
                </div>
                <div class="html-mail-editor-container">
                    <div id="html-mail-wysiwyg" contenteditable="true" spellcheck="true"></div>
                    <textarea id="html-mail-source" spellcheck="false"></textarea>
                    <div class="html-mail-status">
                        <span>Rich HTML Mail (RFC multipart/alternative)</span>
                        <span id="html-mail-stat-words">0 words, 0 chars</span>
                    </div>
                </div>
            `;

            // Insert editor wrapper before or replacing visual of textarea
            rawTextarea.parentNode.insertBefore(wrapper, rawTextarea);

            const wysiwyg = document.getElementById('html-mail-wysiwyg');
            const source = document.getElementById('html-mail-source');

            // Populate initial content from textarea
            if (rawTextarea.value.trim().length > 0) {
                // If it already contains HTML tags, use directly, otherwise convert newlines to paras
                if (/<[a-z][\s\S]*>/i.test(rawTextarea.value)) {
                    wysiwyg.innerHTML = rawTextarea.value;
                } else {
                    const paras = rawTextarea.value.split("\n\n");
                    wysiwyg.innerHTML = paras.map(p => '<p>' + p.replace(/\n/g, '<br>') + '</p>').join('');
                }
            }

            wysiwyg.addEventListener('input', window.htmlMailUpdateStats);
            wysiwyg.addEventListener('keyup', window.htmlMailUpdateStats);
            window.htmlMailUpdateStats();

            // Initial view mode
            if (isHtmlMode) {
                rawTextarea.style.display = 'none';
                wrapper.style.display = 'block';
            } else {
                rawTextarea.style.display = 'block';
                wrapper.style.display = 'none';
            }

            // Sync on submit
            const form = rawTextarea.closest('form');
            if (form) {
                form.addEventListener('submit', function() {
                    if (isSourceMode) {
                        wysiwyg.innerHTML = source.value;
                    }
                    const hiddenBody = document.getElementById('html_mail_body');
                    const hiddenEnabled = document.getElementById('html_mail_enabled');

                    if (isHtmlMode) {
                        const htmlContent = wysiwyg.innerHTML;
                        const textContent = wysiwyg.innerText || wysiwyg.textContent || '';
                        if (hiddenBody) hiddenBody.value = htmlContent;
                        if (hiddenEnabled) hiddenEnabled.value = '1';
                        rawTextarea.value = textContent;
                    } else {
                        if (hiddenEnabled) hiddenEnabled.value = '0';
                        if (hiddenBody) hiddenBody.value = '';
                    }
                });
            }
        });
    })();
    </script>
    <?php
}

/**
 * Hook: Add hidden inputs to form close
 */
function html_mail_compose_close_do()
{
    global $data_dir, $username;
    $default_mode = getPref($data_dir, $username, 'html_mail_default', '1');

    $html = '<input type="hidden" name="html_mail_enabled" id="html_mail_enabled" value="' . ($default_mode === '1' ? '1' : '0') . '" />' . "\n"
          . '<input type="hidden" name="html_mail_body" id="html_mail_body" value="" />' . "\n";

    return array('compose_bottom' => $html);
}

/**
 * Hook: Construct compliant multipart/alternative MIME message structure upon sending
 *
 * @param Message $composeMessage Passed by reference
 */
function html_mail_compose_send_do(&$composeMessage)
{
    global $default_charset;

    $enabled = isset($_POST['html_mail_enabled']) && $_POST['html_mail_enabled'] === '1';
    $html_body = isset($_POST['html_mail_body']) ? trim($_POST['html_mail_body']) : '';

    if (!$enabled || empty($html_body)) {
        return; // Send normal plain-text message
    }

    $charset = !empty($default_charset) ? $default_charset : 'utf-8';
    $plain_body = !empty($_POST['body']) ? $_POST['body'] : strip_tags($html_body);

    // Entity 1: Plain text alternative
    $plain_part = new Message();
    $plain_part->body_part = $plain_body;
    $plain_part_header = new MessageHeader();
    $plain_part_header->type0 = 'text';
    $plain_part_header->type1 = 'plain';
    $plain_part_header->encoding = '8bit';
    $plain_part_header->parameters['charset'] = $charset;
    $plain_part->mime_header = $plain_part_header;

    // Entity 2: Rich HTML alternative
    $html_part = new Message();
    if (stripos($html_body, '<html') === false) {
        $full_html = "<!DOCTYPE html>\n<html>\n<head>\n"
                   . "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=" . htmlspecialchars($charset) . "\">\n"
                   . "<style>\n"
                   . "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #202124; margin: 0; padding: 12px; }\n"
                   . "blockquote { border-left: 3px solid #dadce0; margin-left: 0; padding-left: 12px; color: #5f6368; }\n"
                   . "pre { background: #f1f3f4; padding: 8px 12px; border-radius: 4px; font-family: monospace; }\n"
                   . "</style>\n"
                   . "</head>\n<body>\n" . $html_body . "\n</body>\n</html>";
    } else {
        $full_html = $html_body;
    }

    $html_part->body_part = $full_html;
    $html_part_header = new MessageHeader();
    $html_part_header->type0 = 'text';
    $html_part_header->type1 = 'html';
    $html_part_header->encoding = '8bit';
    $html_part_header->parameters['charset'] = $charset;
    $html_part->mime_header = $html_part_header;

    // Build MIME hierarchy
    if (empty($composeMessage->entities)) {
        // No attachments: Top level is multipart/alternative
        $composeMessage->body_part = '';
        $composeMessage->entities = array($plain_part, $html_part);
        $composeMessage->rfc822_header->content_type = new ContentType('multipart/alternative');
    } else {
        // Has attachments: Top level is multipart/mixed containing nested multipart/alternative + attachments
        $alt_container = new Message();
        $alt_header = new MessageHeader();
        $alt_header->type0 = 'multipart';
        $alt_header->type1 = 'alternative';
        $alt_container->mime_header = $alt_header;
        $alt_container->entities = array($plain_part, $html_part);

        // First entity in SquirrelMail composeMessage was the plain text body; replace it with alt_container
        $attachments = array_slice($composeMessage->entities, 1);
        $composeMessage->body_part = '';
        $composeMessage->entities = array_merge(array($alt_container), $attachments);
        $composeMessage->rfc822_header->content_type = new ContentType('multipart/mixed');
    }
}

/**
 * Hook: Register options page under SquirrelMail Options
 */
function html_mail_optpage_register_block_do()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("HTML Mail Settings"),
        'url'  => SM_PATH . 'plugins/html_mail/options.php',
        'desc' => _("Configure rich text HTML email composer preferences, default formatting fonts, and compose modes."),
        'js'   => false
    );
}
