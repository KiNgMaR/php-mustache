<?php
/**
* @package php-mustache
* @subpackage examples
**/

/**
 * include interpreter.
 **/
require_once dirname(__FILE__) . '/../lib/MustacheInterpreter.php';

/**
 * include shared example data.
 **/
include dirname(__FILE__) . '/example_data_shared.inc.php';

$mi = new MustacheInterpreter($parser);

echo $mi->run($data);
