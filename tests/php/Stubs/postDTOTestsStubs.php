<?php


namespace Mollie\WooCommerceTests\Stubs;

use Faker;
use Faker\Generator;

class postDTOTestsStubs
{
    /**
     * @var string
     */
    public $nonce;
    /**
     * @var string
     */
    public $validationUrl;
    /**
     * @var integer
     */
    public $productId;
    /**
     * @var integer
     */
    public $productQuantity;
    /**
     * @var string between productDetail and cart
     */
    public $callerPage;
    /**
     * @var boolean
     */
    public $needShipping;
    /**
     * @var array
     */
    public $simplifiedContact;
    /**
     * @var array
     */
    public $shippingContact;
    /**
     * @var array
     */
    public $billingContact;
    /**
     * @var array
     */
    public $shippingMethod;
    /**
     * @var string
     */
    public $siteUrl;
    /**
     * @var int
     */
    public $productPrice;
    /**
     * @var Generator
     */
    private $faker;

    public function __construct()
    {
        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
        $this->nonce = $this->faker->word;
        $this->validationUrl = $this->faker->url;
        $this->productId = $this->faker->numberBetween(1, 100);
        $this->productQuantity = $this->faker->numberBetween(1, 100);
        $this->callerPage = 'productDetail';
        $this->needShipping = true;
        $this->shippingContact = [
            'givenName' => $this->faker->word,
            'familyName' => $this->faker->word,
            'emailAddress' => $this->faker->email,
            'phoneNumber' => $this->faker->phoneNumber,
            'addressLines' => [
                0 => [$this->faker->address],
                1 => [$this->faker->address]
            ],
            'locality' => $this->faker->word,
            'administrativeArea' => $this->faker->word,
            'postalCode' => $this->faker->word,
            'countryCode' => $this->faker->word
        ];
        $this->simplifiedContact = [
            'locality' => $this->shippingContact['locality'],
            'postalCode' => $this->shippingContact['postalCode'],
            'countryCode' => $this->shippingContact['countryCode']
        ];
        $this->shippingMethod = [
            'amount' => $this->faker->randomNumber(),
            'detail' => '',
            'identifier' => $this->faker->word,
            'label' => $this->faker->word
        ];
        $this->billingContact = $this->shippingContact;
        $this->siteUrl = $this->faker->url;
        $this->productPrice = $this->faker->numberBetween(1, 100);
    }
}
