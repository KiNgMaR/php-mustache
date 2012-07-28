<?php

require_once dirname(__FILE__) . '/../lib/MustacheInterpreter.php';

include dirname(__FILE__) . '/example_data_shared.inc.php';

$mi = new MustacheInterpreter($parser);

echo $mi->run($data);
