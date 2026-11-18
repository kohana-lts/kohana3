<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests Kohana Logging API
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.logging
 *
 * @package    Kohana
 * @category   Tests
 * @author     Kohana Team
 * @author     Matt Button <matthew@sigswitch.com>
 * @copyright  (c) 2008-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_LogTest extends Unittest_TestCase
{

	/**
	 * @var  array  temporary directories to remove
	 */
	protected $temp_dirs = array();

	/**
	 * @var  integer  umask to restore
	 */
	protected $old_umask = NULL;

	// @codingStandardsIgnoreStart
	public function tearDown(): void
	// @codingStandardsIgnoreEnd
	{
		$this->remove_temp_dirs();
		parent::tearDown();
	}

	/**
	 * Tests that when a new logger is created the list of messages is initially
	 * empty
	 *
	 * @test
	 * @covers Log
	 */
	public function test_messages_is_initially_empty()
	{
		$logger = new Log;

		$this->assertAttributeSame(array(), '_messages', $logger);
	}

	/**
	 * Tests that when a new logger is created the list of writers is initially
	 * empty
	 *
	 * @test
	 * @covers Log
	 */
	public function test_writers_is_initially_empty()
	{
		$logger = new Log;

		$this->assertAttributeSame(array(), '_writers', $logger);
	}

	/**
	 * Test that attaching a log writer using an array of levels adds it to the array of log writers
	 *
	 * @TODO Is this test too specific?
	 *
	 * @test
	 * @covers Log::attach
	 */
	public function test_attach_attaches_log_writer_and_returns_this()
	{
		$logger = new Log;
		$writer = $this->getMockForAbstractClass('Log_Writer');

		$this->assertSame($logger, $logger->attach($writer));

		$this->assertAttributeSame(
			array(spl_object_hash($writer) => array('object' => $writer, 'levels' => array())),
			'_writers',
			$logger
		);
	}

	/**
	 * Test that attaching a log writer using a min/max level adds it to the array of log writers
	 *
	 * @TODO Is this test too specific?
	 *
	 * @test
	 * @covers Log::attach
	 */
	public function test_attach_attaches_log_writer_min_max_and_returns_this()
	{
		$logger = new Log;
		$writer = $this->getMockForAbstractClass('Log_Writer');

		$this->assertSame($logger, $logger->attach($writer, Log::NOTICE, Log::CRITICAL));

		$this->assertAttributeSame(
			array(spl_object_hash($writer) => array('object' => $writer, 'levels' => array(Log::CRITICAL, Log::ERROR, Log::WARNING, Log::NOTICE))),
			'_writers',
			$logger
		);
	}

	/**
	 * When we call detach() we expect the specified log writer to be removed
	 *
	 * @test
	 * @covers Log::detach
	 */
	public function test_detach_removes_log_writer_and_returns_this()
	{
		$logger = new Log;
		$writer = $this->getMockForAbstractClass('Log_Writer');

		$logger->attach($writer);

		$this->assertSame($logger, $logger->detach($writer));

		$this->assertAttributeSame(array(), '_writers', $logger);
	}



	/**
	 * Provides test data for test_log_file_modes()
	 *
	 * @return array
	 */
	public function provider_log_file_modes()
	{
		return array(
			array(array(), 02755, 0644),
			array(array('Log_File::$dir_mode' => 0750, 'Log_File::$file_mode' => 0640), 0750, 0640),
		);
	}

	/**
	 * Log_File must not create world-writable directories or files.
	 *
	 * @test
	 * @dataProvider provider_log_file_modes
	 * @covers Log_File::write
	 */
	public function test_log_file_modes($environment, $dir_mode, $file_mode)
	{
		$dir = $this->make_temp_dir();
		$this->setEnvironment($environment);
		$this->old_umask = umask(0);

		$writer = new Log_File($dir);
		$writer->write(array(array(
			'time'  => time(),
			'level' => Log::INFO,
			'body'  => 'message',
			'file'  => __FILE__,
			'line'  => __LINE__,
		)));

		$year  = $dir.DIRECTORY_SEPARATOR.date('Y');
		$month = $year.DIRECTORY_SEPARATOR.date('m');

		$this->assertSame($dir_mode, $this->mode($year));
		$this->assertSame($dir_mode, $this->mode($month));
		$this->assertSame($file_mode, $this->mode($month.DIRECTORY_SEPARATOR.date('d').EXT));
	}

	/**
	 * Creates an empty temporary directory
	 *
	 * @return  string
	 */
	protected function make_temp_dir()
	{
		$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_modes_'.bin2hex(random_bytes(4));
		mkdir($dir);
		$this->temp_dirs[] = $dir;

		return realpath($dir);
	}

	/**
	 * Removes the temporary directories and restores the umask
	 */
	protected function remove_temp_dirs()
	{
		if ($this->old_umask !== NULL)
		{
			umask($this->old_umask);
			$this->old_umask = NULL;
		}

		foreach ($this->temp_dirs as $dir)
		{
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($files as $file)
			{
				$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
			}

			rmdir($dir);
		}

		$this->temp_dirs = array();
	}

	/**
	 * @param   string  $path
	 * @return  integer permission bits of the path
	 */
	protected function mode($path)
	{
		clearstatcache();

		return fileperms($path) & 07777;
	}
}
