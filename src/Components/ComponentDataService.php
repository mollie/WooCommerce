<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Components;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Settings\Settings;
class ComponentDataService
{
    private Settings $settingsHelper;
    public function __construct(Settings $settingsHelper)
    {
        $this->settingsHelper = $settingsHelper;
    }
    /**
     * Get component data for Mollie credit card components
     *
     * @return array|null Returns component data array or null if requirements not met
     */
    public function getComponentData(): ?array
    {
        try {
            $merchantProfileId = $this->settingsHelper->mollieWooCommerceMerchantProfileId();
        } catch (ApiException $exception) {
            return null;
        }
        $mollieComponentsStylesGateways = mollieWooCommerceComponentsStylesForAvailableGateways();
        $gatewayNames = array_keys($mollieComponentsStylesGateways);
        if (!$merchantProfileId || !$mollieComponentsStylesGateways) {
            return null;
        }
        $locale = $this->getValidatedLocale();
        return ['merchantProfileId' => $merchantProfileId, 'options' => ['locale' => $locale, 'testmode' => $this->settingsHelper->isTestModeEnabled()], 'enabledGateways' => $gatewayNames, 'componentsSettings' => $mollieComponentsStylesGateways, 'componentsAttributes' => $this->getComponentAttributes(), 'messages' => $this->getComponentMessages()];
    }
    /**
     * Get component data with context-specific flags
     *
     * @param bool $isCheckout
     * @param bool $isCheckoutPayPage
     * @return array|null
     */
    public function getComponentDataWithContext(bool $isCheckout = \false, bool $isCheckoutPayPage = \false): ?array
    {
        $data = $this->getComponentData();
        if ($data === null) {
            return null;
        }
        $data['isCheckout'] = $isCheckout;
        $data['isCheckoutPayPage'] = $isCheckoutPayPage;
        return $data;
    }
    /**
     * Check if components should be loaded based on context
     *
     * @return bool
     */
    public function shouldLoadComponents(): bool
    {
        global $wp_query;
        if (!isset($wp_query)) {
            return \false;
        }
        return !is_admin() && (is_checkout() && !has_block("woocommerce/checkout") || is_checkout_pay_page());
    }
    public function isComponentsEnabled(PaymentMethodI $paymentMethod): bool
    {
        $hasComponentsEnabled = $paymentMethod->getProperty('mollie_components_enabled');
        return $hasComponentsEnabled === 'yes';
    }
    private function getValidatedLocale(): string
    {
        $locale = get_locale();
        $locale = str_replace('_formal', '', $locale);
        $allowedLocaleValues = \Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP;
        if (!in_array($locale, $allowedLocaleValues, \true)) {
            return \Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE;
        }
        return $locale;
    }
    private function getComponentAttributes(): array
    {
        return [['name' => 'cardHolder', 'label' => esc_html__('Name on card', 'mollie-payments-for-woocommerce')], ['name' => 'cardNumber', 'label' => esc_html__('Card number', 'mollie-payments-for-woocommerce')], ['name' => 'expiryDate', 'label' => esc_html__('Expiry date', 'mollie-payments-for-woocommerce')], ['name' => 'verificationCode', 'label' => esc_html__('CVC/CVV', 'mollie-payments-for-woocommerce')]];
    }
    private function getComponentMessages(): array
    {
        return ['defaultErrorMessage' => esc_html__('An unknown error occurred, please check the card fields.', 'mollie-payments-for-woocommerce')];
    }
}
