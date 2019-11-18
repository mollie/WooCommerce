<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Helper_IconFactory
{

    /**
     * @param $data
     * @return string
     */
    public function create($data)
    {
        if (!preg_match('/[^A-Za-z0-9]/', $data)) {
            $methods = $this->getApiMethods();
            if(!$methods){
                return $this->fallbackToAssetsFolder($data);
            }

            foreach ($methods as $method){
                if($method['id'] == $data){
                    return $method['image']->svg;
                }
            }
        }
        //Problems
        return $data;
    }

    /**
     * @param $data
     * @return string
     */
    protected function fallbackToAssetsFolder($data)
    {
        if ($data == PaymentMethod::CREDITCARD && !is_admin()) {
            return Mollie_WC_Plugin::getPluginUrl('assets/images/' . $data . 's.svg');
        }

        return Mollie_WC_Plugin::getPluginUrl('assets/images/' . $data . '.svg');
    }

    /**
     * @return array|bool|mixed|\Mollie\Api\Resources\BaseCollection|\Mollie\Api\Resources\MethodCollection
     */
    protected function getApiMethods()
    {
        $settings_helper = Mollie_WC_Plugin::getSettingsHelper();
        $test_mode = $settings_helper->isTestModeEnabled();

        $data_helper = Mollie_WC_Plugin::getDataHelper();
        $methods = $data_helper->getApiPaymentMethods($test_mode, $use_cache = true);
        return $methods;
    }
}

