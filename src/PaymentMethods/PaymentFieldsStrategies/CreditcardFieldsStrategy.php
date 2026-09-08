<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
class CreditcardFieldsStrategy extends \Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    public function renderFields(): string
    {
        if (!$this->isMollieComponentsEnabled($this->deprecatedHelperGateway->paymentMethod())) {
            return $this->gatewayDescription;
        }
        $allowedHtml = $this->svgAllowedHtml();
        $output = $this->gatewayDescription;
        $output .= '<div class="mollie-components"></div>';
        $output .= '<p class="mollie-components-description">';
        $output .= sprintf(esc_html__('%1$s Secure payments provided by %2$s', 'mollie-payments-for-woocommerce'), wp_kses($this->lockIcon($this->dataHelper), $allowedHtml), wp_kses($this->mollieLogo($this->dataHelper), $allowedHtml));
        $output .= '</p>';
        return $output;
    }
    public function getFieldMarkup($gateway, $dataHelper)
    {
        if (!$this->isMollieComponentsEnabled($gateway->paymentMethod())) {
            return \false;
        }
        $descriptionTranslated = __('Secure payments provided by', 'mollie-payments-for-woocommerce');
        $componentsDescription = "{$this->lockIcon($dataHelper)} {$descriptionTranslated} {$this->mollieLogo($dataHelper)}";
        return "<div class='payment_method_mollie_wc_gateway_creditcard'><div class='mollie-components'></div><p class='mollie-components-description'>{$componentsDescription}</p></div>";
    }
    protected function isMollieComponentsEnabled(PaymentMethodI $paymentMethod): bool
    {
        $hasComponentsEnabled = $paymentMethod->getProperty('mollie_components_enabled');
        return $hasComponentsEnabled === 'yes';
    }
    protected function lockIcon($dataHelper)
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file is OK.
        return file_get_contents($dataHelper->pluginPath() . '/' . 'public/images/lock-icon.svg');
    }
    protected function mollieLogo($dataHelper)
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local file is OK.
        return file_get_contents($dataHelper->pluginPath() . '/' . 'public/images/mollie-logo.svg');
    }
    /**
     * @return array
     */
    protected function svgAllowedHtml(): array
    {
        return ['svg' => ['class' => [], 'width' => [], 'height' => [], 'viewbox' => [], 'xmlns' => [], 'aria-hidden' => [], 'role' => []], 'g' => [], 'defs' => [], 'use' => ['xlink:href' => [], 'clip-path' => [], 'fill' => [], 'stroke' => [], 'stroke-width' => []], 'path' => ['fill' => [], 'd' => [], 'id' => []], 'clipPath' => ['id' => []]];
    }
}
