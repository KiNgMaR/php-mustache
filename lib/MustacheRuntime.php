<?php
/**
 * @package php-mustache
 * @subpackage shared
 * @author Ingmar Runge 2011 - https://github.com/KiNgMaR - BSD license
 **/


/**
 *
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheRuntime
{
	/**
	 * Performs a variable lookup on the given stack. Returns the variable's contents.
	 * @param array $stack
	 * @param string|array $var_name
	 * @return mixed
	 **/
	public static function lookUpVar(MustacheRuntimeStack $stack, $var_name)
	{
		$item = NULL;

		if($var_name === '.')
		{
			// scalar value, hopefully.
			$item = $stack->top();
		}
		else
		{
			if(is_array($var_name)) // is this dot syntax?
			{
				// find first item on current stack level:
				$item = self::lookUpVarFlat($stack, array_shift($var_name));

				// while the current var_name resolves to a new view context,
				// walk that context with the next var_name part...
				while(count($var_name) > 0 && $item)
				{
					$item = self::lookUpVarInContext($item, array_shift($var_name));
				}
			}
			else
			{
				// no dot syntax, do a simple lookup.
				$item = self::lookUpVarFlat($stack, $var_name);
			}
		}

		return $item;
	}

	/**
	 * Performs a simple variable lookup against the given stack by walking it from
	 * top to bottom and checking for the existence of a member with the given name.
	 * @param MustacheRuntimeStack $stack
	 * @param string $var_name
	 * @return mixed Returns an empty string if there's no matching variable on any stack level.
	 **/
	protected static function lookUpVarFlat(MustacheRuntimeStack $stack, $var_name)
	{
		foreach($stack as $ctx) // top2bottom
		{
			$item = self::lookUpVarInContext($ctx, $var_name);

			if(!is_null($item))
			{
				return $item;
			}
		}

		return '';
	}

	/**
	 * Checks whether the stack member (context) $ctx contains an entity of the given name.
	 * Behaves accordingly to the specs when it comes to lists, objects, member functions and such.
	 * @param mixed $ctx
	 * @param string $var_name
	 * @return NULL|mixed
	 **/
	protected static function lookUpVarInContext($ctx, $var_name)
	{
		if(is_array($ctx) && isset($ctx[$var_name]))
		{
			return $ctx[$var_name];
		}
		elseif(is_object($ctx) && isset($ctx->$var_name))
		{
			return $ctx->$var_name;
		}
		// :TODO: check for callable members

		return NULL;
	}

	/**
	 * Returns true if $section_var (which should be something returned by lookUpVar)
	 * is iterable, may modify $section_var's contents to be iterable when it makes sense.
	 * @see lookUpVar
	 * @return boolean
	 **/
	public static function sectionIterable(&$section_var)
	{
		// $section_var contains a result from lookUpVar

		if(empty($section_var))
		{
			// falsey sections don't iterate yo
			return false;
		}
		elseif(is_array($section_var) || $section_var instanceof Iterator)
		{
			// easy peasy iterable
			return true;
		}
		elseif(is_scalar($section_var))
		{
			// according to the specs, treat scalars as one-item-lists.
			$section_var = array((string)$section_var);
			return true;
		}
		elseif(is_object($section_var))
		{
			// this must be pushed onto the context stack.
			$section_var = array($section_var);
			return true;
		}

		return false;
	}

	/**
	 * Returns whether $section_var qualifies as "falsey" for an (inverted or normal) section.
	 * @param mixed $section_var
	 * @return boolean
	 **/
	public static function sectionFalsey(&$section_var)
	{
		return empty($section_var);
	}

	/**
	 * Runtime-wrapper for partial evaluation.
	 * @see MustacheInterpreter::runOnStack
	 * @param MustacheRuntimeStack $mustache_stack
	 * @param int $whitespace_mode
	 * @param string $partial_name
	 * @param array $partials ('name' => 'tpl code'), must contain $partial_name.
	 * @return string
	 **/
	public static function doRuntimeTemplate(MustacheRuntimeStack $mustache_stack, $whitespace_mode, $partial_name, array $partials)
	{
		$parser = new MustacheParser($partials[$partial_name], $whitespace_mode);
		$parser->addPartials($partials);
		$parser->parse(); // don't care about exceptions, syntax should have been validated at compile-time, at least for recursive partials
		$mi = new MustacheInterpreter($parser);
		return $mi->runOnStack($mustache_stack);
	}
}


/**
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheRuntimeStack extends SplStack
{
	/**
	 * @param $view Bottom-most item of the stack.
	 **/
	public function __construct(&$view)
	{
		// MODE_LIFO for stack behaviour
		// MODE_KEEP for easier foreach() access, from top to bottom
		$this->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_KEEP);
		$this->push($view);
	}
}
