<?php


namespace RequiredVersionDisabler\Constraints;


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
}
