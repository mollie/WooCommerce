<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\PluginApi;

use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\CapturePayment;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\VoidPayment;
use Mollie\WooCommerce\Payment\MollieObject;
use Psr\Container\ContainerInterface;

class PluginApiModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        return [
            MolliePluginApi::class => static function (ContainerInterface $container): MolliePluginApi {
                MolliePluginApi::init(
                    $container->get(CapturePayment::class),
                    $container->get(VoidPayment::class),
                    $container->get(MollieObject::class)
                );
                return MolliePluginApi::getInstance();
            },
        ];
    }
}
