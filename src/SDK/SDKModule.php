<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\SDK;

use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\Psr\Container\ContainerInterface;
class SDKModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        return ['SDK.api_helper' => static function (ContainerInterface $container): \Mollie\WooCommerce\SDK\Api {
            $pluginVersion = $container->get('shared.plugin_version');
            $pluginId = $container->get('shared.plugin_id');
            return new \Mollie\WooCommerce\SDK\Api($pluginVersion, $pluginId);
        }, 'SDK.HttpResponse' => static function (): HttpResponse {
            return new HttpResponse();
        }, \Mollie\WooCommerce\SDK\MollieApi::class => static function (ContainerInterface $container): \Mollie\WooCommerce\SDK\MollieApi {
            $api = $container->get('SDK.api_helper');
            assert($api instanceof \Mollie\WooCommerce\SDK\Api);
            $settings = $container->get('settings.settings_helper');
            assert($settings instanceof Settings);
            $log = $container->get(EventLog::class);
            assert($log instanceof EventLog);
            return new \Mollie\WooCommerce\SDK\SdkMollieApi($api, $settings, (int) $container->get('express.config')['sessionLifetimeSeconds'], $log);
        }];
    }
    public function run(ContainerInterface $container): bool
    {
        return \true;
    }
}
