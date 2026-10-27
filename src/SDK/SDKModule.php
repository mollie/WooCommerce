<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Psr\Container\ContainerInterface;

class SDKModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        return [
            'SDK.api_helper' => static function (ContainerInterface $container): Api {
                $pluginVersion = $container->get('shared.plugin_version');
                $pluginId = $container->get('shared.plugin_id');
                return new Api($pluginVersion, $pluginId);
            },
            'SDK.HttpResponse' => static function (): HttpResponse {
                return new HttpResponse();
            },
            MollieApi::class => static function (ContainerInterface $container): MollieApi {
                $api = $container->get('SDK.api_helper');
                assert($api instanceof Api);
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof Settings);
                $log = $container->get(EventLog::class);
                assert($log instanceof EventLog);

                return new SdkMollieApi(
                    $api,
                    $settings,
                    (int) $container->get('express.config')['sessionLifetimeSeconds'],
                    $log
                );
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        return true;
    }
}
