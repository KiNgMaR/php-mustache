This is a new mustache implementation in and for PHP.
mustache is a logic-less template language. You can find out more about it at:
http://mustache.github.com/

php-mustache consists of three main components:

- a mustache parser
- a mustache interpreter
- a mustache PHP code generator, dubbed as "compiling mustache".

It aims to be compliant with the official mustache specs, as found on
https://github.com/mustache/spec

php-mustache has the basics up and running, but there are still some things
left to implement, such as:

- better whitespace treatment
- recursive partials
- lambdas