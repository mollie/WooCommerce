<?php

namespace Mollie\WooCommerce\Settings\General;

use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Shared\SharedDataDictionary;

class MollieGeneralSettings
{
    public function gatewayFormFields(
        $defaultTitle,
        $defaultDescription,
        $paymentConfirmation
    ) {

        $formFields = [
            'enabled' => [
                'title' => __(
                    'Enable/Disable',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                /* translators: Placeholder 1: Gateway title */
                'label' => sprintf(
                    __('Enable %s', 'mollie-payments-for-woocommerce'),
                    $defaultTitle
                ),
                'default' => 'yes',
            ],
            'display' => [
                'id' => $defaultTitle . '_' . 'title',
                'title' => sprintf(
                    /* translators: Placeholder 1: Gateway title */
                    __('%s display settings', 'mollie-payments-for-woocommerce'),
                    $defaultTitle
                ),
                'type' => 'title',
            ],
            'use_api_title' => [
                'title' => __(
                    'Use API dynamic title and gateway logo',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'label' => __('Retrieve the gateway title and logo from the Mollie API', 'mollie-payments-for-woocommerce'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'mollie-payments-for-woocommerce'),
                'type' => 'text',
                /* translators: Placeholder 1: Gateway title */
                'description' => sprintf(
                    __(
                        'This controls the title which the user sees during checkout. Default <code>%s</code>',
                        'mollie-payments-for-woocommerce'
                    ),
                    $defaultTitle
                ),
                'default' => $defaultTitle,
                'desc_tip' => true,
            ],
            'display_logo' => [
                'title' => __(
                    'Display logo',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'label' => __(
                    'Display logo on checkout page. Default <code>enabled</code>',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => 'yes',
            ],
            'enable_custom_logo' => [
                'title' => __(
                    'Enable custom logo',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => 'no',
                'type' => 'checkbox',
                'label' => __(
                    'Enable the feature to add a custom logo for this gateway. This feature will have precedence over other logo options.',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            'upload_logo' => [
                'title' => __(
                    'Upload custom logo',
                    'mollie-payments-for-woocommerce'
                ),
                'default' => null,
                'type' => 'file',
                'custom_attributes' => ['accept' => '.png, .jpeg, .svg, image/png, image/jpeg'],
                'description' => sprintf(
                    __(
                        'Upload a custom icon for this gateway. The feature must be enabled.',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'mollie-payments-for-woocommerce'),
                'type' => 'textarea',
                /* translators: Placeholder 1: Gateway description */
                'description' => sprintf(
                    __(
                        'Payment method description that the customer will see on your checkout. Default <code>%s</code>',
                        'mollie-payments-for-woocommerce'
                    ),
                    $defaultDescription
                ),
                'default' => $defaultDescription,
                'desc_tip' => true,
            ],
            'sales' => [
                'id' => $defaultTitle . '_' . 'title',
                'title' => sprintf(__(
                    'Sales countries',
                    'mollie-payments-for-woocommerce'
                )),
                'type' => 'title',
            ],
            'allowed_countries' => [
                'title' => __(
                    'Sell to specific countries',
                    'mollie-payments-for-woocommerce'
                ),
                'desc' => '',
                'css' => 'min-width: 350px;',
                'default' => [],
                'type' => 'multi_select_countries',
            ],
            'surcharge' => [
                'id' => $defaultTitle . '_' . 'surcharge',
                'title' => sprintf(
                /* translators: Placeholder 1: Gateway title */
                    __('%s surcharge', 'mollie-payments-for-woocommerce'),
                    $defaultTitle
                ),
                'type' => 'title',
            ],
            'payment_surcharge' => [
                'title' => __(
                    'Payment Surcharge',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'select',
                'options' => [
                    Surcharge::NO_FEE => __(
                        'No fee',
                        'mollie-payments-for-woocommerce'
                    ),
                    Surcharge::FIXED_FEE => __(
                        'Fixed fee',
                        'mollie-payments-for-woocommerce'
                    ),
                    Surcharge::PERCENTAGE => __(
                        'Percentage',
                        'mollie-payments-for-woocommerce'
                    ),
                    Surcharge::FIXED_AND_PERCENTAGE => __(
                        'Fixed fee and percentage',
                        'mollie-payments-for-woocommerce'
                    ),
                ],
                'default' => 'no_fee',
                'description' => __(
                    'Choose a payment surcharge for this gateway',
                    'mollie-payments-for-woocommerce'
                ),
                'desc_tip' => true,
            ],
            'fixed_fee' => [
                'title' => sprintf(
                /* translators: Placeholder 1: currency */
                    __('Payment surcharge fixed amount in %s', 'mollie-payments-for-woocommerce'),
                    html_entity_decode(get_woocommerce_currency_symbol())
                ),
                'type' => 'number',
                'description' => sprintf(
                    __(
                        'Control the fee added on checkout. Default 0.00',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'custom_attributes' => ['step' => '0.01', 'min' => '0.00', 'max' => '999'],
                'default' => '0.00',
                'desc_tip' => true,
            ],
            'percentage' => [
                'title' => __('Payment surcharge percentage amount %', 'mollie-payments-for-woocommerce'),
                'type' => 'number',
                'description' => sprintf(
                    __(
                        'Control the percentage fee added on checkout. Default 0.00',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'custom_attributes' => ['step' => '0.01', 'min' => '0.00', 'max' => '999'],
                'default' => '0.00',
                'desc_tip' => true,
            ],
            'surcharge_limit' => [
                /* translators: Placeholder 1: currency */
                'title' => sprintf(__('Payment surcharge limit in %s', 'mollie-payments-for-woocommerce'), html_entity_decode(get_woocommerce_currency_symbol())),
                'type' => 'number',
                'description' => sprintf(
                    __(
                        'Limit the maximum fee added on checkout. Default 0, means no limit',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'custom_attributes' => ['step' => '0.01', 'min' => '0.00', 'max' => '999'],
                'default' => '0.00',
                'desc_tip' => true,
            ],
            'maximum_limit' => [
                /* translators: Placeholder 1: currency */
                'title' => sprintf(__('Surcharge only under this limit, in %s', 'mollie-payments-for-woocommerce'), html_entity_decode(get_woocommerce_currency_symbol())),
                'type' => 'number',
                'description' => sprintf(
                    __(
                        'Maximum order amount to apply surcharge. If the order is above this number the surcharge will not apply. Default 0, means no maximum',
                        'mollie-payments-for-woocommerce'
                    )
                ),
                'custom_attributes' => ['step' => '0.01', 'min' => '0.00', 'max' => '999'],
                'default' => '0.00',
                'desc_tip' => true,
            ],
            'advanced' => [
                'id' => $defaultTitle . '_' . 'advanced',
                'title' => sprintf(
                /* translators: Placeholder 1: gateway title */
                    __('%s advanced', 'mollie-payments-for-woocommerce'),
                    $defaultTitle
                ),
                'type' => 'title',
            ],
        ];

        if ($paymentConfirmation) {
            $formFields['initial_order_status'] = [
                'title' => __(
                    'Initial order status',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'select',
                'options' => [
                    SharedDataDictionary::STATUS_ON_HOLD => wc_get_order_status_name(SharedDataDictionary::STATUS_ON_HOLD) . ' (' . __(
                        'default',
                        'mollie-payments-for-woocommerce'
                    ) . ')',
                    SharedDataDictionary::STATUS_PENDING => wc_get_order_status_name(
                        SharedDataDictionary::STATUS_PENDING
                    ),
                ],
                'default' => SharedDataDictionary::STATUS_ON_HOLD,
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => sprintf(
                    __(
                        'Some payment methods take longer than a few hours to complete. The initial order state is then set to \'%1$s\'. This ensures the order is not cancelled when the setting %2$s is used. This will also prevent the order to be canceled when expired.',
                        'mollie-payments-for-woocommerce'
                    ),
                    wc_get_order_status_name(
                        SharedDataDictionary::STATUS_ON_HOLD
                    ),
                    '<a href="' . admin_url(
                        'admin.php?page=wc-settings&tab=products&section=inventory'
                    ) . '" target="_blank">' . __(
                        'Hold Stock (minutes)',
                        'woocommerce'
                    ) . '</a>'
                ),
            ];
        }

        $formFields['activate_expiry_days_setting'] = [
            'title' => __('Activate expiry time setting', 'mollie-payments-for-woocommerce'),
            'label' => __('Enable expiry time for payments', 'mollie-payments-for-woocommerce'),
            'description' => __('Enable this option if you want to be able to set the time after which the order will expire.', 'mollie-payments-for-woocommerce'),
            'type' => 'checkbox',
            'default' => 'no',
        ];
        $formFields['order_dueDate'] = [
            'title' => sprintf(__('Expiry time', 'mollie-payments-for-woocommerce')),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '10', 'max' => '526000'],
            'description' => sprintf(
                __(
                    'Number of MINUTES after the order will expire and will be canceled at Mollie and WooCommerce.',
                    'mollie-payments-for-woocommerce'
                )
            ),
            'default' => '10',
            'desc_tip' => false,
        ];

        return $formFields;
    }
}
