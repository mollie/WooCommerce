<?php


namespace RequiredVersionDisabler;


abstract class Constraint implements ValidateInterface
{
    protected $requiredVersion;
    protected $pluginName;
}
