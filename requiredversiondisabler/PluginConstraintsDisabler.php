<?php


namespace RequiredVersionDisabler;


use RequiredVersionDisabler\Notice\AdminNotice;

class PluginConstraintsDisabler
{
    /**
     * @var EnvironmentChecker
     */
    private $checker;

    /**
     * PluginConstraintsDisabler constructor.
     *
     * @param Constraint[] $constraintsArray
     */
    public function __construct(array $constraintsArray)
    {
        $this->checker = new EnvironmentChecker($constraintsArray);
    }

    public function maybeDisable()
    {
        if ($this->checker->isCompatible()) {
            return;
        }
        $this->disableAutomaticUpdate();
        $this->disablePluginActivation();
    }

    private function disableAutomaticUpdate()
    {
        //TODO disable update
        //TODO show notice is disabled because of failed requirements
    }

    private function disablePluginActivation()
    {
        //TODO disable activation
        //TODO show notice is disabled because of failed requirements
    }
    protected function maybeShowNotice($message)
    {
        $message = sprintf(
            esc_html__(
                $message,
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="https://wordpress.org/plugins/woocommerce/">',
            '</a>',
            '<a href="' . esc_url(admin_url('plugins.php')) . '">',
            '</a>',
            $this->pluginName
        );
        $notice = new AdminNotice();
        $notice->addAdminNotice('error', $message);
    }
}
