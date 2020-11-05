<?php


namespace RequiredVersionDisabler\Constraints;


use RequiredVersionDisabler\Notice\AdminNotice;
use RequiredVersionDisabler\ValidateInterface;

abstract class Constraint implements ValidateInterface
{
    /**
     * @var string Version against we need to check
     */
    protected $requiredVersion;
    /**
     * @var string Name of this plugin
     */
    protected $pluginName;

    /**
     * Show error notice
     *
     * @param $message
     */
    protected function showNotice($message)
    {
        $notice = new AdminNotice();
        $notice->addAdminNotice('error', $message);
    }
}
