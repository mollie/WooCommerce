<?php

declare(strict_types=1);

// scoper.inc.php

use Isolated\Symfony\Component\Finder\Finder;

$wp_classes = json_decode(
    file_get_contents(
        __DIR__.'/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-classes.json'
    ),
    true
);
$wp_constants = json_decode(
    file_get_contents(
        __DIR__.'/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-constants.json'
    ),
    true
);
$wp_functions = json_decode(
    file_get_contents(
        __DIR__.'/vendor/sniccowp/php-scoper-wordpress-excludes/generated/exclude-wordpress-functions.json'
    ),
    true
);

$finders = [
    Finder::create()
        ->files()
        ->ignoreVCS(true)
        ->ignoreDotFiles(false) # We need to keep .distignore around
        ->exclude([
                      '.github',
                      '.ddev',
                      '.idea',
                      'modules.local',
                      'tests',
                  ])
        ->in('.'),
];

return [
    'prefix' => 'Syde\\Vendor', // string|null
    'finders' => $finders,      // list<Finder>
    'patchers' => [
        static function (string $filePath, string $prefix, string $content): string {
            //
            // PHP-Parser patch conditions for file targets
            //
            if ($filePath === 'src/Gateway/GatewayModule.php') {
                return preg_replace(
                    "%\$class = 'Mollie\\\\WooCommerce\\\\PaymentMethod\\\\' . \$transformedId;%",
                    '$class = \'' . $prefix . '\\\\Mollie\\\\WooCommerce\\\\PaymentMethod\\\\\' . $transformedId;',
                    $content
                );
            }

            return $content;
        },
    ], // list<callable(string $filePath, string $prefix, string $contents): string>
    'exclude-files' => [
        'vendor/symfony/polyfill-php80/Resources/stubs/Stringable.php',
        'inc/functions.php',
        'inc/utils.php',
        'inc/woocommerce.php'
    ], // list<string>
    'exclude-namespaces' => [
        'Composer',
        'Automattic',
        '^WooCommerce',
        'Inpsyde\Assets',
    ], // list<string|regex>
    'exclude-constants' => array_merge($wp_constants, [
        'WC_VERSION',
        'M4W_FILE',
        'M4W_PLUGIN_DIR',
        'M4W_PLUGIN_URL'
    ]), // list<string|regex>
    'exclude-classes' => array_merge($wp_classes, [
        'WooCommerce',
        '/^WC_/',
    ]),     // list<string|regex>
    'exclude-functions' => array_merge($wp_functions, [
        '/^wc/',
    ]), // list<string|regex>

    'expose-global-constants' => false,   // bool
    'expose-global-classes' => false,     // bool
    'expose-global-functions' => false,   // bool

    'expose-namespaces' => [], // list<string|regex>
    'expose-constants' => [],  // list<string|regex>
    'expose-classes' => [],    // list<string|regex>
    'expose-functions' => [],  // list<string|regex>
];
