<?php

/* 
 * User configuration of the converter
 */

// Album pictures are available (at least) starting from version 3.7 (maybe 3.6? I don't know)
// Uncomment this only if you want to convert picture albums to be used by the phpBBgallery extension
// Or comment it out if you don't need to transfer your albums
define('CONVERT_ALBUMS', 1);

// It is important to change the name of your default groups if you're using a non-English version of vBulletin:
define('VB_GROUP_GUESTS',				"Unregistered / Not Logged In");
define('VB_GROUP_AWAITING_EMAIL',		"Users Awaiting Email Confirmation");
define('VB_GROUP_AWAITING_MODERATION',	"Users Awaiting Moderation");
define('VB_GROUP_USERS',				"Registered Users");
define('VB_GROUP_MODERATORS',			"Moderators");
define('VB_GROUP_SUPER_MODERATORS',	"Super Moderators");
define('VB_GROUP_ADMINISTRATORS',		"Administrators");
