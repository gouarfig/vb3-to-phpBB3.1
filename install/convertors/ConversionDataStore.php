<?php
/**
 * Stores some data permanently:
 *   1. put some data with setData($name, $value)
 *   2. destroy the instance
 *   3. create a new instance of DataStore
 *   4. get the data back with getData($name)
 */
class ConversionDataStore
{
	private static $folder = '';
	private static $prefix = '_convertor_';
	private static $extension = '.data';
	private static $context_file = '_context.data';
	private $path = '';
	private $dirty = false;
	private $data = Array();

	/**
     * Returns the *Singleton* instance of this class.
     *
     * @staticvar Singleton $instance The *Singleton* instances of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function getInstance()
    {
        static $instance = null;
        if (null === $instance) {
            $instance = new static();
        }

        return $instance;
    }

	protected function __construct()
	{
		$this->path = getcwd();
		if (substr($this->path, -1) != '/')
		{
			$this->path .= '/';
		}
		$this->restoreContext();
	}

	public function __destruct()
	{
		if ($this->dirty)
		{
			$this->saveContext();
		}
	}

	private function dataFileName($data_name)
	{
		return $this->path . self::$folder . self::$prefix . md5($data_name) . self::$extension;
	}

	private function contextFileName()
	{
		return $this->path . self::$context_file;
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
		$filehandle = fopen($filename, 'w');
		if ($filehandle) {
			if (flock($filehandle, LOCK_EX)) {
				fwrite($filehandle, serialize($this->data[$data_name]));
				fflush($filehandle);
				flock($filehandle, LOCK_UN);
				$saved = true;
			}
			fclose($filehandle);
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

		$filename = $this->contextFileName();
		$filehandle = fopen($filename, 'w');
		if ($filehandle) {
			if (flock($filehandle, LOCK_EX)) {
				fwrite($filehandle, serialize($context));
				fflush($filehandle);
				flock($filehandle, LOCK_UN);
				$saved = true;
			}
			fclose($filehandle);
		}
		$this->dirty = !$saved;
		return $saved;
	}

	private function restoreContext()
	{
		$context = Array();
		$contextFileName = $this->contextFileName();
		if (is_file($contextFileName)) {
			$tmp = file_get_contents($contextFileName);
			$context = unserialize($tmp);
		}
		if (!empty($context)) {
			foreach ($context as $data_name) {
				$this->loadData($data_name);
			}
		}
		$this->dirty = false;
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
	 * Cleans everything (all stored data and the context file)
	 */
	function clean()
	{
		$this->purge();
		@unlink($this->contextFileName());
		$this->dirty = false;
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
		$this->dirty = true;
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