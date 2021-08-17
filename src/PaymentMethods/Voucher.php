<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Voucher implements PaymentMethodI
{
    use CommonPaymentMethodTrait;

    /**
     * @var string
     */
    const MEAL = 'meal';
    /**
     * @var string
     */
    const ECO = 'eco';
    /**
     * @var string
     */
    const GIFT = 'gift';
    /**
     * @var string
     */
    const NO_CATEGORY = 'no_category';
    /**
     * @var string
     */
    const MOLLIE_VOUCHER_CATEGORY_OPTION = '_mollie_voucher_category';

    /**
     * @var string[]
     */
    private $config = [];
    /**
     * @var array[]
     */
    private $settings = [];
    /**
     * Ideal constructor.
     */
    public function __construct(PaymentMethodSettingsHandlerI $paymentMethodSettingsHandler)
    {
        $this->config = $this->getConfig();
        $this->settings = $paymentMethodSettingsHandler->getSettings($this);
    }

    private function getConfig(): array
    {
        return [
            'id' => 'voucher',
            'defaultTitle' => __('Voucher', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Select your voucher', 'mollie-payments-for-woocommerce'),
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products'
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        $paymentMethodFormFieds = [
            'mealvoucher_category_default' => [
                'title' => __('Select the default products category', 'mollie-payments-for-woocommerce'),
                'type' => 'select',
                'options' => [
                    self::NO_CATEGORY => $this->categoryName(self::NO_CATEGORY),
                    self::MEAL => $this->categoryName(self::MEAL),
                    self::ECO => $this->categoryName(self::ECO),
                    self::GIFT => $this->categoryName(self::GIFT),
                ],
                'default' => self::NO_CATEGORY,
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => __('In order to process it, all products in the order must have a category. This selector will assign the default category for the shop products', 'mollie-payments-for-woocommerce'),
                'desc_tip' => true,
            ],
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }
    private function categoryName($category)
    {
        return ucfirst(str_replace('_', ' ', $category));
    }
}
