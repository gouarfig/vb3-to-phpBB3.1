<?php
global $phpbb_root_path;
include_once $phpbb_root_path . 'install/convertors/functions_vb3.php';

/**
 *
 *
 * @group conversion
 */
class user_test extends phpbb_database_test_case
{
    public function getDataSet()
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/user.xml');
    }

    public static function fetchrow_data()
    {
        return array(
            array(1, 3),
            array(2, 2),
        );
    }

    /**
    * @dataProvider fetchrow_data
    */
    public function test_user_id($where, $expected)
	{
		global $config;
		$config['increment_user_id'] = 3;

		$this->assertEquals($expected, vb_user_id($where));
	}
}