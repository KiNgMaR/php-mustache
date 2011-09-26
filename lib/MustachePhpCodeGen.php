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
	/**
	 * Internal: Opening PHP tag.
	 **/
	const PHP_OPEN = '<?php ';
	/**
	 * Internal: Closing PHP tag.
	 **/
	const PHP_CLOSE = " ?>";
	/**
	 * Internal: Closing PHP tag after code that outputs stuff.
	 **/
	const PHP_CLOSE_AFTER_OUTPUT = " ?>\n";

	/**
	 * Root section from MustacheParser's getTree().
	 * @var MustacheParserSection
	 **/
	protected $tree = NULL;
	/**
	 * @var string
	 **/
	protected $view_var_name;
	/**
	 * Random string for unique variable names.
	 * @var string
	 **/
	protected $codebit_var;
	/**
	 * Section index for even more unique variable names.
	 * @var int
	 **/
	protected $section_idx = 0;
	/**
	 * @var int
	 **/
	protected $whitespace_mode;

	/**
	 * @param MustacheParser $parser Parser with the syntax tree.
	 **/
	public function __construct(MustacheParser $parser)
	{
		$this->tree = $parser->getTree();
		$this->whitespace_mode = $parser->getWhitespaceMode();
		$this->codebit_var = sprintf('%08X', crc32(getmypid() . microtime()));
	}

	/**
	 * Does the magic, i.e. turns the given parser tree into PHP code that
	 * processes the data from $view_var_name according to the template.
	 * @param string $view_var_name
	 * @return string Returns false if there's no parser tree or no data variable.
	 **/
	public function generate($view_var_name)
	{
		if(!is_string($view_var_name) || !is_object($this->tree))
		{
			return false;
		}

		$this->view_var_name = $view_var_name;

		// start code generation:
		$code = $this->generateInternal($this->tree);
		/* remove ?><?php */
		$code = str_replace(self::PHP_CLOSE . self::PHP_OPEN, "\n", $code);

		if($this->whitespace_mode == MUSTACHE_WHITESPACE_STRIP)
		{
			$code = self::stripPhpWhitespace($code);
		}

		return $code;
	}

	/**
	 * Branches into the appropriate generating method based on $obj's class.
	 * @param MustacheParserObject $obj child class instance inheriting from MustacheParserObject
	 * @return string
	 **/
	protected function generateInternal(MustacheParserObject $obj)
	{
		if($obj instanceof MustacheParserSection)
		{
			return $this->generateSection($obj);
		}
		elseif($obj instanceof MustacheParserLiteral)
		{
			// just return the contents, no fuzz:
			return $obj->getContents();
		}
		elseif($obj instanceof MustacheParserVariable)
		{
			return $this->generateVar($obj);
		}
		elseif($obj instanceof MustacheParserRuntimeTemplate)
		{
			return $this->generateRuntimeTemplate($obj);
		}
	}

	/**
	 * Returns PHP code that later (at runtime) evaluates to $var's contents. Also handles dot-notation style variables.
	 * @param MustacheParserObjectWithName $var
	 * @return string
	 **/
	static protected function varToPhp(MustacheParserObjectWithName $var)
	{
		// var_export takes care of escaping matters plus nicely formats arrays into PHP syntax.
		return var_export($var->isDotNotation() ? $var->getNames() : $var->getName(), true);
	}

	/**
	 * Generates PHP code that runs a {section} based on the current context.
	 * @param $MustacheParserSection $section
	 * @return string
	 **/
	protected function generateSection(MustacheParserSection $section)
	{
		$is_root = ($section->getName() === '#ROOT#');

		$section_id = ++$this->section_idx;
		if($is_root == 1)
		{
			// use a _globally_ unique identifier outside template sections (i.e. outside closures):
			$section_id = $this->codebit_var;
		}

		$s = '';

		if($is_root)
		{
			// generate a MustacheRuntimeStack instance from the given view's variable name.
			$s .= self::PHP_OPEN . '$stack_' . $this->codebit_var . ' = new MustacheRuntimeStack($' . $this->view_var_name . '); ';
		}
		else
		{
			// look up the variable name given in the {section} tag.
			$s .= self::PHP_OPEN . '$secv = MustacheRuntime::lookUpVar($mustache_stack, ' . self::varToPhp($section) . '); ';
		}

		// use a closure to avoid pollution of the global or current runtime scope.
		$s .= '$section_' . $section_id . ' = function(&$mustache_stack) {' . self::PHP_CLOSE;

		// add section child contents:
		foreach($section as $child)
		{
			$s .= $this->generateInternal($child);
		}

		$s .= self::PHP_OPEN . '};' . self::PHP_CLOSE_AFTER_OUTPUT;

		if($is_root)
		{
			// execute wrapping root closure, then clean up:
			$s .= self::PHP_OPEN . '$section_' . $section_id . '($stack_' . $this->codebit_var . '); ';
			$s .= 'unset($stack_' . $this->codebit_var . ');' . self::PHP_CLOSE;
		}
		else
		{
			// generate if clause according to the section type:
			$s .= self::PHP_OPEN;
			if($section instanceof MustacheParserInvertedSection)
			{
				// inverted section are never iterable:
				$s .= 'if(MustacheRuntime::sectionFalsey($secv)) { ';
				$s .= '$mustache_stack->push($secv); $section_' . $section_id . '($mustache_stack); $mustache_stack->pop(); ';
				$s .= '}';
			}
			else
			{
				// use foreach to iterate:
				// @see MustacheRuntime::sectionIterable
				$s .= 'if(MustacheRuntime::sectionIterable($secv)) foreach($secv as $v) { ';
				$s .= '$mustache_stack->push($v); $section_' . $section_id . '($mustache_stack); $mustache_stack->pop(); ';
				$s .= '}';
			}
			$s .= self::PHP_CLOSE;
		}

		return $s;
	}

	/**
	 * Returns PHP code that inserts the contents of an entity with the given variable name at runtime.
	 * @param MustacheParserVariable $var
	 * @return string
	 **/
	protected function generateVar(MustacheParserVariable $var)
	{
		$s = 'MustacheRuntime::lookUpVar($mustache_stack, ' . self::varToPhp($var) . ')';

		if($var->escape())
		{
			$s = 'htmlspecialchars(' . $s . ')';
		}

		return self::PHP_OPEN . 'echo ' . $s . ';' . self::PHP_CLOSE_AFTER_OUTPUT;
	}

	/**
	 * Returns PHP code that runs a chunk of mustache template code against the runtime-current stack.
	 * @param MustacheParserRuntimeTemplate $tpl
	 * @return string
	 **/
	protected function generateRuntimeTemplate(MustacheParserRuntimeTemplate $tpl)
	{
		// is this really adequate?
		$s = 'require_once ' . var_export(dirname(__FILE__) . '/MustacheInterpreter.php', true) . '; ';

		$s .= 'echo MustacheRuntime::doRuntimeTemplate($mustache_stack, ' . var_export($this->whitespace_mode, true) . ', ' .
			var_export($tpl->getName(), true) . ', ' . var_export($tpl->getPartials(), true) . ');';

		return self::PHP_OPEN . $s  . self::PHP_CLOSE;
	}

	/**
	 * Removes/compacts whitespace from PHP code.
	 * @param string $code
	 * @return string
	 **/
	protected static function stripPhpWhitespace($code)
	{
		$ret = '';
		$tokens = token_get_all($code);

		foreach($tokens as $token)
		{
			if(!is_array($token))
			{
				$ret .= $token;
			}
			else
			{
				switch($token[0])
				{
				case T_COMMENT:
				case T_DOC_COMMENT:
					// entirely remove comments
					break;
				case T_WHITESPACE:
					// compact whitespace to one space character
					$ret .= ' ';
					break;
				case T_CLOSE_TAG:
					// a T_CLOSE_TAG is often followed by an implicit new line
					$ret .= '?>';
					break;
				default:
					$ret .= $token[1];
				}
			}
		}

		return $ret;
	}
}


/**
 * Pull in parser classes...
 **/
require_once dirname(__FILE__) . '/MustacheParser.php';
