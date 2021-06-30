<?php
namespace Mollie\WooCommerce\Gateway\Belfius;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Subscription\AbstractSepaRecurring;

class Mollie_WC_Gateway_Belfius extends AbstractSepaRecurring
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
        return PaymentMethod::BELFIUS;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Belfius Direct Net', 'mollie-payments-for-woocommerce');
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
