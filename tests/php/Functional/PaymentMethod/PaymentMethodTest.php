<?php


namespace php\Functional\PaymentMethod;


use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Payment\PaymentFieldsService;
use Mollie\WooCommerce\PaymentMethods\Creditcard;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;

class PaymentMethodTest extends TestCase
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
        $this->pluginUrl = "http://pluginUrl.com";
        $this->pluginPath = "/plugin/path/";
        $this->helperMocks = new HelperMocks();
    }
    /**
     * For every Payment Method
     * GIVEN the setting show icon is enabled and show custom icon is disabled
     * WHEN I ask for the paymentMethod icon
     * THEN then I receive the default url where the icon can be found
     *
     * @test
     */
    public function showDefaultIcon(){
        $paymentMethodName = 'ideal';
        $urlBuild = $this->pluginUrl . '/' . 'public/images/'. $paymentMethodName . '.svg';
        $settingOptionName = 'mollie_wc_gateway_'. $paymentMethodName . '_settings';
        $testee = $this->paymentMethodBuilder($paymentMethodName);
        expect('get_option')
            ->with($settingOptionName)
            ->andReturn(
                $this->helperMocks->paymentMethodMergedProperties($paymentMethodName, false, true)
            );
        expect('esc_attr')->with($urlBuild)->andReturn($urlBuild);

        $expectedUrl = '<img src="' . $urlBuild . '" class="mollie-gateway-icon" />';
        $iconUrl = $testee->getIconUrl();
        self::assertEquals($expectedUrl, $iconUrl);
    }

    /**
     * For every Payment Method
     * GIVEN the setting show icon is enabled and show custom icon is enabled
     * WHEN I ask for the paymentMethod icon BUT the file does not exists
     * THEN I receive the default url where the icon can be found
     *
     * @test
     */
    public function fallbackToDefaultIcon(){
        $paymentMethodName = 'ideal';
        $urlBuild = $this->pluginUrl . '/' . 'public/images/'. $paymentMethodName . '.svg';
        $settingOptionName = 'mollie_wc_gateway_'. $paymentMethodName . '_settings';
        $testSettings = [
            'enable_custom_logo' => true,
            'iconFileUrl' => 'http://iconfileurl.com',
            'iconFilePath' => '/icon/path/',
        ];
        $testee = $this->paymentMethodBuilder($paymentMethodName, $testSettings);
        expect('get_option')
            ->with($settingOptionName)
            ->andReturn(
                $this->helperMocks->paymentMethodMergedProperties($paymentMethodName, false, true, $testSettings)
            );
        expect('esc_attr')->withAnyArgs()->andReturn($urlBuild);

        $expectedUrl = '<img src="' . $urlBuild . '" class="mollie-gateway-icon" />';
        $iconUrl = $testee->getIconUrl();
        self::assertEquals($expectedUrl, $iconUrl);
    }
    /**
     * For every Payment Method
     * GIVEN the setting show icon is enabled and show custom icon is enabled
     * WHEN I ask for the paymentMethod icon AND the file is found
     * THEN I receive the custom url saved in the iconFileUrl option where the icon can be found
     *
     * @test
     */
    public function showCustomIcon(){
        $paymentMethodName = 'ideal';
        $urlBuild = $this->pluginUrl . '/' . 'public/images/' . $paymentMethodName . '.svg';
        $settingOptionName = 'mollie_wc_gateway_' . $paymentMethodName . '_settings';
        $testSettings = [
            'enable_custom_logo' => true,
            'iconFileUrl' => 'http://iconfileurl.com',
            'iconFilePath' => '/icon/path',
        ];
        $testee = $this->paymentMethodBuilder($paymentMethodName, $testSettings);
        expect('get_option')
            ->with($settingOptionName)
            ->andReturn(
                $this->helperMocks->paymentMethodMergedProperties($paymentMethodName, false, true, $testSettings)
            );
        expect('file_exists')->with($testSettings['iconFilePath'])->andReturn(true);
        expect('esc_attr')->withAnyArgs()->andReturn($urlBuild);

        $expectedUrl = '<img src="' . $urlBuild . '" class="mollie-gateway-icon" />';
        $iconUrl = $testee->getIconUrl();
        self::assertEquals($expectedUrl, $iconUrl);
    }
    /**
     *
     * GIVEN the setting show icon is enabled and the icon selector in creditcard is enabled
     * WHEN I ask for the paymentMethod icon
     * THEN I receive the svg build with my selected icons
     *
     * @test
     */
    public function showIconSelectionCreditCard(){
        $paymentMethodName = 'creditcard';
        $settingOptionName = 'mollie_wc_gateway_' . $paymentMethodName . '_settings';
        $testSettings = [
            'mollie_creditcard_icons_enabler'=>true,
            'mollie_creditcard_icons_amex' => 'yes',
        ];
        $assetsImagesPath
            = $this->pluginPath . '/' . 'public/images/';

        $testee = $this->paymentMethodBuilder($paymentMethodName, $testSettings);
        expect('is_admin')->andReturn(false);
        expect('get_option')
            ->with($settingOptionName)
            ->andReturn(
                $this->helperMocks->paymentMethodMergedProperties($paymentMethodName, false, true, $testSettings)
            );
        expect('get_transient')->andReturn(false);
        expect('file_get_contents')->with($assetsImagesPath . 'amex')->andReturn('<svg width="33"></svg>');
        expect('set_transient')->andReturn(true);


        $expectedUrl = '<svg width="33" height="24" class="mollie-gateway-icon" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><svg x="0" width="33"></svg></svg>';
        $iconUrl = $testee->getIconUrl();
        self::assertEquals($expectedUrl, $iconUrl);
    }



    public function paymentMethodBuilder($paymentMethodName, $testSettings = [])
    {
        $iconFactory = new IconFactory($this->pluginUrl, $this->pluginPath);
        $settingsHelper = $this->helperMocks->settingsHelper();
        $paymentFieldsService = new PaymentFieldsService($this->helperMocks->dataHelper());
        $surchargeService = new Surcharge();

        $paymentMethod = $this->buildTesteeMock(
            Creditcard::class,
            [$iconFactory, $settingsHelper, $paymentFieldsService, $surchargeService],
            ['getConfig', 'getSettings', 'getInitialOrderStatus', 'getIdFromConfig']
        )->getMock();
        $paymentMethod->config = $this->helperMocks->gatewayMockedOptions($paymentMethodName, false, true);
        $paymentMethod->settings = $this->helperMocks->paymentMethodSettings();
        $paymentMethod->id = $paymentMethod->config['id'];

        return $paymentMethod;
    }
}
