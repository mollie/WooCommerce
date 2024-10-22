<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\PluginApi;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\CapturePayment;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\VoidPayment;
use Mollie\WooCommerce\Payment\MollieObject;
use Psr\Container\ContainerInterface;

class PluginApiModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run (ContainerInterface $container): bool {
        MolliePluginApi::init(
            $container->get(CapturePayment::class),
            $container->get(VoidPayment::class),
            $container->get(MollieObject::class)
        );
        return true;
    }
}
