<?php

declare(strict_types=1);

if( class_exists(WC_Logger::class)){
    return;
}

interface WC_Logger_Interface
{
    public function add($handle, $message, $level = \WC_Log_Levels::NOTICE);
    public function log($level, $message, $context = []);
    public function emergency($message, $context = []);
    public function alert($message, $context = []);
    public function critical($message, $context = []);
    public function error($message, $context = []);
    public function warning($message, $context = []);
    public function notice($message, $context = []);
    public function info($message, $context = []);
    public function debug($message, $context = []);
}

class WC_Logger implements WC_Logger_Interface
{

    public function add($handle, $message, $level = \WC_Log_Levels::NOTICE)
    {
    }

    public function log($level, $message, $context = [])
    {
    }

    public function emergency($message, $context = [])
    {
    }

    public function alert($message, $context = [])
    {
    }

    public function critical($message, $context = [])
    {
    }

    public function error($message, $context = [])
    {
    }

    public function warning($message, $context = [])
    {
    }

    public function notice($message, $context = [])
    {
    }

    public function info($message, $context = [])
    {
    }

    public function debug($message, $context = [])
    {
    }
}
