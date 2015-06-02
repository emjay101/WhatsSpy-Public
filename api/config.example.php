<?php
/* -----------------------------------------------------------------------
*	WhatsSpy Public
*   @Author Maikel Zweerink
*	Config.php - edit this to your needs.
* -----------------------------------------------------------------------
*/


// -------------------------------------------------
// Required authentication & general information
// -------------------------------------------------

// Postgres database user & password.
$dbAuth = 			['host' => 'localhost',
					 'port' => '5432',
					 'dbname' => 'whatsspy',	// Make sure you understand the difference between schema and database in PostgreSQL.
					 'user' => 'whatsspy', 
		   			 'password' => ''];

// Whatsapp login number & secret.
// 'number' may only contain:
// - Digits (no spaces, special characters like +)
// - Needs to be without any prefix 0's. 0031 06 xxx becomes 31 6 xxx (no 0's prefix for both the country code and phonenumber itself).
// 'secret' is the string of characters ending with a '='. Use WART or look at the wiki to retrieve this.
// Debug might be handy if you want debug information about WhatsApp exceptions occuring.
$whatsappAuth = 	['number' => '',
				 	 'secret' => '',
				 	 'debug' => false];

// Password to use in the WhatsSpy Public application
// NOTE: You can disable the password by setting it to false ($whatsspyPublicAuth = false;). This is not advised for installations that are accesable from the internet!
// NOTE 2: Do not use your primary password for this setting.			 	 
$whatsspyPublicAuth = 'whatsspypublic';

// Set your timezone
// Check for all timezones: http://php.net/manual/en/timezones.php
date_default_timezone_set('Europe/Amsterdam');

// -------------------------------------------------
// You can edit beyond this point, but all options below are optional.
// -------------------------------------------------

// Location to store the profile pictures.
// This path has to be absolute and the user running the tracker needs write access.
// NOTE: If you installed whatsspy in /var/www/whatsspy you do not need to change this.
// NOTE 2: Include the last / in the path!
$whatsspyProfilePath = '/var/www/whatsspy/images/profilepicture/';

//	Notifications about tracker and users.
//
//	If you want to recieve information about the tracker status (and specific events of users) you can enable this here. Enter the API key or phonenumber and set enabled to true.
//  NOTE: WhatsApp phonenumber cannot recieve tracker notifications (since WhatsApp connection might be down).
//  NOTE 2: restart the tracker if you change any of these settings.
$whatsspyNotificatons = [// NotifyMyAndroid (notifymyandroid.com)
						 'nma' => 	['enabled' 			=> false,	
								   	 'key' 				=> '',
								   	 'name' 			=> 'NotifyMyAndroid',
								   	 'notify-tracker' 	=> true,
								   	 'notify-user' 		=> false],	
						 // LiveNotifier (livenotifier.net)		   
						 'ln' =>  	['enabled' 			=> false,	
								   	 'key' 				=> '',
								   	 'name' 			=> 'LiveNotifier',
								   	 'notify-tracker' 	=> true,
								   	 'notify-user' 		=> false],
						 // WhatsApp phonenumber
						 'wa' =>  	['enabled' 			=> false,	
								   	 'key' 				=> '',		// Enter <countrycode><phonenumber> here without prefix 0's and no special chars.
								   	 'name' 			=> 'WhatsApp',
								   	 'notify-user' 		=> true],
						 // Script call		   
						 'script' =>['enabled' 			=> false,	
								   	 'cmd' 				=> '',				// Enter a script location+name (like /var/scripts/mycustomnotification.sh).
								   	 'name' 			=> 'Custom Script',
								   	 'notify-tracker' 	=> false,
								   	 'notify-user' 		=> false]];
 /*		Examples of script calls:
  *		/path/to/script.sh "tracker" "Event title" "Event description"
  *		/path/to/script.sh "user" ":user has title" ":user event description" "user notification type" "name" "number"
  *
  *		First parameter is either 'user' or 'tracker':
  *		- In case of 'tracker' the next parameters will be a [title, description, event-type (start, error)].
  *		- In case of 'user' the next parameters will be a [title, description, user notification type (status, statusmsg, profilepic, privacy), name of user, number of user].
  */

// Error handling for the tracker
// ignoreConnectionClosed: Ignore the Connection Closed error and try to connect again without notifying the UI. This error will be shown in the tracker log.
$whatsspyErrorHandling = ['ignoreConnectionClosed' => false];

// Heuristic options for start/end time.
// It takes time to capture a user "online" event. In general these times are very consistent.
// On average there is a lag of 2-3 seconds if a user comes online and 8-12 seconds if a user goes offline (the client will timeout in this timespan).
// NOTE: To disable heuristic options just set all these properties to 0.
// NOTE2: All values here are seconds. For example: The online time stored is: time of recieving the "online" message of WhatsApp - 2 seconds (hence the -2).
// NOTE3: There are 3 Unavailable fases. In each fase there will be checked if the ending time of the user session is after the start of the user session.
//        If for example endtime - 12 seconds is before the starttime of the session, fase 2 will be checked and used.
$whatsspyHeuristicOptions = ['onPresenceAvailableLag' => -2,
							 'onPresenceUnavailableLagFase1' => -12,
							 'onPresenceUnavailableLagFase2' => -8,
							 'onPresenceUnavailableLagFase3' => -5];

// Enable/disable advanced controls to start/shutdown and update the tracker from within the GUI.
// You must first shutdown your tracker before using these start/stop controls (because of the changed uid)
// EXPERIMENTAL
$whatsspyAdvControls = 	['enabled' => false,
				 	 	 'startup' => 'tools/controls/startup.sh',
				 	 	 'shutdown' => 'tools/controls/shutdown.sh',
				 	 	 'update' => 'tools/controls/update.sh'];

// -------------------------------------------------
// You don't need to edit beyond this point
// -------------------------------------------------

// Default URL to request Q&A information and version for WhatsSpy Public.
// Don't change this URL unless you know what you are doing.
$whatsspyAboutQAUrl = 'https://maikel.pro/service/whatsspy/';

?>