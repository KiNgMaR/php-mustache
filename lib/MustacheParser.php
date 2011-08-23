<?php
/**
 * @package php-mustache
 * @subpackage shared
 * @author Ingmar Runge 2011 - https://github.com/KiNgMaR - BSD license
 **/


/**
 * Mustache whitespace handling: Don't spend extra CPU cycles on trying to be 100% conforming to the specs. This is the default mode.
 **/
define('MUSTACHE_WHITESPACE_LAZY', 1);
/**
 * Mustache whitespace handling: Try to be 100% conforming to the specs.
 **/
define('MUSTACHE_WHITESPACE_STRICT', 2);
/**
 * Mustache whitespace handling: Compact output, compact all superflous whitespace.
 **/
define('MUSTACHE_WHITESPACE_STRIP', 4);


/**
 * Very simple, but hopefully effective tokenizer for Mustache templates.
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheTokenizer
{
	/**
	 * Default opening delimiter.
	 **/
	const DEFAULT_DELIMITER_OPEN = '{{';
	/**
	 * Default closing delimiter.
	 **/
	const DEFAULT_DELIMITER_CLOSE = '}}';

	/**
	 * List of special characters that denote a section.
	 **/
	const SECTION_TYPES = '^#';
	/**
	 * List of characters that denote the end of a section.
	 **/
	const CLOSING_SECTION_TYPES = '/';
	/**
	 * List of prefix characters that are specific to tags.
	 * The difference between tags and sections is that tags can not contain
	 * other tags and do not have a closing counter-part.
	 **/
	const TAG_TYPES = '!>&';

	/**
	 * Constant that denotes a literal token.
	 **/
	const TKN_LITERAL = 'LITERAL';
	/**
	 * Constant that denotes a section start token.
	 **/
	const TKN_SECTION_START = 'SECTION_START';
	/**
	 * Constant that denotes a section end token.
	 **/
	const TKN_SECTION_END = 'SECTION_END';
	/**
	 * Constant that denotes a tag token.
	 **/
	const TKN_TAG = 'TAG';
	/**
	 * Constant that denotes a tag token with escaping disabled.
	 **/
	const TKN_TAG_NOESCAPE = 'TAG_NOESCAPE';

	/**
	 * Template string.
	 * @var string
	 **/
	protected $template = '';

	/**
	 * List of extracted tokens.
	 * Example entry: array('t' => one of the TKN_ consts[, 'm' => modifier character from _TYPES], 'd' => data/contents)
	 * @var array
	 **/
	protected $tokens = array();
	/**
	 * @var int
	 **/
	protected $whitespace_mode;

	/**
	 * @param string $template
	 **/
	public function __construct($template, $whitespace_mode = MUSTACHE_WHITESPACE_LAZY)
	{
		if(is_string($template))
		{
			$this->template = $template;
			$this->whitespace_mode = $whitespace_mode;
		}
	}

	/**
	 * This tokenizer basically ignores invalid syntax (thereby keeping it in the template output as literals).
	 * @return boolean
	 **/
	public function tokenize()
	{
		$dlm_o = self::DEFAULT_DELIMITER_OPEN;
		$dlm_c = self::DEFAULT_DELIMITER_CLOSE;

		$pos = strpos($this->template, $dlm_o);
		$prev_pos = 0;

		while($pos !== false)
		{
			$end_pos = strpos($this->template, $dlm_c, $pos + strlen($dlm_o));

			if($end_pos === false)
			{
				break;
			}

			if($pos > $prev_pos)
			{
				$this->tokens[] = array('t' => self::TKN_LITERAL, 'd' => substr($this->template, $prev_pos, $pos - $prev_pos));
			}

			$skip = false;
			$advance_extra = 0;

			$tag_contents = substr($this->template, $pos + strlen($dlm_o), $end_pos - $pos - strlen($dlm_o));

			// save this in case the modifiers changes:
			$dlm_c_len = strlen($dlm_c);

			if(empty($tag_contents))
			{
				$skip = true;
			}
			elseif(strpos(self::SECTION_TYPES, $tag_contents[0]) !== false)
			{
				// t for token, m for modifier, d for data
				$this->tokens[] = array('t' => self::TKN_SECTION_START, 'm' => $tag_contents[0], 'd' => trim(substr($tag_contents, 1)));
			}
			elseif(strpos(self::CLOSING_SECTION_TYPES, $tag_contents[0]) !== false)
			{
				$this->tokens[] = array('t' => self::TKN_SECTION_END, 'd' => trim(substr($tag_contents, 1)));
			}
			elseif(preg_match('~^=\s*(\S+)\s+(\S+)\s*=$~', $tag_contents, $match))
			{
				// delimiter change!
				$dlm_o = $match[1];
				$dlm_c = $match[2];
			}
			else
			{
				$t = self::TKN_TAG;

				// support {{{ / }}} for not-to-be-escaped tags
				if($dlm_o == self::DEFAULT_DELIMITER_OPEN && $tag_contents[0] == substr(self::DEFAULT_DELIMITER_OPEN, -1))
				{
					if(substr($this->template, $end_pos, $dlm_c_len + 1) == $dlm_c . substr(self::DEFAULT_DELIMITER_CLOSE, -1))
					{
						$tag_contents = substr($tag_contents, 1);
						$t = self::TKN_TAG_NOESCAPE;
						$advance_extra = 1; // get rid of extra } from closing delimiter
					}
				}

				if(empty($tag_contents)) // re-check, may have changed
				{
					$skip = true;
				}
				elseif(strpos(self::TAG_TYPES, $tag_contents[0]) !== false)
				{
					$this->tokens[] = array('t' => $t, 'm' => $tag_contents[0], 'd' => trim(substr($tag_contents, 1)));
				}
				else
				{
					$this->tokens[] = array('t' => $t, 'd' => trim($tag_contents));
				}
			}

			if(!$skip)
			{
				$prev_pos = $end_pos + $dlm_c_len + $advance_extra;
			}

			// find next opening delimiter:
			$pos = strpos($this->template, $dlm_o, $end_pos + $dlm_c_len + $advance_extra);
		}

		// append remainder (literal following the last section or tag), if there's any:
		if($prev_pos < strlen($this->template))
		{
			$this->tokens[] = array('t' => self::TKN_LITERAL, 'd' => substr($this->template, $prev_pos));
		}

		return true;
	}

	/**
	 * Use this method to retrieve the results from tokenize().
	 * @return array
	 **/
	public function getTokens()
	{
		return $this->tokens;
	}
}


/**
 * Mustache parser.
 *
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheParser
{
	/**
	 * @var array
	 **/
	protected $tokens;
	/**
	 * @var MustacheParserSection
	 **/
	protected $tree = NULL;
	/**
	 * @var array
	 **/
	protected $partials = array();
	/**
	 * If this is a partial, its name is stored here.
	 * @var string
	 **/
	protected $this_partial_name = NULL;
	/**
	 * @var int
	 * @see MUSTACHE_WHITESPACE_LAZY
	 * @see MUSTACHE_WHITESPACE_STRICT
	 * @see MUSTACHE_WHITESPACE_STRIP
	 **/
	protected $whitespace_mode;

	/**
	 * @param string $template
	 **/
	public function __construct($template, $whitespace_mode = MUSTACHE_WHITESPACE_LAZY)
	{
		if(!is_string($template))
		{
			throw new MustacheParserException(__CLASS__ . '\'s constructor expects a template string, ' . gettype($template) . ' given.');
		}

		$this->whitespace_mode = $whitespace_mode;

		$tokenizer = new MustacheTokenizer($template, $whitespace_mode);

		if(!$tokenizer->tokenize())
		{
			throw new MustacheParserException('The tokenizer failed miserably, please check your template syntax.');
		}

		$this->tokens = $tokenizer->getTokens();
	}

	/**
	 * @return int
	 **/
	public function getWhitespaceMode()
	{
		return $this->whitespace_mode;
	}

	/**
	 * Adds a partial with name $key and template contentens $tpl.
	 * @param string $key
	 * @param string $tpl
	 **/
	public function addPartial($key, $tpl)
	{
		if(is_scalar($key) && is_string($tpl))
		{
			$this->partials[(string)$key] = $tpl;
		}
	}

	/**
	 * Adds multiple partials.
	 * @see addPartial
	 * @param array|object $partials
	 **/
	public function addPartials($partials)
	{
		if(is_array($partials) || $partials instanceof Iterator)
		{
			foreach($partials as $key => $tpl)
			{
				$this->addPartial($key, $tpl);
			}
		}
	}

	/**
	 * Empties the list of added partials.
	 **/
	public function clearPartials()
	{
		$this->partials = array();
	}

	/**
	 * References all partials from $partials, usually from another MustacheParser instance.
	 * @param array& $partials
	 **/
	protected function refPartials($this_partial_name, array& $partials)
	{
		$this->this_partial_name = $this_partial_name;
		$this->partials = &$partials;
	}

	/**
	 * @throw MustacheParserException
	 **/
	public function parse()
	{
		$open_sections = array();

		// use a container section for the entire template:
		$root = new MustacheParserSection('#ROOT#');

		// walk tokens, simultanously checking for invalidities (will throw)
		// and adding stuff into a tree under $root:
		$parent = $root;
		foreach($this->tokens as $token)
		{
			if($token['t'] == MustacheTokenizer::TKN_LITERAL)
			{
				if(stripos($token['d'], '<?php') !== false)
				{
					throw new MustacheParserException('Found PHP code start tag in literal!');
				}

				$parent->addChild(new MustacheParserLiteral($token['d']));
			}
			elseif($token['t'] == MustacheTokenizer::TKN_SECTION_START)
			{
				if($token['m'] == '#')
				{
					$new = new MustacheParserSection($token['d'], $parent);
				}
				elseif($token['m'] == '^')
				{
					$new = new MustacheParserInvertedSection($token['d'], $parent);
				}
				else
				{
					throw new MustacheParserException('Unknown section type \'' . $token['m'] . '\'.');
				}

				$parent->addChild($new);

				$open_sections[] = $new;
				$parent = $new; // descend
			}
			elseif($token['t'] == MustacheTokenizer::TKN_SECTION_END)
			{
				$top_sect = array_pop($open_sections);

				if($token['d'] != $top_sect->getName())
				{
					throw new MustacheParserException('Found end tag for section \'' . $token['d'] . '\' which is not open.');
				}

				$parent = $top_sect->getParent(); // restore parent
			}
			elseif($token['t'] == MustacheTokenizer::TKN_TAG || $token['t'] == MustacheTokenizer::TKN_TAG_NOESCAPE)
			{
				$modifier = isset($token['m']) ? $token['m'] : '';
				if($modifier == '!')
				{
					// it's a comment, ignore it
				}
				elseif($modifier == '>')
				{
					// resolve partial
					if(isset($this->partials[$token['d']]))
					{
						if(is_string($this->this_partial_name) && !strcmp($this->this_partial_name, $token['d']))
						{
							// recursive partial
							$tag = new MustacheParserRuntimeTemplate($token['d'], $this->partials);

							$parent->addChild($tag);
						}
						else
						{
							// resolve partials at "compile-time":
							$partial_parser = new self($this->partials[$token['d']]);
							$partial_parser->refPartials($token['d'], $this->partials);
							$partial_parser->parse();

							foreach($partial_parser->getTree() as $partial_child)
							{
								$parent->addChild($partial_child);
							}

							unset($partial_parser);
						}
					}
				}
				elseif($modifier == '&' || $modifier == '')
				{
					// boring interpolation...
					$tag = new MustacheParserVariable($token['d'], ($token['t'] != MustacheTokenizer::TKN_TAG_NOESCAPE) xor $modifier == '&');

					$parent->addChild($tag);
				}
				else
				{
					throw new MustacheParserException('Unknown tag type \'' . $modifier . '\'.');
				}
			}
		} // end of $token loop

		if(count($open_sections) > 0)
		{
			throw new MustacheParserException('Found unclosed section tag pairs.');
		}

		$this->tree = $root;

		return true;
	}

	/**
	 * Returns the tree formed by parse(), encapsulated in a root MustacheParserSection of name #ROOT#.
	 * @see parse
	 * @return MustacheParserSection
	 **/
	public function getTree()
	{
		return $this->tree;
	}
}


abstract class MustacheParserObject
{
	protected $parent = NULL;

	public function __construct(MustacheParserSection $parent = NULL)
	{
		$this->parent = $parent;
	}

	public function getParent()
	{
		return $this->parent;
	}

	public function _setParent(MustacheParserSection $new_parent)
	{
		$this->parent = $new_parent;
	}
}


abstract class MustacheParserObjectWithName extends MustacheParserObject
{
	protected $name;
	protected $dot_parts;

	public function __construct($name, MustacheParserSection $parent = NULL)
	{
		parent::__construct($parent);
		$this->name = $name;
		$this->dot_parts = ($this->name == '.' ? array('.') : explode('.', $name));
	}

	public function isDotNotation()
	{
		return (count($this->dot_parts) > 1);
	}

	public function getName()
	{
		return $this->name;
	}

	public function getNames()
	{
		return $this->dot_parts;
	}
}


class MustacheParserSection extends MustacheParserObjectWithName implements Iterator
{
	protected $children = array();

	public function __construct($name, MustacheParserSection $parent = NULL)
	{
		parent::__construct($name, $parent);
		$this->name = $name;
	}

	public function addChild(MustacheParserObject $child)
	{
		$child->_setParent($this);
		$this->children[] = $child;
	}

	private $it_pos = 0;
	function rewind() { $this->it_pos = 0; }
	function current() { return $this->children[$this->it_pos]; }
	function key() { return $this->it_pos; }
	function next() { $this->it_pos++; }
	function valid() { return isset($this->children[$this->it_pos]); }
}


class MustacheParserInvertedSection extends MustacheParserSection
{

}


class MustacheParserLiteral extends MustacheParserObject
{
	protected $contents;

	public function __construct($contents)
	{
		$this->contents = $contents;
	}

	public function getContents()
	{
		return $this->contents;
	}
}


class MustacheParserVariable extends MustacheParserObjectWithName
{
	protected $escape;

	public function __construct($name, $escape)
	{
		parent::__construct($name);
		$this->escape = $escape;
	}

	public function escape()
	{
		return $this->escape;
	}
}


class MustacheParserRuntimeTemplate extends MustacheParserObject
{
	protected $name;
	protected $partials;

	public function __construct($name, array $partials)
	{
		$this->name = $name;
		$this->partials = $partials;
	}

	public function lookupSelf()
	{
		return $this->partials[$this->name];
	}

	public function getPartials()
	{
		return $this->partials;
	}

	public function getName()
	{
		return $this->name;
	}
}


/**
 * Mustache parser exception class.
 **/
class MustacheParserException extends Exception
{

}
