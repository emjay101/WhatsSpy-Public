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

// Set your timezone
// Check for all timezones: http://php.net/manual/en/timezones.php
date_default_timezone_set('Europe/Amsterdam');

// -------------------------------------------------
// You can edit beyond this point, but all options below are optional.
// -------------------------------------------------

// Location to store the profile pictures.
// This path has to be absolute and the user running the tracker needs write access.
// If you installed whatsspy in /var/www/whatsspy you do not need to change this.
// include the last / in the path!
$whatsspyProfilePath = '/var/www/whatsspy/images/profilepicture/';

// Relative or absolute path for the web-user.
// THIS PATH IS FOR USERS ACCESSING THE PROFILE PICTURES (path here above) FROM THE WEB.
// NOTE: usually it is enough to use $whatsspyProfilePath and remove the /var/www
// If you installed whatsspy in /var/www/whatsspy you do not need to change this.
// include the last / in the path!
$whatsspyWebProfilePath = '/whatsspy/images/profilepicture/';

//	Notifications about tracker and users.
//
//	If you want to recieve information about the tracker status (and specific events of users) you can enable this here. Enter the API key or phonenumber and set enabled to true.
//  NOTE: WhatsApp phonenumber cannot recieve tracker notifications (since WhatsApp connection might be down).
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
								   	 'name' 			=> 'Custom Script', // Paramters will be: $type (tracker or user), $title, $description, $number, $filteredTitle, $filteredDesc 
								   	 'notify-tracker' 	=> false,
								   	 'notify-user' 		=> false]];

// -------------------------------------------------
// You don't need to edit beyond this point
// -------------------------------------------------

// Default URL to request Q&A information and version for WhatsSpy Public.
// Don't change this URL unless you know what you are doing.
$whatsspyAboutQAUrl = 'https://maikel.pro/service/whatsspy/';

?>