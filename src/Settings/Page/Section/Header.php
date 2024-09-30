<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

class Header extends AbstractSection
{
    public function config(): array
    {
        return [
                [
                        'id' => $this->settings->getSettingId('header'),
                        'type' => 'mollie_content',
                        'value' => $this->content()
                ]
        ];
    }

    public function styles(): string
    {
        ob_start();
        ?>
        <style>
            .mollie-section--header {
                display: flex;
                justify-content: center;
            }

            .mollie-settings-header {
                display: flex;
                flex-direction: column;
                gap: 12px;
                align-items: center;
            }

            .mollie-settings-header__image {
                width: 200px;
            }

            .mollie-settings-header__description {
                margin: 0;
            }

            .mollie-settings-header__buttons {
                display: flex;
                gap: 12px;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    protected function content(): string
    {
        ob_start();
        ?>
        <div class="mollie-section mollie-section--header">
            <div class="mollie-settings-header">
                <img class="mollie-settings-header__image" src="<?= $this->pluginUrl; ?>public/images/logo/black.svg"
                     alt=""/>
                <p class="mollie-settings-header__description">
                    <strong>
                        <?= __(
                                'Effortless payments for your customers, designed for growth',
                                'mollie-payments-for-woocommerce'
                        ); ?>
                    </strong>
                </p>
                <div class="mollie-settings-header__buttons">
                    <a href="https://help.mollie.com/hc/en-us/sections/12858723658130-Mollie-for-WooCommerce"
                       target="_blank" class="button-secondary">
                        <?= __('Mollie Plugin Documentation', 'mollie-payments-for-woocommerce'); ?>
                    </a>
                    <a href="https://www.mollie.com/contact/merchants" target="_blank" class="button-secondary">
                        <?= __('Contact Mollie Support', 'mollie-payments-for-woocommerce'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
