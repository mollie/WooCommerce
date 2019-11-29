<?php

use Mollie\WooCommerce\Tests\TestCase;
use function Brain\Monkey\Functions\expect;

class Mollie_WC_Components_Assets_Test extends TestCase
{
    public function testLocalize()
    {
        /*
         * Stubs
         */
        $profileId = uniqid();
        $styles = uniqid();
        $locale = uniqid();

        /*
         * Sut
         */
        $mollieComponentsLocalizeScript = new Mollie_WC_Components_Assets();

        /*
         * Expect wp_localize_script is called with the right parameters
         */
        expect('wp_localize_script')
            ->once()
            ->with(
                'mollie-components',
                'mollieComponentsSettings',
                [
                    'merchantProfileId' => $profileId,
                    'options' => [
                        'locale' => $locale,
                        'testmode' => true,
                    ],
                    'componentSettings' => [
                        'styles' => $styles,
                    ],
                    'componentsSelectors' => [
                        'cardHolder' => '#card-holder',
                        'cardNumber' => '#card-number',
                        'expiryDate' => '#expiry-date',
                        'verificationCode' => '#verification-code',
                    ],
                ]
            );

        /*
         * Execute Test
         */
        $mollieComponentsLocalizeScript->localize($profileId, $locale, $styles);
    }
}
