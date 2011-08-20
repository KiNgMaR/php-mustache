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

	public function __construct(MustacheParser $parser)
	{
		$this->tree = $parser->getTree();
	}

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

	protected function runSection(MustacheRuntimeStack $mustache_stack, MustacheParserSection $section)
	{
		$result = '';

		$is_root = ($section->getName() === '#ROOT#');

		$do_run = $is_root;

		// don't push the root context onto the stack, it's there already.

		if(!$is_root)
		{
			$secv = MustacheRuntime::lookUpVar($mustache_stack, $section->isDotNotation() ? $section->getNames() : $section->getName());

			if($section instanceof MustacheParserInvertedSection && MustacheRuntime::sectionFalsey($secv))
			{
				$mustache_stack->push($secv);
				$do_run = true;
			}
			else
			{
				if(MustacheRuntime::sectionIterable($secv))
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
