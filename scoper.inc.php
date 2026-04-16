<?php

declare (strict_types=1);
namespace Mollie;

// scoper.inc.php
use Mollie\Isolated\Symfony\Component\Finder\Finder;
$wp_classes = \json_decode(\file_get_contents(__DIR__ . '/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-classes.json'), \true);
$wp_constants = \json_decode(\file_get_contents(__DIR__ . '/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-constants.json'), \true);
$wp_functions = \json_decode(\file_get_contents(__DIR__ . '/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-functions.json'), \true);
$finders = [Finder::create()->files()->ignoreVCS(\true)->ignoreDotFiles(\false)->exclude(['.github', '.ddev', '.idea', 'modules.local', 'tests', 'node_modules'])->in('.')];
return [
    'prefix' => 'Mollie',
    // string|null
    'finders' => $finders,
    // list<Finder>
    'patchers' => [],
    // list<callable(string $filePath, string $prefix, string $contents): string>
    'exclude-files' => ['vendor/symfony/polyfill-php80/Resources/stubs/Stringable.php', 'inc/functions.php', 'inc/utils.php', 'inc/woocommerce.php'],
    // list<string>
    'exclude-namespaces' => ['Composer', 'Automattic', '^WooCommerce', 'Mollie\Inpsyde\Assets', 'Mollie'],
    // list<string|regex>
    'exclude-constants' => \array_merge($wp_constants, ['WC_VERSION', 'M4W_FILE', 'M4W_PLUGIN_DIR', 'M4W_PLUGIN_URL']),
    // list<string|regex>
    'exclude-classes' => \array_merge($wp_classes, ['WooCommerce', '/^WC_/', '\WCS_Retry_Manager']),
    // list<string|regex>
    'exclude-functions' => \array_merge($wp_functions, ['/^wc/']),
    // list<string|regex>
    'expose-global-constants' => \false,
    // bool
    'expose-global-classes' => \false,
    // bool
    'expose-global-functions' => \false,
    // bool
    'expose-namespaces' => [],
    // list<string|regex>
    'expose-constants' => [],
    // list<string|regex>
    'expose-classes' => [],
    // list<string|regex>
    'expose-functions' => [],
];
