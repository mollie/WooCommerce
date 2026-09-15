<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Uninstall;

use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
class UninstallModule implements ServiceModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        return [\Mollie\WooCommerce\Uninstall\CleanDb::class => static function (): \Mollie\WooCommerce\Uninstall\CleanDb {
            return new \Mollie\WooCommerce\Uninstall\CleanDb(SharedDataDictionary::GATEWAY_CLASSNAMES);
        }];
    }
}
