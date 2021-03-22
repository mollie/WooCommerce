<?php

class Mollie_WC_ActivationHandle_PluginDisabler
{


    private $pluginSlug;
    private $initFunctionName;

    /**
     * PluginConstraintsDisabler constructor.
     *
     * @param string                $pluginSlug
     * @param string                $initFunctionName
     */
    public function __construct(
        $pluginSlug,
        $initFunctionName
    ) {

        $this->pluginSlug = $pluginSlug;
        $this->initFunctionName = $initFunctionName;
    }

    /**
     * Disable the plugin if conditions apply
     */
    public function disableAll()
    {
        $this->disableAutomaticUpdate();
        $this->disablePluginActivation($this->initFunctionName);
    }

    /**
     * Disable automatic updates of this plugin
     */
    public function disableAutomaticUpdate()
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
    public function disablePluginActivation($initFunctionName)
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
