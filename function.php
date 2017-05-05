<?php

ini_set('error_log', 'syslog');

function username() {
        if (isset ($_SERVER['REMOTE_USER'])) $user = $_SERVER['REMOTE_USER'];
                else if (isset ($_SERVER['USER'])) $user = $_SERVER['USER'];
                else if ( isset($_SERVER['PHP_AUTH_USER']) ) $user = $_SERVER['PHP_AUTH_USER'];
                else {
                        syslog(LOG_ALERT, "No user given by connection from {$_SERVER['REMOTE_ADDR']}. Exiting");
                        exit(0);
                }
        return $user;
}

function getClientIP() {
	return getenv('HTTP_CLIENT_IP')?:
		getenv('HTTP_X_FORWARDED_FOR')?:
		getenv('HTTP_X_FORWARDED')?:
		getenv('HTTP_FORWARDED_FOR')?:
		getenv('HTTP_FORWARDED')?:
		getenv('REMOTE_ADDR');
}


function splunksearch ($service,$searchQueryBlocking,$date,$ndays) {

        // Run a blocking search

        // A blocking search returns the job when the search is done
        /* Wait to finish */
        $job = $service->getJobs()->create($searchQueryBlocking, array(
            'exec_mode' => 'blocking',
            'earliest_time' => date("c",strtotime ($date)),
            'latest_time' => date("c",strtotime ($date)+86400*$ndays)
        ));

        /*
        // Display properties of the job
        echo '<p>Search job properties:</p><hr/>';
        echo '<p>Search job ID:' . htmlspecialchars($job['sid']);
        echo '</p><p>The number of events:' . htmlspecialchars($job['eventCount']);
        echo '</p><p>The number of results:' . htmlspecialchars($job['resultCount']);
        echo '</p><p>Search duration:' . htmlspecialchars($job['runDuration']);
        echo ' seconds';
        echo '</p><p>This job expires in:' . htmlspecialchars($job['ttl']);
        echo ' seconds</p>';
        */

        if ($job['resultCount'] == 0) return FALSE;

        // Get job results
        $resultSearch = $job->getResults();


        // Use the built-in XML parser to display the job results
        foreach ($resultSearch as $result)
          {
            if ($result instanceof Splunk_ResultsFieldOrder)
            {
              // More than one field attribute returned by search
              // You must redefine the search
              if ( count($result->getFieldNames()) < 6 ) return array();
            }
            else if ($result instanceof Splunk_ResultsMessage)
            {
              // I don't want messages in my search
              return array();
            }
            else if (is_array($result))
            {
		#print_r($result);
		return $result;
            }
            else
            {
              #print "Unknow result type";
              return array();
            }
          }
}


function parseResult($array, $find) {

/*
$array =
(
    [client] => Array
        (
            [0] => client.example.com
            [1] => NULL
            [2] => NULL
            [3] => NULL
            [4] => NULL
            [5] => NULL
            [6] => NULL
            [7] => NULL
            [8] => NULL
        )

    [host] => Array
        (
            [0] => msafarm2
            [1] => msafarm4
            [2] => msafarm2
            [3] => msafarm1
            [4] => msafarm2
            [5] => msafarm3
            [6] => msafarm2
            [7] => msafarm5
            [8] => msafarm2
        )

    [from] => Array
        (
            [0] => NULL
            [1] => NULL
            [2] => NULL
            [3] => sender@example.com
            [4] => NULL
            [5] => NULL
            [6] => NULL
            [7] => NULL
            [8] => NULL
        )

    [to] => Array
        (
            [0] => NULL
            [1] => NULL
            [2] => NULL
            [3] => NULL
            [4] => otherrecip1@example.com
            [5] => wanted@example.com
            [6] => otherrecip2@example.com
            [7] => otherrecip3@example.com
            [8] => NULL
        )

    [status] => Array
        (
            [0] => NULL
            [1] => NULL
            [2] => NULL
            [3] => NULL
            [4] => sent
            [5] => sent
            [6] => sent
            [7] => sent
            [8] => NULL
        )

    [reason] => Array
        (
            [0] => NULL
            [1] => NULL
            [2] => NULL
            [3] => NULL
            [4] => 250 2.1.5 Ok SESSIONID=...1
            [5] => 250 2.1.5 Ok SESSIONID=...2
            [6] => 250 2.1.5 Ok SESSIONID=...3
            [7] => 250 2.1.5 Ok SESSIONID=...4
            [8] => NULL
        )

)   */


	$keys = array_keys($array['to'], $find);
	$fromKeys = array_keys(preg_grep("/^NULL/", $array['from'], PREG_GREP_INVERT));
	$notifKeys = array_keys(preg_grep("/^NULL/", $array['notification_type'], PREG_GREP_INVERT));

	if ( count($keys) == 0)
		return array();

	foreach ( array ('client', 'client_ip') as $field ) {
		if (isset($array["$field"]))
			$return["$field"] = $array["$field"][0];
	}
	$return['host'] = $array['host'][$keys[0]];
	$return['from'] = $array['from'][$fromKeys[0]];
	$i=0;
	foreach ( $keys as $key ) {
		$return[$i]['to'] = $array['to'][$key];
		$return[$i]['status'] = $array['status'][$key];
		$return[$i]['reason'] = $array['reason'][$key];
		$i++;
	}
	$return['time'] = $array['_time'];
	$return['delay'] = $array['delay'][$key] . ' s';
	foreach ($notifKeys as $notifKey) {
        	foreach ( array ('notification_type', 'notification_queue_id') as $field ) {
                	if (isset($array["$field"]))
                        	$return["$field"][$notifKey] = $array["$field"][$notifKey];
        	}
	}
	$return['duration'] = $array['duration'];

	return $return;
}


function printTableHeader($title,$content,$footer=FALSE,$fcontent) {
        print <<<END
<caption>$title</caption>
<thead>
<tr>
END;
        $cols = count($content);
        for ($i=0; $i<$cols; $i++)
		if ($i == 0)
                	print '<th colspan="2">'.$content[$i].'</th>';
		else	print '<th>'.$content[$i].'</th>';
        print '</tr></thead>';
        if ($footer) {
                print '<tfoot><tr>';
                printf ('<th colspan="%d">%s</th>',$cols+1,$fcontent);
                print '</tr></tfoot>';
        }
        return TRUE;
}


function printTableRows($values) {
	$i=1;
	foreach ( $values as $field => $value ) {
		if ( $field === 'notification_queue_id' ) continue;
		if ( $field === 'notification_type' ) {
			foreach ($value as $index => $type ) 
				printf('<tr><td colspan="2">%s</td><td>%s</td></tr>',
				htmlspecialchars($type), htmlspecialchars($values['notification_queue_id']["$index"]));
		}
		else {
			if ( is_array($value) ) {
				$c = count($value);
				printf('<tr><td rowspan="%d" nowrap>Attempt %d</td>', $c, $i);
				$first = TRUE;
				foreach ($value as $desc => $val ) {
					$style = NULL;
					if ($desc == 'status')
						switch ( $val ) {
							case 'sent':
								$style = 'style="background-color: green; color: white;"';
								break;
							case 'deferred':
								$style = 'style="background-color: orange"';
                                                        	break;
							case 'bounced':
                                                        	$style = 'style="background-color: red"';
                                                        	break;
						}
					if ($first) {
						printf('<td>%s</td><td %s>%s</td></tr>', htmlspecialchars($desc), $style, htmlspecialchars($val));
						$first = FALSE;
					}
					else
						printf('<tr><td>%s</td><td %s>%s</td></tr>', htmlspecialchars($desc), $style, htmlspecialchars($val));
				}
				$i++;
			}
			else
				printf('<tr><td colspan="2">%s</td><td>%s</td></tr>',$field,htmlspecialchars($value));
		}
	}
}
?>
