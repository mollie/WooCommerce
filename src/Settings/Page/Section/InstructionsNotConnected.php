<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

class InstructionsNotConnected extends AbstractSection
{
    public function config(): array
    {
        return [
                [
                        'id' => $this->settings->getSettingId('instructions'),
                        'type' => 'mollie_content',
                        'value' => $this->content(),
                ],
        ];
    }

    protected function content(): string
    {
        ob_start();
        ?>
        <h3><?= esc_html(__("Mollie API Keys", 'mollie-payments-for-woocommerce')); ?></h3>
        <p>
            <?= wp_kses(sprintf(
                __(
                    "To start receiving payments through the Mollie plugin in your WooCommerce store,
                you'll need to connect it to your Mollie account using an <a href='%s' target='_blank'>API access token.</a>",
                    'mollie-payments-for-woocommerce'
                ),
                'https://my.mollie.com/dashboard/developers/api-access-tokens?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner'
            ), [
                'a' => [
                    'href' => [],
                    'target' => [],
                ],
            ]); ?>
        </p>
        <p>
            <strong>
                <?= esc_html(__("How to find your API keys:", 'mollie-payments-for-woocommerce')); ?>
            </strong>
        </p>
        <ol>
            <li>
                <?= wp_kses(sprintf(
                    __(
                        "Don’t have a Mollie account yet? <a href='%s' target='_blank'>Get started with Mollie today.</a>",
                        'mollie-payments-for-woocommerce'
                    ),
                    apply_filters('mollie-payments-for-woocommerce_signup_url', 'https://my.mollie.com/dashboard/signup?utm_campaign=GLO_Q4__Woo-Signup-tracker&utm_medium=referral&utm_source={woodashboard}&campaign_name=GLO_Q4__Woo-Signup-tracker')
                ), [
                    'a' => [
                        'href' => [],
                        'target' => [],
                    ],
                ]); ?>
            </li>
            <li>
                <?= wp_kses(sprintf(
                    __(
                        "Log in to your <a href='%s' target='_blank'>Mollie Dashboard</a>.",
                        'mollie-payments-for-woocommerce'
                    ),
                    'https://my.mollie.com/dashboard/login'
                ), [
                        'a' => [
                                'href' => [],
                                'target' => [],
                        ],
                ]); ?>
            </li>
            <li>
                <?= wp_kses(
                    __("Navigate to <strong>Developers > API access tokens.</strong>", 'mollie-payments-for-woocommerce'),
                    [
                                'strong' => [],
                        ]
                ); ?>
            </li>
            <li>
                <?= wp_kses(
                    __("Click <strong>+ Create access token</strong>, select <strong>Standard API key</strong> as the token type, choose your payment profile and API mode (Test or Live), then click <strong>Create access token</strong>.", 'mollie-payments-for-woocommerce'),
                    [
                        'strong' => [],
                        ]
                ); ?>
            </li>
            <li>
                <?= wp_kses(
                    __(
                        "Paste the copied API key into the <strong>Live API key</strong> or <strong>Test API key</strong> fields below.",
                        'mollie-payments-for-woocommerce'
                    ),
                    [
                                'strong' => [],
                        ]
                ); ?>
            </li>
        </ol>
        <p>
            <?= esc_html(__(
                "Please note that your API keys are unique to your Mollie account and should be kept
            private to ensure the security of your transactions.",
                'mollie-payments-for-woocommerce'
            )); ?>
        </p>
        <?php
        return ob_get_clean();
    }
}
