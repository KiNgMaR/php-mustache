<?php
/**
 * @package php-mustache
 * @subpackage auxillary
 * @author Ingmar Runge 2012 - https://github.com/KiNgMaR - BSD license
 **/


/**
 *
 * @package php-mustache
 * @subpackage auxillary
 **/
class MustacheJavaScriptCodeGen
{
	/**
	 * Root section from MustacheParser's getTree().
	 * @var MustacheParserSection
	 **/
	protected $tree = NULL;
	/**
	 * @var int
	 **/
	protected $whitespace_mode;

	/**
	 * Note: for successful JS code generation, the template must be provided in UTF-8 encoding!
	 * @param MustacheParser $parser Parser with the syntax tree.
	 **/
	public function __construct(MustacheParser $parser)
	{
		$this->tree = $parser->getTree();
		$this->whitespace_mode = $parser->getWhitespaceMode();
	}

	/**
	 * Returns the runtime library JS code that is required when executing the
	 * function(){...} code returned by generate. Feel free to minimize the
	 * returned JS blob before deploying.
	 * @see generate
	 * @return string
	 **/
	public static function getRuntimeCode()
	{
		return <<<'EOJS'

/**
 * Runtime class for mustache templates 'compiled' by php-mustache.
 * Feel free to minimize this when deploying.
 *
 * Using one-letter method names to help keep the generated code small.
 **/
(function() {

	// static data:
	var _charsToEscape = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;'
	};

	// XML escaping helper:
	function xmlEscape(str)
	{
		str = new String(!str ? '' : str);
		// fastest method according to http://jsperf.com/encode-html-entities
		return str.replace(/[&<>"]/g, function(ch) { return _charsToEscape[ch]; });
	};

	// is_array helper function:
	var is_array = Array.isArray || function(a) {
		return Object.prototype.toString.call(a) === '[object Array]';
	};

	// lookup business internal static functions:
	function look_up_var_flat(stack, var_name)
	{
		// walk stack from top to bottom:
		for(var i = stack.length - 1; i >= 0; i--)
		{
			var item = look_up_var_in_context(stack[i], var_name);

			if(item !== null)
			{
				return item;
			}
		}

		return '';
	}

	function look_up_var_in_context(ctx, var_name)
	{
		if(typeof ctx === 'object' && typeof ctx[var_name] !== 'undefined')
		{
			// :TODO: consider adding support for callable members
			return ctx[var_name];
		}

		return null;
	}

	function is_section_falsey(secv)
	{
		if(secv === undefined || secv === null || secv === 0 || secv === false || secv === '')
			return true;
		else if(is_array(secv) && secv.length === 0)
			return true;

		return !secv;
	}

	// main runtime class:
	MustacheRuntime = function(data)
	{
		this.stack = [ data ];
		// using an array to avoid memory reallocations as the buffer grows:
		this.buf = [];
	};

	MustacheRuntime.prototype = {
		// escape
		e: xmlEscape,

		// literal
		l: function(s)
		{
			this.buf.push(s);
		},

		// variable
		v: function(var_name, no_esc)
		{
			var val = this._look_up_var(var_name);
			if(no_esc !== 1)
				this.buf.push(xmlEscape(val));
			else
				this.buf.push(val);
		},

		// section:
		s: function(is_inverted, var_name, core)
		{
			var secv = this._look_up_var(var_name);

			var falsey = is_section_falsey(secv);

			if(is_inverted === 1 || falsey)
			{
				if(is_inverted === 1 && falsey)
				{
					this.stack.push(secv);
					core();
					this.stack.pop();
				}

				return;
			}

			// it's a regular section.

			if(!is_array(secv))
			{
				// wrap scalars and objects...
				secv = [ secv ];
			}

			for(var i in secv)
			{
				this.stack.push(secv[i]);
				core();
				this.stack.pop();
			}
		},

		_look_up_var: function(var_name)
		{
			var item = null;

			if(var_name === '.')
			{
				item = this.stack[this.stack.length - 1];
			}
			else
			{
				if(is_array(var_name)) // is this dot syntax?
				{
					item = look_up_var_flat(this.stack, var_name.shift());

					while(var_name.length > 0 && !!item)
					{
						item = look_up_var_in_context(item, var_name.shift());
					}
				}
				else
				{
					item = look_up_var_flat(this.stack, var_name);
				}
			}

			return item;
		},

		_buffer: function(s)
		{
			this.buf.push(s);
		},

		get: function()
		{
			return this.buf.join('');
		},
	};

})(); // end of wrapper to keep scope private

EOJS;
	}

	/**
	 * Returns JavaScript code that yields equal results as the provided template.
	 * Its structure looks like "function(data){...}" where data is the data variable
	 * that is to be used while executing the template. The returned code can only
	 * run successfully if the library provided by getRuntimeCode() is present.
	 * @see getRuntimeCode
	 * @return string Returns false if there's no parser tree or no data variable.
	 **/
	public function generate()
	{
		// arguments:
		// - context (that is data)
		$js = 'function(_c){var r=new MustacheRuntime(_c);';

		// start code generation:
		$js .= $this->generateInternal($this->tree);

		$js .= 'return r.get()}';

		return $js;
	}

	/**
	 * Escapes whitespace and friends, then returns the given string with quotes around it so it can be used in JS.
	 * @param string $str
	 * @return string
	 **/
	protected static function quoteLiteral($str)
	{
		return json_encode($str);
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
			$str = $obj->getContents();

			if($this->whitespace_mode == MUSTACHE_WHITESPACE_STRIP)
			{
				$str = preg_replace('~\s+~', ' ', $str);
			}

			return 'r.l(' . self::quoteLiteral($str) . ');';
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
	 * Returns an intermediate JS code representation of $var - important for dot syntax.
	 * @param MustacheParserObjectWithName $var
	 * @return string
	 **/
	static protected function varToJs(MustacheParserObjectWithName $var)
	{
		if($var->isDotNotation())
			return json_encode($var->getNames());
		else
			return self::quoteLiteral($var->getName());
	}

	/**
	 * Generates JS code that runs a section.
	 * @param $MustacheParserSection $section
	 * @return string
	 **/
	protected function generateSection(MustacheParserSection $section)
	{
		$is_root = ($section->getName() === '#ROOT#');
		$inverted_section = ($section instanceof MustacheParserInvertedSection);

		$s = '';

		if(!$is_root)
		{
			$s .= 'r.s(' . ($inverted_section ? '1' : '0') . ',' . self::varToJs($section) .
				',function(){';
		}

		foreach($section as $child)
		{
			$s .= $this->generateInternal($child);
		}

		if(!$is_root)
		{
			$s .= '});';
		}

		return $s;
	}

	/**
	 * Generates JS code that looks up and inserts variable contents.
	 * @param MustacheParserVariable $var
	 * @return string
	 **/
	protected function generateVar(MustacheParserVariable $var)
	{
		return 'r.v(' . self::varToJs($var) . ($var->escape() ? '' : ',1') . ');';
	}

	/**
	 *
	 * @param MustacheParserRuntimeTemplate $tpl
	 * @return string
	 **/
	protected function generateRuntimeTemplate(MustacheParserRuntimeTemplate $tpl)
	{
		// :TODO:
	}
}


/**
 * Pull in parser classes...
 **/
require_once dirname(__FILE__) . '/MustacheParser.php';
