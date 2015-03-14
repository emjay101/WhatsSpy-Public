<?php
// -----------------------------------------------------------------------
//	@Name WhatsSpy Public
// 	@Author Maikel Zweerink
//	Index.php - contains the webservice supplying information to the webUI.
// -----------------------------------------------------------------------
require_once 'config.php';
require_once 'db-functions.php';
require_once 'functions.php';

$DBH  = setupDB($dbAuth);


header("Content-type: application/json; charset=utf-8");
// Process any GET requests that are for WhatsSpy Public.
switch($_GET['whatsspy']) {
	/**
	  *		Attempt to create a new contact to the WhatsSpy Public Database (39512f5ea29c597f25483697471ac0b00cbb8088359c219e98fa8bdaf7e079fa)
	  *		@notice This user is not verified as a WhatsApp user, the tracker verifies the contacts.
	  */
	case 'addContact':
		if(isset($_GET['number']) && isset($_GET['countrycode'])) {
			// Name is optional
			$name = (isset($_GET['name']) ? $_GET['name'] : null);
			// cut any prefix zero's of the number and country code.
			$number = cutZeroPrefix($_GET['number']);
			$countrycode = cutZeroPrefix($_GET['countrycode']);
			
			$account = preg_replace('/\D/', '', $countrycode.$number);

			echo json_encode(addAccount($name, $account, true));
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Set a contact to inactive, causing the user will not be tracked anymore but all data will be retained.
	  */
	case 'setContactInactive':
		// We need the exact ID: this means no 003106 (only 316...)
		if(isset($_GET['number'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);
			$update = $DBH->prepare('UPDATE accounts
										SET "active" = false WHERE id = :id;');
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
		if(isset($_GET['number']) && isset($_GET['name'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);

			$notify_status = ($_GET['notify_status'] == 'true' ? true : false);
			$notify_statusmsg = ($_GET['notify_statusmsg'] == 'true' ? true : false);
			$notify_profilepic = ($_GET['notify_profilepic'] == 'true' ? true : false);
			$group_id = ($_GET['group_id'] == 'null' ? null : $_GET['group_id']);

			$name = $_GET['name']; // do not use htmlentities, AngularJS will protect us
			$update = $DBH->prepare('UPDATE accounts
										SET name = :name, notify_status = :notify_status, notify_statusmsg = :notify_statusmsg, notify_profilepic = :notify_profilepic, group_id = :group_id WHERE id = :id;');
			$update->execute(array(':id' => $number, 
								   ':name' => $name, 
								   ':notify_status' => (int)$notify_status,
								   ':notify_statusmsg' => (int)$notify_statusmsg,
								   ':notify_profilepic' => (int)$notify_profilepic,
								   ':group_id' => $group_id));
			echo json_encode(['success' => true, 'number' => $number]);
		} else {
			echo json_encode(['error' => 'No name or correct phone number supplied!', 'code' => 400]);
		}
		break;
	/**
	  *		Attempt to create a new group
	  */
	case 'addGroup':
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
	  *		Get all users and some basic information about them.
	  *		@notice These quries are optimised to perform <2 seconds on an Raspberry Pi. This is why all the contact data is lazy-loaded. Querying for all status data can cost over 60 seconds for 10 contacts and 7 days of data.
	  */
	case 'getStats':
		// Because this will be the first call for the GUI, we will only check it here.
		// Upgrade DB if it's old:
		checkDBMigration($DBH);

		$select = $DBH->prepare('SELECT n.id, n.name, n."notify_status", n."notify_statusmsg", n."notify_profilepic", n."lastseen_privacy", n."group_id", n."profilepic_privacy", n."statusmessage_privacy", n.verified, 
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
			array_push($result, $account);
		}

		$select_pending = $DBH->prepare('SELECT n.id, n.name FROM accounts n WHERE n.active = true AND n.verified = false');
		$select_pending -> execute();
		$result_pending = $select_pending->fetchAll(PDO::FETCH_ASSOC);

		$tracker_select = $DBH->prepare('SELECT "start", "end" FROM tracker_history WHERE "start" >= NOW() - \'14 day\'::INTERVAL ORDER BY "start" DESC LIMIT 50');
		$tracker_select -> execute();
		$tracker = $tracker_select->fetchAll(PDO::FETCH_ASSOC);

		$groups_select = $DBH->prepare('SELECT * FROM groups');
		$groups_select -> execute();
		$groups = $groups_select->fetchAll(PDO::FETCH_ASSOC);
		$null_group['gid'] = null;
		$null_group['name'] = 'No group (all)';
		array_unshift($groups, $null_group);

		$tracker_start_select = $DBH->prepare('SELECT start FROM tracker_history ORDER BY start ASC LIMIT 1');
		$tracker_start_select -> execute();
		$tracker_start = $tracker_start_select -> fetch();
		$start_tracker = $tracker_start['start'];


		echo json_encode(['accounts' => $result, 'pendingAccounts' => $result_pending, 'groups' => $groups, 'tracker' => $tracker, 'trackerStart' => $start_tracker, 'profilePicPath' => $whatsspyWebProfilePath, 'notificationSettings' => $whatsspyNotificatons]);

		break;
	/**
	  *		Get specific stats of a account. You can specify multiple accounts at the same time.
	  */
	case 'getContactStats':
		if (isset($_GET['number'])) {
			$numbers = explode(',', $_GET['number']);
			$accounts = array();

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
				$select = $DBH->prepare('SELECT status, start, "end", sid FROM status_history WHERE status=true AND number = :number AND start >= NOW() - \'14 day\'::INTERVAL ORDER BY start DESC');
				$select->execute(array(':number'=> $number));
				$result_status = array();

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
				array_push($accounts, array('id' => $number, 'user' => $result_user, 'status' => $result_status, 'statusmessages' => $result_statusmsg, 'pictures' => $result_picture, 'advanced_analytics' => $result_advanced_analytics));
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
		$select = $DBH->prepare('(
									(SELECT null as "type", null as "start", null as "end", null as "id", null as "name", 0 as "group_id", null as "msg_status", null as "hash", false as "lastseen_privacy", false as "profilepic_privacy", false as "statusmsg_privacy", null as "changed_at")
									UNION ALL
									(SELECT \'tracker_start\', x.start, x."end", null, null, null, null, null, null, null, null, x.start FROM tracker_history x WHERE start > :since AND start <= :till)
									UNION ALL
									(SELECT \'tracker_end\', x.start, x."end", null, x.reason, null, null, null, null, null, null, x."end" FROM tracker_history x WHERE "end" IS NOT NULL AND "end" > :since AND "end" <= :till)
									UNION ALL
									(SELECT  \'statusmsg\', null, null, x.number, a.name, a.group_id, x.status, null, null, null, null, x.changed_at FROM statusmessage_history x LEFT JOIN accounts a ON a.id = x.number WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'profilepic\', null, null, x.number, a.name, a.group_id, null, x.hash, null, null, null, x.changed_at FROM profilepicture_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'lastseen_privacy\', null, null, x.number, a.name, a.group_id, null, null, x.privacy, null, null, x.changed_at FROM lastseen_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'profilepic_privacy\', null, null, x.number, a.name, a.group_id, null, null, null, x.privacy, null, x.changed_at FROM profilepic_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'statusmsg_privacy\', null, null, x.number, a.name, a.group_id, null, null, null, null, x.privacy, x.changed_at FROM statusmessage_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
								 ) ORDER BY changed_at DESC;');
		$select->execute(array(':since'=> date('c', $since_activity), ':till'=> date('c', $till)));
		
		$result_activity = array();
		foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $activity) {	
			$activity['changed_at'] = fixTimezone($activity['changed_at']);			
			array_push($result_activity, $activity);
		}
		// Shift first record: its just a placeholder in the PostGreSQL UNION
		array_shift($result_activity);

		if($return_statuses){
			// Get user stats
			$select = $DBH->prepare('SELECT  x.sid, x.start, x."end", a.id, a.name, x.status, x.start, a.group_id 
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
		$group = null;
		$group_query_join = '';
		$group_query_join_where = 'WHERE';
		$group_query_and = '';

		if(isset($_GET['group']) && is_numeric($_GET['group'])) {
			$group = $_GET['group'];
			// safe to insert, it is nummeric.
			$group_query_and = 'AND "group_id" = '.$group.' ';
			$group_query_join = 'LEFT JOIN accounts a ON number = a.id WHERE a."group_id" = '.$group.' ';
			$group_query_join_where = $group_query_join.'AND';
		}

		switch ($_GET['component']) {
			case 'global_stats':
				// General tracker info
				$select_global = $DBH->prepare('SELECT
													(SELECT COUNT(1) FROM tracker_history) "tracker_session_count",
													(SELECT start FROM tracker_history ORDER BY start ASC LIMIT 1) "first_tracker_session",
													(SELECT COUNT(1) FROM status_history '.$group_query_join_where.' status=true) "user_status_count",
													(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) FROM status_history '.$group_query_join_where.' status=true AND "end" IS NOT NULL) "user_status_count_time",
													(SELECT COUNT(1) FROM profilepicture_history '.$group_query_join.') "profilepicture_count",
													(SELECT COUNT(1) FROM statusmessage_history '.$group_query_join.') "statusmessage_count",
													(SELECT COUNT(1) FROM lastseen_privacy_history '.$group_query_join.') "lastseen_privacy_count",
													(SELECT COUNT(1) FROM profilepic_privacy_history '.$group_query_join.') "profilepic_privacy_count",
													(SELECT COUNT(1) FROM statusmessage_privacy_history '.$group_query_join.') "statusmessage_privacy_count",
													(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND lastseen_privacy = true '.$group_query_and.') "account_lastseen_privacy_enabled",
													(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND lastseen_privacy = false '.$group_query_and.') "account_lastseen_privacy_disabled",
													(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND statusmessage_privacy = true '.$group_query_and.') "account_statusmessage_privacy_enabled",
													(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND statusmessage_privacy = false '.$group_query_and.') "account_statusmessage_privacy_disabled",
													(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND profilepic_privacy = true '.$group_query_and.') "account_profilepic_privacy_enabled",
													(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND profilepic_privacy = false '.$group_query_and.') "account_profilepic_privacy_disabled";');
				$select_global -> execute();
				$result_global = $select_global -> fetch(PDO::FETCH_ASSOC);
				// Fix timezone
				$result_global['first_tracker_session'] = fixTimezone($result_global['first_tracker_session']);
				
				echo json_encode($result_global);
				break;
			case 'top10_users':
				// Top 10 setup
				$result_top10 = array();

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', NOW()) 
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['today'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'1 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', NOW()) 
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['yesterday'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'2 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', (NOW() - \'1 day\'::INTERVAL)) 
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['2days_ago'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'3 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', (NOW() - \'2 day\'::INTERVAL)) 
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['3days_ago'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= DATE_TRUNC(\'day\', (NOW() - \'4 day\'::INTERVAL)) 
									        	AND start < DATE_TRUNC(\'day\', (NOW() - \'3 day\'::INTERVAL)) 
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['4days_ago'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'1 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['24hours'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'7 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['7days'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'14 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['14days'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND start >= NOW() - \'31 day\'::INTERVAL
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['31days'] = $select->fetchAll(PDO::FETCH_ASSOC);

				$select = $DBH->prepare('SELECT a.name, ROUND(EXTRACT(\'epoch\' FROM SUM(sh."end" - sh."start"))) "online", COUNT(sh.status) "count"
									        FROM accounts a, status_history sh
									        WHERE a.id = sh.number 
									        	AND a.active = true 
									        	AND sh.status = true
									        	AND "end" IS NOT NULL
									        	'.$group_query_and.'
									        GROUP BY a.name 
									        ORDER BY online DESC, count DESC
									        LIMIT 10');
				$select -> execute();
				$result_top10['alltime'] = $select->fetchAll(PDO::FETCH_ASSOC);

				echo json_encode($result_top10);
				break;
			case 'user_status_analytics_user':
				// user data for pie charts
				$select_user_status = $DBH->prepare('SELECT n.id, n.name,
										(SELECT COUNT(1) FROM status_history WHERE number = n.id AND status = true AND start >= DATE_TRUNC(\'day\', NOW())) "count_today",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= DATE_TRUNC(\'day\', NOW()) AND "end" IS NOT NULL) "seconds_today",
										(SELECT COUNT(1) FROM status_history WHERE number = n.id AND status = true AND start >= NOW() - \'7 day\'::INTERVAL) "count_7day",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'7 day\'::INTERVAL AND "end" IS NOT NULL) "seconds_7day",
										(SELECT COUNT(1) FROM status_history WHERE number = n.id AND status = true AND start >= NOW() - \'14 day\'::INTERVAL) "count_14day",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'14 day\'::INTERVAL AND "end" IS NOT NULL) "seconds_14day",
										(SELECT COUNT(1) FROM status_history WHERE number = n.id AND status = true) "count_all",
										(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id AND "end" IS NOT NULL) "seconds_all"
										FROM accounts n
										WHERE n.active = true AND n.verified=true '.$group_query_and.'
										ORDER BY n.name ASC');
				$select_user_status -> execute();
				$result_user_status = cleanSecondCounts($select_user_status->fetchAll(PDO::FETCH_ASSOC));

				echo json_encode($result_user_status);
				break;
			case 'user_status_analytics_time':
				// Get status count per hour
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history '.$group_query_join_where.' status = true AND start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history '.$group_query_join_where.' status = true AND start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');
				
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history '.$group_query_join_where.' status = true AND start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history '.$group_query_join_where.' status = true GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
				$select->execute();
				$hour_status_all = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

				// Get status count per weekday
				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history '.$group_query_join_where.' status = true AND start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
				$select->execute();
				$weekday_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history '.$group_query_join_where.' status = true AND start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
				$select->execute();
				$weekday_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history '.$group_query_join_where.' status = true AND start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
				$select->execute();
				$weekday_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

				$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history '.$group_query_join_where.' status = true GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
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
