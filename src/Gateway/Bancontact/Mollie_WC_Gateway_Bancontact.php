<?php

namespace Mollie\WooCommerce\Gateway\Bancontact;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Subscription\AbstractSepaRecurring;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;

class Mollie_WC_Gateway_Bancontact extends AbstractSepaRecurring {
	/**
	 *
	 */
    public function __construct(
        IconFactory $iconFactory,
        PaymentService $paymentService,
        SurchargeService $surchargeService,
        MollieOrderService $mollieOrderService,
        Logger $logger,
        NoticeInterface $notice
    ) {
		$this->supports = array (
			'products',
			'refunds',
		);
        parent::__construct(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice
        );
	}

	/**
	 * @return string
	 */
	public function getMollieMethodId() {
		return PaymentMethod::BANCONTACT;
	}

	/**
	 * @return string
	 */
	public function getDefaultTitle() {
		return __( 'Bancontact', 'mollie-payments-for-woocommerce' );
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
	protected function getDefaultDescription() {
		return '';
	}
}
