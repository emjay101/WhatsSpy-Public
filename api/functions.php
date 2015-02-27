<?php
// -----------------------------------------------------------------------
//Whatsspy tracker
// @Maikel Zweerink
//	Functions.php - some general functions used in both the webservice and tracker.
// -----------------------------------------------------------------------

function setupDB($dbAuth) {
	$DBH  = new PDO("pgsql:host=".$dbAuth['host'].";port=".$dbAuth['port'].";dbname=".$dbAuth['dbname'].";user=".$dbAuth['user'].";password=".$dbAuth['password']);
	// Set UTF8
	$DBH->query('SET NAMES \'UTF8\';');
	return $DBH;
}

function checkDBMigration($DBH) {
	/**
	  *		Database option added in 1.3.0
	  *		- Allows user to specificly add notifications for users
	  */
	$select = $DBH->prepare('SELECT column_name  
								FROM information_schema.columns 
								WHERE table_name=\'accounts\' and column_name=\'notify_actions\';');
	$select -> execute();
	if($select -> rowCount() == 0) {
		$alter = $DBH->prepare('ALTER TABLE accounts
  									ADD COLUMN notify_actions boolean NOT NULL DEFAULT false;');
		$alter -> execute();
		if(!$alter) {
			echo 'The following error occured when trying to upgrade DB:';
			print_r($DBH->errorInfo());
			exit();
		}
	}
	/**
	  *		Database option added in 1.
	  *		- 
	  */
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

/** Cut any prefix 0's of an string */
function cutZeroPrefix($string) {
	$prefix = '0';
	if (substr($string, 0, strlen($prefix)) == $prefix) {
	    $string = substr($string, strlen($prefix));
	    return cutZeroPrefix($string);
	} else {
		return $string;
	}
}


/** Add an new account if not an duplicate */
function addAccount($name, $account_id, $array_result = false) {
	global $DBH;

	$name = ($name != null ? htmlentities($name) : null);
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

$global_timezone_digits = null;

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

function cleanTimeIntervals($data, $type) {
	if($type == 'weekday') {
		$returnData = array();

		$weekdays = [0,1,2,3,4,5,6];
		$i = 0;
		foreach ($weekdays as $weekday) {
			// Check for missing data
			if($weekday == (int)$data[$i]['dow']) {
				// Set value to integer (default string when out of DB)
				$data[$i]['dow'] = (int)$data[$i]['dow'];
				$data[$i]['minutes'] = (int)$data[$i]['minutes'];
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
			if($hour == (int)$data[$i]['hour']) {
				// Set value to integer (default string when out of DB)
				$data[$i]['hour'] = (int)$data[$i]['hour'];
				$data[$i]['minutes'] = (int)$data[$i]['minutes'];
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

function tracker_log($msg, $date = true, $newline = true) {
	if($date) {
		echo '('.date('Y-m-d H:i:s').') ';
	}
	echo $msg;
	if($newline) {
		echo "\n";
	}
}

function checkDB($DBH, $dbTables) {
	$where_query = '';
	foreach ($dbTables as $table) {
		$where_query .= ' table_name = :'.$table;
		if(end($dbTables) != $table) {
			$where_query .= ' OR ';
		}
	}
	$select = $DBH->prepare('SELECT COUNT(1) as "table_count"
								FROM   information_schema.tables
								WHERE table_schema = \'public\' AND '.$where_query.';');

	$arguments = array();
	foreach ($dbTables as $table) {
		$arguments[':'.$table] = $table;
	}
	$select->execute($arguments);
	$row  = $select -> fetch();
	if($row['table_count'] == count($dbTables)) {
		return true;
	}
	return false;
}

?>