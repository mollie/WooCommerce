<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Log;

use InvalidArgumentException;
use Mollie\Psr\Log\AbstractLogger;
use Mollie\Psr\Log\LoggerTrait;
use Mollie\Psr\Log\LogLevel;
class WcPsrLoggerAdapter extends AbstractLogger
{
    use LoggerTrait;
    /**
     * @var array
     */
    private $psrWcLoggingLevels = [LogLevel::EMERGENCY => \WC_Log_Levels::EMERGENCY, LogLevel::ALERT => \WC_Log_Levels::ALERT, LogLevel::CRITICAL => \WC_Log_Levels::CRITICAL, LogLevel::ERROR => \WC_Log_Levels::ERROR, LogLevel::WARNING => \WC_Log_Levels::WARNING, LogLevel::NOTICE => \WC_Log_Levels::NOTICE, LogLevel::INFO => \WC_Log_Levels::INFO, LogLevel::DEBUG => \WC_Log_Levels::DEBUG];
    /**
     * @var string
     */
    private $loggerSource;
    /**
     * @var \WC_Logger_Interface
     */
    private $wcLogger;
    /**
     * @var string
     */
    private $className = '';
    /**
     * @var string
     */
    private $loggingLevel;
    /**
     * WcPsrLoggerAdapter constructor.
     *
     * @param \WC_Logger_Interface $wcLogger
     * @param string               $loggingLevel
     */
    public function __construct(\WC_Logger_Interface $wcLogger, $loggerSource, string $loggingLevel = \WC_Log_Levels::DEBUG)
    {
        $this->wcLogger = $wcLogger;
        \assert(in_array($loggingLevel, $this->psrWcLoggingLevels, \true));
        $this->loggingLevel = $loggingLevel;
        $this->loggerSource = $loggerSource;
    }
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $wcLevel = $level;
        if (isset($this->psrWcLoggingLevels[$level])) {
            $wcLevel = $this->psrWcLoggingLevels[$level];
        }
        if (\WC_Log_Levels::get_level_severity($wcLevel) < \WC_Log_Levels::get_level_severity($this->loggingLevel)) {
            $message = sprintf("Unknown log level %s", $wcLevel);
            throw new InvalidArgumentException(esc_html($message));
        }
        if (isset($context['source']) && $context['source'] !== $this->loggerSource) {
            $context['originalSource'] = $context['source'];
        }
        if ($this->className && !isset($context['originalSource'])) {
            $context['originalSource'] = $this->className;
        }
        $context['source'] = $this->loggerSource;
        $interpolatedMessage = is_string($message) ? $this->interpolate($message, $this->getReplacements($context)) : $message;
        $this->wcLogger->log($level, $interpolatedMessage, $context);
    }
    /**
     * @param string $className
     */
    public function setName(string $className)
    {
        \assert(\class_exists($className));
        $this->className = $className;
    }
    /**
     * Interpolates the given values into the message placeholders.
     * based on
     * {@link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#12-message}
     */
    protected function interpolate(string $message, array $replace): string
    {
        return strtr($message, $replace);
    }
    /**
     * Builds replacements list (for interpolate()) from the context values.
     * based on
     * {@link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#12-message}
     * @param array $context
     * @return array
     */
    protected function getReplacements(array $context = []): array
    {
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_string($key)) {
                continue;
            }
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return $replace;
    }
}
