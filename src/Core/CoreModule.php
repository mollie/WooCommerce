<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Core;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Mollie\Api\CompatibilityChecker;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Utils\Data;
use Mollie\WooCommerce\Utils\Status;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

class CoreModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public const PLUGIN_ID = 'mollie-payments-for-woocommerce';

    public function services(): array
    {
        return [
            'core.plugin_id' => function (): string {
                //Get plugin legacy id
                return 'mollie-payments-for-woocommerce';
            },
            'core.plugin_version' => function (): string {
                //Get plugin version TODO handle with properties
                return '6.7.0';
            },
            'core.plugin_title' => function (): string {
                //Get plugin version TODO handle with properties
                return 'Mollie Payments for WooCommerce';
            },
            'core.plugin_file' => function (): string {
                //Get location of main plugin file TODO handle with properties
                return plugin_basename(self::PLUGIN_ID . '/' . self::PLUGIN_ID . '.php');
            },
            'core.plugin_url' => function (ContainerInterface $container): string {
                $pluginProperties = $container->get(Package::PROPERTIES);

                return $pluginProperties->baseUrl();
            },
            'core.plugin_path' => function (ContainerInterface $container): string {

                $pluginProperties = $container->get(Package::PROPERTIES);

                return $pluginProperties->basePath();
            },
            'core.api_helper' => function (ContainerInterface $container): Api {
                $pluginVersion = $container->get('core.plugin_version');
                $pluginId = $container->get('core.plugin_id');
                return new Api($pluginVersion, $pluginId);
            },
            'core.data_helper' => function (ContainerInterface $container): Data {
                /** @var Api $apiHelper */
                $apiHelper = $container->get('core.api_helper');
                $logger = $container->get(Logger::class);
                $pluginId = $container->get('core.plugin_id');
                $settings = $container->get('settings.settings_helper');
                return new Data($apiHelper, $logger, $pluginId, $settings);
            },
            'core.status_helper' => function (ContainerInterface $container): Status {
                $pluginTitle = $container->get('core.plugin_title');
                return new Status(new CompatibilityChecker(), $pluginTitle);
            },
            'core.set_http_response_code' => function ($status_code): void {
                if (PHP_SAPI !== 'cli' && !headers_sent()) {
                    if (function_exists("http_response_code")) {
                        http_response_code($status_code);
                    } else {
                        header(" ", true, $status_code);
                    }
                }
            },
        ];
    }
}
