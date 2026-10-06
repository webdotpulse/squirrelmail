<?php
/**
 * Addressbook Import-Export Web Interface
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage abook_import_export
 */

require('../../include/init.php');
include_once(SM_PATH . 'functions/addressbook.php');
include_once(SM_PATH . 'functions/page_header.php');
include_once(SM_PATH . 'plugins/abook_import_export/functions.php');

$abook = addressbook_init(false, true);

// Get writable backends
$backends = getWritableBackends();
if (empty($backends)) {
    $backends = array(1 => _("Personal Address Book"));
}

$message = null;
$messageType = 'info';

// Handle Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    $backend_id = isset($_GET['backend']) ? intval($_GET['backend']) : -1;

    $contacts = $abook->list_addr($backend_id);
    if (!is_array($contacts)) {
        $contacts = array();
    }

    $timestamp = date('Ymd_His');
    if ($format === 'vcf') {
        $data = abook_ie_export_vcard($contacts);
        $filename = "contacts_{$timestamp}.vcf";
        $contentType = 'text/vcard; charset=utf-8';
    } elseif ($format === 'ldif') {
        $data = abook_ie_export_ldif($contacts);
        $filename = "contacts_{$timestamp}.ldif";
        $contentType = 'text/plain; charset=utf-8';
    } else {
        $data = abook_ie_export_csv($contacts);
        $filename = "contacts_{$timestamp}.csv";
        $contentType = 'text/csv; charset=utf-8';
    }

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $data;
    exit;
}

// Handle Import Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $conflict_mode = isset($_POST['conflict_mode']) ? $_POST['conflict_mode'] : 'skip';
    $backend_id = isset($_POST['backend']) ? intval($_POST['backend']) : 1;

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $message = _("Please select a valid file to import.");
        $messageType = 'error';
    } else {
        $filename = $_FILES['import_file']['name'];
        $tmpPath = $_FILES['import_file']['tmp_name'];
        $content = file_get_contents($tmpPath);

        $contacts = array();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext === 'vcf') {
            $contacts = abook_ie_parse_vcard($content);
        } else {
            // Default to CSV
            $contacts = abook_ie_parse_csv($content);
        }

        if (empty($contacts)) {
            $message = _("No valid contact records found in the uploaded file. Please ensure it is a valid CSV or vCard (.vcf) file.");
            $messageType = 'error';
        } else {
            $res = abook_ie_import_contacts($contacts, $conflict_mode, $backend_id);
            $msg = sprintf(_("Import completed: %d contact(s) imported, %d skipped as duplicates."), $res['imported'], $res['skipped']);
            if (!empty($res['errors'])) {
                $msg .= " (" . count($res['errors']) . " error(s) occurred)";
            }
            $message = $msg;
            $messageType = ($res['imported'] > 0) ? 'success' : 'warning';
        }
    }
}

displayPageHeader($color, 'None');
?>
<style>
.ie-container {
    max-width: 900px;
    margin: 24px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #202124;
}
.ie-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dadce0;
}
.ie-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ie-nav-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #f1f3f4;
    color: #3c4043;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.15s;
}
.ie-nav-back:hover {
    background: #e8eaed;
    color: #202124;
}
.ie-alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.ie-alert.success { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
.ie-alert.error   { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }
.ie-alert.warning { background: #fef7e0; color: #b06000; border: 1px solid #feefc3; }
.ie-alert.info    { background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; }

.ie-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e8eaed;
}
.ie-tab-btn {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    border: none;
    background: transparent;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    color: #5f6368;
    transition: all 0.15s ease;
}
.ie-tab-btn.active {
    color: #1a73e8;
    border-bottom-color: #1a73e8;
}
.ie-panel {
    display: none;
    background: #ffffff;
    border: 1px solid #dadce0;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(60,64,67,0.08);
}
.ie-panel.active {
    display: block;
}
.ie-form-group {
    margin-bottom: 20px;
}
.ie-form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #3c4043;
    margin-bottom: 8px;
}
.ie-select, .ie-input {
    width: 100%;
    max-width: 450px;
    padding: 10px 14px;
    border: 1px solid #dadce0;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.ie-select:focus, .ie-input:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 2px rgba(26,115,232,0.2);
}
.ie-radio-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 6px;
}
.ie-radio-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    cursor: pointer;
}
.ie-upload-box {
    border: 2px dashed #dadce0;
    border-radius: 10px;
    padding: 32px 20px;
    text-align: center;
    background: #f8f9fa;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    max-width: 500px;
}
.ie-upload-box:hover {
    border-color: #1a73e8;
    background: #f1f7ff;
}
.ie-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    background: #1a73e8;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    transition: background 0.15s, box-shadow 0.15s;
    text-decoration: none;
}
.ie-btn-primary:hover {
    background: #1557b0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.ie-format-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.ie-format-card {
    border: 1px solid #dadce0;
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
}
.ie-format-card:hover {
    border-color: #1a73e8;
    background: #f8fafd;
}
.ie-format-card input[type="radio"] {
    position: absolute;
    top: 16px;
    right: 16px;
}
.ie-format-card strong {
    display: block;
    font-size: 15px;
    margin-bottom: 4px;
    color: #202124;
}
.ie-format-card span {
    font-size: 12px;
    color: #5f6368;
    line-height: 1.4;
    display: block;
}
</style>

<div class="ie-container">
    <div class="ie-header">
        <h2>
            <span>📇</span>
            <span><?php echo _("Address Book Import &amp; Export"); ?></span>
        </h2>
        <a href="../../src/addressbook.php" class="ie-nav-back">&larr; <?php echo _("Back to Address Book"); ?></a>
    </div>

    <?php if ($message): ?>
    <div class="ie-alert <?php echo $messageType; ?>">
        <span><?php echo ($messageType === 'success' ? '✅' : ($messageType === 'error' ? '❌' : 'ℹ️')); ?></span>
        <div><?php echo htmlspecialchars($message); ?></div>
    </div>
    <?php endif; ?>

    <div class="ie-tabs">
        <button type="button" class="ie-tab-btn active" onclick="switchIeTab('import')">📥 <?php echo _("Import Contacts"); ?></button>
        <button type="button" class="ie-tab-btn" onclick="switchIeTab('export')">📤 <?php echo _("Export Contacts"); ?></button>
    </div>

    <!-- IMPORT PANEL -->
    <div id="panel-import" class="ie-panel active">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">

            <div class="ie-form-group">
                <label><?php echo _("Select File to Import"); ?> (.csv, .vcf)</label>
                <div class="ie-upload-box" onclick="document.getElementById('import_file').click()">
                    <div style="font-size: 32px; margin-bottom: 8px;">📂</div>
                    <strong style="color: #1a73e8;"><?php echo _("Choose a file"); ?></strong> <?php echo _("or drag and drop here"); ?>
                    <div style="font-size: 12px; color: #5f6368; margin-top: 4px;">CSV (Google / Outlook / Thunderbird) or vCard (.vcf)</div>
                    <input type="file" id="import_file" name="import_file" accept=".csv,.vcf" style="display: none;" onchange="updateFileName(this)">
                    <div id="file-name-display" style="margin-top: 10px; font-weight: 600; color: #137333; display: none;"></div>
                </div>
            </div>

            <div class="ie-form-group">
                <label><?php echo _("Target Address Book"); ?></label>
                <select name="backend" class="ie-select">
                    <?php foreach ($backends as $bId => $bName): ?>
                    <option value="<?php echo $bId; ?>"><?php echo htmlspecialchars($bName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ie-form-group">
                <label><?php echo _("Duplicate Handling"); ?></label>
                <div class="ie-radio-group">
                    <label class="ie-radio-label">
                        <input type="radio" name="conflict_mode" value="skip" checked>
                        <span><?php echo _("Skip existing contacts (preserve original entries)"); ?></span>
                    </label>
                    <label class="ie-radio-label">
                        <input type="radio" name="conflict_mode" value="overwrite">
                        <span><?php echo _("Update/Overwrite existing contacts with imported info"); ?></span>
                    </label>
                    <label class="ie-radio-label">
                        <input type="radio" name="conflict_mode" value="add">
                        <span><?php echo _("Add all contacts (create unique nickname if duplicate)"); ?></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="ie-btn-primary">
                <span>📥</span>
                <span><?php echo _("Import Contacts Now"); ?></span>
            </button>
        </form>
    </div>

    <!-- EXPORT PANEL -->
    <div id="panel-export" class="ie-panel">
        <form method="get">
            <input type="hidden" name="action" value="export">

            <div class="ie-form-group">
                <label><?php echo _("Select Export Format"); ?></label>
                <div class="ie-format-cards">
                    <label class="ie-format-card">
                        <input type="radio" name="format" value="csv" checked>
                        <strong>CSV (Standard / Google)</strong>
                        <span>Compatible with Gmail, Google Contacts, Outlook, Excel &amp; Thunderbird.</span>
                    </label>
                    <label class="ie-format-card">
                        <input type="radio" name="format" value="vcf">
                        <strong>vCard 3.0 (.vcf)</strong>
                        <span>Standard electronic business card format for Apple Contacts, iOS, Android, and Outlook.</span>
                    </label>
                    <label class="ie-format-card">
                        <input type="radio" name="format" value="ldif">
                        <strong>LDIF (.ldif)</strong>
                        <span>LDAP Data Interchange Format for enterprise directories and Thunderbird.</span>
                    </label>
                </div>
            </div>

            <div class="ie-form-group">
                <label><?php echo _("Source Address Book"); ?></label>
                <select name="backend" class="ie-select">
                    <option value="-1"><?php echo _("All Address Books"); ?></option>
                    <?php foreach ($backends as $bId => $bName): ?>
                    <option value="<?php echo $bId; ?>"><?php echo htmlspecialchars($bName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="ie-btn-primary">
                <span>📤</span>
                <span><?php echo _("Download Export File"); ?></span>
            </button>
        </form>
    </div>
</div>

<script>
function switchIeTab(tab) {
    document.querySelectorAll('.ie-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.ie-panel').forEach(p => p.classList.remove('active'));
    
    if (tab === 'import') {
        document.querySelectorAll('.ie-tab-btn')[0].classList.add('active');
        document.getElementById('panel-import').classList.add('active');
    } else {
        document.querySelectorAll('.ie-tab-btn')[1].classList.add('active');
        document.getElementById('panel-export').classList.add('active');
    }
}

function updateFileName(input) {
    var display = document.getElementById('file-name-display');
    if (input.files && input.files[0]) {
        display.textContent = 'Selected: ' + input.files[0].name + ' (' + Math.round(input.files[0].size / 1024) + ' KB)';
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}
</script>
<?php
echo "</body></html>\n";
