<?php

namespace Mollie\WooCommerce\Settings\General;

use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Inpsyde\PaymentGateway\SettingsFieldRendererInterface;
class MultiCountrySettingsField implements SettingsFieldRendererInterface
{
    /**
     * @var mixed
     */
    private $paymentMethod;
    public function __construct($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }
    public function render(string $fieldId, array $fieldConfig, PaymentGateway $gateway): string
    {
        return $this->multiSelectCountry($this->paymentMethod);
    }
    public function multiSelectCountry($paymentMethod)
    {
        $selections = (array) $paymentMethod->getProperty('allowed_countries', []);
        $gatewayId = $paymentMethod->getProperty('id');
        $id = 'woocommerce_mollie_wc_gateway_' . $gatewayId . '_allowed_countries';
        $title = __('Sell to specific countries', 'mollie-payments-for-woocommerce');
        $countries = WC()->countries->countries;
        asort($countries);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php 
        echo esc_attr($id);
        ?>"><?php 
        echo esc_html($title);
        ?> </label>
            </th>
            <td class="forminp">
                <select multiple="multiple" name="<?php 
        echo esc_attr($id);
        ?>[]" style="width:350px"
                        data-placeholder="<?php 
        esc_attr_e('Choose countries&hellip;', 'mollie-payments-for-woocommerce');
        ?>"
                        aria-label="<?php 
        esc_attr_e('Country', 'mollie-payments-for-woocommerce');
        ?>" class="wc-enhanced-select">
                    <?php 
        if (!empty($countries)) {
            foreach ($countries as $key => $val) {
                echo '<option value="' . esc_attr($key) . '"' . esc_attr(wc_selected($key, $selections)) . '>' . esc_html($val) . '</option>';
            }
        }
        ?>
                </select><br/><a class="select_all button" href="#"><?php 
        esc_html_e('Select all', 'mollie-payments-for-woocommerce');
        ?></a>
                <a class="select_none button" href="#"><?php 
        esc_html_e('Select none', 'mollie-payments-for-woocommerce');
        ?></a>
            </td>
        </tr>
        <?php 
        return ob_get_clean();
    }
}
