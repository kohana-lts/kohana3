<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * File log writer. Writes out messages and stores them in a YYYY/MM directory.
 *
 * @package    Kohana
 * @category   Logging
 * @author     Kohana Team
 * @copyright  (c) 2008-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_Log_File extends Log_Writer {

	/**
	 * @var  integer  mode of the year and month directories
	 */
	public static $dir_mode = 02755;

	/**
	 * @var  integer  mode of new log files. Use 0664 or 0666 when the web
	 *                server and the CLI write logs as different users.
	 */
	public static $file_mode = 0644;

	/**
	 * @var  string  Directory to place log files in
	 */
	protected $_directory;

	/**
	 * Creates a new file logger. Checks that the directory exists and
	 * is writable.
	 *
	 *     $writer = new Log_File($directory);
	 *
	 * @param   string  $directory  log directory
	 * @return  void
	 */
	public function __construct($directory)
	{
		if ( ! is_dir($directory) OR ! is_writable($directory))
		{
			throw new Kohana_Exception('Directory :dir must be writable',
				array(':dir' => Debug::path($directory)));
		}

		// Determine the directory path
		$this->_directory = realpath($directory).DIRECTORY_SEPARATOR;
	}

	/**
	 * Writes each of the messages into the log file. The log file will be
	 * appended to the `YYYY/MM/DD.log.php` file, where YYYY is the current
	 * year, MM is the current month, and DD is the current day.
	 *
	 *     $writer->write($messages);
	 *
	 * @param   array   $messages
	 * @return  void
	 */
	public function write(array $messages)
	{
		// Set the yearly directory name
		$directory = $this->_directory.date('Y');

		if ( ! is_dir($directory))
		{
			// Create the yearly directory
			mkdir($directory, Log_File::$dir_mode);

			// Set permissions (must be manually set to fix umask issues)
			chmod($directory, Log_File::$dir_mode);
		}

		// Add the month to the directory
		$directory .= DIRECTORY_SEPARATOR.date('m');

		if ( ! is_dir($directory))
		{
			// Create the monthly directory
			mkdir($directory, Log_File::$dir_mode);

			// Set permissions (must be manually set to fix umask issues)
			chmod($directory, Log_File::$dir_mode);
		}

		// Set the name of the log file
		$filename = $directory.DIRECTORY_SEPARATOR.date('d').EXT;

		if ( ! file_exists($filename))
		{
			// Create the log file
			file_put_contents($filename, Kohana::FILE_SECURITY.' ?>'.PHP_EOL);

			// Set permissions (must be manually set to fix umask issues)
			chmod($filename, Log_File::$file_mode);
		}

		foreach ($messages as $message)
		{
			// Write each message into the log file
			file_put_contents($filename, PHP_EOL.$this->format_message($message), FILE_APPEND);
		}
	}

}
