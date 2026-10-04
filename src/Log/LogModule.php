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
        return [
            Logger::class => static function (ContainerInterface $container) use ($source): AbstractLogger {
                $debugEnabled = $container->get('settings.IsDebugEnabled');
                if ($debugEnabled) {
                    return new \Mollie\WooCommerce\Log\WcPsrLoggerAdapter(\wc_get_logger(), $source);
                }
                return new NullLogger();
            },
            // Written whatever the debug switch says.
            'log.always_on' => static function (ContainerInterface $container): Logger {
                return new \Mollie\WooCommerce\Log\WcPsrLoggerAdapter(\wc_get_logger(), $container->get('shared.plugin_id') . '-');
            },
            \Mollie\WooCommerce\Log\EventLog::class => static function (ContainerInterface $container): \Mollie\WooCommerce\Log\EventLog {
                $logger = $container->get(Logger::class);
                assert($logger instanceof Logger);
                if ($container->get('settings.IsDebugEnabled')) {
                    return new \Mollie\WooCommerce\Log\EventLog($logger);
                }
                $alwaysOn = $container->get('log.always_on');
                assert($alwaysOn instanceof Logger);
                // Debug off: warnings and errors are always written, info only if the site opts in.
                if (apply_filters('mollie_wc_event_log_always_on', \false)) {
                    return new \Mollie\WooCommerce\Log\EventLog($alwaysOn);
                }
                return new \Mollie\WooCommerce\Log\EventLog($logger, $alwaysOn);
            },
        ];
    }
}
