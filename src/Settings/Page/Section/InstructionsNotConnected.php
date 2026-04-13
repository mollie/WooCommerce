<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

class InstructionsNotConnected extends \Mollie\WooCommerce\Settings\Page\Section\AbstractSection
{
    public function config(): array
    {
        return [['id' => $this->settings->getSettingId('instructions'), 'type' => 'mollie_content', 'value' => $this->content()]];
    }
    protected function content(): string
    {
        ob_start();
        ?>
        <h3><?php 
        echo esc_html(__("Mollie API Keys", 'mollie-payments-for-woocommerce'));
        ?></h3>
        <p>
            <?php 
        echo esc_html(__("To start receiving payments through the Mollie plugin in your WooCommerce store,\n            you'll need to connect it to your Mollie account using an API key.", 'mollie-payments-for-woocommerce'));
        ?>
        </p>
        <p>
            <strong>
                <?php 
        echo esc_html(__("How to find your API keys:", 'mollie-payments-for-woocommerce'));
        ?>
            </strong>
        </p>
        <ol>
            <li>
                <?php 
        echo wp_kses(sprintf(__("Don’t have a Mollie account yet? <a href='%s' target='_blank'>Get started with Mollie today.</a>", 'mollie-payments-for-woocommerce'), apply_filters('mollie-payments-for-woocommerce_signup_url', 'https://my.mollie.com/dashboard/signup?utm_campaign=GLO_Q4__Woo-Signup-tracker&utm_medium=referral&utm_source={woodashboard}&campaign_name=GLO_Q4__Woo-Signup-tracker')), ['a' => ['href' => [], 'target' => []]]);
        ?>
            </li>
            <li>
                <?php 
        echo wp_kses(sprintf(__("Log in to your <a href='%s' target='_blank'>Mollie Dashboard</a>.", 'mollie-payments-for-woocommerce'), 'https://my.mollie.com/dashboard/login'), ['a' => ['href' => [], 'target' => []]]);
        ?>
            </li>
            <li>
                <?php 
        echo wp_kses(__("Navigate to <strong>Developers > API keys.</strong>", 'mollie-payments-for-woocommerce'), ['strong' => []]);
        ?>
            </li>
            <li>
                <?php 
        echo wp_kses(__("Click on <strong>Copy</strong> next to your API key.", 'mollie-payments-for-woocommerce'), ['strong' => []]);
        ?>
            </li>
            <li>
                <?php 
        echo wp_kses(__("Paste the copied API key into the <strong>Live API key</strong> or <strong>Test API key</strong> fields below.", 'mollie-payments-for-woocommerce'), ['strong' => []]);
        ?>
            </li>
        </ol>
        <p>
            <?php 
        echo esc_html(__("Please note that your API keys are unique to your Mollie account and should be kept\n            private to ensure the security of your transactions.", 'mollie-payments-for-woocommerce'));
        ?>
        </p>
        <?php 
        return ob_get_clean();
    }
}
