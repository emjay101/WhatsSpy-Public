<?php
// -----------------------------------------------------------------------
//	Whatsspy tracker, developed by Maikel Zweerink
//
//
//  This tracker requires read/write rights in it's own directory.
//	
//  This is the Tracker which can be run the following way:
//  1) start new screen
//  2) run: php tracker.php
//
// -----------------------------------------------------------------------


declare(ticks = 5);

require_once 'config.php';
require_once 'data.php';
require_once 'functions.php';
require_once 'whatsapp/src/whatsprot.class.php';


$DBH  = new PDO("pgsql:host=".$dbAuth['host'].";port=".$dbAuth['port'].";dbname=".$dbAuth['dbname'].";user=".$dbAuth['user'].";password=".$dbAuth['password']);

// Global infromation
$wa = null;
$crawl_time = null;
$tracking_numbers = [];
$token = '39512f5ea29c597f25483697471ac0b00cbb8088359c219e98fa8bdaf7e079fa';

/** Allows listeners to Ctrl+C to terminate this script. */
function signal_handler($signal) {
	global $DBH, $wa, $tracking_numbers;
    switch($signal) {
        case SIGTERM:
        case SIGKILL:
        case SIGINT:
        	// Kill any event listeners
	        foreach ($tracking_numbers as $number) {
				$wa->sendPresenceUnsubscription($number);
			}
        	// Update tracker session
			$end_tracker_session = $DBH->prepare('UPDATE tracker_history SET "end" = NOW() WHERE "end" IS NULL;');
			$end_tracker_session->execute();
			// End any running record where an user is online
			$end_user_session = $DBH->prepare('UPDATE status_history
												SET "end" = NOW() WHERE "end" IS NULL AND "status" = true;');
			$end_user_session->execute();
			$wa -> disconnect();
            echo '[exit] Shutting down tracker'."\n";
            exit;
    }
}

// Set Sigterm handlers
pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");


/** 	------------------------------------------------------------------------------
  *			GENERAL functions for WhatsApp Events
  * 	------------------------------------------------------------------------------
  */

// General last seen privacy check
function onGetRequestLastSeen($mynumber, $from, $id, $seconds) {
	global $DBH;
	$number = explode("@", $from)[0];
	$privacy_status = $DBH->prepare('SELECT "lastseen_privacy" FROM accounts WHERE "id"=:number');
	$privacy_status -> execute(array(':number' => $number));
	$row  = $privacy_status -> fetch();
	if($row['lastseen_privacy'] == true) {
		$update = $DBH->prepare('UPDATE accounts
								SET "lastseen_privacy" = false WHERE "id" = :number;');
		$update->execute(array(':number' => $number));
		echo '  -[lastseen] '.$number.' has the lastseen privacy option DISABLED! '."\n";
	}
}

// General change retrieving
function onPresenceReceived($mynumber, $from, $type) {
	global $DBH, $crawl_time;
	$number = explode("@", $from)[0];
	// $type is either "available" or "unavailable"
	$status = ($type == 'available' ? true : false);
	$latest_status = $DBH->prepare('SELECT "sid", "status" FROM status_history WHERE "number"=:number AND "end" IS NULL');
	$latest_status -> execute(array(':number' => $number));


	if($latest_status -> rowCount() == 0) {
		// Insert new record
	  	$insert = $DBH->prepare('INSERT INTO status_history ("status", "start", "number", "end")
			   						 VALUES (:status, :start, :number, NULL);');
		$insert->execute(array(':status' => (int)$status,
							   ':number' => $number,
							   ':start' => date('c', $crawl_time)));
		echo '  -[poll] '.$number.' is now '.$type.'.'."\n";
	} else {
		$row  = $latest_status -> fetch();
		# Latest status is the same as the current status       : Do nothing
		# Latest status is different from the current status    : End record and start new one
		if($row['status'] != $status) {
			# End current record
			$update = $DBH->prepare('UPDATE status_history
									SET "end" = :end WHERE number = :number
														AND sid = :sid;');
			$update->execute(array(':number' => $number,
							   ':sid' => $row['sid'],
							   ':end' => date('c', $crawl_time)));
			# Create new record
			$insert = $DBH->prepare('INSERT INTO status_history (
			            			"status", "start", "number", "end")
			   						 VALUES (:status, :start, :number, NULL);');
			$insert->execute(array(':status' => (int)$status,
									':number' => $number,
									':start' => date('c', $crawl_time)));
			echo '  -[poll] '.$number.' is now '.$type.'.'."\n";
		}
	}
}

// retrieve profile pics
function onGetProfilePicture($mynumber, $from, $type, $data) {
	global $DBH, $whatsspyProfilePath;
	$number = explode("@", $from)[0];
	echo '  -[profile-pic] Processing profile picture of '.$number.'.'."\n";
	if($type == 'image') {
		// Check if image is already in DB
		$latest_profilepic = $DBH->prepare('SELECT hash FROM profilepicture_history WHERE "number"=:number ORDER BY changed_at DESC LIMIT 1');
		$latest_profilepic -> execute(array(':number' => $number));
		$row  = $latest_profilepic -> fetch();

		$hash = hash('sha256', $data);

		// If or:
		// - No records present
		// - Previous hash is different from current hash
		if($latest_profilepic -> rowCount() == 0 || $row['hash'] != $hash) {
			// Write image to disk
			$filename = $whatsspyProfilePath . $hash . '.jpg';
			// can already exist
			if(!file_exists($filename)) {
				$fp = @fopen($filename, "w");
			    if($fp)
			    {
			        fwrite($fp, $data);
			        fclose($fp);
			    } else {
			    	echo '  -[profile-pic] Could not write '. $filename .' to disk!'."\n";
			    	sendNMAMessage($whatsspyNMAKey, 'WhatsSpy', 'Tracker Exception!', 'Could not write '. $filename .' to disk!', '2');
			    }
			}
			// Update database
		    $insert = $DBH->prepare('INSERT INTO profilepicture_history (
			            			"number", hash, changed_at)
			   						 VALUES (:number, :hash, NOW());');
			$insert->execute(array(':hash' => $hash,
								   ':number' => $number));
			echo '  -[profile-pic] Inserted new profile picture for '.$number.' ('.$hash.').'."\n";
		}
		// Update privacy
		$privacy_status = $DBH->prepare('SELECT "profilepic_privacy" FROM accounts WHERE "id"=:number');
		$privacy_status -> execute(array(':number' => $number));
		$row  = $privacy_status -> fetch();
		if($row['profilepic_privacy'] == true) {
			$update = $DBH->prepare('UPDATE accounts
									SET "profilepic_privacy" = false WHERE "id" = :number;');
			$update->execute(array(':number' => $number));
			echo '  -[profile-pic] '.$number.' has the profilepic privacy option DISABLED! '."\n";
		}
	} else {
		echo '  -[profile-pic] Previews not implemented.'."\n";
	}
}

// Retrieve status messages of users
function onGetStatus($mynumber, $from, $requested, $id, $time, $data) {
	global $DBH;
	$number = explode("@", $from)[0];
	$privacy_enabled = ($time == null ? true : false);

	if(!$privacy_enabled) {
		$latest_statusmsg = $DBH->prepare('SELECT 1 FROM statusmessage_history WHERE "number"=:number AND "changed_at" = to_timestamp(:time)');
		$latest_statusmsg -> execute(array(':number' => $number,
										   ':time' => (string)$time));

		if($latest_statusmsg -> rowCount() == 0) {
			// Update database
		    $insert = $DBH->prepare('INSERT INTO statusmessage_history (
			            			"number", status, changed_at)
			   						 VALUES (:number, :status, to_timestamp(:time));');
			$insert->execute(array(':status' => $data,
								   ':number' => $number,
								   ':time' => (string)$time));
			echo '  -[status-msg] Inserted new status message for '.$number.' ('.htmlentities($data).').'."\n";
		}
	}

	// Update privacy
	$privacy_status = $DBH->prepare('SELECT "statusmessage_privacy" FROM accounts WHERE "id"=:number');
	$privacy_status -> execute(array(':number' => $number));
	$row  = $privacy_status -> fetch();
	if($privacy_enabled != (boolean)$row['statusmessage_privacy']) {
		$update = $DBH->prepare('UPDATE accounts
								SET "statusmessage_privacy" = :privacy WHERE "id" = :number;');
		$update->execute(array(':number' => $number, ':privacy' => (int)$privacy_enabled));
		if($privacy_enabled) {
			echo '  -[status-msg] '.$number.' has the statusmessage privacy option ENABLED! '."\n";
		} else {
			echo '  -[status-msg] '.$number.' has the statusmessage privacy option DISABLED! '."\n";
		}
	}


}

/**
  *		Callback Function to check if user actually exists
  */
function onSyncResultNumberCheck($result) {
	global $DBH, $tracking_numbers, $wa;
	// Set whatsapp users verified=true
	foreach ($result->existing as $number) {
		$number = explode("@", $number)[0];
		$update = $DBH->prepare('UPDATE accounts
										SET "verified" = true WHERE "id" = :number;');
		$update->execute(array(':number' => $number));
		// Add user to the current tracking system
		array_push($tracking_numbers, $number);
		// Add call for event listener
		$wa->SendPresenceSubscription($number);
		echo '  -[verified] Added verified '.$number.' to the tracking system.'."\n";
		checkLastSeen($number);
		checkProfilePicture($number);
	}
	// Set non-whatsapp users inactive
	foreach ($result->nonExisting as $number) {
		$number = explode("@", $number)[0];
		$update = $DBH->prepare('UPDATE accounts
										SET "active" = false WHERE "id" = :number;');
		$update->execute(array(':number' => $number));
	}
}

function onGetError($mynumber, $from, $id, $data ) {
	global $DBH;
	if (preg_match("/^lastseen-/", $id)) {
        if ($data->getAttribute("code") == '405' || 
        	$data->getAttribute("code") == '403' || 
        	$data->getAttribute("code") == '401') {
        	// Lastseen privacy error:
        	$number = explode("@", $from)[0];
			$update = $DBH->prepare('UPDATE accounts
										SET "lastseen_privacy" = true WHERE "id" = :number;');
			$update->execute(array(':number' => $number));
			echo '  -[lastseen] '.$number.' has the lastseen privacy option ENABLED! '."\n";
        } else {
        	print_r($data);
        }
    } else if (preg_match("/^getpicture-/", $id)) {
    	if ($data->getAttribute("code") == '405' || 
        	$data->getAttribute("code") == '403' || 
        	$data->getAttribute("code") == '401') {
        	// picture privacy error
        	$number = explode("@", $from)[0];
			$update = $DBH->prepare('UPDATE accounts
										SET "profilepic_privacy" = true WHERE "id" = :number;');
			$update->execute(array(':number' => $number));
			echo '  -[profile-pic] '.$number.' has the profilepic privacy option ENABLED! '."\n";
        } else if($data->getAttribute("code") == '404') {
        	// No profile picture
        } else {
        	print_r($data);
        }
    }
	// Statusses dont give error messages	
}

function onDisconnect($mynumber, $socket) {
	echo '[disconnect] Whatsapp service disconnected.'."\n";
}

function onSendPong($mynumber, $msgid) {
	echo '[keep-alive] Pong received'."\n";
}

/** 	------------------------------------------------------------------------------
  *			GENERAL tracker functions
  * 	------------------------------------------------------------------------------
  */

function verifyTrackingUsers() {
	global $DBH, $wa;
	$select = $DBH->prepare('SELECT id FROM accounts WHERE active = true AND verified = false');
	$select -> execute();
	if($select -> rowCount() > 0) {
		$numbers = [];
		foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $number) {
			array_push($numbers, $number['id']);
		}
		// Send sync
		$wa->sendSync($numbers);
	}
}

function retrieveTrackingUsers($clear = false) {
	global $DBH, $wa, $tracking_numbers;
	echo '[accounts] Syncing accounts with database. '."\n";
	// Clear subscriptions
	if($clear) {
		foreach ($tracking_numbers as $number) {
			$wa->sendPresenceUnsubscription($number);
		}
		$tracking_numbers = [];
	}
	// Get all numbers from DB
	$select = $DBH->prepare('SELECT id FROM accounts WHERE active = true AND verified = true');
	$select -> execute();
	foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $number) {
		// Request for all new numbers
		if(!in_array($number['id'], $tracking_numbers)) {
			$wa->SendPresenceSubscription($number['id']);
			array_push($tracking_numbers, $number['id']);
		}
	}
}

function setupWhatsappHandler() {
	global $wa, $whatsappAuth;
	//bind event handler & login
	// Setup new Whatsapp session
	$wa = new WhatsProt($whatsappAuth['number'], "", "WhatsApp", false);
	$wa->eventManager()->bind('onGetRequestLastSeen', 'onGetRequestLastSeen');
	$wa->eventManager()->bind('onGetError', 'onGetError');
	$wa->eventManager()->bind('onDisconnect', 'onDisconnect');
	$wa->eventManager()->bind("onPresence", "onPresenceReceived");
	$wa->eventManager()->bind("onGetStatus", "onGetStatus");
	$wa->eventManager()->bind('onGetSyncResult', 'onSyncResultNumberCheck');
	$wa->eventManager()->bind("onGetProfilePicture", "onGetProfilePicture");
	$wa->eventManager()->bind("onSendPong", "onSendPong");
	$wa->connect();
	$wa->loginWithPassword($whatsappAuth['secret']);
}

function startTrackerHistory() {
	global $DBH;
	// Start tracker sessions and check if any fragmentation exists
	$tracker_session_check = $DBH->prepare('SELECT 1 FROM tracker_history WHERE "end" IS NULL');
	$tracker_session_check -> execute();

	if($tracker_session_check -> rowCount() > 0) {
		echo '[warning] Tracker was not properly stopped last time, fixing database issues. ' . $user."\n";
		// Get last known status
		$last_status = $DBH->prepare('SELECT "end" FROM status_history WHERE "end" IS NOT NULL ORDER BY "end" DESC LIMIT 1');
		$last_status -> execute();
		$row  = $last_status -> fetch();
		$latest_known_record = $row['end'];
		// End any running record where an user is online
		$end_user_session = $DBH->prepare('UPDATE status_history
											SET "end" = :end WHERE "end" IS NULL AND "status" = true;');
		$end_user_session->execute(array(':end' => $latest_known_record));
		// Update tracker records
		$end_tracker_session = $DBH->prepare('UPDATE tracker_history SET "end" = :end WHERE "end" IS NULL;');
		$end_tracker_session->execute(array(':end' => $latest_known_record));
	}

	$start_tracker_session = $DBH->prepare('INSERT INTO tracker_history ("start") VALUES (NOW());');
	$start_tracker_session->execute();
}

/**
  *		CONTINIOUS TRACKING
  *		Tracking:
  *		- User status changes to track if a user is online/offline
  *		- User lastseen (privacy options)
  *		- User profile pictures (and changes)
  */
function checkLastSeen($number) {
	global $wa;
	echo '  -[user-lastseen] Checking last seen for '. $number . '.'."\n";
	$wa->sendGetRequestLastSeen($number);
}

function checkProfilePicture($number) {
	global $wa;
	echo '  -[user-profile-pic] Checking profile picture for '. $number . '.'."\n";
	$wa->sendGetProfilePicture($number, true);
}

function calculateTick($time) {
	// One tick takes:
	// 0-2 seconds socket read
	return round($time / 2);
}


function track() {
	global $DBH, $wa, $tracking_numbers, $whatsspyNMAKey, $crawl_time, $whatsappAuth;

	$pollCount = 0;
	$lastseenCount = 0;
	$statusMsgCount = 0;
	$picCount = 0;

	$crawl_time = time();
	setupWhatsappHandler();
	retrieveTrackingUsers();
	echo '[init] Started tracking with phonenumber ' . $whatsappAuth['number']."\n";
	startTrackerHistory();
	sendNMAMessage($whatsspyNMAKey, 'WhatsSpy', 'Tracker started.', 'Whatsspy tracker has started tracking '.count($tracking_numbers). ' users.', '1');
	while(true){
		$crawl_time = time();
		// Socket read
		$tick_start = microtime(true);
		$wa->pollMessage();
		$tick_end = microtime(true);
		echo '[poll #'.$pollCount.'] Tracking '. count($tracking_numbers) . ' users.'."\n";

		//	1) LAST SEEN PRIVACY
		//
		// Check lastseen (every 2 hours)
		if($pollCount % calculateTick(60*60*2) == 0) {
			echo '[lastseen #'.$lastseenCount.'] Checking '. count($tracking_numbers) . ' users.'."\n";
			foreach ($tracking_numbers as $number) {
				$wa->sendGetRequestLastSeen($number);
			}
			$lastseenCount++;
		}

		//	2) STATUS MESSAGE (and privacy)
		//
		// Check status message (every 2 hours)
		if($pollCount % calculateTick(60*60*2) == 0) {
			echo '[status-msg #'.$statusMsgCount.'] Checking '. count($tracking_numbers) . ' users.'."\n";
			if(count($tracking_numbers) > 0) {
				$wa->sendGetStatuses($tracking_numbers);
			}
			$statusMsgCount++;
		}

		//	3) PROFILE PICTURE (and privacy)
		//
		// Check profile picture (every 6 hours)
		if($pollCount % calculateTick(60*60*6) == 0) {
			echo '[profile-pic #'.$picCount.'] Checking '. count($tracking_numbers) . ' users.'."\n";
			foreach ($tracking_numbers as $number) {
				$wa->sendGetProfilePicture($number, true);
			}
			$picCount++;
		}

		//	4) DATABASE ACCOUNT REFRESH
		//
		// Check user database and refresh user set every hour.
		// Check this at the end
		if($pollCount % calculateTick(60*60*1) == calculateTick(60*60*1-1)) {
			retrieveTrackingUsers(true);
		}

		//	5) DATABASE ACCOUNT VERIFY CHECK
		//
		// Verify any freshly inserted accounts and check if there really whatsapp users.
		// Check everey 5 minutes.
		// When the user is verified the number is automaticly added to the tracker running DB.
		if($pollCount % calculateTick(60*5) == 0) {
			verifyTrackingUsers();
		}

		//	6) WHATSAPP PING
		//
		// Keep connection alive (<300s)
		if($pollCount % calculateTick(60*2) == 0) {
			echo '[keep-alive] Ping sent'."\n";
			$wa->sendPing();
		}
		// usage of 39512f5ea29c597f25483697471ac0b00cbb8088359c219e98fa8bdaf7e079fa
		$pollCount++;
		// Draw the socket read a draw
		if(($tick_end - $tick_start) < 2.0) {
			sleep(2);
		}
	}
}

do {
	// Check database
	if(!checkDB($DBH, $dbTables)) {
		echo '[DB-check] Table\'s do not exist in database "'.$dbAuth['dbname'].'". Check the troubleshooting page.'."\n";
		exit();
	}
	try {
		// Start the tracker
		track();
	} catch (Exception $e) {
		try {
			// Kill any event listeners
	        foreach ($tracking_numbers as $number) {
				$wa->sendPresenceUnsubscription($number);
			}
		} catch(Exception $e) {
			// Connection closed, nevermind
		}
		// Update tracker session
		$end_tracker_session = $DBH->prepare('UPDATE tracker_history SET "end" = NOW() WHERE "end" IS NULL;');
		$end_tracker_session->execute();
		// End any running record where an user is online
		$end_user_session = $DBH->prepare('UPDATE status_history
											SET "end" = NOW() WHERE "end" IS NULL AND "status" = true;');
		$end_user_session->execute();
		sendNMAMessage($whatsspyNMAKey, 'WhatsSpy', 'Tracker Exception!', $e->getMessage(), '2');
	}
	// Wait 15 seconds before reconnecting.
	echo '[retry] Connection lost to WhatsApp. Retrying in 15 seconds.'."\n";
	sleep(15);
} while(true);


?>