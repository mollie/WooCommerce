<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Faker;
use Faker\Generator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;


class Mollie_WC_ApplePayButton_ResponsesToApple_Test extends TestCase
{
    use MockeryPHPUnitIntegration;
    /**
     * @var Generator
     */
    protected $faker;

    /**
     *
     */
    public function testappleFormattedResponseWithoutShippingMethod()
    {
        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
        $subtotal = $this->faker->numberBetween(1, 200);
        $taxes = $this->faker->numberBetween(1, 200);
        $total = $this->faker->numberBetween(1, 200);
        $totalLabel = $this->faker->word;
        $paymentDetails = [
            'subtotal' => $subtotal,
            'shipping' => [
                'amount' =>  null,
                'label' =>  null
            ],
            'shippingMethods' => null,
            'taxes' => $taxes,
            'total' => $total
        ];
        $expectedResponse = [
            'newLineItems'=>[
                [
                    "label" => 'Subtotal',
                    "amount" => $subtotal,
                    "type" => 'final'
                ],
                [
                    "label" => 'Estimated Tax',
                    "amount" => $taxes,
                    "type" => 'final'
                ]
            ],
            'newTotal'=>[
                "label" => $totalLabel,
                "amount" => $total,
                "type" => 'final'
            ]

        ];

        expect('get_bloginfo')
            ->once()
            ->with('name')
            ->andReturn($totalLabel);
        /*
         * Sut
         */
        $responsesTemplate = new \Mollie_WC_ApplePayButton_ResponsesToApple();
        $response = $responsesTemplate->appleFormattedResponse($paymentDetails);



        self::assertEquals($response, $expectedResponse);
    }
    public function testappleFormattedResponseWithShippingMethod()
    {
        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
        $subtotal = $this->faker->numberBetween(1, 200);
        $taxes = $this->faker->numberBetween(1, 200);
        $total = $this->faker->numberBetween(1, 200);
        $shippingTotal = $this->faker->numberBetween(1, 200);
        $totalLabel = $this->faker->word;
        $shippingLabel = $this->faker->word;
        $paymentDetails = [
            'subtotal' => $subtotal,
            'shipping' => [
                'amount' =>  $shippingTotal,
                'label' =>  $shippingLabel
            ],
            'shippingMethods' => null,
            'taxes' => $taxes,
            'total' => $total
        ];
        $expectedResponse = [
            'newLineItems'=>[
                [
                    "label" => 'Subtotal',
                    "amount" => $subtotal,
                    "type" => 'final'
                ],
                [
                    "label" => $shippingLabel,
                    "amount" => $shippingTotal,
                    "type" => 'final'
                ],
                [
                    "label" => 'Estimated Tax',
                    "amount" => $taxes,
                    "type" => 'final'
                ]
            ],
            'newTotal'=>[
                "label" => $totalLabel,
                "amount" => $total,
                "type" => 'final'
            ]

        ];

        expect('get_bloginfo')
            ->once()
            ->with('name')
            ->andReturn($totalLabel);
        /*
         * Sut
         */
        $responsesTemplate = new \Mollie_WC_ApplePayButton_ResponsesToApple();
        $response = $responsesTemplate->appleFormattedResponse($paymentDetails);



        self::assertEquals($response, $expectedResponse);
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
