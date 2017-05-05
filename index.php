<html>
<head>
<title>Log Search</title>
<meta http-equiv="Content-Type" content="text/html; charset="UTF-8">
<link rel="stylesheet" type="text/css" href="/include/style.css">
<link rel="SHORTCUT ICON" href="favicon.ico"> 
<script  src="/include/ajaxsbmt.js" type="text/javascript"></script>
</head>
<body>
<h1 style="margin:2">Log Search</h1>
<p style="text-align: right; margin:0">Hello <b><?php echo $_SERVER["REMOTE_USER"];?></b></p>
<?php
$date = new DateTime('now');
$datepick = $date->format('Y-m-d');

print <<<END
 <form method="POST" accept-charset="UTF-8" name="Richiestadati" action="result.php" onSubmit="xmlhttpPost('result.php', 'Richiestadati', 'Risultato', '<img src=\'/include/pleasewait.gif\'>'); return false;">
<table align="center" cellspacing=1>
<caption>Use this tool to find your email log.</caption>
<thead>
<tr><td class="form">Date </td><td colspan="5"><input type="date" name="date" value="${datepick}" placeholder="yyyy-mm-dd" class="input_text" id="1" required></td></tr>
</thead>
<tbody>
<tr><td class="form">From </td><td colspan="5"><input type="email" name="from" size="50" class="input_text" id="1" placeholder="The from email address" required></td></tr>
<tr><td class="form">To </td><td colspan="5"><input type="email" name="to" size="50" class="input_text" id="1" placeholder="The recipient email address" required></td></tr>
<tr><td class="form">Message-ID </td><td colspan="5"><input type="email" name="msgid" size="50" class="input_text" id="1" placeholder="The Message-ID" required></td></tr>
</tbody>
<tfoot>
<tr style= "margin-top: 3"><td><input type="reset" value="Reset" name="Reset" class="btn"></td>
<td colspan="5"><input type="submit" value="View Log" name="View Log" class="btn"></td></tr>
</tfoot></table></form>


<div id="Risultato"></div>
END;
?>
<p style="text-align: right; margin:0">Log Search is presented in HTML5.</p>
</body>
</html>
