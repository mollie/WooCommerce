<?php

class Mollie_WC_Gateway_Mealvoucher extends Mollie_WC_Gateway_Abstract
{
    const FOOD_AND_DRINKS = 'food_and_drinks';
    const HOME_AND_GARDEN = 'home_and_garden';
    const GIFTS_AND_FLOWERS = 'gifts_and_flowers';
    const NO_CATEGORY = 'no_category';

    /**
     *
     */
    public function __construct ()
    {
        $this->supports = array(
            'products',
        );

	    /* Has issuers dropdown */
	    //$this->has_fields = TRUE;

        parent::__construct();
    }

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields()
	{
		parent::init_form_fields();

		$this->form_fields = array_merge($this->form_fields, array(
			'mealvoucher_category_default' => array(
				'title'       => __('Select the default products category', 'mollie-payments-for-woocommerce'),
                'type'        => 'select',
                'options'     => array(
                    self::FOOD_AND_DRINKS => $this->categoryName(self::FOOD_AND_DRINKS),
                    self::HOME_AND_GARDEN => $this->categoryName(self::HOME_AND_GARDEN),
                    self::GIFTS_AND_FLOWERS => $this->categoryName(self::GIFTS_AND_FLOWERS)
                ),
                'default'     => self::FOOD_AND_DRINKS,
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => sprintf(
                    __('In order to process it, all products in the order must have a category. This selector will assign the default category for the shop products', 'mollie-payments-for-woocommerce')
                ),
				'desc_tip'    => true,
			),
		));
	}

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return 'mealvoucher';
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Meal and Eco voucher', 'mollie-payments-for-woocommerce');
    }

	/**
	 * @return string
	 */
	protected function getSettingsDescription() {
		return '';
	}

	/**
     * @return string
     */
    protected function getDefaultDescription ()
    {
	    /* translators: Default gift card dropdown description, displayed above issuer drop down */
	    return __('voucher', 'mollie-payments-for-woocommerce');
    }


    private function categoryName($category)
    {
        return ucfirst(str_replace('_', ' ', $category));
    }

}
