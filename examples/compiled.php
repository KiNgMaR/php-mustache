<?php

require_once dirname(__FILE__) . '/../lib/MustachePhpCodeGen.php';

include dirname(__FILE__) . '/example_data_shared.inc.php';

$codegen = new MustachePHPCodeGen($parser);

// view is the name of the variable that the code expects to find its data
// in when run:
$code = $codegen->generate('view');

// you probably want to save this to your cached .tpl.php files or the like instead of echoing it:
echo $code . "\n";
