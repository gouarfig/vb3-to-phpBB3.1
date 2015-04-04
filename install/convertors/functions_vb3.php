<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

// Bitfield constants
require_once 'vb_conversion_constants.php';
// Permission convertion
require_once 'vbPermission.class.php';
// Data store to keep data between requests
require_once 'ConversionDataStore.php';
// Functions specific to forum permissions
require_once 'functions_vb3_permissions.php';

/**
* Helper functions for vBulletin 3 to phpBB 3.1.x conversion
*/

/**
 * Add an entry in the conversion log file
 *
 * @param string $logString
 * @param string $logFile
 * @return boolean
 */
function vb_conversion_log($logString, $logFile = 'vb_conversion_log.txt') {
	$written = false;
	$retry = 3;

	while (!$written and ($retry > 0)) {
		$fh = fopen($logFile, 'a');
		if ($fh) {
			fwrite($fh, date('d/m/Y H:i:s') . ' - ' . $logString . "\n");
			fclose($fh);
			$written = true;
		} else {
			// The file could be already opened?
			$retry--;
			usleep(mt_rand( 100, 500000));
		}
	}
	return $written;
}


/**
* Set forum flags
 */
function vb_forum_flags()
{
	// Set forum flags
	$forum_flags = 0;

	// FORUM_FLAG_LINK_TRACK
	$forum_flags += 0;

	// FORUM_FLAG_PRUNE_POLL
	$forum_flags += FORUM_FLAG_PRUNE_POLL;

	// FORUM_FLAG_PRUNE_ANNOUNCE
	$forum_flags += 0;

	// FORUM_FLAG_PRUNE_STICKY
	$forum_flags += 0;

	// FORUM_FLAG_ACTIVE_TOPICS
	$forum_flags += FORUM_FLAG_ACTIVE_TOPICS;

	// FORUM_FLAG_POST_REVIEW
	$forum_flags += FORUM_FLAG_POST_REVIEW;

	return $forum_flags;
}

/**
 * Convert and insert forums
 *
 * @global type $db
 * @global type $src_db
 * @global type $convert
 */
function vb_convert_forums() {
	global $convert;

	define('BITFIELD_ALLOWPOSTING', 2);
	define('BITFIELD_CANCONTAINTHREADS', 4);
	define('BITFIELD_ALLOWICONS', 1024);

	$src_db = get_src_db_object();
	$forums = Array();
	$hierarchy = Array();
	$sql = 'SELECT * FROM ' . $convert->src_table_prefix . 'forum ORDER BY displayorder';
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result))
	{
		$new_forum = array(
			//'display_order'				=> $row['displayorder'],
			'forum_id'					=> (int) $row['forumid'],
			'forum_name'				=> htmlspecialchars(vb_set_encoding_from_source($row['title'], 'forum', 'title'), ENT_COMPAT, 'UTF-8'),
			'parent_id'					=> ($row['parentid'] < 0) ? 0 : $row['parentid'],
			'forum_parents'				=> '',
			'forum_desc'				=> htmlspecialchars(vb_set_encoding_from_source($row['description'], 'forum', 'description'), ENT_COMPAT, 'UTF-8'),
			'forum_type'				=> ($row['options'] & BITFIELD_CANCONTAINTHREADS) ? FORUM_POST : FORUM_CAT,
			'forum_status'				=> ($row['options'] & BITFIELD_ALLOWPOSTING) ? ITEM_UNLOCKED : ITEM_LOCKED,
			'forum_flags'				=> vb_forum_flags(),
			'display_subforum_list'		=> ($row['options'] & BITFIELD_CANCONTAINTHREADS) ? 0 : 1,
			'forum_options'				=> 0,
			// Default values
			'forum_desc_bitfield'		=> '',
			'forum_desc_options'		=> 7,
			'forum_desc_uid'			=> '',
			'forum_link'				=> '',
			'forum_password'			=> '',
			'forum_style'				=> 0,
			'forum_image'				=> '',
			'forum_rules'				=> '',
			'forum_rules_link'			=> '',
			'forum_rules_bitfield'		=> '',
			'forum_rules_options'		=> 7,
			'forum_rules_uid'			=> '',
			'forum_topics_per_page'		=> 0,
			'forum_posts_approved'		=> 0,
			'forum_posts_unapproved'	=> 0,
			'forum_posts_softdeleted'	=> 0,
			'forum_topics_approved'		=> 0,
			'forum_topics_unapproved'	=> 0,
			'forum_topics_softdeleted'	=> 0,
			'forum_last_post_id'		=> 0,
			'forum_last_poster_id'		=> 0,
			'forum_last_post_subject'	=> '',
			'forum_last_post_time'		=> 0,
			'forum_last_poster_name'	=> '',
			'forum_last_poster_colour'	=> '',
			'display_on_index'			=> 1,
			'enable_indexing'			=> 1,
			'enable_icons'				=> ($row['options'] & BITFIELD_ALLOWICONS) ? 1 : 0,
		);

		// Add to the list of forums
		$forums[$new_forum['forum_id']] = $new_forum;
	}
	$src_db->sql_freeresult($result);

	vb_conversion_log('vb_convert_forums(): ' . count($forums) . ' forum(s) found');

	$active_forums = vb_clean_orphaned_forums($forums);

	// Build the hierarchy from the root
	vb_build_hierarchy($forums, $hierarchy);

	$left_id = 0;
	vb_build_left_right_id($forums, $hierarchy, $left_id);

	// We save the hierarchy for later use (forum permissions)
	$datastore = ConversionDataStore::getInstance();
	$datastore->clearData('forums');
	$datastore->setData('forums', $active_forums);
	unset($datastore);

	// Make a copy now
	$forums_nokey = $forums;

	// We have to remove keys on $forums, because multi_insert is expecting an item in [0]
	sort($forums_nokey);

	$db = get_db_object();
	$db->sql_query($convert->truncate_statement . FORUMS_TABLE);
	$db->sql_multi_insert(FORUMS_TABLE, $forums_nokey);
}

/**
 * In some installation, a parent can be deleted and all the children will still point to it.
 * This function is cleaning these by moving the orphaned forums to the root
 *
 * @return array
 */
function vb_clean_orphaned_forums(&$forums)
{
	$forums_to_hide = array();
	$active_forums = array();
	$checked_forums = array();
	foreach ($forums as $forum_id => $forum)
	{
		if (!isset($forums[$forum['parent_id']]))
		{
			$forum['parent_id'] = 0;
			$forums_to_hide[] = $forum['forum_id'];
			vb_conversion_log("vb_clean_orphaned_forums(): WARNING - Moving orphaned forum id {$forum['forum_id']} to the root");
		}
		else
		{
			// In this array we only copy the active forums
			$active_forums[$forum_id] = $forum;
		}
		// This array will contain all forums, with the orphaned ones moved to the root
		$checked_forums[$forum_id] = $forum;
	}
	if (!empty($forums_to_hide))
	{
		$datastore = ConversionDataStore::getInstance();
		$datastore->setData('hidden_forums', $forums_to_hide);
		unset($datastore);
	}
	$forums = $checked_forums;
	return $active_forums;
}

/**
 * Build a tree hierarchy of forums (or gallery albums)
 *
 * @param array $forums
 * @param array $hierarchy
 * @param int $parent_id
 * @param string $field_name
 */
function vb_build_hierarchy($forums, &$hierarchy, $parent_id = 0, $field_name = 'forum_id') {
	//vb_conversion_log('vb_build_hierarchy(): Searching for children of parent id ' . $parent_id . '...');
	foreach ($forums as $forum) {
		if ($forum['parent_id'] == $parent_id) {
			$new_element = Array(
				$field_name	=> $forum[$field_name],
				'children'	=> array()
				);
			$hierarchy[] = &$new_element;
			vb_build_hierarchy($forums, $new_element['children'], $forum[$field_name], $field_name);
			// Build a new element
			unset($new_element);
		}
	}
}

/**
 * Add the "left" and "right" values to the forum tree hierarchy
 *
 * @param array $forums
 * @param array $children
 * @param int $left_id
 * @return int
 */
function vb_build_left_right_id(&$forums, &$children, &$left_id, $field_name = 'forum_id') {
	$right_id = $left_id + 1;
	if (is_array($children)) {
		foreach ($children as &$leaf) {
			$left_id++;
			$leaf['left_id'] = $left_id;
			if (!empty($leaf['children'])) {
				$right_id = vb_build_left_right_id($forums, $leaf['children'], $left_id, $field_name);
			} else {
				$right_id = $left_id;
			}
			$right_id++;
			$leaf['right_id'] = $right_id;
			$left_id = $right_id;
			// Update forums
			$forums[$leaf[$field_name]]['left_id'] = $leaf['left_id'];
			$forums[$leaf[$field_name]]['right_id'] = $leaf['right_id'];
		}
	}
	return $right_id;
}

function get_vb3_default_language_code()
{
	static $language_code;

	if (empty($language_code))
	{
		// The default will be English if nothing else is found
		$language_code = 'en';

		$language_id = get_config_value('languageid');
		if (!empty($language_id))
		{
			global $convert, $src_db;

			$sql = "SELECT languagecode
				FROM {$convert->src_table_prefix}language
				WHERE languageid = $language_id";
			$result = $src_db->sql_query($sql);
			$temp = $src_db->sql_fetchfield('languagecode');
			$src_db->sql_freeresult($result);

			if (!empty($temp))
			{
				$language_code = $temp;
			}
		}
		vb_conversion_log("get_vb3_default_language_code(): Default language is set to '$language_code'");
	}
	return $language_code;
}

/**
 * Returns the vBulletin encoding for the selected language (either ID or CODE)
 * If none provided, will return the default charset.
 *
 * @param type $language_id
 * @param type $language_code
 * @return string
 */
function get_vb3_encoding($language_id = 0, $language_code = '')
{
	global $convert, $src_db;

	if (empty($language_id) && empty($language_code))
	{
		$language_id = get_config_value('languageid');
	}
	$sql = "SELECT charset FROM {$convert->src_table_prefix}language WHERE ";
	if (!empty($language_id))
	{
		$sql .= "languageid=$language_id";
	}
	elseif (!empty($language_code))
	{
		$sql .= "languagecode='$language_code'";
	}
	$result = $src_db->sql_query($sql);
	$charset = $src_db->sql_fetchfield('charset');
	$src_db->sql_freeresult($result);

	// There's no charset? I don't think it should happen. Anyway, let's keep going with what is likely be the default one
	if (empty($charset))
	{
		$charset = 'ISO-8859-1';
		vb_conversion_log("WARNING: No charset found for language_id=$language_id; language_code='$language_code'");
	}
	return $charset;
}

/**
 * Returns user configured language. Returns 0 if the user has the default language
 *
 * @global type $convert
 * @global type $convert_row
 * @global type $src_db
 * @global type $same_db
 * @return int
 */
function vb_get_user_lang_id()
{
	global $convert, $convert_row;

	$lang_id = 0;
	if (!empty($convert_row))
	{
		if (!empty($convert_row['poster_id']))
		{
			global $src_db, $same_db;

			if ($convert->mysql_convert && $same_db)
			{
				$src_db->sql_query("SET NAMES 'binary'");
			}

			$sql = "SELECT languageid FROM {$convert->src_table_prefix}user WHERE userid = " . (int) $convert_row['poster_id'];
			$result = $src_db->sql_query($sql);
			$lang_id = (int) $src_db->sql_fetchfield('languageid');
			$src_db->sql_freeresult($result);

			if ($convert->mysql_convert && $same_db)
			{
				$src_db->sql_query("SET NAMES 'utf8'");
			}
		}
	}
	return $lang_id;
}

function get_vb3_mysql_encoding()
{
	global $convert, $src_db;
	static $force_encoding = null;

	if ($convert->mysql_convert) {
		if (is_null($force_encoding)) {
			$force_encoding = false;
			if (isset($convert->convertor_data['forum_path'])) {
				$filename = '../' . $convert->convertor_data['forum_path'] . '/includes/config.php';
				if (is_file($filename)) {
					include $filename;
					if (isset($config['Mysqli']['charset'])) {
						$force_encoding = $config['Mysqli']['charset'];
					}
				}
			}
			if (!empty($force_encoding))
			{
				vb_conversion_log("get_vb3_mysql_encoding(): Mysql charset encoding forced to '{$force_encoding}'");
			}
		}
		if (empty($force_encoding))
		{
		}
	}
	return $encoding;
}

/**
 * Returns the mysql table/field encoding from the source database
 *
 * @staticvar array $table_encoding
 * @param type $table_name
 * @return string
 */
function get_src_db_table_encoding($table_name)
{
	global $convert;
	static $table_encoding = array();

	if (!is_null($table_name))
	{
		$table_name = source_table_name($table_name);

		if (empty($table_encoding)) {
			$datastore = ConversionDataStore::getInstance();
			$table_encoding = $datastore->getData('table_encoding');
			if (empty($table_encoding)) {
				$encoding_count = array();
				$src_db = get_src_db_object();
				$db_name = $src_db->dbname;
				$sql = "SELECT "
						. "T.table_name, "
						. "CCSA.character_set_name "
						. "FROM "
						. "information_schema.`TABLES` T, "
						. "information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA "
						. "WHERE "
						. "CCSA.collation_name = T.table_collation "
						. "AND "
						. "T.table_schema = '{$db_name}' ";
				$result = $src_db->sql_query($sql);
				while ($row = $src_db->sql_fetchrow($result)) {
					$table_encoding[$row['table_name']] = $row['character_set_name'];
					if (!isset($encoding_count[$row['character_set_name']]))
					{
						$encoding_count[$row['character_set_name']] = 1;
					}
					else
					{
						$encoding_count[$row['character_set_name']]++;
					}
				}
				vb_conversion_log("get_src_db_table_encoding(): " . count($table_encoding) . " table encoding loaded.");
				if (!empty($encoding_count))
				{
					foreach($encoding_count as $encoding => $count)
					{
						vb_conversion_log("get_src_db_table_encoding(): {$count} tables are using the character set '{$encoding}'.");
					}
				}
				$datastore->setData('table_encoding', $table_encoding);
			}
		}
		return $table_encoding[$table_name];
	}
	else
	{
		return null;
	}
}

/**
 * Makes sure the source database table name has the prefix in front of it.
 *
 * @global type $convert
 * @param string $table_name
 * @return string
 */
function source_table_name($table_name)
{
	global $convert;

	if (substr($table_name, 0, strlen($convert->src_table_prefix)) != $convert->src_table_prefix)
	{
		$table_name = $convert->src_table_prefix . $table_name;
	}
	return $table_name;
}

function get_current_mysql_src_db_encoding()
{
	global $src_db, $convert;
	static $encoding = null;

	if (is_null($encoding) && $convert->mysql_convert) {
		$sql = "SELECT @@character_set_client, @@character_set_connection, @@character_set_results";
		$result = $src_db->sql_query($sql);
		if (!($row = $src_db->sql_fetchrow($result))) {
			vb_conversion_log("get_current_mysql_src_db_encoding(): Mysql charset encoding: character_set_client='{$row['@@character_set_client']}', character_set_connection='{$row['@@character_set_connection']}', character_set_results='{$row['@@character_set_results']}'");
			$encoding = $row['@@character_set_results'];
		}
	}
	return $encoding;
}

/**
 * Returns the source (board to be converted) database object from the globals.
 * If the source and destination are on the same server, it also deals with character charset
 *
 * @global type $src_db
 * @global type $convert
 * @global bool $same_db
 * @param string $with_encoding
 * @return db object
 */
function get_src_db_object($with_encoding = null)
{
	global $src_db, $convert, $same_db;

	if ($convert->mysql_convert)
	{
		if (!is_null($with_encoding))
		{
			$src_db->sql_query("SET NAMES '{$with_encoding}'");
		}
		else if ($same_db)
		{
			// I don't know why it's done like that, but it's everywhere in the converter backend
			$src_db->sql_query("SET NAMES 'binary'");
		}
	}
	return $src_db;
}

/**
 * Returns the phpBB database object from the globals
 * If the source and destination are on the same server, it also deals with character charset
 *
 * @global type $db
 * @global type $convert
 * @global bool $same_db
 * @return db object
 */
function get_db_object()
{
	global $db, $convert, $same_db;

	if ($convert->mysql_convert && $same_db)
	{
		$db->sql_query("SET NAMES 'utf8'");
	}
	return $db;
}

/**
* Function for recoding text with the default language
*
* @param string $text text to recode to utf8
* @param bool $grab_user_lang
*/
function vb_set_encoding($text, $grab_user_lang = true)
{
	static $default_vb_charset = '';
	$vb_charset = '';

	if ($grab_user_lang)
	{
		$vb_charset = get_vb3_encoding(vb_get_user_lang_id());
	}
	else
	{
		if (empty($default_vb_charset))
		{
			$default_vb_charset = get_vb3_encoding();
		}
		$vb_charset = $default_vb_charset;
	}

	return utf8_recode($text, $vb_charset);
}

/**
* Same as vb_set_encoding, but forcing boards default language
*/
function vb_set_default_encoding($text)
{
	return vb_set_encoding($text, false);
}

/**
 * Re-encode the $text in UTF-8 using the source encoding for $table and $field
 *
 * @param type $text
 * @param type $table
 * @param type $field
 * @return text
 */
function vb_set_encoding_from_source($text, $table = null, $field = null)
{
	$source_encoding = '';

	if (is_null($table))
	{
		$table = get_table_name_from_convert_global();
	}
	if (!empty($table))
	{
		$source_encoding = get_src_db_table_encoding($table);
	}
	if (!empty($source_encoding))
	{
		store_latest_encoding($source_encoding);
	}
	else
	{
		$source_encoding = use_latest_encoding();
	}
	if (empty($source_encoding))
	{
		vb_conversion_log("vb_set_encoding_from_source(): WARNING: Encoding for table '{$table}' was not found!");
	}
	return utf8_recode($text, $source_encoding);
}

function vb_set_encoding_from_setting($text)
{
	return vb_set_encoding_from_source($text, 'setting', 'value');
}

/**
 * Returns the source table name being processed
 *
 * @global type $convert
 * @return string
 */
function get_table_name_from_convert_global()
{
	global $convert;

	return $convert->src_table_prefix . $convert->convertor['current_table_name'];
}

function store_latest_encoding($encoding)
{
	$datastore = ConversionDataStore::getInstance();
	$datastore->setData('latest_encoding', $encoding);
	unset($datastore);
}

function use_latest_encoding()
{
	$encoding = null;
	$datastore = ConversionDataStore::getInstance();
	$encoding = $datastore->getData('latest_encoding');
	unset($datastore);
	return $encoding;
}

/**
* Convert Birthday to phpBB Format
*/
function vb_get_birthday($birthday = '')
{
	$birthday = (string) $birthday;

	// stored as month, day, year
	if (!$birthday)
	{
		return ' 0- 0-   0';
	}

	// Expected format from vB3 is MM-DD-YYYY
	$birthday_parts = explode('-',$birthday);

	$month = $birthday_parts[0];
	$day = $birthday_parts[1];
	$year =  $birthday_parts[2];

	return sprintf('%2d-%2d-%4d', $day, $month, $year);
}

/**
* Return correct user id value
*/
function vb_user_id($user_id)
{
	global $config;

	// Increment user id if the old forum is having a user with the id 1
	if (!isset($config['increment_user_id']))
	{
		global $src_db, $convert;

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
	}

	if (!empty($config['increment_user_id']) && $user_id == 1)
	{
		$user_id = $config['increment_user_id'];
	}

	// Manual conversion of users id (you shouldn't need that)
	if (is_file('./convertors/vb_user_id_conversion.php')) {
		include 'vb_user_id_conversion.php';
		if (isset($vb_user_id_conversion) && isset($vb_user_id_conversion[$user_id]))
		{
			$user_id = $vb_user_id_conversion[$user_id];
		}
	}

	return (int) $user_id;
}

/**
* Get ID from Username
*/
function vb_get_userid_from_username($username)
{
	global $db, $src_db, $same_db, $convert;

	if (empty($username))
	{
		return null;
	}

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'binary'");
	}

	$username = $db->sql_escape($username);
	$sql = "SELECT userid
		FROM {$convert->src_table_prefix}user
		WHERE username = '$username'";
	$result = $src_db->sql_query($sql);
	if (!($row = $src_db->sql_fetchrow($result)))
	{
		return null;
	}

	if ($convert->mysql_convert && $same_db)
	{
		$src_db->sql_query("SET NAMES 'utf8'");
	}

	return vb_user_id($row['userid']);
}

/**
* Convert the group name, making sure to avoid conflicts with 3.0 special groups
*/
function vb_convert_group_name($group_name)
{
	$default_groups = array(
		'GUESTS',
		'REGISTERED',
		'REGISTERED_COPPA',
		'GLOBAL_MODERATORS',
		'ADMINISTRATORS',
		'BOTS',
	);

	$group_name = strip_tags($group_name);
	if (in_array(strtoupper($group_name), $default_groups))
	{
		$group_name = 'Converted - ' . $group_name;
	}

	return strip_tags(vb_set_encoding_from_source($group_name, 'usergroup'));
}

/**
 * Convert group type
 *
 * @param int $group_type
 * @return int
 */
function vb_convert_group_type($group_type) {
	if ($group_type == 0) {
		// We have two options: either CLOSED or HIDDEN. I think I'm going to choose HIDDEN
		//return GROUP_CLOSED;
		return GROUP_HIDDEN;
	} else {
		return GROUP_OPEN;
	}
}

/**
 * Add the special vBulletin BBcode
 *
 * @global type $src_db
 * @global type $db
 * @global type $cache
 * @global type $convert
 */
function vb_add_bbcodes()
{
	global $cache, $convert;

	$src_db = get_src_db_object();

	$existing_bbcodes = array();
	$new_bbcodes = array(
			'font=' => array(
				'bbcode_tag' 				=> 'font=',
				'bbcode_match' 				=> '[font={SIMPLETEXT}]{TEXT}[/font]',
				'bbcode_tpl' 				=> '<span style="font-family: {SIMPLETEXT};">{TEXT}</span>',
				'display_on_posting' 		=> '1',
				'bbcode_helpline' 			=> 'Change font: [font=Georgia]Georgia font[/font]',
				'first_pass_match' 			=> '!\[font\=([a-zA-Z0-9-+.,_ ]+)\](.*?)\[/font\]!ies',
				'first_pass_replace' 		=> '\'[font=${1}:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/font:$uid]\'',
				'second_pass_match' 		=> '!\[font\=([a-zA-Z0-9-+.,_ ]+):$uid\](.*?)\[/font:$uid\]!s',
				'second_pass_replace' 		=> '<span style="font-family: ${1};">${2}</span>'
			),
			'align=' => array(
				'bbcode_tag' 				=> 'align=',
				'bbcode_match'				=> '[align={SIMPLETEXT}]{TEXT}[/align]',
				'bbcode_tpl'				=> '<div style="text-align: {SIMPLETEXT};">{TEXT}</div>',
				'display_on_posting'		=> '1',
				'bbcode_helpline'			=> 'Alignment: can use center, left, right',
				'first_pass_match'			=> '!\[align\=([a-zA-Z0-9-+.,_ ]+)\](.*?)\[/align\]!ies',
				'first_pass_replace'		=> '\'[align=${1}:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/align:$uid]\'',
				'second_pass_match'			=> '!\[align\=([a-zA-Z0-9-+.,_ ]+):$uid\](.*?)\[/align:$uid\]!s',
				'second_pass_replace'		=> '<div style="text-align: ${1};">${2}</div>'
			),

			'indent' => array(
				'bbcode_tag' 				=> 'indent',
				'bbcode_match'				=> '[indent]{TEXT}[/indent]',
				'bbcode_tpl'				=> '<span style="margin-left: 20px">{TEXT}</span>',
				'display_on_posting'		=> '1',
				'bbcode_helpline'			=> 'Indentation: [indent]text[/indent]',
				'first_pass_match'			=> '!\[indent\](.*?)\[/indent\]!ies',
				'first_pass_replace'		=> '\'[indent:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/indent:$uid]\'',
				'second_pass_match'			=> '!\[indent:$uid\](.*?)\[/indent:$uid\]!s',
				'second_pass_replace'		=> '<span style="margin-left: 20px">${1}</span>'
			),
	);
	if (vb_version() >= 370)
	{
		$new_bbcodes['sigpic'] = array(
				'bbcode_tag' 				=> 'sigpic',
				'bbcode_match' 				=> '[sigpic]{TEXT}[/sigpic]',
				'bbcode_tpl' 				=> '<table class="ModTable" style="background-color:#FFFFFF;border:1px solid #000000;border-collapse:separate;border-spacing:5px;padding:0;width:100%;color:#333333;overflow:hidden;"><tr><td class="exclamation" rowspan="2" style="background-color:#ff6060;font-weight:bold;font-family:\'Times New Roman\',Verdana,sans-serif;font-size:4em;color:#ffffff;vertical-align:middle;text-align:center;width:1%;">&nbsp;!&nbsp;</td><td class="rowuser" style="border-bottom:1px solid #000000;font-weight:bold;">Warning</td></tr><tr><td class="row text">The signature picture extension is not installed.</td></tr></table>',
				'display_on_posting' 		=> '0',
				'bbcode_helpline' 			=> 'Display signature picture: [sigpic][/sigpic]',
				'first_pass_match'			=> '!\[sigpic\](.*?)\[/sigpic\]!ies',
				'first_pass_replace'		=> '\'[sigpic:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/sigpic:$uid]\'',
				'second_pass_match'			=> '!\[sigpic:$uid\](.*?)\[/sigpic:$uid\]!s',
				'second_pass_replace' 		=> '<table class="ModTable" style="background-color:#FFFFFF;border:1px solid #000000;border-collapse:separate;border-spacing:5px;padding:0;width:100%;color:#333333;overflow:hidden;"><tr><td class="exclamation" rowspan="2" style="background-color:#ff6060;font-weight:bold;font-family:\'Times New Roman\',Verdana,sans-serif;font-size:4em;color:#ffffff;vertical-align:middle;text-align:center;width:1%;">&nbsp;!&nbsp;</td><td class="rowuser" style="border-bottom:1px solid #000000;font-weight:bold;">Warning</td></tr><tr><td class="row text">The signature picture extension is not installed.</td></tr></table>'
			);
	}

	$sql = "SELECT bbcodetag,bbcodereplacement,bbcodeexplanation,twoparams FROM {$convert->src_table_prefix}bbcode";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$tag = trim(strtolower($row['bbcodetag']), '=');
		$row['bbcodeexplanation'] = vb_set_encoding_from_source($row['bbcodeexplanation'], 'bbcode', 'bbcodeexplanation');

		if (!isset($new_bbcodes[$tag])) {
			if ($row['twoparams'] == 0) {
				$new_bbcodes[$tag] = array(
					'bbcode_tag' 				=> $tag,
					'bbcode_match'				=> "[{$tag}]{TEXT}[/{$tag}]",
					'bbcode_tpl'				=> str_replace('%1$s', '{TEXT}', $row['bbcodereplacement']),
					'display_on_posting'		=> '1',
					'bbcode_helpline'			=> $row['bbcodeexplanation'],
					'first_pass_match'			=> '!\[' . $tag . '\](.*?)\[/' . $tag . '\]!ies',
					'first_pass_replace'		=> '\'[' . $tag . ':$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/' . $tag . ':$uid]\'',
					'second_pass_match'			=> '!\[' . $tag . ':$uid\](.*?)\[/' . $tag . ':$uid\]!s',
					'second_pass_replace'		=> str_replace('%1$s', '${1}', $row['bbcodereplacement'])
				);
			} elseif ($row['twoparams'] == 1) {
				$new_bbcodes[$tag] = array(
					'bbcode_tag' 				=> $tag,
					'bbcode_match'				=> '[' . $tag . '={TEXT1}]{TEXT2}[/' . $tag . ']',
					'bbcode_tpl'				=> str_replace(array('%1$s','%2$s'), array('{TEXT2}','{TEXT1}'), $row['bbcodereplacement']),
					'display_on_posting'		=> '1',
					'bbcode_helpline'			=> $row['bbcodeexplanation'],
					'first_pass_match'			=> '!\[' . $tag . '\=(.*?)\](.*?)\[/' . $tag . '\]!ies',
					'first_pass_replace'		=> '\'[' . $tag . '=${1}:$uid]\'.str_replace(array("\\r\\n", \'\\"\', \'\\\'\', \'(\', \')\'), array("\\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/' . $tag . ':$uid]\'',
					'second_pass_match'			=> '!\[' . $tag . '\=(.*?):$uid\](.*?)\[/' . $tag . ':$uid\]!s',
					'second_pass_replace'		=> str_replace(array('%1$s','%2$s'), array('${2}','${1}'), $row['bbcodereplacement']),
				);
			}
		}
	}
	$src_db->sql_freeresult($result);

	$db = get_db_object();
	$sql = 'SELECT bbcode_tag FROM ' . BBCODES_TABLE;
	$result = $db->sql_query($sql);
	while ($record = $db->sql_fetchrow($result)) {
		$existing_bbcodes[] = strtolower($record['bbcode_tag']);
	}
	$db->sql_freeresult($result);
	vb_conversion_log("vb_add_bbcodes(): Loaded " . count($existing_bbcodes) . " existing BBcodes.");

	$sql = 'SELECT MAX(bbcode_id) AS max_bbcode_id FROM ' . BBCODES_TABLE;
	$result = $db->sql_query($sql);
	$max_bbcode = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if (($max_bbcode === false) || empty($max_bbcode)) {
		$bbcode_id = intval(NUM_CORE_BBCODES);
	} else {
		$bbcode_id = $max_bbcode['max_bbcode_id'];
	}
	// Make sure it is greater than the core bbcode ids...
	if ($bbcode_id < NUM_CORE_BBCODES)
	{
		$bbcode_id = intval(NUM_CORE_BBCODES);
	}

	foreach ($new_bbcodes as $new_record)
	{
		// If the bbcode does not already exist?
		if (!in_array($new_record['bbcode_tag'], $existing_bbcodes))
		{
			$bbcode_id++;
			// 1511 is the maximum bbcode_id allowed.
			if ($bbcode_id <= BBCODE_LIMIT)
			{
				$new_record['bbcode_id'] = (int) $bbcode_id;
				$db->sql_query('INSERT INTO ' . BBCODES_TABLE . $db->sql_build_array('INSERT', $new_record));
				vb_conversion_log("vb_add_bbcodes(): BBcode '{$new_record['bbcode_tag']}' added.");
			} else {
				vb_conversion_log("vb_add_bbcodes(): WARNING bbcode_id over the limit! ({$bbcode_id} > " . BBCODE_LIMIT . ")");
			}
		} else {
			vb_conversion_log("vb_add_bbcodes(): BBcode {$new_record['bbcode_tag']} already exists!");
		}
	}
	$cache->destroy('sql', BBCODES_TABLE);
}

/**
 * Converts font size to %
 *
 * @param int $size
 * @return int
 */
function vb_convert_font_size($size)
{
	//$size = (($size -1) *50) +50;
	$size = ($size *25) +50;
	if ($size < 75) $size = 75;
	if ($size > 200) $size = 200;
	return $size;
}

/**
 * Returns the list of smilies from the database
 *
 * @global type $db
 * @staticvar array $smilies
 * @return array
 */
function get_smilies_array()
{
	global $db;
	static $smilies = array();

	if (empty($smilies)) {
		$sql = 'SELECT code FROM ' . SMILIES_TABLE;
		$result = $db->sql_query($sql);
		while ($record = $db->sql_fetchrow($result)) {
			$smilies[] = $record['code'];
		}
		$db->sql_freeresult($result);
		//vb_conversion_log("get_smilies_array(): Loaded " . count($smilies) . " smilies.");
	}
	return $smilies;
}

/**
* Reparse the message and setting the bbcode bitfield
*/
function vb_prepare_message($message)
{
	global $config, $convert, $user, $convert_row, $message_parser;

	if (!$message)
	{
		$convert->row['mp_bbcode_bitfield'] = $convert_row['mp_bbcode_bitfield'] = 0;
		return '';
	}

	// make the post UTF-8
	$message = vb_set_encoding_from_source($message);
	if (function_exists('mb_internal_encoding'))
	{
		mb_internal_encoding("UTF-8");
	}

	// Convert vBulletin BBcode to phpBB BBcode using the configuration
	foreach ($convert->convertor['bbcode_converson'] as $vb_bbcode => $convert_bbcode)
	{
		$bbcode = $vb_bbcode;
		$pos1 = strpos($bbcode, '=');
		if ($pos1 !== FALSE) {
			$temp = $bbcode;
			$bbcode = substr($temp, 0, $pos1);
			$parameter = substr($temp, $pos1 +1);

			$pos2 = strpos($convert_bbcode, '=');
			//if (substr($convert_bbcode, -1, 1) == '=') {
			if ($pos2 !== FALSE) {
				$temp = $convert_bbcode;
				$convert_bbcode = substr($temp, 0, $pos2);
				$convert_parameter = substr($temp, $pos2 +1);
			}
			if (!$parameter && !$convert_parameter) {
				// The ? in (.*?) is important, it's asking the pattern to be lazy (match the closest instead of including the most you can)
				$message = preg_replace("#\[{$bbcode}=(.*?)\](.*?)\[/{$bbcode}\]#siu", "[{$convert_bbcode}=\\1]\\2[/{$convert_bbcode}]", $message);
				//if (empty($message)) {
				//	vb_conversion_log("vb_prepare_message(): Error converting BBCode '{$vb_bbcode}' on the first pass.");
				//}
				//$message = preg_replace("#\[{$bbcode}=(.*?)\](.*?)\[/{$bbcode}\]#su", "[{$convert_bbcode}=\\1]\\2[/{$convert_bbcode}]", $message);
				//if (empty($message)) {
				//	vb_conversion_log("vb_prepare_message(): Error converting BBCode '{$vb_bbcode}' on the second pass.");
				//}
			} elseif (!$parameter && $convert_parameter) {
				// The ? in (.*?) is important, it's asking the pattern to be lazy (match the closest instead of including the most you can)
				$message = preg_replace("#\[{$bbcode}=(.*?)\](.*?)\[/{$bbcode}\]#siu", "[{$convert_bbcode}={$convert_parameter}]\\2[/{$convert_bbcode}]", $message);
				//$message = preg_replace("#\[{$bbcode}=(.*?)\](.*?)\[/{$bbcode}\]#su", "[{$convert_bbcode}={$convert_parameter}]\\2[/{$convert_bbcode}]", $message);
			} elseif ($parameter && $convert_parameter) {
				// The ? in (.*?) is important, it's asking the pattern to be lazy (match the closest instead of including the most you can)
				$message = preg_replace("#\[{$bbcode}={$parameter}\](.*?)\[/{$bbcode}\]#siu", "[{$convert_bbcode}={$convert_parameter}]\\1[/{$convert_bbcode}]", $message);
				//$message = preg_replace("#\[{$bbcode}={$parameter}\](.*?)\[/{$bbcode}\]#su", "[{$convert_bbcode}={$convert_parameter}]\\1[/{$convert_bbcode}]", $message);
			} elseif ($parameter && !$convert_parameter) {
				// The ? in (.*?) is important, it's asking the pattern to be lazy (match the closest instead of including the most you can)
				$message = preg_replace("#\[{$bbcode}={$parameter}\](.*?)\[/{$bbcode}\]#siu", "[{$convert_bbcode}]\\1[/{$convert_bbcode}]", $message);
				//$message = preg_replace("#\[{$bbcode}={$parameter}\](.*?)\[/{$bbcode}\]#su", "[{$convert_bbcode}]\\1[/{$convert_bbcode}]", $message);
			}
		} else {
			$pos2 = strpos($convert_bbcode, '=');
			//if (substr($convert_bbcode, -1, 1) == '=') {
			if ($pos2 !== FALSE) {
				$temp = $convert_bbcode;
				$convert_bbcode = substr($temp, 0, $pos2);
				$convert_parameter = substr($temp, $pos2 +1);

				// The ? in (.*?) is important, it's asking the pattern to be lazy (match the closest instead of including the most you can)
				$message = preg_replace("#\[{$bbcode}\](.*?)\[/{$bbcode}\]#siu", "[{$convert_bbcode}={$convert_parameter}]\\1[/{$convert_bbcode}]", $message);
				//$message = preg_replace("#\[{$bbcode}\](.*?)\[/{$bbcode}\]#su", "[{$convert_bbcode}={$convert_parameter}]\\1[/{$convert_bbcode}]", $message);
			} else {
				// The ? in (.*?) is important, it's asking the pattern to be lazy (match the closest instead of including the most you can)
				$message = preg_replace("#\[{$bbcode}\](.*?)\[/{$bbcode}\]#siu", "[{$convert_bbcode}]\\1[/{$convert_bbcode}]", $message);
				//$message = preg_replace("#\[{$bbcode}\](.*?)\[/{$bbcode}\]#su", "[{$convert_bbcode}]\\1[/{$convert_bbcode}]", $message);
			}
		}
		if (empty($message)) {
			vb_conversion_log("vb_prepare_message(): Error converting BBCode '{$vb_bbcode}'");
			break;
		}
	}

	if (strpos($message, '[size=') !== false)
	{
		// Remove quotes if there
		$message = preg_replace('/\[size="(\d*?)"\]/su', '[size=\1]', $message);

		$message = preg_replace_callback(
				'/\[size=(\d*?)\]/u',
				function ($matches) {
					$percent = vb_convert_font_size(intval($matches[1]));
					return "[size={$percent}]";
				},
				$message);
	}

	foreach(array('color', 'font', 'url', 'email') as $bbcode) {
		if (strpos($message, "[{$bbcode}=") !== false)
		{
			// Remove quotes if there
			$message = preg_replace('/\[' . $bbcode . '="(.*?)"\]/su', '[' . $bbcode . '=\1]', $message);
		}
	}

	if (strpos($message, '[quote=') !== false)
	{
		// vBulletin keeps an id after the name
		$message = preg_replace('/\[quote=(.*?);(\d+)\]/su', '[quote=&quot;\1&quot;]', $message);

		// Add quotes if not there yet
		$message = preg_replace('/\[quote=([^"]*?)\]/su', '[quote=&quot;\1&quot;]', $message);

		$message = preg_replace('/\[quote="(.*?)"\]/su', '[quote=&quot;\1&quot;]', $message);
		$message = preg_replace('/\[quote=\\\"(.*?)\\\"\]/su', '[quote=&quot;\1&quot;]', $message);

		// Deal with escaped quotes.
		$message = str_replace('\"', '&quot;', $message);
		$message = str_replace('\&quot;', '&quot;', $message);
	}

	// vBulletin accepts smilies stuck to others, but phpBB needs a space in between them
	$smilies = get_smilies_array();
	foreach ($smilies as $code) {
		// Escape all meta-character
		$code = str_replace(
					array('\\',   '(',  ')',  '[',  ']',  '|',  '?',  '/',  '$',  '*',  '+',  '.',  '^',  '{',  '}'),
					array('\\\\', '\(', '\)', '\[', '\]', '\|', '\?', '\/', '\$', '\*', '\+', '\.', '\^', '\{', '\}'), $code);
		// Add space before and after any smiley code
		$message = preg_replace('/([^[:space:]])(' . $code . ')/siu', '\1 \2', $message);
		$message = preg_replace('/(' . $code . ')([^[:space:]])/siu', '\1 \2', $message);
	}

	$user_id = $convert->row['poster_id'];

	$message = str_replace('<br />', "\n", $message);
	$message = str_replace('<', '&lt;', $message);
	$message = str_replace('>', '&gt;', $message);

	// make the post UTF-8
	//$message = vb_set_encoding_from_source($message);

	$message_parser->warn_msg = array(); // Reset the errors from the previous message
	$message_parser->bbcode_uid = make_uid($convert->row['post_time']);
	$message_parser->message = $message;
	$message_parser->message_status = '';
	unset($message);

	// Make sure options are set.
	$enable_bbcode = (!isset($convert->row['enable_bbcode'])) ? true : $convert->row['enable_bbcode'];
	$enable_smilies = (!isset($convert->row['enable_smilies'])) ? true : $convert->row['enable_smilies'];
	$enable_magic_url = (!isset($convert->row['enable_magic_url'])) ? true : $convert->row['enable_magic_url'];

	// Tell the parser not to complain if a message is too big
	$config['max_post_chars'] = 0;

	$message_parser->parse($enable_bbcode, $enable_magic_url, $enable_smilies);

	if (sizeof($message_parser->warn_msg))
	{
		$msg_id = isset($convert->row['postid']) ? $convert->row['postid'] : $convert->row['pmtextid'];
		$convert->p_master->error('<span style="color:red">' . $user->lang['POST_ID'] . ': ' . $msg_id . ' ' . $user->lang['CONV_ERROR_MESSAGE_PARSER'] . ': <br /><br />' . implode('<br />', $message_parser->warn_msg), __LINE__, __FILE__, true);
	}

	$convert->row['mp_bbcode_bitfield'] = $convert_row['mp_bbcode_bitfield'] = $message_parser->bbcode_bitfield;

	$message = $message_parser->message;
	unset($message_parser->message);

	return $message;
}

/**
* Return the bitfield calculated by the previous function
*/
function get_bbcode_bitfield()
{
	global $convert_row;

	return $convert_row['mp_bbcode_bitfield'];
}

/**
* Just undos the replacing of '<' and '>'
*/
function  vb_smilie_html_decode($code)
{
	$code = str_replace('&lt;', '<', $code);
	return str_replace('&gt;', '>', $code);
}

/**
 * vBulletin 3 is using the same timezone field as phpBB 2.x, which is quite handy
 *
 * @global type $config
 * @global type $db
 * @global type $phpbb_root_path
 * @global type $phpEx
 * @global type $table_prefix
 * @param type $timezone
 * @return type
 */
function vb_convert_timezone($timezone)
{
	global $config, $db, $phpbb_root_path, $phpEx, $table_prefix;
	$timezone_migration = new \phpbb\db\migration\data\v310\timezone($config, $db, new \phpbb\db\tools($db), $phpbb_root_path, $phpEx, $table_prefix);
	return $timezone_migration->convert_phpbb30_timezone($timezone, 0);
}

/**
 * Add user notifications
 *
 * @global type $convert_row
 * @global type $db
 * @param type $user_notify_pm
 */
function vb_add_notification_options($user_id)
{
	global $convert_row, $db;

	$user_id = vb_user_id($user_id);
	if ($user_id == ANONYMOUS)
	{
		return;
	}

	$rows = array();

	$rows[] = array(
		'item_type'		=> 'notification.type.post',
		'item_id'		=> 0,
		'user_id'		=> (int) $user_id,
		'notify'		=> 1,
		'method'		=> 'notification.method.email',
	);
	$rows[] = array(
		'item_type'		=> 'notification.type.topic',
		'item_id'		=> 0,
		'user_id'		=> (int) $user_id,
		'notify'		=> 1,
		'method'		=> 'notification.method.email',
	);
	if ($convert_row['useroptions'] & user_options_emailonpm)
	{
		$rows[] = array(
			'item_type'		=> 'notification.type.pm',
			'item_id'		=> 0,
			'user_id'		=> (int) $user_id,
			'notify'		=> 1,
			'method'		=> 'notification.method.email',
		);
	}

	$sql = $db->sql_multi_insert(USER_NOTIFICATIONS_TABLE, $rows);
}

/**
 * Convert a password string
 *
 * @param string $hash
 * @return string
 */
function vb_convert_password_hash($hash)
{
	return '$CP$' . $hash;
}


/**
 * Add password salt field to users table
 *
 * @global type $phpbb_container
 */
function add_user_salt_field()
{
	global $phpbb_container;

	$column_name = 'user_passwd_salt';
	$column_type = 'VCHAR:3';

	$db_tools = $phpbb_container->get('dbal.tools');
	if (!$db_tools->sql_column_exists(USERS_TABLE, $column_name)) {
		$db_tools->sql_column_add(USERS_TABLE, $column_name, array($column_type, ''));
	} else {
		vb_conversion_log("add_user_salt_field(): WARNING column '{$column_name}' already exists in '" . USERS_TABLE . "'.");
	}
}

/**
 * Add signature picture fields to users table
 *
 * @global type $phpbb_container
 */
function add_user_sigpic_fields()
{
	global $db, $phpbb_container;

	if (vb_version() >= 370)
	{
		$columns = array(
			'user_sigpic' => array('VCHAR', ''),
			'user_sigpic_width' => array('USINT', 0),
			'user_sigpic_height' => array('USINT', 0),
		);

		$db_tools = $phpbb_container->get('dbal.tools');

		foreach ($columns as $column_name => $column_type)
		{
			if (!$db_tools->sql_column_exists(USERS_TABLE, $column_name)) {
				$db_tools->sql_column_add(USERS_TABLE, $column_name, $column_type);
			} else {
				vb_conversion_log("add_user_sigpic_field(): WARNING column '{$column_name}' already exists in '" . USERS_TABLE . "'.");
			}
		}
	}
}

/**
 * Add profile picture fields to users table
 *
 * @global type $phpbb_container
 */
function add_user_profilepic_fields()
{
	global $db, $phpbb_container;

	$columns = array(
		'user_profilepic' => array('VCHAR', ''),
		'user_profilepic_width' => array('USINT', 0),
		'user_profilepic_height' => array('USINT', 0),
	);

	$db_tools = $phpbb_container->get('dbal.tools');

	foreach ($columns as $column_name => $column_type)
	{
		if (!$db_tools->sql_column_exists(USERS_TABLE, $column_name)) {
			$db_tools->sql_column_add(USERS_TABLE, $column_name, $column_type);
		} else {
			vb_conversion_log("add_user_profilepic_fields(): WARNING column '{$column_name}' already exists in '" . USERS_TABLE . "'.");
		}
	}
}

/**
 * Return a phpBB group ID from a vBulletin group name
 *
 * @global type $convert
 * @param string $group_name
 * @return int
 */
function get_default_group_id_from_vb_name($group_name) {
	global $convert;

	if (isset($convert->convertor['usergroup_convert_table'])) {
		if (isset($convert->convertor['usergroup_convert_table'][$group_name]) && !empty($convert->convertor['usergroup_convert_table'][$group_name])) {
			$group_name = $convert->convertor['usergroup_convert_table'][$group_name];
		}
	}
	return get_group_id($group_name);
}

/**
 * Imports all the default groups (vb group id < 7)
 *
 * @global type $db
 * @global type $src_db
 * @global type $convert
 */
function vb_convert_default_groups() {
	global $convert;

	$src_db = get_src_db_object();
	//$sql = "SELECT usergroupid,title,description,pmquota,pmsendmax,ispublicgroup,forumpermissions,pmpermissions,calendarpermissions,wolpermissions,adminpermissions,genericpermissions,genericpermissions2,genericoptions,signaturepermissions,visitormessagepermissions,attachlimit,avatarmaxwidth,avatarmaxheight,avatarmaxsize,profilepicmaxwidth,profilepicmaxheight,profilepicmaxsize,sigpicmaxwidth,sigpicmaxheight,sigpicmaxsize,sigmaximages,sigmaxsizebbcode,sigmaxchars,sigmaxrawchars,sigmaxlines,albumpermissions,albumpicmaxwidth,albumpicmaxheight,albumpicmaxsize,albummaxpics,albummaxsize"
	$sql = "SELECT *"
			. " FROM {$convert->src_table_prefix}usergroup"
			. " WHERE usergroupid <= 7";
	$result = $src_db->sql_query($sql);

	$source_groups = Array();
	while ($row = $src_db->sql_fetchrow($result)) {
		$row['title'] = vb_set_encoding_from_source($row['title'], 'usergroup', 'title');
		$source_groups[$row['usergroupid']] = $row;
	}
	$src_db->sql_freeresult($result);

	$db = get_db_object();
	foreach ($source_groups as $row) {
		$dest_id = get_default_group_id_from_vb_name($row['title']);
		// I'm not too sure these parameters are even used any more (I haven't found them in the admin console)
		// Also reset all group colors
		$sql = "UPDATE " . GROUPS_TABLE ." SET ";
		if (is_numeric($row['sigmaxchars'])) {
			$sql .= "group_sig_chars = {$row['sigmaxchars']}, ";
		}
		$sql .= "group_max_recipients = {$row['pmsendmax']}, group_colour = '' WHERE group_id = {$dest_id}";
		$db->sql_query($sql);
	}
}

/**
 * Returns a phpBB converted group ID from the source group ID
 *
 * @global type $src_db
 * @global type $convert
 * @staticvar array $convert_table_for_group_ids
 * @param int $src_group_id
 * @return int
 */
function vb_convert_group_id($src_group_id) {
	global $src_db, $convert;
	static $convert_table_for_group_ids = Array();

	if (empty($convert_table_for_group_ids)) {
		$sql = "SELECT usergroupid,title FROM {$convert->src_table_prefix}usergroup WHERE usergroupid <= 7";
		$result = $src_db->sql_query($sql);
		while ($row = $src_db->sql_fetchrow($result)) {
			$convert_table_for_group_ids[$row['usergroupid']] = get_default_group_id_from_vb_name($row['title']);
		}
		$src_db->sql_freeresult($result);
		vb_conversion_log("vb_convert_group_id(): " . count($convert_table_for_group_ids) . " groups loaded");
	}

	// If not found, we return the same value
	$dest_groupd_id = $src_group_id;
	if (isset($convert_table_for_group_ids[$src_group_id])) {
		$dest_groupd_id = $convert_table_for_group_ids[$src_group_id];
	}
	return $dest_groupd_id;
}

/**
 * If the user doesn't have a configured language, returns the board default one
 *
 * @global type $config
 * @param string $src_lang
 * @return string
 */
function vb_get_default_lang($src_lang) {
	global $config;

	if (!empty($src_lang)) {
		$dest_lang = $src_lang;
	} else {
		$dest_lang = $config['default_lang'];
	}
	return $dest_lang;
}


/**
 * Adds all the members of each group
 *
 * @global type $db
 * @global type $src_db
 * @global type $convert
 */
function add_membergroups() {
	global $db, $src_db, $convert;

	$leaders = Array();
	$sql = "SELECT userid, usergroupid FROM {$convert->src_table_prefix}usergroupleader";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$leaders[$row['userid']][$row['usergroupid']] = 1;
	}
	$src_db->sql_freeresult($result);
	vb_conversion_log('add_membergroups(): ' . count($leaders) . ' leader(s) found');

	$pendings = Array();
	$sql = "SELECT userid, usergroupid FROM {$convert->src_table_prefix}usergrouprequest";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$pendings[$row['userid']][$row['usergroupid']] = 1;
	}
	$src_db->sql_freeresult($result);
	vb_conversion_log('add_membergroups(): ' . count($pendings) . ' pending membership(s) found');

	$memberships = Array();
	// Primary groups
	$sql = "SELECT userid, usergroupid FROM {$convert->src_table_prefix}user WHERE usergroupid > 0";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$memberships[] = array(
			'group_id'		=> vb_convert_group_id($row['usergroupid']),
			'user_id'		=> vb_user_id($row['userid']),
			'group_leader'	=> (isset($leaders[$row['userid']][$row['usergroupid']]) ? 1 : 0),
			'user_pending'	=> (isset($pendings[$row['userid']][$row['usergroupid']]) ? 1 : 0),
		);
		if (isset($leaders[$row['userid']][$row['usergroupid']])) $leaders[$row['userid']][$row['usergroupid']]++;
		if (isset($pendings[$row['userid']][$row['usergroupid']])) $pendings[$row['userid']][$row['usergroupid']]++;
	}
	$primary_count = count($memberships);
	vb_conversion_log('add_membergroups(): ' . $primary_count . ' primary memberships found');

	// Secondary groups
	$sql = "SELECT userid, membergroupids FROM {$convert->src_table_prefix}user WHERE membergroupids != ''";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		if (strpos($row['membergroupids'], ",") !== FALSE) {
			$groups = explode(',', $row['membergroupids']);
			foreach ($groups as $group) {
				$group = intval($group);
				if ($group > 0) {
					$memberships[] = array(
						'group_id'		=> vb_convert_group_id($group),
						'user_id'		=> vb_user_id($row['userid']),
						'group_leader'	=> (isset($leaders[$row['userid']][$group]) ? 1 : 0),
						'user_pending'	=> (isset($pendings[$row['userid']][$group]) ? 1 : 0),
					);
					if (isset($leaders[$row['userid']][$group])) $leaders[$row['userid']][$group]++;
					if (isset($pendings[$row['userid']][$group])) $pendings[$row['userid']][$group]++;
				}
			}
		} else {
			$row['membergroupids'] = intval($row['membergroupids']);
			if ($row['membergroupids'] > 0) {
				$memberships[] = array(
					'group_id'		=> vb_convert_group_id($row['membergroupids']),
					'user_id'		=> vb_user_id($row['userid']),
					'group_leader'	=> (isset($leaders[$row['userid']][$row['membergroupids']]) ? 1 : 0),
					'user_pending'	=> (isset($pendings[$row['userid']][$row['membergroupids']]) ? 1 : 0),
				);
				if (isset($leaders[$row['userid']][$row['membergroupids']])) $leaders[$row['userid']][$row['membergroupids']]++;
				if (isset($pendings[$row['userid']][$row['membergroupids']])) $pendings[$row['userid']][$row['membergroupids']]++;
			}
		}
	}
	$src_db->sql_freeresult($result);
	$secondary_count = count($memberships) - $primary_count;
	vb_conversion_log('add_membergroups(): ' . $secondary_count . ' secondary memberships found');

	// Now deal with the left-overs
	foreach ($leaders as $userid => $group) {
		foreach ($group as $groupid => $value) {
			if ($value == 1) {
				$memberships[] = array(
					'group_id'		=> vb_convert_group_id($groupid),
					'user_id'		=> vb_user_id($userid),
					'group_leader'	=> 1,
					'user_pending'	=> 0,
				);
			}
		}
	}
	$leader_no_member = count($memberships) - $primary_count - $secondary_count;
	vb_conversion_log('add_membergroups(): ' . $leader_no_member . ' leader(s) but not member found');

	foreach ($pendings as $userid => $group) {
		foreach ($group as $groupid => $value) {
			if ($value == 1) {
				$memberships[] = array(
					'group_id'		=> vb_convert_group_id($groupid),
					'user_id'		=> vb_user_id($userid),
					'group_leader'	=> 0,
					'user_pending'	=> 1,
				);
			}
		}
	}
	$pending_no_member = count($memberships) - $primary_count - $secondary_count - $leader_no_member;
	vb_conversion_log('add_membergroups(): ' . $pending_no_member . ' pending membership(s) and not member yet found');

	vb_conversion_log('add_membergroups(): ' . count($memberships) . ' total membership records found');
	$db->sql_multi_insert(USER_GROUP_TABLE, $memberships);
}


/**
 * Converts source user type to a phpBB user type
 *
 * @global type $convert_row
 * @global type $src_db
 * @global type $convert
 * @staticvar type $vBulletin
 * @param int $type
 * @return int
 */
function vb_user_type($type) {
	global $convert_row, $src_db, $convert;
	static $founders = Array();

	if (empty($founders)) {
		if (isset($convert->convertor_data['forum_path'])) {
			$filename = '../' . $convert->convertor_data['forum_path'] . '/includes/config.php';
			if (is_file($filename)) {
				include $filename;
				if (isset($config['SpecialUsers']['superadministrators'])) {
					$temp_founders = explode(',', $config['SpecialUsers']['superadministrators']);
					foreach ($temp_founders as $founder) {
						$founder = intval($founder);
						if ($founder > 0) {
							$founders[] = $founder;
						}
					}
					if (strpos($config['SpecialUsers']['superadministrators'], ',')) {
					} else {
						$founders[] = intval($config['SpecialUsers']['superadministrators']);
					}
				}
			}
		}
		vb_conversion_log('vb_user_type(): ' . count($founders) . ' founder(s) found');
	}

	if (in_array($convert_row['userid'], $founders)) {
		$type = USER_FOUNDER;
	}
	return $type;
}

/**
 * Make phpBB poll options field
 *
 * @global type $convert
 * @param type $multiple
 * @return int
 */
function vb_poll_options($multiple)
{
	global $convert;
	if ($multiple == 0) return 1;
	return $convert->row['numberoptions'];
}

/**
 * Converts poll length (from days to seconds)
 *
 * @param type $day
 * @return int
 */
function vb_poll_length($day)
{
	return $day*86400;
}


/**
 * Converts and imports poll options
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 */
function vb_import_polloption()
{
	Global $convert;

	$src_db = get_src_db_object();
	$source = array();
	$sql = 'SELECT p.*, t.threadid FROM '. $convert->src_table_prefix . 'poll p INNER JOIN '. $convert->src_table_prefix .'thread t ON p.pollid=t.pollid';
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result))
	{
		$source[] = $row;
	}
	$src_db->sql_freeresult($result);

	$db = get_db_object();
	$db->sql_query($convert->truncate_statement . POLL_OPTIONS_TABLE);

	foreach ($source as $row) {
		$option = explode('|||',$row['options']);
		$vote = explode('|||',$row['votes']);
		$topic_id = (int) $row['threadid'];

		$row['numberoptions'] = count($option);

		for ($i=1;$i<=$row['numberoptions'];$i++)
		{
			$poll_option = $db->sql_escape(vb_set_encoding_from_source($option[$i-1], 'poll'));
			$sql = 'INSERT INTO '.POLL_OPTIONS_TABLE.' (poll_option_id,topic_id,poll_option_text,poll_option_total)'
					. ' VALUES ('.$i.','.$topic_id.',\''.$poll_option.'\','.$vote[$i-1].')';
			$db->sql_query($sql);
		}
	}
}

/**
 * Sets the board start date to the date of the very first post
 *
 * @global type $db
 */
function vb_set_board_startdate()
{
	global $db;

	$sql = 'SELECT post_time FROM '.POSTS_TABLE.' ORDER BY post_id';
	$result = $db->sql_query_limit($sql,1);
	$start = $db->sql_fetchfield('post_time');
	$db->sql_freeresult($result);
	if (!empty($start))
	{
		$sql = 'UPDATE '.CONFIG_TABLE.' SET config_value='.$start.' WHERE config_name="board_startdate"';
		$db->sql_query($sql);
	}
}

/**
 * Converts "thread_open" field to "topic_status"
 * @param int $open
 * @return int
 */
function thread_open_to_topic_status($open) {
	$status = ITEM_UNLOCKED;

	switch ($open) {
		case 0:
			$status = ITEM_LOCKED;
			break;

		case 1:
			$status = ITEM_UNLOCKED;
			break;

		case 10:
			$status = ITEM_MOVED;
			break;
	}
	return $status;
}

function vb_set_moved_id($pollid)
{
	global $convert_row;

	$moved_id = 0;
	if ($convert_row['open'] == 10) {
		$moved_id = $pollid;
	}
	return $moved_id;
}

/**
 * Cleans our local cache of temporary data
 */
function vb_clean_datastore()
{
	$datastore = ConversionDataStore::getInstance();
	$datastore->purge();
}

/**
 * Generates a proper mime type
 *
 * @param type $mimetype
 * @return string
 */
function vb_mimetype($mimetype)
{
	$tmp = unserialize($mimetype);
	if (is_array($tmp) && isset($tmp[0])) {
		return str_replace('Content-type: ', '', $tmp[0]);
	} else {
		return '';
	}
}

/**
 * Check that we can write into the different destination folders
 */
function vb_check_folders()
{
	global $config, $convert, $phpbb_root_path;

	$attachments = $phpbb_root_path . $config['upload_path'];
	if (!is_dir($attachments) || !is_writable($attachments)) {
		$convert->p_master->error("Attachments path is not writeable: {$attachments}", __LINE__, __FILE__);
		exit();
	}
	vb_conversion_log("vb_check_folders(): Attachments folder is '{$attachments}'");

	$customavatars = $phpbb_root_path . $config['avatar_path'];
	if (!is_dir($customavatars) || !is_writable($customavatars)) {
		$convert->p_master->error("Custom avatars path is not writeable: {$customavatars}", __LINE__, __FILE__);
		exit();
	}
	vb_conversion_log("vb_check_folders(): Custom avatars folder is '{$customavatars}'");

	$customprofilepics = vb_get_customprofilepic_path();
	vb_conversion_log("vb_check_folders(): Custom profile pictures folder is '{$customprofilepics}'");

	$signaturepics = vb_get_signaturepic_path();
	vb_conversion_log("vb_check_folders(): Signature pictures folder is '{$signaturepics}'");

	vb_conversion_log("vb_check_folders(): All destination folders are writable.");
}

/**
 * Imports attachements
 *
 * @global type $config
 * @global type $convert
 * @global type $phpbb_root_path
 * @param type $userid
 * @return string
 */
function vb_import_attachment($userid)
{
	global $config, $convert, $phpbb_root_path;

	$attach_dir = $phpbb_root_path . $config['upload_path'];
	$attachid = $convert->row['attachmentid'];
	$physical = $userid.'_'.md5(unique_id());

	// attachments as files
	if ( $convert->convertor['attach_loc'] == 2 )
	{
		$file=$convert->options['forum_path'].'/'.$convert->convertor['upload_path'].implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY)).'/'.$attachid.'.attach';
		copy($file, $attach_dir.'/'.$physical);

		if ( $convert->row['thumbnail_filesize'] )
		{
			$thumb_file = $convert->options['forum_path'].'/'.$convert->convertor['upload_path'].implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY)).'/'.$attachid.'.thumb';
			$thumb_physical = 'thumb_'.$physical;
			copy($thumb_file, $attach_dir.'/'.$thumb_physical);
		}
	}

	// attachments in database
	if ( $convert->convertor['attach_loc'] == 0 )
	{
		$attach_data = $convert->row['filedata'];
		$new_filename = $physical;

		if ($fp = fopen($attach_dir . "/" . $new_filename, "w"))
		{
			fwrite($fp, $attach_data);
			fclose($fp);
		}

		if ( $convert->row['thumbnail'] )
		{
			$thumb_physical = 'thumb_'.$physical;
			$thumb_data = $convert->row['thumbnail'];
			$new_filename = $thumb_physical;

			if ($fp = fopen($attach_dir . "/" . $new_filename, "w"))
			{
				fwrite($fp, $thumb_data);
				fclose($fp);
			}
		}
	}
	return $physical;
}

/**
 * Returns the filename extension
 *
 * @param string $filename
 * @return string
 */
function vb_file_ext($filename)
{
	return strtolower(substr(strrchr($filename,'.'),1));
}

/**
 * Imports the custom avatars
 *
 * @global type $db
 * @global type $src_db
 * @global type $convert
 * @global type $config
 * @global type $phpbb_root_path
 */
function vb_import_customavatar()
{
	global $db, $src_db, $convert, $config, $phpbb_root_path;

	$destination_path = $phpbb_root_path . $config['avatar_path'];

	// Avatars in files
	if ( $convert->convertor['avatar_loc'] == 1 )
	{
		$sql = 'SELECT ca.userid AS ca_user_id, ca.filename, ca.width, ca.height, u.userid AS u_user_id, u.avatarrevision FROM ' . $convert->src_table_prefix . 'customavatar ca, ' . $convert->src_table_prefix . 'user u WHERE ca.userid = u.userid';
		$result = $src_db->sql_query($sql);
		while ($row = $src_db->sql_fetchrow($result))
		{
			$avatar_userid = vb_user_id($row['ca_user_id']);
			$avatar_src = $convert->options['forum_path'] . '/' . $convert->convertor['avatar_path'] . 'avatar' . $row['ca_user_id'] . '_' . $row['avatarrevision'] . '.' . vb_file_ext($row['filename']);
			$avatar_dest = $destination_path . '/' . $config['avatar_salt'] . '_' . $avatar_userid . '.' . vb_file_ext($avatar_src);
			$user_avatar = $avatar_userid . '_' . time() . '.' . vb_file_ext($avatar_src);
			$avatar_width = $row['width'];
			$avatar_height = $row['height'];
			// TODO: Check this value is still valid!
			$avatar_type = AVATAR_UPLOAD;
			copy($avatar_src, $avatar_dest);

			$sql = 'UPDATE ' . USERS_TABLE . ' SET '
					. 'user_avatar="' . $user_avatar . '",'
					. 'user_avatar_width=' . $avatar_width . ','
					. 'user_avatar_height=' . $avatar_height . ','
					. 'user_avatar_type=' . $avatar_type . ' '
					. 'WHERE user_id=' . vb_user_id($row['ca_user_id']);
			$db->sql_query($sql);
		}
		$src_db->sql_freeresult($result);
	}
	// Avatars in database
	if ( $convert->convertor['avatar_loc'] == 0 )
	{
		$sql='SELECT * FROM '. $convert->src_table_prefix . 'customavatar';
		$result = $src_db->sql_query($sql);
		while ($row = $src_db->sql_fetchrow($result))
		{
			$avatar_src = $row['filename'];
			$avatar_data = $row['filedata'];
			$avatar_userid = vb_user_id($row['userid']);

			$avatar_dest = $destination_path . '/' . $config['avatar_salt'] . '_' . $avatar_userid . '.' . vb_file_ext($avatar_src);
			$user_avatar = $avatar_userid . '_' . time() . '.' . vb_file_ext($avatar_src);
			$avatar_width = $row['width'];
			$avatar_height = $row['height'];
			// TODO: Check this value is still valid!
			$avatar_type = AVATAR_UPLOAD;

			if ($fp = fopen($avatar_dest, "w"))
			{
				fwrite($fp, $avatar_data);
				fclose($fp);
			}

			$sql = 'UPDATE ' . USERS_TABLE . ' SET '
					. 'user_avatar="' . $user_avatar . '",'
					. 'user_avatar_width=' . $avatar_width . ','
					. 'user_avatar_height=' . $avatar_height . ','
					. 'user_avatar_type=' . $avatar_type . ' '
					. 'WHERE user_id=' . vb_user_id($row['userid']);
			$db->sql_query($sql);
		}
		$src_db->sql_freeresult($result);
	}
}

function vb_get_customprofilepic_path()
{
	global $convert, $config, $phpbb_root_path;

	if (!isset($config['profilepics_path'])) {
		$config['profilepics_path'] = 'images/profile_pics';
	}
	$destination_path = $phpbb_root_path;
	if (substr($destination_path, -1) != '/') {
		$destination_path .= '/';
	}
	$destination_path .= $config['profilepics_path'];
	if (!file_exists($destination_path)) {
		mkdir($destination_path);
	}
	if (!is_writeable($destination_path)) {
		$convert->p_master->error("Custom profile pictures path is not writeable: {$destination_path}", __LINE__, __FILE__);
		exit();
	}
	return $destination_path;
}

/**
 * Import the custome profile pictures. You will need a phpBB extension to deal with them.
 *
 * @global type $db
 * @global type $src_db
 * @global type $convert
 * @global type $config
 * @global type $phpbb_root_path
 * @param type $profilepics_path
 */
function vb_import_customprofilepic()
{
	global $db, $src_db, $convert, $config;

	$destination_path = vb_get_customprofilepic_path();

	$sql='SELECT * FROM '. $convert->src_table_prefix . 'customprofilepic';
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result))
	{
		$profilepic_src = $row['filename'];
		$profilepic_data = $row['filedata'];
		$profilepic_userid = vb_user_id($row['userid']);

		$profilepic_dest = $destination_path . '/' . $config['avatar_salt'] . '_' . $profilepic_userid . '.' . vb_file_ext($profilepic_src);
		$user_profilepic = $profilepic_userid . '_' . time() . '.' . vb_file_ext($profilepic_src);
		$profilepic_width = $row['width'];
		$profilepic_height = $row['height'];
		if ($fp = fopen($profilepic_dest, "w"))
		{
			fwrite($fp, $profilepic_data);
			fclose($fp);
		}
		$sql = 'UPDATE ' . USERS_TABLE . ' SET '
				. 'user_profilepic="' . $user_profilepic . '", '
				. 'user_profilepic_width=' . $profilepic_width . ', '
				. 'user_profilepic_height=' . $profilepic_height . ' '
				. 'WHERE user_id=' . vb_user_id($row['userid']);
		$db->sql_query($sql);
	}
	$src_db->sql_freeresult($result);
}

function vb_get_signaturepic_path()
{
	global $convert, $config, $phpbb_root_path;

	if (!isset($config['signaturepic_path'])) {
		$config['signaturepic_path'] = 'images/signature_pics';
	}
	$destination_path = $phpbb_root_path;
	if (substr($destination_path, -1) != '/') {
		$destination_path .= '/';
	}
	$destination_path .= $config['signaturepic_path'];
	if (!file_exists($destination_path)) {
		mkdir($destination_path);
	}
	if (!is_writeable($destination_path)) {
		$convert->p_master->error("Custom signature pictures path is not writeable: {$destination_path}", __LINE__, __FILE__);
		exit();
	}
	return $destination_path;
}

/**
 * Imports the signature profile pictures. You will need to install a phpBB extension to deal with these.
 * @global type $db
 * @global type $src_db
 * @global type $convert
 * @global type $config
 * @global type $phpbb_root_path
 */
function vb_import_signaturepic()
{
	global $db, $src_db, $convert, $config;

	if (vb_version() >= 370)
	{
		$destination_path = vb_get_signaturepic_path();

		$sql = 'SELECT * FROM '. $convert->src_table_prefix . 'sigpic';
		$result = $src_db->sql_query($sql);
		while ($row = $src_db->sql_fetchrow($result))
		{
			$signaturepic_src = $row['filename'];
			$signaturepic_data = $row['filedata'];
			$signaturepic_userid = vb_user_id($row['userid']);

			$signaturepic_dest = $destination_path . '/' . $config['avatar_salt'] . '_' . $signaturepic_userid . '.' . vb_file_ext($signaturepic_src);
			$user_sigpic = $signaturepic_userid . '_' . time() . '.' . vb_file_ext($signaturepic_src);
			$sigpic_width = $row['width'];
			$sigpic_height = $row['height'];
			if ($fp = fopen($signaturepic_dest, "w"))
			{
				fwrite($fp, $signaturepic_data);
				fclose($fp);
			}
			$sql = 'UPDATE ' . USERS_TABLE . ' SET '
					. 'user_sigpic="' . $user_sigpic . '", '
					. 'user_sigpic_width=' . $sigpic_width . ', '
					. 'user_sigpic_height=' . $sigpic_height . ' '
					. 'WHERE user_id=' . vb_user_id($row['userid']);
			$db->sql_query($sql);
		}
		$src_db->sql_freeresult($result);
	}
}

/**
 * Retreives some configuration values from the default "registered" settings
 *
 * @global type $src_db
 * @global type $convert
 * @return type
 */
function vb_get_registered_usergroup_settings()
{
	Global $src_db, $convert;

	$row = Array();
	$sql = 'SELECT avatarmaxwidth,avatarmaxheight,avatarmaxsize FROM '. $convert->src_table_prefix . "usergroup WHERE title='" . VB_GROUP_USERS . "'";
	$result = $src_db->sql_query($sql);
	$row = $src_db->sql_fetchrow($result);
	return $row;
}

/**
 * Avatar maximum filesize
 *
 * @return int
 */
function vb_get_avatar_filesize()
{
	$row = vb_get_registered_usergroup_settings();
	return isset($row['avatarmaxsize']) ? $row['avatarmaxsize'] : 20 * 1024;
}

/**
 * Avatar maximum height
 *
 * @return int
 */
function vb_get_avatar_max_height()
{
	$row = vb_get_registered_usergroup_settings();
	return isset($row['avatarmaxheight']) ? $row['avatarmaxheight'] : 90;
}

/**
 * Avatar maximum width
 *
 * @return int
 */
function vb_get_avatar_max_width()
{
	$row = vb_get_registered_usergroup_settings();
	return isset($row['avatarmaxwidth']) ? $row['avatarmaxwidth'] : 90;
}

/**
* Calculate the correct to_address field for private messages
*/
function vb_privmsgs_to_user_array($touserarray)
{
	$users = Array();
	$msg = unserialize($touserarray);
	if (isset($msg['cc'])) {
		foreach ($msg['cc'] as $user_id => $user_name) {
			$users[] = 'u_' . vb_user_id($user_id);
		}
	}
	return implode(':', $users);
}

/**
 *
 * @param type $touserarray
 * @return type
 */
function vb_privmsgs_bcc_user_array($touserarray)
{
	$users = Array();
	$msg = unserialize($touserarray);
	if (isset($msg['bcc'])) {
		foreach ($msg['bcc'] as $user_id => $user_name) {
			$users[] = 'u_' . vb_user_id($user_id);
		}
	}
	return implode(':', $users);
}

// Field messageread:
//	0 = unread
//	1 = read
//	2 = replied
//	3 = forwarded
function vb_unread_pm($messageread)
{
	return ($messageread == 0) ? 1 : 0;
}

function vb_replied_pm($messageread)
{
	return ($messageread == 2) ? 1 : 0;
}

function vb_forwarded_pm($messageread)
{
	return ($messageread == 3) ? 1 : 0;
}

/**
 * Imports private message folders
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 */
function vb_convert_pm_folders()
{
	Global $src_db, $db, $convert;

	$db->sql_query($convert->truncate_statement . PRIVMSGS_FOLDER_TABLE);

	$folder_id = 1;
	$rows = Array();
	$folders = Array();
	$sql = 'SELECT userid,pmfolders FROM '. $convert->src_table_prefix . "usertextfield WHERE pmfolders IS NOT NULL";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$definition = unserialize($row['pmfolders']);
		if (!empty($definition) && is_array($definition)) {
			foreach ($definition as $id => $folder_name) {
				$folders[] = Array(
								'folder_id' => $folder_id,
								'user_id' => vb_user_id($row['userid']),
								'folder_name' => $folder_name,
								'pm_count' => 0,
							);
				$row['folders'][$id] = $folder_id;
				$folder_id++;
			}
		}
		// Keep it for later
		$rows[$row['userid']] = $row;
	}

	$db->sql_multi_insert(PRIVMSGS_FOLDER_TABLE, $folders);
	vb_conversion_log('vb_convert_pm_folder(): ' . count($folders) . ' PM folder(s) found');

	$datastore = ConversionDataStore::getInstance();
	$datastore->clearData('pmfolders');
	$datastore->setData('pmfolders', $rows);
}

/**
 * Returns converted folder ID from source ID
 *
 * @global type $convert
 * @param type $folder_id
 * @return type
 */
function vb_folder_id($folder_id)
{
	Global $convert;

	$vb_folder_id = 0;

	if ($folder_id == -1) {
		if ($convert->row['messageread'] == 0) {
			// Sent and unread
			$vb_folder_id = PRIVMSGS_OUTBOX;
		} else {
			// Sent and read
			$vb_folder_id = PRIVMSGS_SENTBOX;
		}

	} elseif ($folder_id == 0) {
		$vb_folder_id = PRIVMSGS_INBOX;

	} elseif ($folder_id > 0) {
		$datastore = ConversionDataStore::getInstance();
		$folders = $datastore->getData('pmfolders');

		if (isset($folders[$convert->row['poster_id']]['folders'][$folder_id])) {
			$vb_folder_id = $folders[$convert->row['poster_id']]['folders'][$folder_id];
		} else {
			vb_conversion_log("vb_folder_id(): WARNING folder not found for user ID {$convert->row['poster_id']} folder ID {$folder_id}!");
		}
	}
	return $vb_folder_id;
}

/**
 * Imports "friends" and "foes" (also called "zebra")
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 */
function vb_convert_friends_and_foes()
{
	global $src_db, $db, $convert;

	$db->sql_query($convert->truncate_statement . ZEBRA_TABLE);

	$sql = "SELECT userid, buddylist, ignorelist FROM {$convert->src_table_prefix}usertextfield
		ORDER BY userid ASC";
	$result = $src_db->sql_query($sql);

	$zebra = array();
	while ($row = $src_db->sql_fetchrow($result))
	{
		$row['userid'] = intval($row['userid']);
		$friends = array();
		$foes = array();
		$buddylist = explode(' ', $row['buddylist']);
		if (!empty($buddylist)) {
			$friends = array_unique($buddylist);
		}
		$ignorelist = explode(' ', $row['ignorelist']);
		if (!empty($ignorelist)) {
			$foes = array_unique($ignorelist);
		}
		$intersect = array_intersect($friends, $foes);
		foreach($intersect as $both) {
			if ($both) {
				vb_conversion_log("vb_convert_friends_and_foes(): WARNING buddy id {$both} is both friend and foe of user id {$row['userid']}!");
				$key = array_search($both, $friends);
				unset($friends[$key]);
			}
		}
		$zebra[$row['userid']]['friends'] = $friends;
		$zebra[$row['userid']]['foes'] = $foes;
	}
	$src_db->sql_freeresult($result);

	$inserts = array();
	$i = 0;
	foreach ($zebra as $user_id => $which_ary)
	{
		foreach ($which_ary as $which => $buddies)
		{
			foreach ($buddies as $zebra_id)
			{
				$zebra_id = intval($zebra_id);
				if ($zebra_id >0)
				{
					$inserts[] = array(
						'user_id'	=> vb_user_id($user_id),
						'zebra_id'	=> $zebra_id,
						'friend'	=> (int) ($which == 'friends'),
						'foe'		=> (int) ($which == 'foes'),
					);
					$i++;
				}
			}
		}
	}
	$db->sql_multi_insert(ZEBRA_TABLE, $inserts);
}

/**
 * Gives a profile field size (for texts and strings)
 *
 * @param array $profilefield
 * @return int
 */
function vb_profile_field_length($profilefield)
{
	$field_length = '';
	if ($profilefield['type'] == 'textarea') {
		$field_length = "{$profilefield['height']}|{$profilefield['size']}";
	} else {
		$field_length = $profilefield['size'];
	}
	return $field_length;
}

/**
 * Gives a profile field minimum length
 *
 * @param array $profilefield
 * @return int
 */
function vb_profile_field_minlen($profilefield)
{
	$field_minlen = '';
	if ($profilefield['type'] == 'textarea') {
		$field_minlen = 2;
	} elseif ($profilefield['type'] == 'input') {
		$field_minlen = 1;
	} else {
		$field_minlen = 0;
	}
	return $field_minlen;
}

/**
 * Gives a profile field maximum length
 *
 * @param array $profilefield
 * @return int
 */
function vb_profile_field_maxlen($profilefield)
{
	$field_maxlen = '';
	if (($profilefield['type'] == 'textarea') || ($profilefield['type'] == 'input')) {
		$field_maxlen = $profilefield['maxlength'];
	} elseif (($profilefield['type'] == 'radio') || ($profilefield['type'] == 'select')) {
		$data = unserialize($profilefield['data']);
		$field_maxlen = count($data);
	} else {
		$field_maxlen = 0;
	}
	return $field_maxlen;
}

/**
 * Gives a profile field default value
 *
 * @param array $profilefield
 * @return mixed
 */
function vb_profile_field_default_value($profilefield)
{
	$default_value = '';
	if (($profilefield['type'] == 'radio') || ($profilefield['type'] == 'select')) {
		$profilefield['def'] = intval($profilefield['def']);
		if ($profilefield['def'] == 0) {
			// No default
			$default_value = 0;
		} elseif ($profilefield['def'] == 1) {
			// Default is first item (even if empty)
			$default_value = 1;
		} else {
			// Default is first non empty item
			$data = unserialize($profilefield['data']);
			$i = -1;
			do {
				$i++;
			} while (($data[$i] != '') && ($i <= count($data)));
			$default_value = $i +1;
		}
	}
	return $default_value;
}

/**
 * Gives a profile field validation string
 *
 * @param array $profilefield
 * @return string
 */
function vb_profile_field_validation($profilefield)
{
	$field_validation = '';
	if (($profilefield['type'] == 'input') || ($profilefield['type'] == 'textarea')) {
		$field_validation = '.*';
	}
	return $field_validation;
}

/**
 * If a profile field is required
 *
 * @param array $profilefield
 * @return int
 */
function vb_profile_field_required($profilefield)
{
	$field_required = 0;
	$required = intval($profilefield['required']);
	if ($required == 0) {
		// Not required
		$field_required = 0;
	} elseif ($required == 1) {
		// Yes, at registration and profile updating
		$field_required = 1;
	} elseif ($required == 2) {
		// No but display at registration
		$field_required = 0;
	} elseif ($required == 3) {
		// Yes, always
		$field_required = 1;
	}
	return $field_required;
}

/**
 * If a profile field should be shown on the registration page
 *
 * @param array $profilefield
 * @return int
 */
function vb_profile_field_show_on_reg($profilefield)
{
	$field_show_on_reg = 0;
	$required = intval($profilefield['required']);
	if ($required == 0) {
		// Not required
		$field_show_on_reg = 0;
	} elseif ($required == 1) {
		// Yes, at registration and profile updating
		$field_show_on_reg = 1;
	} elseif ($required == 2) {
		// Not required but display at registration
		$field_show_on_reg = 1;
	} elseif ($required == 3) {
		// Yes, always
		$field_show_on_reg = 1;
	}
	return $field_show_on_reg;
}

/**
 * Builds a profile field name (or ident) from its title
 *
 * @param array $profilefield
 * @return type
 */
function vb_build_field_name($profilefield)
{
	static $number = null;

	$field_name = strtolower($profilefield['title']);
	//$field_name = preg_replace('/[^a-z0-9 _]+/u', '', $field_name);
	// Only letters and _ are allowed in a name
	$field_name = preg_replace('/[^a-z _]+/u', '', $field_name);
	$field_name = str_replace(' ', '_', $field_name);
	$field_name = trim($field_name, '_');
	if (strlen($field_name) > 17) {
		$field_name = trim(substr($field_name, 0 , 17), '_');
	}
	if (empty($field_name))
	{
		// It's using special characters so we have to give a default name
		if (is_null($number))
		{
			$number = ord('a');
		}
		else
		{
			$number++;
		}
		$field_name = 'imported_field_' . chr($number);
	}
	return $field_name;
}

/**
 * Imports the custom profile fields
 *
 * @global type $db
 * @global type $src_db
 * @global type $convert
 * @global type $phpbb_container
 */
function vb_convert_profile_custom_fields()
{
	global $convert, $phpbb_container;

	//$languagecode = $convert->convertor['default_language_code'];
	$languagecode = get_vb3_default_language_code();
	$convertible_types = array_keys($convert->convertor['profilefields_type_convert_table']);

	$db = get_db_object();
	$languages = array();
	$sql = "SELECT lang_id FROM " . LANG_TABLE;
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		$languages[] = $row['lang_id'];
	}
	$db->sql_freeresult($result);
	vb_conversion_log("vb_convert_profile_custom_fields(): " . count($languages) . " languages loaded from phpBB.");

	$src_db = get_src_db_object();
	if (vb_version() >= 370)
	{
		$sql = "SELECT profilefieldid,required,hidden,maxlength,size,displayorder,editable,type,data,height,def,optional,searchable,memberlist,regex,form,html"
				. " FROM {$convert->src_table_prefix}profilefield ORDER BY displayorder";
	} else {
		$sql = "SELECT profilefieldid,title,description,required,hidden,maxlength,size,displayorder,editable,type,data,height,def,optional,searchable,memberlist,regex,form,html"
				. " FROM {$convert->src_table_prefix}profilefield ORDER BY displayorder";
	}
	$result = $src_db->sql_query($sql);

	$profilefields = array();
	while ($row = $src_db->sql_fetchrow($result)) {
		if (in_array($row['type'], $convertible_types)) {
			//$profilefields[$row['profilefieldid']] = $row;
			$row['title'] = vb_set_encoding_from_source($row['title'], 'profilefield', 'title');
			$row['description'] = vb_set_encoding_from_source($row['title'], 'profilefield', 'description');
			// Keep the display order
			$profilefields[] = $row;
		} else {
			vb_conversion_log("vb_convert_profile_custom_fields(): WARNING custom profile ID {$row['profilefieldid']} can't be converted because its type '{$row['type']}' is not managed by phpBB.");
		}
	}
	$src_db->sql_freeresult($result);
	vb_conversion_log("vb_convert_profile_custom_fields(): " . count($profilefields) . " custom profile field(s) loaded.");

	$db = get_db_object();
	$existing_fields = array();
	$sql = "SELECT field_id,field_name FROM " . PROFILE_FIELDS_TABLE;
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		$existing_fields[$row['field_name']] = $row['field_id'];
	}
	$db->sql_freeresult($result);
	vb_conversion_log("vb_convert_profile_custom_fields(): " . count($existing_fields) . " existing profile field(s) loaded.");

	$src_db = get_src_db_object();
	$definition = array();
	if (vb_version() >= 370)
	{
		$sql = "SELECT languageid,phrasegroup_cprofilefield FROM {$convert->src_table_prefix}language WHERE languagecode='{$languagecode}'";
		$result = $src_db->sql_query($sql);
		$row = $src_db->sql_fetchrow($result);
		if (!empty($row)) {
			$definition = unserialize($row['phrasegroup_cprofilefield']);
		}
	}

	for ($i=0; $i <count($profilefields); $i++) {
		$id = $profilefields[$i]['profilefieldid'];
		$field_desc = "field{$id}_desc";
		$field_title = "field{$id}_title";
		if (isset($definition[$field_desc]) && isset($definition[$field_title])) {
			$profilefields[$i]['description'] = $definition[$field_desc];
			$profilefields[$i]['title'] = $definition[$field_title];
		}
		if (isset($profilefields[$i]['title']))
		{
			if (isset($convert->convertor['profilefields_convert_table'][$profilefields[$i]['title']])) {
				$profilefields[$i]['name'] = $convert->convertor['profilefields_convert_table'][$profilefields[$i]['title']];
			} else {
				$profilefields[$i]['name'] = vb_build_field_name($profilefields[$i]);
			}
		} else {
			vb_conversion_log("vb_convert_profile_custom_fields(): WARNING not enough information to import profile field ID {$id}.");
		}
	}

	$db = get_db_object();
	$sql = "SELECT max(field_id) AS max_field_id, max(field_order) AS max_field_order FROM " . PROFILE_FIELDS_TABLE;
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$field_id = $row['max_field_id'];
	$field_order = $row['max_field_order'];
	$db->sql_freeresult($result);

	$data_conversion = array();
	$new_profile_fields = array();
	$new_profile_field_options = array();
	$new_profile_field_langs = array();
	$update_profile_fields = array();
	$new_database_fields = array();
	foreach ($profilefields as $profilefield)
	{
		$field_name = $profilefield['name'];
		if (!empty($field_name) && isset($existing_fields[$field_name])) {
			// We update the existing one
			$update_field_id = $existing_fields[$field_name];
			$update_profile_field = array(
				'field_length' => vb_profile_field_length($profilefield),
				'field_minlen' => vb_profile_field_minlen($profilefield),
				'field_maxlen' => vb_profile_field_maxlen($profilefield),
				'field_novalue' => vb_profile_field_default_value($profilefield),
				'field_default_value' => vb_profile_field_default_value($profilefield),
				'field_validation' =>  vb_profile_field_validation($profilefield),
				'field_required' => vb_profile_field_required($profilefield),
				'field_show_on_reg' => vb_profile_field_show_on_reg($profilefield),
				'field_hide' => $profilefield['hidden'],
				'field_no_view' => $profilefield['hidden'],
				'field_active' => 1,
				'field_show_profile' => ($profilefield['hidden']) ? 0 : 1,
			);
			$update_profile_fields[$update_field_id] = $update_profile_field;

			if (!empty($profilefield['data'])) {
				$data = unserialize($profilefield['data']);
				foreach ($data as $option_id => $option_value) {
					// Build a conversion table for the data
					$data_conversion["field{$profilefield['profilefieldid']}"][$option_value] = $option_id +1;
				}
			}

		} else {
			// We create a new one
			$field_id++;
			$field_order++;
			$field_name = vb_build_field_name($profilefield);
			vb_conversion_log("vb_convert_profile_custom_fields(): new custom profile field ID {$field_id} named '{$field_name}'.");
			$new_profile_field = array(
				'field_id' => $field_id,
				'field_name' => $field_name,
				'field_type' => $convert->convertor['profilefields_type_convert_table'][$profilefield['type']],
				'field_ident' => $field_name,
				'field_length' => vb_profile_field_length($profilefield),
				'field_minlen' => vb_profile_field_minlen($profilefield),
				'field_maxlen' => vb_profile_field_maxlen($profilefield),
				'field_novalue' => vb_profile_field_default_value($profilefield),
				'field_default_value' => vb_profile_field_default_value($profilefield),
				'field_validation' =>  vb_profile_field_validation($profilefield),
				'field_required' => vb_profile_field_required($profilefield),
				'field_show_on_reg' => vb_profile_field_show_on_reg($profilefield),
				'field_hide' => $profilefield['hidden'],
				'field_no_view' => $profilefield['hidden'],
				'field_active' => 1,
				'field_order' => $field_order,
				'field_show_profile' => ($profilefield['hidden']) ? 0 : 1,
				'field_show_on_vt' => 0,	// Arbitrary
				'field_show_novalue' => 0,	// Arbitrary
				'field_show_on_pm' => 0,	// Arbitrary
				'field_show_on_ml' => 0,	// Arbitrary
				'field_is_contact' => 0,
				'field_contact_desc' => '',
				'field_contact_url' => '',
			);
			$new_profile_fields[] = $new_profile_field;

			// We make one record for each language (no, we're not trying to translate)
			foreach ($languages as $lang_id) {
				$new_profile_field_lang = array(
					'field_id' => $field_id,
					'lang_id' => $lang_id,
					'lang_name' => $profilefield['title'],
					'lang_explain' => $profilefield['description'],
				);
				$new_profile_field_langs[] = $new_profile_field_lang;
			}

			if (!empty($profilefield['data'])) {
				$data = unserialize($profilefield['data']);
				foreach ($data as $option_id => $option_value) {
					// We make one record for each language (no, we're not trying to translate)
					foreach ($languages as $lang_id) {
						$new_profile_field_option = array(
							'field_id' => $field_id,
							'lang_id' => $lang_id,
							'option_id' => $option_id,
							'field_type' => 'profilefields.type.dropdown',
							'lang_value' => $option_value,
						);
						$new_profile_field_options[] = $new_profile_field_option;
					}
					// Also build a conversion table for the data
					$data_conversion["field{$profilefield['profilefieldid']}"][$option_value] = $option_id +1;
				}
			}

			// And create the database field
			if (($profilefield['type'] == 'select') || ($profilefield['type'] == 'radio')) {
				$new_database_field = array(
					'name' => "pf_{$field_name}",
					'type' => 'UINT',
				);
			} elseif ($profilefield['type'] == 'textarea') {
				$new_database_field = array(
					'name' => "pf_{$field_name}",
					'type' => 'MTEXT',
				);
			} elseif ($profilefield['type'] == 'input') {
				$new_database_field = array(
					'name' => "pf_{$field_name}",
					'type' => 'VCHAR',
				);
			}
			$new_database_fields[] = $new_database_field;
		}

		// Also update the data field conversion table
		$convert->convertor['profilefields_field_table']["field{$profilefield['profilefieldid']}"] = "pf_{$field_name}";
	}

	$db_tools = $phpbb_container->get('dbal.tools');
	foreach ($new_database_fields as $new_database_field) {
		if (!$db_tools->sql_column_exists(PROFILE_FIELDS_DATA_TABLE, $new_database_field['name'])) {
			$db_tools->sql_column_add(PROFILE_FIELDS_DATA_TABLE, $new_database_field['name'], array($new_database_field['type'], null));
		} else {
			vb_conversion_log("vb_convert_profile_custom_fields(): WARNING column '{$new_database_field['name']}' already exists in '" . PROFILE_FIELDS_DATA_TABLE . "'.");
		}
	}
	$db->sql_multi_insert(PROFILE_FIELDS_TABLE, $new_profile_fields);
	$db->sql_multi_insert(PROFILE_LANG_TABLE, $new_profile_field_langs);
	$db->sql_multi_insert(PROFILE_FIELDS_LANG_TABLE, $new_profile_field_options);

	foreach ($update_profile_fields as $field_id => $update_profile_field) {
		$sql = 'UPDATE ' . PROFILE_FIELDS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $update_profile_field) . "
			WHERE field_id = $field_id";
		$db->sql_query($sql);
	}

	$db->sql_query($convert->truncate_statement . PROFILE_FIELDS_DATA_TABLE);

	$src_db = get_src_db_object();
	$user_fields_data = array();
	$sql = "SELECT u.userid AS user_id,u.homepage,u.icq,u.aim,u.yahoo,u.msn,u.skype,uf.*"
			. " FROM {$convert->src_table_prefix}user u"
			. " LEFT JOIN {$convert->src_table_prefix}userfield uf"
			. " ON u.userid=uf.userid";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$user_field_data = array(
			'user_id' => vb_user_id($row['userid']),
		);
		foreach ($convert->convertor['profilefields_field_table'] as $source_field => $dest_field) {
			if (isset($data_conversion[$source_field])) {
				$user_field_data[$dest_field] = isset($data_conversion[$source_field][$row[$source_field]]) ? $data_conversion[$source_field][$row[$source_field]] : 0;
			} else {
				$user_field_data[$dest_field] = vb_set_encoding_from_source($row[$source_field], 'user', $source_field);
			}
		}
		// All the other stupid unused profile fields can't accept a NULL, so we need to fill in the missing ones with an empty string
		foreach ($existing_fields as $required_field => $temp) {
			if (!isset($user_field_data["pf_{$required_field}"])) {
				$user_field_data["pf_{$required_field}"] = '';
			}
		}
		$user_fields_data[] = $user_field_data;
	}
	$src_db->sql_freeresult($result);

	$db = get_db_object();
	$db->sql_multi_insert(PROFILE_FIELDS_DATA_TABLE, $user_fields_data);
}

/**
 * Converts vb visible field to phpBB visibility field
 *
 * @param int $visible
 * @return int
 */
function vb_convert_visible($visible)
{
	global $convert_row;

	// vb codes:
	// 0 = "Moderated": waiting for approval
	// 1 = Visible
	// 2 = Soft delete

	$visible = intval($visible);

	return $visible;
}

function vb_fix_softdeleted($deletedcount)
{
	global $convert_row;

	$softdeleted = intval($deletedcount);
	if (($softdeleted == 0) && (isset($convert_row['d_dateline'])) && (intval($convert_row['d_dateline']) > 0)) {
		$softdeleted = 1;
	}
	return $softdeleted;
}

/**
 * Import post icons
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 * @global type $phpbb_root_path
 * @global type $config
 */
function vb_import_icons()
{
	global $convert, $phpbb_root_path, $config;

	// == Source ==
	// 1  = post (notepad)
	// 2  = arrow
	// 3  = light bulb
	// 4  = exclamation
	// 5  = question
	// 6  = cool
	// 7  = smile
	// 8  = angry
	// 9  = unhappy
	// 10 = talking (happy face)
	// 11 = red face
	// 12 = wink
	// 13 = thumbs down
	// 14 = thumbs up

	// Only three of these basic icons can be reused
	$basic_conversion = array(
		'Exclamation' => 'alert.gif',
		'Question' => 'question.gif',
		'Talking' => 'mrgreen.gif',
	);
	$basic_conversion_source = array();
	$basic_conversion_dest = array();

	$src_db = get_src_db_object();
	$source = array();
	$sql = "SELECT iconid,title,iconpath FROM {$convert->src_table_prefix}icon";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$row['iconid'] = intval($row['iconid']);
		$row['title'] = vb_set_encoding_from_source($row['title'], 'icon', 'title');
		$source[$row['iconid']] = $row;
		foreach ($basic_conversion as $title => $icons_url)
		{
			if ($title == $row['title']) {
				$basic_conversion_source[$title] = $row['iconid'];
			}
		}
	}
	$src_db->sql_freeresult($result);

	// == destination ==
	// 1  = flames
	// 2  = red face
	// 3  = happy face
	// 4  = heart
	// 5  = star
	// 6  = radioactive
	// 7  = thinking
	// 8  = information
	// 9  = question
	// 10 = exclamation

	$db = get_db_object();
	$dest = array();
	$existing = array();
	$icon_id = 0;
	$sql = "SELECT icons_id,icons_url,icons_order FROM " . ICONS_TABLE;
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result)) {
		$row['icons_id'] = intval($row['icons_id']);
		$dest[$row['icons_id']] = $row;
		$existing[$row['icons_url']] = $row;
		if ($row['icons_id'] > $icon_id) {
			$icon_id = $row['icons_id'];
		}
		foreach ($basic_conversion as $title => $icons_url)
		{
			if (stripos($row['icons_url'], $icons_url) !== FALSE) {
				$basic_conversion_dest[$title] = $row['icons_id'];
			}
		}
	}
	$db->sql_freeresult($result);

	$icon_conversion = array();
	foreach ($source as $icon_source)
	{
		if (isset($basic_conversion_source[$icon_source['title']])) {
			// The source is in the list, so we have a match
			if (isset($basic_conversion_dest[$icon_source['title']])) {
				// Here's a match!
				$icon_conversion[$icon_source['iconid']] = $basic_conversion_dest[$icon_source['title']];
			}
		} else {
			// We need to import this one
			$filename_src = $convert->options['forum_path'] . '/' . $icon_source['iconpath'];
			if (is_file($filename_src)) {
				$imagesize = getimagesize($filename_src);
				if (!empty($imagesize)) {
					$width = $imagesize[0];
					$height = $imagesize[1];

					$folder_icons = $phpbb_root_path . '/' . $config['icons_path'];
					$folder_dest = $folder_icons . '/imported';
					if (!is_dir($folder_dest)) {
						@mkdir($folder_dest);
					}
					if (is_dir($folder_dest))
					{
						if (is_file($folder_icons . '/index.htm') && !is_file($folder_dest . '/index.htm')) {
							@copy($folder_icons . '/index.htm', $folder_dest . '/index.htm');
						}
						$pos = strrpos($filename_src, '.');
						if ($pos !== FALSE) {
							$file_ext = substr($filename_src, $pos);
							$icon_name = strtolower(str_replace(' ', '_', $icon_source['title']));
							if (file_exists($filename_src)) {
								@copy($filename_src, $folder_dest . '/' . $icon_name . $file_ext);
								$icon_id++;
								$icon_url = 'imported/' . $icon_name . $file_ext;
								// Protection in case another conversion is run after another
								if (!isset($existing[$icon_url])) {
									$insert = array(
										'icons_id' => $icon_id,
										'icons_url' => $icon_url,
										'icons_width' => $width,
										'icons_height' => $height,
										'icons_order' => $icon_id,
										'display_on_posting' => 1,
									);
									$inserts[] = $insert;
									$icon_conversion[$icon_source['iconid']] = $icon_id;
									vb_conversion_log("vb_import_icons(): icon '{$icon_url}' imported.");
								}
							} else {
								vb_conversion_log("vb_import_icons(): WARNING cannot find the icon file '{$filename_src}'.");
							}
						}
					} else {
						vb_conversion_log("vb_import_icons(): WARNING cannot create the imported icon folder '{$folder_dest}'.");
					}
				}
			}
		}
	}
	$db->sql_multi_insert(ICONS_TABLE, $inserts);

	$ds = ConversionDataStore::getInstance();
	$ds->setData('icon_conversion', $icon_conversion);
	unset($ds);
}

/**
 * Transforms post icon ID
 *
 * @param int $source_id
 * @return int
 */
function vb_icon_id($source_id)
{
	static $icon_conversion = array();

	if (empty($icon_conversion)) {
		$ds = ConversionDataStore::getInstance();
		$icon_conversion = $ds->getData('icon_conversion');
		unset($ds);
	}

	$dest_id = $icon_conversion[$source_id];
	return $dest_id;
}

/**
 * Convert edited post counter information
 */
function vb_import_edited_posts_counter()
{
	global $src_db, $db, $convert;

	if (vb_version() >= 370)
	{
		$sql = "SELECT postid,count(postedithistoryid) AS nb FROM {$convert->src_table_prefix}postedithistory GROUP BY postid HAVING count(postedithistoryid)>2";
		$result = $src_db->sql_query($sql);
		while ($row = $src_db->sql_fetchrow($result)) {
			$db->sql_query("UPDATE " . POSTS_TABLE . " SET post_edit_count=" . (intval($row['nb']) -1) . " WHERE post_id=" . $row['postid']);
		}
		$src_db->sql_freeresult($result);
	}
}


function vb_allow_pm()
{
	global $convert_row;

	return ($convert_row['useroptions'] & user_options_receivepm);
}

function vb_notify_pm()
{
	global $convert_row;

	return ($convert_row['useroptions'] & user_options_emailonpm);
}

function vb_viewonline()
{
	global $convert_row;

	return !($convert_row['useroptions'] & user_options_invisible);
}

function vb_user_notify($autosubscribe)
{
	return ($autosubscribe >= 0);
}

function vb_set_user_options($options)
{
	global $convert_row;

	// Key need to be set in row, else default value is chosen
	$keyoptions = array(
		'viewimg'		=> array('bit' => 0, 'default' => 1),
		'viewflash'		=> array('bit' => 1, 'default' => 1),
		'viewsmilies'	=> array('bit' => 2, 'default' => 1),
		'viewsigs'		=> array('bit' => 3, 'default' => 1),
		'viewavatars'	=> array('bit' => 4, 'default' => 1),
		'viewcensors'	=> array('bit' => 5, 'default' => 1),
		'attachsig'		=> array('bit' => 6, 'default' => 1),
		'bbcode'		=> array('bit' => 8, 'default' => 1),
		'smilies'		=> array('bit' => 9, 'default' => 1),
		'sig_bbcode'	=> array('bit' => 15, 'default' => 1),
		'sig_smilies'	=> array('bit' => 16, 'default' => 1),
		'sig_links'		=> array('bit' => 17, 'default' => 1),
	);

	// Set the options from the parameter
	$convert_row['viewimg'] = ($options & user_options_showimages);
	$convert_row['viewsigs'] = ($options & user_options_showsignatures);
	$convert_row['viewavatars'] = ($options & user_options_showavatars);

	$option_field = 0;

	foreach ($keyoptions as $key => $key_ary)
	{
		$value = (isset($convert_row[$key])) ? (int) $convert_row[$key] : $key_ary['default'];

		if ($value && !($option_field & 1 << $key_ary['bit']))
		{
			$option_field += 1 << $key_ary['bit'];
		}
	}

	return $option_field;
}

/**
 * In VB when deleting a first post, the information is only recorded in the thread.
 * Guess which information is taking precendence on phpBB side?
 */
function vb_fix_deleted_threads()
{
	Global $db;

	$updates = array();
	$sql = "SELECT p.post_id, t.topic_id, topic_delete_time, topic_delete_reason, topic_delete_user FROM " . POSTS_TABLE . " p"
			. " INNER JOIN " . TOPICS_TABLE . " t ON (p.topic_id=t.topic_id)"
			. " WHERE post_delete_time=0 AND topic_delete_time>0 AND topic_visibility=" . ITEM_DELETED;
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result))
	{
		$updates[$row['post_id']] = array(
			'post_visibility' => ITEM_DELETED,
			'post_delete_time' => intval($row['topic_delete_time']),
			'post_delete_reason' => $row['topic_delete_reason'],
			'post_delete_user' => intval($row['topic_delete_user']),
		);
	}
	$db->sql_freeresult($result);

	vb_conversion_log("vb_fix_deleted_threads(): found " . count($updates) . " posts to mark as deleted.");

	foreach ($updates as $post_id => $record)
	{
		$sql = 'UPDATE ' . POSTS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $record) . "
			WHERE post_id = $post_id";
		$db->sql_query($sql);
	}
}

/**
 * Convert all the banned email addresses
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 */
function vb_convert_banemail()
{
	global $convert;

	$src_db = get_src_db_object();
	$emails = array();
	$sql = "SELECT data FROM {$convert->src_table_prefix}datastore WHERE title='banemail'";
	$result = $src_db->sql_query($sql);
	if ($row = $src_db->sql_fetchrow($result)) {
		$emails = explode(' ', $row['data']);
	}
	$src_db->sql_freeresult($result);

	$bans = array();
	if (!empty($emails))
	{
		foreach ($emails as $email)
		{
			if (substr($email, 0, 1) == '@') {
				$email = '*' . $email;
			}
			$bans[] = array(
				'ban_userid' => 0,
				'ban_ip' => '',
				'ban_email' => vb_set_encoding_from_source($email, 'datastore', 'data'),
				'ban_start' => time(),
				'ban_end' => 0,
				'ban_exclude' => 0,
				'ban_reason' => '',
				'ban_give_reason' => '',
			);
		}
	}
	if (!empty($bans)) {
		$db = get_db_object();
		$db->sql_multi_insert(BANLIST_TABLE, $bans);
	}
}

/**
 * Convert all the censored words
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 */
function vb_convert_censorwords()
{
	global $convert;

	$src_db = get_src_db_object();
	$sql = "SELECT value FROM {$convert->src_table_prefix}setting WHERE varname='censorwords'";
	$result = $src_db->sql_query($sql);
	if ($row = $src_db->sql_fetchrow($result)) {
		$words = explode(' ', $row['value']);
	}
	$src_db->sql_freeresult($result);

	$character = '*';
	$sql = "SELECT value FROM {$convert->src_table_prefix}setting WHERE varname='censorchar'";
	$result = $src_db->sql_query($sql);
	if ($row = $src_db->sql_fetchrow($result)) {
		$character = $row['value'];
	}
	$src_db->sql_freeresult($result);

	$censorwords = array();
	if (!empty($words))
	{
		foreach ($words as $word)
		{
			if ((substr($word, 0, 1) == '{') && (substr($word, -1) == '}')) {
				$word = substr($word, 1, strlen($word) -2);
				$replacement = str_repeat($character, strlen($word));
			} else {
				$replacement = str_repeat($character, strlen($word));
				$word = '*' . $word . '*';
			}
			$censorwords[] = array(
				'word' => vb_set_encoding_from_source($word, 'setting', 'value'),
				'replacement' => vb_set_encoding_from_source($replacement, 'setting', 'value'),
			);
		}
	}
	if (!empty($censorwords)) {
		$db = get_db_object();
		$db->sql_query($convert->truncate_statement . WORDS_TABLE);
		$db->sql_multi_insert(WORDS_TABLE, $censorwords);
	}
}

/**
 * Import new smilies to phpBB
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 * @global type $phpbb_root_path
 * @global type $config
 */
function vb_import_smilies()
{
	global $convert, $phpbb_root_path, $config;

	$src_db = get_src_db_object();
	$src_smilies = array();
	$existing_smilies = get_smilies_array();

	$sql = "SELECT title, smilietext, smiliepath FROM {$convert->src_table_prefix}smilie ORDER BY displayorder";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		if (!in_array($row['smilietext'], $existing_smilies)) {
			$row['title'] = vb_set_encoding_from_source($row['title'], 'smilie', 'title');
			$src_smilies[] = $row;
		}
	}
	$src_db->sql_freeresult($result);

	$db = get_db_object();
	$sql = "SELECT max(smiley_order) AS max_smiley_order FROM " . SMILIES_TABLE;
	$result = $db->sql_query($sql);
	if ($row = $db->sql_fetchrow($result)) {
		$smiley_order = $row['max_smiley_order'];
	}
	$db->sql_freeresult($result);

	$dst_smilies = array();
	foreach ($src_smilies as $smilie)
	{
		$filename_src = $convert->options['forum_path'] . '/' . $smilie['smiliepath'];
		if (is_file($filename_src)) {
			$imagesize = getimagesize($filename_src);
			if (!empty($imagesize)) {
				$width = $imagesize[0];
				$height = $imagesize[1];

				$folder_dest = $phpbb_root_path . '/' . $config['smilies_path'];
				if (is_dir($folder_dest))
				{
					$filename_dest = 'imported_' . basename($filename_src);
					if (file_exists($filename_src)) {
						@copy($filename_src, $folder_dest . '/' . $filename_dest);
						$smiley_order++;
						$new_smiley = array(
							'code' => $smilie['smilietext'],
							'emotion' => $smilie['title'],
							'smiley_url' => $filename_dest,
							'smiley_width' => $width,
							'smiley_height' => $height,
							'smiley_order' => $smiley_order,
							'display_on_posting' => 1,
						);
						$dst_smilies[] = $new_smiley;
						vb_conversion_log("vb_import_smilies(): smiley '{$smilie['smilietext']}' imported.");
					} else {
						vb_conversion_log("vb_import_smilies(): WARNING: Can't find the picture file for the smiley '{$smilie['smilietext']}' ({$filename_src}).");
					}
				}
			}
		}
	}
	if (!empty($dst_smilies)) {
		$db->sql_multi_insert(SMILIES_TABLE, $dst_smilies);
	}
}

/**
 * Moderator log
 *
 * @return int
 */
function vb_log_type()
{
	return LOG_MOD;
}

/**
 * Returns the log type (for moderator log)
 *
 * @global type $convert
 * @param type $type
 * @return string
 */
function vb_log_operation($type)
{
	global $convert;

	return $convert->convertor['log_operations'][$type]
			? $convert->convertor['log_operations'][$type]
			: '';
}

/**
 * Returns the serialized log_data field for moderator log
 *
 * @global type $convert
 * @global type $convert_row
 * @return string
 */
function vb_log_data()
{
	global $convert, $convert_row;

	$log_definition = $convert->convertor['log_definition'];
	$type = $convert_row['type'];
	$log_type = $convert->convertor['log_operations'][$type];
	$log_data = array();
	if (isset($log_definition[$log_type])) {
	foreach ($log_definition[$log_type] as $field)
	{
		if (($field == 'topic_title') || ($field == 'destination_topic_title'))
		{
			$topic_title = $convert_row['threadtitle'];
			if (empty($topic_title)) {
				$topic_title = vb_get_topic_title($convert_row['threadid']);
			}
			if (empty($topic_title)) {
				$temp = unserialize($convert_row['action']);
				if (isset($temp[0])) {
					$topic_title = $temp[0];
				}
			}
			$log_data[] = $topic_title;
		}
		else if ($field == 'post_title')
		{
			$post_title = vb_get_post_title($convert_row['postid']);
			if (empty($post_title)) {
				$post_title = vb_get_topic_title($convert_row['threadid']);
			}
			$log_data[] = $post_title;
		}
		else if ($field == 'user_name')
		{
			$user_name = '';
			$temp = unserialize($convert_row['action']);
			if (isset($temp[1])) {
				$user_name = $temp[1];
			}
			if (empty($user_name) && !empty($convert_row['postid'])) {
				$user_name = vb_get_user_name_from_post_id($convert_row['postid']);
			}
			if (empty($user_name) && !empty($convert_row['threadid'])) {
				$user_name = vb_get_user_name_from_topic_id($convert_row['threadid']);
			}
			$log_data[] = $user_name;
		}
		else if ($field == 'reason')
		{
			$log_data[] = '';
		}
		else if ($field == 'source_forum_title')
		{
			$log_data[] = vb_get_forum_title($convert_row['forumid']);
		}
		else if ($field == 'destination_forum_title')
		{
			$log_data[] = $convert_row['action'];
		}
		else if ($field == 'source_forum_id')
		{
			$log_data[] = $convert_row['forumid'];
		}
		else if ($field == 'destination_forum_id')
		{
			$log_data[] = 0;
		}
		else
		{
			vb_conversion_log("vb_log_data(): Unknown field='{$field}'");
		}
	}
	} else {
		vb_conversion_log("vb_log_data(): Unknown type='{$log_type}' ({$type})");
	}
	return serialize($log_data);
}

/**
 * Returns the title of a topic
 *
 * @global type $db
 * @staticvar array $topics
 * @param type $topic_id
 * @return string
 */
function vb_get_topic_title($topic_id)
{
	global $db;
	static $topics = array();

	if (empty($topics)) {
		$sql = "SELECT topic_id,topic_title FROM " . TOPICS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$topics[$row['topic_id']] = $row['topic_title'];
		}
		$db->sql_freeresult($result);
		vb_conversion_log("vb_get_topic_title(): " . count($topics) . " topics loaded.");
	}
	return !empty($topics[$topic_id]) ? $topics[$topic_id] : '';
}

/**
 * Returns the title of a post
 *
 * @global type $db
 * @param type $post_id
 * @return string
 */
function vb_get_post_title($post_id)
{
	global $db;

	$post_subject = '';
	$sql = "SELECT post_subject FROM " . POSTS_TABLE . " WHERE post_id=" . intval($post_id);
	$result = $db->sql_query($sql);
	$post_subject = $db->sql_fetchfield('post_subject');
	$db->sql_freeresult($result);

	return $post_subject;
}

/**
 * Returns a user name from its ID
 *
 * @global type $db
 * @staticvar array $users
 * @param type $user_id
 * @return string
 */
function vb_get_user_name($user_id)
{
	global $db;
	static $users = array();

	if (empty($users)) {
		$sql = "SELECT user_id,username FROM " . USERS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$users[$row['user_id']] = $row['username'];
		}
		$db->sql_freeresult($result);
		vb_conversion_log("vb_get_user_name(): " . count($users) . " users loaded.");
	}
	return !empty($users[$user_id]) ? $users[$user_id] : '';
}

/**
 * Returns the title of the forum
 *
 * @global type $db
 * @staticvar array $forums
 * @param type $forum_id
 * @return string
 */
function vb_get_forum_title($forum_id)
{
	global $db;
	static $forums = array();

	if (empty($forums)) {
		$sql = "SELECT forum_id,forum_name FROM " . FORUMS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$forums[$row['forum_id']] = $row['forum_name'];
		}
		$db->sql_freeresult($result);
		vb_conversion_log("vb_get_forum_title(): " . count($forums) . " forums loaded.");
	}
	return !empty($forums[$forum_id]) ? $forums[$forum_id] : '';
}

/**
 * Returns the poster user name
 *
 * @global type $db
 * @param type $post_id
 * @return string
 */
function vb_get_user_name_from_post_id($post_id)
{
	global $db;

	$user_name = '';
	$sql = "SELECT post_username FROM " . POSTS_TABLE . " WHERE post_id=" . intval($post_id);
	$result = $db->sql_query($sql);
	$user_name = $db->sql_fetchfield('post_username');
	$db->sql_freeresult($result);

	return $user_name;
}

/**
 * Returns the poster user name of the first post of a topic
 *
 * @global type $db
 * @staticvar array $topics
 * @param type $topic_id
 * @return string
 */
function vb_get_user_name_from_topic_id($topic_id)
{
	global $db;
	static $topics = array();

	if (empty($topics)) {
		$sql = "SELECT topic_id,topic_first_poster_name FROM " . TOPICS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$topics[$row['topic_id']] = $row['topic_first_poster_name'];
		}
		$db->sql_freeresult($result);
		vb_conversion_log("vb_get_user_name_from_topic_id(): " . count($topics) . " topics loaded.");
	}
	return !empty($topics[$topic_id]) ? $topics[$topic_id] : '';
}

/**
 * Convert infractions into phpBB warnings
 *
 * @global type $src_db
 * @global type $db
 * @global type $convert
 */
function vb_convert_infractions()
{
	global $convert;

	if (vb_version() >= 370)
	{
		$src_db = get_src_db_object();
		$infractions = array();
		$sql = "SELECT infractionid,infractionlevelid,i.postid,i.userid,whoadded,points,i.dateline,note,action,actiondateline,actionuserid,actionreason,expires,i.threadid,customreason,t.forumid"
				. " FROM {$convert->src_table_prefix}infraction i"
				. " LEFT JOIN {$convert->src_table_prefix}thread t ON i.threadid=t.threadid";
		$result = $src_db->sql_query($sql);
		if ($row = $src_db->sql_fetchrow($result)) {
			$infractions[] = $row;
		}
		$src_db->sql_freeresult($result);

		$db = get_db_object();
		$db->sql_query($convert->truncate_statement . WARNINGS_TABLE);

		$sql = "SELECT MAX(log_id) AS max_log_id FROM " . LOG_TABLE;
		$result = $db->sql_query($sql);
		$max_log_id = (int) $db->sql_fetchfield('max_log_id');
		$db->sql_freeresult($result);

		$logs = array();
		$warnings = array();
		$user_warnings = array();
		foreach ($infractions as $infraction)
		{
			$max_log_id++;
			$log1 = array(
				'log_id' => $max_log_id,
				'log_type' => LOG_ADMIN,
				'user_id' => vb_user_id($infraction['whoadded']),
				'forum_id' => 0,
				'topic_id' => 0,
				'reportee_id' => 0,
				'log_ip' => '',
				'log_time' => $infraction['dateline'],
				'log_operation' => 'LOG_USER_WARNING',
				'log_data' => serialize(
								array(
									vb_get_user_name($infraction['userid']),
								)
							),
			);
			$logs[] = $log1;

			$max_log_id++;
			$log2 = array(
				'log_id' => $max_log_id,
				'log_type' => LOG_USERS,
				'user_id' => vb_user_id($infraction['whoadded']),
				'forum_id' => 0,
				'topic_id' => 0,
				'reportee_id' => vb_user_id($infraction['userid']),
				'log_ip' => '',
				'log_time' => $infraction['dateline'],
				'log_operation' => 'LOG_USER_WARNING_BODY',
				'log_data' => serialize(
								array(
									vb_set_encoding_from_source($infraction['customreason'], 'infraction', 'customreason'),
								)
							),
			);
			$logs[] = $log2;

			$max_log_id++;
			$log3 = array(
				'log_id' => $max_log_id,
				'log_type' => LOG_MOD,
				'user_id' => vb_user_id($infraction['whoadded']),
				'forum_id' => $infraction['forumid'],
				'topic_id' => $infraction['threadid'],
				'reportee_id' => 0,
				'log_ip' => '',
				'log_time' => $infraction['dateline'],
				'log_operation' => 'LOG_USER_WARNING',
				'log_data' => serialize(
						array(
							vb_get_user_name($infraction['userid']),
						)
				),
			);
			$logs[] = $log3;

			$warning = array(
				'warning_id' => $infraction['infractionid'],
				'user_id' => vb_user_id($infraction['userid']),
				'post_id' => $infraction['postid'],
				'log_id' => $max_log_id -1,
				'warning_time' => $infraction['dateline'],
			);
			$warnings[] = $warning;

			$user_id = vb_user_id($infraction['userid']);
			if (!isset($user_warnings[$user_id]))
			{
				$user_warnings[$user_id] = array(
					'count' => 1,
					'last' => $infraction['dateline']
				);
			}
			else
			{
				$user_warnings[$user_id]['count']++;
				if ($user_last_warnings[$user_id]['last'] < $infraction['dateline'])
				{
					$user_last_warnings[$user_id]['last'] = $infraction['dateline'];
				}
			}

		}
		if (!empty($warnings) && !empty($logs)) {
			$db->sql_multi_insert(LOG_TABLE, $logs);
			$db->sql_multi_insert(WARNINGS_TABLE, $warnings);
		}
		if (!empty($user_warnings))
		{
			foreach ($user_warnings as $user_id => $user_warning)
			{
				$sql = "UPDATE " . USERS_TABLE . " SET user_warnings={$user_warning['count']}, user_last_warning={$user_warning['last']} WHERE user_id={$user_id}";
				$db->sql_query($sql);
			}
		}
	}
}

function vb_is_convert_albums()
{
	return (defined('CONVERT_ALBUMS') && (CONVERT_ALBUMS == 1) && (vb_version() >= 370));
}

/**
 * Creates all the tables needed by phpBBGallery extension
 *
 * @global type $phpbb_container
 * @global type $convert
 * @global type $table_prefix
 */
function vb_create_gallery_tables()
{
	global $phpbb_container, $convert, $phpbb_config_php_file;
	$table_prefix = $phpbb_config_php_file->get('table_prefix');

	if (vb_is_convert_albums())
	{
		$db_tools = $phpbb_container->get('dbal.tools');

		foreach ($convert->convertor['gallery_extension'] as $table_name => $table_data) {
			$table_name = $table_prefix . $table_name;
			if (!$db_tools->sql_table_exists($table_name)) {
				$db_tools->sql_create_table($table_name, $table_data);
				vb_conversion_log("vb_create_gallery_tables(): '{$table_name}' created.");
			} else {
				vb_conversion_log("vb_create_gallery_tables(): WARNING table '{$table_name}' already exists.");
			}
		}
	}
}

/**
 * Creates all the folders needed for phpBBGallery
 *
 * @global type $phpbb_root_path
 */
function vb_create_gallery_file_system()
{
	global $phpbb_root_path;

	if (vb_is_convert_albums())
	{
		$phpbbgallery_core_file = $phpbb_root_path . 'files/phpbbgallery/core';
		$phpbbgallery_core_file_medium = $phpbb_root_path . 'files/phpbbgallery/core/medium';
		$phpbbgallery_core_file_mini = $phpbb_root_path . 'files/phpbbgallery/core/mini';
		$phpbbgallery_core_file_source = $phpbb_root_path . 'files/phpbbgallery/core/source';

		if (is_writable($phpbb_root_path . 'files'))
		{
			@mkdir($phpbbgallery_core_file, 0755, true);
			@mkdir($phpbbgallery_core_file_medium, 0755, true);
			@mkdir($phpbbgallery_core_file_mini, 0755, true);
			@mkdir($phpbbgallery_core_file_source, 0755, true);
		}

		if (!is_dir($phpbbgallery_core_file) || !is_writable($phpbbgallery_core_file)) {
			$convert->p_master->error("Album gallery path is not writeable: {$phpbbgallery_core_file}", __LINE__, __FILE__);
			exit();
		}
		vb_conversion_log("vb_create_gallery_file_system(): phpbbgallery folder is '{$phpbbgallery_core_file}'");

		if (!is_dir($phpbbgallery_core_file_medium) || !is_writable($phpbbgallery_core_file_medium)) {
			$convert->p_master->error("Album gallery path is not writeable: {$phpbbgallery_core_file_medium}", __LINE__, __FILE__);
			exit();
		}
		if (!is_dir($phpbbgallery_core_file_mini) || !is_writable($phpbbgallery_core_file_mini)) {
			$convert->p_master->error("Album gallery path is not writeable: {$phpbbgallery_core_file_mini}", __LINE__, __FILE__);
			exit();
		}
		if (!is_dir($phpbbgallery_core_file_source) || !is_writable($phpbbgallery_core_file_source)) {
			$convert->p_master->error("Album gallery path is not writeable: {$phpbbgallery_core_file_source}", __LINE__, __FILE__);
			exit();
		}
	}
}


function vb_album_auth_access($state)
{
	$auth_access = 0;
	if ($state == 'private')
	{
		$auth_access = 3;
	}
	return $auth_access;
}

function vb_image_name($caption)
{
	if (strlen($caption) > 200)
	{
		$caption = 'Unnamed';
	}
	return vb_set_encoding_from_source($caption, 'albumpicture');
}

function vb_save_image($filedata)
{
	global $phpbb_root_path, $convert_row;

	$phpbbgallery_core_file_source = $phpbb_root_path . 'files/phpbbgallery/core/source';
	do
	{
		$filename = md5(unique_id()) . '.' . $convert_row['extension'];
	}
	while (@file_exists($phpbbgallery_core_file_source . '/' . $filename));

	file_put_contents($phpbbgallery_core_file_source . '/' . $filename, $filedata);

	return $filename;
}

function vb_fix_albums()
{
	global $db, $phpbb_config_php_file;

	$table_prefix = $phpbb_config_php_file->get('table_prefix');

	if (vb_is_convert_albums())
	{
		$album_id = 0;
		$existing_albums = array();
		$users = array();
		$sql = "SELECT * FROM {$table_prefix}gallery_albums";
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$existing_albums[] = $row;
			$users[$row['album_user_id']] = -1;
			if ($row['album_id'] > $album_id)
			{
				$album_id = $row['album_id'];
			}
		}
		$db->sql_freeresult($result);
		vb_conversion_log("vb_fix_albums(): " . count($existing_albums) . " album(s) loaded.");

		$album_last_image = array();
		$sql = "SELECT image_album_id,max(image_time),image_name,image_user_id,image_username FROM {$table_prefix}gallery_images GROUP BY image_album_id";
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$album_last_image[$row['image_album_id']] = $row;
		}
		$db->sql_freeresult($result);

		$user_root_albums = array();
		// We need to create a root album for each user
		foreach ($users as $user_id => $nothing)
		{
			$album_id++;
			$user_root_albums[$user_id] = array(
				'album_id' => $album_id,
				'parent_id' => 0,
				'left_id' => 0,
				'right_id' => 0,
				'album_parents' => '',
				'album_type' => 1,
				'album_status' => 0,
				'album_contest' => 0,
				'album_name' => vb_get_user_name($user_id),
				'album_desc' => vb_get_user_name($user_id) . "'s album",
				'album_desc_options' => 7,
				'album_desc_uid' => '',
				'album_desc_bitfield' => '',
				'album_user_id' => $user_id,
				'album_images' => 0,
				'album_images_real' => 0,
				'album_last_image_id' => 0,
				'album_image' => '',
				'album_last_image_time' => 0,
				'album_last_image_name' => '',
				'album_last_username' => '',
				'album_last_user_colour' => '',
				'album_last_user_id' => 0,
				'album_watermark' => 1,
				'album_sort_key' => '',
				'album_sort_dir' => '',
				'display_in_rrc' => 0,
				'display_on_index' => 0,
				'display_subalbum_list' => 1,
				'album_feed' => 1,
				'album_auth_access' => 0,
			);
		}

		// Build the whole list of albums starting from the existing ones
		$albums = array();
		foreach ($existing_albums as $album)
		{
			// Now we know the parent, we just created it
			$album['parent_id'] = $user_root_albums[$album['album_user_id']]['album_id'];
			$albums[$album['album_user_id']][$album['album_id']] = $album;
		}
		// Now add all the root albums (the user default albums)
		foreach ($user_root_albums as $album)
		{
			$albums[$album['album_user_id']][$album['album_id']] = $album;
		}

		foreach ($albums as $user_id => $user_albums)
		{
			vb_fix_user_albums($user_albums, $album_last_image);
		}
	}
}

function vb_fix_user_albums($user_albums, &$album_last_image)
{
	global $db, $phpbb_config_php_file;

	$table_prefix = $phpbb_config_php_file->get('table_prefix');

	//var_dump($user_albums); echo '<br />';
	$hierarchy = array();

	// Build the hierarchy from the root
	vb_build_hierarchy($user_albums, $hierarchy, 0, 'album_id');

	$left_id = 0;
	vb_build_left_right_id($user_albums, $hierarchy, $left_id, 'album_id');

	$new_albums = array();
	// The list is built, now extract the ones to update and the ones to create
	foreach ($user_albums as $album_id => $album)
	{
		if ($album['parent_id'] == 0)
		{
			$new_albums[] = $album;
		}
		elseif ($album['parent_id'] > 0)
		{
			$parent_name = $user_albums[$album['parent_id']]['album_name'];
			$album_parents = array(
				$album['parent_id'] => array(
					$parent_name,
					1,
				),
			);
			$update = array(
				'parent_id' => $album['parent_id'],
				'left_id' => $album['left_id'],
				'right_id' => $album['right_id'],
				'album_parents' => serialize($album_parents),
				'album_last_image_name' => isset($album_last_image[$album_id]['image_name']) ? $album_last_image[$album_id]['image_name'] : '',
				'album_last_username' => isset($album_last_image[$album_id]['image_username']) ? $album_last_image[$album_id]['image_username'] : '',
				'album_last_user_id' => isset($album_last_image[$album_id]['image_user_id']) ? $album_last_image[$album_id]['image_user_id'] : 0,
			);
			$sql = 'UPDATE ' . $table_prefix . 'gallery_albums SET ' . $db->sql_build_array('UPDATE', $update) . " WHERE album_id = $album_id";
			$db->sql_query($sql);
		}
	}
	if (!empty($new_albums))
	{
		$db->sql_multi_insert($table_prefix . 'gallery_albums', $new_albums);
	}
}

function vb_fix_image_comments()
{
	global $db, $phpbb_config_php_file;

	if (vb_is_convert_albums())
	{
		$table_prefix = $phpbb_config_php_file->get('table_prefix');

		$comments = array();
		$sql = "SELECT comment_image_id, comment_id, max(comment_time) AS max, count(comment_id) AS count FROM {$table_prefix}gallery_comments GROUP BY comment_image_id";
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$comments[] = $row;
		}
		$db->sql_freeresult($result);
		vb_conversion_log("vb_fix_image_comments(): " . count($comments) . " last comment(s) per picture loaded.");

		foreach ($comments as $comment)
		{
			$sql = "UPDATE {$table_prefix}gallery_images SET image_comments={$comment['count']}, image_last_comment={$comment['comment_id']} WHERE image_id={$comment['comment_image_id']}";
			$db->sql_query($sql);
		}
	}
}

/**
 * Returns vBulletin version as a number
 *
 * @staticvar type $vb_version
 * @return int
 */
function vb_version()
{
	static $vb_version = null;

	if (is_null($vb_version))
	{
		$datastore = ConversionDataStore::getInstance();
		$vb_version = $datastore->getData('vb_version');
		if (empty($vb_version))
		{
			$version_string = get_config_value('templateversion');
			$version_string = str_replace('.', '', $version_string);
			$vb_version = intval($version_string);
			$datastore->setData('vb_version', $vb_version);
			vb_conversion_log("vb_version(): vBulletin version {$vb_version} detected.");
		}
		unset($datastore);
	}
	return $vb_version;
}