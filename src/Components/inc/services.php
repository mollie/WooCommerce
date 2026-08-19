<?php

declare (strict_types=1);
namespace Mollie;

use Mollie\WooCommerce\Components\ComponentDataService;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\Psr\Container\ContainerInterface;
return static function (): array {
    return ['components.data_service' => static function (ContainerInterface $container): ComponentDataService {
        $settingsHelper = $container->get('settings.settings_helper');
        \assert($settingsHelper instanceof Settings);
        return new ComponentDataService($settingsHelper);
    }];
};
