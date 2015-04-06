<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

require_once 'vb_conversion_constants.php';

/**
 * Only rights that are granted by both are valid
 */
define('VB_CONVERSION_MERGE_EXCLUSIVE', 1);
/**
 * All rights are added up together
 */
define('VB_CONVERSION_MERGE_ADD', 2);
/**
 * The second set of rights replace the first set
 */
define('VB_CONVERSION_MERGE_REPLACE', 3);

/**
 * Manages and converts a permission
 */
class vbPermission
{
	private $record = array();
	private $userid = 0;
	private $user_name = '';
	private $usergroupid = 0;
	private $group_name = '';
	private $forumid = 0;
	private $forum_name = '';
	private $sources = array();
	private $roles = array();
	private $permissions = array(
		'roles' => array(),
		'rights_add' => array(),
		'rights_remove' => array(),
	);

	private $forum_permissions_conversion = Array(
		fp_canview => array('f_', 'f_list', 'f_subscribe'),
		fp_canviewothers => array('f_', 'f_list', 'f_subscribe'),
		fp_cansearch => array('f_', 'f_search'),
		fp_canemail => array('f_', 'f_email'),
		fp_canpostnew => array('f_', 'f_post', 'f_sigs', 'f_postcount', 'f_noapprove'),
		fp_canreplyown => array('f_', 'f_reply', 'f_bump'),
		fp_canreplyothers => array('f_', 'f_reply', 'f_bump'),
		fp_caneditpost => array('f_', 'f_edit'),
		fp_candeletepost => array('f_', 'f_delete', 'f_softdelete'),
		fp_candeletethread => array('f_', 'f_delete', 'f_softdelete'),
		fp_canopenclose => array('f_', 'f_user_lock'),
		fp_cangetattachment => array('f_', 'f_download'),
		fp_canpostattachment => array('f_', 'f_attach','f_download'),
		fp_canpostpoll => array('f_', 'f_post', 'f_poll'),
		fp_canvote => array('f_', 'f_vote', 'f_votechg'),
		//fp_canthreadrate => '!f_ignoreflood',
		//fp_followforummoderation => '!f_noapprove',
		fp_canviewthreads => array('f_', 'f_read', 'f_report', 'f_subscribe', 'f_print'),
	);

	private $forum_permissions_conversion_remove = Array(
		fp_canpostnew => array('!f_bbcode', '!f_img', '!f_smilies', '!f_icons', '!f_postcount'),
		fp_canreplyown => array('!f_bbcode', '!f_img', '!f_smilies', '!f_icons', '!f_postcount'),
		fp_canreplyothers => array('!f_bbcode', '!f_img', '!f_smilies', '!f_icons', '!f_postcount'),
	);

	private $options_conversion = Array(
		o_moderatenewpost => '!f_noapprove',
		o_moderatenewthread => '!f_noapprove',
		o_allowbbcode => 'f_bbcode',
		o_allowimages => 'f_img',
		o_allowsmilies => 'f_smilies',
		o_allowicons => 'f_icons',
		o_countposts => 'f_postcount',
	);

	private $options_conversion_remove = Array(
		o_active => array('!f_list'),
		o_allowposting => array('!f_bump', '!f_post', '!f_reply', '!f_edit', '!f_user_lock', '!f_poll', '!f_delete', '!f_softdelete'),
	);

	private $admin_permissions_conversion = Array(
		a_ismoderator => array('role=ROLE_MOD_FULL', 'role=ROLE_FORUM_FULL', 'u_pm_forward', 'u_chggrp', 'u_chgname', 'u_pm_flash', 'u_ignoreflood', 'f_ignoreflood', 'f_sticky', 'f_flash', 'f_announce'),
		a_cancontrolpanel => array('role=ROLE_ADMIN_STANDARD', 'u_pm_forward', 'u_chggrp', 'u_chgname', 'u_pm_flash', 'u_ignoreflood', 'f_ignoreflood', 'f_sticky', 'f_flash', 'f_announce'),
		a_canadminsettings => array('a_', 'a_board', 'a_phpinfo', 'a_server', 'a_jabber', 'a_words'),
		a_canadminstyles => array('a_', 'a_styles'),
		a_canadminlanguages => array('a_', 'a_language'),
		a_canadminforums => array('a_', 'a_forum', 'a_forumadd', 'a_forumdel', 'a_prune'),
		a_canadminthreads => array('a_', 'a_icons'),
		a_canadminusers => array('a_', 'a_user', 'a_userdel', 'a_authusers', 'a_ban', 'a_group', 'a_groupadd', 'a_groupdel', 'a_names', 'a_profile', 'a_ranks', 'u_chggrp'),
		a_canadminpermissions => array('a_', 'a_roles', 'a_authgroups', 'a_uauth', 'a_fauth', 'a_mauth', 'a_aauth', 'a_switchperm', 'a_viewauth'),
		a_canadminimages => array('a_', 'a_attach'),
		a_canadminbbcodes => array('a_', 'a_bbcode'),
		a_canadminmaintain => array('a_', 'a_reasons', 'a_search', 'a_email', 'a_bots', 'a_backup'),
		a_canadminplugins => array('a_', 'a_extensions', 'a_modules'),
		a_canadminmodlog => array('a_', 'a_viewlogs', 'a_clearlogs'),
	);

	private $moderator_permissions_conversion = Array(
		mp_caneditposts => array('role=ROLE_FORUM_FULL', 'm_', 'm_warn', 'm_edit', 'm_report'),
		mp_candeleteposts => array('role=ROLE_FORUM_FULL', 'm_', 'm_warn', 'm_softdelete', 'm_delete', 'm_report'),
		mp_canopenclose => array('m_', 'm_lock'),
		mp_caneditthreads => array('role=ROLE_FORUM_FULL', 'm_', 'm_warn', 'm_edit'),
		mp_canmanagethreads => array('role=ROLE_FORUM_FULL', 'm_', 'm_warn', 'm_edit', 'm_merge', 'm_move', 'm_info', 'm_split', 'f_sticky', 'f_announce'),
		mp_canannounce => array('m_', 'f_announce', 'f_sticky'),
		mp_canmoderateposts => array('role=ROLE_FORUM_FULL', 'm_', 'm_warn', 'm_approve', 'm_report', 'f_ignoreflood', 'u_ignoreflood'),
		mp_canbanusers => array('m_', 'm_warn', 'm_ban', 'm_chgposter', 'm_report'),
		mp_canunbanusers => array('m_', 'm_ban', 'm_report'),
		mp_canremoveposts => array('role=ROLE_FORUM_FULL', 'm_', 'm_warn', 'm_softdelete', 'm_delete', 'm_report'),
	);

	// There are loads of rights that doesn't look to belong there, but I had to fill in all the default rights of vBulletin
	private $generic_permissions_conversion = Array(
		gp_canviewmembers => array('u_', 'u_download', 'u_chgprofileinfo', 'u_chgemail', 'u_chgpasswd', 'u_search', 'u_viewprofile'),
		gp_caninvisible => array('u_', 'u_download', 'u_hideonline'),
		gp_canuseavatar => array('u_', 'u_download', 'u_chgavatar'),
		gp_canusesignature => array('u_', 'u_attach', 'u_download', 'u_chgcensors', 'u_sig'),
		gp_canseeprofilepic => array('u_', 'u_download', 'u_chgprofileinfo', 'u_viewprofile'),
		gp_cangiveinfraction => 'm_report',
		gp_canemailmember => array('u_', 'u_download', 'u_chgemail', 'u_chgpasswd', 'u_sendemail', 'u_masspm', 'u_masspm_group'),
	);

	private $wol_permissions_conversion = array(
		wol_canwhosonline => array('u_', 'u_viewonline'),
	);

	private $pm_default_permissions = array(
		'u_',
		'u_readpm',
		'u_pm_attach',
		'u_pm_bbcode',
		'u_pm_delete',
		'u_pm_download',
		'u_pm_edit',
		'u_pm_emailpm',
		'u_pm_img',
		'u_pm_printpm',
		'u_pm_smilies',
		'u_savedrafts',
		'u_sendim',
		'u_sendpm');

	/**
	 * Creates a new converted permission from $record.
	 * If $merge is present, it will merge both set of permissions, according to $merge_type
	 * 
	 * @param array $record
	 * @param array $merge
	 * @param int $merge_type
	 */
	function __construct($record, $merge = null, $merge_type = VB_CONVERSION_MERGE_EXCLUSIVE) {
		if (!is_null($merge)) {
			foreach ($merge as $key => $value)
			{
				if (($key == 'grouptitle') || ($key == 'forumtitle') || ($key == 'username'))
				{
					if (!isset($record[$key])) $record[$key] = $value;
				}
				elseif (($key == 'userid') || ($key == 'usergroupid') || ($key == 'forumid') || ($key == 'pmquota'))
				{
					if (!isset($record[$key])) $record[$key] = intval($value);
				}
				elseif (($key == 'forumpermissions') || ($key == 'genericpermissions') || ($key == 'wolpermissions') || ($key == 'adminpermissions') || ($key == 'moderatorpermissions') || ($key == 'moderatorpermissions2') || ($key == 'options'))
				{
					if (!isset($record[$key]))
					{
						$record[$key] = intval($value);
					}
					else
					{
						if ($merge_type == VB_CONVERSION_MERGE_EXCLUSIVE) {
							$record[$key] = $record[$key] & intval($value);
						} elseif ($merge_type == VB_CONVERSION_MERGE_ADD) {
							$record[$key] = $record[$key] | intval($value);
						} elseif ($merge_type == VB_CONVERSION_MERGE_REPLACE) {
							$record[$key] = intval($value);
						}
					}
				}
			}
		}
		// Now use the merged record
		$this->record = $record;
		foreach ($record as $key => $value)
		{
			if ($key == 'userid')
			{
				$this->userid = intval($value);
			}
			elseif ($key == 'usergroupid')
			{
				$this->usergroupid = intval($value);
			}
			elseif ($key == 'grouptitle')
			{
				$this->group_name = $value;
			}
			elseif ($key == 'forumid')
			{
				$this->forumid = intval($value);
			}
			elseif ($key == 'forumtitle')
			{
				$this->forum_name = $value;
			}
			elseif ($key == 'username')
			{
				$this->user_name = $value;
			}
			elseif (($key == 'forumpermissions') || ($key == 'genericpermissions') || ($key == 'wolpermissions') || ($key == 'adminpermissions') || ($key == 'moderatorpermissions') || ($key == 'moderatorpermissions2') || ($key == 'options') || ($key == 'pmquota'))
			{
				$this->sources[$key] = intval($value);
			}
		}
	}

	/**
	 * Adds the value to the array, only if it's not already in it
	 *
	 * @param array $array
	 * @param mixed $value
	 */
	private function addValueToArray(&$array, &$value)
	{
		if (empty($array) || !in_array($value, $array)) {
			$array[] = $value;
		}
	}

	/**
	 * Sets the role in the permission array
	 *
	 * @param string $role_name
	 */
	private function setRole($role_name)
	{
		// We only add roles (we don't remove them)
		$this->addValueToArray($this->permissions['roles'], $role_name);
	}

	/**
	 * Sets the right in the permission array
	 *
	 * @param string $right_name
	 */
	private function setRight($right_name)
	{
		if (substr($right_name, 0, 1) == '!') {
			$right_name = substr($right_name, 1);
			$this->addValueToArray($this->permissions['rights_remove'][substr($right_name, 0, 2)], $right_name);
		} else {
			$this->addValueToArray($this->permissions['rights_add'][substr($right_name, 0, 2)], $right_name);
		}
	}

	/**
	 * Sets the permission
	 *
	 * @param mixed $converted_permission
	 */
	private function setPermission($converted_permission)
	{
		if (!empty($converted_permission)) {
			if (is_array($converted_permission)) {
				// Array of rights (or roles)
				foreach ($converted_permission as $single_right) {
					if (substr($single_right, 0, 5) == 'role=') {
						$this->expandRole(substr($single_right, 5));
					} else {
						$this->setRight($single_right);
					}
				}
			} else if (is_string($converted_permission)) {
				// Single right
				$this->setRight($converted_permission);
			}
		}
	}

	/**
	 * Transforms a role into rights into the permission array
	 *
	 * @param string $role_name
	 */
	private function expandRole($role_name)
	{
		if (isset($this->roles[$role_name])) {
			foreach ($this->roles[$role_name] as $permission)
			{
				$this->setPermission($permission);
			}
		}
	}

	function getUserId()
	{
		return $this->userid;
	}

	function getGroupName()
	{
		return $this->group_name;
	}

	function getForumName()
	{
		return $this->forum_name;
	}

	function getUserName()
	{
		return $this->user_name;
	}

	/**
	 * Retreive the original record of data, used for merging
	 *
	 * @return array
	 */
	function getOriginalRecord()
	{
		return $this->record;
	}

	/**
	 * Sort out the arrays of permissions (and remove possible duplicates)
	 */
	private function cleanPermissions()
	{
		sort($this->permissions['roles']);
		$this->permissions['roles'] = array_unique($this->permissions['roles']);

		$temp = array();
		foreach($this->permissions['rights_add'] as $type => $permissions)
		{
			sort($permissions);
			$temp[$type] = array_unique($permissions);
		}
		$this->permissions['rights_add'] = $temp;

		$temp = array();
		foreach($this->permissions['rights_remove'] as $type => $permissions)
		{
			sort($permissions);
			$temp[$type] = array_unique($permissions);
		}
		$this->permissions['rights_remove'] = $temp;
	}

	/**
	 * Removes the $value from the $array
	 *
	 * @param array $array
	 * @param mixed $value
	 */
	function removeValueFromArray(&$array, $value)
	{
		if (!empty($array))
		{
			if(($key = array_search($value, $array)) !== false) {
				unset($array[$key]);
			}
		}
	}

	/**
	 * Returns the permissions cleaned up in one array [rights_add]
	 * (with the [rights_remove] removed)
	 *
	 * @return array permissions
	 */
	function preparePermissions()
	{
		$this->cleanPermissions();
		$permissions = $this->permissions;
		foreach ($permissions['rights_remove'] as $type => $rights)
		{
			if (!empty($rights))
			{
				foreach($rights as $right)
				{
					if (!empty($right) && isset($permissions['rights_add'][$type]))
					{
						$this->removeValueFromArray($permissions['rights_add'][$type], $right);
					}
				}
			}
		}
		unset($permissions['rights_remove']);

		// If the user doesn't have access to the forum, we remove his user rights
		if (array_key_exists('f_', $permissions['rights_add']) && empty($permissions['rights_add']['f_']))
		{
			unset($permissions['rights_add']['u_']);
		}

		$new_permissions = $permissions;
		// Empty arrays that doesn't have the "type" included in the list of rights
		foreach ($permissions['rights_add'] as $type => $rights)
		{
			if (empty($rights) || !in_array($type, $rights))
			{
				unset($new_permissions['rights_add'][$type]);
			}
		}

		return $new_permissions;
	}

	/**
	 * Gives the array of roles to be used to convert rights to roles inside $this->reducePermissions()
	 *
	 * @param array $roles
	 */
	function setRoles($roles)
	{
		// Sort out the list of right before
		foreach ($roles as $role_name => $rights)
		{
			sort($rights);
			$this->roles[$role_name] = $rights;
		}
	}

	/**
	 * Try to exchange right based permissions to role based permissions
	 */
	function reducePermissions()
	{
		$permissions = $this->preparePermissions();
		$new_permissions = $permissions;
		if (!empty($this->roles)) {
			// Copy the permissions
			foreach ($permissions['rights_add'] as $type => $rights)
			{
				foreach ($this->roles as $role_name => $role_rights)
				{
					if ($rights == $role_rights) {
						$new_permissions['roles'][] = $role_name;
						unset($new_permissions['rights_add'][$type]);
						break;
					}
				}
			}
		}
		return $new_permissions;
	}

	/**
	 * Returns the array of permissions
	 *
	 * @return array
	 */
	function &getRawPermissions()
	{
		$this->cleanPermissions();
		return $this->permissions;
	}

	/**
	 * Returns a clean array of permissions (with matching roles)
	 *
	 * @return array
	 */
	function getPermissions()
	{
		return $this->reducePermissions();
	}

	/**
	 * Tranforms an bitfield value ($permission) from vBulletin to an array of roles/rights
	 * The process needs an array of conversions ($permissions_conversion)
	 * The result is stored in $this->permissions
	 *
	 * @param int $permission
	 * @param array $permission_conversion
	 */
	private function transformPermissions($permission, &$permission_conversion)
	{
		foreach ($permission_conversion as $bitfield => $converted_permission) {
			if ($permission & $bitfield) {
				$this->setPermission($converted_permission);
			}
		}
	}

	/**
	 * Tranforms an bitfield value ($permission) from vBulletin to an array of roles/rights
	 * The process needs an array of conversions ($permissions_conversion)
	 * The result is stored in $this->permissions
	 * This function sets the permission when the bitfield right is NOT present.
	 * It acts as the opposite of $this->transformPermissions()
	 *
	 * @param int $permission
	 * @param array $permission_conversion
	 */
	private function transformNegativePermissions($permission, &$permission_conversion)
	{
		foreach ($permission_conversion as $bitfield => $converted_permission) {
			if (!($permission & $bitfield)) {
				$this->setPermission($converted_permission);
			}
		}
	}

	function transformModeratorPermissions()
	{
		if (isset($this->sources['moderatorpermissions'])) {
			$this->transformPermissions($this->sources['moderatorpermissions'], $this->moderator_permissions_conversion);
		}
	}

	function transformAdministratorPermissions()
	{
		if (isset($this->sources['adminpermissions'])) {
			$this->transformPermissions($this->sources['adminpermissions'], $this->admin_permissions_conversion);
		}
	}

	function transformForumPermissions()
	{
		if (isset($this->sources['forumpermissions'])) {
			$this->transformPermissions($this->sources['forumpermissions'], $this->forum_permissions_conversion);
			$this->transformNegativePermissions($this->sources['forumpermissions'], $this->forum_permissions_conversion_remove);
		}
	}

	function transformForumOptions()
	{
		if (isset($this->sources['options'])) {
			$this->transformPermissions($this->sources['options'], $this->options_conversion);
			$this->transformNegativePermissions($this->sources['options'], $this->options_conversion_remove);
		}
	}

	function transformUserPermissions()
	{
		if (isset($this->sources['genericpermissions'])) {
			$this->transformPermissions($this->sources['genericpermissions'], $this->generic_permissions_conversion);
		}
		if (isset($this->sources['wolpermissions'])) {
			$this->transformPermissions($this->sources['wolpermissions'], $this->wol_permissions_conversion);
		}
		if (isset($this->sources['pmquota'])) {
			if ($this->sources['pmquota'] > 0) {
				$this->setPermission($this->pm_default_permissions);
			}
		}
	}

	function transformAllPermissions()
	{
		$this->transformAdministratorPermissions();
		$this->transformModeratorPermissions();
		$this->transformForumOptions();
		$this->transformForumPermissions();
		$this->transformUserPermissions();
	}
}