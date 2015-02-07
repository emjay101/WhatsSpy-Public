<?php
// -----------------------------------------------------------------------
//	Whatsspy tracker, developed by Maikel Zweerink
//	Config.php - edit this to your needs.
// -----------------------------------------------------------------------


/** General authentication info **/

// Postgres database user & password.
$dbAuth = 			['host' => 'localhost',
					 'port' => '5432',
					 'dbname' => 'whatsspy',
					 'user' => 'whatsspy', 
		   			 'password' => ''];

// Whatsapp login number & secret.
$whatsappAuth = 	['number' => '',
				 	 'secret' => ''];



// Location to store the profile pictures.
// This path has to be absolute and the user running the tracker needs write access.
// include the last / in the path!
$whatsspyProfilePath = '/var/www/whatsspy/images/profilepicture/';

// Relative or absolute path for the web-user.
// include the last / in the path!
$whatsspyWebProfilePath = 'images/profilepicture/';

// Set NMA key for notifications about the tracker,
// Check notifymyandroid.com for more information.
// OPTIONAL
$whatsspyNMAKey = 	'';


// -------------------------------------------------
// You don't need to edit beyond this point
// -------------------------------------------------

// Default URL to request Q&A information for Whatsspy.
// Don't change this URL unless you know what you are doing.
$whatsspyAboutQAUrl = 'https://maikel.pro/service/whatsspy/';

?>