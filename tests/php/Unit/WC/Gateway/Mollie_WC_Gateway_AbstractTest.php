<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Gateway;

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use Mollie\WooCommerceTests\Stubs\varPolylangTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Gateway_Abstract as Testee;
use UnexpectedValueException;
use WooCommerce;


use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Actions\expectDone;

/**
 * Class Mollie_WC_Helper_Settings_Test
 */
class Mollie_WC_Gateway_Abstract_Test extends TestCase
{
    /* -----------------------------------------------------------------
       getIconUrl Tests
       -------------------------------------------------------------- */

    /**
     * Test getIconUrl will return the url string
     *
     * @test
     */
    public function getIconUrlReturnsUrlString()
    {
        /*
         * Setup Stubs to mock the API call
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
        //this part is the same code as data::getApiPaymentMethods
        $methods[] = $methodIdeal;
        $methods_cleaned = array();
        foreach ( $methods as $method ) {
            $public_properties = get_object_vars( $method ); // get only the public properties of the object
            $methods_cleaned[] = $public_properties;
        }
        $methods = $methods_cleaned;
        /*
        * Expect to call availablePaymentMethods() function and return a mock of one method with id 'ideal'
        */
        expect('mollieWooCommerceAvailablePaymentMethods')
            ->once()
            ->withNoArgs()
            ->andReturn($methods);

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['getMollieMethodId']
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Expect testee is has id 'ideal'
        */
        $testee
            ->expects($this->once())
            ->method('getMollieMethodId')
            ->willReturn('ideal');

        /*
         * Execute test
         */
        $result = $testee->getIconUrl();

        self::assertEquals('https://mollie.com/external/icons/payment-methods/ideal.svg', $result);

    }
    /**
     * Test getIconUrl will return the url string even if Api returns empty
     *
     * @test
     */
    public function getIconUrlReturnsUrlStringFromAssets()
    {

        /*
        * Expect to call availablePaymentMethods() function and return false from the API
        */
        expect('mollieWooCommerceAvailablePaymentMethods')
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['getMollieMethodId']
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Expect testee is has id 'ideal'
        */
        $testee
            ->expects($this->once())
            ->method('getMollieMethodId')
            ->willReturn('ideal');

        /*
         * Execute test
         */
        $result = $testee->getIconUrl();

        self::assertStringEndsWith('public/images/ideal.svg', $result);

    }

    /**
     * Test associativePaymentMethodsImages returns associative array
     * ordered by id (method name) of image urls
     *
     * @test
     */
    public function associativePaymentMethodsImagesReturnsArrayOrderedById()
    {
        /*
         * Setup stubs
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
        $methods_cleaned = array();
        foreach ( $methods as $method ) {
            $public_properties = get_object_vars( $method ); // get only the public properties of the object
            $methods_cleaned[] = $public_properties;
        }
        $methods = $methods_cleaned;
        $paymentMethodsImagesResult = [
            "ideal" => $methodIdeal->image
        ];
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            []
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Execute Test
         */
        $result = $testee->associativePaymentMethodsImages($methods);

        self::assertEquals($paymentMethodsImagesResult,$result );
    }

    /**
     * Test associativePaymentMethodsImages returns array ordered by id of payment method to access images directly
     *
     * @test
     */
    public function associativePaymentMethodsImagesReturnsEmptyArrayIfApiFails()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            []
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Execute Test
         */
        $emptyArr = [];
        $result = $testee->associativePaymentMethodsImages($emptyArr);

        self::assertEquals($emptyArr, $result);
    }



    /* -----------------------------------------------------------------
      getReturnRedirectUrlForOrder Tests
      -------------------------------------------------------------- */
    /**
     * Test getReturnRedirectUrlForOrder
     * given order does NOT need payment
     * then will fire
     * do_action('mollie-payments-for-woocommerce_customer_return_payment_success', $order)
     *
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    public function testgetReturnRedirectUrlForOrderHookIsSuccessWhenNeedPaymentFalse()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['orderNeedsPayment', 'get_return_url']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Setup Stubs
        */
        $order = $this->createMock(\WC_Order::class);

        /*
        * Expectations
        */
        expect('mollieWooCommerceOrderId')
            ->once()
            ->with($order);
        expect('mollieWooCommerceDebug')
            ->once();
        $testee
            ->expects($this->once())
            ->method('orderNeedsPayment')
            ->with($order)
            ->willReturn(false);
        expectDone('mollie-payments-for-woocommerce_customer_return_payment_success')
            ->once()
            ->with($order);
        $testee
            ->expects($this->once())
            ->method('get_return_url')
            ->with($order);
        /*
         * Execute test
         */
        $testee->getReturnRedirectUrlForOrder($order);
    }

    /**
     * Test getReturnRedirectUrlForOrder
     * given order does need payment
     * When there is exception thrown
     * then will fire
     * do_action('mollie-payments-for-woocommerce_customer_return_payment_failed', $order)
     *
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    public function testgetReturnRedirectUrlForOrderHookIsFailedWhenNeedPaymentException()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['orderNeedsPayment', 'get_return_url', 'paymentObject', 'activePaymentObject']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Setup Stubs
        */
        $order = $this->createMock(\WC_Order::class);
        $payment = $this->createMock(\Mollie_WC_Payment_Object::class);

        /*
        * Expectations
        */
        expect('mollieWooCommerceOrderId')
            ->once()
            ->with($order);
        expect('mollieWooCommerceDebug')
            ->twice();
        $testee
            ->expects($this->once())
            ->method('orderNeedsPayment')
            ->with($order)
            ->willReturn(true);
        $testee
            ->expects($this->once())
            ->method('paymentObject')
            ->willReturn($payment);
        $testee
            ->expects($this->once())
            ->method('activePaymentObject')
            ->willThrowException(new UnexpectedValueException());
        expect('mollieWooCommerceNotice')
            ->once();
        expectDone('mollie-payments-for-woocommerce_customer_return_payment_failed')
            ->once()
            ->with($order);
        $testee
            ->expects($this->once())
            ->method('get_return_url')
            ->with($order);
        /*
         * Execute test
         */
        $testee->getReturnRedirectUrlForOrder($order);
    }
}
