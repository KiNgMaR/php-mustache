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
	 * Defines the tag type that denotes a comment.
	 **/
	const COMMENT_TYPE = '!';
	/**
	 * Defines the tag type that denotes a partial.
	 **/
	const PARTIAL_TYPE = '>';

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
	 * Constant that denotes a comment tag token.
	 **/
	const TKN_COMMENT = 'COMMENT';
	/**
	 * Constant that denotes a partial tag token.
	 **/
	const TKN_PARTIAL = 'PARTIAL';
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

		// radically compact whitespace in the template:
		if($this->whitespace_mode == MUSTACHE_WHITESPACE_STRIP)
		{
			$this->template = preg_replace('~\s+~', ' ', $this->template);
		}

		// start tokenizing:
		$pos = strpos($this->template, $dlm_o);
		$prev_pos = 0;
		$line = 0;

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

			$new_token = NULL;
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
				$new_token = array('t' => self::TKN_SECTION_START, 'm' => $tag_contents[0], 'd' => trim(substr($tag_contents, 1)));
			}
			elseif(strpos(self::CLOSING_SECTION_TYPES, $tag_contents[0]) !== false)
			{
				$new_token = array('t' => self::TKN_SECTION_END, 'd' => trim(substr($tag_contents, 1)));
			}
			elseif(preg_match('~^=\s*(\S+)\s+(\S+)\s*=$~', $tag_contents, $match))
			{
				// delimiter change!
				$dlm_o = $match[1];
				$dlm_c = $match[2];
			}
			elseif($tag_contents[0] === self::COMMENT_TYPE)
			{
				$new_token = array('t' => self::TKN_COMMENT, 'd' => trim(substr($tag_contents, 1)));
			}
			elseif($tag_contents[0] === self::PARTIAL_TYPE)
			{
				$new_token = array('t' => self::TKN_PARTIAL, 'd' => trim(substr($tag_contents, 1)));
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
						$advance_extra += 1; // get rid of extra } from closing delimiter
					}
				}

				if(empty($tag_contents)) // re-check, may have changed
				{
					$skip = true;
				}
				elseif(strpos(self::TAG_TYPES, $tag_contents[0]) !== false)
				{
					$new_token = array('t' => $t, 'm' => $tag_contents[0], 'd' => trim(substr($tag_contents, 1)));
				}
				else
				{
					$new_token = array('t' => $t, 'd' => trim($tag_contents));
				}
			}

			// beautiful code is over, here comes the fugly whitespacing fixing mess!

			$standalone = NULL;
			$sa_indent = '';
			if($this->whitespace_mode == MUSTACHE_WHITESPACE_STRICT)
			{
				if(count($this->tokens) > 0)
				{
					$prev_token = &$this->tokens[count($this->tokens) - 1];
				}
				else
				{
					$prev_token = NULL;
				}

				// slowpoke is slow...
				$line_index = substr_count($this->template, "\n", 0, ($pos > 0 ? $pos : strlen($this->template)));

				// let's dissect this a bit:
				// condition A: there's no new token (=delimiter change, invalid stuff) or the new token is not a tag (so a section, partial, etc.)
				// condition B: this is the first token or at least not preceded by a different token on the same line, or only preceded by whitespace (in a literal)
				// condition C: there's nothing but a newline or the end of the template following this token
				$standalone = (!$new_token || ($new_token['t'] != self::TKN_TAG && $new_token['t'] != self::TKN_TAG_NOESCAPE)) &&
					($prev_token === NULL || ($prev_token['t'] !== self::TKN_LITERAL && $prev_token['line'] != $line_index) || (bool)preg_match('~(?:' . (count($this->tokens) == 1 ? '^|' : '') . '\r?\n)([\t ]*)$~D', $prev_token['d'], $match))
					&& (bool)preg_match('~^(\r?\n|$)~D', substr($this->template, $end_pos + $dlm_c_len + $advance_extra), $match2);

				if($standalone)
				{
					// capture indentation:
					$sa_indent = isset($match[1]) ? $match[1] : '';

					// remove it from the preceding literal token, if necessary:
					if(strlen($sa_indent) > 0 && $prev_token['t'] === self::TKN_LITERAL)
					{
						$prev_token['d'] = substr($prev_token['d'], 0, -strlen($sa_indent));
					}

					// skip trailing newline:
					$advance_extra += strlen($match2[1]);

					// store token properties:
					if($new_token)
					{
						$new_token['sa'] = true;
						$new_token['ind'] = $sa_indent;
					}
				}
			}
			else
			{
				unset($line_index);
			}

			// end of whitespace fixing mess.

			if($new_token)
			{
				if(isset($line_index)) $new_token['line'] = $line_index;
				$this->tokens[] = $new_token;
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
	 * Adds a partial with name $key and template contents $tpl.
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
			elseif($token['t'] == MustacheTokenizer::TKN_COMMENT)
			{
				// it's a comment, ignore it
			}
			elseif($token['t'] == MustacheTokenizer::TKN_PARTIAL)
			{
				// resolve partial
				if(isset($this->partials[$token['d']]))
				{
					if(is_string($this->this_partial_name) && !strcmp($this->this_partial_name, $token['d']))
					{
						// recursive partial
						$tag = new MustacheParserRuntimeTemplate($token['d'], $this->partials);

						if(isset($token['ind'])) $tag->setIndent($token['ind']);

						$parent->addChild($tag);
					}
					else
					{
						// resolve partials at "compile-time":
						$partial_parser = new self($this->partials[$token['d']]);
						$partial_parser->refPartials($token['d'], $this->partials);
						$partial_parser->parse();

						// :TODO: consider indentation

						foreach($partial_parser->getTree() as $partial_child)
						{
							$parent->addChild($partial_child);
						}

						unset($partial_parser);
					}
				}
			}
			elseif($token['t'] == MustacheTokenizer::TKN_TAG || $token['t'] == MustacheTokenizer::TKN_TAG_NOESCAPE)
			{
				$modifier = isset($token['m']) ? $token['m'] : '';

				if($modifier == '&' || $modifier == '')
				{
					// boring interpolation...
					$tag = new MustacheParserVariable($token['d'], ($token['t'] != MustacheTokenizer::TKN_TAG_NOESCAPE) xor $modifier == '&');

					if(isset($token['ind'])) $tag->setIndent($token['ind']);

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


/**
 * An object extracted by the parser. Used by code gens, interpreters and such.
 * Does not contain a lot of logic, mostly a data store with some utils.
 * The other parser object classes derive from this class.
 * @package php-mustache
 * @subpackage shared
 **/
abstract class MustacheParserObject
{
	/**
	 * Parent element. Not all derived objects use this.
	 * @var MustacheParserSection|NULL
	 **/
	protected $parent = NULL;
	/**
	 * Whitespace string that defines this object's indentation. Used by partials mostly.
	 * @var string
	 **/
	protected $indent = '';

	/**
	 * Constructor.
	 * @param MustacheParserSection $parent Really only used for sections so far.
	 **/
	public function __construct(MustacheParserSection $parent = NULL)
	{
		$this->parent = $parent;
	}

	/**
	 * @return MustacheParserSection|NULL
	 **/
	public function getParent()
	{
		return $this->parent;
	}

	/**
	 * Corrects or sets this object's parent element. Used by addChild in section objects.
	 * @see MustacheParserSection::addChild
	 **/
	public function _setParent(MustacheParserSection $new_parent)
	{
		$this->parent = $new_parent;
	}

	/**
	 * Sets the whitespace/indentation to store with this element.
	 * @param string $new Whitespace characters.
	 **/
	public function setIndent($new)
	{
		$this->indent = $new;
	}

	/**
	 * Returns the indent string, usually empty or a number of whitespace characters.
	 **/
	public function getIndent()
	{
		return $this->indent;
	}
}


/**
 * An object extracted by the parser, with an entity name. Provides helper methods
 * for dealing with dot-notation syntax.
 * @package php-mustache
 * @subpackage shared
 **/
abstract class MustacheParserObjectWithName extends MustacheParserObject
{
	/**
	 * "Variable" name, e.g. "view" or "object.description".
	 * @var string
	 **/
	protected $name;
	/**
	 * Dot-notation parts as an array.
	 * @var array
	 **/
	protected $dot_parts;

	/**
	 * Constructor.
	 * @param string $name
	 * @param MustacheParserSection|null $parent
	 **/
	public function __construct($name, MustacheParserSection $parent = NULL)
	{
		parent::__construct($parent);
		$this->name = $name;
		$this->dot_parts = ($this->name == '.' ? array('.') : explode('.', $name));
	}

	/**
	 * Returns whether this object's name makes use of dot-notation.
	 * @return boolean
	 **/
	public function isDotNotation()
	{
		return (count($this->dot_parts) > 1);
	}

	/**
	 * Returns this object's name.
	 * @return string
	 **/
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Returns this object's names, as an array. Useful with dot-notation.
	 * @return array
	 **/
	public function getNames()
	{
		return $this->dot_parts;
	}
}


/**
 * A section parser object.
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheParserSection extends MustacheParserObjectWithName implements Iterator
{
	/**
	 * @var array
	 **/
	protected $children = array();

	/**
	 * Constructor.
	 * @param string $name
	 * @param MustacheParserSection|null $parent
	 **/
	public function __construct($name, MustacheParserSection $parent = NULL)
	{
		parent::__construct($name, $parent);
		$this->name = $name;
	}

	/**
	 * Adds a child parser object to this section. Changes $child's parent to $this.
	 * @param MustacheParserObject $child
	 **/
	public function addChild(MustacheParserObject $child)
	{
		$child->_setParent($this);
		$this->children[] = $child;
	}

	// Iterator interface implementation:

	private $it_pos = 0;
	function rewind() { $this->it_pos = 0; }
	function current() { return $this->children[$this->it_pos]; }
	function key() { return $this->it_pos; }
	function next() { $this->it_pos++; }
	function valid() { return isset($this->children[$this->it_pos]); }
}


/**
 * An inverted section parser object. Exactly the same as MustacheParserSection,
 * however "$var isinstanceof MustacheParserInvertedSection" will be used to tell them apart.
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheParserInvertedSection extends MustacheParserSection
{

}


/**
 * Parser object that represents a literal string part of a template.
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheParserLiteral extends MustacheParserObject
{
	/**
	 * @var string
	 **/
	protected $contents;

	/**
	 * Constructor...
	 * @param string $contents
	 **/
	public function __construct($contents)
	{
		$this->contents = $contents;
	}

	/**
	 * Damn, this is a boring class.
	 * @return string
	 **/
	public function getContents()
	{
		return $this->contents;
	}
}

/**
 * This parser object represents a variable / {{interpolation}}.
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheParserVariable extends MustacheParserObjectWithName
{
	/**
	 * @var boolean
	 **/
	protected $escape;

	/**
	 * @param string name
	 * @param boolean escape
	 **/
	public function __construct($name, $escape)
	{
		parent::__construct($name);
		$this->escape = $escape;
	}

	/**
	 * (HTML)escape this variable's contents?
	 * @return boolean
	 **/
	public function escape()
	{
		return $this->escape;
	}
}


/**
 * Represents a piece of mustache template that *must* be evaluated at runtime.
 * Currently only used for recursive partials.
 * @package php-mustache
 * @subpackage shared
 **/
class MustacheParserRuntimeTemplate extends MustacheParserObject
{
	/**
	 * Partial's name
	 * @var string
	 **/
	protected $name;
	/**
	 * List of all partials, required since they could be used by the "main" partial or other partials.
	 * @var array
	 **/
	protected $partials;

	/**
	 * @var string $name
	 * @var array $partials
	 **/
	public function __construct($name, array $partials)
	{
		$this->name = $name;
		$this->partials = $partials;
	}

	/**
	 * Returns the template contents of this partial.
	 * @return string
	 **/
	public function lookupSelf()
	{
		return $this->partials[$this->name];
	}

	/**
	 * Returns a copy of the list of all partials.
	 * @return array
	 **/
	public function getPartials()
	{
		return $this->partials;
	}

	/**
	 * Returns this partial's name.
	 * @return string
	 **/
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
