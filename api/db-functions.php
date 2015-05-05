<?php
// -----------------------------------------------------------------------
//	@Name WhatsSpy Public
// 	@Author Maikel Zweerink
//	DB (migration) functions
// -----------------------------------------------------------------------


/**
  *		Setup database connection and set proper timezone and UTF8 support.
  */
function setupDB($dbAuth) {
	$DBH  = new PDO("pgsql:host=".$dbAuth['host'].";port=".$dbAuth['port'].";dbname=".$dbAuth['dbname'].";user=".$dbAuth['user'].";password=".$dbAuth['password']);
	// Set UTF8
	$DBH->query('SET NAMES \'UTF8\';');
	// Set timezone
	$DBH->query('SET TIME ZONE "'.date_default_timezone_get().'";');
	return $DBH;
}

/**
  *		Do a tracker_log incase anything went wrong with a query.
  */
function checkDatabaseInsert($query) {
	global $DBH;
	if(!$query) {
		$e_code = $DBH->errorInfo()[1];
		$e_msg = $DBH->errorInfo()[2];
		// Database constraint
		if($e_code != 7) {
			tracker_log('[error] Database exception: code: '.$e_code.', message: '.$e_msg);
		} else {
			tracker_debug('[assumed safe error] Database exception: code: '.$e_code.', message: '.$e_msg);
		}
	}	
	return $query;
}

/**
  *		Alter the database in case any updates are present.
  */
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
	  *		Database option added in 1.3.6
	  *		- Indexes for improved performance (up to 4x as fast), noteable on slow machines
	  *		- Custom version table for better DB checking
	  */
	$select = $DBH->prepare('SELECT 1
							   FROM   information_schema.tables 
							   WHERE  table_schema = \'public\'
							   AND    table_name = \'whatsspy_config\'');
	$select -> execute();
	if($select -> rowCount() == 0) {
		$sql_update = '-- 1.3.6 Index updates.
						CREATE INDEX index_account_id
						   ON accounts (id ASC NULLS LAST);
						CREATE INDEX index_tracker_history_end
						   ON tracker_history ("end" ASC NULLS FIRST);
						CREATE INDEX index_tracker_history_start
						   ON tracker_history ("start" DESC);
						CREATE INDEX index_tracker_history_start_end_not_null
						   ON tracker_history ("start" DESC) WHERE "end" IS NOT NULL;
						CREATE INDEX index_status_history_end_status
						   ON status_history (status ASC NULLS LAST, "end" ASC NULLS FIRST);
						CREATE INDEX index_status_history_number_end
						   ON status_history ("number" ASC NULLS LAST, "end" ASC NULLS FIRST);
						CREATE INDEX index_profilepicture_history_number
						   ON profilepicture_history ("number" ASC NULLS LAST);
						CREATE INDEX index_statusmessage_number
						   ON statusmessage_history ("number" ASC NULLS LAST);
						CREATE INDEX index_accounts_active_true_verified_true
						   ON accounts ("id") WHERE active = true AND verified = true;
						CREATE INDEX index_status_history_number_end_is_null
						   ON status_history ("number") WHERE "end" = null;
						CREATE INDEX index_status_history_number_status_true_end_not_null
						   ON status_history ("number") WHERE status = true AND "end" IS NOT NULL;
						CREATE INDEX index_status_history_number_start_status_true_end_not_null
						   ON status_history ("number", "start") WHERE status = true AND "end" IS NOT NULL;
						CREATE INDEX index_status_history_status_true
						   ON status_history ("status") WHERE status = true;
						CREATE INDEX index_status_history_number_status_true_start_desc
						   ON status_history ("number", "start" DESC) WHERE status = true;
						CREATE INDEX index_status_history_sid_start_end_status_true
						   ON status_history ("sid" DESC, "start" DESC, "end" DESC) WHERE status = true;
						CREATE INDEX index_status_history_number_start_asc
						   ON status_history ("number", "start" ASC);
						CREATE INDEX index_profilepicture_history_number_changed_at_desc
						   ON profilepicture_history ("number", "changed_at" DESC);
						CREATE INDEX index_statusmessage_history_number_changed_at_desc
						   ON statusmessage_history ("number", "changed_at" DESC);
						CREATE INDEX index_lastseen_privacy_history_number_changed_at_desc
						   ON lastseen_privacy_history ("number", "changed_at" DESC);
						CREATE INDEX index_profilepic_privacy_history_number_changed_at_desc
						   ON profilepic_privacy_history ("number", "changed_at" DESC);
						CREATE INDEX index_statusmessage_privacy_history_number_changed_at_desc
						   ON statusmessage_privacy_history ("number", "changed_at" DESC);
						CREATE TABLE whatsspy_config
						(
						   db_version integer
						) 
						WITH (
						  OIDS = FALSE
						);

						INSERT INTO whatsspy_config (db_version)
						    VALUES (3);';
		$upgrade = $DBH->exec($sql_update);

		if($DBH->errorCode() != '00000') {
			echo 'The following error occured when trying to upgrade DB:';
			print_r($DBH->errorInfo());
			exit();
		}
	}

	/**
	  *		Database migration added in 1.4.0
	  *		- Now update according to version number which saves countless queries.
	  */
	$select = $DBH->prepare('SELECT db_version
							   FROM   whatsspy_config');
	$select -> execute();
	$row  = $select -> fetch();
	$version = $row['db_version'];
	if($version == 3) {
		/**
		  *		Database option added in 1.4.0
		  *		- Tracker history now gives a reason why it closed.
		  *		- Notifications can be specificly chosen.
		  */
		$version = doMigration($DBH, 
								'-- Add tracker reason
								ALTER TABLE tracker_history
								  ADD COLUMN reason character varying(255);

							   -- Add notification options
							   	ALTER TABLE accounts RENAME notify_actions  TO notify_status;
								ALTER TABLE accounts
								  ADD COLUMN notify_statusmsg boolean NOT NULL DEFAULT false;
								ALTER TABLE accounts
								  ADD COLUMN notify_profilepic boolean NOT NULL DEFAULT false;
								CREATE INDEX index_account_notify_status
   									ON accounts (notify_status ASC NULLS LAST);
   								CREATE INDEX index_account_notify_statusmsg
   									ON accounts (notify_statusmsg ASC NULLS LAST);
								CREATE INDEX index_account_notify_profilepic
								   ON accounts (notify_profilepic ASC NULLS LAST);

							   -- Add groups
							   CREATE TABLE groups
								(
								  gid serial NOT NULL,
								  name character varying(255) NOT NULL,
								  CONSTRAINT pk_groups_gid PRIMARY KEY (gid)
								)
								WITH (
								  OIDS=FALSE
								);
								ALTER TABLE accounts
								  ADD COLUMN group_id integer;
								ALTER TABLE accounts
								  ADD CONSTRAINT fk_group_id FOREIGN KEY (group_id) REFERENCES groups (gid) ON UPDATE NO ACTION ON DELETE NO ACTION;  

								CREATE INDEX index_account_group_id
   									ON accounts (group_id ASC NULLS LAST);
   								CREATE INDEX index_account_id_group_id
   									ON accounts (id ASC NULLS LAST, group_id ASC NULLS LAST);', 
								4);
	} 

	if($version == 4) {
		/**
		  *		Database option added in 1.4.1
		  *		- Added multiple groups
		  */
		$version = doMigration($DBH, 
								'CREATE TABLE accounts_to_groups
								(
								  "number" character(50) NOT NULL,
								  gid integer NOT NULL,
								  CONSTRAINT pk_account_to_group PRIMARY KEY (number, gid)
								)
								WITH (
								  OIDS=FALSE
								);
								INSERT INTO accounts_to_groups (number, gid) (SELECT id, group_id FROM accounts WHERE group_id IS NOT NULL);

								ALTER TABLE accounts
								  DROP COLUMN group_id;

								ALTER TABLE accounts
  									ADD COLUMN notify_timeline boolean NOT NULL DEFAULT false;', 
								5);
	}

	if($version == 5) {
		/**
		  *		Database option added in 1.4.4
		  *		- Added notify for privacy settings
		  */
		$version = doMigration($DBH, 
								'ALTER TABLE accounts
 							   		ADD COLUMN notify_privacy boolean NOT NULL DEFAULT false;', 
								6);
	}

	if($version == 6) {
		/**
		  *		Database option added in 1.5.0
		  *		- Generate read-only tokens for users/groups.
		  */
		$version = doMigration($DBH, 
								'ALTER TABLE accounts
									ADD COLUMN read_only_token character varying(255);
								 ALTER TABLE groups
									ADD COLUMN read_only_token character varying(255);
								ALTER TABLE whatsspy_config
  									ADD COLUMN last_login_attempt timestamp with time zone;', 
								7);
	}

	if($version == 7) {
		/**
		  *		Database option added in 1.5.1
		  *		- Performance options
		  */
		$version = doMigration($DBH, 
								'ALTER TABLE whatsspy_config
								  ADD COLUMN account_show_timeline_length integer NOT NULL DEFAULT 14;
								ALTER TABLE whatsspy_config
								  ADD COLUMN account_show_timeline_tracker boolean NOT NULL DEFAULT true;', 
								8);
	}

}


/**
  *	Try to upgrade the database to a newer version.
  */
function doMigration($DBH, $upgrade_sql, $new_version) {
	$upgrade = $DBH->exec($upgrade_sql);
	if($DBH->errorCode() != '00000') {
		echo 'The following error occured when trying to upgrade DB:';
		print_r($DBH->errorInfo());
		exit();
		return null;
	} else {
		$update = $DBH->prepare('UPDATE whatsspy_config SET db_version = :version;');
		$update -> execute(array(':version' => $new_version));
		return $new_version;
	}
}


/**
  *	Check if the minimal tables are in the database. 
  * @notice This function does not take any migrations into account!
  */
function checkMinimalDB($DBH, $dbTables) {
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