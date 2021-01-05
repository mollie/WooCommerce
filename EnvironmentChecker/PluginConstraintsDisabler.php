<?php

namespace EnvironmentChecker;

use EnvironmentChecker\Constraints\ConstraintsCollection;

class PluginConstraintsDisabler
{

    /**
     * @var EnvironmentChecker
     */
    private $checker;
    private $pluginSlug;
    private $initFunctionName;

    /**
     * PluginConstraintsDisabler constructor.
     *
     * @param ConstraintsCollection $constraintsArray
     * @param string                $pluginSlug
     * @param string                $initFunctionName
     */
    public function __construct(
        ConstraintsCollection $constraintsArray,
        $pluginSlug,
        $initFunctionName
    ) {
        $this->checker = new EnvironmentChecker(
            $constraintsArray->constraints()
        );
        $this->pluginSlug = $pluginSlug;
        $this->initFunctionName = $initFunctionName;
    }

    /**
     * Disable the plugin if conditions apply
     */
    public function maybeDisable()
    {
        if ($this->checker->isCompatible()) {
            return true;
        }
        $this->disableAutomaticUpdate();
        $this->disablePluginActivation($this->initFunctionName);
        return false;
    }

    /**
     * Disable automatic updates of this plugin
     */
    protected function disableAutomaticUpdate()
    {
        add_filter(
            'auto_update_plugin',
            [$this, 'notAutoUpdateThisPlugin'],
            10,
            2
        );
    }

    /**
     * Disable this plugin by removing its init function
     *
     * @param string $initFunctionName Name of the method that initiates the plugin.
     */
    protected function disablePluginActivation($initFunctionName)
    {
        remove_action('init', $initFunctionName);
    }

    /**
     * Remove the plugin from the auto-update list
     * @param $update
     * @param $item
     *
     * @return false
     */
    public function notAutoUpdateThisPlugin($update, $item)
    {
        if ($item == $this->pluginSlug) {
            return false;
        } else {
            return $update;
        }
    }
}
