<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\ReturnPage\framework\ReturnPageManager;
use Mollie\WooCommerce\ReturnPage\mollie\MollieReturnPageExtension;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

/**
 * Payment Failover Module
 * Integrates the race condition failover mechanism into the Mollie plugin
 */
class ReturnPageModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    /**
     * @inheritDoc
     */
    public function services(): array
    {
        return [
            ReturnPageManager::class => static function (ContainerInterface $container): ReturnPageManager {
                return new ReturnPageManager($container);
            },
            MollieReturnPageExtension::class => static function (ContainerInterface $container): MollieReturnPageExtension {
                return new MollieReturnPageExtension(
                    $container->get(ReturnPageManager::class),
                    $container->get(MollieOrderService::class),
                    $container->get(Logger::class)
                );
            }
        ];
    }

    /**
     * @inheritDoc
     */
    public function run(ContainerInterface $container): bool
    {
        if (!$this->isWooCommerceActive()) {
            return false;
        }

        $mollieExtension = $container->get(MollieReturnPageExtension::class);
        assert($mollieExtension instanceof MollieReturnPageExtension);
        $mollieExtension->init();

        return true;
    }

    /**
     * Check if WooCommerce is active
     */
    private function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_order');
    }
}
