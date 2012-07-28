<?php
/**
* @package php-mustache
* @subpackage examples
**/

/**
 * include codegen.
 **/
require_once dirname(__FILE__) . '/../lib/MustacheJavaScriptCodeGen.php';

/**
 * include shared example data.
 **/
include dirname(__FILE__) . '/example_data_shared.inc.php';

$codegen = new MustacheJavaScriptCodeGen($parser);

$code = $codegen->generate();

// you probably want to save this to a .js file or the like instead of echoing it:
echo $code . "\n";

// make sure to include the library code returned by
// MustacheJavaScriptCodeGen::getRuntimeCode()
// then just invoke the function(data){...} as returned by generate.
// Pass along the data object/array variable and receive the
// evaluated template+data results in return.
