<?php
// -----------------------------------------------------------------------
// Whatsspy tracker
// @Author Maikel Zweerink
//
//  This tracker requires read/write rights in it's own directory.
//	
//  This is the Tracker which can be run the following way:
//  1) start new screen
//  2) run: php tracker.php
//
// -----------------------------------------------------------------------

// DO NOT START THIS SCRIPT FROM THE CGI.
if (PHP_SAPI !== 'cli'){ 
	exit();
}

declare(ticks = 20);

require_once 'config.php';
require_once 'data.php';
require_once 'functions.php';
require_once 'whatsapp/src/whatsprot.class.php';


$DBH  = setupDB($dbAuth);

// Global infromation
$wa = null;
$crawl_time = null;
$tracking_numbers = [];
$token = 'c1a2e762238d8ed5e14ef20c5809a32ace32797e47aca70ef6ff451aa01b5748';

// Global Poll timers
$pollCount = 0;
$lastseenCount = 0;
$statusMsgCount = 0;
$picCount = 0;

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
			// Reset DB connection
			$DBH = null;
			$wa -> disconnect();
            tracker_log('[exit] Shutting down tracker');
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
	global $DBH, $wa;
	$number = explode("@", $from)[0];
	$privacy_status = $DBH->prepare('SELECT "lastseen_privacy" FROM accounts WHERE "id"=:number');
	$privacy_status -> execute(array(':number' => $number));
	$row  = $privacy_status -> fetch();
	if($row['lastseen_privacy'] == true) {
		$update = $DBH->prepare('UPDATE accounts
								SET "lastseen_privacy" = false WHERE "id" = :number;');
		$update->execute(array(':number' => $number));
		tracker_log('  -[lastseen] '.$number.' has the lastseen privacy option DISABLED! ');
	}
}

// General change retrieving
function onPresenceReceived($mynumber, $from, $type) {
	global $DBH, $wa, $crawl_time;
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
		tracker_log('  -[poll] '.$number.' is now '.$type.'.');
		checkAndSendWhatsAppNotify($DBH, $wa, $number, ':name is now '.$type.'.');
	} else {
		$row  = $latest_status -> fetch();
		# Latest status is the same as the current status       : Do nothing
		# Latest status is different from the current status    : End record and start new one
		if($row['status'] != $status) {
			# End current record
			$update = $DBH->prepare('UPDATE status_history
									SET "end" = :end WHERE number = :number
														AND sid = :sid;');
			// Use crawl_time -4 second for compensation of the Infratructure.
			// End signals tend to be sent later than starting signals.
			$update->execute(array(':number' => $number,
							   ':sid' => $row['sid'],
							   ':end' => date('c', $crawl_time-4)));
			# Create new record
			$insert = $DBH->prepare('INSERT INTO status_history (
			            			"status", "start", "number", "end")
			   						 VALUES (:status, :start, :number, NULL);');
			// Use crawl_time -2 second for compensation of the Infratructure.
			$insert->execute(array(':status' => (int)$status,
									':number' => $number,
									':start' => date('c', $crawl_time-2)));
			tracker_log('  -[poll] '.$number.' is now '.$type.'.');
			if($type == 'available') {
				checkAndSendWhatsAppNotify($DBH, $wa, $number, ':name is now '.$type.'.');
			}
		}
	}
}

// retrieve profile pics
function onGetProfilePicture($mynumber, $from, $type, $data) {
	global $DBH, $wa, $whatsspyProfilePath, $whatsspyNMAKey, $whatsspyLNKey;
	$number = explode("@", $from)[0];
	tracker_log('  -[profile-pic] Processing profile picture of '.$number.'.');
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
			    	tracker_log('  -[profile-pic] Could not write '. $filename .' to disk!');
			    	sendMessage('Tracker Exception!', 'Could not write '. $filename .' to disk!', $whatsspyNMAKey, $whatsspyLNKey);
			    }
			}
			// Update database
		    $insert = $DBH->prepare('INSERT INTO profilepicture_history (
			            			"number", hash, changed_at)
			   						 VALUES (:number, :hash, NOW());');
			$insert->execute(array(':hash' => $hash,
								   ':number' => $number));
			tracker_log('  -[profile-pic] Inserted new profile picture for '.$number.' ('.$hash.').');
			checkAndSendWhatsAppNotify($DBH, $wa, $number, ':name has a new profile picture.', $filename);
		}
		// Update privacy
		$privacy_status = $DBH->prepare('SELECT "profilepic_privacy" FROM accounts WHERE "id"=:number');
		$privacy_status -> execute(array(':number' => $number));
		$row  = $privacy_status -> fetch();
		if($row['profilepic_privacy'] == true) {
			$update = $DBH->prepare('UPDATE accounts
									SET "profilepic_privacy" = false WHERE "id" = :number;');
			$update->execute(array(':number' => $number));
			tracker_log('  -[profile-pic] '.$number.' has the profilepic privacy option DISABLED!');
		}
	} else {
		tracker_log('  -[profile-pic] Previews not implemented.');
	}
}

// Retrieve status messages of users
function onGetStatus($mynumber, $from, $requested, $id, $time, $data) {
	global $DBH, $wa;
	$number = explode("@", $from)[0];
	$privacy_enabled = ($time == null ? true : false);

	if(!$privacy_enabled) {
		$latest_statusmsg = $DBH->prepare('SELECT 1 FROM statusmessage_history WHERE "number"=:number AND ("changed_at" = to_timestamp(:time) OR "status" = :status)');
		$latest_statusmsg -> execute(array(':number' => $number,
										   ':status' => $data,
										   ':time' => (string)$time));

		if($latest_statusmsg -> rowCount() == 0) {
			// Check last message
			// Update database
		    $insert = $DBH->prepare('INSERT INTO statusmessage_history (
			            			"number", status, changed_at)
			   						 VALUES (:number, :status, to_timestamp(:time));');
			$insert->execute(array(':status' => $data,
								   ':number' => $number,
								   ':time' => (string)$time));
			tracker_log('  -[status-msg] Inserted new status message for '.$number.' ('.$data.').');
			checkAndSendWhatsAppNotify($DBH, $wa, $number, ':name has a new status message: \''.$data.'\'.');
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
			tracker_log('  -[status-msg] '.$number.' has the statusmessage privacy option ENABLED! ');
		} else {
			tracker_log('  -[status-msg] '.$number.' has the statusmessage privacy option DISABLED! ');
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
		tracker_log('  -[verified] Added verified '.$number.' to the tracking system.');
		checkLastSeen($number);
		checkProfilePicture($number);
		checkStatusMessage($number);
	}
	// Set non-whatsapp users inactive
	foreach ($result->nonExisting as $number) {
		$number = explode("@", $number)[0];
		$update = $DBH->prepare('UPDATE accounts
										SET "active" = false WHERE "id" = :number;');
		$update->execute(array(':number' => $number));
		tracker_log('  -[verified] Number '.$number.' is NOT a WhatsApp user.');
	}
}

function onGetError($mynumber, $from, $id, $data ) {
	global $DBH, $wa, $pollCount;
	if (preg_match("/^lastseen-/", $id)) {
        if ($data->getAttribute("code") == '405' || 
        	$data->getAttribute("code") == '403' || 
        	$data->getAttribute("code") == '401') {
        	// Lastseen privacy error:
        	$number = explode("@", $from)[0];
			$update = $DBH->prepare('UPDATE accounts
										SET "lastseen_privacy" = true WHERE "id" = :number;');
			$update->execute(array(':number' => $number));
			tracker_log('  -[lastseen] '.$number.' has the lastseen privacy option ENABLED! ');
        } else if($data->getAttribute("code") == '404') {
        	tracker_log('  -[lastseen] cannot determine lastseen, ignoring request.');
        } else {
        	tracker_log('  -[lastseen] unknown error for '.$number.'. ');
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
			tracker_log('  -[profile-pic] '.$number.' has the profilepic privacy option ENABLED! ');
        } else if($data->getAttribute("code") == '404') {
        	// No profile picture
        } else {
        	tracker_log('  -[profile-pic] unknown error for '.$number.'. ');
        	print_r($data);
        }
    }
	// Statusses dont give error messages	
}

function onDisconnect($mynumber, $socket) {
	tracker_log('[disconnect] Whatsapp service disconnected.');
}

function onSendPong($mynumber, $msgid) {
	tracker_log('[keep-alive] Pong received');
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
		if(count($numbers) > 0) {		
			$wa->sendSync($numbers);
		}
	}
}

function retrieveTrackingUsers($clear = false) {
	global $DBH, $wa, $tracking_numbers;
	tracker_log('[accounts] Syncing accounts with database. ');
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
	//bind event handler & tracker_login
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
		tracker_log('[warning] Tracker was not properly stopped last time, fixing database issues. ' . $user);
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
  *     - User status message (and changes)
  */
function checkLastSeen($number) {
	global $wa;
	tracker_log('  -[user-lastseen] Checking last seen for '. $number . '.');
	$wa->sendGetRequestLastSeen($number);
}

function checkProfilePicture($number) {
	global $wa;
	tracker_log('  -[user-profile-pic] Checking profile picture for '. $number . '.');
	$wa->sendGetProfilePicture($number, true);
}

function checkStatusMessage($number) {
	global $wa;
	tracker_log('  -[user-status-msg] Checking status message for '. $number . '.');
	$wa->sendGetStatuses([$number]);
}

function calculateTick($time) {
	// One tick takes:
	// 0-1 seconds socket read
	return round($time / 1);
}


function track() {
	global $DBH, $wa, $tracking_ticks, $tracking_numbers, $whatsspyNMAKey, $whatsspyLNKey, $crawl_time, $whatsappAuth, $pollCount, $lastseenCount, $statusMsgCount, $picCount, $request_error_queue;

	$crawl_time = time();
	setupWhatsappHandler();
	retrieveTrackingUsers();
	tracker_log('[init] Started tracking with phonenumber ' . $whatsappAuth['number']);
	startTrackerHistory();
	sendMessage('WhatsSpy Public has started tracking!', 'tracker has started tracking '.count($tracking_numbers). ' users.', $whatsspyNMAKey, $whatsspyLNKey);
	while(true){
		$crawl_time = time();
		// Socket read
		$tick_start = microtime(true);
		$wa->pollMessage();
		$tick_end = microtime(true);
		tracker_log('[poll #'.$pollCount.'] Tracking '. count($tracking_numbers) . ' users.'."\r", true, false);

		//	1) LAST SEEN PRIVACY
		//
		// Check lastseen
		if($pollCount % calculateTick($tracking_ticks['lastseen']) == 0) {
			tracker_log('[lastseen #'.$lastseenCount.'] Checking '. count($tracking_numbers) . ' users.');
			foreach ($tracking_numbers as $number) {
				$wa->sendGetRequestLastSeen($number);
			}
			$lastseenCount++;
		}

		//	2) STATUS MESSAGE (and privacy)
		//
		// Check status message 
		if($pollCount % calculateTick($tracking_ticks['statusmsg']) == 0) {
			tracker_log('[status-msg #'.$statusMsgCount.'] Checking '. count($tracking_numbers) . ' users.');
			if(count($tracking_numbers) > 0) {
				$wa->sendGetStatuses($tracking_numbers);
			}
			$statusMsgCount++;
		}

		//	3) PROFILE PICTURE (and privacy)
		//
		// Check profile picture
		if($pollCount % calculateTick($tracking_ticks['profile-pic']) == 0) {
			tracker_log('[profile-pic #'.$picCount.'] Checking '. count($tracking_numbers) . ' users.');
			foreach ($tracking_numbers as $number) {
				$wa->sendGetProfilePicture($number, true);
			}
			$picCount++;
		}

		//	4) DATABASE ACCOUNT REFRESH
		//
		// Check user database and refresh user set every hour but with a offset of 80 seconds.
		if($pollCount % calculateTick($tracking_ticks['refresh-db']) == calculateTick($tracking_ticks['refresh-db']-80)) {
			retrieveTrackingUsers(true);
		}

		//	5) DATABASE ACCOUNT VERIFY CHECK
		//
		// Verify any freshly inserted accounts and check if there really whatsapp users.
		// Check everey 5 minutes.
		// When the user is verified the number is automaticly added to the tracker running DB.
		if($pollCount % calculateTick($tracking_ticks['verify-check']) == 0) {
			verifyTrackingUsers();
		}

		//	6) WHATSAPP PING
		//
		// Keep connection alive (<300s)
		if($pollCount % calculateTick($tracking_ticks['keep-alive']) == 0) {
			tracker_log('[keep-alive] Ping sent');
			$wa->sendPing();
		}
		// usage of 39512f5ea29c597f25483697471ac0b00cbb8088359c219e98fa8bdaf7e079fa
		$pollCount++;
		// Draw the socket read a draw
		if(($tick_end - $tick_start) < 1.0) {
			sleep(1);
		}
	}
}

// Starting the tracker
tracker_log('------------------------------------------------------------------', false);
tracker_log('|                    WhatsSpy Public Tracker                     |', false);
tracker_log('|                        Proof of Concept                        |', false);
tracker_log('|              Check gitlab.maikel.pro for more info             |', false);
tracker_log('------------------------------------------------------------------', false);
sleep(2);
do {
	// Check database
	if(!checkDB($DBH, $dbTables)) {
		tracker_log('[DB-check] Table\'s do not exist in database "'.$dbAuth['dbname'].'". Check the troubleshooting page.');
		exit();
	}
	// Upgrade DB if it's old
	checkDBMigration($DBH);
	// Nag about config.php if it's old
	checkConfig();
	try {
		// Start the tracker
		track();
	} catch (Exception $e) {
		try {
			// Kill the connection
			$wa->disconnect();
		} catch(Exception $e) {
			// Connection closed, nevermind
		}
		// Reset DB connection
		$DBH = null;
		$DBH = setupDB($dbAuth);
		// Update tracker session
		$end_tracker_session = $DBH->prepare('UPDATE tracker_history SET "end" = NOW() WHERE "end" IS NULL;');
		$end_tracker_session->execute();
		// End any running record where an user is online
		$end_user_session = $DBH->prepare('UPDATE status_history
											SET "end" = NOW() WHERE "end" IS NULL AND "status" = true;');
		$end_user_session->execute();

		tracker_log('[error] Tracker exception! '.$e->getMessage());
		sendMessage('Tracker Exception!', $e->getMessage(), $whatsspyNMAKey, $whatsspyLNKey);
	}
	// Wait 30 seconds before reconnecting.
	tracker_log('[retry] Reconnectiong to WhatsApp in 30 seconds.');
	sleep(30);
} while(true);


?>
