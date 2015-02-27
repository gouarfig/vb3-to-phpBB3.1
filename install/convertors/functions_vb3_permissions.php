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


/**
 * Loads the administrators permissions from VB
 *
 * @global type $src_db
 * @return array
 */
function &vb_get_administrators_permissions()
{
	global $src_db, $convert;

	$administrators = array();
	$sql = "SELECT userid,adminpermissions FROM {$convert->src_table_prefix}administrator ORDER BY userid ";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$administrators[$row['userid']] = $row;
	}
	return $administrators;
}

/**
 * Loads the moderators permissions from VB
 *
 * @global type $src_db
 * @return array
 */
function &vb_get_moderators_permissions()
{
	global $src_db, $convert;
	$moderator_permissions = array();

	if (vb_version() >= 370)
	{
		$sql = "SELECT userid,forumid,permissions AS moderatorpermissions,permissions2 AS moderatorpermissions2"
				. " FROM {$convert->src_table_prefix}moderator"
				. " ORDER BY userid,forumid";
	}
	else
	{
		$sql = "SELECT userid,forumid,permissions AS moderatorpermissions"
				. " FROM {$convert->src_table_prefix}moderator"
				. " ORDER BY userid,forumid";
	}
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$row['forumid'] = intval($row['forumid']);
		if ($row['forumid'] == -1) {
			$row['forumid'] = 0;
		}
		$moderator_permissions[$row['forumid']][$row['userid']] = $row;
	}
	return $moderator_permissions;
}

function vb_get_roles()
{
	global $db;
	static $roles = array();

	if (empty($roles)) {
		$sql = "SELECT role_name, auth_option, auth_setting"
			. " FROM  " . ACL_ROLES_TABLE . " roles"
			. " LEFT JOIN " . ACL_ROLES_DATA_TABLE . " roles_data ON roles.role_id = roles_data.role_id"
			. " LEFT JOIN " . ACL_OPTIONS_TABLE . " options ON roles_data.auth_option_id =  options.auth_option_id"
			. " ORDER BY role_name, auth_option";
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$role_name = $row['role_name'];
			if ($row['auth_setting'] == 0) {
				$auth_option = '-' . $row['auth_option'];
			} elseif ($row['auth_setting'] == 1) {
				$auth_option = $row['auth_option'];
			}
			$roles[$role_name][] = $auth_option;
		}
		$db->sql_freeresult($result);
	}
	return $roles;
}

function &vb_get_forums_defaults()
{
	global $src_db, $convert;

	$forums_defaults = array();
	$sql = "SELECT forumid,title AS forumtitle,options FROM {$convert->src_table_prefix}forum ORDER BY forumid";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$forums_defaults[$row['forumid']] = $row;
		unset($row);
	}
	$src_db->sql_freeresult($result);
	vb_conversion_log("vb_get_forums_defaults(): " . count($forums_defaults) . " forums default options loaded.");

	return $forums_defaults;
}

function &vb_get_usergroups_defaults($ignore_groups = array())
{
	global $src_db, $convert;

	$usergroups_defaults = array();
	$sql = "SELECT usergroupid,title AS grouptitle,forumpermissions,adminpermissions,wolpermissions,genericpermissions,pmquota"
			. " FROM {$convert->src_table_prefix}usergroup";
	if (!empty($ignore_groups)) {
		$sql .= " WHERE title NOT IN ('" . implode("','", $ignore_groups). "')";
	}
	$sql .=  " ORDER BY usergroupid";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$usergroups_defaults[$row['usergroupid']] = $row;
		unset($row);
	}
	$src_db->sql_freeresult($result);
	vb_conversion_log("vb_get_usergroups_defaults(): " . count($usergroups_defaults) . " user groups default options loaded.");

	return $usergroups_defaults;
}

function &vb_get_forums_permissions($ignore_groups = array())
{
	global $src_db, $convert;

	$forums_per_usergroups_permissions = Array();
	$sql = "SELECT fp.forumid, fp.usergroupid, fp.forumpermissions,f.title AS forumtitle"
			. " FROM {$convert->src_table_prefix}forumpermission fp"
			. " INNER JOIN {$convert->src_table_prefix}forum f ON fp.forumid = f.forumid"
			. " INNER JOIN {$convert->src_table_prefix}usergroup ug ON fp.usergroupid = ug.usergroupid";
	if (!empty($ignore_groups)) {
		$sql .= " WHERE ug.title NOT IN ('" . implode("','", $ignore_groups). "')";
	}
	$sql .= " ORDER BY fp.forumid,fp.usergroupid";
	$result = $src_db->sql_query($sql);
	while ($row = $src_db->sql_fetchrow($result)) {
		$row['forumid'] = intval($row['forumid']);
		if ($row['forumid'] == -1) {
			$row['forumid'] = 0;
		}
		$forums_per_usergroups_permissions[$row['forumid']][$row['usergroupid']] = $row;
	}
	$src_db->sql_freeresult($result);

	return $forums_per_usergroups_permissions;
}

/**
 * Returns auth_option ID from its name
 *
 * @global type $db
 * @staticvar array $auth_options
 * @param string $auth_option_name
 * @return int
 */
function vb_get_auth_option_id($auth_option_name)
{
	global $db;
	static $auth_options = array();

	if (empty($auth_options)) {
		// Get the IDs of the human names of phpBB rights
		$sql = "SELECT auth_option_id, auth_option FROM " . ACL_OPTIONS_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$auth_options[$row['auth_option']] = intval($row['auth_option_id']);
		}
		$db->sql_freeresult($result);
		vb_conversion_log('vb_get_auth_option_id(): ' . count($auth_options) . ' auth_options(s) loaded');
	}
	if (isset($auth_options[$auth_option_name])) {
		return $auth_options[$auth_option_name];
	} else {
		vb_conversion_log("vb_get_auth_option_id(): WARNING auth_option '{$auth_option_name}' not found!");
		return NULL;
	}
}

/**
 * Returns a role ID from its name
 *
 * @global type $db
 * @staticvar array $roles
 * @param string $role_name
 * @return int
 */
function vb_get_role_id($role_name)
{
	global $db;
	static $roles = array();

	// Try to reload if the role wasn't found
	if (empty($roles) || !isset($roles[$role_name])) {
		// Get the IDs of the human names of phpBB roles
		$sql = "SELECT role_id, role_name FROM " . ACL_ROLES_TABLE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result)) {
			$roles[$row['role_name']] = intval($row['role_id']);
		}
		$db->sql_freeresult($result);
		vb_conversion_log('vb_get_role_id(): ' . count($roles) . ' roles(s) loaded');
	}
	return isset($roles[$role_name]) ? $roles[$role_name] : 0;
}



function vb_convert_forum_permissions_for_multi_insert(&$dst_permissions, &$permissions)
{
	// Convert permissions into a flat list
	foreach ($dst_permissions as $forum_id => $vb_permissions) {
		if (!empty($vb_permissions)) {
			foreach ($vb_permissions as $group_id => $vb_permission) {
				$permission = $vb_permission->getPermissions();
				if (!empty($permission)) {
					$parameters = array(
						'group_id' => vb_convert_group_id($group_id),
						'forum_id' => $forum_id,
					);
					vb_convert_permissions_for_multi_insert($permission, $parameters, $permissions);
					unset($permission);
				} else {
					vb_conversion_log("vb_convert_forum_permissions_for_multi_insert(): WARNING empty permissions for group ID {$group_id} in forum ID {$forum_id}!");
				}
			}
		} else {
			vb_conversion_log("vb_convert_forum_permissions_for_multi_insert(): WARNING empty user group permissions for forum ID {$forum_id}!");
		}
	}
	vb_conversion_log('vb_convert_forum_permissions_for_multi_insert(): ' . count($permissions) . ' permission(s) found');
}

function vb_convert_moderator_permissions_for_multi_insert(&$dst_permissions, &$permissions)
{
	// Convert permissions into a flat list
	foreach ($dst_permissions as $forum_id => $vb_permissions) {
		if (!empty($vb_permissions)) {
			foreach ($vb_permissions as $user_id => $vb_permission) {
				$permission = $vb_permission->getPermissions();
				if (!empty($permission)) {
					$parameters = array(
						'user_id' => vb_user_id($user_id),
						'forum_id' => ($forum_id >0) ? $forum_id : 0,
					);
					vb_convert_permissions_for_multi_insert($permission, $parameters, $permissions);
					unset($permission);
				} else {
					vb_conversion_log("vb_convert_moderator_permissions_for_multi_insert(): WARNING empty permissions for user ID {$user_id} in forum ID {$forum_id}!");
				}
			}
		} else {
			vb_conversion_log("vb_convert_moderator_permissions_for_multi_insert(): WARNING empty moderator permissions for forum ID {$forum_id}!");
		}
	}
	vb_conversion_log('vb_convert_moderator_permissions_for_multi_insert(): ' . count($permissions) . ' permission(s) found');
}


/**
 * Creates the default minimum permissions for phpBB to work
 *
 * @param type $permissions
 */
function vb_set_default_minimum_permissions(&$permissions)
{
	// Set a few default permissions
	$permissions[] = Array(
		'group_id' => get_group_id('administrators'),
		'forum_id' => 0,
		'auth_option_id' => 0,
		'auth_role_id' => vb_get_role_id('ROLE_USER_FULL'),
		'auth_setting' => 0,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('administrators'),
		'forum_id' => 0,
		'auth_option_id' => 0,
		'auth_role_id' => vb_get_role_id('ROLE_ADMIN_STANDARD'),
		'auth_setting' => 0,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('global_moderators'),
		'forum_id' => 0,
		'auth_option_id' => 0,
		'auth_role_id' => vb_get_role_id('ROLE_USER_FULL'),
		'auth_setting' => 0,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('global_moderators'),
		'forum_id' => 0,
		'auth_option_id' => 0,
		'auth_role_id' => vb_get_role_id('ROLE_MOD_FULL'),
		'auth_setting' => 0,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('registered'),
		'forum_id' => 0,
		'auth_option_id' => 0,
		'auth_role_id' => vb_get_role_id('ROLE_USER_STANDARD'),
		'auth_setting' => 0,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('registered_coppa'),
		'forum_id' => 0,
		'auth_option_id' => 0,
		'auth_role_id' => vb_get_role_id('ROLE_USER_STANDARD'),
		'auth_setting' => 0,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('newly_registered'),
		'forum_id' => 0,
		'auth_option_id' => 0,
		'auth_role_id' => vb_get_role_id('ROLE_USER_NEW_MEMBER'),
		'auth_setting' => 0,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('guests'),
		'forum_id' => 0,
		'auth_option_id' => vb_get_auth_option_id('u_'),
		'auth_role_id' => 0,
		'auth_setting' => ACL_YES,
		);
	$permissions[] = Array(
		'group_id' => get_group_id('guests'),
		'forum_id' => 0,
		'auth_option_id' => vb_get_auth_option_id('u_search'),
		'auth_role_id' => 0,
		'auth_setting' => ACL_YES,
		);
}

function &vb_build_forums_per_usergroups_defaults(&$forums_defaults, &$usergroups_defaults)
{
	$forums_per_usergroups_defaults = array();
	foreach ($forums_defaults as $forum_id => $forum_row)
	{
		foreach ($usergroups_defaults as $usergroup_id => $usergroup_row)
		{
			$row = $forum_row + $usergroup_row;
			$forums_per_usergroups_defaults[$forum_id][$usergroup_id] = $row;
			unset($row);
		}
	}
	return $forums_per_usergroups_defaults;
}

function vb_build_permissions_inheritance(&$forums, &$forums_per_usergroups_permissions)
{
	do {
		$work_done = 0;
		foreach($forums as $forum_id => $forum)
		{
			if (!isset($forums_per_usergroups_permissions[$forum_id])) {
				// Searching for a parent?
				if ($forum['parent_id'] == 0) {
					// The record [0] might contain the user defaults
					if (isset($forums_per_usergroups_permissions[$forum['parent_id']])) {
						$forums_per_usergroups_permissions[$forum_id] = $forums_per_usergroups_permissions[$forum['parent_id']];
					} else {
						$forums_per_usergroups_permissions[$forum_id] = array();
					}
					$work_done++;
				} elseif (($forum['parent_id'] > 0) && isset($forums_per_usergroups_permissions[$forum['parent_id']])) {
					$forums_per_usergroups_permissions[$forum_id] = $forums_per_usergroups_permissions[$forum['parent_id']];
					$work_done++;
				}
			}
		}
	} while ($work_done > 0);
}

function vb_merge_permissions_with_defaults(&$forums_per_usergroups_defaults, &$forums_per_usergroups_permissions, &$roles, &$dst_permissions)
{
	foreach ($forums_per_usergroups_defaults as $forum_id => $usergroups_defaults_rows)
	{
		foreach ($usergroups_defaults_rows as $usergroup_id => $default)
		{
			$permission = isset($forums_per_usergroups_permissions[$forum_id][$usergroup_id])
					? $forums_per_usergroups_permissions[$forum_id][$usergroup_id]
					: null;
			$vbPermission = new vbPermission($default, $permission, VB_CONVERSION_MERGE_REPLACE);
			$vbPermission->setRoles($roles);
			$vbPermission->transformAllPermissions();
			$dst_permissions[$forum_id][$usergroup_id] = $vbPermission;
		}
	}
}

/**
 * Installs all the forum permissions
 *
 * @global type $db
 * @global type $src_db
 * @global type $convert
 */
function vb_convert_forum_permissions()
{
	global $db, $src_db, $convert;

	$db->sql_query($convert->truncate_statement . ACL_GROUPS_TABLE);

	$dst_permissions = Array();
	$datastore = new ConversionDataStore();
	$forums = $datastore->getData('forums');
	$roles = vb_get_roles();

	// 1- Get forum defaults
	$forums_defaults = vb_get_forums_defaults();

	// 2- Get usergroup defaults
	$usergroups_defaults = vb_get_usergroups_defaults($convert->convertor['ignore_groups']);

	// 3- Build the defaults: forums per usergroups
	$forums_per_usergroups_defaults = vb_build_forums_per_usergroups_defaults($forums_defaults, $usergroups_defaults);

	// 4- Build the specific permissions: forums / usergroups
	$forums_per_usergroups_permissions = vb_get_forums_permissions($convert->convertor['ignore_groups']);

	// 5- Build the permissions inheritance
	vb_build_permissions_inheritance($forums, $forums_per_usergroups_permissions);

	// 6- Merge both information (defaults + permissions)
	$dst_permissions = array();
	vb_merge_permissions_with_defaults($forums_per_usergroups_defaults, $forums_per_usergroups_permissions, $roles, $dst_permissions);

	// 7- Add some required ones
	$permissions = Array();
	vb_set_default_minimum_permissions($permissions);

	// 8- then convert into phpBB format
	vb_convert_forum_permissions_for_multi_insert($dst_permissions, $permissions);

	// 9- Remove all duplicate values in a multi-dimensional array (actually shouldn't be needed any more, but meh)
	$permissions = array_map("unserialize", array_unique(array_map("serialize", $permissions)));

	// And we're done!
	$db->sql_multi_insert(ACL_GROUPS_TABLE, $permissions);
}

function vb_convert_moderator_permissions()
{
	global $db, $src_db, $convert;

	$dst_permissions = Array();
	$datastore = new ConversionDataStore();
	$forums = $datastore->getData('forums');
	$roles = vb_get_roles();

	$administrators_permissions = vb_get_administrators_permissions();
	vb_conversion_log('vb_convert_moderator_permissions(): ' . count($administrators_permissions) . " administrator permissions loaded.");

	$moderators_per_forum_permissions = vb_get_moderators_permissions();
	vb_conversion_log('vb_convert_moderator_permissions(): ' . count($moderators_per_forum_permissions) . " moderator permissions loaded.");

	// 1- Add all moderator permissions to each forum
	$mixed_admin_permissions = array();
	foreach ($forums as $forum_id => $forum_details)
	{
		$permissions = array();
		// Add all moderators
		if (isset($moderators_per_forum_permissions[$forum_id])) {
			$permissions = $moderators_per_forum_permissions[$forum_id];
		}
		if (!empty($permissions)) {
			$mixed_admin_permissions[$forum_id] = $permissions;
		}
	}

	// 2- Build the inheritance
	vb_build_permissions_inheritance($forums, $mixed_admin_permissions);


	// 3- Add the administrator permissions
	foreach ($forums as $forum_id => $forum_details)
	{
		// Add each administrators in each forum
		foreach ($administrators_permissions as $user_id => $administrator_row)
		{
			if (isset($mixed_admin_permissions[$forum_id][$user_id])) {
				$mixed_admin_permissions[$forum_id][$user_id]['adminpermissions'] = $administrator_row['adminpermissions'];
			} else {
				$mixed_admin_permissions[$forum_id][$user_id] = $administrator_row;
			}
		}
	}
	vb_conversion_log('vb_convert_moderator_permissions(): ' . count($mixed_admin_permissions) . " administrator/moderator permissions generated.");

	// 4- Convert the information into phpBB format
	$dst_permissions = array();
	foreach ($mixed_admin_permissions as $forum_id => $users)
	{
		foreach ($users as $user_id => $row)
		{
			$vbPermission = new vbPermission($row);
			$vbPermission->setRoles($roles);
			$vbPermission->transformAllPermissions();
			$dst_permissions[$forum_id][$user_id] = $vbPermission;
		}
	}

	// 5- Prepare the data for SQL insert
	$permissions = Array();
	vb_convert_moderator_permissions_for_multi_insert($dst_permissions, $permissions);

	// Remove all duplicate values in a multi-dimensional array (I don't think it's still needed)
	$permissions = array_map("unserialize", array_unique(array_map("serialize", $permissions)));

	// And we're done!
	$db->sql_multi_insert(ACL_USERS_TABLE, $permissions);
}

/**
 * Fix some missing permissions
 *
 * @global type $db
 */
function vb_fix_permissions()
{
	Global $db, $src_db, $convert;

	// There are a few things to tweak:

	// Put the Anonymous user back into the GUESTS group
	$sql = 'INSERT INTO ' . USER_GROUP_TABLE . ' ' . $db->sql_build_array('INSERT', array(
		'user_id'		=> (int) 1,
		'group_id'		=> (int) get_group_id('guests'),
		'user_pending'	=> 0)
	);
	$db->sql_query($sql);
}

function vb_convert_permissions_for_multi_insert(&$permission, $parameters, &$permissions)
{
	// Roles
	$permission['roles'] = array_unique($permission['roles']);
	if (!empty($permission['roles'])) {
		foreach ($permission['roles'] as $role) {
			$new_permission = Array(
				'auth_option_id' => 0,
				'auth_role_id' => vb_get_role_id($role),
				'auth_setting' => 0,
			);
			$permissions[] = $parameters + $new_permission;
		}
	}
	// Rights
	$permission['rights_add'] = array_unique($permission['rights_add']);
	if (!empty($permission['rights_remove'])) {
		$permission['rights_remove'] = array_unique($permission['rights_remove']);
	}
	if (!empty($permission['rights_add'])) {
		foreach ($permission['rights_add'] as $rights) {
			foreach ($rights as $right) {
				if (empty($permission['rights_remove']) || !in_array($right, $permission['rights_remove'])) {
					$new_permission = Array(
						'auth_option_id' => vb_get_auth_option_id($right),
						'auth_role_id' => 0,
						'auth_setting' => ACL_YES,
					);
					$permissions[] = $parameters + $new_permission;
				}
			}
		}
	}
}


