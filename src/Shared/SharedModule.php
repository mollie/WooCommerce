<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared;

use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Package;
use Mollie\Api\CompatibilityChecker;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

class SharedModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public const PLUGIN_ID = 'mollie-payments-for-woocommerce';

    public function services(): array
    {
        return [
            'shared.plugin_id' => static function (ContainerInterface $container): string {
                //Get plugin legacy id
                return $container->get('properties')->get('textDomain');
            },
            'shared.plugin_version' => static function (ContainerInterface $container): string {
                return $container->get('properties')->get('version');
            },
            'shared.plugin_title' => static function (ContainerInterface $container): string {
                return $container->get('properties')->get('Title');
            },
            'shared.plugin_file' => static function (): string {
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
            },
        ];
    }
}
