<?php

/**
 * This file is part of the  Mollie\WooCommerce.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * PHP version 7
 *
 * @category Activation
 * @package  Mollie\WooCommerce
 * @author   AuthorName <hello@inpsyde.com>
 * @license  GPLv2+
 * @link     https://www.inpsyde.com
 */

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\SDK\HttpResponse;
use Psr\Container\ContainerInterface;

class SDKModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services(): array
    {
        return [
            'SDK.HttpResponse' => function (): HttpResponse {
                return new HttpResponse();
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        return true;
    }
}
