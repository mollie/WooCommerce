<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Log;

use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\AbstractLogger;
use Mollie\Psr\Log\LoggerInterface as Logger;
use Mollie\Psr\Log\NullLogger;
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
        return [Logger::class => static function (ContainerInterface $container) use ($source): AbstractLogger {
            $debugEnabled = $container->get('settings.IsDebugEnabled');
            if ($debugEnabled) {
                return new \Mollie\WooCommerce\Log\WcPsrLoggerAdapter(\wc_get_logger(), $source);
            }
            return new NullLogger();
        }];
    }
}
