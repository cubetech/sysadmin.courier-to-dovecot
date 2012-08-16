<?php

set_time_limit(0);

//* Soap user configuration
$username = 'admin';
$password = 'admin';
$server_id = 1;
$mail_base_dir = '/var/vmail';

$soap_location = 'http://192.168.0.105:8080/remote/index.php';
$soap_uri = 'http://192.168.0.105:8080/remote/';

//* Get all mail users
$client = new SoapClient(null, array('location' => $soap_location,
                                     'uri'      => $soap_uri,
									 'trace' => 1,
									 'exceptions' => 1));


try {
	if($session_id = $client->login($username,$password)) {
		echo 'Logged successfull. Session ID:'.$session_id."\n";
	}
	
	$active_mail_filters = array();
	$active_autoresponders = array();
		
	$mail_users = $client->mail_user_get($session_id, array('server_id' => $server_id));
	
	if(is_array($mail_users)) {
		foreach($mail_users as $mail_user) {
			$mailuser_id = $mail_user['mailuser_id'];
			$client_id = $client->client_get_id($session_id, $mail_user['sys_userid']);
			$mail_user['password'] = '';
			echo "Disabling mailfilters for mailbox ".$mail_user['email'].":\n";
			
			//* Get all mailfilters of the user and disable them
			$mail_filters = $client->mail_user_filter_get($session_id, array('mailuser_id' => $mailuser_id, 'active' => 'y'));
			if(is_array($mail_filters)) {
				foreach($mail_filters as $mail_filter) {
					$mail_filter['active'] = 'n';
					$client->mail_user_filter_update($session_id, $client_id, $mail_filter['filter_id'], $mail_filter);
					$active_mail_filters[] = $mail_filter['filter_id'];
					echo "- Disabling mailfilter ".$mail_filter['rulename']."\n";
				}
			}
			
			//* Update the mail user and empty the custom mailfilter field
			if($mail_user['custom_mailfilter'] != '' or $mail_user['autoresponder'] == 'y') {
				$mail_user['custom_mailfilter'] = '';
				if($mail_user['autoresponder'] == 'y') {
					$mail_user['autoresponder'] = 'n';
					$active_autoresponders[] = $mailuser_id;
					echo "Disabling autoresponder for mailbox ".$mail_user['email']."\n";
				}
				$client->mail_user_update($session_id, $client_id, $mailuser_id, $mail_user);
			}
		}
		
		//* Now we wait until ispconfig has written everything to disk
		echo "We wait 5 minutes, dont interrupt the script.";
		for($n = 1; $n < 300; $n++) {
			echo ".";
			sleep(1);
		}
		echo "\n";
		
		//* Disable maildrop plugin and enable dovecot deliver plugin
		unlink('/usr/local/ispconfig/server/plugins-enabled/maildrop_plugin.inc.php');
		symlink('/usr/local/ispconfig/server/plugins-available/maildeliver_plugin.inc.php','/usr/local/ispconfig/server/plugins-enabled/maildeliver_plugin.inc.php');

		
		//* Now we go trough the mailboxes again
		foreach($mail_users as $mail_user) {
			$mailuser_id = $mail_user['mailuser_id'];
			$client_id = $client->client_get_id($session_id, $mail_user['sys_userid']);
			$mail_user['password'] = '';
			
			//* We move the maildir contents to its new location
			$maildir = $mail_user['maildir'];
			echo $maildir."\n";
			
			if($maildir != '' && stristr($maildir,$mail_base_dir)) {
				if(!is_dir($maildir.'/Maildir')) {
					mkdir($maildir.'/Maildir');
					chown($maildir.'/Maildir','vmail');
					chgrp($maildir.'/Maildir','vmail');
				}
				if(is_dir($maildir.'/courierimapkeywords')) @rmdir($maildir.'/courierimapkeywords');
				if(file_exists($maildir.'/courierimapsubscribed')) unlink($maildir.'/courierimapsubscribed');
				if(file_exists($maildir.'/courierimapuiddb')) unlink($maildir.'/courierimapuiddb');
				
				exec("mv $maildir/* $maildir/Maildir/ 2> /dev/null");
				exec("mv $maildir/.* $maildir/Maildir/ 2> /dev/null");
				echo "Moved maildir contents from $maildir to $maildir/Maildir/ \n";
			}
			
			//* activate the autoresponder again, if it was active before
			if(in_array($mailuser_id,$active_autoresponders)) {
				$client->mail_user_update($session_id, $client_id, $mailuser_id, $mail_user);
				echo "Activate autoresponder for mailbox ".$mail_user['email']."\n";
			}
		}
		
		//* Now we activate our mailfilters again
		foreach($active_mail_filters as $filter_id) {
			$mail_filter = $client->mail_user_filter_get($session_id, $filter_id);
			$client_id = $client->client_get_id($session_id, $mail_filter['sys_userid']);
			$mail_filter['active'] = 'y';
			$client->mail_user_filter_update($session_id, $client_id, $filter_id, $mail_filter);
			echo "Enabling mailfilter ".$mail_filter['rulename']."\n";
		}
		
	}
	
	if($client->logout($session_id)) {
		echo "Logged out.\n";
	}
	
	
} catch (SoapFault $e) {
	echo $client->__getLastResponse();
	die("SOAP Error: ".$e->getMessage()."\n");
}





?>