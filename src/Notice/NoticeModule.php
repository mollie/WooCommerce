<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Notice;

use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
class NoticeModule implements ServiceModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        return [\Mollie\WooCommerce\Notice\AdminNotice::class => static function (): \Mollie\WooCommerce\Notice\AdminNotice {
            return new \Mollie\WooCommerce\Notice\AdminNotice();
        }, \Mollie\WooCommerce\Notice\FrontendNotice::class => static function (): \Mollie\WooCommerce\Notice\FrontendNotice {
            return new \Mollie\WooCommerce\Notice\FrontendNotice();
        }];
    }
}
