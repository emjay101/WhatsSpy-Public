<?php
// -----------------------------------------------------------------------
// Whatsspy tracker
// @Author Maikel Zweerink
//	Index.php - contains the webservice supplying information to the webUI.
// -----------------------------------------------------------------------
require_once 'config.php';
require_once 'functions.php';

$DBH  = setupDB($dbAuth);


header("Content-type: application/json; charset=utf-8");
// Process requests
switch($_GET['whatsspy']) {
	// Add an new contact to the whatsspy database (39512f5ea29c597f25483697471ac0b00cbb8088359c219e98fa8bdaf7e079fa)
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
	// Set the status of an contact to inactive.
	// This means the information stays in the database but the user won't be tracked.
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
	// Delete account.
	// REMOVE ALL TRACES OF A USER
	case 'deleteContact':
		// We need the exact ID: this means no 003106 (only 316...)
		if(isset($_GET['number'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);

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

			$result = ['success' => true, 'number' => $number];
			echo json_encode($result);
		} else {
			echo json_encode(['error' => 'No phone number supplied!', 'code' => 400]);
		}
		break;
	// Update the name of an existing contact
	case 'updateName':
		if(isset($_GET['number']) && isset($_GET['name'])) {
			$number = preg_replace('/\D/', '', $_GET['number']);
			$notify = ($_GET['notify_actions'] == 'true' ? true : false);
			$name = $_GET['name']; // do not use htmlentities, AngularJS will protect us
			$update = $DBH->prepare('UPDATE accounts
										SET name = :name, "notify_actions" = :notify_actions WHERE id = :id;');
			$update->execute(array(':id' => $number, ':name' => $name, ':notify_actions' => (int)$notify));
			echo json_encode(['success' => true, 'number' => $number]);
		} else {
			echo json_encode(['error' => 'No name or correct phone number supplied!', 'code' => 400]);
		}
		break;
	// Get global statistics of your whatsspy database.
	// These quries are optimised to perform <2 seconds on an Raspberry Pi.
	case 'getStats':
		// Because this will be the first call for the GUI, we will only check it here.
		// Upgrade DB if it's old:
		checkDBMigration($DBH);

		$select = $DBH->prepare('SELECT n.id, n.name, n."notify_actions", n."lastseen_privacy", n."profilepic_privacy", n."statusmessage_privacy", n.verified, 
										smh.status as "last_statusmessage",
										pph.hash as "profilepic", pph.changed_at as "profilepic_updated",
								(SELECT (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END) FROM status_history WHERE number = n.id ORDER BY start ASC LIMIT 1) "since",
								(SELECT (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END) FROM status_history WHERE status = true AND number = n.id ORDER BY start DESC LIMIT 1) "latest_online",
								(SELECT date_trunc(\'second\', SUM("end" - "start")) as "result" FROM status_history WHERE status = true AND number= n.id  AND "end" IS NOT NULL) "resultall"
								FROM accounts n
								LEFT JOIN profilepicture_history pph
									ON n.id = pph.number AND pph.changed_at = (SELECT changed_at FROM profilepicture_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
								LEFT JOIN statusmessage_history smh
									ON n.id = smh.number AND smh.changed_at = (SELECT changed_at FROM statusmessage_history WHERE number = n.id ORDER BY changed_at DESC LIMIT 1)
								WHERE n.active = true AND n.verified=true
								ORDER BY n.name ASC');
		$select -> execute();
		$result = array();

		// Quick fix, need better solution
		foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $account) {
			$account['profilepic_updated'] = fixTimezone($account['profilepic_updated']);
			$account['latest_online'] = fixTimezone($account['latest_online']);			
			$account['since'] = fixTimezone($account['since']);			
			array_push($result, $account);
		}

		$select_pending = $DBH->prepare('SELECT n.id, n.name FROM accounts n WHERE n.active = true AND n.verified = false');
		$select_pending -> execute();
		$result_pending = $select_pending->fetchAll(PDO::FETCH_ASSOC);

		$tracker_select = $DBH->prepare('SELECT * FROM tracker_history WHERE "start" >= NOW() - \'14 day\'::INTERVAL ORDER BY "start" DESC');
		$tracker_select -> execute();
		$tracker = $tracker_select->fetchAll(PDO::FETCH_ASSOC);

		$start_tracker = null;
		if(count($tracker) > 0) {
			$start_tracker = $tracker[count($tracker)-1]['start'];
		}


		echo json_encode(['accounts' => $result, 'pendingAccounts' => $result_pending, 'tracker' => $tracker, 'trackerStart' => $start_tracker, 'profilePicPath' => $whatsspyWebProfilePath, 'userNotificationPhonenumber' => $whatsspyWhatsAppUserNotification]);

		break;
	// Get specific analytics and information of an given contact.
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

				// Quick fix, need better solution
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

				// Quick fix, need better solution
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

				// Quick fix, need better solution
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

				// Quick fix, need better solution
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
	// Get timeline statistics
	case 'getTimelineStats':
		$data = array();
		$timespan = 60*60*12;
		$since_activity = (time() - ($timespan*8)); // 4 days
		$since_users = (time() - $timespan); // 12 hours
		$till = time(); // Until now

		if(isset($_GET['since']) && is_numeric($_GET['since'])) {
			$since_activity = $_GET['since'];
			$since_users = $_GET['since'];
		}
		// Till only works for Activities, NOT STATUS
		// till overrules since.
		if(isset($_GET['till']) && is_numeric($_GET['till'])) {
			$till = $_GET['till'];
			$since_activity = ($till - ($timespan*8)); // 4 days
		}
		// Get general stats
		$select = $DBH->prepare('(
									(SELECT null as "type", null as "start", null as "end", null as "id", null as "name", null as "msg_status", null as "hash", false as "lastseen_privacy", false as "profilepic_privacy", false as "statusmsg_privacy", null as "changed_at")
									UNION ALL
									(SELECT \'tracker_start\', x.start, x."end", null, null, null, null, null, null, null, x.start FROM tracker_history x WHERE start > :since AND start <= :till)
									UNION ALL
									(SELECT \'tracker_end\', x.start, x."end", null, null, null, null, null, null, null, x."end" FROM tracker_history x WHERE "end" IS NOT NULL AND start > :since AND start <= :till)
									UNION ALL
									(SELECT  \'statusmsg\', null, null, x.number, a.name, x.status, null, null, null, null, x.changed_at FROM statusmessage_history x LEFT JOIN accounts a ON a.id = x.number WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'profilepic\', null, null, x.number, a.name, null, x.hash, null, null, null, x.changed_at FROM profilepicture_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'lastseen_privacy\', null, null, x.number, a.name, null, null, x.privacy, null, null, x.changed_at FROM lastseen_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'profilepic_privacy\', null, null, x.number, a.name, null, null, null, x.privacy, null, x.changed_at FROM profilepic_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
									UNION ALL
									(SELECT  \'statusmsg_privacy\', null, null, x.number, a.name, null, null, null, null, x.privacy, x.changed_at FROM statusmessage_privacy_history x LEFT JOIN accounts a ON a.id = x.number  WHERE changed_at > :since AND changed_at <= :till)
								 ) ORDER BY changed_at DESC;');
		$select->execute(array(':since'=> date('c', $since_activity), ':till'=> date('c', $till)));
		
		$result_activity = array();
		// Quick fix, need better solution
		foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $activity) {	
			$activity['changed_at'] = fixTimezone($activity['changed_at']);			
			array_push($result_activity, $activity);
		}
		// Shift first record: its just a placeholder in the PostGreSQL UNION
		array_shift($result_activity);

		// Ignore user status
		if(!isset($_GET['till'])) {
			// Get user stats
			$select = $DBH->prepare('SELECT  x.sid, x.start, x."end", a.id, a.name, x.status, x.start 
										FROM status_history x 
										LEFT JOIN accounts a ON a.id = x.number
										WHERE x.status = true 
											AND x."end" IS NOT NULL 
											AND x."end" > :since 
											AND x."end" <= :till 
											AND a."active" = true
										ORDER BY x."end" DESC
										LIMIT 200;');
			$select->execute(array(':since'=> date('c', $since_users), ':till'=> date('c', $till)));

			$result_user_status = array();
			// Quick fix, need better solution
			foreach ($select->fetchAll(PDO::FETCH_ASSOC) as $userstatus) {	
				$userstatus['start'] = fixTimezone($userstatus['start']);			
				$userstatus['end'] = fixTimezone($userstatus['end']);			
				array_push($result_user_status, $userstatus);
			}

		}

		if(isset($_GET['till'])) {
			echo json_encode(array('activity' => $result_activity, 'userstatus' => array(), 'since' => $since_activity));
		} else {
			echo json_encode(array('activity' => $result_activity, 'userstatus' => $result_user_status, 'since' => (int)$since_activity, 'till' => $till));
		}

		break;
	// Get global statistics for the whole tracking DB
	case 'getGlobalStats':
		// General tracker info
		$select_global = $DBH->prepare('SELECT
											(SELECT COUNT(1) FROM tracker_history) "tracker_session_count",
											(SELECT start FROM tracker_history ORDER BY start ASC LIMIT 1) "first_tracker_session",
											(SELECT COUNT(1) FROM status_history WHERE status=true) "user_status_count",
											(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) FROM status_history WHERE status=true AND "end" IS NOT NULL) "user_status_count_time",
											(SELECT COUNT(1) FROM profilepicture_history) "profilepicture_count",
											(SELECT COUNT(1) FROM statusmessage_history) "statusmessage_count",
											(SELECT COUNT(1) FROM lastseen_privacy_history) "lastseen_privacy_count",
											(SELECT COUNT(1) FROM profilepic_privacy_history) "profilepic_privacy_count",
											(SELECT COUNT(1) FROM statusmessage_privacy_history) "statusmessage_privacy_count",
											(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND lastseen_privacy = true) "account_lastseen_privacy_enabled",
											(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND lastseen_privacy = false) "account_lastseen_privacy_disabled",
											(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND statusmessage_privacy = true) "account_statusmessage_privacy_enabled",
											(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND statusmessage_privacy = false) "account_statusmessage_privacy_disabled",
											(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND profilepic_privacy = true) "account_profilepic_privacy_enabled",
											(SELECT COUNT(1) FROM accounts WHERE active = true AND verified = true AND profilepic_privacy = false) "account_profilepic_privacy_disabled";');
		$select_global -> execute();
		$result_global = $select_global -> fetch(PDO::FETCH_ASSOC);
		// Fix timezone
		$result_global['first_tracker_session'] = fixTimezone($result_global['first_tracker_session']);


		// Get status count per hour
		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true AND start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
		$select->execute();
		$hour_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true AND start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
		$select->execute();
		$hour_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');
		
		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true AND start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
		$select->execute();
		$hour_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "hour" FROM status_history WHERE status = true GROUP BY TRUNC(EXTRACT(HOUR FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "hour"');
		$select->execute();
		$hour_status_all = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'hour');

		// Get status count per weekday
		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true AND start >= DATE_TRUNC(\'day\', NOW()) GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
		$select->execute();
		$weekday_status_today = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true AND start >= NOW() - \'7 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
		$select->execute();
		$weekday_status_7day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true AND start >= NOW() - \'14 day\'::INTERVAL GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
		$select->execute();
		$weekday_status_14day = cleanTimeIntervals($select->fetchAll(PDO::FETCH_ASSOC), 'weekday');

		$select = $DBH->prepare('SELECT COUNT(1) as "count", ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))/60) as "minutes", TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) as "dow" FROM status_history WHERE status = true GROUP BY TRUNC(EXTRACT(DOW FROM (CASE WHEN ("end" IS NULL) THEN start ELSE "end" END))) ORDER BY "dow"');
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

		// user data for pie charts
		$select_user_status = $DBH->prepare('SELECT n.id, n.name,
								(SELECT COUNT(1) FROM status_history WHERE number = n.id AND status = true AND start >= NOW() - \'7 day\'::INTERVAL) "count_7day",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'7 day\'::INTERVAL AND "end" IS NOT NULL) "seconds_7day",
								(SELECT COUNT(1) FROM status_history WHERE number = n.id AND status = true AND start >= NOW() - \'14 day\'::INTERVAL) "count_14day",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id  AND start >= NOW() - \'14 day\'::INTERVAL AND "end" IS NOT NULL) "seconds_14day",
								(SELECT COUNT(1) FROM status_history WHERE number = n.id AND status = true) "count_all",
								(SELECT ROUND(EXTRACT(\'epoch\' FROM SUM("end" - "start"))) as "result" FROM status_history WHERE status = true AND number= n.id AND "end" IS NOT NULL) "seconds_all"
								FROM accounts n
								WHERE n.active = true AND n.verified=true
								ORDER BY n.name ASC');
		$select_user_status -> execute();
		$result_user_status = $select_user_status->fetchAll(PDO::FETCH_ASSOC);


		echo json_encode(['global_stats' => $result_global, 'user_status_analytics_user' => $result_user_status, 'user_status_analytics_time' => $result_user_status_time]);
		break;
	case 'getAbout':
		echo file_get_contents($whatsspyAboutQAUrl);
		break;
	default:
		echo json_encode(['error' => 'Unknown action!', 'code' => 400]);
}



?>
