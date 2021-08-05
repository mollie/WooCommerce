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

namespace Mollie\WooCommerce\Log;

use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

class LogModule implements ServiceModule
{
    use ModuleClassNameIdTrait;

    private $loggerSource;

    /**
     * LogModule constructor.
     */
    public function __construct($loggerSource)
    {
        $this->loggerSource = $loggerSource;
    }

    public function services(): array
    {
        $source = $this->loggerSource;
        return [
            Logger::class => static function (ContainerInterface $container) use ($source): WcPsrLoggerAdapter {
                return new WcPsrLoggerAdapter(\wc_get_logger(), $source);
            },
        ];
    }
}
