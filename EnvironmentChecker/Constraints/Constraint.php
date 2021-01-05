<?php

namespace EnvironmentChecker\Constraints;

use EnvironmentChecker\Notice\AdminNotice;
use EnvironmentChecker\ValidateInterface;

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
