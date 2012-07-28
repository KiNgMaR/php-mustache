<?php
/**
 * @package php-mustache
 * @subpackage tests
 **/

/**
 * @package php-mustache
 * @subpackage tests
 **/
abstract class MustacheSpecsTests
{
	/**
	 * List of tests, populated by loadTests().
	 * @var array
	 **/
	protected $tests = array();

	/**
	 * Default constructor.
	 **/
	public function construct()
	{
	}

	/**
	 * Loads specs tests from their JSON files in $specs_dir.
	 * @param string $specs_dir
	 * @param boolean $silent
	 * @return boolean
	 **/
	public function loadTests($specs_dir, $silent = false)
	{
		$this->tests = array(); // reset

		// this is pretty simple, but gets the job done:
		foreach(glob($specs_dir . '/*.json') as $json_file)
		{
			$data = json_decode(file_get_contents($json_file));

			if($data)
			{
				$name_prefix = pathinfo($json_file, PATHINFO_FILENAME) . '-';

				foreach($data->tests as $test)
				{
					$this->tests[$name_prefix . $test->name] = $test;
				}
			}
			elseif(!$silent)
			{
				echo 'Failed to decode JSON data from "' . $json_file . '". Skipping.' . "\n";
			}
		}

		// assume it went well if at least one test has been loaded:
		return (count($this->tests) > 0);
	}

	/**
	 * Executes a $test, returns debug information in $info.
	 * @param stdClass $test Test from a json file.
	 * @param string $info
	 * @return boolean Whether the test has passed.
	 **/
	protected function runTest(stdClass $test, &$info)
	{
		$info = 'Running test "' . $test->name . '" (' . $test->desc . ")...\n\n";

		$parser = new MustacheParser($test->template, MUSTACHE_WHITESPACE_STRICT);

		if(isset($test->partials))
		{
			$parser->addPartials((array)$test->partials);
		}

		// :TODO: add a way to test for expected exceptions
		try
		{
			$parser->parse();
		}
		catch(MustacheParserException $ex)
		{
			$info .= '[PARSER EXCEPTION] ' . $ex->getMessage();
			return false;
		}

		// carry out actual test, the details are defined by the
		// child class implementing runFromParser:
		$extra_info = '';
		$output = $this->runFromParser($parser, $test->data, $extra_info);

		// only consider an exact match a passed test:
		$pass = (strcmp($output, $test->expected) == 0);

		if($pass)
		{
			$info .= 'TEST PASSED!';
		}
		else
		{
			// collect some debug information for failed tests:
			$info .= "TEST NOT PASSED:\n>output>\n" . $output . "\n<<>expected>\n" . $test->expected . "\n<<\n\n" . (string)$extra_info;
			$info .= "\n\nTEMPLATE:\n\n{$test->template}\n\nDATA:\n\n" . json_encode($test->data) . "\n";
			if(!empty($test->partials))
			{
				$info .= "\nPARTIALS:\n\n" . print_r($test->partials, true) . "\n";
			}
		}

		return $pass;
	}

	/**
	 * Executes all loaded tests from $this->tests. Outputs a line with FAIL or PASS for each test and a summary at the end.
	 * @param string $fail_output_dir Optional, path to a directory where a file with debug information is created for each failed test. This directory is *not* being cleaned beforehand.
	 **/
	public function runTests($fail_output_dir = NULL)
	{
		echo 'Running ' . count($this->tests) . " tests...\n\n";

		$tests_passed = 0;

		foreach($this->tests as $test_id => $test)
		{
			$result = $this->runTest($test, $info);

			if($result)
			{
				$tests_passed++;
				echo "[PASS] '$test_id'\n";
			}
			else
			{
				echo "[FAIL] '{$test_id}'\n";

				if(is_string($fail_output_dir) && is_dir($fail_output_dir))
				{
					file_put_contents($fail_output_dir . '/' . preg_replace('~[^a-zA-Z0-9 _.-]+~', '', $test_id) . '.txt', $info);
				}
			}
		}

		echo "Passed $tests_passed/" . count($this->tests) . " tests!\n";
	}

	/**
	 * Each testing child class has to implement this.
	 * @param MustacheParser $parser Parser instance that has the template ready to use.
	 * @param object|array $data Template view/data.
	 * @param string $extra_info The child class can put extra debug info here.
	 * @return Final output from putting the template in $parser and the data in $data together.
	 **/
	abstract protected function runFromParser(MustacheParser $parser, $data, &$extra_info = NULL);
}


/**
 * Specs test suite class for MustacheParser + MustachePHPCodeGen.
 * @package php-mustache
 * @subpackage tests
 **/
class CompilingMustacheSpecsTests extends MustacheSpecsTests
{
	/**
	 * @see MustacheSpecsTests::runFromParser
	 **/
	protected function runFromParser(MustacheParser $parser, $data, &$extra_info = NULL)
	{
		$codegen = new MustachePHPCodeGen($parser);
		$code = $codegen->generate('view');

		// use a separate, clean, scope for eval():
		$test_scope = function($view) use ($code)
		{
			eval('?>' . $code . '<?php ');
		};

		// use output buffering to retrieve the result:
		ob_start();
		$test_scope($data);

		$extra_info = "GENERATED CODE:\n\n" . $code;

		return ob_get_clean();
	}
}


/**
 * Specs test suite class for MustacheParser + MustacheInterpreter.
 * @package php-mustache
 * @subpackage tests
 **/
class MustacheInterpreterSpecsTests extends MustacheSpecsTests
{
	/**
	 * @see MustacheSpecsTests::runFromParser
	 **/
	protected function runFromParser(MustacheParser $parser, $data, &$extra_info = NULL)
	{
		// yay, this is simple!
		$mi = new MustacheInterpreter($parser);
		return $mi->run($data);
	}
}


/**
 * Specs test suite class for MustacheJavaScriptCodeGen.
 * @package php-mustache
 * @subpackage tests
 **/
class MustacheJavaScriptSpecsTests extends MustacheSpecsTests
{
	/**
	 * @see MustacheSpecsTests::runFromParser
	 **/
	protected function runFromParser(MustacheParser $parser, $data, &$extra_info = NULL)
	{
		$codegen = new MustacheJavaScriptCodeGen($parser);
		$js = $codegen->generate();

		$extra_info = "GENERATED CODE:\n\n" . $js;

		$temp_fn = sys_get_temp_dir() . '/MustacheTest.js';

		file_put_contents($temp_fn,
			MustacheJavaScriptCodeGen::getRuntimeCode() . "\n" .
			'var data = ' . json_encode($data) . ";\n" .
			'var tpl = ' . $js . ";\n" .
			'require("util").print(tpl(data));');

		$result = shell_exec('node ' . escapeshellarg($temp_fn));

		return $result;
	}
}


/**
 * main() sort of thing.
 **/
function mustache_tests_main($argc, $argv)
{
	if(!empty($argc) && strstr($argv[0], basename(__FILE__)) && count($argv) > 1)
	{
		$tests = NULL;

		if($argv[1] == '-interpreted')
		{
			/**
			 * Load interpreter classes.
			 **/
			require_once dirname(__FILE__) . '/../lib/MustacheInterpreter.php';

			$tests = new MustacheInterpreterSpecsTests();
		}
		elseif($argv[1] == '-compiled')
		{
			/**
			 * Load php code gen classes.
			 **/
			require_once dirname(__FILE__) . '/../lib/MustachePhpCodeGen.php';
			/**
			 * Load runtime classes as required by eval()ing our generated code.
			 **/
			require_once dirname(__FILE__) . '/../lib/MustacheRuntime.php';

			$tests = new CompilingMustacheSpecsTests();
		}
		elseif($argv[1] == '-javascript')
		{
			/**
			 * Load javascript code gen classes.
			 **/
			require_once dirname(__FILE__) . '/../lib/MustacheJavaScriptCodeGen.php';

			$tests = new MustacheJavaScriptSpecsTests();
		}

		if(!$tests)
		{
			echo 'Please use -interpreted, -compiled or -javascript as command line argument.' . "\n";
		}
		else
		{
			// run specs test suite...

			if($tests->loadTests(dirname(__FILE__) . '/specs'))
			{
				$tests->runTests(dirname(__FILE__) . '/fail-output');
			}
			else
			{
				echo '[ERROR] Unable to load specs test files.';
			}
		}
	}
	else
	{
		echo 'Please run this script from the command line and use -interpreted or -compiled as command line argument.' . "\n";
	}
}

mustache_tests_main($argc, $argv);
