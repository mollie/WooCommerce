<?php

declare (strict_types=1);
namespace Mollie;

use Mollie\PhpParser\ParserFactory;
use Mollie\Snicco\PhpScoperExcludes\Option;
return [
    Option::EMULATE_PHP_VERSION => Option::PHP_8_0,
    // use the current working directory
    Option::OUTPUT_DIR => __DIR__ . '/generated',
    // pass files as command arguments
    Option::FILES => [__DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php', __DIR__ . '/vendor/php-stubs/wordpress-globals/wordpress-globals.php'],
    Option::PREFER_PHP_VERSION => ParserFactory::PREFER_PHP7,
];
