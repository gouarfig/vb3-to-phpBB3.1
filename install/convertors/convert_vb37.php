<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

/**
* Since this file gets included more than once on one page you are not able to add functions to it.
* Instead use a functions_ file.
*
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

$phpbb_config_php_file = new \phpbb\config_php_file($phpbb_root_path, $phpEx);
extract($phpbb_config_php_file->get_all());
unset($dbpasswd);

$dbms = $phpbb_config_php_file->convert_30_dbms_to_31($dbms);

/**
* $convertor_data provides some basic information about this convertor which is
* used on the initial list of convertors and to populate the default settings
*/
$convertor_data = array(
	'forum_name'	=> 'vBulletin v3.7.x',
	'version'		=> '1.0.0-RC5',
	'phpbb_version'	=> '3.1.2',
	'author'		=> '<a href="https://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=1438256">FredQ</a>',
	'dbms'			=> $dbms,
	'dbhost'		=> $dbhost,
	'dbport'		=> $dbport,
	'dbuser'		=> $dbuser,
	'dbpasswd'		=> '',
	'dbname'		=> $dbname,
	'table_prefix'	=> 'vb_',
	'forum_path'	=> '../forums',
	'author_notes'	=> 'Please refer to the <a href="https://www.phpbb.com/customise/db/converter/vbulletin_%283.7%29_to_phpbb_3.1.2/">phpBB forum</a> if you need more information about the convertor.',
);

/**
* $tables is a list of the tables (minus prefix) which we expect to find in the
* source forum. It is used to guess the prefix if the specified prefix is incorrect
*/
$tables = array(
 	'access',
	'adminhelp',
	'administrator',
	'adminlog',
	'adminmessage',
	'adminutil',
	'album',
	'albumpicture',
	'announcement',
	'announcementread',
	'attachment',
	'attachmentpermission',
	'attachmenttype',
	'attachmentviews',
	'avatar',
	'bbcode',
//	'bookmarksite',
//	'calendar',
//	'calendarcustomfield',
//	'calendarmoderator',
//	'calendarpermission',
	'cpsession',
	'cron',
	'cronlog',
	'customavatar',
	'customprofilepic',
	'datastore',
	'deletionlog',
	'editlog',
	'event',
	'externalcache',
	'faq',
	'forum',
	'forumpermission',
	'forumprefixset',
	'forumread',
	'groupmessage',
	'groupmessage_hash',
//	'holiday',
	'humanverify',
	'hvanswer',
	'hvquestion',
	'icon',
	'imagecategory',
	'imagecategorypermission',
	'infraction',
	'infractionban',
	'infractiongroup',
	'infractionlevel',
	'language',
	'mailqueue',
	'moderation',
	'moderator',
	'moderatorlog',
	'notice',
	'noticecriteria',
	'passwordhistory',
//	'paymentapi',
//	'paymentinfo',
//	'paymenttransaction',
	'phrase',
	'phrasetype',
	'picture',
	'picturecomment',
	'picturecomment_hash',
//	'plugin',
	'pm',
	'pmreceipt',
	'pmtext',
	'podcast',
	'podcastitem',
	'poll',
	'pollvote',
	'post',
	'postedithistory',
	'posthash',
	'postindex',
	'postlog',
	'postparsed',
	'prefix',
	'prefixset',
//	'product',
//	'productcode',
//	'productdependency',
	'profilefield',
	'profilefieldcategory',
	'profilevisitor',
	'ranks',
//	'reminder',
	'reputation',
	'reputationlevel',
//	'rssfeed',
//	'rsslog',
	'search',
	'session',
	'setting',
	'settinggroup',
	'sigparsed',
	'sigpic',
	'smilie',
//	'socialgroup',
//	'socialgroupmember',
//	'socialgrouppicture',
	'spamlog',
	'stats',
	'strikes',
	'style',
	'subscribeevent',
	'subscribeforum',
	'subscribethread',
	'subscription',
	'subscriptionlog',
	'subscriptionpermission',
	'tachyforumcounter',
	'tachyforumpost',
	'tachythreadcounter',
	'tachythreadpost',
	'tag',
	'tagsearch',
	'tagthread',
	'template',
	'templatehistory',
	'thread',
	'threadrate',
	'threadread',
	'threadredirect',
	'threadviews',
//	'upgradelog',
	'user',
	'useractivation',
	'userban',
	'userchangelog',
	'usercss',
	'usercsscache',
	'userfield',
	'usergroup',
	'usergroupleader',
	'usergrouprequest',
	'userlist',
	'usernote',
	'userpromotion',
	'usertextfield',
	'usertitle',
	'visitormessage',
	'visitormessage_hash',
	'word',
);

/**
* $config_schema details how the board configuration information is stored in the source forum.
*
* 'table_format' can take the value 'file' to indicate a config file. In this case array_name
* is set to indicate the name of the array the config values are stored in
* Example of using a file:
* $config_schema = array(
* 	'table_format'	=>	'file',
* 	'filename'	=>	'NAME OF FILE', // If the file is not in the root directory, the path needs to be added with no leading slash
* 	'array_name' => 'NAME OF ARRAY', // Only used if the configuration file stores the setting in an array.
* 	'settings'		=>	array(
*        'board_email' => 'SUPPORT_EMAIL', // target config name => source target name
* 	)
* );
* 'table_format' can be an array if the values are stored in a table which is an assosciative array
* (as per phpBB 2.0.x)
* If left empty, values are assumed to be stored in a table where each config setting is
* a column (as per phpBB 1.x)
*
* In either of the latter cases 'table_name' indicates the name of the table in the database
*
* 'settings' is an array which maps the name of the config directive in the source forum
* to the config directive in phpBB3. It can either be a direct mapping or use a function.
* Please note that the contents of the old config value are passed to the function, therefore
* an in-built function requiring the variable passed by reference is not able to be used. Since
* empty() is such a function we created the function is_empty() to be used instead.
*/
$config_schema = array(
	'table_name'	=>	'setting',
	'table_format'	=>	array('varname' => 'value'),
	'settings'		=>	array(
		'allow_avatar'			=> 'avatarenabled',
		'allow_bbcode'			=> 'allowbbcode',
		'allow_emailreuse'		=> '!requireuniqueemail',
		'allow_smilies'			=> 'allowsmilies',
		'allow_privmsg'			=> 'enablepms',
		'allow_quick_reply'		=> 'is_positive(quickreply)',	// This switch allows for the quick reply to be disabled board-wide. When enabled, forum specific settings will be used to determine whether the quick reply is displayed in individual forums.
		'avatar_filesize'		=> 'vb_get_avatar_filesize()',
		'avatar_max_height'		=> 'vb_get_avatar_max_height()',
		'avatar_max_width'		=> 'vb_get_avatar_max_width()',
		'board_contact'			=> 'webmasteremail',
		'board_disable'			=> '!bbactive',
		'board_email'			=> 'webmasteremail',
		'board_email_form'		=> 'secureemail',
		//'board_timezone'		=> 'timeoffset',
		'board_hide_emails'		=> '!displayemails',
// *** It's probably better not to convert these ***
//		'cookie_domain'			=> 'cookiedomain',
//		'cookie_path'			=> 'cookiepath',
// ***
		'coppa_enable'			=> 'usecoppa',
		'coppa_fax'				=> 'faxnumber',
		'coppa_mail'			=> 'webmasteremail',
		//'default_dateformat'	=> '',
		//unsure?
		'enable_confirm'		=> 'enableemail',
		'email_enable'			=> 'enableemail',
		'flood_interval'		=> 'floodchecktime',
		'gzip_compress'			=> 'gzipoutput',
		'hot_threshold'			=> 'hotnumberposts',
		'img_create_thumbnail'	=> 'is_positive(attachthumbs)',
		'img_display_inlined'	=> 'is_positive(viewattachedimages)',
		'img_max_thumb_width'	=> 'attachthumbssize',
		'limit_load'			=> 'loadlimit',
		'max_attachments'		=> 'attachlimit',
		// or 'max_attachments'		=> 'maximages',
		'max_name_chars'		=> 'maxuserlength',
		'max_poll_options'		=> 'maxpolloptions',
		'max_post_chars'		=> 'postmaxchars',
		//? 'max_sig_chars'			=> 'sigmax',
		'posts_per_page'		=> 'maxposts',
		'require_activation'	=> 'verifyemail',
		'search_anonymous_interval'	=> 'searchfloodtime',
		'search_interval'		=> 'searchfloodtime',
		'site_desc'				=> 'vb_set_encoding(description)',
		'sitename'				=> 'vb_set_encoding(bbtitle)',
		'topics_per_page'		=> 'maxthreads',
		'smtp_delivery'			=> 'use_smtp',
		'smtp_host'				=> 'smtp_host',
		'smtp_port'				=> 'smtp_port',
		'smtp_username'			=> 'smtp_user',
		'smtp_password'			=> 'smtp_pass',
	)
);

/**
* $test_file is the name of a file which is present on the source
* forum which can be used to check that the path specified by the
* user was correct
*/
$test_file = 'showthread.php';

/**
* If this is set then we are not generating the first page of information but getting the conversion information.
*/
if (!$get_info)
{

	$src_db->sql_return_on_error(false);

	// Let us set a temporary config variable for user id incrementing
	$sql = "SELECT userid
		FROM {$convert->src_table_prefix}user
		WHERE userid = 1";
	$result = $src_db->sql_query($sql);
	$user_id = (int) $src_db->sql_fetchfield('userid');
	$src_db->sql_freeresult($result);

	// If there is a user id 1, we need to increment user ids. :/
	if ($user_id === 1)
	{
		// Try to get the maximum user id possible...
		$sql = "SELECT MAX(userid) AS max_user_id
			FROM {$convert->src_table_prefix}user";
		$result = $src_db->sql_query($sql);
		$user_id = (int) $src_db->sql_fetchfield('max_user_id');
		$src_db->sql_freeresult($result);

		set_config('increment_user_id', ($user_id + 1), true);
	}
	else
	{
		set_config('increment_user_id', 0, true);
	}

	// Overwrite maximum avatar width/height
	@define('DEFAULT_AVATAR_X_CUSTOM', get_config_value('avatar_max_width'));
	@define('DEFAULT_AVATAR_Y_CUSTOM', get_config_value('avatar_max_height'));

	@define('VB_GROUP_GUESTS', "Unregistered / Not Logged In");
	@define('VB_GROUP_AWAITING_EMAIL', "Users Awaiting Email Confirmation");
	@define('VB_GROUP_AWAITING_MODERATION', "Users Awaiting Moderation");
	@define('VB_GROUP_USERS', "Registered Users");
	@define('VB_GROUP_MODERATORS', "Moderators");
	@define('VB_GROUP_SUPER_MODERATORS', "Super Moderators");
	@define('VB_GROUP_ADMINISTRATORS', "Administrators");

/**
*	Description on how to use the convertor framework.
*
*	'schema' Syntax Description
*		-> 'target'			=> Target Table. If not specified the next table will be handled
*		-> 'primary'		=> Primary Key. If this is specified then this table is processed in batches
*		-> 'query_first'	=> array('target' or 'src', Query to execute before beginning the process
*								(if more than one then specified as array))
*		-> 'function_first'	=> Function to execute before beginning the process (if more than one then specified as array)
*								(This is mostly useful if variables need to be given to the converting process)
*		-> 'test_file'		=> This is not used at the moment but should be filled with a file from the old installation
*
*		// DB Functions
*		'distinct'	=> Add DISTINCT to the select query
*		'where'		=> Add WHERE to the select query
*		'group_by'	=> Add GROUP BY to the select query
*		'left_join'	=> Add LEFT JOIN to the select query (if more than one joins specified as array)
*		'having'	=> Add HAVING to the select query
*
*		// DB INSERT array
*		This one consist of three parameters
*		First Parameter:
*							The key need to be filled within the target table
*							If this is empty, the target table gets not assigned the source value
*		Second Parameter:
*							Source value. If the first parameter is specified, it will be assigned this value.
*							If the first parameter is empty, this only gets added to the select query
*		Third Parameter:
*							Custom Function. Function to execute while storing source value into target table.
*							The functions return value get stored.
*							The function parameter consist of the value of the second parameter.
*
*							types:
*								- empty string == execute nothing
*								- string == function to execute
*								- array == complex execution instructions
*
*		Complex execution instructions:
*		@todo test complex execution instructions - in theory they will work fine
*
*							By defining an array as the third parameter you are able to define some statements to be executed. The key
*							is defining what to execute, numbers can be appended...
*
*							'function' => execute function
*							'execute' => run code, whereby all occurrences of {VALUE} get replaced by the last returned value.
*										The result *must* be assigned/stored to {RESULT}.
*							'typecast'	=> typecast value
*
*							The returned variables will be made always available to the next function to continue to work with.
*
*							example (variable inputted is an integer of 1):
*
*							array(
*								'function1'		=> 'increment_by_one',		// returned variable is 2
*								'typecast'		=> 'string',				// typecast variable to be a string
*								'execute'		=> '{RESULT} = {VALUE} . ' is good';', // returned variable is '2 is good'
*								'function2'		=> 'replace_good_with_bad',				// returned variable is '2 is bad'
*							),
*
*/


	$log_operations = array(
		1 => 'LOG_LOCK',
		2 => 'LOG_UNLOCK',
		3 => 'LOG_MOVE',
		4 => 'LOG_MOVE',
		5 => 'LOG_FORK',
		6 => 'LOG_TOPIC_TYPE_CHANGED',
		7 => 'LOG_MERGE',
		8 => 'LOG_SPLIT_DESTINATION',
		9 => 'LOG_TOPIC_TYPE_CHANGED',
		10 => 'LOG_TOPIC_TYPE_CHANGED',
		13 => 'LOG_POST_EDITED',
		14 => 'LOG_SOFTDELETE_TOPIC',
		15 => 'LOG_DELETE_TOPIC',
		16 => 'LOG_RESTORE_TOPIC',
		17 => 'LOG_SOFTDELETE_POST',
		18 => 'LOG_DELETE_POST',
		19 => 'LOG_POST_RESTORED',
		20 => 'LOG_POST_EDITED',
		21 => 'LOG_POST_APPROVED',
		22 => 'LOG_TOPIC_DISAPPROVED',
		23 => 'LOG_MERGE',
		24 => 'LOG_POST_DISAPPROVED',
		25 => 'LOG_POST_APPROVED',
		26 => 'LOG_MERGE',
		29 => 'LOG_POST_EDITED',
		31 => 'LOG_FORK',
	);

	$log_definition = array(
		'LOG_LOCK' => array('topic_title'),
		'LOG_UNLOCK' => array('topic_title'),
		'LOG_MOVE' => array('source_forum_title', 'destination_forum_title', 'source_forum_id', 'destination_forum_id'),
		'LOG_FORK' => array('topic_title'),
		'LOG_TOPIC_TYPE_CHANGED' => array('topic_title'),
		'LOG_MERGE' => array('destination_topic_title'),
		'LOG_SPLIT_SOURCE' => array('topic_title'),
		'LOG_SPLIT_DESTINATION' => array('topic_title'),
		'LOG_POST_EDITED' => array('post_title', 'user_name', 'reason'),
		'LOG_SOFTDELETE_TOPIC' => array('topic_title', 'user_name', 'reason'),
		'LOG_DELETE_TOPIC' => array('topic_title', 'user_name', 'reason'),
		'LOG_RESTORE_TOPIC' => array('topic_title', 'user_name'),
		'LOG_SOFTDELETE_POST' => array('post_title', 'user_name', 'reason'),
		'LOG_DELETE_POST' => array('post_title', 'user_name', 'reason'),
		'LOG_POST_RESTORED' => array('post_title'),
		'LOG_POST_EDITED' => array('post_title', 'user_name', 'reason'),
		'LOG_TOPIC_DISAPPROVED' => array('topic_title', 'reason', 'user_name'),
		'LOG_POST_DISAPPROVED' => array('post_title', 'reason', 'user_name'),
		'LOG_POST_APPROVED' => array('post_title'),
	);

	$convertor = array(
		'test_file'				=> 'showthread.php',

		'avatar_path'			=> get_config_value('avatarurl') . '/',
		'avatar_gallery_path'	=> 'images/avatars/',
		'smilies_path'			=> 'images/smilies/',
		'upload_path'			=> get_config_value('attachpath') . '/',
		'thumbnails'			=> '',
		//'ranks_path'			=> false,
		'avatar_loc'			=> get_config_value('usefileavatar'),
		'attach_loc'			=> get_config_value('attachfile'),
		'version'				=> get_config_value('templateversion'),

		'signature_pics_path'	=> get_config_value('sigpicurl'),
		'profile_pics_enabled'	=> get_config_value('profilepicenabled'),
		'profile_pics_path'		=> get_config_value('profilepicurl'),

		// Don't change this without knowing what you're doing: you're probably going to brake the converter
		'default_language_code' => 'en',

		// We'll be trying to map vBulletin default groups to phpBB default groups
		// Please note this is based on an English version of vBulletin only, and assuming you haven't changed the default names.
		'usergroup_convert_table' => array(
			VB_GROUP_GUESTS						=> 'GUESTS',
			VB_GROUP_USERS						=> 'REGISTERED',
			VB_GROUP_SUPER_MODERATORS			=> 'GLOBAL_MODERATORS',
			VB_GROUP_ADMINISTRATORS				=> 'ADMINISTRATORS',
			// Other groups not transferred by default
			// VB_GROUP_AWAITING_EMAIL			=> 'GUESTS',	*** We don't import these users ***
			// VB_GROUP_AWAITING_MODERATION		=> 'REGISTERED',	// It's better not to use 'NEWLY_REGISTERED'
			// VB_GROUP_MODERATORS				=> 'GLOBAL_MODERATORS',
			// 'Banned Users'					=> '',
		),

		// List of groups to ignore: we don't transfer forum rights and users from these
		'ignore_groups' => array(
			VB_GROUP_AWAITING_EMAIL,
			VB_GROUP_AWAITING_MODERATION,
			VB_GROUP_MODERATORS,
		),

		// Profile fields already defined in phpBB
		'profilefields_convert_table' => array(
			// 'Biography' => '',		This field doesn't exists in phpBB and will be created
			'Location' => 'phpbb_location',
			'Interests' => 'phpbb_interests',
			'Occupation' => 'phpbb_occupation',
		),

		// Profile fields type to convert
		'profilefields_type_convert_table' => array(
			'input' => 'profilefields.type.string',
			'textarea' => 'profilefields.type.text',
			'radio' => 'profilefields.type.dropdown',
			'select' => 'profilefields.type.dropdown',
			// All the other types are not compatible and won't be converted
		),

		// Database fields conversion for user data
		'profilefields_field_table' => array(
			'homepage' => 'pf_phpbb_website',
			'icq' => 'pf_phpbb_icq',
			'aim' => 'pf_phpbb_aol',
			'yahoo' => 'pf_phpbb_yahoo',
			'msn' => 'pf_phpbb_skype',		// MSN got replaced by Skype
			'skype' => 'pf_phpbb_skype',
		),

		// List of vBulletin default bbcodes and their phpBB version
		'bbcode_converson' => array(
			'QUOTE=' => 'quote=',
			'QUOTE' => 'quote',
			'HIGHLIGHT' => 'highlight',
			'NOPARSE' => 'noparse',
			'B' => 'b',
			'I' => 'i',
			'U' => 'u',
			'COLOR=' => 'color=',
			'SIZE=' => 'size=',
			'FONT=' => 'font=',
			/*
			'LEFT' => 'left',
			'CENTER' => 'center',
			'RIGHT' => 'right',
			 */
			'LEFT' => 'align=left',
			'CENTER' => 'align=center',
			'RIGHT' => 'align=right',
			'INDENT' => 'indent',
			'LIST' => 'list',
			'LIST=' => 'list=',
			'EMAIL' => 'email',
			'EMAIL=' => 'email=',
			'URL' => 'url',
			'URL=' => 'url=',
			'IMG' => 'img',
			'THREAD' => 'thread',
			'THREAD=' => 'thread=',
			'POST' => 'post',
			'POST=' => 'post=',
			'CODE' => 'code',
			'HTML' => 'html',
			'SIGPIC' => 'sigpic',
		),

		'log_operations' => $log_operations,

		'log_definition' => $log_definition,

		// We empty some tables to have clean data available
		'query_first'			=> array(
			array('target', $convert->truncate_statement . SEARCH_RESULTS_TABLE),
			array('target', $convert->truncate_statement . SEARCH_WORDLIST_TABLE),
			array('target', $convert->truncate_statement . SEARCH_WORDMATCH_TABLE),
			array('target', $convert->truncate_statement . LOG_TABLE),

			array('target', $convert->truncate_statement . USER_GROUP_TABLE),
			array('target', $convert->truncate_statement . TEAMPAGE_TABLE),
		),

		// This can't be an array for some reason
		'execute_first'	=> '
			vb_clean_datastore();
			add_default_groups();
			vb_convert_default_groups();
			add_user_salt_field();
			vb_convert_forums();
			vb_add_bbcodes();
			vb_import_icons();
			vb_import_smilies();
		',

		'execute_last'	=> array('
			vb_import_edited_posts_counter();
		', '
			vb_fix_deleted_threads();
		', '
			vb_convert_banemail();
		', '
			vb_convert_censorwords();
		', '
			vb_convert_infractions();
		', '
			add_groups_to_teampage();
		', '
			add_bots();
		', '
			vb_convert_profile_custom_fields();
		', '
			vb_convert_friends_and_foes();
		', '
			vb_import_customavatar();
		', '
			add_user_profilepic_fields();
		', '
			vb_import_customprofilepic();
		', '
			add_user_sigpic_fields();
		', '
			vb_import_signaturepic();
		', '
			vb_import_polloption();
		', '
			vb_set_board_startdate();
		', '
			update_folder_pm_count();
		', '
			update_unread_count();
		', '
			vb_convert_forum_permissions();
		', '
			vb_convert_moderator_permissions();
		', '
			vb_fix_permissions();
		', '
			vb_clean_datastore();
		'),

		'schema' => array(

			array(
				'target'		=> ATTACHMENTS_TABLE,
				'primary'		=> 'attachment.attachmentid',
				'query_first'	=>  array('target', $convert->truncate_statement . ATTACHMENTS_TABLE),
				'autoincrement'	=> 'attach_id',

				array('attach_id',			'attachment.attachmentid',			''),
				array('post_msg_id',		'attachment.postid',				''),
				array('topic_id',			'post.threadid',					''),
				array('in_message',			0,									''),
				array('is_orphan',			0,									''),
				array('poster_id',			'attachment.userid AS poster_id',	'vb_user_id'),
				array('',					'attachment.filedata',				''),
				array('',					'attachment.thumbnail',				''),
				array('physical_filename',	'attachment.userid',				'vb_import_attachment'),
				array('real_filename',		'attachment.filename',				array('function1' => 'vb_set_encoding', 'function2' => 'utf8_htmlspecialchars')),
				array('download_count',		'attachment.counter',				''),
				array('attach_comment',		'',									''),
				//array('extension',			'attachment.filename',				'vb_file_ext'),
				// This one is better
				array('extension',			'attachment.extension',				''),
				array('mimetype',			'attachmenttype.mimetype',			'vb_mimetype'),
				array('filesize',			'attachment.filesize',				''),
				array('filetime',			'attachment.dateline',				''),
				array('thumbnail',			'attachment.thumbnail_filesize',	'is_positive'),

				'left_join'		=> array(
									'attachment LEFT JOIN post ON post.postid = attachment.postid',
									'attachment LEFT JOIN attachmenttype ON attachment.extension = attachmenttype.extension',
								),
				'where'			=> '',
			),

			array(
				'target'		=> BANLIST_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . BANLIST_TABLE),

				array('ban_userid',			'userban.userid',			'vb_user_id'),
				array('ban_reason',			'userban.reason',			''),
				array('ban_give_reason',	'',							''),
				array('ban_start',			'userban.bandate',			''),
				array('ban_end',			'userban.liftdate',			''),
			),

			array(
				'target'		=> RANKS_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . RANKS_TABLE),
				'autoincrement'	=> 'rank_id',

				array('rank_id',					'ranks.rankid',				''),
				array('rank_title',					'ranks.rankimg',			array('function1' => 'vb_set_default_encoding', 'function2' => 'utf8_htmlspecialchars')),
				array('rank_min',					'ranks.minposts',			array('typecast' => 'int')),
				array('rank_special',				0,							''),
				array('rank_image',					'',							''),
			),

			array(
				'target'		=> TOPICS_TABLE,
				'query_first'	=> array('target', $convert->truncate_statement . TOPICS_TABLE),
				'primary'		=> 'thread.threadid',
				'autoincrement'	=> 'topic_id',

				array('topic_id',				'thread.threadid',					''),
				array('forum_id',				'thread.forumid',					''),
				array('icon_id',				'thread.iconid',					'vb_icon_id'),
				array('topic_attachment',		'thread.attach',					''),
				array('topic_title',			'thread.title',						'vb_set_encoding'),
				array('topic_poster',			'thread.postuserid AS poster_id',	'vb_user_id'),
				array('topic_time',				'thread.dateline',					''),
				array('topic_views',			'thread.views',						''),
				array('topic_status',			'thread.open',						'thread_open_to_topic_status'),
				array('topic_type',				'thread.sticky',					''),
				array('topic_first_post_id',	'thread.firstpostid',				''),
				array('topic_first_poster_name','thread.postusername',				'vb_set_default_encoding'),
				array('topic_last_post_id',		'thread.lastpostid',				''),
				array('topic_last_poster_id',	'thread.lastposter',				'vb_get_userid_from_username'),
				array('topic_last_poster_name',	'thread.lastposter',				'vb_set_default_encoding'),
				array('topic_last_post_time',	'thread.lastpost',					''),
				array('topic_moved_id',			'thread.pollid',					'vb_set_moved_id'),

				array('poll_title',				'poll.question AS poll_title',		array('function1' => 'null_to_str', 'function2' => 'vb_set_encoding', 'function3' => 'utf8_htmlspecialchars')),
				array('poll_start',				'poll.dateline AS poll_start',		'null_to_zero'),
				array('poll_length',			'poll.timeout AS poll_length',		'vb_poll_length'),
				array('',						'poll.numberoptions',				''),
				array('poll_max_options',		'poll.multiple',					'vb_poll_options'),
				array('poll_last_vote',			'poll.lastvote',					''),
				array('poll_vote_change',		0,									''),
				array('topic_visibility',		'thread.visible',					''),	// Using the same codes :-)
				array('topic_delete_time',		'deletionlog.dateline AS d_dateline',''),
				array('topic_delete_reason',	'deletionlog.reason AS d_reason',	'vb_set_default_encoding'),
				array('topic_delete_user',		'deletionlog.userid AS d_user_id',	'vb_user_id'),
				array('topic_posts_approved',	'thread.replycount',				''),
				array('topic_posts_softdeleted','thread.deletedcount',				'vb_fix_softdeleted'),

				'left_join'		=> array(
									'thread LEFT JOIN poll ON thread.pollid = poll.pollid',
									'thread LEFT JOIN deletionlog ON (thread.threadid = deletionlog.primaryid AND deletionlog.type=\'thread\')',
								),
					//'where'			=> 'topics.topic_moved_id = 0',
			),

			array(
				'target'		=> FORUMS_WATCH_TABLE,
				'primary'		=> 'subscribeforum.subscribeforumid',
				'query_first'	=> array('target', $convert->truncate_statement . FORUMS_WATCH_TABLE),

				array('forum_id',				'subscribeforum.forumid',		''),
				array('user_id',				'subscribeforum.userid',		'vb_user_id'),
				array('notify_status',			0,								''),
			),

			array(
				'target'		=> TOPICS_WATCH_TABLE,
				'primary'		=> 'subscribethread.subscribethreadid',
				'query_first'	=> array('target', $convert->truncate_statement . TOPICS_WATCH_TABLE),

				array('topic_id',				'subscribethread.threadid',		''),
				array('user_id',				'subscribethread.userid',		'vb_user_id'),
				array('notify_status',			0,								''),
			),

			array(
				'target'		=> POLL_VOTES_TABLE,
				'primary'		=> 'pollvote.pollvoteid',
				'query_first'	=> array('target', $convert->truncate_statement . POLL_VOTES_TABLE),

				array('poll_option_id',			'pollvote.voteoption',		''),	// Any need for the function vb_voteoption?
				array('topic_id',				'thread.threadid',			''),
				array('vote_user_id',			'pollvote.userid',			'vb_user_id'),
				array('vote_user_ip',			'',							''),

				'left_join'		=> 'pollvote LEFT JOIN thread ON pollvote.pollid = thread.pollid',
			),

			array(
				'target'		=> POSTS_TABLE,
				'primary'		=> 'post.postid',
				'autoincrement'	=> 'postid',
				'query_first'	=> array('target', $convert->truncate_statement . POSTS_TABLE),
				'execute_first'	=> '
					$config["max_post_chars"] = 0;
					$config["min_post_chars"] = 0;
					$config["max_quote_depth"] = 0;
				',

				array('post_id',				'post.postid',						''),
				array('topic_id',				'post.threadid',					''),
				array('forum_id',				'thread.forumid',					''),
				array('poster_id',				'post.userid as poster_id',			'vb_user_id'),
				array('icon_id',				'post.iconid',						'vb_icon_id'),
				array('poster_ip',				'post.ipaddress',					''),
				array('post_time',				'post.dateline AS postdateline',	''),
				array('post_reported',			'post.reportthreadid',				'is_positive'),
				array('enable_bbcode',			1,									''),
				array('enable_smilies',			'post.allowsmilie',					''),
				array('enable_sig',				'post.showsignature',				''),
				array('enable_magic_url',		1,									''),
				array('post_username',			'post.username',					'vb_set_encoding'),
				array('post_subject',			'post.title',						'vb_set_encoding'),
				array('post_attachment',		'post.attach',						''),

				array('post_edit_time',			'editlog.dateline AS e_dateline',	array('typecast' => 'int')),
				array('post_edit_count',		'editlog.postid AS e_postid',		'is_positive'),
				array('post_edit_reason',		'editlog.reason AS e_reason',		'vb_set_encoding'),
				array('post_edit_user',			'editlog.userid AS e_userid',		'vb_user_id'),

				array('post_delete_time',		'deletionlog.dateline AS d_dateline',	''),
				array('post_delete_reason',		'deletionlog.reason AS d_reason',		'vb_set_encoding'),
				array('post_delete_user',		'deletionlog.userid AS d_userid',		'vb_user_id'),

				array('bbcode_uid',				'post.dateline AS post_time',		'make_uid'),
				array('post_text',				'post.pagetext',					'vb_prepare_message'),
				array('bbcode_bitfield',		'',									'get_bbcode_bitfield'),
				array('post_checksum',			'',									''),
				array('post_visibility',		'post.visible',						'vb_convert_visible'),

				'left_join'		=> array(
										'post LEFT JOIN editlog ON (post.postid = editlog.postid)',
										'post LEFT JOIN deletionlog ON (post.postid = deletionlog.primaryid AND deletionlog.type=\'post\')',
										'post LEFT JOIN thread ON (post.threadid = thread.threadid)'
									),
			),

			array(
				'target'		=> PRIVMSGS_TABLE,
				'primary'		=> 'pmtextid',
				'autoincrement'	=> 'msg_id',
				'query_first'	=> array(
					array('target', $convert->truncate_statement . PRIVMSGS_TABLE),
					array('target', $convert->truncate_statement . PRIVMSGS_RULES_TABLE),
				),

				'execute_first'	=> '
					$config["max_post_chars"] = 0;
					$config["min_post_chars"] = 0;
					$config["max_quote_depth"] = 0;
					vb_convert_pm_folders();
				',

				array('msg_id',					'pmtext.pmtextid',						''),
				array('root_level',				0,										''),
				array('author_id',				'pmtext.fromuserid AS poster_id',		'vb_user_id'),
				array('icon_id',				'pmtext.iconid',						'vb_icon_id'),
				array('author_ip',				'',										''),
				array('message_time',			'pmtext.dateline',						''),
				array('enable_bbcode',			1,										''),
				array('enable_smilies',			'pmtext.allowsmilie AS enable_smilies',	''),
				array('enable_magic_url',		1,										''),
				array('enable_sig',				'pmtext.showsignature',					''),
				array('message_subject',		'pmtext.title',							'vb_set_encoding'),
				array('message_edit_reason',	'',										''),
				array('message_edit_user',		0,										''),
				array('message_edit_time',		0,										''),
				array('message_edit_count',		0,										''),
				array('message_attachment',		0,										''),
				// Trick to enable bbcode for vb_prepare_message
				array('',						'pmtext.pmtextid AS enable_bbcode',		'is_positive'),
				array('bbcode_uid',				'pmtext.dateline AS post_time',			'make_uid'),
				array('message_text',			'pmtext.message',						'vb_prepare_message'),
				//array('',						'privmsgs_text.privmsgs_bbcode_uid AS old_bbcode_uid',			''),
				array('bbcode_bitfield',		'',										'get_bbcode_bitfield'),
				array('to_address',				'pmtext.touserarray',					'vb_privmsgs_to_user_array'),
				array('bcc_address',			'pmtext.touserarray',					'vb_privmsgs_bcc_user_array'),
			),

			array(
				'target'		=> PRIVMSGS_TO_TABLE,
				'primary'		=> 'pm.pmid',
				'query_first'	=> array('target', $convert->truncate_statement . PRIVMSGS_TO_TABLE),

				array('msg_id',					'pm.pmtextid',				''),
				array('user_id',				'pm.userid AS poster_id',	'vb_user_id'),
				array('author_id',				'pmtext.fromuserid',		'vb_user_id'),
				array('pm_deleted',				0,							''),
				array('pm_new',					'pm.messageread',			'vb_unread_pm'),
				array('pm_unread',				'pm.messageread',			'vb_unread_pm'),
				array('pm_replied',				'pm.messageread',			'vb_replied_pm'),
				array('pm_marked',				0,							''),
				array('pm_forwarded',			'pm.messageread',			'vb_forwarded_pm'),
				array('folder_id',				'pm.folderid',				'vb_folder_id'),

				'left_join'		=> 'pm LEFT JOIN pmtext ON pm.pmtextid = pmtext.pmtextid',
				'where'			=> '',
			),

			array(
				'target'		=> GROUPS_TABLE,
				'autoincrement'	=> 'group_id',

				array('group_id',				'usergroup.usergroupid',			''),
				array('group_type',				'usergroup.ispublicgroup',			'vb_convert_group_type'),
				array('group_display',			0,									''),
				array('group_legend',			0,									''),
				array('group_name',				'usergroup.title',					'vb_convert_group_name'), // vb_set_encoding called in vb_convert_group_name
				array('group_desc',				'usergroup.description',			'vb_set_encoding'),
				array('group_sig_chars',		'usergroup.sigmaxchars',			''),
				array('group_max_recipients',	'usergroup.pmsendmax',				''),

				// Yes, this is how vBulletin recognise its default groups - actually this is handy because it's the same number of default groups for phpBB 3.1
				'where'			=> 'usergroup.usergroupid > 7',
			),


			array(
				'target'		=> USERS_TABLE,
				'primary'		=> 'user.userid',
				'autoincrement'	=> 'user_id',
				'query_first'	=> array(
					array('target', 'DELETE FROM ' . USERS_TABLE . ' WHERE user_id <> ' . ANONYMOUS),
					array('target', $convert->truncate_statement . BOTS_TABLE),
					array('target', $convert->truncate_statement . USER_NOTIFICATIONS_TABLE),
				),
				// This will add all the users belonging to the groups
				'execute_first' => '
					add_membergroups();
					',

				array('user_id',				'user.userid',						'vb_user_id'),
				array('',						'user.userid AS poster_id',			'vb_user_id'),			// Needed for vb_prepare_message
				array('user_type',				USER_NORMAL,						'vb_user_type'),
				array('group_id',				'user.usergroupid',					'vb_convert_group_id'),
				array('user_ip',				'user.ipaddress',					''),
				array('user_regdate',			'user.joindate',					''),
				array('username',				'user.username',					'vb_set_default_encoding'), // recode to utf8 with default lang
				array('username_clean',			'user.username',					array('function1' => 'vb_set_default_encoding', 'function2' => 'utf8_clean_string')),
				array('user_password',			'user.password',					'vb_convert_password_hash'),
				array('user_passwd_salt',		'user.salt',						''),
				array('user_posts',				'user.posts',						'intval'),
				array('user_email',				'user.email',						'strtolower'),
				array('user_email_hash',		'user.email',						'gen_email_hash'),
				array('user_birthday',			'user.birthday',					'vb_get_birthday'),
				array('user_lastvisit',			'user.lastvisit',					'intval'),
				array('user_lastmark',			'user.lastactivity',				'intval'),
				array('user_lang',				'language.languagecode',			'vb_get_default_lang'),
				// vb3 used the same timezone format as phpBB2
				array('user_timezone',			'user.timezoneoffset',				'vb_convert_timezone'),
				array('user_dateformat',		$config['default_dateformat'],		array('function1' => 'vb_set_encoding', 'function2' => 'fill_dateformat')),
				array('user_inactive_reason',	0,									''),
				array('user_inactive_time',		0,									''),

				array('user_jabber',			'',									''),
				array('user_rank',				0,									'intval'),
				array('user_permissions',		'',									''),

				// Avatars will be converted later
				array('user_avatar',			'',									''),
				array('user_avatar_type',		'',									''),
				array('user_avatar_width',		0,									''),
				array('user_avatar_height',		0,									''),

				array('user_warnings',			'user.warnings',					''),
				array('user_new_privmsg',		'user.pmunread',					'is_positive'),
				array('user_unread_privmsg',	'user.pmunread',					''),
				array('user_last_privmsg',		0,									'intval'),
				array('user_emailtime',			'user.emailstamp',					'null_to_zero'),
				array('',						'user.options AS useroptions',		''),
				array('user_notify',			'user.autosubscribe',				'vb_user_notify'),
				array('user_notify_pm',			'',									'vb_notify_pm'),
				array('user_notify_type',		NOTIFY_EMAIL,						''),
				array('user_allow_pm',			'',									'vb_allow_pm'),
				array('user_allow_viewonline',	'',									'vb_viewonline'),
				array('user_allow_viewemail',	1,									'intval'),
				array('user_actkey',			'',									''),
				array('user_newpasswd',			'',									''), // Users need to re-request their password...
				array('user_style',				$config['default_style'],			''),

				array('user_options',			'user.options',						'vb_set_user_options'),

				array('user_sig_bbcode_uid',		'user.joindate',								'make_uid'),
				array('user_sig',					'usertextfield.signature',						'vb_prepare_message'),
				array('user_sig_bbcode_bitfield',	'',												'get_bbcode_bitfield'),
				array('',							'user.joindate AS post_time',					''),
				array('',						'user.userid AS user_id',			'vb_add_notification_options'),


				'left_join'		=> array(
					'user LEFT JOIN usertextfield ON user.userid=usertextfield.userid',
					'user LEFT JOIN usergroup ON user.usergroupid=usergroup.usergroupid',
					'user LEFT JOIN language ON user.languageid=language.languageid',
					),
				'where'			=> 'usergroup.title != "' . VB_GROUP_AWAITING_EMAIL . '" AND usergroup.title != "' . VB_GROUP_AWAITING_MODERATION . '"',

			),
			// Do it again for users awaiting moderation
			array(
				'target'		=> USERS_TABLE,
				'primary'		=> 'user.userid',
				'autoincrement'	=> 'user_id',

				'execute_last'	=> '
					remove_invalid_users();
				',

				array('user_id',				'user.userid',						'vb_user_id'),
				array('',						'user.userid AS poster_id',			'vb_user_id'),
				array('user_type',				USER_INACTIVE,						''),
				array('group_id',				'user.usergroupid',					'vb_convert_group_id'),
				array('user_ip',				'user.ipaddress',			''),
				array('user_regdate',			'user.joindate',					''),
				array('username',				'user.username',					'vb_set_default_encoding'), // recode to utf8 with default lang
				array('username_clean',			'user.username',					array('function1' => 'vb_set_default_encoding', 'function2' => 'utf8_clean_string')),
				array('user_password',			'user.password',					'vb_convert_password_hash'),
				array('user_passwd_salt',		'user.salt',						''),
				array('user_posts',				'user.posts',						'intval'),
				array('user_email',				'user.email',						'strtolower'),
				array('user_email_hash',		'user.email',						'gen_email_hash'),
				array('user_birthday',			'user.birthday',					'vb_get_birthday'),
				array('user_lastvisit',			'user.lastvisit',					'intval'),
				array('user_lastmark',			'user.lastactivity',				'intval'),
				array('user_lang',				'language.languagecode',			'vb_get_default_lang'),
				// vb3 used the same timezone format as phpBB2
				array('user_timezone',			'user.timezoneoffset',				'vb_convert_timezone'),
				array('user_dateformat',		$config['default_dateformat'],		array('function1' => 'vb_set_encoding', 'function2' => 'fill_dateformat')),
				array('user_inactive_reason',	INACTIVE_REGISTER,					''),
				array('user_inactive_time',		'user.joindate',					''),

				array('user_jabber',			'',									''),
				array('user_rank',				0,									'intval'),
				array('user_permissions',		'',									''),

				// Avatars will be converter after
				array('user_avatar',			'',									''),
				array('user_avatar_type',		'',									''),
				array('user_avatar_width',		0,									''),
				array('user_avatar_height',		0,									''),

				array('user_new_privmsg',		'user.pmunread',					'is_positive'),
				array('user_unread_privmsg',	'user.pmunread',					''),
				array('user_last_privmsg',		0,									'intval'),
				array('user_emailtime',			'user.emailstamp',					'null_to_zero'),
				array('user_notify',			0,									'intval'),
				array('user_notify_pm',			1,									'intval'),
				array('user_notify_type',		NOTIFY_EMAIL,						''),
				array('user_allow_pm',			1,									'intval'),
				array('user_allow_viewonline',	0,									'intval'),
				array('user_allow_viewemail',	0,									'intval'),
				array('user_actkey',			'',									''),
				array('user_newpasswd',			'',									''), // Users need to re-request their password...
				array('user_style',				$config['default_style'],			''),

				array('user_options',			'',									'set_user_options'),

				array('user_sig_bbcode_uid',		'user.joindate',								'make_uid'),
				array('user_sig',					'usertextfield.signature',						'vb_prepare_message'),
				array('user_sig_bbcode_bitfield',	'',												'get_bbcode_bitfield'),
				array('',							'user.joindate AS post_time',					''),

				'left_join'		=> array(
					'user LEFT JOIN usertextfield ON user.userid=usertextfield.userid',
					'user LEFT JOIN usergroup ON user.usergroupid=usergroup.usergroupid',
					'user LEFT JOIN language ON user.languageid=language.languageid',
					),
				'where'			=> 'usergroup.title = "' . VB_GROUP_AWAITING_MODERATION . '"',

			),

			array(
				'target'		=> LOG_TABLE,
				'primary'		=> 'moderatorlog.moderatorlogid',
				'query_first'	=> array(
					array('target', $convert->truncate_statement . LOG_TABLE),
				),

				array('log_type',				'',								'vb_log_type'),
				array('user_id',				'moderatorlog.userid',			'vb_user_id'),
				array('forum_id',				'moderatorlog.forumid',			''),
				array('topic_id',				'moderatorlog.threadid',		''),
				array('reportee_id',			0,								''),
				array('log_ip',					'moderatorlog.ipaddress',		''),
				array('log_time',				'moderatorlog.dateline',		''),
				array('log_operation',			'moderatorlog.type',			'vb_log_operation'),
				array('',						'moderatorlog.postid',			''),
				array('',						'moderatorlog.action',			''),
				array('',						'moderatorlog.threadtitle',		''),
				array('log_data',				'',								'vb_log_data'),

				'where'			=> 'type IN (' . implode(',', array_keys($log_operations)) . ')',
			),
		),
	);
}
