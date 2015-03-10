<?php
/* -----------------------------------------------------------------------
*	WhatsSpy Public
*   @Author Maikel Zweerink
*	Config.php - edit this to your needs.
* -----------------------------------------------------------------------
*/


/** General authentication info **/

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



// Location to store the profile pictures.
// This path has to be absolute and the user running the tracker needs write access.
// include the last / in the path!
$whatsspyProfilePath = '/var/www/whatsspy/images/profilepicture/';

// Relative or absolute path for the web-user.
// THIS PATH IS FOR USERS ACCESSING THE PROFILE PICTURES (path here above) FROM THE WEB.
// NOTE: usually it is enough to use $whatsspyProfilePath and remove the /var/www
// include the last / in the path!
$whatsspyWebProfilePath = '/whatsspy/images/profilepicture/';

// -------------------------------------------------
// You can edit beyond this point, but all options below are optional.
// -------------------------------------------------

// Set NMA key for notifications about the tracker,
// Check notifymyandroid.com for more information.
// OPTIONAL
$whatsspyNMAKey = 	'';

// You can also set an key for LiveNotifier
// OPTIONAL
$whatsspyLNKey = '';

// Set this varible to recieve notficiations via WhatsApp on your phone.
// In the GUI you can enable user specific tracking of activities etc.
// On default no actions will be sent of any tracking contact. In the UI you need to enable this for a specific contact under "edit".
// USE <countrycode><phonenumber> and do not use prefix 0's in countrycode and phonenumber
// (eg. 0031 0611223300 will become 31 611223300)
// DO NOT use any special chars (spaces, + etc), only type the number (eg. '31611223300')
// NOTE: If you change this meanwhile the tracker is running, you need to restart the tracker.
// NOTE2: If you don't use this, please leave it empty. This greatly reduces queries to PostgreSQL.
// OPTIONAL
$whatsspyWhatsAppUserNotification = '';

// -------------------------------------------------
// You don't need to edit beyond this point
// -------------------------------------------------

// Default URL to request Q&A information and version for WhatsSpy Public.
// Don't change this URL unless you know what you are doing.
$whatsspyAboutQAUrl = 'https://maikel.pro/service/whatsspy/';

?>