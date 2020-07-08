<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_ApplePayButton_ApplePayDataObjectHttp;


use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;


class Mollie_WC_ApplePayButton_ApplePayDataObject_Test extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     *
     */
    public function testDataObjectSuccess()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $postValidation = [
            'nonce' => $postDummyData->nonce,
            'validationUrl' => $postDummyData->validationUrl
        ];

        $postUpdateContact = [
            'nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'callerPage' => $postDummyData->callerPage,
            'simplifiedContact' => $postDummyData->simplifiedContact,
            'needShipping' => $postDummyData->needShipping,
        ];
        $expectedContact = [
            'city' => $postUpdateContact['simplifiedContact']['locality'],
            'postcode' => $postUpdateContact['simplifiedContact']['postalCode'],
            'country' => strtoupper(
                $postUpdateContact['simplifiedContact']['countryCode']
            )
        ];

        $postUpdateMethod = [
            'nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'callerPage' => $postDummyData->callerPage,
            'simplifiedContact' => $postDummyData->simplifiedContact,
            'shippingMethod' => $postDummyData->shippingMethod,
        ];

        $postOrder = [
            'nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'simplifiedContact' => $postDummyData->simplifiedContact,
            'shippingMethod' => $postDummyData->shippingMethod,
            'shippingContact' => $postDummyData->shippingContact,
            'billingContact' => $postDummyData->billingContact
        ];
        $expectedAddress = [
            'first_name' => $postDummyData->shippingContact['givenName'],
            'last_name' => $postDummyData->shippingContact['familyName'],
            'email' => $postDummyData->shippingContact['emailAddress'],
            'phone' => $postDummyData->shippingContact['phoneNumber'],
            'address_1' => $postDummyData->shippingContact['addressLines'][0],
            'address_2' => $postDummyData->shippingContact['addressLines'][1],
            'city' => $postDummyData->shippingContact['locality'],
            'state' => $postDummyData->shippingContact['administrativeArea'],
            'postcode' => $postDummyData->shippingContact['postalCode'],
            'country' => strtoupper(
                $postDummyData->shippingContact['countryCode']
            )
        ];


        /*
         * Sut
         */
        $dataObject = new Mollie_WC_ApplePayButton_ApplePayDataObjectHttp();
        $dataObject->validationData($postValidation);

        $nonce = $dataObject->nonce;
        self::assertEquals($postValidation['nonce'], $nonce);
        $validationUrl = $dataObject->validationUrl;
        self::assertEquals($postValidation['validationUrl'], $validationUrl);

        $dataObject->updateContactData($postUpdateContact);

        $nonce = $dataObject->nonce;
        self::assertEquals($postUpdateContact['nonce'], $nonce);
        $productId = $dataObject->productId;
        self::assertEquals($postUpdateContact['productId'], $productId);
        $simplifiedContact = $dataObject->simplifiedContact;
        self::assertEquals($expectedContact, $simplifiedContact);

        $dataObject->updateMethodData($postUpdateMethod);

        $method = $dataObject->shippingMethod;

        self::assertEquals($postUpdateMethod['shippingMethod'], $method);

        $dataObject->orderData($postOrder, 'productDetail');

        $shippingAddress = $dataObject->shippingAddress;
        $shippingAddress['address_1'] = htmlspecialchars_decode($shippingAddress['address_1'], ENT_QUOTES);
        $shippingAddress['address_2'] = htmlspecialchars_decode($shippingAddress['address_2'], ENT_QUOTES);

        self::assertEquals($expectedAddress, $shippingAddress);
    }

    public function testDataObjectError()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $postMissingIndex = [
            'nonce' => $postDummyData->nonce,
        ];
        $expectedErrorsIndex = [['errorCode' => 'unknown']];

        $postMissingValue = [
            'nonce' => '',
            'validationUrl' => $postDummyData->validationUrl
        ];
        $expectedErrorsValue = [['errorCode' => 'unknown']];

        $postUpdateContact = [
            'nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'callerPage' => $postDummyData->callerPage,
            'simplifiedContact' => [
                'locality' => 'localityValue',
                'postalCode' => '',
                'countryCode' => ''
            ],
            'needShipping' => $postDummyData->needShipping,
        ];
        $expectedErrorsContact = [
            [
                'errorCode' => 'shipping Contact Invalid',
                'contactField' => 'postalCode'
            ],
            [
                'errorCode' => 'shipping Contact Invalid',
                'contactField' => 'countryCode'
            ]
        ];


        /*
         * Sut
         */
        $dataObject = new Mollie_WC_ApplePayButton_ApplePayDataObjectHttp();
        expect('mollieWooCommerceDebug')
            ->withAnyArgs();
        $dataObject->validationData($postMissingIndex);

        self::assertEquals($expectedErrorsIndex, $dataObject->errors);


        $dataObject->validationData($postMissingValue);
        self::assertEquals($expectedErrorsValue, $dataObject->errors);

        $dataObject->updateContactData($postUpdateContact);
        self::assertEquals($expectedErrorsContact, $dataObject->errors);
        /*
         * Execute Test
         */
    }


    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        when('__')->returnArg(1);
    }
}
