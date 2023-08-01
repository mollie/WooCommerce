<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Notice;

use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;

class NoticeModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        return [
            AdminNotice::class => static function (): AdminNotice {
                return new AdminNotice();
            },
            FrontendNotice::class => static function (): FrontendNotice {
                return new FrontendNotice();
            },
        ];
    }
}
