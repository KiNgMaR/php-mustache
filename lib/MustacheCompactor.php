<?php
/**
 * @package php-mustache
 * @subpackage compiling
 * @author Ingmar Runge 2012 - https://github.com/KiNgMaR - BSD license
 **/


/**
 *
 * @package php-mustache
 * @subpackage auxillary
 **/
class MustacheCompactor
{
	/**
	 * Root section from MustacheParser's getTree().
	 * @var MustacheParserSection
	 **/
	protected $tree = NULL;
	/**
	 * @var string
	 **/
	protected $delimiter_opening = '{{';
	/**
	 * @var string
	 **/
	protected $delimiter_closing = '}}';
	/**
	 * @var string
	 **/
	protected $section_type = '#';
	/**
	 * @var string
	 **/
	protected $inverted_section_type = '^';
	/**
	 * @var string
	 **/
	protected $closing_section_type = '/';
	/**
	 * @var string
	 **/
	protected $partial_type = '>';
	/**
	 * @var string
	 **/
	protected $unescaped_var_type = '&';

	/**
	 * List of recursive partials - it's not possible to fully compact these.
	 * @var array
	 **/
	protected $runtime_templates = array();

	/**
	 * @param MustacheParser $parser Parser with the syntax tree.
	 **/
	public function __construct(MustacheParser $parser)
	{
		$this->tree = $parser->getTree();

		if($parser->getWhitespaceMode() !== MUSTACHE_WHITESPACE_STRIP)
		{
			// maybe raising an Exception would be more consistent?
			trigger_error('Using MustacheCompactor without having set MUSTACHE_WHITESPACE_STRIP does not make a lot of sense.', E_USER_NOTICE);
		}
	}

	/**
	 * @return string
	 **/
	public function generate()
	{
		return preg_replace('~\s+~', ' ',
			$this->generateInternal($this->tree));
	}

	/**
	 * @return bool
	 **/
	public function runtimeTemplatesFound()
	{
		return (count($this->runtime_templates) > 0);
	}

	/**
	 * @return array
	 **/
	public function getRuntimeTemplates()
	{
		return $this->runtime_templates;
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
			return $obj->getContents();
		}
		elseif($obj instanceof MustacheParserVariable)
		{
			return $this->delimiter_opening . (!$obj->escape() ? $this->unescaped_var_type : '') . self::varName($obj) . $this->delimiter_closing;
		}
		elseif($obj instanceof MustacheParserRuntimeTemplate)
		{
			return $this->generateRuntimeTemplate($obj);
		}
	}

	/**
	 * @param MustacheParserObjectWithName $var
	 * @return string
	 **/
	static protected function varName(MustacheParserObjectWithName $var)
	{
		return ($var->isDotNotation() ? implode('.', $var->getNames()) : $var->getName());
	}

	/**
	 * @param $MustacheParserSection $section
	 * @return string
	 **/
	protected function generateSection(MustacheParserSection $section)
	{
		$is_root = ($section->getName() === '#ROOT#');

		$s = '';

		if(!$is_root)
		{
			$s .= $this->delimiter_opening .
				($section instanceof MustacheParserInvertedSection ? $this->inverted_section_type : $this->section_type) .
				self::varName($section) . $this->delimiter_closing;
		}

		foreach($section as $child)
		{
			$s .= $this->generateInternal($child);
		}

		if(!$is_root)
		{
			$s .= $this->delimiter_opening . $this->closing_section_type .
				self::varName($section) . $this->delimiter_closing;
		}

		return $s;
	}

	/**
	 * @param MustacheParserRuntimeTemplate $tpl
	 * @return string
	 **/
	protected function generateRuntimeTemplate(MustacheParserRuntimeTemplate $tpl)
	{
		$this->runtime_templates[$tpl->getName()] = $tpl->lookupSelf();

		return $this->delimiter_opening . $this->partial_type . $tpl->getName() . $this->delimiter_closing;
	}
}


/**
 * Pull in parser classes...
 **/
require_once dirname(__FILE__) . '/MustacheParser.php';
