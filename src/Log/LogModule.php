<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Log;

use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

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
            Logger::class => static function (ContainerInterface $container) use ($source): AbstractLogger {
                $debugEnabled = $container->get('settings.IsDebugEnabled');
                if ($debugEnabled) {
                    return new WcPsrLoggerAdapter(\wc_get_logger(), $source);
                }
                return new NullLogger();
            },
        ];
    }
}
