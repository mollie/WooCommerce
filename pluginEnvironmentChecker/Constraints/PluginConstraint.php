<?php

namespace Mollie\Inpsyde\EnvironmentChecker\Constraints;

use Mollie\Inpsyde\EnvironmentChecker\Exception\ConstraintFailedException;
class PluginConstraint extends AbstractVersionConstraint
{
    protected $pluginDisplayName;
    /**
     * WooCommerceAbstractVersionConstraint constructor.
     *
     * @param        $requiredVersion
     * @param string $requiredPluginName name of the class we need to check against
     * @param string $pluginDisplayName name of the plugin to be shown on notices
     */
    public function __construct($requiredVersion, $requiredPluginName, $pluginDisplayName)
    {
        parent::__construct($requiredVersion, $requiredPluginName);
        $this->error = esc_html('Plugin incompatibility');
        $this->requiredPluginName = $requiredPluginName;
        $this->pluginDisplayName = $pluginDisplayName;
    }
    /**
     * @inheritDoc
     */
    public function check()
    {
        $pathToPluginFile = $this->absolutePathToPlugin();
        if (!$pathToPluginFile) {
            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new ConstraintFailedException($this, esc_html($this->requiredPluginName), [$this->error], esc_html("Cannot find absolute path to {$this->pluginDisplayName} plugin"));
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if (!function_exists('get_plugin_data')) {
            require_once \ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $pluginData = get_plugin_data($pathToPluginFile, \false, \false);
        $currentVersion = $pluginData['Version'];
        $this->message = "The {$this->pluginDisplayName} plugin has to be version " . $this->requiredVersion . " or higher. Please update your {$this->pluginDisplayName} version.";
        $this->message = esc_html($this->message);
        return $this->checkVersion($currentVersion);
    }
    protected function absolutePathToPlugin()
    {
        if (defined('WP_PLUGIN_DIR')) {
            return \WP_PLUGIN_DIR . "/{$this->requiredPluginName}/{$this->requiredPluginName}.php";
        }
        return \false;
    }
}
