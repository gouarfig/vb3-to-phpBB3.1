<?php

/* 
 * User configuration of the converter
 */

// Album pictures are available (at least) starting from version 3.7 (maybe 3.6? I don't know)
// Uncomment this if you want to convert picture albums to be used by the phpBBgallery extension
// Or comment it out if you don't need to transfer your picture albums
define('CONVERT_ALBUMS', 1);

// It is important to change the name of your default groups if you're using a non-English version of vBulletin:
define('VB_GROUP_GUESTS',				"Unregistered / Not Logged In");
define('VB_GROUP_AWAITING_EMAIL',		"Users Awaiting Email Confirmation");
define('VB_GROUP_AWAITING_MODERATION',	"Users Awaiting Moderation");
define('VB_GROUP_USERS',				"Registered Users");
define('VB_GROUP_MODERATORS',			"Moderators");
define('VB_GROUP_SUPER_MODERATORS',		"Super Moderators");
define('VB_GROUP_ADMINISTRATORS',		"Administrators");

// Here's an example from a Russian board:
//define('VB_GROUP_GUESTS',				"Не Зарегистрированные / Не Вошедшие");
//define('VB_GROUP_AWAITING_EMAIL',		"Ожидающие Подтверждения Через Email");
//define('VB_GROUP_AWAITING_MODERATION',	"(COPPA) Ожидающие Модерации");
//define('VB_GROUP_USERS',				"Зарегистрированные");
//define('VB_GROUP_MODERATORS',			"Модераторы");
//define('VB_GROUP_SUPER_MODERATORS',		"Супер-Модераторы");
//define('VB_GROUP_ADMINISTRATORS',		"Администраторы");
