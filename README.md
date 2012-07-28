This is a new mustache implementation in and for PHP.
mustache is a logic-less template language. You can find out more about it at:
http://mustache.github.com/

php-mustache consists of the following components:

- a mustache tokenizer and parser
- a mustache interpreter
- a mustache PHP code generator, dubbed "compiling mustache".
- a mustache JavaScript code generator, written in PHP

It aims to be compliant with the official mustache specs, as found on
https://github.com/mustache/spec

php-mustache is sufficiently mature and robust, however these are some things that are currently not implemented:

- lambdas [ no plans to add them currently ]
- calling methods on data objects (only properties are being read) [ should be an easy patch ]
- the JavaScript code generator does not support recursive partials yet
- there are some failing tests [ all related to whitespace handling only! ]

It is strongly adviced to use UTF-8 encoded templates and data only.

## Examples

### mustache interpreted at runtime

```php
<?php
require_once 'lib/MustacheInterpreter.php';
$parser = new MustacheParser('Hello {{name}}!');
$parser->parse();
$mi = new MustacheInterpreter($parser);
$data = (object)array('name' => 'John Wayne');
echo $mi->run($data);
```

### mustache 'compiled' to PHP code

```php
<?php
require_once 'lib/MustachePhpCodeGen.php';
$parser = new MustacheParser('Hello {{name}}!');
$parser->parse();
$codegen = new MustachePHPCodeGen($parser);
// view is the name of the variable that the code expects to find its data
// in when run:
$code = $codegen->generate('view');
// you probably want to save this to your cached .tpl.php files or the like instead of echoing it:
echo $code . "\n";
```

### mustache 'compiled' to JavaScript code

```php
<?php
require_once 'lib/MustacheJavaScriptCodeGen.php';
$parser = new MustacheParser('Hello {{name}}!');
$parser->parse();
$codegen = new MustacheJavaScriptCodeGen($parser);
$code = $codegen->generate();
// you probably want to save this to a .js file or the like instead of echoing it:
echo $code . "\n";
// make sure to include the library code returned by
// MustacheJavaScriptCodeGen::getRuntimeCode()
// then just invoke the function(data){...} as returned by generate.
// Pass along the data object/array variable.
```

## MustacheParser public API

```php
<?php
/**
 * Mustache whitespace handling: Don't spend extra CPU cycles on trying to be 100% conforming to the specs.
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
 * @param string $template
 **/
public function __construct($template, $whitespace_mode = MUSTACHE_WHITESPACE_LAZY);

/**
 * @return int
 **/
public function getWhitespaceMode();

/**
 * Adds a partial with name $key and template contentens $tpl.
 * @param string $key
 * @param string $tpl
 **/
public function addPartial($key, $tpl);

/**
 * Adds multiple partials.
 * @see addPartial
 * @param array|object $partials
 **/
public function addPartials($partials);

/**
 * Empties the list of added partials.
 **/
public function clearPartials();

/**
 * @throw Exception
 **/
public function parse();
```

You can have a look at the [test suite invocation](https://github.com/KiNgMaR/php-mustache/blob/master/test/MustacheSpecsTests.php) for another example.
