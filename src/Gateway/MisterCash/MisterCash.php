<?php

namespace Mollie\WooCommerce\Gateway\MisterCash;

use Mollie\WooCommerce\Subscription\AbstractSepaRecurring;

/**
 * Class MisterCash
 *
 * LEGACY - DO NOT REMOVE!
 * MisterCash was renamed to Bancontact, but this class should stay available for
 * old orders and subscriptions!
 *
 * @deprecated Replaced by Bancontact
 */

class MisterCash extends AbstractSepaRecurring
{
    /**
     *
     */
    public function __construct ()
    {
        $this->supports = array(
            'products',
            'refunds',
        );

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
    	// Don't use constant as it's no longer part of Mollie API PHP
        return 'mistercash';
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Bancontact', 'mollie-payments-for-woocommerce');
    }

	/**
	 * @return string
	 */
	protected function getSettingsDescription() {
		return '';
	}

	/**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return '';
    }
}
