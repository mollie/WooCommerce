<?php


namespace Mollie\WooCommerceTests\Functional;


use Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerce\Gateway\Refund\OrderItemsRefunder;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\PaymentMethods\Ideal;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\Stubs\Status;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;

class HelperMocks extends TestCase
{



    public function pluginId()
    {
        return 'mollie-payments-for-woocommerce';
    }
    public function pluginVersion()
    {
        return '7.0.0';
    }
    public function pluginPath()
    {
        return 'plugin/path';
    }
    public function pluginUrl()
    {
        return 'https://pluginUrl.com';
    }
    public function statusHelper()
    {
        return new Status();
    }

    public function genericPaymentGatewayMock()
    {
        $mock = $this->getMockBuilder(PaymentGateway::class)
            ->addMethods(['supports'])
            ->onlyMethods(['get_return_url'])
            ->disableOriginalConstructor()
            ->getMock();
        return $mock;
    }

    public function paymentFactory(){
        return new PaymentFactory(
            function(){
                return $this->mollieOrderMock();
            },
            function(){
                return $this->molliePaymentMock();
            }
        );
    }

    public function mollieOrderMock()
    {
        return $this->getMockBuilder(MollieOrder::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
    public function molliePaymentMock()
    {
        return $this->getMockBuilder(MolliePayment::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
    public function noticeMock()
    {
        return $this->getMockBuilder(AdminNotice::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function paymentService()
    {
        return $this->createConfiguredMock(
            PaymentProcessor::class,
            [

            ]
        );
    }
    public function orderInstructionsService()
    {
        return $this->createConfiguredMock(
            OrderInstructionsManager::class,
            [

            ]
        );
    }
    public function mollieOrderService()
    {
        return $this->createConfiguredMock(
            MollieOrderService::class,
            [

            ]
        );
    }

    public function orderItemsRefunder()
    {
        return $this->getMockBuilder(OrderItemsRefunder::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
    public function orderLines($apiClientMock){
        return new OrderLines(
            $this->dataHelper($apiClientMock),
            $this->pluginId()
        );
    }

    public function loggerMock()
    {
        return new emptyLogger();
    }
    public function settingsHelper()
    {
        return $this->createConfiguredMock(
            Settings::class,
            [
                'isTestModeEnabled' => true,
                'getApiKey' => 'test_NtHd7vSyPSpEyuTEwhjsxdjsgVG4Sx',
                'getPaymentLocale' => 'en_US',
                'shouldStoreCustomer' => false,
            ]
        );

    }

    public function apiClient(){
        return $this->getMockBuilder(MollieApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function dataHelper($apiClientMock = false){
        if(!$apiClientMock){
            $apiClientMock = $this->apiClient();
        }
        $apiHelper = $this->apiHelper($apiClientMock);
        $logger = $this->loggerMock();
        $pluginId = $this->pluginId();
        $pluginPath = $this->pluginPath();
        $settings = $this->settingsHelper();
        return new Data($apiHelper, $logger, $pluginId, $settings, $pluginPath);
    }
    public function apiHelper($apiClientMock)
    {
        $api = $this->createPartialMock(
            Api::class,
            ['getApiClient']
        );


        $api->method('getApiClient')->willReturn($apiClientMock);
        return $api;

    }

    public function gatewayMockedOptions(string $paymentMethodId, $isSepa = false, $isSubscription = false)
    {
        return [
            'id' => strtolower($paymentMethodId),
            'defaultTitle' => __($paymentMethodId, 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Select your bank', 'mollie-payments-for-woocommerce'),
            'paymentFields' => true,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => $isSepa,
            'Subscription' => $isSubscription
        ];
    }

    public function paymentMethodSettings($testParams = []){
        return [
            'enabled' => isset($testParams['enabled']) ? $testParams['enabled'] : 'yes',
            'title' => isset($testParams['title']) ? $testParams['title'] : 'default title',
            'description' => isset($testParams['description']) ? $testParams['description'] : 'default description',
            'display_logo' => isset($testParams['display_logo']) ? $testParams['display_logo'] : 'yes',
            'iconFileUrl' => isset($testParams['iconFileUrl']) ? $testParams['iconFileUrl'] : '',
            'iconFilePath' => isset($testParams['iconFilePath']) ? $testParams['iconFilePath'] : '',
            'allowed_countries' => isset($testParams['allowed_countries']) ? $testParams['allowed_countries'] : [],
            'enable_custom_logo' => isset($testParams['enable_custom_logo']) ? $testParams['enable_custom_logo'] : false,
            'payment_surcharge' => isset($testParams['payment_surcharge']) ? $testParams['payment_surcharge'] : 'no_fee',
            'fixed_fee' => isset($testParams['fixed_fee']) ? $testParams['fixed_fee'] : '0.00',
            'percentage' => isset($testParams['percentage']) ? $testParams['percentage'] : '0.00',
            'surcharge_limit' => isset($testParams['surcharge_limit']) ? $testParams['surcharge_limit'] : '0.00',
            'maximum_limit' => isset($testParams['maximum_limit']) ? $testParams['maximum_limit'] : '0.00',
            'activate_expiry_days_setting' => isset($testParams['activate_expiry_days_setting']) ? $testParams['activate_expiry_days_setting'] : 'no',
            'order_dueDate' => isset($testParams['order_dueDate']) ? $testParams['order_dueDate'] : '0',
            'issuers_dropdown_shown' => isset($testParams['issuers_dropdown_shown']) ? $testParams['issuers_dropdown_shown'] : 'yes',
            'issuers_empty_option' => isset($testParams['issuers_empty_option']) ? $testParams['issuers_empty_option'] : 'Select your bank',
            'initial_order_status' => isset($testParams['initial_order_status']) ? $testParams['initial_order_status'] : 'on-hold',
            'mollie_creditcard_icons_enabler' => isset($testParams['mollie_creditcard_icons_enabler']) ? $testParams['mollie_creditcard_icons_enabler'] : false,
            'mollie_creditcard_icons_amex' => isset($testParams['mollie_creditcard_icons_amex']) ? $testParams['mollie_creditcard_icons_amex'] : '',
        ];
    }

    public function paymentMethodMergedProperties($paymentMethodName, $isSepa, $isSubscription, $testSettings = [])
    {
        $options = $this->gatewayMockedOptions($paymentMethodName, $isSepa, $isSubscription);
        $settings = $this->paymentMethodSettings($testSettings);
        return array_merge($options, $settings);
    }

    public function paymentMethodBuilder($paymentMethodName, $isSepa = false, $isSubscription = false, $settings = [])
    {
        $paymentMethod = $this->createPartialMock(
            Ideal::class,
            ['getConfig', 'getInitialOrderStatus', 'getMergedProperties', 'getSettings', 'title']
        );
        $paymentMethod
            ->method('getConfig')
            ->willReturn(
                $this->gatewayMockedOptions($paymentMethodName, $isSepa, $isSubscription)
            );
        $paymentMethod
            ->method('getInitialOrderStatus')
            ->willReturn('paid');
        $paymentMethod
            ->method('getMergedProperties')
            ->willReturn($this->paymentMethodMergedProperties($paymentMethodName, $isSepa, $isSubscription, $settings));
        $paymentMethod
            ->method('title')
            ->willReturn($paymentMethodName);

        return $paymentMethod;
    }

    public function mollieGatewayBuilder($paymentMethodName, $isSepa, $isSubscription, $settings, $paymentService = null) {
        $paymentMethod = $this->paymentMethodBuilder($paymentMethodName, $isSepa, $isSubscription, $settings);
        $paymentService = $paymentService ?? $this->paymentService();
        $orderInstructionsService = $this->orderInstructionsService();
        $mollieOrderService = $this->mollieOrderService();
        $data = $this->dataHelper();
        $logger = $this->loggerMock();
        $notice = $this->noticeMock();
        $HttpResponseService = new HttpResponse();
        $mollieObject = $this->getMockBuilder(MollieObject::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentFactory = $this->paymentFactory();
        $pluginId = $this->pluginId();

        return $this->buildTesteeMock(
            MolliePaymentGatewayHandler::class,
            [
                $paymentMethod,
                $paymentService,
                $orderInstructionsService,
                $mollieOrderService,
                $data,
                $logger,
                $notice,
                $HttpResponseService,
                $mollieObject,
                $paymentFactory,
                $pluginId
            ],
            [
                'init_form_fields',
                'initDescription',
                'initIcon',
                'get_order_total',
                'getSelectedIssuer',
                'get_return_url'
            ]
        )->getMock();
    }

    public function requestFactory()
    {
        return $this->getMockBuilder(RequestFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}

class emptyLogger implements LoggerInterface{

    public function emergency($message, array $context = array())
    {
        // TODO: Implement emergency() method.
    }

    public function alert($message, array $context = array())
    {
        // TODO: Implement alert() method.
    }

    public function critical($message, array $context = array())
    {
        // TODO: Implement critical() method.
    }

    public function error($message, array $context = array())
    {
        // TODO: Implement error() method.
    }

    public function warning($message, array $context = array())
    {
        // TODO: Implement warning() method.
    }

    public function notice($message, array $context = array())
    {
        // TODO: Implement notice() method.
    }

    public function info($message, array $context = array())
    {
        // TODO: Implement info() method.
    }

    public function debug($message, array $context = array())
    {
        // TODO: Implement debug() method.
    }

    public function log($level, $message, array $context = array())
    {
        // TODO: Implement log() method.
    }
}
