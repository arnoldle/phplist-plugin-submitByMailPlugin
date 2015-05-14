<?php

print('<h2>Testing How to Position jQuery Dialogs</h2>');
print ('<p>The dialog should be centered</p>');
print ('<p>The dialog should be centered</p>');
print($dilg);
$dilg = '<div id="mydialog" title="Data Not Saved" style="text-align:center;"></div>'; // Space for modal dialogs using jQueryUI
$str = <<<LIN
<p style="margin-left:auto; margin-right:auto; margin-top:10px"><button title="Show Dialog" onclick="myalert('Here is a message!')">Show the Dialog</button></p>;
LIN;
print('<a href="javascript:alert(document.compatMode);">What mode am I?</a>');
print ('<p id="para">The dialog should be centered</p>');
print($str);
print($dilg);
print ('<script type="text/javascript">');
$str = <<<EOD
$(document).ready(function () {
      $("#mydialog").dialog({
    		modal: true,
    		autoOpen: false,
    		width: 500,
    		position:[800, 500]
    	}); 
	$(".ui-dialog-titlebar-close").css("display","none");
	$(".ui-dialog-content").css("margin", "10px");
	$(".ui-dialog").css("border","3px solid DarkGray");
	$(".ui-dialog-content").css("font-size", "18px");
	});
EOD;
print($str);
$str = <<<EOS
function myalert(msg) {
	$("#mydialog").html(msg);
	$("#mydialog").dialog("option",{buttons:{"OK": function() {
        				$(this).dialog("close");}}});
    $("#mydialog").dialog("open");
}
</script>
EOS;
print($str);
?>