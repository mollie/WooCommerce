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
        <h3><?= __("Mollie API Keys", 'mollie-payments-for-woocommerce'); ?></h3>
        <p>
            <?= __(
                "To start receiving payments through the Mollie plugin in your WooCommerce store,
            you'll need to connect it to your Mollie account using an API key.",
                'mollie-payments-for-woocommerce'
            ); ?>
        </p>
        <p>
            <strong>
                <?= __("How to find your API keys:", 'mollie-payments-for-woocommerce'); ?>
            </strong>
        </p>
        <ol>
            <li>
                <?= sprintf(
                    __(
                        "Log in to your <a href='%s' target='_blank'>Mollie Dashboard</a>",
                        'mollie-payments-for-woocommerce'
                    ),
                    'https://my.mollie.com/dashboard/login?lang=en'
                ); ?>
            </li>
            <li>
                <?= __("Navigate to <strong>Developers > API keys.</strong>", 'mollie-payments-for-woocommerce'); ?>
            </li>
            <li>
                <?= __("Click on <strong>Copy</strong> next to your API key.", 'mollie-payments-for-woocommerce'); ?>
            </li>
            <li>
                <?= __(
                    "Paste the copied API key into the <strong>Live API key</strong> or <strong>Test API key</strong> fields below.",
                    'mollie-payments-for-woocommerce'
                ); ?>
            </li>
        </ol>
        <p>
            <?= __(
                "Please note that your API keys are unique to your Mollie account and should be kept
            private to ensure the security of your transactions.",
                'mollie-payments-for-woocommerce'
            ); ?>
        </p>
        <?php
        return ob_get_clean();
    }
}
