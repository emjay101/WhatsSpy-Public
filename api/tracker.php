<?php
// -----------------------------------------------------------------------
//	@Name WhatsSpy Public
// 	@Author Maikel Zweerink
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

declare(ticks = 30);

require_once 'config.php';
require_once 'data.php';
require_once 'db-functions.php';
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
        case SIGTSTP:
        	// Kill any event listeners
	        foreach ($tracking_numbers as $number) {
				@$wa->sendPresenceUnsubscription($number);
			}

        	// Update tracker session
			$end_tracker_session = $DBH->prepare('UPDATE tracker_history SET "end" = NOW(), "reason" = \'Normal shutdown\' WHERE "end" IS NULL;');
			$end_tracker_session->execute();
			// End any running record where an user is online
			$end_user_session = $DBH->prepare('UPDATE status_history
												SET "end" = NOW() WHERE "end" IS NULL AND "status" = true;');
			checkDatabaseInsert($end_user_session->execute());
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
pcntl_signal(SIGTSTP, "signal_handler");


/** 	------------------------------------------------------------------------------
  *			GENERAL functions for WhatsApp Events
  * 	------------------------------------------------------------------------------
  */

// General last seen privacy check
function onGetRequestLastSeen($mynumber, $from, $id, $seconds) {
	global $DBH, $wa, $whatsspyNotificatons;
	$number = explode("@", $from)[0];
	$privacy_status = $DBH->prepare('SELECT "lastseen_privacy" FROM accounts WHERE "id"=:number');
	$privacy_status -> execute(array(':number' => $number));
	$row  = $privacy_status -> fetch();
	if($row['lastseen_privacy'] == true) {
		$update = $DBH->prepare('UPDATE accounts
								SET "lastseen_privacy" = false WHERE "id" = :number;');
		if(checkDatabaseInsert($update->execute(array(':number' => $number)))) {
			tracker_log('  -[lastseen] '.$number.' has the lastseen privacy option DISABLED! ');
			sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name changed the lastseen privacy option', 
																		'description' => ':name has the lastseen privacy option to DISABLED!.',
																		'number' => $number,
																		'notify_type' => 'privacy']);
		}
	}
}

function handPresenceChange($number, $type, $DBH, $wa, $crawl_time, $whatsspyNotificatons) {
	global $whatsspyHeuristicOptions;
	$status = ($type == 'available' ? true : false);
	$latest_status = $DBH->prepare('SELECT "sid", "status", ROUND(EXTRACT(\'epoch\' FROM "start")) as "start" FROM status_history WHERE "number"=:number AND "end" IS NULL');
	$latest_status -> execute(array(':number' => $number));

	$real_time = $crawl_time;
	if($latest_status -> rowCount() == 0) {
		tracker_debug('No status records found for '.$number.'. Inserting first one.');
		// Insert new record
		if($status == true) {
			// Once a user comes online, you will be notified by WhatsApp within 2-3 seconds.
			$real_time = $real_time + $whatsspyHeuristicOptions['onPresenceAvailableLag'];
		}
	  	$insert = $DBH->prepare('INSERT INTO status_history ("status", "start", "number", "end")
			   						 VALUES (:status, :start, :number, NULL);');
		if(checkDatabaseInsert($insert->execute(array(':status' => (int)$status,
												   ':number' => $number,
												   ':start' => date('c', $real_time))))) {
			tracker_log('  -[poll] '.$number.' is now '.$type.'.');
			if($type == 'available') {
				sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name status change', 
																			'description' => ':name is now '.$type.'.',
																			'number' => $number,
																			'notify_type' => 'status']);
			}
		}
	} else {
		tracker_debug('Status records found for '.$number.'.');
		$row  = $latest_status -> fetch();
		# Latest status is the same as the current status       : Do nothing
		# Latest status is different from the current status    : End record and start new one
		if($row['status'] != $status) {
			tracker_debug('Latest status ('.$row['status'].') is different from this one ('.$status.'). Processing update.');
			if($row['status'] == true) {
				// Correct ending time of this online status
				if($row['start'] < ($real_time + $whatsspyHeuristicOptions['onPresenceUnavailableLagFase1'])) {
					$real_time = $real_time + $whatsspyHeuristicOptions['onPresenceUnavailableLagFase1'];
				} elseif($row['start'] < ($real_time + $whatsspyHeuristicOptions['onPresenceUnavailableLagFase2'])) {
					$real_time = $real_time + $whatsspyHeuristicOptions['onPresenceUnavailableLagFase2'];
				} elseif($row['start'] < ($real_time + $whatsspyHeuristicOptions['onPresenceUnavailableLagFase3'])) {
					$real_time = $real_time + $whatsspyHeuristicOptions['onPresenceUnavailableLagFase3'];
				} else {
					if($row['start'] < $real_time) {
						// End time is after before time, seems ok
					} else {
						// It seems like the timing is off, assume small session of 10 seconds.
						$real_time = $row['start'] + 10;
					}
				}
			} else {
				// Correct starting time of this online status
				$real_time = $real_time + $whatsspyHeuristicOptions['onPresenceAvailableLag'];
			}
			$update = $DBH->prepare('UPDATE status_history
									SET "end" = :end WHERE number = :number
														AND sid = :sid;');

			checkDatabaseInsert($update->execute(array(':number' => $number,
													   ':sid' => $row['sid'],
													   ':end' => date('c', $real_time))));
			# Create new record
			$insert = $DBH->prepare('INSERT INTO status_history (
			            			"status", "start", "number", "end")
			   						 VALUES (:status, :start, :number, NULL);');
			if(checkDatabaseInsert($insert->execute(array(':status' => (int)$status,
														':number' => $number,
														':start' => date('c', $real_time))))) {

				tracker_log('  -[poll] '.$number.' is now '.$type.'.');
				if($type == 'available') {
					sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name status change', 
																				'description' => ':name is now '.$type.'.',
																				'number' => $number,
																				'notify_type' => 'status']);
				}
			}
		}
	}
}

function onPresenceAvailable($username, $from) {
	global $DBH, $wa, $crawl_time, $whatsspyNotificatons;
	$number = explode("@", $from)[0];
	handPresenceChange($number, 'available', $DBH, $wa, $crawl_time, $whatsspyNotificatons);
}

function onPresenceUnavailable($username, $from, $last) {
	// Ignore last
	global $DBH, $wa, $crawl_time, $whatsspyNotificatons;
	$number = explode("@", $from)[0];
	handPresenceChange($number, 'unavailable', $DBH, $wa, $crawl_time, $whatsspyNotificatons);
}

// retrieve profile pics
function onGetProfilePicture($mynumber, $from, $type, $data) {
	global $DBH, $wa, $whatsspyProfilePath, $whatsspyNotificatons;
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
			    	sendNotification($DBH, null, $whatsspyNotificatons, 'tracker', ['title' => 'Tracker Exception!', 'description' => 'Could not write '. $filename .' to disk!', 'event-type' => 'error']);
			    }
			}
			// Update database
		    $insert = $DBH->prepare('INSERT INTO profilepicture_history (
			            			"number", hash, changed_at)
			   						 VALUES (:number, :hash, NOW());');
			if(checkDatabaseInsert($insert->execute(array(':hash' => $hash,
													   ':number' => $number)))) {
				tracker_log('  -[profile-pic] Inserted new profile picture for '.$number.' ('.$hash.').');
				sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name profile picture', 
																			'description' => ':name has a new profile picture.',
																			'image' => $filename,
																			'number' => $number,
																			'notify_type' => 'profilepic']);
			}
		}
		// Update privacy
		$privacy_status = $DBH->prepare('SELECT "profilepic_privacy" FROM accounts WHERE "id"=:number');
		$privacy_status -> execute(array(':number' => $number));
		$row  = $privacy_status -> fetch();
		if($row['profilepic_privacy'] == true) {
			$update = $DBH->prepare('UPDATE accounts
									SET "profilepic_privacy" = false WHERE "id" = :number;');
			if(checkDatabaseInsert($update->execute(array(':number' => $number)))) {
				tracker_log('  -[profile-pic] '.$number.' has the profilepic privacy option DISABLED!');
				sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name changed the profile picture privacy option', 
																		'description' => ':name has the profile picture privacy option to DISABLED!',
																		'number' => $number,
																		'notify_type' => 'privacy']);
			}
		}
	} else {
		tracker_log('  -[profile-pic] Image type not implemented.');
	}
}

// Retrieve status messages of users
function onGetStatus($mynumber, $from, $requested, $id, $time, $data) {
	global $DBH, $wa, $whatsspyNotificatons;
	$number = explode("@", $from)[0];
	$privacy_enabled = ($time == null ? true : false);

	$query_time = strtotime($time);

	if(!$privacy_enabled) {
		// Check if the user has no status message records yet
		$first_check = $DBH->prepare('SELECT 1 FROM statusmessage_history WHERE "number"=:number');
		$first_check -> execute(array(':number' => $number));

		if($first_check -> rowCount() == 0) {
			// Use first known date
			$insert = $DBH->prepare('INSERT INTO statusmessage_history (
			            			"number", status, changed_at)
			   						 VALUES (:number, :status, to_timestamp(:time));');
			if(checkDatabaseInsert($insert->execute(array(':status' => $data,
													   ':number' => $number,
													   ':time' => (string)$time)))) {
				tracker_log('  -[status-msg] Inserted new status message for '.$number.' ('.$data.').');
				sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name status message', 
																			'description' => ':name has a new status message: \''.$data.'\'.',
																			'number' => $number,
																			'notify_type' => 'statusmsg']);
			}
		} else {
			// User has known records, use the current insertion time

			// Check if any previous record indicate the same message
			$select_latest_statusmsg = $DBH->prepare('SELECT "status" FROM statusmessage_history WHERE "number"=:number ORDER BY changed_at DESC LIMIT 1');
			$select_latest_statusmsg -> execute(array(':number' => $number));
			$latest_statusmsg = $select_latest_statusmsg -> fetch(PDO::FETCH_ASSOC);

			if($latest_statusmsg['status'] != $data) {
				$insert = $DBH->prepare('INSERT INTO statusmessage_history (
				            			"number", status, changed_at)
				   						 VALUES (:number, :status, NOW());');
				if(checkDatabaseInsert($insert->execute(array(':status' => $data,
														   ':number' => $number)))) {

					tracker_log('  -[status-msg] Inserted new status message for '.$number.' ('.$data.').');
					sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name status message', 
																				'description' => ':name has a new status message: \''.$data.'\'.',
																				'number' => $number,
																				'notify_type' => 'statusmsg']);
				}
			}
		}
	}

	// Update privacy
	$privacy_status = $DBH->prepare('SELECT "statusmessage_privacy" FROM accounts WHERE "id"=:number');
	$privacy_status -> execute(array(':number' => $number));
	$row  = $privacy_status -> fetch();
	if($privacy_enabled != (boolean)$row['statusmessage_privacy']) {
		$update = $DBH->prepare('UPDATE accounts
								SET "statusmessage_privacy" = :privacy WHERE "id" = :number;');
		if(checkDatabaseInsert($update->execute(array(':number' => $number, ':privacy' => (int)$privacy_enabled)))) {
			if($privacy_enabled) {
				tracker_log('  -[status-msg] '.$number.' has the statusmessage privacy option ENABLED! ');
				sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name changed the statusmessage privacy option', 
																			'description' => ':name has the statusmessage privacy option to ENABLED!',
																			'number' => $number,
																			'notify_type' => 'privacy']);
			} else {
				tracker_log('  -[status-msg] '.$number.' has the statusmessage privacy option DISABLED! ');
				sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name changed the statusmessage privacy option', 
																			'description' => ':name has the statusmessage privacy option to DISABLED!',
																			'number' => $number,
																			'notify_type' => 'privacy']);
			}
		}
	}


}

/**
  *		Callback Function to check if user actually exists
  */
function onSyncResultNumberCheck($result) {
	global $DBH, $tracking_numbers, $wa, $whatsspyNotificatons;
	// Set whatsapp users verified=true
	foreach ($result->existing as $number) {
		$number = explode("@", $number)[0];
		$update = $DBH->prepare('UPDATE accounts
										SET "verified" = true WHERE "id" = :number;');
		checkDatabaseInsert($update->execute(array(':number' => $number)));
		// Add user to the current tracking system
		array_push($tracking_numbers, $number);
		// Add call for event listener
		$wa->SendPresenceSubscription($number);
		tracker_log('  -[verified] Added verified '.$number.' to the tracking system.');
		sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name is verified', 
																			'description' => ':name is verified as a WA user.',
																			'number' => $number,
																			'notify_type' => 'verify']);
		checkLastSeen($number);
		checkProfilePicture($number);
		checkStatusMessage($number);
	}
	// Set non-whatsapp users inactive
	foreach ($result->nonExisting as $number) {
		$number = explode("@", $number)[0];
		$update = $DBH->prepare('UPDATE accounts
										SET "active" = false WHERE "id" = :number;');
		checkDatabaseInsert($update->execute(array(':number' => $number)));
		tracker_log('  -[verified] Number '.$number.' is NOT a WhatsApp user.');
		sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name is verified', 
																			'description' => ':name is not a WA user.',
																			'number' => $number,
																			'notify_type' => 'verify']);
	}
}

function onGetError($mynumber, $from, $id, $data, $errorType = null) {
	global $DBH, $wa, $pollCount, $whatsspyNotificatons;
	if ($errorType == 'getlastseen') {
        if ($data->getAttribute("code") == '405' || 
        	$data->getAttribute("code") == '403' || 
        	$data->getAttribute("code") == '401') {
        	// Lastseen privacy error:
        	$number = explode("@", $from)[0];
	        $privacy_status = $DBH->prepare('SELECT "lastseen_privacy" FROM accounts WHERE "id"=:number');
			$privacy_status -> execute(array(':number' => $number));
			$row  = $privacy_status -> fetch();
			if($row['lastseen_privacy'] == false) {
				$update = $DBH->prepare('UPDATE accounts
											SET "lastseen_privacy" = true WHERE "id" = :number;');
				if(checkDatabaseInsert($update->execute(array(':number' => $number)))) {
					tracker_log('  -[lastseen] '.$number.' has the lastseen privacy option to ENABLED! ');
					sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name changed the lastseen privacy option', 
																				'description' => ':name has the lastseen privacy option to ENABLED!',
																				'number' => $number,
																				'notify_type' => 'privacy']);
				}
			}
        } else if($data->getAttribute("code") == '404') {
        	tracker_log('  -[lastseen] cannot determine lastseen, ignoring request.');
        } else {
        	tracker_log('  -[lastseen] unknown error for '.$number.'. ');
        	print_r($data);
        }
    } else if ($errorType == 'getprofilepic') {
    	if ($data->getAttribute("code") == '405' || 
        	$data->getAttribute("code") == '403' || 
        	$data->getAttribute("code") == '401') {
        	// picture privacy error
        	$number = explode("@", $from)[0];
        	$privacy_status = $DBH->prepare('SELECT "profilepic_privacy" FROM accounts WHERE "id"=:number');
			$privacy_status -> execute(array(':number' => $number));
			$row  = $privacy_status -> fetch();
			if($row['profilepic_privacy'] == false) {
				$update = $DBH->prepare('UPDATE accounts
											SET "profilepic_privacy" = true WHERE "id" = :number;');
				if(checkDatabaseInsert($update->execute(array(':number' => $number)))) {
					tracker_log('  -[profile-pic] '.$number.' has the profilepic privacy option to ENABLED! ');
					sendNotification($DBH, $wa, $whatsspyNotificatons, 'user', ['title' => ':name changed the profile picture privacy option', 
																				'description' => ':name has the profile picture privacy option to ENABLED!',
																				'number' => $number,
																				'notify_type' => 'privacy']);
				}
			}
        } else if($data->getAttribute("code") == '404') {
        	// No profile picture
        } else {
        	tracker_log('  -[profile-pic] unknown error for '.$number.'. ');
        	print_r($data);
        }
    } else {
    	tracker_log('Unknown error back from WhatsApp:');
    	tracker_log('ID:'.$id.', from:'.$from.', type:'.$errorType);
    	print_r($data);
    }
	// Statusses dont give error messages	
}

function onDisconnect($mynumber, $socket) {
	tracker_log('[disconnect] Whatsapp service disconnected.');
}

function onSendPong($mynumber, $msgid) {
	tracker_log('[keep-alive] Pong received');
}

function onPing($mynumber, $msgid) {
	tracker_log('[keep-alive] Ping received');
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

function resetSocket() {
	global $wa, $tracking_numbers;
	// End any running record where an user is online
	tracker_log('[refresh] Resetting socket to ensure a working connection.');
	// Kill current conecction and login.
	$wa -> disconnect();
	$wa = null;
	$tracking_numbers = [];
	setupWhatsappHandler();
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
		// Force subscription, even for old users
		$wa->SendPresenceSubscription($number['id']);
		if(!in_array($number['id'], $tracking_numbers)) {
			array_push($tracking_numbers, $number['id']);
		}
	}
}

function setupWhatsappHandler() {
	global $wa, $whatsappAuth;
	// bind event handler & tracker_login
	// Setup new Whatsapp session
	// change the "false" to "true" if you want debug information about the WhatsApp connection.
	$wa = new WhatsProt($whatsappAuth['number'], "WhatsApp", $whatsappAuth['debug']);
	$wa->eventManager()->bind('onGetRequestLastSeen', 'onGetRequestLastSeen');
	$wa->eventManager()->bind('onGetError', 'onGetError');
	$wa->eventManager()->bind('onDisconnect', 'onDisconnect');
	$wa->eventManager()->bind("onPresenceAvailable", "onPresenceAvailable");
	$wa->eventManager()->bind("onPresenceUnavailable", "onPresenceUnavailable");
	$wa->eventManager()->bind("onGetStatus", "onGetStatus");
	$wa->eventManager()->bind('onGetSyncResult', 'onSyncResultNumberCheck');
	$wa->eventManager()->bind("onGetProfilePicture", "onGetProfilePicture");
	$wa->eventManager()->bind("onPing", "onPing");
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
		tracker_log('[warning] Tracker was not properly stopped last time, fixing database issues. ');
		// Get last known status
		$last_status = $DBH->prepare('SELECT "end" FROM status_history WHERE "end" IS NOT NULL ORDER BY "end" DESC LIMIT 1');
		$last_status -> execute();
		$row  = $last_status -> fetch();
		$latest_known_record = $row['end'];
		// End any running record where an user is online
		$end_user_session = $DBH->prepare('UPDATE status_history
											SET "end" = :end WHERE "end" IS NULL AND "status" = true;');
		checkDatabaseInsert($end_user_session->execute(array(':end' => $latest_known_record)));
		// Update tracker records
		$end_tracker_session = $DBH->prepare('UPDATE tracker_history SET "end" = :end, "reason" = \'Improper shutdown.\' WHERE "end" IS NULL;');
		checkDatabaseInsert($end_tracker_session->execute(array(':end' => $latest_known_record)));
	}

	$start_tracker_session = $DBH->prepare('INSERT INTO tracker_history ("start") VALUES (NOW());');
	checkDatabaseInsert($start_tracker_session->execute());
}

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

/**
  *		CONTINIOUS TRACKING
  *		Tracking:
  *		- User status changes to track if a user is online/offline
  *		- User lastseen (privacy options)
  *		- User profile pictures (and changes)
  *     - User status message (and changes)
  */
function track() {
	global $DBH, $wa, $tracking_ticks, $tracking_numbers, $whatsspyNotificatons, $crawl_time, $whatsappAuth, $pollCount, $lastseenCount, $statusMsgCount, $picCount, $request_error_queue, $continue_tracker_session;

	$crawl_time = time();
	setupWhatsappHandler();
	retrieveTrackingUsers();
	tracker_log('[init] Started tracking with phonenumber ' . $whatsappAuth['number']);
	if($continue_tracker_session == false) {
		startTrackerHistory();
		sendNotification($DBH, null, $whatsspyNotificatons, 'tracker', ['title' => 'WhatsSpy Public has started tracking!', 'description' => 'tracker has started tracking '.count($tracking_numbers). ' users.', 'event-type' => 'start']);
	} else {
		$continue_tracker_session = false;
	}
	while(true){
		$crawl_time = time();
		// Socket read
		$tick_start = microtime(true);
		$wa->pollMessage();
		$tick_end = microtime(true);
		tracker_log('[poll #'.$pollCount.'] Tracking '. count($tracking_numbers) . ' users.       '."\r", true, false);

		//	1) LAST SEEN PRIVACY
		//
		// Check lastseen
		if($pollCount % calculateTick($tracking_ticks['lastseen']) == 0) {
			tracker_log('[lastseen #'.$lastseenCount.'] Checking '. count($tracking_numbers) . ' users.               ');
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

		//  5) SOCKET RESET AND LOGIN
		//
		// Disconnect and reconnect with whatsapp to prevent dead tracker
		if($pollCount % calculateTick($tracking_ticks['reset-socket']) == calculateTick($tracking_ticks['reset-socket']-40)) {
			resetSocket();
			retrieveTrackingUsers(false);
		}

		//	6) DATABASE ACCOUNT VERIFY CHECK
		//
		// Verify any freshly inserted accounts and check if there really whatsapp users.
		// Check everey 5 minutes.
		// When the user is verified the number is automaticly added to the tracker running DB.
		if($pollCount % calculateTick($tracking_ticks['verify-check']) == 0) {
			verifyTrackingUsers();
		}

		//	7) WHATSAPP PING
		//
		// Keep connection alive (<300s)
		if($pollCount % calculateTick($tracking_ticks['keep-alive']) == 0) {
			tracker_log('[keep-alive] Ping sent.'."\r", true, false);
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

// Selective error handling
$last_error = null;
$continue_tracker_session = false;

// Starting the tracker
tracker_log('------------------------------------------------------------------', false);
tracker_log('|                    WhatsSpy Public Tracker                     |', false);
tracker_log('|                        Proof of Concept                        |', false);
tracker_log('|              Check gitlab.maikel.pro for more info             |', false);
tracker_log('------------------------------------------------------------------', false);
sleep(3);
do {
	// Check database
	if(!checkMinimalDB($DBH, $dbTables)) {
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
		$last_error = $e->getMessage();
		// Check for ignore settings
		if($whatsspyErrorHandling['ignoreConnectionClosed'] == true && $last_error == 'Connection Closed!') {
			tracker_log('[error] Connection to WhatsApp is closed. Attempting direct re-connect.');
			$continue_tracker_session = true;
		} else {
			// Reset DB connection
			$DBH = null;
			$DBH = setupDB($dbAuth);
			// Update tracker session
			$end_tracker_session = $DBH->prepare('UPDATE tracker_history SET "end" = NOW(), "reason" = :error WHERE "end" IS NULL;');
			checkDatabaseInsert($end_tracker_session->execute(array(':error' => get_class($e).': '.$e->getMessage())));
			// End any running record where an user is online
			$end_user_session = $DBH->prepare('UPDATE status_history
												SET "end" = NOW() WHERE "end" IS NULL AND "status" = true;');
			checkDatabaseInsert($end_user_session->execute());

			tracker_log('[error] Tracker exception! '.get_class($e).': '.$e->getMessage());
			if($whatsappAuth['debug']) {
				print_r($e);
			}
			sendNotification($DBH, null, $whatsspyNotificatons, 'tracker', ['title' => 'Tracker Exception!', 'description' => get_class($e).': '.$e->getMessage(), 'event-type' => 'error']);
			if($last_error == 'Connection Closed!') {
				// Wait 30 seconds before reconnecting.
				tracker_log('[retry] Reconnecting to WhatsApp in 30 seconds.');
				sleep(30);
			} else {
				// Wait 120 seconds before reconnecting.
				tracker_log('[retry] Reconnecting to WhatsApp in 120 seconds.');
				sleep(120);
			}
		}
		$last_error = null;
	}
} while(true);


?>
