<?php defined('SYSPATH') OR die('Kohana bootstrap needs to be included before tests run');

/**
 * The guide-media and guide routes must serve only files inside the guide
 * directories.
 *
 * @group kohana
 * @group kohana.userguide
 */
class Userguide_MediaTest extends Unittest_TestCase
{
	public function provider_media_traversal()
	{
		return array(
			array('guide-media/../../../application/bootstrap.php'),
			array('guide-media/%2e%2e/%2e%2e/%2e%2e/application/bootstrap.php'),
			array('guide-media/css/../../../../../application/bootstrap.php'),
			array('guide-media/../../../../../../../../../../etc/passwd.'),
			array('guide-media/css/..%5c..%5c..%5capplication%5cbootstrap.php'),
			array("guide-media/css/kodoc.css\0.png"),
		);
	}

	/**
	 * @dataProvider provider_media_traversal
	 */
	public function test_media_rejects_paths_outside_the_media_directory($uri)
	{
		$response = Request::factory(rawurldecode($uri))->execute();

		$this->assertSame(404, $response->status());
		$this->assertStringNotContainsString('Kohana::modules', $response->body());
		$this->assertStringNotContainsString('root:', $response->body());
	}

	public function test_media_serves_guide_files()
	{
		$response = Request::factory('guide-media/css/kodoc.css')->execute();

		$this->assertSame(200, $response->status());
		$this->assertNotSame('', $response->body());
	}

	public function provider_unsafe_pages()
	{
		return array(
			array('userguide/../../../application/bootstrap'),
			array('../../application/bootstrap'),
			array("userguide/adding\0"),
			array('userguide\\..\\..\\README'),
		);
	}

	/**
	 * @dataProvider provider_unsafe_pages
	 */
	public function test_file_rejects_unsafe_pages($page)
	{
		$controller = $this->getMock('Controller_Userguide', array('__construct'), array(), '', FALSE);

		$this->assertFalse($controller->file($page));
	}
}
