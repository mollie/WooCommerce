<?php

declare(strict_types=1);

if( class_exists(WC_Log_Levels::class)){
    return;
}

abstract class WC_Log_Levels
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    protected static $level_to_severity = array(self::EMERGENCY => 800, self::ALERT => 700, self::CRITICAL => 600, self::ERROR => 500, self::WARNING => 400, self::NOTICE => 300, self::INFO => 200, self::DEBUG => 100);

    protected static $severity_to_level = array(800 => self::EMERGENCY, 700 => self::ALERT, 600 => self::CRITICAL, 500 => self::ERROR, 400 => self::WARNING, 300 => self::NOTICE, 200 => self::INFO, 100 => self::DEBUG);

    public static function is_valid_level($level)
    {
    }

    public static function get_level_severity($level)
    {
    }

    public static function get_severity_level($severity)
    {
    }
}
