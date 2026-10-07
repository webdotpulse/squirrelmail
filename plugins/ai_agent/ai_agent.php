<?php
/**
 * AI Agent & Assistant Plugin - Main implementation
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

include_once(SM_PATH . 'plugins/ai_agent/config.php');

/**
 * Hook: Add "✨ AI Assistant" button to the compose button row
 */
function ai_agent_compose_buttons_do()
{
    global $ai_enable_compose;
    if (!$ai_enable_compose) return array();

    $html = '<button type="button" id="btn-ai-compose-open" class="btn btn-secondary ai-compose-trigger" '
          . 'onclick="if(window.aiAgentOpenModal) window.aiAgentOpenModal();" '
          . 'title="' . _("Open Gemini 3.8 AI Email Assistant") . '" '
          . 'style="margin-left: 6px; padding: 4px 10px; font-size: 12px; font-weight: 600; cursor: pointer; border-radius: 4px; border: 1px solid #c2e7ff; background: linear-gradient(135deg, #e8f0fe 0%, #f3e8fd 100%); color: #0b57d0; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">'
          . '<span>✨</span> <span>' . _("AI Assistant") . '</span>'
          . '</button>';

    return array('compose_button_row' => $html);
}

/**
 * Hook: Injects AI Assistant Modal, styles, and scripts into compose form bottom
 */
function ai_agent_compose_close_do()
{
    global $ai_enable_compose, $body, $subject, $action;
    if (!$ai_enable_compose) return array();

    $is_reply = ($action === 'reply' || $action === 'reply_all');
    ob_start();
    ?>
    <style>
        .ai-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(32, 33, 36, 0.6);
            z-index: 100000;
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
        }
        .ai-modal-box {
            background: #ffffff;
            width: 90%;
            max-width: 640px;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            animation: aiModalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes aiModalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .ai-modal-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #1a73e8 0%, #681da8 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ai-modal-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ai-modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #ffffff;
            font-size: 18px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .ai-modal-close:hover {
            background: rgba(255, 255, 255, 0.35);
        }
        .ai-modal-tabs {
            display: flex;
            background: #f1f3f4;
            border-bottom: 1px solid #dadce0;
        }
        .ai-tab-btn {
            flex: 1;
            padding: 10px 12px;
            border: none;
            background: none;
            font-size: 13px;
            font-weight: 500;
            color: #5f6368;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
            border-bottom: 2px solid transparent;
        }
        .ai-tab-btn.active {
            color: #1a73e8;
            background: #ffffff;
            border-bottom: 2px solid #1a73e8;
            font-weight: 600;
        }
        .ai-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        .ai-field-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #3c4043;
            margin-bottom: 6px;
        }
        .ai-textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #dadce0;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
            outline: none;
        }
        .ai-textarea:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        .ai-chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 8px 0 14px 0;
        }
        .ai-chip {
            background: #f1f3f4;
            border: 1px solid #dadce0;
            border-radius: 16px;
            padding: 4px 10px;
            font-size: 12px;
            color: #3c4043;
            cursor: pointer;
            transition: all 0.15s;
        }
        .ai-chip:hover {
            background: #e8f0fe;
            color: #1a73e8;
            border-color: #aecbfa;
        }
        .ai-output-box {
            margin-top: 14px;
            padding: 12px 14px;
            background: #f8fafd;
            border: 1px solid #dadce0;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.5;
            color: #1f1f1f;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            white-space: pre-wrap;
        }
        .ai-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: aiSpin 0.7s linear infinite;
            margin-right: 6px;
        }
        @keyframes aiSpin {
            to { transform: rotate(360deg); }
        }
        .ai-modal-footer {
            padding: 12px 20px;
            background: #f8fafd;
            border-top: 1px solid #ebebeb;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
    </style>

    <!-- AI Assistant Modal Dialog -->
    <div id="ai-agent-modal" class="ai-modal-backdrop">
        <div class="ai-modal-box">
            <div class="ai-modal-header">
                <h3><span>✨</span> Gemini 3.8 Email Assistant</h3>
                <button type="button" class="ai-modal-close" onclick="aiAgentCloseModal();">&times;</button>
            </div>

            <div class="ai-modal-tabs">
                <button type="button" class="ai-tab-btn active" id="tab-btn-compose" onclick="aiAgentSwitchTab('compose')">Draft with AI</button>
                <button type="button" class="ai-tab-btn" id="tab-btn-reply" onclick="aiAgentSwitchTab('reply')">Smart Reply</button>
                <button type="button" class="ai-tab-btn" id="tab-btn-rephrase" onclick="aiAgentSwitchTab('rephrase')">Rephrase &amp; Polish</button>
            </div>

            <div class="ai-modal-body">
                <!-- Tab 1: Draft Email -->
                <div id="ai-panel-compose">
                    <label class="ai-field-label" for="ai-compose-prompt">What should this email be about?</label>
                    <textarea id="ai-compose-prompt" class="ai-textarea" rows="3" placeholder="e.g. Write an email to the team announcing the new quarterly roadmap, emphasizing deadlines and client deliverables."></textarea>
                    
                    <div style="margin-top: 10px; display: flex; gap: 12px; align-items: center;">
                        <div>
                            <span class="ai-field-label" style="margin-bottom: 2px;">Tone:</span>
                            <select id="ai-compose-tone" style="padding: 6px 10px; border: 1px solid #dadce0; border-radius: 4px; font-size: 12px;">
                                <option value="professional" selected>Professional &amp; Courteous</option>
                                <option value="friendly">Warm &amp; Friendly</option>
                                <option value="concise">Concise &amp; Direct</option>
                                <option value="persuasive">Persuasive &amp; Inspiring</option>
                                <option value="formal">Strictly Formal</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <span class="ai-field-label" style="margin-bottom: 2px;">Quick Prompts:</span>
                            <div class="ai-chip-group" style="margin: 0;">
                                <span class="ai-chip" onclick="aiAgentSetPrompt('Schedule a project check-in meeting for next week with proposed times.');">Schedule meeting</span>
                                <span class="ai-chip" onclick="aiAgentSetPrompt('Polite follow-up inquiring on the status of my previous inquiry.');">Follow-up</span>
                                <span class="ai-chip" onclick="aiAgentSetPrompt('Thank the client for their business and provide next onboarding steps.');">Client onboarding</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Smart Reply -->
                <div id="ai-panel-reply" style="display: none;">
                    <label class="ai-field-label">Choose Reply Direction:</label>
                    <div class="ai-chip-group">
                        <span class="ai-chip" onclick="aiAgentSetReplyIntent('Acknowledge and agree to the proposal or request.');">✓ Agree / Confirm</span>
                        <span class="ai-chip" onclick="aiAgentSetReplyIntent('Politely decline while explaining current scheduling constraints.');">✗ Decline Politely</span>
                        <span class="ai-chip" onclick="aiAgentSetReplyIntent('Request more details, timeline clarification, and budget overview.');">❓ Request More Info</span>
                        <span class="ai-chip" onclick="aiAgentSetReplyIntent('Confirm receipt and let them know I will review thoroughly by tomorrow.');">⏳ Received &amp; Reviewing</span>
                    </div>

                    <label class="ai-field-label" for="ai-reply-intent">Custom Reply Instructions (optional):</label>
                    <textarea id="ai-reply-intent" class="ai-textarea" rows="2" placeholder="e.g. Tell them Thursday at 2pm works, and ask if we need to prepare slides."></textarea>
                </div>

                <!-- Tab 3: Rephrase & Polish -->
                <div id="ai-panel-rephrase" style="display: none;">
                    <label class="ai-field-label">How would you like to improve your drafted text?</label>
                    <div class="ai-chip-group">
                        <span class="ai-chip" onclick="aiAgentRephrase('Fix all grammar, typos, and improve flow while keeping original tone.');">🧹 Fix Grammar &amp; Flow</span>
                        <span class="ai-chip" onclick="aiAgentRephrase('Make this email sound more executive, professional, and confident.');">👔 More Professional</span>
                        <span class="ai-chip" onclick="aiAgentRephrase('Make this email shorter, punchier, and remove fluff.');">✂️ Shorten &amp; Direct</span>
                        <span class="ai-chip" onclick="aiAgentRephrase('Make the tone warm, friendly, and approachable.');">😊 Warmer &amp; Friendly</span>
                    </div>
                </div>

                <!-- Generated Output Preview Area -->
                <div id="ai-output-container" class="ai-output-box">
                    <div style="font-weight: 600; color: #1a73e8; margin-bottom: 4px;" id="ai-output-subject"></div>
                    <div id="ai-output-body"></div>
                </div>
            </div>

            <div class="ai-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="aiAgentCloseModal();" style="padding: 8px 14px; border: 1px solid #dadce0; border-radius: 4px; background: #fff; cursor: pointer;">Cancel</button>
                <button type="button" id="btn-ai-generate" onclick="aiAgentExecute();" style="padding: 8px 16px; border: none; border-radius: 4px; background: #1a73e8; color: #fff; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center;">
                    <span id="ai-gen-spinner" class="ai-spinner" style="display: none;"></span>
                    <span id="ai-gen-btn-label">✨ Generate</span>
                </button>
                <button type="button" id="btn-ai-insert" onclick="aiAgentInsertOutput();" style="display: none; padding: 8px 16px; border: none; border-radius: 4px; background: #137333; color: #fff; font-weight: 600; cursor: pointer;">
                    ✓ Insert into Email
                </button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        let currentTab = '<?php echo ($is_reply ? "reply" : "compose"); ?>';
        let generatedSubject = '';
        let generatedBody = '';

        window.aiAgentOpenModal = function() {
            document.getElementById('ai-agent-modal').style.display = 'flex';
            aiAgentSwitchTab(currentTab);
        };

        window.aiAgentCloseModal = function() {
            document.getElementById('ai-agent-modal').style.display = 'none';
        };

        window.aiAgentSwitchTab = function(tab) {
            currentTab = tab;
            ['compose', 'reply', 'rephrase'].forEach(t => {
                const panel = document.getElementById('ai-panel-' + t);
                const btn = document.getElementById('tab-btn-' + t);
                if (panel) panel.style.display = (t === tab) ? 'block' : 'none';
                if (btn) {
                    if (t === tab) btn.classList.add('active');
                    else btn.classList.remove('active');
                }
            });
        };

        window.aiAgentSetPrompt = function(text) {
            const p = document.getElementById('ai-compose-prompt');
            if (p) { p.value = text; p.focus(); }
        };

        window.aiAgentSetReplyIntent = function(text) {
            const r = document.getElementById('ai-reply-intent');
            if (r) { r.value = text; r.focus(); }
        };

        window.aiAgentRephrase = function(instruction) {
            window.aiAgentExecuteRephrase(instruction);
        };

        window.aiAgentExecute = function() {
            if (currentTab === 'compose') {
                aiAgentExecuteCompose();
            } else if (currentTab === 'reply') {
                aiAgentExecuteReply();
            }
        };

        function getCurrentEditorContent() {
            const wysiwyg = document.getElementById('html-mail-wysiwyg');
            const rawTextarea = document.getElementById('body');
            if (wysiwyg && wysiwyg.offsetParent !== null) {
                return wysiwyg.innerText || wysiwyg.textContent || '';
            }
            return rawTextarea ? rawTextarea.value : '';
        }

        function setGeneratingState(isGen) {
            const spinner = document.getElementById('ai-gen-spinner');
            const label = document.getElementById('ai-gen-btn-label');
            const genBtn = document.getElementById('btn-ai-generate');
            if (isGen) {
                if (spinner) spinner.style.display = 'inline-block';
                if (label) label.textContent = 'Generating with Gemini 3.8...';
                if (genBtn) genBtn.disabled = true;
            } else {
                if (spinner) spinner.style.display = 'none';
                if (label) label.textContent = '✨ Regenerate';
                if (genBtn) genBtn.disabled = false;
            }
        }

        function displayAiOutput(subj, body) {
            generatedSubject = subj || '';
            generatedBody = body || '';
            const outBox = document.getElementById('ai-output-container');
            const outSubj = document.getElementById('ai-output-subject');
            const outBody = document.getElementById('ai-output-body');
            const insertBtn = document.getElementById('btn-ai-insert');

            if (outSubj) {
                outSubj.textContent = generatedSubject ? 'Subject: ' + generatedSubject : '';
                outSubj.style.display = generatedSubject ? 'block' : 'none';
            }
            if (outBody) outBody.textContent = generatedBody;
            if (outBox) outBox.style.display = 'block';
            if (insertBtn) insertBtn.style.display = 'inline-block';
        }

        function aiAgentExecuteCompose() {
            const prompt = document.getElementById('ai-compose-prompt').value.trim();
            const tone = document.getElementById('ai-compose-tone').value;
            if (!prompt) {
                alert('Please enter instructions for the email.');
                return;
            }

            setGeneratingState(true);
            const fd = new FormData();
            fd.append('action', 'compose');
            fd.append('prompt', prompt);
            fd.append('tone', tone);

            fetch('<?php echo SM_PATH; ?>plugins/ai_agent/ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    setGeneratingState(false);
                    if (data.success && data.data) {
                        displayAiOutput(data.data.subject, data.data.body);
                    } else {
                        alert('AI Error: ' + (data.error || 'Failed to generate email.'));
                    }
                })
                .catch(err => {
                    setGeneratingState(false);
                    alert('Network error communicating with AI agent: ' + err);
                });
        }

        function aiAgentExecuteReply() {
            const originalContent = getCurrentEditorContent();
            const intent = document.getElementById('ai-reply-intent').value.trim() || 'Acknowledge and reply politely';
            const subjectInput = document.querySelector('input[name="subject"]');
            const subj = subjectInput ? subjectInput.value : '';

            setGeneratingState(true);
            const fd = new FormData();
            fd.append('action', 'reply');
            fd.append('subject', subj);
            fd.append('body', originalContent);
            fd.append('intent', intent);

            fetch('<?php echo SM_PATH; ?>plugins/ai_agent/ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    setGeneratingState(false);
                    if (data.success && data.data) {
                        displayAiOutput(data.data.subject, data.data.body);
                    } else {
                        alert('AI Error: ' + (data.error || 'Failed to generate reply.'));
                    }
                })
                .catch(err => {
                    setGeneratingState(false);
                    alert('Network error: ' + err);
                });
        }

        window.aiAgentExecuteRephrase = function(instruction) {
            const currentContent = getCurrentEditorContent();
            if (!currentContent.trim()) {
                alert('Your compose email is empty. Type some content first or use "Draft with AI".');
                return;
            }

            setGeneratingState(true);
            const fd = new FormData();
            fd.append('action', 'compose');
            fd.append('prompt', instruction);
            fd.append('context', currentContent);
            fd.append('tone', 'professional');

            fetch('<?php echo SM_PATH; ?>plugins/ai_agent/ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    setGeneratingState(false);
                    if (data.success && data.data) {
                        displayAiOutput('', data.data.body);
                    } else {
                        alert('AI Error: ' + (data.error || 'Failed to rephrase.'));
                    }
                })
                .catch(err => {
                    setGeneratingState(false);
                    alert('Network error: ' + err);
                });
        };

        window.aiAgentInsertOutput = function() {
            if (!generatedBody) return;

            // Set Subject if present and original was empty or new draft
            const subjectInput = document.querySelector('input[name="subject"]');
            if (subjectInput && generatedSubject && (!subjectInput.value || subjectInput.value.indexOf('Re:') !== 0)) {
                subjectInput.value = generatedSubject;
            }

            // Set Body (both HTML editor if active and textarea)
            const wysiwyg = document.getElementById('html-mail-wysiwyg');
            const rawTextarea = document.getElementById('body');

            if (wysiwyg) {
                const formatted = generatedBody.split("\n\n").map(p => '<p>' + p.replace(/\n/g, '<br>') + '</p>').join('');
                wysiwyg.innerHTML = formatted;
                if (window.htmlMailUpdateStats) window.htmlMailUpdateStats();
            }

            if (rawTextarea) {
                rawTextarea.value = generatedBody;
            }

            aiAgentCloseModal();
        };
    })();
    </script>
    <?php
    $output = ob_get_clean();
    return array('compose_bottom' => $output);
}

/**
 * Hook: Add "✨ AI Actions" menu to the message read toolbar
 */
function ai_agent_read_toolbar_do(&$links)
{
    global $ai_enable_summarize, $ai_enable_translate, $ai_enable_scam_check;

    $links[] = array(
        'URL'  => 'javascript:void(window.aiAgentSummarize?window.aiAgentSummarize():0);',
        'Text' => '✨ AI Summarize'
    );

    if ($ai_enable_scam_check) {
        $links[] = array(
            'URL'  => 'javascript:void(window.aiAgentScamCheck?window.aiAgentScamCheck():0);',
            'Text' => '🛡️ Scam Check'
        );
    }

    if ($ai_enable_translate) {
        $links[] = array(
            'URL'  => 'javascript:void(window.aiAgentTranslatePrompt?window.aiAgentTranslatePrompt():0);',
            'Text' => '🌐 Translate'
        );
    }
}

/**
 * Hook: Injects AI results container & scripts at the top of the message display
 */
function ai_agent_read_top_do()
{
    ?>
    <style>
        .ai-read-card {
            margin: 12px 14px;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: var(--sm-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: none;
            position: relative;
            animation: aiCardSlide 0.25s ease-out;
        }
        @keyframes aiCardSlide {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ai-summary-card {
            background: linear-gradient(135deg, #eef7ff 0%, #f4f0ff 100%);
            border: 1px solid #c2e7ff;
            color: #1f1f1f;
        }
        .ai-scam-card-safe {
            background: #e6f4ea;
            border: 1px solid #ceead6;
            color: #137333;
        }
        .ai-scam-card-warning {
            background: #fef7e0;
            border: 1px solid #feefc3;
            color: #b06000;
        }
        .ai-scam-card-danger {
            background: #fce8e6;
            border: 1px solid #fad2cf;
            color: #c5221f;
        }
        .ai-read-close {
            position: absolute;
            top: 10px;
            right: 12px;
            border: none;
            background: none;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            opacity: 0.6;
            color: inherit;
        }
        .ai-read-close:hover { opacity: 1; }

        [data-theme="dark"] .ai-summary-card {
            background: linear-gradient(135deg, #1e2638 0%, #201a35 100%);
            border-color: #3b82f6;
            color: #f1f5f9;
        }
        [data-theme="dark"] .ai-summary-card span[style*="color:#0b57d0"] {
            color: #93c5fd !important;
        }
        [data-theme="dark"] .ai-summary-card div[style*="color:#0b57d0"] {
            color: #93c5fd !important;
        }
        [data-theme="dark"] .ai-scam-card-safe {
            background: #132e1f;
            border-color: #15803d;
            color: #86efac;
        }
        [data-theme="dark"] .ai-scam-card-warning {
            background: #382c13;
            border-color: #a16207;
            color: #fde047;
        }
        [data-theme="dark"] .ai-scam-card-danger {
            background: #3b1818;
            border-color: #b91c1c;
            color: #fca5a5;
        }
    </style>

    <div id="ai-read-banner" class="ai-read-card" style="display: none;">
        <button type="button" class="ai-read-close" onclick="document.getElementById('ai-read-banner').style.display='none';" aria-label="Close">&times;</button>
        <div id="ai-read-banner-content"></div>
    </div>

    <script>
    (function() {
        function getEmailBodyText() {
            // 1. Inspect open Shadow DOM host if present
            const shadowHost = document.getElementById('sm-email-shadow-host') || document.querySelector('.sm-email-shadow-container');
            if (shadowHost && shadowHost.shadowRoot) {
                const tempDiv = document.createElement('div');
                try {
                    tempDiv.innerHTML = shadowHost.shadowRoot.innerHTML || '';
                } catch (e) {
                    Array.from(shadowHost.shadowRoot.childNodes).forEach(child => {
                        try {
                            tempDiv.appendChild(child.cloneNode(true));
                        } catch (err) {}
                    });
                }
                const unwanted = tempDiv.querySelectorAll('style, script, noscript');
                unwanted.forEach(el => el.remove());
                const text = (tempDiv.innerText || tempDiv.textContent || '').trim();
                if (text) return text;
            }

            // 2. Inspect raw template if Shadow DOM not yet mounted
            const rawTemplate = document.querySelector('.sm-email-raw-template');
            if (rawTemplate) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = rawTemplate.innerHTML || rawTemplate.textContent || '';
                const unwanted = tempDiv.querySelectorAll('style, script, noscript');
                unwanted.forEach(el => el.remove());
                const text = (tempDiv.innerText || tempDiv.textContent || '').trim();
                if (text) return text;
            }

            // 3. Inspect standard read-body containers
            const containers = document.querySelectorAll('.sm-read-body-wrapper, div.readBody, .readBody, .message-body, pre, td.readBody');
            let content = '';
            for (let el of containers) {
                try {
                    const clone = el.cloneNode(true);
                    const unwanted = clone.querySelectorAll('style, script, noscript, #ai-read-banner, .ai-read-card');
                    unwanted.forEach(e => e.remove());
                    const t = (clone.innerText || clone.textContent || '').trim();
                    if (t.length > content.length) {
                        content = t;
                    }
                } catch (e) {}
            }
            if (content) return content;

            // 4. Fallback: inspect pre tags in workspace
            const workspace = document.getElementById('sm-workspace');
            if (workspace) {
                const pre = workspace.querySelector('pre');
                if (pre) return (pre.innerText || pre.textContent || '').trim();
            }

            return '';
        }

        function getEmailSubject() {
            const h = document.querySelector('.sm-email-header-row.field_Subject .fieldValue, .field_Subject .fieldValue, td.readHeaderValue, h2, title');
            return h ? (h.innerText || h.textContent || '').trim() : '';
        }

        function getEmailFrom() {
            const f = document.querySelector('.sm-email-header-row.field_From .fieldValue, .field_From .fieldValue');
            return f ? (f.innerText || f.textContent || '').trim() : '';
        }

        function getEmailHeaders() {
            const rows = document.querySelectorAll('.sm-email-header-row');
            const lines = [];
            rows.forEach(r => {
                const name = r.querySelector('.fieldName');
                const val = r.querySelector('.fieldValue');
                if (name && val) {
                    lines.push(name.textContent.trim() + ' ' + val.textContent.trim());
                }
            });
            return lines.join('\n');
        }

        function showBanner(html, cardClass) {
            const b = document.getElementById('ai-read-banner');
            const c = document.getElementById('ai-read-banner-content');
            if (!b || !c) return;
            b.className = 'ai-read-card ' + cardClass;
            c.innerHTML = html;
            b.style.display = 'block';
            b.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        const ajaxEndpoint = '<?php echo sqm_baseuri(); ?>plugins/ai_agent/ajax.php';

        window.aiAgentSummarize = function() {
            const body = getEmailBodyText();
            const subject = getEmailSubject();
            const from = getEmailFrom();

            if (!body) {
                showBanner('⚠️ Could not extract email body to summarize. Please ensure the message content has loaded.', 'ai-scam-card-warning');
                return;
            }

            showBanner('<div style="display:flex;align-items:center;gap:8px;"><span>⏳</span> <em>Analyzing email with Gemini 3.8...</em></div>', 'ai-summary-card');

            const fd = new FormData();
            fd.append('action', 'summarize');
            fd.append('subject', subject);
            fd.append('from', from);
            fd.append('body', body);

            fetch(ajaxEndpoint, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const d = data.data;
                        let out = '<div style="font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;font-size:14px;color:#0b57d0;">'
                                + '<span>✨</span> Gemini 3.8 Executive Summary '
                                + '<span style="font-size:11px;padding:2px 8px;border-radius:12px;background:#c2e7ff;color:#001d35;font-weight:600;">' + (d.urgency || 'Normal') + ' Priority</span>'
                                + '</div>'
                                + '<div style="margin-bottom:8px;font-size:13px;line-height:1.5;">' + d.tldr + '</div>';

                        if (d.action_items && d.action_items.length > 0) {
                            out += '<div style="font-weight:600;font-size:12px;margin-top:8px;color:#3c4043;">Action Items:</div><ul style="margin:4px 0 6px 18px;padding:0;font-size:13px;">';
                            d.action_items.forEach(item => { out += '<li>' + item + '</li>'; });
                            out += '</ul>';
                        }

                        if (d.key_dates && d.key_dates.length > 0) {
                            out += '<div style="font-weight:600;font-size:12px;margin-top:6px;color:#3c4043;">Deadlines &amp; Dates:</div><ul style="margin:4px 0 6px 18px;padding:0;font-size:13px;">';
                            d.key_dates.forEach(date => { out += '<li>' + date + '</li>'; });
                            out += '</ul>';
                        }

                        showBanner(out, 'ai-summary-card');
                    } else {
                        let err = data.error || 'Failed to summarize.';
                        let errHtml = '❌ AI Error: ' + err;
                        if (err.indexOf('Gemini API key is not configured') !== -1 || err.indexOf('API key') !== -1) {
                            errHtml += '<div style="margin-top:8px;"><a href="<?php echo sqm_baseuri(); ?>plugins/ai_agent/options.php" class="sm-btn sm-btn-primary sm-btn-sm" style="display:inline-block; text-decoration:none;">⚙️ Set Gemini API Key in Options</a></div>';
                        }
                        showBanner(errHtml, 'ai-scam-card-danger');
                    }
                })
                .catch(err => {
                    showBanner('❌ Network error: ' + err, 'ai-scam-card-danger');
                });
        };

        window.aiAgentScamCheck = function() {
            const body = getEmailBodyText();
            const subject = getEmailSubject();
            const from = getEmailFrom();
            const headers = getEmailHeaders();

            showBanner('<div style="display:flex;align-items:center;gap:8px;"><span>🛡️</span> <em>Scanning headers and content for phishing, impersonation, and scams...</em></div>', 'ai-summary-card');

            const fd = new FormData();
            fd.append('action', 'scam_check');
            fd.append('subject', subject);
            fd.append('sender', from);
            fd.append('headers', headers);
            fd.append('body', body);

            fetch(ajaxEndpoint, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const d = data.data;
                        let cardClass = 'ai-scam-card-safe';
                        let icon = '🛡️';

                        if (d.risk_score >= 60 || (d.risk_level && d.risk_level.toLowerCase().indexOf('danger') !== -1)) {
                            cardClass = 'ai-scam-card-danger';
                            icon = '🚨';
                        } else if (d.risk_score >= 30 || (d.risk_level && d.risk_level.toLowerCase().indexOf('suspicious') !== -1)) {
                            cardClass = 'ai-scam-card-warning';
                            icon = '⚠️';
                        }

                        let out = '<div style="font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;font-size:14px;">'
                                + '<span>' + icon + '</span> Security Check: ' + d.risk_level + ' (' + d.risk_score + '/100 Risk Score)'
                                + '</div>'
                                + '<div style="margin-bottom:6px;font-size:13px;"><strong>Verdict:</strong> ' + d.verdict + '</div>';

                        if (d.red_flags && d.red_flags.length > 0) {
                            out += '<div style="font-weight:600;font-size:12px;margin-top:6px;">Warning Flags Detected:</div><ul style="margin:4px 0 6px 18px;padding:0;font-size:13px;">';
                            d.red_flags.forEach(rf => { out += '<li>' + rf + '</li>'; });
                            out += '</ul>';
                        }

                        if (d.recommendation) {
                            out += '<div style="font-size:12px;margin-top:6px;opacity:0.9;"><strong>Advice:</strong> ' + d.recommendation + '</div>';
                        }

                        showBanner(out, cardClass);
                    } else {
                        let err = data.error || 'Failed to complete security check.';
                        let errHtml = '❌ Scan Error: ' + err;
                        if (err.indexOf('Gemini API key is not configured') !== -1 || err.indexOf('API key') !== -1) {
                            errHtml += '<div style="margin-top:8px;"><a href="<?php echo sqm_baseuri(); ?>plugins/ai_agent/options.php" class="sm-btn sm-btn-primary sm-btn-sm" style="display:inline-block; text-decoration:none;">⚙️ Set Gemini API Key in Options</a></div>';
                        }
                        showBanner(errHtml, 'ai-scam-card-danger');
                    }
                })
                .catch(err => {
                    showBanner('❌ Network error: ' + err, 'ai-scam-card-danger');
                });
        };

        window.aiAgentTranslatePrompt = function() {
            const lang = prompt("Enter target language for translation (e.g. Dutch, English, French, German, Spanish):", "Dutch");
            if (!lang) return;

            const body = getEmailBodyText();
            if (!body) {
                showBanner('⚠️ Could not extract email body to translate.', 'ai-scam-card-warning');
                return;
            }

            showBanner('<div style="display:flex;align-items:center;gap:8px;"><span>🌐</span> <em>Translating email to ' + lang + ' with Gemini 3.8...</em></div>', 'ai-summary-card');

            const fd = new FormData();
            fd.append('action', 'translate');
            fd.append('body', body);
            fd.append('target_lang', lang);

            fetch(ajaxEndpoint, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.translation) {
                        const out = '<div style="font-weight:700;margin-bottom:6px;font-size:14px;color:#0b57d0;">'
                                  + '<span>🌐</span> Translated to ' + lang
                                  + '</div>'
                                  + '<div style="white-space:pre-wrap;font-size:13px;line-height:1.5;background:var(--sm-bg-surface,#ffffff);color:var(--sm-text-primary,#1e293b);padding:12px;border-radius:6px;border:1px solid var(--sm-border,#dadce0);">'
                                  + data.translation
                                  + '</div>';
                        showBanner(out, 'ai-summary-card');
                    } else {
                        showBanner('❌ Translation Error: ' + (data.error || 'Failed to translate.'), 'ai-scam-card-danger');
                    }
                })
                .catch(err => {
                    showBanner('❌ Network error: ' + err, 'ai-scam-card-danger');
                });
        };

        // Event delegation listener for any AI Summarize buttons
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.sm-btn-ai-summarize, [data-ai-action="summarize"]');
            if (btn) {
                e.preventDefault();
                window.aiAgentSummarize();
            }
        });
    })();
    </script>
    <?php
}

/**
 * Hook: Register AI Agent Options Block in SquirrelMail Options
 */
function ai_agent_optpage_register_block_do()
{
    global $optpage_blocks;

    $optpage_blocks[] = array(
        'name' => _("AI Agent & Assistant (Gemini 3.8)"),
        'url'  => SM_PATH . 'plugins/ai_agent/options.php',
        'desc' => _("Configure your Google Gemini API key, AI model, automated spam filter, categorization rules, and server-side background cron jobs."),
        'js'   => false
    );
}
