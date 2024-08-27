<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use WC_Order;

class Banktransfer extends AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @var int
     */
    public const EXPIRY_DEFAULT_DAYS = 12;
    /**
     * @var int
     */
    public const EXPIRY_MIN_DAYS = 5;
    /**
     * @var int
     */
    public const EXPIRY_MAX_DAYS = 60;
    /**
     * @var string
     */
    public const EXPIRY_DAYS_OPTION = 'order_dueDate';

    protected function getConfig(): array
    {
        return [
            'id' => 'banktransfer',
            'defaultTitle' => __('Bank Transfer', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => true,
            'confirmationDelayed' => true,
            'SEPA' => false,
            'customRedirect' => true,
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        unset($generalFormFields['activate_expiry_days_setting']);
        unset($generalFormFields['order_dueDate']);
        $paymentMethodFormFieds = [
            'activate_expiry_days_setting' => [
                'title' => __('Activate expiry time setting', 'mollie-payments-for-woocommerce'),
                'label' => __('Enable expiry time for payments', 'mollie-payments-for-woocommerce'),
                'description' => __('Enable this option if you want to be able to set the time after which the payment will expire. This will turn all transactions into payments instead of orders', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'order_dueDate' => [
                'title' => __('Expiry time', 'mollie-payments-for-woocommerce'),
                'type' => 'number',
                /* translators: Placeholder 1: Default expiry days. */
                'description' => sprintf(__('Number of DAYS after the payment will expire. Default <code>%d</code> days', 'mollie-payments-for-woocommerce'), self::EXPIRY_DEFAULT_DAYS),
                'default' => self::EXPIRY_DEFAULT_DAYS,
                'custom_attributes' => [
                    'min' => self::EXPIRY_MIN_DAYS,
                    'max' => self::EXPIRY_MAX_DAYS,
                    'step' => 1,
                ],
            ],
            'skip_mollie_payment_screen' => [
                'title' => __('Skip Mollie payment screen', 'mollie-payments-for-woocommerce'),
                'label' => __('Skip Mollie payment screen when Bank Transfer is selected', 'mollie-payments-for-woocommerce'),
                'description' => __('Enable this option if you want to skip redirecting your user to the Mollie payment screen, instead this will redirect your user directly to the WooCommerce order received page displaying instructions how to complete the Bank Transfer payment.', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }

    public function filtersOnBuild()
    {
        add_filter('woocommerce_mollie_wc_gateway_' . $this->getProperty('id') . 'payment_args', function (array $args, \WC_Order $order): array {
            return $this->addPaymentArguments($args, $order);
        }, 10, 2);
    }
    /**
     * @param WC_Order $order
     * @return array
     */
    public function addPaymentArguments(array $args, WC_Order $order)
    {
        // Expiry date
        $expiry_days = (int)$this->getProperty(self::EXPIRY_DAYS_OPTION) ?: self::EXPIRY_DEFAULT_DAYS;

        if ($expiry_days >= self::EXPIRY_MIN_DAYS && $expiry_days <= self::EXPIRY_MAX_DAYS) {
            $expiry_date = gmdate("Y-m-d", strtotime(sprintf('+%s days', $expiry_days)));

            // Add dueDate at the correct location
            if ($this->isExpiredDateSettingActivated()) {
                if (isset($args['payment'])) {
                    $args['payment']['dueDate'] = $expiry_date;
                } else {
                    $args['dueDate'] = $expiry_date;
                }
            }

            $email = (ctype_space($order->get_billing_email())) ? null
                : $order->get_billing_email();
            if ($email) {
                $args['billingEmail'] = $email;
            }
        }

        return $args;
    }

    public function isExpiredDateSettingActivated()
    {
        $expiryDays = $this->getProperty(
            'activate_expiry_days_setting'
        );
        return mollieWooCommerceStringToBoolOption($expiryDays);
    }
}
