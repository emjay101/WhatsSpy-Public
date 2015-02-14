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

function fixTimezone($timestamp) {
	if($timestamp != null) {
		return $timestamp.'00';
	}
	return $timestamp;
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