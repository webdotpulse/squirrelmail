<?php
/**
 * Signature Creator & Templates - Options Page
 *
 * Interactive Visual Signature Designer with responsive email templates,
 * logo embeds, color pickers, and direct SquirrelMail identity synchronization.
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage signature_creator
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'functions/identity.php');

$msg = null;
$msg_type = 'success';
$mgrUrl = sqm_baseuri() . 'plugins/signature_creator/options.php';

// Load identities
$identities = get_identities();

// Save Signature Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sig') {
    $token = isset($_POST['smtoken']) ? $_POST['smtoken'] : '';
    if (!empty($token) && function_exists('sm_validate_security_token') && !sm_validate_security_token($token, -1, false)) {
        $msg = _("Invalid security token.");
        $msg_type = 'error';
    } else {
        $identIdx = isset($_POST['identity_id']) ? trim($_POST['identity_id']) : 'g';
        $htmlSig  = isset($_POST['html_signature']) ? $_POST['html_signature'] : '';
        $plainSig = isset($_POST['plain_signature']) ? $_POST['plain_signature'] : '';

        // Save HTML signature
        setHtmlSig($data_dir, $username, $identIdx, $htmlSig);

        // Save plain text signature
        setSig($data_dir, $username, $identIdx, $plainSig);

        // Ensure use_signature is enabled
        setPref($data_dir, $username, 'use_signature', '1');

        $msg = _("Signature successfully saved and applied to your identity!");
        $msg_type = 'success';
    }
}

// Current active identity details for default field values
$defaultIdent = $identities[0] ?? array();
$defaultName  = !empty($defaultIdent['full_name']) ? $defaultIdent['full_name'] : $username;
$defaultEmail = !empty($defaultIdent['email_address']) ? $defaultIdent['email_address'] : $username;

displayPageHeader($color, 'None');
?>
<style>
.sc-container {
    max-width: 1180px;
    margin: 24px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #1e293b;
}
.sc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #cbd5e1;
}
.sc-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #0f172a;
}
.sc-grid-main {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 960px) {
    .sc-grid-main {
        grid-template-columns: 1fr;
    }
}
.sc-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 24px;
}
.sc-card-title {
    font-size: 16px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
}
.sc-form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
}
.sc-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}
.sc-input, .sc-select, .sc-textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    color: #1e293b;
    outline: none;
    transition: all 0.15s;
    font-family: inherit;
    background: #ffffff;
}
.sc-input:focus, .sc-select:focus, .sc-textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.2);
}

/* Template Choice Cards */
.sc-tpl-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}
@media (max-width: 600px) {
    .sc-tpl-grid {
        grid-template-columns: 1fr;
    }
}
.sc-tpl-choice {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.15s;
    text-align: center;
}
.sc-tpl-choice:hover {
    border-color: #93c5fd;
    background: #f8fafc;
}
.sc-tpl-choice.active {
    border-color: #2563eb;
    background: #eff6ff;
    box-shadow: 0 0 0 1px #2563eb;
}
.sc-tpl-choice-icon {
    font-size: 24px;
    margin-bottom: 4px;
}
.sc-tpl-choice-name {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}
.sc-tpl-choice-desc {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}

/* Preview Column */
.sc-preview-sticky {
    position: sticky;
    top: 20px;
}
.sc-preview-box {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 24px;
    min-height: 180px;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
    overflow-x: auto;
}
.sc-btn-primary {
    padding: 10px 22px;
    background: #2563eb;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.sc-btn-primary:hover {
    background: #1d4ed8;
}
.sc-btn-secondary {
    padding: 8px 14px;
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.sc-btn-secondary:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.sc-color-picker-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}
.sc-color-swatch {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    cursor: pointer;
    padding: 0;
}
.sc-raw-textarea {
    display: none;
    width: 100%;
    min-height: 160px;
    padding: 10px;
    box-sizing: border-box;
    font-family: monospace;
    font-size: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    margin-top: 10px;
    background: #f8fafc;
}
</style>

<div class="sc-container">
    <div class="sc-header">
        <h2>
            <span>✒️</span>
            <span><?php echo _("Signature Creator &amp; Templates"); ?></span>
        </h2>
        <a href="<?php echo sqm_baseuri(); ?>src/options.php?optpage=personal" class="sc-btn-secondary">
            ← <?php echo _("Personal Options"); ?>
        </a>
    </div>

    <?php if ($msg): ?>
    <div style="background: <?php echo ($msg_type === 'error' ? '#fce8e6' : '#e6f4ea'); ?>; color: <?php echo ($msg_type === 'error' ? '#c5221f' : '#137333'); ?>; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid <?php echo ($msg_type === 'error' ? '#fad2cf' : '#ceead6'); ?>;">
        <?php echo ($msg_type === 'error' ? '⚠️ ' : '✅ '); ?><?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <div class="sc-grid-main">
        <!-- LEFT: BUILDER & SETTINGS -->
        <div>
            <!-- TEMPLATE SELECTOR -->
            <div class="sc-card">
                <h3 class="sc-card-title">🎨 <?php echo _("Choose Signature Template"); ?></h3>
                <div class="sc-tpl-grid">
                    <div class="sc-tpl-choice active" onclick="selectTemplate('modern', this)">
                        <div class="sc-tpl-choice-icon">💼</div>
                        <div class="sc-tpl-choice-name">Modern Clean</div>
                        <div class="sc-tpl-choice-desc">Vertical accent bar &amp; neat layout</div>
                    </div>
                    <div class="sc-tpl-choice" onclick="selectTemplate('corporate', this)">
                        <div class="sc-tpl-choice-icon">🏢</div>
                        <div class="sc-tpl-choice-name">Corporate Two-Col</div>
                        <div class="sc-tpl-choice-desc">Side-by-side logo &amp; contact card</div>
                    </div>
                    <div class="sc-tpl-choice" onclick="selectTemplate('minimalist', this)">
                        <div class="sc-tpl-choice-icon">✨</div>
                        <div class="sc-tpl-choice-name">Minimalist Chic</div>
                        <div class="sc-tpl-choice-desc">Understated line divider</div>
                    </div>
                    <div class="sc-tpl-choice" onclick="selectTemplate('letterhead', this)">
                        <div class="sc-tpl-choice-icon">📜</div>
                        <div class="sc-tpl-choice-name">Executive Classic</div>
                        <div class="sc-tpl-choice-desc">Formal serif header &amp; borders</div>
                    </div>
                    <div class="sc-tpl-choice" onclick="selectTemplate('tech', this)">
                        <div class="sc-tpl-choice-icon">💻</div>
                        <div class="sc-tpl-choice-name">Tech &amp; Developer</div>
                        <div class="sc-tpl-choice-desc">Badges, GitHub &amp; key links</div>
                    </div>
                    <div class="sc-tpl-choice" onclick="selectTemplate('badge_card', this)">
                        <div class="sc-tpl-choice-icon">🪪</div>
                        <div class="sc-tpl-choice-name">Creative Card</div>
                        <div class="sc-tpl-choice-desc">Compact boxed card with CTA button</div>
                    </div>
                </div>

                <!-- ACCENT COLOR & IDENTITY -->
                <div class="sc-form-row-2">
                    <div>
                        <label class="sc-label"><?php echo _("Save for Identity"); ?></label>
                        <select id="field-identity" class="sc-select" onchange="onIdentityChange(this.value)">
                            <option value="g"><?php echo _("Default Identity"); ?> (<?php echo htmlspecialchars($defaultEmail); ?>)</option>
                            <?php foreach ($identities as $idx => $ident): ?>
                                <?php if ($idx > 0): ?>
                                    <option value="<?php echo $idx; ?>">
                                        Identity <?php echo $idx; ?>: <?php echo htmlspecialchars($ident['full_name'] ? $ident['full_name'] . ' <' . $ident['email_address'] . '>' : $ident['email_address']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="sc-label"><?php echo _("Accent Brand Color"); ?></label>
                        <div class="sc-color-picker-wrap">
                            <input type="color" id="field-color" value="#2563eb" class="sc-color-swatch" onchange="updateSignature()">
                            <input type="text" id="field-color-text" value="#2563eb" class="sc-input" style="width: 100px;" onchange="document.getElementById('field-color').value=this.value; updateSignature();">
                        </div>
                    </div>
                </div>
            </div>

            <!-- PERSONAL & CONTACT DETAILS -->
            <div class="sc-card">
                <h3 class="sc-card-title">👤 <?php echo _("Personal &amp; Company Details"); ?></h3>

                <div class="sc-form-row-2">
                    <div>
                        <label class="sc-label"><?php echo _("Full Name"); ?></label>
                        <input type="text" id="field-name" class="sc-input" value="<?php echo htmlspecialchars($defaultName); ?>" oninput="updateSignature()">
                    </div>
                    <div>
                        <label class="sc-label"><?php echo _("Job Title / Role"); ?></label>
                        <input type="text" id="field-title" class="sc-input" value="Senior Project Director" oninput="updateSignature()">
                    </div>
                </div>

                <div class="sc-form-row-2">
                    <div>
                        <label class="sc-label"><?php echo _("Company / Organization"); ?></label>
                        <input type="text" id="field-company" class="sc-input" value="Acme Global Solutions" oninput="updateSignature()">
                    </div>
                    <div>
                        <label class="sc-label"><?php echo _("Department / Division"); ?></label>
                        <input type="text" id="field-department" class="sc-input" value="Operations &amp; Tech" oninput="updateSignature()">
                    </div>
                </div>

                <div class="sc-form-row-2">
                    <div>
                        <label class="sc-label"><?php echo _("Email Address"); ?></label>
                        <input type="email" id="field-email" class="sc-input" value="<?php echo htmlspecialchars($defaultEmail); ?>" oninput="updateSignature()">
                    </div>
                    <div>
                        <label class="sc-label"><?php echo _("Phone Number"); ?></label>
                        <input type="text" id="field-phone" class="sc-input" value="+1 (555) 234-5678" oninput="updateSignature()">
                    </div>
                </div>

                <div class="sc-form-row-2">
                    <div>
                        <label class="sc-label"><?php echo _("Mobile / Direct"); ?></label>
                        <input type="text" id="field-mobile" class="sc-input" value="+1 (555) 987-6543" oninput="updateSignature()">
                    </div>
                    <div>
                        <label class="sc-label"><?php echo _("Website URL"); ?></label>
                        <input type="text" id="field-website" class="sc-input" value="https://example.com" oninput="updateSignature()">
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label class="sc-label"><?php echo _("Office / Physical Address"); ?></label>
                    <input type="text" id="field-address" class="sc-input" value="100 Innovation Way, Suite 400, New York, NY 10001" oninput="updateSignature()">
                </div>

                <div style="margin-bottom: 14px;">
                    <label class="sc-label"><?php echo _("Logo / Avatar Image URL"); ?> <small style="font-weight: normal; color: #64748b;">(Publicly accessible image URL)</small></label>
                    <input type="text" id="field-logo" class="sc-input" value="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=128&auto=format&fit=crop&q=80" oninput="updateSignature()">
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                        Quick presets:
                        <a href="javascript:void(0)" onclick="setLogo('https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=128&auto=format&fit=crop&q=80');" style="color: #2563eb; text-decoration: none; margin-right: 8px;">Avatar</a>
                        <a href="javascript:void(0)" onclick="setLogo('https://placehold.co/120x60/2563eb/ffffff?text=ACME+CORP');" style="color: #2563eb; text-decoration: none; margin-right: 8px;">Company Logo</a>
                        <a href="javascript:void(0)" onclick="setLogo('');" style="color: #64748b; text-decoration: none;">None (Text Only)</a>
                    </div>
                </div>

                <!-- SOCIAL PROFILES -->
                <div style="margin-bottom: 14px;">
                    <label class="sc-label">🌐 <?php echo _("Social Media &amp; Professional Links"); ?></label>
                    <div class="sc-form-row-2">
                        <input type="text" id="field-linkedin" class="sc-input" placeholder="LinkedIn URL (e.g. https://linkedin.com/in/username)" value="https://linkedin.com" oninput="updateSignature()">
                        <input type="text" id="field-twitter" class="sc-input" placeholder="X / Twitter URL" value="https://x.com" oninput="updateSignature()">
                    </div>
                    <div class="sc-form-row-2">
                        <input type="text" id="field-github" class="sc-input" placeholder="GitHub URL" value="https://github.com" oninput="updateSignature()">
                        <input type="text" id="field-facebook" class="sc-input" placeholder="Facebook URL" value="" oninput="updateSignature()">
                    </div>
                </div>

                <!-- DISCLAIMER / LEGAL NOTICE -->
                <div>
                    <label class="sc-label"><?php echo _("Confidentiality / Legal Disclaimer (Optional)"); ?></label>
                    <textarea id="field-disclaimer" class="sc-textarea" rows="2" placeholder="This message contains confidential information intended only for the recipient..." oninput="updateSignature()"></textarea>
                </div>
            </div>
        </div>

        <!-- RIGHT: LIVE PREVIEW & ACTIONS -->
        <div class="sc-preview-sticky">
            <div class="sc-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                    <h3 class="sc-card-title" style="margin: 0;">👁️ <?php echo _("Live Signature Preview"); ?></h3>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="sc-btn-secondary" onclick="toggleRawCode()" id="btn-toggle-raw">
                            &lt;/&gt; <?php echo _("HTML Code"); ?>
                        </button>
                    </div>
                </div>

                <div class="sc-preview-box" id="preview-container">
                    <!-- Dynamic signature injected here -->
                </div>

                <textarea id="raw-html-output" class="sc-raw-textarea" readonly></textarea>

                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                    <form method="post" id="sig-save-form" action="<?php echo htmlspecialchars($mgrUrl); ?>">
                        <input type="hidden" name="action" value="save_sig">
                        <input type="hidden" name="identity_id" id="form-save-ident" value="g">
                        <input type="hidden" name="html_signature" id="form-save-html" value="">
                        <input type="hidden" name="plain_signature" id="form-save-plain" value="">
                        <input type="hidden" name="smtoken" value="<?php echo function_exists('sm_generate_security_token') ? sm_generate_security_token() : ''; ?>">

                        <button type="submit" class="sc-btn-primary" style="width: 100%; justify-content: center; font-size: 15px; padding: 12px;">
                            💾 <?php echo _("Save &amp; Apply to Webmail Signature"); ?>
                        </button>
                    </form>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button type="button" class="sc-btn-secondary" style="justify-content: center;" onclick="copySignatureHtml()">
                            📋 <?php echo _("Copy HTML"); ?>
                        </button>
                        <button type="button" class="sc-btn-secondary" style="justify-content: center;" onclick="copySignaturePlain()">
                            📄 <?php echo _("Copy Plain Text"); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var activeTemplate = 'modern';
    var rawModeActive = false;

    window.selectTemplate = function(tplName, elem) {
        activeTemplate = tplName;
        document.querySelectorAll('.sc-tpl-choice').forEach(function(el) { el.classList.remove('active'); });
        if (elem) elem.classList.add('active');
        updateSignature();
    };

    window.setLogo = function(url) {
        document.getElementById('field-logo').value = url;
        updateSignature();
    };

    window.onIdentityChange = function(val) {
        document.getElementById('form-save-ident').value = val;
    };

    window.toggleRawCode = function() {
        var raw = document.getElementById('raw-html-output');
        var btn = document.getElementById('btn-toggle-raw');
        rawModeActive = !rawModeActive;
        if (rawModeActive) {
            raw.style.display = 'block';
            btn.textContent = '👁️ Hide Code';
        } else {
            raw.style.display = 'none';
            btn.textContent = '</> HTML Code';
        }
    };

    window.updateSignature = function() {
        var name       = document.getElementById('field-name').value.trim() || 'Your Name';
        var title      = document.getElementById('field-title').value.trim();
        var company    = document.getElementById('field-company').value.trim();
        var dept       = document.getElementById('field-department').value.trim();
        var email      = document.getElementById('field-email').value.trim();
        var phone      = document.getElementById('field-phone').value.trim();
        var mobile     = document.getElementById('field-mobile').value.trim();
        var website    = document.getElementById('field-website').value.trim();
        var address    = document.getElementById('field-address').value.trim();
        var logo       = document.getElementById('field-logo').value.trim();
        var color      = document.getElementById('field-color').value || '#2563eb';
        var linkedin   = document.getElementById('field-linkedin').value.trim();
        var twitter    = document.getElementById('field-twitter').value.trim();
        var github     = document.getElementById('field-github').value.trim();
        var facebook   = document.getElementById('field-facebook').value.trim();
        var disclaimer = document.getElementById('field-disclaimer').value.trim();

        // Update hex color text field
        document.getElementById('field-color-text').value = color;

        var html = '';
        var plain = '';

        // Generate Plain Text Fallback
        plain = name + "\n";
        if (title || company) plain += (title ? title : '') + (title && company ? ' | ' : '') + (company ? company : '') + "\n";
        if (email) plain += "Email: " + email + "\n";
        if (phone) plain += "Phone: " + phone + "\n";
        if (mobile) plain += "Mobile: " + mobile + "\n";
        if (website) plain += "Web: " + website + "\n";
        if (address) plain += "Address: " + address + "\n";
        if (disclaimer) plain += "\n" + disclaimer + "\n";

        // Social Icons Helper
        var socialHtml = '';
        var socialIcons = [];
        if (linkedin) socialIcons.push('<a href="' + escapeAttr(linkedin) + '" target="_blank" style="display:inline-block; margin-right:8px; text-decoration:none; color:' + color + '; font-size:12px; font-weight:bold;">LinkedIn</a>');
        if (twitter) socialIcons.push('<a href="' + escapeAttr(twitter) + '" target="_blank" style="display:inline-block; margin-right:8px; text-decoration:none; color:' + color + '; font-size:12px; font-weight:bold;">X/Twitter</a>');
        if (github) socialIcons.push('<a href="' + escapeAttr(github) + '" target="_blank" style="display:inline-block; margin-right:8px; text-decoration:none; color:' + color + '; font-size:12px; font-weight:bold;">GitHub</a>');
        if (facebook) socialIcons.push('<a href="' + escapeAttr(facebook) + '" target="_blank" style="display:inline-block; margin-right:8px; text-decoration:none; color:' + color + '; font-size:12px; font-weight:bold;">Facebook</a>');
        if (socialIcons.length > 0) {
            socialHtml = '<div style="margin-top:10px; font-size:12px;">' + socialIcons.join('<span style="color:#cbd5e1; margin-right:8px;">•</span>') + '</div>';
        }

        var logoImgHtml = '';
        if (logo) {
            logoImgHtml = '<img src="' + escapeAttr(logo) + '" alt="' + escapeAttr(name) + '" width="80" height="80" style="width:80px; height:80px; border-radius:50%; object-fit:cover; display:block; border:2px solid ' + color + ';" />';
        }

        // 1. TEMPLATE: MODERN CLEAN
        if (activeTemplate === 'modern') {
            html = '<table cellpadding="0" cellspacing="0" border="0" style="font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size:13px; color:#334155; line-height:1.5;">' +
                   '<tr>' +
                   '<td style="border-left:3px solid ' + color + '; padding-left:14px;">' +
                   '<div style="font-size:16px; font-weight:700; color:#0f172a; letter-spacing:-0.2px;">' + escapeHtml(name) + '</div>' +
                   '<div style="font-size:13px; color:' + color + '; font-weight:600; margin-bottom:4px;">' + escapeHtml(title) + (title && company ? ' • ' : '') + escapeHtml(company) + '</div>' +
                   '<div style="font-size:12px; color:#64748b;">' +
                   (email ? '<span>✉️ <a href="mailto:' + escapeAttr(email) + '" style="color:#334155; text-decoration:none;">' + escapeHtml(email) + '</a></span> ' : '') +
                   (phone ? '<span style="margin-left:8px;">📞 ' + escapeHtml(phone) + '</span> ' : '') +
                   (mobile ? '<span style="margin-left:8px;">📱 ' + escapeHtml(mobile) + '</span> ' : '') +
                   (website ? '<span style="margin-left:8px;">🌐 <a href="' + escapeAttr(website) + '" target="_blank" style="color:' + color + '; text-decoration:none; font-weight:600;">' + escapeHtml(website.replace(/^https?:\/\//, '')) + '</a></span>' : '') +
                   '</div>' +
                   (address ? '<div style="font-size:11px; color:#94a3b8; margin-top:3px;">📍 ' + escapeHtml(address) + '</div>' : '') +
                   socialHtml +
                   (disclaimer ? '<div style="font-size:10px; color:#94a3b8; margin-top:8px; line-height:1.3; max-width:500px; border-top:1px solid #f1f5f9; padding-top:4px;">' + escapeHtml(disclaimer) + '</div>' : '') +
                   '</td>' +
                   '</tr>' +
                   '</table>';
        }

        // 2. TEMPLATE: CORPORATE TWO-COLUMN
        else if (activeTemplate === 'corporate') {
            html = '<table cellpadding="0" cellspacing="0" border="0" style="font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; font-size:13px; color:#334155; line-height:1.4;">' +
                   '<tr>' +
                   (logo ? '<td style="vertical-align:middle; padding-right:16px;">' + logoImgHtml + '</td>' : '') +
                   (logo ? '<td style="border-right:1px solid #e2e8f0; width:1px; padding-right:16px;"></td>' : '') +
                   '<td style="vertical-align:top; ' + (logo ? 'padding-left:16px;' : '') + '">' +
                   '<div style="font-size:17px; font-weight:700; color:#0f172a;">' + escapeHtml(name) + '</div>' +
                   '<div style="font-size:13px; font-weight:600; color:' + color + '; margin-top:1px;">' + escapeHtml(title) + '</div>' +
                   '<div style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px;">' + escapeHtml(company) + (dept ? ' — ' + escapeHtml(dept) : '') + '</div>' +
                   '<div style="margin-top:6px; font-size:12px; color:#64748b;">' +
                   (email ? '<div>Email: <a href="mailto:' + escapeAttr(email) + '" style="color:' + color + '; text-decoration:none;">' + escapeHtml(email) + '</a></div>' : '') +
                   (phone ? '<div>Tel: <span style="color:#1e293b;">' + escapeHtml(phone) + '</span>' + (mobile ? ' | Mob: <span style="color:#1e293b;">' + escapeHtml(mobile) + '</span>' : '') + '</div>' : '') +
                   (website ? '<div>Web: <a href="' + escapeAttr(website) + '" target="_blank" style="color:' + color + '; text-decoration:none;">' + escapeHtml(website.replace(/^https?:\/\//, '')) + '</a></div>' : '') +
                   '</div>' +
                   socialHtml +
                   '</td>' +
                   '</tr>' +
                   (disclaimer ? '<tr><td colspan="' + (logo ? '3' : '1') + '" style="font-size:10px; color:#94a3b8; padding-top:10px; border-top:1px solid #f1f5f9; margin-top:8px;">' + escapeHtml(disclaimer) + '</td></tr>' : '') +
                   '</table>';
        }

        // 3. TEMPLATE: MINIMALIST CHIC
        else if (activeTemplate === 'minimalist') {
            html = '<div style="font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size:13px; color:#334155;">' +
                   '<div style="font-size:15px; font-weight:600; color:#0f172a;">' + escapeHtml(name) + ' <span style="font-size:12px; font-weight:normal; color:#64748b;">/ ' + escapeHtml(title) + '</span></div>' +
                   '<div style="width:60px; height:2px; background:' + color + '; margin:6px 0;"></div>' +
                   '<div style="font-size:12px; color:#64748b;">' +
                   escapeHtml(company) +
                   (email ? ' • <a href="mailto:' + escapeAttr(email) + '" style="color:' + color + '; text-decoration:none;">' + escapeHtml(email) + '</a>' : '') +
                   (phone ? ' • ' + escapeHtml(phone) : '') +
                   (website ? ' • <a href="' + escapeAttr(website) + '" target="_blank" style="color:' + color + '; text-decoration:none;">' + escapeHtml(website.replace(/^https?:\/\//, '')) + '</a>' : '') +
                   '</div>' +
                   socialHtml +
                   '</div>';
        }

        // 4. TEMPLATE: EXECUTIVE CLASSIC
        else if (activeTemplate === 'letterhead') {
            html = '<div style="font-family:Georgia, Times, serif; font-size:13px; color:#2c3e50; border-top:2px solid ' + color + '; border-bottom:1px solid #cbd5e1; padding:12px 0; max-width:540px;">' +
                   '<div style="font-size:18px; font-weight:bold; letter-spacing:0.5px; color:#1a202c;">' + escapeHtml(name).toUpperCase() + '</div>' +
                   '<div style="font-style:italic; font-size:13px; color:' + color + '; margin-top:2px;">' + escapeHtml(title) + ' — ' + escapeHtml(company) + '</div>' +
                   '<div style="font-family:sans-serif; font-size:11px; color:#64748b; margin-top:6px; line-height:1.4;">' +
                   (address ? '<div>' + escapeHtml(address) + '</div>' : '') +
                   '<div>' + (phone ? 'Phone: ' + escapeHtml(phone) + ' ' : '') + (email ? ' • Email: <a href="mailto:' + escapeAttr(email) + '" style="color:' + color + '; text-decoration:none;">' + escapeHtml(email) + '</a>' : '') + '</div>' +
                   '</div>' +
                   socialHtml +
                   (disclaimer ? '<div style="font-family:sans-serif; font-size:10px; color:#94a3b8; margin-top:8px; font-style:italic;">' + escapeHtml(disclaimer) + '</div>' : '') +
                   '</div>';
        }

        // 5. TEMPLATE: TECH & DEVELOPER
        else if (activeTemplate === 'tech') {
            html = '<table cellpadding="0" cellspacing="0" border="0" style="font-family:\'Courier New\', Courier, monospace, -apple-system, sans-serif; font-size:12px; color:#334155;">' +
                   '<tr>' +
                   '<td style="background:#0f172a; border-radius:6px; padding:14px 18px; color:#f8fafc;">' +
                   '<div style="color:' + color + '; font-weight:bold; font-size:14px;">$&gt; ' + escapeHtml(name) + '</div>' +
                   '<div style="color:#94a3b8; font-size:12px; margin-top:2px;">// ' + escapeHtml(title) + ' @ ' + escapeHtml(company) + '</div>' +
                   '<div style="margin-top:8px; font-size:11px; color:#cbd5e1;">' +
                   (email ? '<div>[email]   ' + escapeHtml(email) + '</div>' : '') +
                   (website ? '<div>[web]     <a href="' + escapeAttr(website) + '" target="_blank" style="color:#38bdf8; text-decoration:none;">' + escapeHtml(website) + '</a></div>' : '') +
                   (github ? '<div>[github]  <a href="' + escapeAttr(github) + '" target="_blank" style="color:#38bdf8; text-decoration:none;">' + escapeHtml(github) + '</a></div>' : '') +
                   '</div>' +
                   '</td>' +
                   '</tr>' +
                   '</table>';
        }

        // 6. TEMPLATE: CREATIVE CARD
        else if (activeTemplate === 'badge_card') {
            html = '<div style="font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width:440px; border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#ffffff; box-shadow:0 2px 6px rgba(0,0,0,0.04);">' +
                   '<div style="display:flex; align-items:center; gap:12px;">' +
                   (logo ? '<img src="' + escapeAttr(logo) + '" alt="' + escapeAttr(name) + '" width="54" height="54" style="border-radius:8px; object-fit:cover; display:block;" />' : '') +
                   '<div>' +
                   '<div style="font-size:16px; font-weight:700; color:#0f172a;">' + escapeHtml(name) + '</div>' +
                   '<div style="font-size:12px; font-weight:600; color:' + color + ';">' + escapeHtml(title) + ' • ' + escapeHtml(company) + '</div>' +
                   '<div style="font-size:11px; color:#64748b; margin-top:2px;"><a href="mailto:' + escapeAttr(email) + '" style="color:#64748b; text-decoration:none;">' + escapeHtml(email) + '</a> • ' + escapeHtml(phone) + '</div>' +
                   '</div>' +
                   '</div>' +
                   (website ? '<div style="margin-top:12px; padding-top:10px; border-top:1px solid #f1f5f9;"><a href="' + escapeAttr(website) + '" target="_blank" style="display:inline-block; padding:4px 12px; background:' + color + '; color:#ffffff; font-size:11px; font-weight:600; text-decoration:none; border-radius:4px;">Visit Website →</a></div>' : '') +
                   '</div>';
        }

        // Inject into Preview & Form fields
        document.getElementById('preview-container').innerHTML = html;
        document.getElementById('raw-html-output').value = html;
        document.getElementById('form-save-html').value = html;
        document.getElementById('form-save-plain').value = plain;
    };

    window.copySignatureHtml = function() {
        var html = document.getElementById('form-save-html').value;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(html).then(function() {
                alert('📋 HTML Signature copied to clipboard!');
            });
        } else {
            var raw = document.getElementById('raw-html-output');
            raw.style.display = 'block';
            raw.select();
            document.execCommand('copy');
            alert('📋 HTML Signature copied to clipboard!');
        }
    };

    window.copySignaturePlain = function() {
        var plain = document.getElementById('form-save-plain').value;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(plain).then(function() {
                alert('📄 Plain text signature copied to clipboard!');
            });
        }
    };

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return (text || '').replace(/"/g, '&quot;');
    }

    // Run initial generation
    updateSignature();
})();
</script>
<?php
echo "</body></html>\n";
