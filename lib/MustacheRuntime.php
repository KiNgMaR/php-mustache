<?php
/**
 * @package CompilingMustache
 * @author Ingmar Runge 2011 - https://github.com/KiNgMaR - BSD license
 **/


/**
 *
 * @package CompilingMustache
 **/
class MustacheRuntime
{
	/**
	 * @param array $stack
	 * @param string|array $var_name
	 **/
	public static function lookUpVar(SplStack $stack, $var_name)
	{
		$item = NULL;

		if($var_name === '.')
		{
			$item = $stack->top();
		}
		else
		{
			if(is_array($var_name))
			{
				$item = self::lookUpVarFlat($stack, array_shift($var_name));

				while(count($var_name) > 0)
				{
					$item = self::lookUpVarInContext($item, array_shift($var_name));
				}
			}
			else
			{
				$item = self::lookUpVarFlat($stack, $var_name);
			}
		}

		return $item;
	}

	protected static function lookUpVarFlat(SplStack $stack, $var_name)
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

		return NULL;
	}

	public static function sectionIterable(&$section_var)
	{
		// $section_var contains a result from lookUpVar

		if(empty($section_var))
		{
			return false;
		}
		elseif(is_array($section_var) || $section_var instanceof Iterator)
		{
			return true;
		}
		elseif(is_scalar($section_var))
		{
			$section_var = array((string)$section_var);
			return true;
		}
		elseif(is_object($section_var))
		{
			$section_var = array($section_var);
			return true;
		}

		return false;
	}

	public static function sectionFalsey(&$section_var)
	{
		return empty($section_var);
	}
}


class MustacheRuntimeStack extends SplStack
{
	public function __construct(&$view)
	{
		$this->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_KEEP);
		$this->push($view);
	}
}
