# Log-Search
Utility to search in Splunk maillog  email from Message-ID, from, to, date.

## Require
Splunk for Postfix and Postfix 3.
Add this fields extraction:
```
postfix_syslog : EXTRACT-notification_type,notification_queue_id
^(?:[^ \n]* ){4}(?P<notification_type>[^:]+):\s+(?P<notification_queue_id>.+)
```

## Install
- Clone from git.
- Move the include folder to your root web path (if you don't have already from my others projects).
- Take Splunk SDK from site http://dev.splunk.com/php, unzip it in include folder with original name.

Oh no, Splunk has just removed support from PHP SDK! Damn... I hate Splunk!
