<?php
// -----------------------------------------------------------------------
//	@Name WhatsSpy Public
// 	@Author Maikel Zweerink
//	Functions.php - some general functions used in both the webservice and tracker.
// -----------------------------------------------------------------------


// -----------------------------------------------------------------------
//	GENERAL FUNCTIONS
// -----------------------------------------------------------------------

/**
  *		Cut off any 0's that are at the beginning of the string.
  *		- Yes, this is recursion.
  */
function cutZeroPrefix($string) {
	$prefix = '0';
	if (substr($string, 0, strlen($prefix)) == $prefix) {
	    $string = substr($string, strlen($prefix));
	    return cutZeroPrefix($string);
	} else {
		return $string;
	}
}

function isValidSha256($str) {
    return (bool) preg_match('/^[0-9a-f]{64}$/i', $str);
}

/**
  *		Make sure that any timestamp from PostgreSQL meet the requirements for MomentJS
  *		This means timezones are always in the format: 00:00
  */
function fixTimezone($timestamp) {
	global $global_timezone_digits;
	if($timestamp != null) {
		// Set global setter to improve performance
		if($global_timezone_digits == null) {
			if(strpos($timestamp, '+') !== false) {
				$split = explode('+', $timestamp); 
			} else {
				$split = explode('-', $timestamp); 
			}
			if(strlen($split[1]) == 2) {
				// contains format +05
				$global_timezone_digits = 2;
			} else {
				// contains the format +05:30
				$global_timezone_digits = 4;
			}
		}
		// Return requested time.
		if($global_timezone_digits == 2) {
			// contains format +05
			return $timestamp.'00';
		} else {
			// contains the format +05:30
			return $timestamp;
		}
	}
	return $timestamp;
}

/**
  *		Format the PostgreSQL data in such a way you have a entry for each hour/weekday even if there are no records.
  */
function cleanTimeIntervals($data, $type) {
	if($type == 'weekday') {
		$returnData = array();

		$weekdays = [0,1,2,3,4,5,6];
		$i = 0;
		foreach ($weekdays as $weekday) {
			// Check for missing data
			if(isset($data[$i]) && $weekday == (int)@$data[$i]['dow']) {
				// Set value to integer (default string when out of DB)
				$data[$i]['dow'] = (int)$data[$i]['dow'];
				$data[$i]['minutes'] = (int)$data[$i]['minutes'];
				$data[$i]['count'] = (int)$data[$i]['count'];
				array_push($returnData, $data[$i]);
				$i++;
			} else {
				$item['dow'] = $weekday;
				$item['count'] = 0;
				$item['minutes'] = 0;
				array_push($returnData, $item);
			}
		}
		return $returnData;
	} elseif($type == 'hour') {
		$returnData = array();

		$hours = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23];
		$i = 0;
		foreach ($hours as $hour) {
			// Check for missing data
			if(isset($data[$i]) && $hour == (int)@$data[$i]['hour']) {
				// Set value to integer (default string when out of DB)
				$data[$i]['hour'] = (int)$data[$i]['hour'];
				$data[$i]['minutes'] = (int)$data[$i]['minutes'];
				$data[$i]['count'] = (int)$data[$i]['count'];
				array_push($returnData, $data[$i]);
				$i++;
			} else {
				$item['hour'] = $hour;
				$item['count'] = 0;
				$item['minutes'] = 0; // seconds
				array_push($returnData, $item);
			}
		}
		return $returnData;
	}
}

function cleanSecondCounts($data) {
	for ($i=0; $i < count($data); $i++) { 
		if($data[$i]['seconds_today'] == null) {
			$data[$i]['seconds_today'] = 0;
		}
		if($data[$i]['seconds_7day'] == null) {
			$data[$i]['seconds_7day'] = 0;
		}
		if($data[$i]['seconds_14day'] == null) {
			$data[$i]['seconds_14day'] = 0;
		}
		if($data[$i]['seconds_all'] == null) {
			$data[$i]['seconds_all'] = 0;
		}
	}
	return $data;
}

/**
  *		General log function.
  */
function tracker_log($msg, $date = true, $newline = true) {
	if($date) {
		echo '('.date('Y-m-d H:i:s').') ';
	}
	echo $msg;
	if($newline) {
		echo "\n";
	}
}


function tracker_debug($msg, $date = true, $newline = true) {
	global $whatsappAuth;
	if($whatsappAuth['debug'] == true) {
		tracker_log($msg, $date, $newline);
	}
}
/**
  *		Check if the user's config is up to standards and attempt to (temp) fix this.
  */
function checkConfig() {
	global $whatsappAuth, $whatsspyNotificatons, $whatsspyAdvControls, $whatsspyErrorHandling, $whatsspyHeuristicOptions;

	$notice = false;

	// Check if config is filled in.
	if($whatsappAuth['secret'] === '') {
		tracker_log('[config] number and secret fields are required for the tracker to operate.');
		exit();
	}
	// Check if debug is set
	if($whatsappAuth['debug'] !== false && $whatsappAuth['debug'] !== true) {
		tracker_log('[config] $whatsappAuth[\'debug\'] missing (assuming false).');
		$notice = true;
		$whatsappAuth['debug'] = false;
	}
	// Check if new notification structure is used.
	if($whatsspyNotificatons === null) {
		tracker_log('[config] $whatsspyNotificatons missing (notifications disabled).');
		$notice = true;
		$whatsspyNotificatons = [];
	}

	if($whatsspyAdvControls === null) {
		$whatsspyAdvControls = ['enabled' => false];
	}

	if($whatsspyErrorHandling === null) {
		tracker_log('[config] $whatsspyErrorHandling missing (using default options).');
		$notice = true;
		$whatsspyErrorHandling = ['ignoreConnectionClosed' => false];
	}

	if($whatsspyHeuristicOptions === null) {
		tracker_log('[config] $whatsspyHeuristicOptions missing (using default options).');
		$notice = true;
		$whatsspyHeuristicOptions = ['onPresenceAvailableLag' => -2,
							 'onPresenceUnavailableLagFase1' => -12,
							 'onPresenceUnavailableLagFase2' => -8,
							 'onPresenceUnavailableLagFase3' => -5];
	}

	if($notice) {
		tracker_log('[config] Please copy over the missing variables from config.example.php (starting in 2s).');
		sleep(2);
	}
}


// -----------------------------------------------------------------------
//	ACCOUNT SPECIFIC FUNCTIONS
// -----------------------------------------------------------------------



/**
  *		Add a new account to the database. 
  *		Give a name, a phonenumber (id) and request if you a true/false or a array for JSON syntax (for any errors).
  */
function addAccount($name, $account_id, $groups, $array_result = false) {
	global $DBH;
	$number = $account_id;

	// Check before insert
	$check = $DBH->prepare('SELECT "active" FROM accounts WHERE "id"=:id');
	$check->execute(array(':id'=> $number));
	if($check -> rowCount() == 0) {
		$insert = $DBH->prepare('INSERT INTO accounts (id, active, name)
   						 			VALUES (:id, true, :name);');
		$insert->execute(array(':id' => $number,
								':name' => $name));
		// Add any new groups
		foreach ($groups as $group) {
			if($group != '') {
				insertUserInGroup($group, $number);
			}
		}
		if($array_result) {
			return ['success' => true];
		} else {
			return true;
		}
	} else {
		// Account already exists, make sure to re-activate if status=false
		$row  = $check -> fetch();
		if($row['active'] == true) {
			if($array_result) {
				return ['error' => 'Phone already exists!', 'code' => 400];
			} else {
				return false;
			}
		} else {
			$update = $DBH->prepare('UPDATE accounts
									SET "active" = true WHERE id = :number;');
			$update->execute(array(':number' => $number));
			// Remove groups if they are not listed anymore
			$select_group = $DBH->prepare('SELECT gid FROM accounts_to_groups WHERE number = :number');
			$select_group -> execute(array(':number' => $number));
			$processed_groups = [];
			foreach ($select_group->fetchAll(PDO::FETCH_ASSOC) as $group_in_db) {
				if(!in_array($group_in_db['gid'], $groups)) {
					removeUserInGroup($group_in_db['gid'], $number);
				} else {
					array_push($processed_groups, $group_in_db['gid']);
				}
			}
			// Add any new groups
			foreach ($groups as $group) {
				if(!in_array($group, $processed_groups) && $group != '') {
					insertUserInGroup($group, $number);
				}
			}
			if($array_result) {
				return ['success' => true];
			} else {
				return true;
			}
		}
	}
}
/**
  *		Attempt to remove all traces of a user. Returns void.
  */
function removeAccount($number) {
	global $DBH;

	// Delete any statusses
	$delete = $DBH->prepare('DELETE FROM lastseen_privacy_history
								WHERE "number" = :id;');
	$delete->execute(array(':id' => $number));

	$delete = $DBH->prepare('DELETE FROM profilepic_privacy_history
								WHERE "number" = :id;');
	$delete->execute(array(':id' => $number));

	$delete = $DBH->prepare('DELETE FROM profilepicture_history
								WHERE "number" = :id;');
	$delete->execute(array(':id' => $number));

	$delete = $DBH->prepare('DELETE FROM status_history
								WHERE "number" = :id;');
	$delete->execute(array(':id' => $number));

	$delete = $DBH->prepare('DELETE FROM statusmessage_history
								WHERE "number" = :id;');
	$delete->execute(array(':id' => $number));

	$delete = $DBH->prepare('DELETE FROM statusmessage_privacy_history
								WHERE "number" = :id;');
	$delete->execute(array(':id' => $number));

	$delete = $DBH->prepare('DELETE FROM accounts_to_groups
								WHERE "number" = :id;');
	$delete->execute(array(':id' => $number));
	// Delete final record of accounts
	$delete = $DBH->prepare('DELETE FROM accounts
								WHERE id = :id;');
	$delete->execute(array(':id' => $number));
}

/**
  *		Add a new group to the database. 
  *		Give a name and request if you a true/false or a array for JSON syntax (for any errors).
  */
function addGroup($name, $array_result = false) {
	global $DBH;


	// Check before insert
	$check = $DBH->prepare('SELECT 1 FROM groups WHERE "name"=:name');
	$check->execute(array(':name'=> $name));
	if($check -> rowCount() == 0) {
		$insert = $DBH->prepare('INSERT INTO groups (name)
   						 			VALUES (:name);');
		$insert->execute(array(':name' => $name));
		if($array_result) {
			return ['success' => true];
		} else {
			return true;
		}
	} else {
		// Group already exists
		if($array_result) {
			return ['success' => false, 'error' => 'Group already exists!'];
		} else {
			return false;
		}
	}
}

function removeGroup($gid) {
	global $DBH;

	// Update accounts
	$update = $DBH->prepare('DELETE FROM accounts_to_groups WHERE gid = :gid');
	$update->execute(array(':gid' => $gid));

	$delete = $DBH->prepare('DELETE FROM groups
								WHERE gid = :gid;');
	$delete->execute(array(':gid' => $gid));
}

function removeUserInGroup($gid, $number) {
	global $DBH;

	// Update accounts
	$delete = $DBH->prepare('DELETE FROM accounts_to_groups WHERE number = :number AND gid = :gid');
	$delete->execute(array(':number' => $number, ':gid' => $gid));
}

function insertUserInGroup($gid, $number) {
	global $DBH;
	// Update accounts
	$insert = $DBH->prepare('INSERT INTO accounts_to_groups (number, gid) VALUES (:number, :gid)');
	$insert->execute(array(':number' => $number, ':gid' => $gid));
}

function getGroupsFromNumber($number) {
	global $DBH;
	
	$select_groups = $DBH->prepare('SELECT gid FROM accounts_to_groups WHERE number = :number ORDER BY gid ASC');
	$select_groups -> execute(array(':number' => $number));
	return $select_groups->fetchAll(PDO::FETCH_ASSOC);
}

function requireAuth() {
	global $whatsspyPublicAuth;
	if((isset($_SESSION['auth']) && $_SESSION['auth'] == $whatsspyPublicAuth) || $whatsspyPublicAuth == false) {
		$_SESSION['auth'] = $whatsspyPublicAuth;
		return;
	} else if(isset($_SESSION['auth']) && $_SESSION['auth'] != $whatsspyPublicAuth) {
		unset($_SESSION['auth']);
		echo json_encode(['error' => 'No longer authenticated!', 'code' => 403]);
		exit();
	} else {
		echo json_encode(['error' => 'Not authenticated!', 'code' => 403]);
		exit();
	}
}

function requireTokenAuthForGroup($token) {
	global $DBH;
	$select = $DBH->prepare('SELECT gid, name FROM groups WHERE "read_only_token" = :token AND "read_only_token" IS NOT NULL;');
	$select -> execute(array(':token' => $token));
	if($select -> rowCount() == 0) {
		echo json_encode(['error' => 'Not authenticated!', 'code' => 403]);
		exit();
	} else {
		$row = $select -> fetch();
		return $row;
	}
}

function requireTokenAuthForUser($token, $profilepic_hash = null) {
	global $DBH;
	if($profilepic_hash == null) {
		$select = $DBH->prepare('SELECT id FROM accounts WHERE "read_only_token" = :token AND "read_only_token" IS NOT NULL;');
		$select -> execute(array(':token' => $token));
	} else {
		$select = $DBH->prepare('SELECT a.id FROM accounts a
									LEFT JOIN profilepicture_history pph
									ON a.id = pph.number
									WHERE a."read_only_token" = :token AND a."read_only_token" IS NOT NULL AND pph.hash = :hash;');
		$select -> execute(array(':token' => $token, ':hash' => $profilepic_hash));
	}
	if($select -> rowCount() == 0) {
		echo json_encode(['error' => 'Not authenticated!', 'code' => 403]);
		exit();
	} else {
		$row = $select -> fetch();
		return $row;
	}
}

function setAuth($authenticated) {
	if($authenticated) {
		$_SESSION['auth'] = true;
	} else {
		session_destroy();
	}
}

function isAuth() {
	if(isset($_SESSION['auth']) && $_SESSION['auth'] == true) {
		return true;
	} else {
		return false;
	}
}


// type can be 'user' or 'tracker'.
function sendNotification($DBH, $wa, $whatsspyNotificatons, $type, $data) {
	global $application_name;

	if($whatsspyNotificatons == null) {
		return;
	}
	
	foreach ($whatsspyNotificatons as $name => $notificationAgent) {
		if($notificationAgent['enabled'] == true) {
			if($type == 'tracker' && @$notificationAgent['notify-tracker'] == true) {
				// Tracker notification can be sent.
				switch ($name) {
					case 'nma':
						sendNMAMessage($notificationAgent['key'], $application_name, $data['title'], $data['description'], '2');
						break;
					case 'ln':
						sendLNMessage($notificationAgent['key'], $data['title'], $data['description'], @$data['image']);
						break;
					case 'script':
						sendCustomScriptMessage($notificationAgent['cmd'], $type, $data);
						break;
					default:
						break;
				}
			} else if($type == 'user' && $notificationAgent['notify-user'] == true) {
				// User notification can be sent.
				// Check if user is enabled as notifyable
				$user = isUserNotifyable($DBH, $data['number'], $data['notify_type']);
				if($user['notifyable'] == true) {
					$filteredTitle = str_replace(':name', $user['name'], $data['title']);
					$filteredDesc = str_replace(':name', $user['name'], $data['description']);

					switch ($name) {
						case 'nma':
							sendNMAMessage($notificationAgent['key'], $application_name, $filteredTitle, $filteredDesc, '1');
							break;
						case 'ln':
							sendLNMessage($notificationAgent['key'], $filteredTitle, $filteredDesc, @$data['image']);
							break;
						case 'wa':
							sendWhatsAppMessage($wa, $notificationAgent['key'], $filteredDesc, @$data['image']);
							break;
						case 'script':
							sendCustomScriptMessage($notificationAgent['cmd'], $type, $data, $user);
							break;
						default:
							break;
					}
				}
			}
		}
	}
}

function isUserNotifyable($DBH, $number, $notify_type) {
	$select = $DBH->prepare('SELECT name FROM accounts WHERE id = :number AND notify_'.$notify_type.' = true');
	$select -> execute(array(':number' => $number));
	$return = ['notifyable' => false, 'name' => null];
	if($select -> rowCount() > 0) {
		// notify_actions enabled
		$row  = $select -> fetch();
		$return['notifyable'] = true;
		$return['name'] = $row['name'];
	}
	return $return;
}

function sendWhatsAppMessage($wa, $number, $msg, $img = null) {
	if($img == null) {
		$wa -> sendMessage($number, $msg);
	} else {
		$wa -> sendMessage($number, $msg);
		$wa -> sendMessageImage($number, $img);
	}
}

/** Send Msg to NMA */
function sendNMAMessage($NMAKey, $application, $event, $desc, $priority) {
	if($NMAKey == null || $NMAKey == '') {
		return false;
	}
	$post = array('apikey' => $NMAKey,
				  'application' => $application,
				  'event' => $event,
				  'description' => $desc,
				  'priority' => $priority);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,"https://www.notifymyandroid.com/publicapi/notify");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
	$response = curl_exec($ch);
	curl_close ($ch);
	return true;
}
/** Send Msg to Livenotifier */
function sendLNMessage($LNKey, $title, $message, $imgurl) { 
	if($LNKey == null || $LNKey == '') { 
		return false; 
	} 
	$post = array('apikey' => $LNKey, 
				  'title' => $title, 
				  'message' => $message, 
				  'imgurl' => $imgurl); 
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL,"http://api.livenotifier.net/notify" . "?" . http_build_query($post)); 
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); 
	$response = curl_exec($ch); 
	curl_close ($ch); 
	return true; 
} 


/**
  *		This command is very unsafe if not properly used.
  *		DO NOT GENERATE A $cmd BASED ON USER INPUT!
  *		Examples of script calls:
  *		/path/to/script.sh "tracker" "Event title" "Event description"
  *		/path/to/script.sh "user" ":user has title" ":user event description" "user notification type" "name" "number"
  *
  *		First parameter is either 'user' or 'tracker':
  *		- In case of 'tracker' the next parameters will be a [title, description, event-type (start, error)].
  *		- In case of 'user' the next parameters will be a [title, description, user notification type (status, statusmsg, profilepic, privacy, verify), name of user, number of user].
  */
function sendCustomScriptMessage($cmd, $type, $data, $user = null) {
	$safe_cmd = $cmd.' "'.escapeshellcmd($type).'" ';
	if($type == 'tracker') {
		$safe_cmd .= '"'.escapeshellcmd($data['title']).'" "'.escapeshellcmd($data['description']).'" "'.escapeshellcmd($data['event-type']).'" ';
	} else {
		$safe_cmd .= '"'.escapeshellcmd($data['title']).'" "'.escapeshellcmd($data['description']).'" "'.escapeshellcmd($data['notify_type']).'" "'.escapeshellcmd(@$user['name']).'" "'.escapeshellcmd($data['number']).'" ';
	}
	$safe_cmd .=  '> /dev/null &';
	exec($safe_cmd);
}



?>