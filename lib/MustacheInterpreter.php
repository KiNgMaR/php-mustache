<?php
/**
 * @package php-mustache
 * @subpackage interpreter
 * @author Ingmar Runge 2011 - https://github.com/KiNgMaR - BSD license
 **/


/**
 *
 * @package php-mustache
 * @subpackage interpreter
 **/
class MustacheInterpreter
{
	protected $tree;

	/**
	 * @param MustacheParser $parser Parser with the syntax tree.
	 **/
	public function __construct(MustacheParser $parser)
	{
		$this->tree = $parser->getTree();
	}

	/**
	 * Runs the previously assigned template tree against the view data in $view.
	 * @param object|array $view
	 * @return string Output, or false if $view is invalid.
	 **/
	public function run($view)
	{
		if(!is_array($view) && !is_object($view))
		{
			return false;
		}

		$mustache_stack = new MustacheRuntimeStack($view);

		$result = $this->runInternal($mustache_stack, $this->tree);

		return $result;
	}

	/**
	 * Runs a parser object against a stack. Not more than a switch based on the type of $obj.
	 * @param MustacheRuntimeStack $mustache_stack
	 * @param MustacheParserObject $obj
	 * @return string Output.
	 **/
	protected function runInternal(MustacheRuntimeStack $mustache_stack, MustacheParserObject $obj)
	{
		if($obj instanceof MustacheParserSection)
		{
			return $this->runSection($mustache_stack, $obj);
		}
		elseif($obj instanceof MustacheParserLiteral)
		{
			return $obj->getContents();
		}
		elseif($obj instanceof MustacheParserVariable)
		{
			return $this->runVar($mustache_stack, $obj);
		}
	}

	/**
	 * Runs a section, which could be the internal root section, an inverted section or a regular section.
	 * @param MustacheRuntimeStack $mustache_stack
	 * @param MustacheParserSection $section
	 * @return string Output.
	 **/
	protected function runSection(MustacheRuntimeStack $mustache_stack, MustacheParserSection $section)
	{
		$result = '';

		// outline:
		// if it's a root, or a falsey inverted section, do_run = true will cause a simple pass,
		// otherwise if the section is not falsey or iterable, all values from the section variable
		// will be put onto the stack and executed with one pass each.

		$is_root = ($section->getName() === '#ROOT#');

		$do_run = $is_root;

		// don't push the root context onto the stack, it's there already.

		if(!$is_root)
		{
			$secv = MustacheRuntime::lookUpVar($mustache_stack, $section->isDotNotation() ? $section->getNames() : $section->getName());

			if($section instanceof MustacheParserInvertedSection)
			{
				if(MustacheRuntime::sectionFalsey($secv))
				{
					$mustache_stack->push($secv);
					$do_run = true;
				}
			}
			elseif(MustacheRuntime::sectionIterable($secv))
			{
				foreach($secv as $v)
				{
					$mustache_stack->push($v);

					foreach($section as $child)
					{
						$result .= $this->runInternal($mustache_stack, $child);
					}

					$mustache_stack->pop();
				}
				// don't use $do_run here, it's either done already or falsey-
			}
		}

		if($do_run)
		{
			foreach($section as $child)
			{
				$result .= $this->runInternal($mustache_stack, $child);
			}

			$mustache_stack->pop();
		}

		return $result;
	}

	/**
	 * "Runs" a template variable from the stack, returns their contents ready for output.
	 * @param MustacheRuntimeStack $mustache_stack
	 * @param MustacheParserVariable $var
	 * @return string Output.
	 **/
	protected function runVar(MustacheRuntimeStack $mustache_stack, MustacheParserVariable $var)
	{
		$v = MustacheRuntime::lookUpVar($mustache_stack, $var->isDotNotation() ? $var->getNames() : $var->getName());

		if($var->escape())
		{
			return htmlspecialchars($v);
		}
		else
		{
			return $v;
		}
	}
}


/**
 * Pull in parser classes...
 **/
require_once dirname(__FILE__) . '/MustacheParser.php';
/**
 * Pull in run-time classes for various references.
 **/
require_once dirname(__FILE__) . '/MustacheRuntime.php';
