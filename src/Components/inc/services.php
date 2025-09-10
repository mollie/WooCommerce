<?php

declare(strict_types=1);

use Mollie\WooCommerce\Components\ComponentDataService;
use Mollie\WooCommerce\Settings\Settings;
use Psr\Container\ContainerInterface;

return static function (): array {
    return [
        'components.data_service' => static function (ContainerInterface $container): ComponentDataService {
            $settingsHelper = $container->get('settings.settings_helper');
            assert($settingsHelper instanceof Settings);

            return new ComponentDataService($settingsHelper);
        },
    ];
};
