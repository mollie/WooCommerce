<?php # -*- coding: utf-8 -*-

namespace MollieTests\Unit;


use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use MollieTests\TestCase;
use Mollie_WC_Helper_IconFactory as Testee;



/**
 * Class Mollie_WC_Helper_IconFactory_Test
 */
class Mollie_WC_Helper_iconFactory_Test extends TestCase
{

    /**
     * @test
     * Test iconFactory::create returns imageUrl from Api when it's set.
     */
    public function createReturnsUrlFromApi()
    {
        /*
         * Prepare Stubs
         */
        $links = new \stdClass();
        $methods = new MethodCollection(13, $links);
        $client = $this
            ->buildTesteeMock(
                MollieApiClient::class,
                [],
                []
            )
            ->getMock();
        $methodIdeal = new Method($client);
        $methodIdeal->id = "ideal";
        $methodIdeal->image = json_decode('{
                            "size1x": "https://mollie.com/external/icons/payment-methods/ideal.png",
                            "size2x": "https://mollie.com/external/icons/payment-methods/ideal%402x.png",
                            "svg": "https://mollie.com/external/icons/payment-methods/ideal.svg"
                            }');
        $methods[] = $methodIdeal;

        $methodCreditcard = new Method($client);
        $methodCreditcard->id = "creditcard";
        $methodCreditcard->image = json_decode('{
                            "size1x": "https://mollie.com/external/icons/payment-methods/creditcard.png",
                            "size2x": "https://mollie.com/external/icons/payment-methods/creditcard%402x.png",
                            "svg": "https://mollie.com/external/icons/payment-methods/creditcard.svg"
                            }');
        $methods[] = $methodCreditcard;
        $methods_cleaned = array();
        foreach ( $methods as $method ) {

            $public_properties = get_object_vars( $method ); // get only the public properties of the object
            $methods_cleaned[] = $public_properties;
        }
        $methods = $methods_cleaned;
        $paymentMethod = 'creditcard';
        $imageUrlexpected = 'https://mollie.com/external/icons/payment-methods/creditcard.svg';

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            ['getApiMethods']
        );

        /*
         * Expect to call getApiMethods and return the methods collection
         */
        $testee
            ->expects($this->once())
            ->method('getApiMethods')
            ->willReturn($methods);

        /*
         * Execute test
         */
        $result = $testee->create($paymentMethod);
        self::assertEquals($imageUrlexpected, $result);
    }

    /**
     * @test
     * Test iconFactory::create returns imageUrl from local Assets when Api fails.
     */
    public function createReturnsUrlFromAssets()
    {
        /*
         * Prepare Stubs
         */
        $paymentMethod = 'creditcard';
        $imageUrlexpected = 'plugin/assets/images/' . $paymentMethod . 's.svg';

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            ['getApiMethods', 'fallbackToAssetsFolder']
        );

        /*
         * Expect to call getApiMethods and get null, so the fallback
         * to the local assets url will be triggered
         */
        $testee
            ->expects($this->once())
            ->method('getApiMethods')
            ->willReturn(null);
        $testee
            ->expects($this->once())
            ->method('fallbackToAssetsFolder')
            ->willReturn($imageUrlexpected);

        /*
         * Execute test
         */
        $result = $testee->create($paymentMethod);

        self::assertEquals($imageUrlexpected, $result);
    }

}
