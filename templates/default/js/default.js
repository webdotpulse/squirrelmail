/**
 * This array is used to remember mark status of rows in browse mode
 *
 * @copyright 2005-2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 */
var marked_row = new Array;
var orig_row_colors = new Array();

/*
 * (un)Checks checkbox for the row that the current table cell is in
 * when it gets clicked.
 *
 * @param string                  The (internal) name of the checkbox
 *                                that should be (un)checked
 * @param JavaScript event object The JavaScript event associated
 *                                with this mouse click
 * @param string                  The name of the encapsulating form
 * @param string                  The (real) name of the checkbox
 *                                that should be (un)checked
 * @param string                  Any extra JavaScript (that will
 *                                be executed herein if non-empty);
 *                                must be valid JavaScript expression(s)
 */
function row_click(chkboxName, event, formName, checkboxRealName, extra) {
    var chkbox = document.getElementById(chkboxName);
    if (chkbox) {
        // initialize orig_row_color if not defined already
        if (!orig_row_colors[chkboxName]) {
            orig_row_colors[chkboxName] = chkbox.parentNode.getAttribute('bgcolor');
            if (orig_row_colors[chkboxName].indexOf("clicked_") == 0)
                orig_row_colors[chkboxName] = orig_row_colors[chkboxName].substring(8, orig_row_colors[chkboxName].length);
        }
        chkbox.checked = (chkbox.checked ? false : true);

        if (extra != '') eval(extra);
    }
}

/*
 * Gets the current class of the requested row.  This is a browser specific function.
 * Code shamelessly ripped from setPointer() below.
 */
function getCSSClass (theRow)
{
    var rowClass;
	// 3.1 ... with DOM compatible browsers except Opera that does not return
	//         valid values with "getAttribute"
	if (typeof(window.opera) == 'undefined'
		&& typeof(theRow.getAttribute) != 'undefined'
		&& theRow.getAttribute('className') ) {
		rowClass = theRow.getAttribute('className');
	}
	// 3.2 ... with other browsers
	else {
		rowClass = theRow.className;
	}
	
	return rowClass;
}

/*
 * Sets a new CSS class for the given row.  Browser-specific.
 */
function setCSSClass (obj, newClass) {
	if (typeof(window.opera) == 'undefined' && typeof(obj.getAttribute) != 'undefined' && obj.getAttribute('className') ) {
		obj.setAttribute('className', newClass, 0);
	}
	else {
		obj.className = newClass;
	}
}

/*
 * This function is used to initialize the orig_row_color array so we do not
 * need to predefine the entire array
 */
function rowOver(chkboxName) {
    var chkbox = document.getElementById(chkboxName);
    if (!chkbox) return;
    var tr = chkbox.closest ? chkbox.closest('tr') : (chkbox.parentNode ? chkbox.parentNode.parentNode : null);
    if (tr) {
        tr.classList.add('mouse_over');
    }
}

/*
 * (un)Checks all checkboxes for the message list from a specific form
 * when it gets clicked.
 */
function toggle_all(formname, name_prefix, fancy) {
    var targetForm = document.getElementById(formname);
    if (!targetForm) return;
    var master = targetForm.querySelector ? targetForm.querySelector('#toggleAll, #checkall') : null;
    var checkboxes = targetForm.querySelectorAll ? targetForm.querySelectorAll('input[type="checkbox"]') : targetForm.elements;
    var isMasterChecked = master ? master.checked : null;

    for (var i = 0; i < checkboxes.length; i++) {
        var cb = checkboxes[i];
        if (cb.type === 'checkbox' && cb !== master && (!name_prefix || cb.name.substring(0, 3) === name_prefix)) {
            cb.checked = (isMasterChecked !== null) ? isMasterChecked : !cb.checked;
            var tr = cb.closest ? cb.closest('tr') : (cb.parentNode ? cb.parentNode.parentNode : null);
            if (tr) {
                if (cb.checked) {
                    tr.classList.add('clicked', 'selected');
                } else {
                    tr.classList.remove('clicked', 'selected');
                }
            }
        }
    }
}

/*
 * Sets/unsets the pointer and marker in browse mode
 */
function setPointer(theRow, theRowNum, theAction, defaultClass, mouseoverClass, clickedClass, optEvent)
{
    if (!theRow) return false;
    var e = optEvent || window.event;

    // Prevent flickering when moving between cells or children within the same row
    if (theAction === 'out' && e && e.relatedTarget && theRow.contains(e.relatedTarget)) {
        return false;
    }

    mouseoverClass = mouseoverClass || 'mouse_over';
    clickedClass = clickedClass || 'clicked';

    if (theAction === 'over') {
        theRow.classList.add(mouseoverClass);
    } else if (theAction === 'out') {
        theRow.classList.remove(mouseoverClass);
    } else if (theAction === 'click') {
        theRow.classList.toggle(clickedClass);
        theRow.classList.toggle('selected', theRow.classList.contains(clickedClass));
        if (typeof theRowNum !== 'undefined') {
            marked_row[theRowNum] = theRow.classList.contains(clickedClass);
        }
    }

    return true;
} // end of the 'setPointer()' function

function comp_in_new_form(comp_uri, button, myform, iWidth, iHeight) {
    comp_uri += "&" + button.name + "=1";
    for ( var i=0; i < myform.elements.length; i++ ) {
        if ( myform.elements[i].type == "checkbox"  && myform.elements[i].checked )
        comp_uri += "&" + myform.elements[i].name + "=1";
    }
    if (!iWidth) iWidth   =  640;
    if (!iHeight) iHeight =  550;
    var sArg = "width=" + iWidth + ",height=" + iHeight + ",scrollbars=yes,resizable=yes,status=yes";
    var newwin = window.open(comp_uri, "_blank", sArg);
}

function comp_in_new(comp_uri, iWidth, iHeight) {
    if (!iWidth) iWidth   =  640;
    if (!iHeight) iHeight =  550;
    sArg = "width=" + iWidth + ",height=" + iHeight + ",scrollbars=yes,resizable=yes,status=yes";
    var newwin = window.open(comp_uri , "_blank", sArg);
}

/*
 * Reload the read_body screen on sending an mdn receipt
 */
function sendMDN() {
    var mdnuri=window.location+'&sendreceipt=1';
    window.location = mdnuri; 
}

var alreadyFocused = false;

function cursorToTop(element) {
    if (typeof element.selectionStart == 'number')
        element.selectionStart = element.selectionEnd = 0;
    else if (typeof element.createTextRange != 'undefined') {
        var selectionRange = element.createTextRange();
        selectionRange.moveStart('character', 0);
        selectionRange.select();
    }
}

function checkForm(smaction) {

    if (alreadyFocused) return;

    /*
     * this part is used for setting the focus in the compose screen
     */
    if (smaction) {
        if (smaction == "select") {
            document.forms['compose'].body.select();
        } else if (smaction == "focus") {
            document.forms['compose'].body.focus();
            cursorToTop(document.forms['compose'].body);
        }
    } else {
    /*
     * All other forms that need to set the focus
     */
        var f = document.forms.length;
        var i = 0;
        var remembered_form = -1;
        var pos = -1;
        var remembered_pos = -1;
        while( pos == -1 && i < f ) {
            var e = document.forms[i].elements.length;
            var j = 0;
            while( pos == -1 && j < e ) {
                if ( document.forms[i].elements[j].type == 'text' || document.forms[i].elements[j].type == 'password' || document.forms[i].elements[j].type == 'textarea' ) {
                    if ( document.forms[i].elements[j].id.substring(0, 13) == '__lastfocus__' ) {
                        remembered_pos = j;
                        remembered_form = i;
                    } else if ( document.forms[i].elements[j].id.substring(0, 11) != '__nofocus__' ) {
                        pos = j;
                    }
                }
                j++;
            }
        i++;
        }
        if( pos >= 0 ) {
            document.forms[i-1].elements[pos].focus();
        } else if ( remembered_pos >= 0 ) {
            document.forms[remembered_form].elements[remembered_pos].focus();
        }
    }
}

function printThis()
{
    parent.frames['right'].focus();
    parent.frames['right'].print();
}

/* JS implementation of more/less links in To/CC. Could later be extended
 * show/hide other interface items */
function showhide (item, txtmore, txtless) {
    var oTemp=document.getElementById("recpt_tail_" + item);
    var oClick=document.getElementById("toggle_" + item);
    if (oTemp.style.display=="inline") {
        oTemp.style.display="none";
        oClick.innerHTML=txtmore;
    } else {
        oTemp.style.display="inline";
        oClick.innerHTML=txtless;
    }
}
