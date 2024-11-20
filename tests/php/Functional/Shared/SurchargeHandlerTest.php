<?php


namespace php\Functional\Shared;


use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;

class SurchargeHandlerTest extends TestCase
{
    protected $pluginUrl;
    /** @var HelperMocks */
    private $helperMocks;
    /**
     * @var string
     */
    protected $pluginPath;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }
    /**
     * with all surcharge types
     * GIVEN I'm in the checkout and the surcharge is set
     * WHEN the cart is above the fee minimum
     * THEN the cart will call add_fee with the proper fee name and amount
     *
     * @test
     */
    public function addsSurchargeFeesInCheckout(){
        $cart = $this->cartMock();
        $paymentSurcharge = Surcharge::FIXED_FEE;
        $fixedFee = 10.00;
        $percentage = 0;
        $feeLimit = 1;
        $expectedLabel = 'Gateway Fee';
        $expectedAmount = 10.00;
        expect('get_option')->andReturn(
            'Gateway Fee',
            $this->helperMocks->paymentMethodSettings(
                [
                    'payment_surcharge' => $paymentSurcharge,
                    'surcharge_limit' => $feeLimit,
                    'fixed_fee' => $fixedFee,
                    'percentage' => $percentage,
                ]
            )
        );
        $testee = $this->buildTesteeMock(
            GatewaySurchargeHandler::class,
            [new Surcharge()],
            ['canProcessOrder', 'canProcessGateway', 'orderRemoveFee', 'orderAddFee']
        )->getMock();
        expect('mollieWooCommerceIsCheckoutContext')->andReturn(true);
        expect('wc_tax_enabled')->andReturn(false);
        expect('WC')->andReturn($this->wooCommerce());
        expect('is_admin')->andReturn(false);

        $cart->expects(self::once())->method('add_fee')->with($expectedLabel, $expectedAmount, true, 'standard');
        $testee->add_engraving_fees($cart);
    }

    /**
     *
     * GIVEN I'm in the checkout and the surcharge is set
     * WHEN the cart is above the fee minimum
     * THEN the cart will call add_fee with the proper fee name and amount
     *
     * @test
     */

    protected function cartMock()
    {
        return $this->createConfiguredMock(
            'WC_Cart',
            [
                'get_subtotal'=> '2.00',
                'get_subtotal_tax' => '2.50',
            ]
        );
    }
    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wooCommerce() {
        $wc = $this->createConfiguredMock(
            'WooCommerce',
            [

            ]
        );
        $wc->session = new \WC_Session();
        $wc->session->chosen_payment_method = 'mollie_wc_gateway_ideal';
        return $wc;
    }

    /**
     *
     * @throws PHPUnit_Framework_Exception
     */

    protected function mollieGateway(){
        $gateway = $this->createConfiguredMock(
            MolliePaymentGateway::class,
            [
                'getSelectedIssuer' => 'ideal_INGBNL2A',
                'get_return_url' => 'https://webshop.example.org/wc-api/',
            ]
        );
        return $gateway;
    }
}
