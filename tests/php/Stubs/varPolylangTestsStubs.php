<?php


namespace Mollie\WooCommerceTests\Stubs;
use Faker\Generator;
use Faker;

class varPolylangTestsStubs
{

    public $orderId;
    public $orderKey;
    /**
     * @var string
     */
    public $homeUrl;
    /**
     * @var string
     */
    public $apiRequestUrl;
    /**
     * @var string
     */
    public $untrailedUrl;
    /**
     * @var string
     */
    public $urlWithParams;
    /**
     * @var string
     */
    public $untrailedWithParams;
    /**
     * @var Generator
     */
    private $faker;
    /**
     * @var string
     */
    public $afterLangUrl;
    /**
     * @var string
     */
    public $result;

    public function __construct()
    {
        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
        $this->orderId = $this->faker->randomDigit;
        $this->orderKey = $this->faker->word;
        $this->homeUrl = rtrim($this->faker->url, '/\\');
        $this->apiRequestUrl = "{$this->homeUrl}/wc-api/mollie_return";
        $this->untrailedUrl = rtrim($this->apiRequestUrl, '/\\');
        $this->urlWithParams
            = "{$this->untrailedUrl}?order_id={$this->orderId}&key=wc_order_{$this->orderKey}";
        $this->untrailedWithParams = rtrim($this->urlWithParams, '/\\');
        $this->result = "{$this->apiRequestUrl}?order_id={$this->orderId}&key=wc_order_{$this->orderKey}";
    }
}
