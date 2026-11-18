<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * Tests Kohana upload class
 *
 * @group kohana
 * @group kohana.core
 * @group kohana.core.upload
 *
 * @package    Kohana
 * @category   Tests
 * @author     Kohana Team
 * @author     Jeremy Bush <contractfrombelow@gmail.com>
 * @copyright  (c) 2008-2012 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_UploadTest extends Unittest_TestCase
{
	/**
	 * @var  string  temporary upload directory
	 */
	protected $upload_dir;

	/**
	 * Provides test data for test_size()
	 * 
	 * @return array
	 */
	public function provider_size()
	{
		return array(
			// $field, $bytes, $environment, $expected
			array(
				'unit_test', 
				5, 
				array('_FILES' => array('unit_test' => array('error' => UPLOAD_ERR_INI_SIZE))), 
				FALSE
			),
			array(
				'unit_test', 
				5, 
				array('_FILES' => array('unit_test' => array('error' => UPLOAD_ERR_NO_FILE))), 
				TRUE
			),
			array(
				'unit_test', 
				'6K', 
				array('_FILES' => array(
					'unit_test' => array(
						'error' => UPLOAD_ERR_OK,
						'name' => 'Unit_Test File',
						'type' => 'image/png',
						'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
						'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
						)
					)
				), 
				TRUE
			),
			array(
				'unit_test', 
				'1B', 
				array('_FILES' => array(
						'unit_test' => array(
							'error' => UPLOAD_ERR_OK,
							'name' => 'Unit_Test File',
							'type' => 'image/png',
							'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
							'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
						)
					)
				), 
				FALSE
			),
		);
	}

	/**
	 * Tests Upload::size
	 *
	 * @test
	 * @dataProvider provider_size
	 * @covers upload::size
	 * @param string $field the files field to test
	 * @param string $bytes valid bite size
	 * @param array $environment set the $_FILES array
	 * @param bool $expected what to expect
	 */
	public function test_size($field, $bytes, $environment, $expected)
	{
		$this->setEnvironment($environment);

		$this->assertSame($expected, Upload::size($_FILES[$field], $bytes));
	}

	/**
	 * size() should throw an exception of the supplied max size is invalid
	 *
	 * @test
	 * @covers upload::size
	 */
	public function test_size_throws_exception_for_invalid_size()
	{
		$this->expectException(Kohana_Exception::class);

		$this->setEnvironment(array(
			'_FILES' => array(
				'unit_test' => array(
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				)
			)
		));

		Upload::size($_FILES['unit_test'], '1DooDah');
	}

	/**
	 * Provides test data for test_vali()
	 *
	 * @return array
	 */
	public function provider_valid()
	{
		return array(
			array(
				TRUE,
				array(
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				)
			),
			array(
				FALSE,
				array(
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				)
			),
			array(
				FALSE,
				array(
					'error' => UPLOAD_ERR_OK,
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				)
			),
			array(
				FALSE,
				array(
					'name' => 'Unit_Test File',
					'error' => UPLOAD_ERR_OK,
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				)
			),
			array(
				FALSE,
				array(
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				)
			),
			array(
				FALSE,
				array(
					'error' => UPLOAD_ERR_OK,
					'name' => 'Unit_Test File',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
				)
			),
			
		);
	}

	/**
	 * Test Upload::valid
	 *
	 * @test
	 * @dataProvider provider_valid
	 * @covers Upload::valid
	 */
	public function test_valid($expected, $file)
	{
		$this->setEnvironment(array(
			'_FILES' => array(
				'unit_test' => $file,
			),
		));

		$this->assertSame($expected, Upload::valid($_FILES['unit_test']));
	}

	/**
	 * Tests Upload::type
	 *
	 * @test
	 * @covers Upload::type
	 */
	public function test_type()
	{
		$this->setEnvironment(array(
			'_FILES' => array(
				'unit_test' => array(
					'error' => UPLOAD_ERR_OK,
					'name' => 'github.png',
					'type' => 'image/png',
					'tmp_name' => Kohana::find_file('tests', 'test_data/github', 'png'),
					'size' => filesize(Kohana::find_file('tests', 'test_data/github', 'png')),
				)
			)
		));
		
		$this->assertTrue(Upload::type($_FILES['unit_test'], array('jpg', 'png', 'gif')));

		$this->assertFalse(Upload::type($_FILES['unit_test'], array('docx')));
	}

	/**
	 * Creates a temporary upload directory and a fake uploaded file
	 *
	 * @param   string  $name  client file name
	 * @return  array   $_FILES item
	 */
	protected function fake_upload($name)
	{
		$this->upload_dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kohana_upload_'.bin2hex(random_bytes(4));
		mkdir($this->upload_dir);

		$tmp = tempnam(sys_get_temp_dir(), 'kup');
		file_put_contents($tmp, 'data');

		return array('name' => $name, 'type' => 'text/plain', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 4);
	}

	// @codingStandardsIgnoreStart
	public function tearDown(): void
	// @codingStandardsIgnoreEnd
	{
		if ( ! empty($this->upload_dir) AND is_dir($this->upload_dir))
		{
			array_map('unlink', glob($this->upload_dir.DIRECTORY_SEPARATOR.'*'));
			rmdir($this->upload_dir);
		}

		parent::tearDown();
	}

	/**
	 * Provides test data for test_save_uses_random_name_and_safe_extension()
	 *
	 * @return array
	 */
	public function provider_save_uses_random_name_and_safe_extension()
	{
		return array(
			array('photo.JPG', '/^[0-9a-f]{32}\\.jpg$/'),
			array('archive.tar.gz', '/^[0-9a-f]{32}\\.gz$/'),
			array('shell.php', '/^[0-9a-f]{32}$/'),
			array('shell.PHTML', '/^[0-9a-f]{32}$/'),
			array('x.jpg.php', '/^[0-9a-f]{32}$/'),
			array('.htaccess', '/^[0-9a-f]{32}$/'),
			array('a.<svg>', '/^[0-9a-f]{32}$/'),
			array('a.verylongextension', '/^[0-9a-f]{32}$/'),
			array('noextension', '/^[0-9a-f]{32}$/'),
			array('my file.png', '/^[0-9a-f]{32}\\.png$/'),
		);
	}

	/**
	 * Upload::save() must not keep the client file name, which the client controls.
	 *
	 * @dataProvider provider_save_uses_random_name_and_safe_extension
	 * @covers Upload::save
	 */
	public function test_save_uses_random_name_and_safe_extension($name, $pattern)
	{
		$file = $this->fake_upload($name);

		$path = Kohana_UploadTest_TestableUpload::save($file, NULL, $this->upload_dir);

		$this->assertIsString($path);
		$this->assertSame(realpath($this->upload_dir), dirname($path));
		$this->assertMatchesRegularExpression($pattern, basename($path));
		$this->assertFileExists($path);
	}

	/**
	 * @covers Upload::save
	 */
	public function test_save_allows_blocked_extension_when_list_is_empty()
	{
		$this->setEnvironment(array('Upload::$blocked_extensions' => array()));
		$file = $this->fake_upload('script.php');

		$path = Kohana_UploadTest_TestableUpload::save($file, NULL, $this->upload_dir);

		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}\\.php$/', basename($path));
	}

	/**
	 * @covers Upload::save
	 */
	public function test_save_uses_explicit_filename()
	{
		$file = $this->fake_upload('a.txt');

		$path = Kohana_UploadTest_TestableUpload::save($file, 'report 1.txt', $this->upload_dir);

		$this->assertSame(realpath($this->upload_dir).DIRECTORY_SEPARATOR.'report_1.txt', $path);
	}

	/**
	 * Provides unsafe explicit file names
	 *
	 * @return array
	 */
	public function provider_save_rejects_unsafe_filename()
	{
		return array(
			array('../x.txt'),
			array('..'),
			array('a/b.txt'),
			array('a\\b.txt'),
			array("a\0.txt"),
			array(''),
		);
	}

	/**
	 * @dataProvider provider_save_rejects_unsafe_filename
	 * @covers Upload::save
	 */
	public function test_save_rejects_unsafe_filename($filename)
	{
		$file = $this->fake_upload('a.txt');

		$this->expectException(Kohana_Exception::class);
		Kohana_UploadTest_TestableUpload::save($file, $filename, $this->upload_dir);
	}
}

/**
 * Replaces the upload checks of PHP, which fail for files that a test creates.
 */
class Kohana_UploadTest_TestableUpload extends Upload {

	protected static function _is_uploaded_file($filename)
	{
		return is_file($filename);
	}

	protected static function _move_uploaded_file($from, $to)
	{
		return rename($from, $to);
	}

}
