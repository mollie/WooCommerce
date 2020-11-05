<?php

use RequiredVersionDisabler\ConstraintsCollectionFactory;
use RequiredVersionDisabler\PluginConstraintsDisabler;

class Mollie_WC_ConstraintsChecker
{
    /**
     * @var array
     */
    protected $constraints;


    /**
     * ConstraintsChecker constructor.
     *
     * @param array $constraints
     */
    public function __construct(array $constraints)
    {
        $this->constraints = $constraints;
    }

    public function checkConstraints()
    {
        $collectionFactory = new ConstraintsCollectionFactory();
        $constraintsCollection = $collectionFactory->create(
            $this->constraints,
            'Mollie Payments for WooCommerce'
        );
        $disabler = new PluginConstraintsDisabler(
            $constraintsCollection,
            'mollie-payments-for-woocommerce',
            'mollie_wc_plugin_init'
        );
        return $disabler->maybeDisable();
    }
}
