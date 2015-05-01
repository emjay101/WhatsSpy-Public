<?php
// -----------------------------------------------------------------------
//	@Name WhatsSpy Public
// 	@Author Maikel Zweerink
//	Data.php - Contains required data for the tracker.
// -----------------------------------------------------------------------

// Required tables for the tracker to start (does not include all tables).
$dbTables = ['accounts', 
			 'lastseen_privacy_history', 
			 'profilepic_privacy_history', 
			 'profilepicture_history', 
			 'status_history', 
			 'statusmessage_history', 
			 'statusmessage_privacy_history', 
			 'tracker_history'];

// Setter for the postgresql to momentjs conversion.
// Instead of stateless this improves the performance.
$global_timezone_digits = null;

$application_name = 'WhatsSpy Public';

// Timing for the tracker
// 		DEFAULT CONFIGURATION:
//		- Online/Offline status in realtime.
// 		- Status messages every 2 hours but with the real changed_at time.
//		- Privacy settings for status message / last seen every 2 hours.
//		- Profile picture and privacy setting every 4 hours.	 
//		
//		WARNING:
//		WhatsApp is very picky when it comes to accepting requests. If you spam to much requests (which come from checking privacy setting checking and profile pictures) you may don't get a response.
//		The tracker will warn you about this is the console, but no action will be undertaken.	
//
//		WARNING 2:
//		Do NOT set these timers to 0. keep-alive cannot be higher than 5 minutes.
$tracking_ticks = ['lastseen' 		=> 60*60*2,			// Every 2 hours
			       'statusmsg' 		=> 60*60*2,			// Every 2 hours
			       'profile-pic' 	=> 60*60*4,			// Every 4 hours
			       'refresh-db' 	=> 60*60*1,			// Every hour (cannot be lower than 81 seconds)
			       'verify-check' 	=> 60*5,			// Every 5 minutes
			       'reset-socket' 	=> 60*60*32,		// Every 32 hours  (cannot be lower than 40 seconds)
			       'keep-alive' 	=> 20];				// Every 20 seconds

?>