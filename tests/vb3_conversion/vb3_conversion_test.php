<?php


/**
 *
 *
 * @group conversion
 */
class vb3_conversion_test extends phpbb_test_case
{

	public function test_files_exist()
	{
		global $phpbb_root_path;

		$this->assertTrue(is_file($phpbb_root_path . 'install/convertors/functions_vb3.php'));
		$this->assertTrue(is_file($phpbb_root_path . 'install/convertors/convert_vb3.php'));
		$this->assertTrue(is_file($phpbb_root_path . 'install/convertors/vb_conversion_constants.php'));
		$this->assertTrue(is_file($phpbb_root_path . 'install/convertors/vbPermission.class.php'));
		$this->assertTrue(is_file($phpbb_root_path . 'install/convertors/functions_vb3_permissions.php'));
		$this->assertTrue(is_file($phpbb_root_path . 'install/convertors/ConversionDataStore.php'));
	}

	/**
	 *
	 * @ignore
	 */
	public function test_include_files()
	{
		global $phpbb_root_path;

		include_once $phpbb_root_path . 'install/convertors/functions_vb3.php';
		$this->assertTrue(function_exists('vb_user_id'));
	}
}
