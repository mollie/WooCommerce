<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Uninstall;

use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Shared\SharedDataDictionary;

class UninstallModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        return [
            CleanDb::class => static function (): CleanDb {
                return new CleanDb(SharedDataDictionary::GATEWAY_CLASSNAMES);
            },
        ];
    }
}
