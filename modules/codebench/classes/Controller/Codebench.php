<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * Codebench — A benchmarking module.
 *
 * @package    Kohana/Codebench
 * @category   Controllers
 * @author     Kohana Team
 * @copyright  (c) 2009 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Controller_Codebench extends Kohana_Controller_Template {

	// The codebench view
	public $template = 'codebench';

	public function action_index()
	{
		$class = $this->request->param('class');

		// Convert submitted class name to URI segment
		if (isset($_POST['class']))
		{
			throw HTTP_Exception::factory(302)->location('codebench/'.rawurlencode(trim((string) $_POST['class'])));
		}

		// Pass the class name on to the view
		$this->template->class = (string) $class;

		// Run only Codebench subclasses: the class name comes from the URL
		if (preg_match('/^[A-Za-z0-9_]+$/D', (string) $class)
			AND class_exists($class)
			AND is_subclass_of($class, 'Codebench'))
		{
			$codebench = new $class;
			$this->template->codebench = $codebench->run();
		}
	}
}
