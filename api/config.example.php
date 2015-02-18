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
$whatsappAuth = 	['number' => '',
				 	 'secret' => ''];



// Location to store the profile pictures.
// This path has to be absolute and the user running the tracker needs write access.
// include the last / in the path!
$whatsspyProfilePath = '/var/www/whatsspy/images/profilepicture/';

// Relative or absolute path for the web-user.
// THIS PATH IS FOR USERS ACCESSING THE PROFILE PICTURES FROM THE WEB.
// include the last / in the path!
$whatsspyWebProfilePath = 'images/profilepicture/';

// Set NMA key for notifications about the tracker,
// Check notifymyandroid.com for more information.
// OPTIONAL
$whatsspyNMAKey = 	'';

// You can also set an key for LiveNotifier
// OPTIONAL
$whatsspyLNKey = '';

// -------------------------------------------------
// You don't need to edit beyond this point
// -------------------------------------------------

// Default URL to request Q&A information and version for WhatsSpy Public.
// Don't change this URL unless you know what you are doing.
$whatsspyAboutQAUrl = 'https://maikel.pro/service/whatsspy/';

?>