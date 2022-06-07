<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared;

use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Mollie\Api\CompatibilityChecker;
use Mollie\WooCommerce\SDK\Api;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

class SharedModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public const PLUGIN_ID = 'mollie-payments-for-woocommerce';

    public function services(): array
    {
        return [
            'shared.plugin_id' => static function (): string {
                //Get plugin legacy id
                return 'mollie-payments-for-woocommerce';
            },
            'shared.plugin_version' => static function (): string {
                //Get plugin version
                return '7.2.0-beta1';
            },
            'shared.plugin_title' => static function (): string {
                //Get plugin version
                return 'Mollie Payments for WooCommerce';
            },
            'shared.plugin_file' => static function (): string {
                //Get location of main plugin file TODO handle with properties
                return plugin_basename(self::PLUGIN_ID . '/' . self::PLUGIN_ID . '.php');
            },
            'shared.plugin_url' => static function (ContainerInterface $container): string {
                $pluginProperties = $container->get(Package::PROPERTIES);

                return $pluginProperties->baseUrl();
            },
            'shared.plugin_path' => static function (ContainerInterface $container): string {

                $pluginProperties = $container->get(Package::PROPERTIES);

                return $pluginProperties->basePath();
            },
            'shared.status_helper' => static function (ContainerInterface $container): Status {
                $pluginTitle = $container->get('shared.plugin_title');
                return new Status(new CompatibilityChecker(), $pluginTitle);
            },
            'shared.set_http_response_code' => static function ($status_code): void {
                if (PHP_SAPI !== 'cli' && !headers_sent()) {
                    if (function_exists("http_response_code")) {
                        http_response_code($status_code);
                    } else {
                        header(" ", true, $status_code);
                    }
                }
            }
        ];
    }
}
