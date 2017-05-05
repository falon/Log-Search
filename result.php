<?php
$conf = parse_ini_file("log.conf", true);
require_once('function.php');
openlog('Log Search', LOG_PID, LOG_MAIL);

$username = username();
$client_ip = getClientIP();

syslog(LOG_INFO, "$username: Info: Starting user activity from IP <$client_ip>.");

$date_part = explode('-', $_POST['date']);
if ( !checkdate($date_part[1], $date_part[2], $date_part[0]) )
	exit ('<p>'.htmlspecialchars('Please, insert a valid date, not <'.$_POST['date'].'>').'.</p>'); 


if ( isset($_POST['from']) )
        $from = $_POST['from'];
else	exit ('<p>No from email address entered.</p>');
if ( isset($_POST['to']) )
        $to = $_POST['to'];
else    exit ('<p>No recipient email address entered.</p>');
if ( isset($_POST['msgid']) )
        $msgid = $_POST['msgid'];
else    exit ('<p>No Message-ID entered.</p>');


if (filter_var($from, FILTER_VALIDATE_EMAIL) === FALSE)
 exit ('<p>'.htmlspecialchars('Please, insert a valid email address, not <'.$from.'>').'.</p>');
if (filter_var($to, FILTER_VALIDATE_EMAIL) === FALSE)
 exit ('<p>'.htmlspecialchars('Please, insert a valid email address, not <'.$to.'>').'.</p>');


/* Splunk connection */
require_once($conf['SplunkSDK']['splpath'].'/Splunk.php');
$conf['SplunkSDK']['splunkConn']['namespace'] = Splunk_Namespace::createUser($conf['SplunkSDK']['namespace']['user'],
						$conf['SplunkSDK']['namespace']['app']);
$splservice = new Splunk_Service($conf['SplunkSDK']['splunkConn']);
$splservice->login();
/********************/

$server = $conf['Search']['msa'];
$search = sprintf('search host=%s* [search host=%s* message_id=%s  | table queue_id] ' .
	'| transaction queue_id,host mvlist=t startswith=client\= endswith=REMOVED ' .
	'| search from=%s to=%s | eval duration=tostring(duration,"duration") '.
	'| eval duration=replace(duration,"(\d*)\+*(\d+):(\d+):(\d+)\.(\d+)","\1 days \2 hours \3 minutes \4 secs") ' .
	'| addinfo | eval dayago=strptime(strftime(relative_time(info_max_time, "-d"),"%%Y-%%m-%%d"." 00:00:00"),"%%Y-%%m-%%d %%H:%%M:%%S") ' .
	'| eval ok = if(_time<dayago,1,0) | search ok=1 '.
	'| table _time client_ip client host from to status reason notification_type notification_queue_id delay duration',
	$server, $server, addslashes( $msgid ), $from, $to );

$result = splunksearch ($splservice,$search,$_POST['date'], 2);
if (empty($result))
	print '<p>No mail found.</p>';
else {
	$values = parseResult($result, $to);
	print '<table>';
	printTableHeader('Log for this email', array('field','value'),TRUE,htmlspecialchars("End of transaction for <$msgid>"));
	printTableRows($values);
	print '</table>';
}

if ( isset($values['notification_queue_id']) ) {
/* print notification mail transaction */
	foreach ( $values['notification_queue_id'] as $index => $queueid ) {
		$search = sprintf('search host=%s* queue_id=%s ' .
        	'| transaction queue_id,host mvlist=t startswith=message-id\= endswith=REMOVED ' .
		'| eval duration=tostring(duration,"duration") '.
        	'| eval duration=replace(duration,"(\d*)\+*(\d+):(\d+):(\d+)\.(\d+)","\1 days \2 hours \3 minutes \4 secs") ' .
        	'| addinfo | eval dayago=strptime(strftime(relative_time(info_max_time, "-d"),"%%Y-%%m-%%d"." 00:00:00"),"%%Y-%%m-%%d %%H:%%M:%%S") ' .
        	'| eval ok = if(_time<dayago,1,0) | search ok=1 '.
        	'| table _time host from to message_id status reason delay duration',
        	$server, $queueid );

		$result = splunksearch ($splservice,$search,$_POST['date'], 3);
		if (empty($result))
        		print '<p>No notification mail returned to sender.</p>';
		else {
			$notifvalues=parseResult($result, $values['from']);
	        	print '<table>';
	        	printTableHeader('Log for '.$values['notification_type']["$index"], array('field','value'),TRUE,htmlspecialchars("End of transaction"));
	        	printTableRows($notifvalues);
	        	print '</table>';
		}
	}
}
closelog();
?> 
