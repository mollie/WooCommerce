<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_BankTransfer extends Mollie_WC_Gateway_Abstract
{
    const EXPIRY_DEFAULT_DAYS = 12;
    const EXPIRY_MIN_DAYS     = 5;
    const EXPIRY_MAX_DAYS     = 60;

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

        add_filter('woocommerce_' . $this->id . '_args', array($this, 'addPaymentArguments'), 10, 2);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields = array_merge($this->form_fields, array(
            'expiry_days' => array(
                'title'             => __('Expiry date', 'mollie-payments-for-woocommerce'),
                'type'              => 'number',
                'description'       => sprintf(__('Number of days after the payment will expire. Default <code>%d</code> days', 'mollie-payments-for-woocommerce'), self::EXPIRY_DEFAULT_DAYS),
                'default'           => self::EXPIRY_DEFAULT_DAYS,
                'custom_attributes' => array(
                    'min'  => self::EXPIRY_MIN_DAYS,
                    'max'  => self::EXPIRY_MAX_DAYS,
                    'step' => 1,
                ),
            ),
            'skip_mollie_payment_screen' => array(
                'title'             => __('Skip Mollie payment screen', 'mollie-payments-for-woocommerce'),
                'label'             => __('Skip Mollie payment screen when Bank Transfer is selected', 'mollie-payments-for-woocommerce'),
                'description'       => __('Enable this option if you want to skip redirecting your user to the Mollie payment screen, instead this will redirect your user directly to the WooCommerce order received page displaying instructions how to complete the Bank Transfer payment.', 'mollie-payments-for-woocommerce'),
                'type'              => 'checkbox',
                'default'           => 'no',
            ),
        ));
    }

	/**
	 * @param array    $args
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function addPaymentArguments( array $args, WC_Order $order ) {
		// Expiry date
		$expiry_days = (int) $this->get_option( 'expiry_days', self::EXPIRY_DEFAULT_DAYS );

		if ( $expiry_days >= self::EXPIRY_MIN_DAYS && $expiry_days <= self::EXPIRY_MAX_DAYS ) {
			$expiry_date = date( "Y-m-d", strtotime( "+$expiry_days days" ) );

			// Add dueDate at the correct location
			if ( isset( $args['payment'] ) ) {
				$args['payment']['dueDate'] = $expiry_date;
			} else {
				$args['dueDate'] = $expiry_date;
			}
		}

		// Billing email is now required

		return $args;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param WC_Order                                            $order
	 * @param \Mollie_WC_Payment_Order|\Mollie_WC_Payment_Payment $payment_object
	 *
	 * @return string
	 */
    protected function getProcessPaymentRedirect(WC_Order $order, $payment_object)
    {
        if ($this->get_option('skip_mollie_payment_screen') === 'yes')
        {
            /*
             * Redirect to order received page
             */
            $redirect_url = $this->get_return_url($order);

            // Add utm_nooverride query string
            $redirect_url = add_query_arg(array(
                'utm_nooverride' => 1,
            ), $redirect_url);

            return $redirect_url;
        }

        return parent::getProcessPaymentRedirect($order, $payment_object);
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return PaymentMethod::BANKTRANSFER;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Bank Transfer', 'mollie-payments-for-woocommerce');
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

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    protected function paymentConfirmationAfterCoupleOfDays ()
    {
        return true;
    }

    /**
     * @param WC_Order                  $order
     * @param Mollie\Api\Resources\Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions (WC_Order $order, Mollie\Api\Resources\Payment $payment, $admin_instructions, $plain_text)
    {
        $instructions = '';

        if (!$payment->details)
        {
            return null;
        }

        $data_helper = Mollie_WC_Plugin::getDataHelper();

        if ($payment->isPaid())
        {
            $instructions .= sprintf(
                /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%s</strong> (IBAN (last 4 digits): %s, BIC: %s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
	            substr($payment->details->consumerAccount, -4),
                $payment->details->consumerBic
            );
        }
        elseif ($data_helper->hasOrderStatus($order, 'on-hold') || $data_helper->hasOrderStatus($order, 'pending') )
        {
            if (!$admin_instructions)
            {
                $instructions .= __('Please complete your payment by transferring the total amount to the following bank account:', 'mollie-payments-for-woocommerce') . "\n\n\n";
            }

            /* translators: Placeholder 1: 'Stichting Mollie Payments' */
            $instructions .= sprintf(__('Beneficiary: %s', 'mollie-payments-for-woocommerce'), $payment->details->bankName) . "\n";
            $instructions .= sprintf(__('IBAN: <strong>%s</strong>', 'mollie-payments-for-woocommerce'), implode(' ', str_split($payment->details->bankAccount, 4))) . "\n";
            $instructions .= sprintf(__('BIC: %s', 'mollie-payments-for-woocommerce'), $payment->details->bankBic) . "\n";

            if ($admin_instructions)
            {
                /* translators: Placeholder 1: Payment reference e.g. RF49-0000-4716-6216 (SEPA) or +++513/7587/59959+++ (Belgium) */
                $instructions .= sprintf(__('Payment reference: %s', 'mollie-payments-for-woocommerce'), $payment->details->transferReference) . "\n";
            }
            else
            {
                /* translators: Placeholder 1: Payment reference e.g. RF49-0000-4716-6216 (SEPA) or +++513/7587/59959+++ (Belgium) */
                $instructions .= sprintf(__('Please provide the payment reference <strong>%s</strong>', 'mollie-payments-for-woocommerce'), $payment->details->transferReference) . "\n";
            }

            if (!empty($payment->expiryPeriod)
                && class_exists('DateTime')
                && class_exists('DateInterval'))
            {
                $expiry_date = DateTime::createFromFormat( 'U', time() );
	            $expiry_date->add( new DateInterval( $payment->expiryPeriod ) );
	            $expiry_date = $expiry_date->format( 'Y-m-d H:i:s' );
	            $expiry_date = date_i18n( wc_date_format(), strtotime( $expiry_date ) );

                if ($admin_instructions)
                {
                    $instructions .= "\n" . sprintf(
                        __('The payment will expire on <strong>%s</strong>.', 'mollie-payments-for-woocommerce'),
                        $expiry_date
                    ) . "\n";
                }
                else
                {
                    $instructions .= "\n" . sprintf(
                        __('The payment will expire on <strong>%s</strong>. Please make sure you transfer the total amount before this date.', 'mollie-payments-for-woocommerce'),
                        $expiry_date
                    ) . "\n";
                }
            }
        }

        return $instructions;
    }
}
