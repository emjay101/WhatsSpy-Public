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

/**
  *		Make sure that any timestamp from PostgreSQL meet the requirements for MomentJS
  *		This means timezones are always in the format: 00:00
  */
function fixTimezone($timestamp) {
	global $global_timezone_digits;
	if($timestamp != null) {
		// Set global setter to improve performance
		if($global_timezone_digits == null) {
			$split = explode('+', $timestamp);
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
			if($weekday == (int)@$data[$i]['dow']) {
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
			if($hour == (int)@$data[$i]['hour']) {
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

/**
  *		Check if the user's config is up to standards and attempt to (temp) fix this.
  */
function checkConfig() {
	global $whatsappAuth;

	// Check if config is filled in.
	if($whatsappAuth['secret'] == '') {
		tracker_log('[config] number and secret fields are required for the tracker to operate.');
		exit();
	}

}


// -----------------------------------------------------------------------
//	ACCOUNT SPECIFIC FUNCTIONS
// -----------------------------------------------------------------------



/**
  *		Add a new account to the database. 
  *		Give a name, a phonenumber (id) and request if you a true/false or a array for JSON syntax (for any errors).
  */
function addAccount($name, $account_id, $array_result = false) {
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
	// Delete final record of accounts
	$delete = $DBH->prepare('DELETE FROM accounts
								WHERE id = :id;');
	$delete->execute(array(':id' => $number));
}


function checkAndSendWhatsAppNotify($DBH, $wa, $number, $msg, $img = null) {
	global $whatsspyWhatsAppUserNotification;
	if($whatsspyWhatsAppUserNotification == '' || $whatsspyWhatsAppUserNotification == null) {
		return;
	} else {
		// Phonenumber is set, now check if the $number actually has notify_actions on
		$select = $DBH->prepare('SELECT name FROM accounts WHERE id = :number AND notify_actions = true');
		$select -> execute(array(':number' => $number));
		if($select -> rowCount() > 0) {
			// notify_actions enabled
			$row  = $select -> fetch();
			$filteredMsg = str_replace(':name', $row['name'], $msg);
			if($img == null) {
				$wa -> sendMessage($whatsspyWhatsAppUserNotification, $filteredMsg);
			} else {
				$wa -> sendMessage($whatsspyWhatsAppUserNotification, $filteredMsg);
				$wa -> sendMessageImage($whatsspyWhatsAppUserNotification, $img);
			}
			
		} else {
			// notify_actions not enabled
			return;
		}
	}
}


function sendMessage($title, $message, $NMAKey = null, $LNKey = null, $priority = '2', $image = null) {
	if($NMAKey != null && $NMAKey != '') {
		sendNMAMessage($NMAKey, 'WhatsSpy Public', $title, $message, $priority);
	}
	if($LNKey != null && $LNKey != '') {
		sendLNMessage($LNKey, $title, $message, $image);
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



?>