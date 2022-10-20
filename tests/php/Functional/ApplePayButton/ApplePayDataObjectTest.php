<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDataObjectHttp;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;


use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;


class ApplePayDataObjectTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /** @var HelperMocks */
    private $helperMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }
    /**
     *
     */
    public function testDataObjectSuccess()
    {
        $postDummyData = new postDTOTestsStubs();
        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
            'validationUrl' => $postDummyData->validationUrl
        ];
        expect('wp_verify_nonce')
            ->andReturn(true);
        $logger = $this->helperMocks->loggerMock();
        $dataObject = new ApplePayDataObjectHttp($logger);
        $dataObject->validationData();
        $nonce = $dataObject->nonce();
        self::assertEquals($_POST['woocommerce-process-checkout-nonce'], $nonce);
        $validationUrl = $dataObject->validationUrl();
        self::assertEquals($_POST['validationUrl'], $validationUrl);


        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'callerPage' => $postDummyData->callerPage,
            'simplifiedContact' => $postDummyData->simplifiedContact,
            'needShipping' => $postDummyData->needShipping,
        ];
        $dataObject->updateContactData();
        $nonce = $dataObject->nonce();
        self::assertEquals($_POST['woocommerce-process-checkout-nonce'], $nonce);
        $productId = $dataObject->productId();
        self::assertEquals($_POST['productId'], $productId);

        $simplifiedContact = $dataObject->simplifiedContact();
        $expectedContact = [
            'city' => $_POST['simplifiedContact']['locality'],
            'postcode' => $_POST['simplifiedContact']['postalCode'],
            'country' => strtoupper(
                $_POST['simplifiedContact']['countryCode']
            )
        ];
        self::assertEquals($expectedContact, $simplifiedContact);

        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'callerPage' => $postDummyData->callerPage,
            'simplifiedContact' => $postDummyData->simplifiedContact,
            'shippingMethod' => $postDummyData->shippingMethod,
        ];
        $dataObject->updateMethodData();
        $method = $dataObject->shippingMethod();
        self::assertEquals($_POST['shippingMethod'], $method);

        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'simplifiedContact' => $postDummyData->simplifiedContact,
            'shippingMethod' => $postDummyData->shippingMethod,
            'shippingContact' => $postDummyData->shippingContact,
            'billingContact' => $postDummyData->billingContact
        ];
        $dataObject->orderData('productDetail');
        $shippingAddress = $dataObject->shippingAddress();
        $shippingAddress['address_1'] = htmlspecialchars_decode($shippingAddress['address_1'], ENT_QUOTES);
        $shippingAddress['address_2'] = htmlspecialchars_decode($shippingAddress['address_2'], ENT_QUOTES);
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
        self::assertEquals($expectedAddress, $shippingAddress);
    }

    public function testDataObjectError()
    {
        $postDummyData = new postDTOTestsStubs();
        $logger = $this->helperMocks->loggerMock();
        $dataObject = new ApplePayDataObjectHttp($logger);
        expect('wp_verify_nonce')
            ->andReturn(true);
        expect('mollieWooCommerceDebug')
            ->withAnyArgs();
        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
        ];
        $dataObject->validationData();
        $expectedErrorsIndex = [['errorCode' => 'unknown']];
        self::assertEquals($expectedErrorsIndex, $dataObject->errors());

        $_POST = [
            'woocommerce-process-checkout-nonce' => '',
            'validationUrl' => $postDummyData->validationUrl
        ];
        $dataObject->validationData();
        $expectedErrorsValue = [['errorCode' => 'unknown']];
        self::assertEquals($expectedErrorsValue, $dataObject->errors());

        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
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
        $dataObject->updateContactData();
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
        self::assertEquals($expectedErrorsContact, $dataObject->errors());
        /*
         * Execute Test
         */
    }


    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        when('__')->returnArg(1);
    }
}
