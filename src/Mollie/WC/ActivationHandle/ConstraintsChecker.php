<?php

use EnvironmentChecker\ConstraintsCollectionFactory;
use EnvironmentChecker\EnvironmentChecker;


class Mollie_WC_ActivationHandle_ConstraintsChecker
{

    /**
     * @var EnvironmentChecker
     */
    private $checker;

    /**
     * @var Mollie_WC_Notice_NoticeInterface
     */
    private $notice;


    /**
     * ConstraintsChecker constructor.
     *
     *
     */
    public function __construct()
    {
        $collectionFactory = new ConstraintsCollectionFactory(
            [
                ConstraintsCollectionFactory::PHP_CONSTRAINT => '5.6',
                ConstraintsCollectionFactory::WORDPRESS_CONSTRAINT => '3.8',
                ConstraintsCollectionFactory::WOOCOMMERCE_CONSTRAINT => '3.0',
            ],
            'Mollie Payments for WooCommerce'
        );
        $constraintsCollection = $collectionFactory->create();
        $this->checker = new EnvironmentChecker(
            $constraintsCollection->constraints()
        );
        $this->notice =  new Mollie_WC_Notice_AdminNotice();
    }

    /**
     * Notices of failed constraint
     * Deactivates the plugin if needed by not met constraint
     * prevents updates
     *
     * @param array $constraints
     */
    public function handleActivation()
    {
        if($this->checker->checkEnvironment()){
            return true;
        }
        $errors = $this->checker->getErrors();
        $this->showNotice($errors);
        $disabler = new Mollie_WC_ActivationHandle_PluginDisabler(
            'mollie-payments-for-woocommerce',
            'mollie_wc_plugin_init'
        );
        $disabler->disableAll();
        return false;
    }

    private function showNotice(array $errors)
    {
        $message = '%1$sMollie Payments for WooCommerce is inactive.%2$s';
        foreach ($errors as $error) {
            $message .= $error.'\n';
        }
        $message =  __( $message, 'mollie-payments-for-woocommerce' );
        $errorLevel = 'notice-error';
        $this->notice->addNotice($errorLevel, $message);
    }
}
