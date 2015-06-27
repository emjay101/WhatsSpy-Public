<?php
// -----------------------------------------------------------------------
//	@Name WhatsSpy Public
// 	@Author Maikel Zweerink
//	Index.php - contains the webservice supplying information to the webUI.
// -----------------------------------------------------------------------
error_reporting(E_ERROR);

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
if($_GET['whatsspy'] != 'getProfilePic') {
	header("Content-type: application/json; charset=utf-8");
}

ini_set('session.cookie_lifetime', 25920000); // 300 days
ini_set('session.gc_maxlifetime', 25920000);


require_once 'config.php';
require_once 'db-functions.php';
require_once 'functions.php';

$DBH  = setupDB($dbAuth);

// Setup session management
session_start();

// Process any GET requests that are for WhatsSpy Public.
switch($_GET['whatsspy']) {
	/**
	  *		Do a login attempt with a password	
	  */
	case 'doLogin':
		if(isset($_GET['password'])) {
			if(!isset($whatsspyPublicAuth)) {
				echo json_encode(['error' => 'Password is not set in the config.php', 'code' => 403]);
				exit();
			}
			// Login spam protection
			$select = $DBH->prepare('SELECT 1 FROM whatsspy_config WHERE "last_login_attempt" >= NOW() - \'7 second\'::INTERVAL;');
			$select -> execute();
			if($select -> rowCount() == 0) {
				if($_GET['password'] == $whatsspyPublicAuth) {
					setAuth(true);
					echo json_encode(['success' => true]);
				} else {
					echo json_encode(['error' => 'Incorrect password!', 'code' => 403]);
				}
				$update = $DBH->prepare('UPDATE whatsspy_config
											SET "last_login_attempt" = NOW();');
				$update->execute();
			} else {
				echo json_encode(['error' => 'Please wait 7 seconds before retrying!', 'code' => 400]);
			}
		} else {
			echo json_encode(['error' => 'No password supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Logout
	  */
	case 'doLogout':
		requireAuth();
		setAuth(false);
		echo json_encode(['success' => true]);
		break;
	/**
	  *		Attempt to create a new contact to the WhatsSpy Public Database (39512f5ea29c597f25483697471ac0b00cbb8088359c219e98fa8bdaf7e079fa)
	  *		@notice This user is not verified as a WhatsApp user, the tracker verifies the contacts.
	  */
	case 'addContact':
		requireAuth();
		if(isset($_GET['number']) && isset($_GET['countrycode'])) {
			// Name is optional
			$name = (isset($_GET['name']) ? $_GET['name'] : null);
			// cut any prefix zero's of the number and country code.
			$number = cutZeroPrefix($_GET['number']);
			$countrycode = cutZeroPrefix($_GET['countrycode']);

			$groups = explode(',', ($_GET['groups'] == '' ? null : $_GET['groups']));
			
			$account = preg_replace('/\D/', '', $countrycode.$number);

			echo json_encode(addAccount($name, $account, $groups, true));
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Set a contact to inactive, causing the user will not be tracked anymore but all data will be retained.
	  */
	case 'setContactInactive':
		requireAuth();
		// We need the exact ID: this means no 003106 (only 316...)
		if(isset($_GET['number'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);
			$update = $DBH->prepare('UPDATE accounts
										SET "active" = false, "notify_status" = false, "notify_statusmsg" = false, "notify_profilepic" = false, "notify_timeline" = false WHERE id = :id;');
			$update->execute(array(':id' => $number));
			$result = ['success' => true, 'number' => $number];
			echo json_encode($result);
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Stop tracking the user and delete ALL data of this user.
	  */
	case 'deleteContact':
		requireAuth();
		// We need the exact ID: this means no 003106 (only 316...)
		if(isset($_GET['number'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);
			removeAccount($number);
			$result = ['success' => true, 'number' => $number];
			echo json_encode($result);
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Update information of the account.
	  */
	case 'updateAccount':
		requireAuth();
		if(isset($_GET['number'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);

			$notify_status = ($_GET['notify_status'] == 'true' ? true : false);
			$notify_statusmsg = ($_GET['notify_statusmsg'] == 'true' ? true : false);
			$notify_profilepic = ($_GET['notify_profilepic'] == 'true' ? true : false);
			$notify_privacy = ($_GET['notify_privacy'] == 'true' ? true : false);
			$notify_timeline = ($_GET['notify_timeline'] == 'true' ? true : false);

			$groups = explode(',', ($_GET['groups'] == '' ? null : $_GET['groups']));


			$name = ($_GET['name'] != '' ? $_GET['name'] : null); // do not use htmlentities, AngularJS will protect us
			$update = $DBH->prepare('UPDATE accounts
										SET name = :name, notify_status = :notify_status, notify_statusmsg = :notify_statusmsg, notify_profilepic = :notify_profilepic, notify_privacy = :notify_privacy, notify_timeline = :notify_timeline WHERE id = :id;');
			$update->execute(array(':id' => $number, 
								   ':name' => $name, 
								   ':notify_status' => (int)$notify_status,
								   ':notify_statusmsg' => (int)$notify_statusmsg,
								   ':notify_profilepic' => (int)$notify_profilepic,
								   ':notify_privacy' => (int)$notify_privacy,
								   ':notify_timeline' => (int)$notify_timeline));
			// Update groups
			$select_group = $DBH->prepare('SELECT gid FROM accounts_to_groups WHERE number = :number');
			$select_group -> execute(array(':number' => $number));
			// Remove groups if they are not listed anymore
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
			echo json_encode(['success' => true, 'number' => $number]);
		} else {
			echo json_encode(['error' => 'No name or correct phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Update whatsspy config.
	  */
	case 'updateConfig':
		requireAuth();

		$account_show_timeline_length = (is_numeric($_GET['account_show_timeline_length']) ? $_GET['account_show_timeline_length'] : 14);
		$account_show_timeline_tracker = ($_GET['account_show_timeline_tracker'] == 'true' ? true : false);

		$update = $DBH->prepare('UPDATE whatsspy_config
										SET account_show_timeline_length = :account_show_timeline_length, account_show_timeline_tracker = :account_show_timeline_tracker;');
		$update->execute(array(':account_show_timeline_length' => $account_show_timeline_length, 
							   ':account_show_timeline_tracker' => (int)$account_show_timeline_tracker));

		echo json_encode(['success' => true]);
		break;
	/**
	  *		Generate a new user read-only token
	  */
	case 'generateToken':
		requireAuth();
		if(isset($_GET['number'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);
			if($_GET['type'] == 'read_only') {
				// generate token
				$token = md5(rand().'whatsspy-token-user-'.microtime().rand());
				// update database
				$update = $DBH->prepare('UPDATE accounts SET read_only_token = :token WHERE id = :number;');
				$update -> execute(array(':token' => $token, ':number' => $number));
				$result = ['success' => true, 'number' => $number, 'token' => $token];
			} else {
				$result = ['success' => false, 'error' => 'No known type!'];
			}
			echo json_encode($result);
		} else if(isset($_GET['group']) && is_numeric($_GET['group'])) {
			$group = $_GET['group'];
			if($_GET['type'] == 'read_only') {
				// generate token
				$token = md5(rand().'whatsspy-token-group-'.microtime().rand());
				// update database
				$update = $DBH->prepare('UPDATE groups SET read_only_token = :token WHERE gid = :gid;');
				$update -> execute(array(':token' => $token, ':gid' => $group));
				$result = ['success' => true, 'gid' => $gid, 'token' => $token];
			} else {
				$result = ['success' => false, 'error' => 'No known type!'];
			}
			echo json_encode($result);
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Generate a new user read-only token
	  */
	case 'resetToken':
		requireAuth();
		if(isset($_GET['number'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);
			if($_GET['type'] == 'read_only') {
				$update = $DBH->prepare('UPDATE accounts SET read_only_token = null WHERE id = :number;');
				$update -> execute(array(':number' => $number));
				$result = ['success' => true, 'number' => $number];
			} else {
				$result = ['success' => false, 'error' => 'No known type!'];
			}
			echo json_encode($result);
		} else if(isset($_GET['group']) && is_numeric($_GET['group'])) {
			$group = $_GET['group'];
			if($_GET['type'] == 'read_only') {
				// update database
				$update = $DBH->prepare('UPDATE groups SET read_only_token = null WHERE gid = :gid;');
				$update -> execute(array(':gid' => $group));
				$result = ['success' => true, 'gid' => $gid];
			} else {
				$result = ['success' => false, 'error' => 'No known type!'];
			}
			echo json_encode($result);
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Attempt to create a new group
	  */
	case 'addGroup':
		requireAuth();
		if(isset($_GET['name']) && strlen($_GET['name']) > 0 && strlen($_GET['name']) < 256) {
			$name = $_GET['name'];
			echo json_encode(addGroup($name, true));
		} else {
			echo json_encode(['error' => 'Incorrect name length', 'code' => 400]);
		}
		break;
	/**
	  *		Delete the group
	  */
	case 'deleteGroup':
		requireAuth();
		// We need the exact ID: this means no 003106 (only 316...)
		if(isset($_GET['gid']) && is_numeric($_GET['gid'])) {
			$gid = $_GET['gid'];
			removeGroup($gid);
			$result = ['success' => true, 'gid' => $gid];
			echo json_encode($result);
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Get a profile picture of a account that is being tracked
	  */
	case 'getProfilePic':
		if(isValidSha256($_GET['hash'])) {
			$hash = $_GET['hash'];
			if(isset($_GET['token'])) {
				$token_user = requireTokenAuthForUser($_GET['token'], $hash);
				$token_auth = true;
			} else {
				requireAuth();
				$token_auth = false;
			}
			if(file_exists($whatsspyProfilePath.$hash.'.jpg')) {
				$seconds_to_cache = 2592000;
				$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
				header("Expires: $ts");
				header("Pragma: cache");
				header("Cache-Control: max-age=$seconds_to_cache");
				header('Content-Type: image/jpeg');
				readfile($whatsspyProfilePath.$hash.'.jpg');
			} else {
				header('Content-Type: image/png');
				readfile('../images/profile_pic_placeholder.png');
			}
		} else {
			echo json_encode(['error' => 'Invalid hash!', 'code' => 400]);
		}
		break;
	/**
	  *		Get all users and some basic information about them.
	  *		@notice These quries are optimised to perform <2 seconds on an Raspberry Pi. This is why all the contact data is lazy-loaded. Querying for all status data can cost over 60 seconds for 10 contacts and 7 days of data.
	  */
	case 'getStats':
		// Because this will be the first call for the GUI, we will only check it here.
		// Upgrade DB if it's old:
		checkDBMigration($DBH);
		if(isset($_GET['token'])) {
			$token_user = requireTokenAuthForUser($_GET['token']);
			$token_auth = true;
		} else {
			requireAuth();
			$token_auth = false;
		}

		if($token_auth == false) {
			// Normal information available for the admin of WhatsSpy

			$select_config = $DBH->prepare('SELECT account_show_timeline_length, account_show_timeline_tracker FROM whatsspy_config');
			$select_config -> execute();
			$result_config = $select_config->fetch(PDO::FETCH_ASSOC);
			

			$select = $DBH->prepare('SELECT n.id, n.name, n."read_only_token", n."notify_status", n."notify_statusmsg", n."notify_profilepic", n."notify_privacy", n."notify_timeline", n."lastseen_privacy", n."profilepic_privacy", n."statusmessage_privacy", n.verified, 
											smh.status as "last_statusmessage",
											pph.hash as "profilepic", pph.changed_at as "profilepic_updated",
									(SELECT (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END) FROM status_history WHERE number = n.id ORDER BY start ASC LIMIT 1) "since",
									(SELECT (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END) FROM status_history WHERE status = true AND number = n.id ORDER BY start DESC LIMIT 1) "latest_online"
									FROM accounts n
									LEFT JOIN profilepicture_history pph
										ON n.id = pph.number AND pph.changed_at = (SELECT changed_at FROM profilepicture_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
									LEFT JOIN statusmessage_history smh
										ON n.id = smh.number AND smh.changed_at = (SELECT changed_at FROM statusmessage_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
									WHERE n.active = true AND n.verified=true 
									ORDER BY n.name ASC');
			$select -> execute();
			$result = array();
			foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $account) {
				$account['profilepic_updated'] = fixTimezone($account['profilepic_updated']);
				$account['latest_online'] = fixTimezone($account['latest_online']);			
				$account['since'] = fixTimezone($account['since']);		
				$account['groups'] = getGroupsFromNumber($account['id']);	
				array_push($result, $account);
			}

			$select_pending = $DBH->prepare('SELECT n.id, n.name FROM accounts n WHERE n.active = true AND n.verified = false');
			$select_pending -> execute();
			$result_pending = $select_pending->fetchAll(PDO::FETCH_ASSOC);

			$tracker_select = $DBH->prepare('SELECT "start", "end" FROM tracker_history WHERE "start" >= NOW() - :timespan::INTERVAL ORDER BY "start" DESC LIMIT :limit');
			$tracker_select -> execute(array(':timespan' => $result_config['account_show_timeline_length'].' day', ':limit' => $result_config['account_show_timeline_length']*5));
			$tracker = array();
			foreach ($tracker_select->fetchAll(PDO::FETCH_ASSOC) as $session) {
				$session['start'] = fixTimezone($session['start']);
				$session['end'] = fixTimezone($session['end']);			
				array_push($tracker, $session);
			}

			$groups_select = $DBH->prepare('SELECT * FROM groups ORDER BY gid ASC');
			$groups_select -> execute();
			$groups = $groups_select->fetchAll(PDO::FETCH_ASSOC);
			$null_group['gid'] = null;
			$null_group['name'] = 'All groups';
			array_unshift($groups, $null_group);

			

			$tracker_start_select = $DBH->prepare('SELECT start FROM tracker_history ORDER BY start ASC LIMIT 1');
			$tracker_start_select -> execute();
			$tracker_start = $tracker_start_select -> fetch();
			$start_tracker = fixTimezone($tracker_start['start']);

			$whatsspyNotifcationSettingsProxy = $whatsspyNotificatons;
			$whatsspyAdvControlsProxy = $whatsspyAdvControls;
		} else {
			// PUBLIC UI
			$select = $DBH->prepare('SELECT n.id, n.name, n."lastseen_privacy", n."profilepic_privacy", n."statusmessage_privacy", n.verified, 
											smh.status as "last_statusmessage",
											pph.hash as "profilepic", pph.changed_at as "profilepic_updated",
									(SELECT (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END) FROM status_history WHERE number = n.id ORDER BY start ASC LIMIT 1) "since",
									(SELECT (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END) FROM status_history WHERE status = true AND number = n.id ORDER BY start DESC LIMIT 1) "latest_online"
									FROM accounts n
									LEFT JOIN profilepicture_history pph
										ON n.id = pph.number AND pph.changed_at = (SELECT changed_at FROM profilepicture_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
									LEFT JOIN statusmessage_history smh
										ON n.id = smh.number AND smh.changed_at = (SELECT changed_at FROM statusmessage_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
									WHERE n.active = true AND n.verified=true AND n.id = :id  
									ORDER BY n.name ASC');
			$select -> execute(array(':id' => $token_user['id']));
			$result = array();
			foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $account) {
				$account['profilepic_updated'] = fixTimezone($account['profilepic_updated']);
				$account['latest_online'] = fixTimezone($account['latest_online']);			
				$account['since'] = fixTimezone($account['since']);		
				$account['groups'] = getGroupsFromNumber($account['id']);	
				array_push($result, $account);
			}

			// Fetch the groups that the requested user has
			$groups_select = $DBH->prepare('SELECT g.gid, g.name FROM groups g LEFT JOIN accounts_to_groups atg ON g.gid = atg.gid WHERE atg.number = :id ORDER BY g.gid ASC');
			$groups_select -> execute(array(':id' => $token_user['id']));
			$groups = $groups_select->fetchAll(PDO::FETCH_ASSOC);
		}


		echo json_encode(['accounts' => $result, 'pendingAccounts' => $result_pending, 'groups' => $groups, 'tracker' => $tracker, 'trackerStart' => $start_tracker, 'notificationSettings' => $whatsspyNotifcationSettingsProxy, 'advancedControls' => $whatsspyAdvControlsProxy, 'config' => $result_config]);

		break;
	/**
	  *		Get specific stats of a account. You can specify multiple accounts at the same time.
	  */
	case 'getContactStats':
		if(isset($_GET['token'])) {
			$token_user = requireTokenAuthForUser($_GET['token']);
			$token_auth = true;
			$_GET['number'] = $token_user['id'];
		} else {
			requireAuth();
			$token_auth = false;
		}
		if (isset($_GET['number'])) {
			$numbers = explode(',', $_GET['number']);
			$accounts = array();

			$select_config = $DBH->prepare('SELECT account_show_timeline_length as timespan FROM whatsspy_config');
			$select_config -> execute();
			$result_config = $select_config->fetch(PDO::FETCH_ASSOC);

			foreach($numbers as $number) {
				//
				//	Primary user information (user)
				//
				$select = $DBH->prepare('SELECT  
										lsph.privacy as "lastseen_changed_privacy", lsph.changed_at as "lastseen_changed_privacy_updated",
										pcph.privacy as "profilepic_changed_privacy", pcph.changed_at as "profilepic_changed_privacy_updated",
										smph.privacy as "statusmessage_changed_privacy", smph.changed_at as "statusmessage_changed_privacy_updated",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'1 day\'::INTERVAL AND "end" IS NOT NULL) "online_1day",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'7 day\'::INTERVAL AND "end" IS NOT NULL) "online_7day",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'14 day\'::INTERVAL AND "end" IS NOT NULL) "online_14day",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'31 day\'::INTERVAL AND "end" IS NOT NULL) "online_31day",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND "end" IS NOT NULL) "online_all",
								(SELECT COUNT(1) FROM status_history WHERE status = true AND number = n.id AND start >= NOW() - \'1 day\'::INTERVAL) "count_1day",
								(SELECT COUNT(1) FROM status_history WHERE status = true AND number = n.id AND start >= NOW() - \'7 day\'::INTERVAL) "count_7day",
								(SELECT COUNT(1) FROM status_history WHERE status = true AND number = n.id AND start >= NOW() - \'14 day\'::INTERVAL) "count_14day",
								(SELECT COUNT(1) FROM status_history WHERE status = true AND number = n.id AND start >= NOW() - \'31 day\'::INTERVAL) "count_31day",
								(SELECT COUNT(1) FROM status_history WHERE status = true AND number = n.id) "count_all"
								FROM accounts n
								LEFT JOIN profilepicture_history pph
									ON n.id = pph.number AND pph.changed_at = (SELECT changed_at FROM profilepicture_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
								LEFT JOIN statusmessage_history smh
									ON n.id = smh.number AND smh.changed_at = (SELECT changed_at FROM statusmessage_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
								LEFT JOIN lastseen_privacy_history lsph
									ON n.id = lsph.number AND lsph.changed_at = (SELECT changed_at FROM lastseen_privacy_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
								LEFT JOIN profilepic_privacy_history pcph
									ON n.id = pcph.number AND pcph.changed_at = (SELECT changed_at FROM profilepic_privacy_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
								LEFT JOIN statusmessage_privacy_history smph
									ON n.id = smph.number AND smph.changed_at = (SELECT changed_at FROM statusmessage_privacy_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
								WHERE n.id = :number');
				$select->execute(array(':number'=> $number));
				$result_user = null;

				foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $userProp) {
					$userProp['lastseen_changed_privacy_updated'] = fixTimezone($userProp['lastseen_changed_privacy_updated']);
					$userProp['profilepic_changed_privacy_updated'] = fixTimezone($userProp['profilepic_changed_privacy_updated']);
					$userProp['statusmessage_changed_privacy_updated'] = fixTimezone($userProp['statusmessage_changed_privacy_updated']);
					// Only one record		
					$result_user = $userProp;
				}

				//
				//	Advanced user statistics (advanced_analytics)
				//

				// Get status count per hour
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true AND number = :number AND start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute(array(':number'=> $number));
				$hour_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true AND number = :number AND start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute(array(':number'=> $number));
				$hour_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');
				
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true AND number = :number AND start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute(array(':number'=> $number));
				$hour_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true AND number = :number GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute(array(':number'=> $number));
				$hour_status_all = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				// Get status count per weekday
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true AND number = :number AND start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
				$select->execute(array(':number'=> $number));
				$weekday_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true AND number = :number AND start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
				$select->execute(array(':number'=> $number));
				$weekday_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true AND number = :number AND start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
				$select->execute(array(':number'=> $number));
				$weekday_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true AND number = :number GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
				$select->execute(array(':number'=> $number));
				$weekday_status_all = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');


				// Set all analytics in the data structure
				$result_advanced_analytics = array();

				$result_advanced_analytics['hour_status_today'] = $hour_status_today;
				$result_advanced_analytics['hour_status_7day'] = $hour_status_7day;
				$result_advanced_analytics['hour_status_14day'] = $hour_status_14day;
				$result_advanced_analytics['hour_status_all'] = $hour_status_all;

				$result_advanced_analytics['weekday_status_today'] = $weekday_status_today;
				$result_advanced_analytics['weekday_status_7day'] = $weekday_status_7day;
				$result_advanced_analytics['weekday_status_14day'] = $weekday_status_14day;
				$result_advanced_analytics['weekday_status_all'] = $weekday_status_all;


				//
				//	User statuses (status)
				//
				$select = $DBH->prepare('SELECT status, start, "end", sid FROM status_history WHERE status=true AND number = :number AND start >= NOW() - :timespan::INTERVAL ORDER BY start DESC');
				$select->execute(array(':number'=> $number, ':timespan' => $result_config['timespan'].' day'));
				$result_status = array();
				$result_status_length = $result_config['timespan'];

				foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $status) {
					$status['start'] = fixTimezone($status['start']);			
					$status['end'] = fixTimezone($status['end']);			
					array_push($result_status, $status);
				}


				//
				//	Profile picture history (pictures)
				//
				$select = $DBH->prepare('SELECT hash, changed_at FROM profilepicture_history WHERE number = :number ORDER BY changed_at DESC');
				$select->execute(array(':number'=> $number));
				$result_picture = array();

				foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $status) {
					$status['changed_at'] = fixTimezone($status['changed_at']);				
					array_push($result_picture, $status);
				}

				//
				//	Status message history (statusmessages)
				//
				$select = $DBH->prepare('SELECT status, changed_at FROM statusmessage_history WHERE number = :number ORDER BY changed_at DESC');
				$select->execute(array(':number'=> $number));
				$result_statusmsg = array();

				foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $status) {
					$status['changed_at'] = fixTimezone($status['changed_at']);				
					array_push($result_statusmsg, $status);
				}

				// It might not be an existing number but just add this because of the 14-day limit.
				array_push($accounts, array('id' => $number, 'user' => $result_user, 'status' => $result_status, 'status_length' => $result_status_length, 'statusmessages' => $result_statusmsg, 'pictures' => $result_picture, 'advanced_analytics' => $result_advanced_analytics));
			}
			echo json_encode($accounts);
		} else {
			echo json_encode(['error' => 'No number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Get all required data for the timeline page.
	  *		Also is used for the live feed in the timeline page. GET till is used for activites, GET sid is used for contact statuses.
	  */
	case 'getTimelineStats':
		requireAuth();
		$data = array();
		// Select by default 7 days of activities
		$since_activity = (time() - (60*60*24*7));
		// Select by default 12 hours of statuses
		$sid_status = 0;
		// Select till now
		$till = time();
		// Return statuses?
		$return_statuses = true;
		$type = 'init';

		$return_tracker_info = true;

		if(isset($_GET['showTrackerInfo']) && $_GET['showTrackerInfo'] == 'false') {
			$return_tracker_info = false;
		}

		// Set a since if given
		if(isset($_GET['activities_since']) && is_numeric($_GET['activities_since']) &&
		   isset($_GET['sid_status']) && is_numeric($_GET['sid_status']) ) {
			$since_activity = $_GET['activities_since'];
			$sid_status = $_GET['sid_status'];
			$type = 'since';
		}

		// older_activities only works for Activities, NOT STATUS
		// older_activities overrules since.
		if(isset($_GET['activities_till']) && is_numeric($_GET['activities_till'])) {
			$till = $_GET['activities_till'];
			$since_activity = ($till - (60*60*24*7)); // 7 days
			$return_statuses = false;
			$type = 'activities_till';
		}

		// Get activity records
		$timeline_query = '(
									(SELECT null as "type", null as "start", null as "end", null as "id", null as "name", true as "notify_timeline", null as "msg_status", null as "hash", false as "lastseen_privacy", false as "profilepic_privacy", false as "statusmsg_privacy", null as "changed_at")';
		if($return_tracker_info) {
			$timeline_query .= '	UNION ALL
									(SELECT \'tracker_start\', x.start, x."end", null, null, null, null, null, null, null, null, x.start FROM tracker_history x WHERE start > :since AND start <= :till)
									UNION ALL
									(SELECT \'tracker_end\', x.start, x."end", null, x.reason, null, null, null, null, null, null, x."end" FROM tracker_history x WHERE "end" IS NOT NULL AND "end" > :since AND "end" <= :till)';
		}
		$timeline_query .= '		UNION ALL
									(SELECT  \'statusmsg\', null, null, x.number, a.name, a.notify_timeline, x.status, null, null, null, null, x.changed_at FROM statusmessage_history x LEFT JOIN accounts a ON a.id = x.number WHERE a.active = true AND changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'profilepic\', null, null, x.number, a.name, a.notify_timeline, null, x.hash, null, null, null, x.changed_at FROM profilepicture_history x LEFT JOIN accounts a ON a.id = x.number  WHERE a.active = true AND changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'lastseen_privacy\', null, null, x.number, a.name, a.notify_timeline, null, null, x.privacy, null, null, x.changed_at FROM lastseen_privacy_history x LEFT JOIN accounts a ON a.id = x.number WHERE a.active = true AND changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'profilepic_privacy\', null, null, x.number, a.name, a.notify_timeline, null, null, null, x.privacy, null, x.changed_at FROM profilepic_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE a.active = true AND changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'statusmsg_privacy\', null, null, x.number, a.name, a.notify_timeline, null, null, null, null, x.privacy, x.changed_at FROM statusmessage_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE a.active = true AND changed_at > :since AND changed_at <= :till)) ORDER BY changed_at DESC;';


		$select = $DBH->prepare($timeline_query);
		$select->execute(array(':since'=> date('c', $since_activity), ':till'=> date('c', $till)));
		
		$result_activity = array();
		foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $activity) {	
			$activity['changed_at'] = fixTimezone($activity['changed_at']);
			if($activity['type'] == 'statusmsg' ||
			   $activity['type'] == 'profilepic' ||
			   $activity['type'] == 'lastseen_privacy' ||
			   $activity['type'] == 'profilepic_privacy' ||
			   $activity['type'] == 'statusmsg_privacy') {
				$activity['groups'] = getGroupsFromNumber($activity['id']);	
			} else {
				$activity['groups'] = [];
			}
			array_push($result_activity, $activity);
		}
		// Shift first record: its just a placeholder in the PostGreSQL UNION
		array_shift($result_activity);

		if($return_statuses){
			// Get user stats
			$select = $DBH->prepare('SELECT  x.sid, x.start, x."end", (CASE WHEN (x."end" IS NULL) THEN NULL ELSE ROUND(EXTRACT(\'epoch\' FROM (x."end" - x.start))) END) as "timediff", a.id, a.name, x.status, x.start, a.notify_timeline
										FROM status_history x 
										LEFT JOIN accounts a ON a.id = x.number
										WHERE x.status = true 
											AND x.sid >= :after_sid_status
											AND (CASE WHEN (x."end" IS NULL) THEN x.start ELSE x."end" END) <= :till 
											AND a."active" = true
										ORDER BY x.start DESC
										LIMIT 200;');
			$select->execute(array(':after_sid_status'=> $sid_status, ':till'=> date('c', $till)));

			$result_user_status = array();
			foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $userstatus) {	
				$userstatus['start'] = fixTimezone($userstatus['start']);			
				$userstatus['end'] = fixTimezone($userstatus['end']);	
				$userstatus['groups'] = getGroupsFromNumber($userstatus['id']);			
				array_push($result_user_status, $userstatus);
			}
		}

		echo json_encode(array('type' => $type,
							   'activity' => $result_activity, 
							   'userstatus' => $result_user_status, 
							   'sid' => $sid_status,
							   'since' => (int)$since_activity, 
							   'till' => $till));

		break;
	/**
	  *		Get general statistics of the WhatsSpy Public installation and the users.
	  */
	case 'getGlobalStats':
		if(isset($_GET['token'])) {
			$token_group = requireTokenAuthForGroup($_GET['token']);
			$_GET['group'] = $token_group['gid'];
			$token_auth = true;
			$select_top = '';
		} else {
			requireAuth();
			$token_auth = false;
			$select_top = 'a.id,';
		}
	
		$group = null;
		$group_query_join = '';
		$group_query_join_where = 'WHERE';
		$group_query_acc_join = '';
		$group_query_acc_join_where = 'WHERE';

		if(isset($_GET['group']) && is_numeric($_GET['group'])) {
			$group = $_GET['group'];
			// safe to insert, it is nummeric.
			$group_query_join = 'LEFT JOIN accounts_to_groups ag ON ag.number = t.number WHERE ag.gid = '.$group.' ';
			$group_query_join_where = $group_query_join.'AND';
			$group_query_acc_join = 'LEFT JOIN accounts_to_groups ag ON ag.number = a.id WHERE ag.gid = '.$group.' ';
			$group_query_acc_join_where = $group_query_acc_join.'AND';
		}

		switch ($_GET['component']) {
			case 'global_stats':
				// General tracker info
				$select_global = $DBH->prepare('SELECT
													(SELECT COUNT(1) FROM tracker_history) "tracker_session_count",
													(SELECT start FROM tracker_history ORDER BY start ASC LIMIT 1) "first_tracker_session",
													(SELECT COUNT(1) FROM accounts a '.$group_query_acc_join.') "user_count",
													(SELECT COUNT(1) FROM status_history t '.$group_query_join_where.' t.status=true) "user_status_count",
													(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) FROM status_history t '.$group_query_join_where.' t.status=true AND t."end" IS NOT NULL) "user_status_count_time",
													(SELECT COUNT(1) FROM profilepicture_history t '.$group_query_join.') "profilepicture_count",
													(SELECT COUNT(1) FROM statusmessage_history t '.$group_query_join.') "statusmessage_count",
													(SELECT COUNT(1) FROM lastseen_privacy_history t '.$group_query_join.') "lastseen_privacy_count",
													(SELECT COUNT(1) FROM profilepic_privacy_history t  '.$group_query_join.') "profilepic_privacy_count",
													(SELECT COUNT(1) FROM statusmessage_privacy_history t '.$group_query_join.') "statusmessage_privacy_count",
													(SELECT COUNT(1) FROM accounts a '.$group_query_acc_join_where.' a.active = true AND a.verified = true AND a.lastseen_privacy = true) "account_lastseen_privacy_enabled",
													(SELECT COUNT(1) FROM accounts a '.$group_query_acc_join_where.' a.active = true AND a.verified = true AND a.lastseen_privacy = false) "account_lastseen_privacy_disabled",
													(SELECT COUNT(1) FROM accounts a '.$group_query_acc_join_where.' a.active = true AND a.verified = true AND a.statusmessage_privacy = true) "account_statusmessage_privacy_enabled",
													(SELECT COUNT(1) FROM accounts a '.$group_query_acc_join_where.' a.active = true AND a.verified = true AND a.statusmessage_privacy = false) "account_statusmessage_privacy_disabled",
													(SELECT COUNT(1) FROM accounts a '.$group_query_acc_join_where.' a.active = true AND a.verified = true AND a.profilepic_privacy = true) "account_profilepic_privacy_enabled",
													(SELECT COUNT(1) FROM accounts a '.$group_query_acc_join_where.' a.active = true AND a.verified = true AND a.profilepic_privacy = false) "account_profilepic_privacy_disabled";');
				$select_global -> execute();
				$result_global = $select_global -> fetch(PDO::FETCH_ASSOC);

				// Fix timezone
				$result_global['first_tracker_session'] = fixTimezone($result_global['first_tracker_session']);
				// Add group name
				$result_global['shared_group_name'] = @$token_group['name'];

				echo json_encode($result_global);
				break;
			case 'top_usage_users':
				// Top 10 setup
				$result_topusers = array();

				// Add metadata: the amount of users (in this group)
				$select_meta = $DBH->prepare('SELECT COUNT(1) FROM accounts a '.$group_query_acc_join);
				$select_meta -> execute();
				$result_topusers['meta'] = $select_meta -> fetch(PDO::FETCH_ASSOC);

				$top_limit = (is_numeric($_GET['users']) ? round($_GET['users']) : 10);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', NOW()) 
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['today'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'1 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', NOW()) 
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['yesterday'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'2 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', (NOW() - \'1 day\'::INTERVAL)) 
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['2days_ago'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'3 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', (NOW() - \'2 day\'::INTERVAL)) 
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['3days_ago'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'4 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', (NOW() - \'3 day\'::INTERVAL)) 
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['4days_ago'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'1 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['24hours'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'7 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['7days'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'14 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['14days'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'31 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['31days'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT '.$select_top.' a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a
									        LEFT JOIN status_history sh ON sh.number = a.id
									        '.$group_query_acc_join_where.' a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND "end" IS NOT NULL
									        GROUP BY a.id, a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT :limit');
				$select -> execute(array(':limit' => $top_limit));
				$result_topusers['alltime'] = $select->fetchAll(PDO::FETCH_ASSOC);

				echo json_encode($result_topusers);
				break;
			case 'user_status_analytics_user':
				// user data for pie charts
				$select_user_status = $DBH->prepare('SELECT a.name,
										(SELECT COUNT(1) FROM status_history WHERE number = a.id AND status = true AND start >= DATE_TRUNC(\'day\', NOW())) "count_today",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= a.id  AND start >= DATE_TRUNC(\'day\', NOW()) AND "end" IS NOT NULL) "seconds_today",
										(SELECT COUNT(1) FROM status_history WHERE number = a.id AND status = true AND start >= NOW() - \'7 day\'::INTERVAL) "count_7day",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= a.id  AND start >= NOW() - \'7 day\'::INTERVAL AND "end" IS NOT NULL) "seconds_7day",
										(SELECT COUNT(1) FROM status_history WHERE number = a.id AND status = true AND start >= NOW() - \'14 day\'::INTERVAL) "count_14day",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= a.id  AND start >= NOW() - \'14 day\'::INTERVAL AND "end" IS NOT NULL) "seconds_14day",
										(SELECT COUNT(1) FROM status_history WHERE number = a.id AND status = true) "count_all",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= a.id AND "end" IS NOT NULL) "seconds_all"
										FROM accounts a
										'.$group_query_acc_join_where.' a.active = true AND a.verified=true
										ORDER BY a.name ASC');
				$select_user_status -> execute();
				$result_user_status = cleanSecondCounts($select_user_status->fetchAll(PDO::FETCH_ASSOC));

				echo json_encode($result_user_status);
				break;
			case 'user_status_analytics_time':
				// Get status count per hour
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history t '.$group_query_join_where.' t.status = true AND t.start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history t '.$group_query_join_where.' t.status = true AND t.start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');
				
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history t '.$group_query_join_where.' t.status = true AND t.start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history t '.$group_query_join_where.' t.status = true GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_all = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				// Get status count per weekday
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history t '.$group_query_join_where.' t.status = true AND t.start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "dow"');
				$select->execute();
				$weekday_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history t '.$group_query_join_where.' t.status = true AND t.start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "dow"');
				$select->execute();
				$weekday_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history t '.$group_query_join_where.' t.status = true AND t.start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "dow"');
				$select->execute();
				$weekday_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history t '.$group_query_join_where.' t.status = true GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN (t."end" IS NULL) THEN t.start ELSE t."end" END))) ORDER BY "dow"');
				$select->execute();
				$weekday_status_all = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');


				// Set all analytics in the data structure
				$result_user_status_time = array();

				$result_user_status_time['hour_status_today'] = $hour_status_today;
				$result_user_status_time['hour_status_7day'] = $hour_status_7day;
				$result_user_status_time['hour_status_14day'] = $hour_status_14day;
				$result_user_status_time['hour_status_all'] = $hour_status_all;

				$result_user_status_time['weekday_status_today'] = $weekday_status_today;
				$result_user_status_time['weekday_status_7day'] = $weekday_status_7day;
				$result_user_status_time['weekday_status_14day'] = $weekday_status_14day;
				$result_user_status_time['weekday_status_all'] = $weekday_status_all;

				echo json_encode($result_user_status_time);
				break;
			default:
				echo json_encode(['error' => 'Unknown action!', 'code' => 400]);
				break;
		}
		break;
	/**
	  *		Start the tracker
	  */
	case 'doStartup':
		requireAuth();
		if($whatsspyAdvControls['enabled'] == true) {
			exec($whatsspyAdvControls['startup'], $cmd_result, $cmd_code);
			echo json_encode(['result' => $cmd_result, 'result-code' => $cmd_code]);
		} else {
			echo json_encode(['error' => 'Commands not enabled.', 'code' => 400]);
		}
		break;
	/**
	  *		Stop the tracker
	  */
	case 'doShutdown':
		requireAuth();
		if($whatsspyAdvControls['enabled'] == true) {
			exec($whatsspyAdvControls['shutdown'], $cmd_result, $cmd_code);
			echo json_encode(['result' => $cmd_result, 'result-code' => $cmd_code]);
		} else {
			echo json_encode(['error' => 'Commands not enabled.', 'code' => 400]);
		}
		break;
	/**
	  *		Update the tracker
	  */
	case 'doUpdate':
		requireAuth();
		if($whatsspyAdvControls['enabled'] == true) {
			exec($whatsspyAdvControls['update'], $cmd_result, $cmd_code);
			echo json_encode(['result' => $cmd_result, 'result-code' => $cmd_code]);
		} else {
			echo json_encode(['error' => 'Commands not enabled.', 'code' => 400]);
		}
		break;
	/**
	  *		Get the about page and Questions & Awnsers section. This is also a version check.
	  */
	case 'getAbout':
		if(!isset($_GET['v'])) {
			echo json_encode(['error' => 'Missing version', 'code' => 400]);
			exit();
		} else {
			// The Q&A is different for the versions:
			// - v1.2.0 (Internet Explorer bugs present)
			// - v1.3.0 (Different timing in the tracker)
			// This information is to send a version specific Q&A.
			$postdata = http_build_query(
			    array(
			        'agent' => $_SERVER['HTTP_USER_AGENT'],
			        'lang' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
			        'version' => $_GET['v']
			    )
			);
			$opts = array('http' => array('method'  => 'POST', 'header'  => 'Content-type: application/x-www-form-urlencoded', 'content' => $postdata));
			$context  = stream_context_create($opts);
			echo file_get_contents($whatsspyAboutQAUrl, false, $context);
		}
		break;
	default:
		echo json_encode(['error' => 'Unknown action!', 'code' => 400]);
}



?>
