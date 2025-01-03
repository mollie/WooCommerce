<?php
# -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\WC_Order_Item_Product;
use Mollie\WooCommerceTests\Stubs\WC_Settings_API;
use Mollie\WooCommerceTests\Stubs\WC_Product;
use Mollie\WooCommerceTests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

/**
 * Class Mollie_WC_Plugin_Test
 */
class RequestObjectTest extends TestCase
{
    /** @var HelperMocks */
    private $helperMocks;
    public $products = [
        '1' => [
            'id' => 1,
            'name' => 'product 1',
            'price' => '11.123'
        ],
        '2' => [
            'id' => 2,
            'name' => 'product 2',
            'price' => '9.00'
        ],
        '3' => [
            'id' => 3,
            'name' => 'product 3',
            'price' => '26.1234'
        ],
        '4' => [
            'id' => 4,
            'name' => 'product 4',
            'price' => '30.01'
        ]
    ];

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }

    /**
     * GIVEN A PAYMENT REQUEST
     * WHEN THE TOTAL AMOUNT HAS DECIMALS
     * THEN THE TOTAL AMOUNT HAS TO BE EQUAL TO THE SUM OF THE LINES
     *
     *
     * @test
     */
    public function processPayment_decimals_and_taxes_request_no_mismatch()
    {
        $testDataSet = $this->generateTestDataSet();

        foreach ($testDataSet as $order) {
            $this->executeTest($order);
        }
    }

    private function generateTestDataSet(): array
    {
        $products = $this->products;
        return [
            $this->wcOrder('76.25', [
                $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1),
                $this->wcOrderItem($products['2']['id'], $products['2']['name'], $products['2']['price'], 1),
                $this->wcOrderItem($products['3']['id'], $products['3']['name'], $products['3']['price'], 1),
                $this->wcOrderItem($products['4']['id'], $products['4']['name'], $products['4']['price'], 1)
            ]),
            $this->wcOrder('46.2464', [
                $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1),
                $this->wcOrderItem($products['2']['id'], $products['2']['name'], $products['2']['price'], 1),
                $this->wcOrderItem($products['3']['id'], $products['3']['name'], $products['3']['price'], 1)
            ]),
             $this->wcOrder('20.1234', [
                 $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1),
                 $this->wcOrderItem($products['2']['id'], $products['2']['name'], $products['2']['price'], 1)
             ]),
             $this->wcOrder('11.123', [
                 $this->wcOrderItem($products['1']['id'], $products['1']['name'], $products['1']['price'], 1)
             ])
        ];
    }

    public function executeTest($order)
    {
        $customerId = 1;
        $wrapperMock = $this->createMock(WC_Product::class);

        $callback = function ($productId) {
            $price = $this->products[$productId]['price'];
            $productMock = $this->wcProduct($productId, $price);

            return $productMock;
        };
        $wrapperMock->method('getProduct')->willReturnCallback($callback);

        stubs([
                  'wc_get_payment_gateway_by_order' => $this->mollieGateway(
                      'ideal',
                      $this->helperMocks->paymentService()
                  ),
                  'add_query_arg' => 'https://webshop.example.org/wc-api/mollie_return?order_id=1&key=wc_order_hxZniP1zDcnM8',
                  'WC' => $this->wooCommerce(),
                  'get_option' => ['enabled' => false],
                  'wc_get_product' => $wrapperMock,
                  'wc_clean' => false,
                  'wp_parse_url' => null,
                  'wp_strip_all_tags' => null
              ]);
        $apiClientMock = $this->createConfiguredMock(
            MollieApiClient::class,
            []
        );
        $orderLines = new OrderLines($this->helperMocks->dataHelper($apiClientMock), $this->helperMocks->pluginId());
        $testee = new MollieOrder(
            $this->helperMocks->orderItemsRefunder(),
            'order',
            $this->helperMocks->pluginId(),
            $this->helperMocks->apiHelper($apiClientMock),
            $this->helperMocks->settingsHelper(),
            $this->helperMocks->dataHelper($apiClientMock),
            $this->helperMocks->loggerMock(),
            $orderLines
        );

        /*
        * Execute Test
        */

        $arrayResult = $testee->getPaymentRequestData($order, $customerId);
        $expectedResult = $this->noMismatchError($arrayResult);
        $this->assertTrue($expectedResult);
    }


    protected function setUp(): void
    {
        $_POST = [];
        parent::setUp();

        when('__')->returnArg(1);
    }

    protected function mollieGateway($paymentMethodName, $testee, $isSepa = false, $isSubscription = false)
    {
        return $this->helperMocks->mollieGatewayBuilder($paymentMethodName, $isSepa, $isSubscription, [], $testee);
    }


    /**
     *
     * @param $total
     * @param $items
     * @return (object&MockObject)|MockObject|\WC_Order|(\WC_Order&object&MockObject)|(\WC_Order&MockObject)
     */
    private function wcOrder($total, $items)
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'get_id' => 1,
                'get_order_key' => 'wc_order_hxZniP1zDcnM8',
                'get_total' => $total,
                'get_items' => $items,
                'get_billing_first_name' => 'billingggivenName',
                'get_billing_last_name' => 'billingfamilyName',
                'get_billing_email' => 'billingemail',
                'get_shipping_first_name' => 'shippinggivenName',
                'get_shipping_last_name' => 'shippingfamilyName',
                'get_billing_address_1' => 'shippingstreetAndNumber',
                'get_billing_address_2' => 'billingstreetAdditional',
                'get_billing_postcode' => 'billingpostalCode',
                'get_billing_city' => 'billingcity',
                'get_billing_state' => 'billingregion',
                'get_billing_country' => 'billingcountry',
                'get_billing_phone' => '+34345678900',
                'get_billing_company' => 'billingorganizationName',
                'get_shipping_address_1' => 'shippingstreetAndNumber',
                'get_shipping_address_2' => 'shippingstreetAdditional',
                'get_shipping_postcode' => 'shippingpostalCode',
                'get_shipping_city' => 'shippingcity',
                'get_shipping_state' => 'shippingregion',
                'get_shipping_country' => 'shippingcountry',
                'get_shipping_methods' => false,
                'get_order_number' => 1,
                'get_payment_method' => 'mollie_wc_gateway_ideal',
                'get_currency' => 'EUR',
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wooCommerce()
    {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [
                'api_request_url' => 'https://webshop.example.org/wc-api/mollie_return'
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrderItem($id, $name, $price, $quantity)
    {
        $item = new \WC_Order_Item_Product();

        $item['quantity'] = $quantity;
        $item['variation_id'] = null;
        $item['product_id'] = $id;
        $item['line_subtotal_tax'] = 0;
        $item['line_total'] = $price;
        $item['line_subtotal'] = $price;
        $item['line_tax'] = 0;
        $item['tax_status'] = '';
        $item['total'] = $price;
        $item['name'] = $name;

        return $item;
    }

    private function noMismatchError(array $arrayResult)
    {
        //array result total equals the sum of the lines
        $total = ($arrayResult['amount']['value']) * 1000;
        $lines = $arrayResult['lines'];
        $sum = 0.0;
        foreach ($lines as $line) {
            $lineValue = ($line['totalAmount']['value']) * 1000;
            $sum += $lineValue;
        }
        return $total == $sum;
    }
}



