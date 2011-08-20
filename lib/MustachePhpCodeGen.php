<?php
/**
 * @package php-mustache
 * @subpackage compiling
 * @author Ingmar Runge 2011 - https://github.com/KiNgMaR - BSD license
 **/


/**
 *
 * @package php-mustache
 * @subpackage compiling
 **/
class MustachePHPCodeGen
{
	const PHP_OPEN = '<?php ';
	const PHP_CLOSE = " ?>";
	const PHP_CLOSE_AFTER_OUTPUT = " ?>\n";

	protected $tree = NULL;
	protected $view_var_name;
	protected $codebit_var;

	public function __construct(MustacheParser $parser)
	{
		$this->tree = $parser->getTree();
		$this->codebit_var = sprintf('%08X', crc32(getmypid() . microtime()));
	}

	public function generate($view_var_name)
	{
		$this->view_var_name = $view_var_name;

		$code = $this->generateInternal($this->tree);
		$code = str_replace(self::PHP_CLOSE . self::PHP_OPEN, "\n", $code);

		return $code;
	}

	protected function generateInternal(MustacheParserObject $obj)
	{
		if($obj instanceof MustacheParserSection)
		{
			return $this->generateSection($obj);
		}
		elseif($obj instanceof MustacheParserLiteral)
		{
			return $obj->getContents();
		}
		elseif($obj instanceof MustacheParserVariable)
		{
			return $this->generateVar($obj);
		}
	}

	static protected function varToPhp(MustacheParserObjectWithName &$var)
	{
		// var_export takes care of escaping matters plus nicely formats arrays into PHP syntax.
		return var_export($var->isDotNotation() ? $var->getNames() : $var->getName(), true);
	}

	protected function generateSection(MustacheParserSection $section)
	{
		$is_root = ($section->getName() === '#ROOT#');

		$section_id = sprintf('%08X', crc32(microtime() . $section->getName()));

		$s = '';

		if($is_root)
		{
			$s .= self::PHP_OPEN . '$stack_' . $this->codebit_var . ' = new MustacheRuntimeStack($' . $this->view_var_name . '); ';
		}
		else
		{
			$s .= self::PHP_OPEN . '$secv = MustacheRuntime::lookUpVar($mustache_stack, ' . self::varToPhp($section) . '); ';
		}

		$s .= '$section_' . $section_id . ' = function(&$mustache_stack) {' . self::PHP_CLOSE;

		foreach($section as $child)
		{
			$s .= $this->generateInternal($child);
		}

		$s .= self::PHP_OPEN . '};' . self::PHP_CLOSE_AFTER_OUTPUT;

		if($is_root)
		{
			$s .= self::PHP_OPEN . '$section_' . $section_id . '($stack_' . $this->codebit_var . '); ';
			$s .= 'unset($stack_' . $this->codebit_var . ');' . self::PHP_CLOSE;
		}
		else
		{
			$s .= self::PHP_OPEN;
			if($section instanceof MustacheParserInvertedSection)
			{
				$s .= 'if(MustacheRuntime::sectionFalsey($secv)) { ';
				$s .= '$mustache_stack->push($secv); $section_' . $section_id . '($mustache_stack); $mustache_stack->pop(); ';
				$s .= '}';
			}
			else
			{
				$s .= 'if(MustacheRuntime::sectionIterable($secv)) foreach($secv as $v) { ';
				$s .= '$mustache_stack->push($v); $section_' . $section_id . '($mustache_stack); $mustache_stack->pop(); ';
				$s .= '}';
			}
			$s .= self::PHP_CLOSE;
		}

		return $s;
	}

	protected function generateVar(MustacheParserVariable $var)
	{
		$s = 'MustacheRuntime::lookUpVar($mustache_stack, ' . self::varToPhp($var) . ')';

		if($var->escape())
		{
			$s = 'htmlspecialchars(' . $s . ')';
		}

		return self::PHP_OPEN . 'echo ' . $s . ';' . self::PHP_CLOSE_AFTER_OUTPUT;
	}
}


/**
 * Pull in parser classes...
 **/
require_once dirname(__FILE__) . '/MustacheParser.php';
