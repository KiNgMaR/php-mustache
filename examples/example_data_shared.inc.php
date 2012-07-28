<?php

// example data from: http://mustache.github.com/#demo

$template = <<<'EOT'
<h1>{{header}}</h1>
{{#bug}}
{{/bug}}

{{#items}}
  {{#first}}
    <li><strong>{{name}}</strong></li>
  {{/first}}
  {{#link}}
    <li><a href="{{url}}">{{name}}</a></li>
  {{/link}}
{{/items}}

{{#empty}}
  <p>The list is empty.</p>
{{/empty}}
EOT;

$data = new stdClass();
$data->header = 'Colors';
$data->items = array(
	(object)array('name' => 'red', 'first' => true, 'url' => '#Red'),
	(object)array('name' => 'green', 'link' => true, 'url' => '#Green'),
	(object)array('name' => 'blue', 'link' => true, 'url' => '#Blue')
);
$data->empty = (count($data->items) === 0);

$parser = new MustacheParser($template);

/* optionally add partials if you have 'em *

$parser->addPartials(array(
	'my_partial' => '<h2>{{subheader}}</h2>'
));

*/

try
{
	$parser->parse();
}
catch(MustacheParserException $ex)
{
	echo '[PARSER EXCEPTION] ' . $ex->getMessage();
	return false;
}
