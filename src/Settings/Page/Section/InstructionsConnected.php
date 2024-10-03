<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

class InstructionsConnected extends AbstractSection
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
            <?= sprintf(
                __(
                    "To start receiving payments through the Mollie plugin in your WooCommerce store, 
                you'll need to connect it to your Mollie account using an <a href='%s' target='_blank'>API key.</a>",
                    'mollie-payments-for-woocommerce'
                ),
                'https://my.mollie.com/dashboard/developers/api-keys?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner'
            ); ?>
        </p>
        <p>
            <?=
            __(
                    'Please note that your API keys are unique to your Mollie account and should be kept private 
                    to ensure the security of your transactions.', 'mollie-payments-for-woocommerce'
            );
            ?>
        </p>
        <?php
        return ob_get_clean();
    }
}
