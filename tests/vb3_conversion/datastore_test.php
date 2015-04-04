<?php

global $phpbb_root_path;
include_once $phpbb_root_path . 'install/convertors/ConversionDataStore.php';

/**
 *
 *
 * @group conversion
 */
class datastore_test extends phpbb_test_case
{
	private $myfirstname = 'my first name!';
	private $mysurname = 'my surname!';

	public function test_empty_getData()
	{
		$datastore = ConversionDataStore::getInstance();
		$this->assertEmpty($datastore->getData('no_data'));
		unset($datastore);
	}

	/**
	 * @depends test_empty_getData
	 */
	public function test_setter_and_getter()
	{
		$datastore = ConversionDataStore::getInstance();
		$datastore->setData('First name', $this->myfirstname);
		$datastore->setData('Surname', $this->mysurname);

		$this->assertEquals($this->myfirstname, $datastore->getData('First name'));
		$this->assertEquals($this->mysurname, $datastore->getData('Surname'));
		unset($datastore);
	}

	/**
	 * @depends test_setter_and_getter
	 */
	public function test_data_was_saved()
	{
		$datastore = ConversionDataStore::getInstance();
		$this->assertEmpty($datastore->getData('no_data'));
		$this->assertEquals($this->myfirstname, $datastore->getData('First name'));
		$this->assertEquals($this->mysurname, $datastore->getData('Surname'));
		unset($datastore);
	}

	/**
	 * @depends test_data_was_saved
	 */
	public function test_clearData()
	{
		$datastore = ConversionDataStore::getInstance();
		$datastore->clearData('First name');
		$this->assertEmpty($datastore->getData('First name'));
		unset($datastore);
	}

	/**
	 * @depends test_clearData
	 */
	public function test_purge()
	{
		$datastore = ConversionDataStore::getInstance();
		$datastore->purge();
		$this->assertEmpty($datastore->getData('Surname'));
		unset($datastore);
	}

	/**
	 * @depends test_purge
	 */
	public function test_flush()
	{
		$datastore = ConversionDataStore::getInstance();
		$datastore->flush();
		$this->assertFileExists('_context.data');
	}

	/**
	 * @depends test_flush
	 */
	public function test_clean()
	{
		$datastore = ConversionDataStore::getInstance();
		$this->assertFileExists('_context.data');
		$datastore->clean();
		unset($datastore);
		$this->assertFileNotExists('_context.data');
	}
}
