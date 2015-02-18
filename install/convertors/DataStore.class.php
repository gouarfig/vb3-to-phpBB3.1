<?php

/**
 * Stores some data permanently:
 *   1. put some data with setData($name, $value)
 *   2. destroy the instance
 *   3. create a new instance of DataStore
 *   4. get the data back with getData($name)
 */
class DataStore
{
	private static $folder = '';
	private static $prefix = '_convertor_';
	private static $extension = '.data';
	private static $context_file = '_context.data';
	private $data = Array();

	function __construct() {
		$this->restoreContext();
	}

	function __destruct() {
		$this->saveContext();
	}

	private function dataFileName($data_name)
	{
		return self::$folder . self::$prefix . md5($data_name) . self::$extension;
	}

	private function &loadData($data_name)
	{
		$this->data[$data_name] = null;
		$filename = $this->dataFileName($data_name);
		if (is_file($filename)) {
			$tmp = file_get_contents($filename);
			$this->data[$data_name] = unserialize($tmp);
		}
		return $this->data[$data_name];
	}

	private function saveData($data_name)
	{
		$saved = false;
		
		$filename = $this->dataFileName($data_name);
		$filehandle = @fopen($filename, 'w');
		if ($filehandle) {
			fwrite($filehandle, serialize($this->data[$data_name]));
			fclose($filehandle);
			$saved = true;
		}
		return $saved;
	}

	private function saveContext()
	{
		$saved = false;

		$context = array_keys($this->data);
		foreach ($context as $data_name) {
			$this->saveData($data_name);
		}

		$filehandle = @fopen(self::$context_file, 'w');
		if ($filehandle) {
			fwrite($filehandle, serialize($context));
			fclose($filehandle);
			$saved = true;
		}
		return $saved;
	}

	private function restoreContext()
	{
		$context = Array();
		if (is_file(self::$context_file)) {
			$tmp = file_get_contents(self::$context_file);
			$context = unserialize($tmp);
		}
		if (!empty($context)) {
			foreach ($context as $data_name) {
				$this->loadData($data_name);
			}
		}
	}

	/**
	 * Save all data right now
	 */
	function flush()
	{
		$this->saveContext();
	}

	/**
	 * Deletes all stored data. Please note this operation is not reversible.
	 */
	function purge()
	{
		$context = array_keys($this->data);
		foreach ($context as $data_name) {
			$this->clearData($data_name);
		}
	}

	/**
	 * Clears the data name $data_name.
	 * Please be careful, there's no coming back possible!
	 *
	 * @param string $data_name
	 */
	function clearData($data_name)
	{
		unset($this->data[$data_name]);
		$filename = $this->dataFileName($data_name);
		@unlink($filename);
	}

	/**
	 * Puts some data under the name $data_name
	 * Please note that $data is passed by reference
	 *
	 * @param string $data_name
	 * @param mixed $data
	 */
	function setData($data_name, &$data)
	{
		$this->data[$data_name] = &$data;
	}

	/**
	 * Gets the data $data_name back.
	 * Please note the data is returned by reference
	 *
	 * @param string $data_name
	 * @return mixed
	 */
	function getData($data_name)
	{
		return isset($this->data[$data_name]) ? $this->data[$data_name] : null;
	}
}