<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests;

use Faker\Generator;
use Faker;
use Inpsyde\Modularity\Properties\PluginProperties;
use Mockery;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Activation\ActivationModule;
use Mollie\WooCommerce\Assets\AssetsModule;
use Mollie\WooCommerce\Gateway\GatewayModule;
use Mollie\WooCommerce\Gateway\Voucher\VoucherModule;
use Mollie\WooCommerce\Log\LogModule;
use Mollie\WooCommerce\Notice\NoticeModule;
use Mollie\WooCommerce\Payment\PaymentModule;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\SDKModule;
use Mollie\WooCommerce\Settings\SettingsModule;
use Mollie\WooCommerce\Shared\SharedModule;
use Mollie\WooCommerce\Uninstall\UninstallModule;
use PHPUnit_Framework_MockObject_MockBuilder;
use PHPUnit_Framework_MockObject_MockObject;
use WP_Error;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Xpmock\Reflection;
use Xpmock\TestCaseTrait;
use Inpsyde\ModularityTestCase\ModularityTestCase;

/**
 * Class Testcase
 */
class TestCase extends ModularityTestCase
{
    use TestCaseTrait;
    /**
     * @var Generator
     */
    protected $faker;
    /**
     * @var array
     */
    protected $modules;
    /**
     * @var PluginProperties
     */
    protected $properties;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {

        parent::setUp();
        setUp();
        $this->setupFaker();

        when('__')->returnArg(1);
        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();
    }

    /**
     * Create Faker instance
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function setupFaker()
    {
        $fakeFactory = new Faker\Factory();
        $this->faker = $fakeFactory->create();
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        tearDown();
    }

    /**
     * Build the Testee Mock Object
     *
     * Basic configuration available for all of the testee objects, call `getMock` to get the mock.
     *
     * @param string $className
     * @param array $constructorArguments
     * @param array $methods
     * @return PHPUnit_Framework_MockObject_MockBuilder
     */
    protected function buildTesteeMock($className, $constructorArguments, $methods)
    {
        $testee = $this->getMockBuilder($className);
        $constructorArguments
            ? $testee->setConstructorArgs($constructorArguments)
            : $testee->disableOriginalConstructor();

        $testee->setMethods($methods);

        return $testee;
    }

    /**
     * Retrieve a Testee Mock to Test Protected Methods
     *
     * return MockBuilder
     * @param string $className
     * @param array $constructorArguments
     * @param array $methods
     * @return Reflection
     */
    protected function buildTesteeMethodMock($className, $constructorArguments, $methods)
    {
        $testee = $this->buildTesteeMock($className, $constructorArguments, $methods)->getMock();

        return $this->proxyFor($testee);
    }

    /**
     * Create a proxy for a mocked class
     *
     * @param PHPUnit_Framework_MockObject_MockObject $testee
     * @return Reflection
     */
    protected function proxyFor(PHPUnit_Framework_MockObject_MockObject $testee)
    {
        return $this->reflect($testee);
    }

    /**
     * @param string $code
     * @param string $message
     * @param string $data
     *
     * @return PHPUnit_Framework_MockObject_MockObject&WP_Error
     */
    protected function createWpError($code = '', $message = '', $data = '')
    {
        $mock = $this->getMockBuilder('WP_Error')
            ->setMethods(['get_error_code', 'get_error_message', 'get_error_data'])
            ->getMock();

        return $mock;
    }

    protected function bootsrapMolliePlugin()
    {
        $mockedApiClient = $this->getMockBuilder(MollieApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockedApiClient->orders = $this->getMockBuilder(OrderEndpoint::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->injectService(
            'SDK.api_helper',
            function () use ($mockedApiClient) {
                return $this->createConfiguredMock(
                    Api::class,
                    [
                        'getApiClient' => $mockedApiClient,
                    ]
                );
            }
        );
        when('get_plugin_data')->justReturn([
                                                'Name' => 'WooCommerce/mollie-payments-for-woocommerce.php',
                                                'Title' => 'Mollie Payments for WooCommerce',
                                                'Description' => 'Accept payments in WooCommerce with the official Mollie plugin',
                                                'TextDomain' => 'mollie-payments-for-woocommerce',
                                                'Version' => '7.3.4',
                                                'RequiresWP' => '5.3',
                                                'RequiresPhp' => '7.2',
                                                'WC requires at least' => '5.0',
                                            ]);
        when('plugin_basename')
            ->justReturn('WooCommerce/mollie-payments-for-woocommerce.php');
        when('plugin_dir_path')
            ->justReturn(M4W_PLUGIN_DIR);

        when('is_multisite')
            ->justReturn(false);

        when('esc_attr')->returnArg(1);

        when('esc_url_raw')->returnArg(1);
        when('trailingslashit')->returnArg(1);
        when('do_action')->returnArg(1);
        when('admin_url')->returnArg(1);
        expect('get_option')->with('mollie-payments-for-woocommerce_test_mode_enabled')->andReturn(false);
        expect('plugins_url')
            ->with('', M4W_PLUGIN_DIR)
            ->andReturn(M4W_PLUGIN_URL);
        when('is_admin')->justReturn(false);
        when('get_woocommerce_currency_symbol')->justReturn('EUR');
        when('wc_get_order_status_name')->returnArg(1);
        when('update_option')->returnArg(1);
        when('wc_get_base_location')->justReturn(['country'=>'ES']);
        when('esc_url')->returnArg(1);
        when('is_plugin_active')->justReturn(true);
        $this->modules = [
            new ActivationModule(),
            new UninstallModule(),
            new NoticeModule(),
            new SharedModule(),
            new SDKModule(),
            new LogModule('mollie-payments-for-woocommerce-'),
            new SettingsModule(),
            new AssetsModule(),
            new GatewayModule(),
            new VoucherModule(),
            new PaymentModule(),
        ];
        $this->properties = PluginProperties::new(M4W_PLUGIN_DIR . '/mollie-payments-for-woocommerce.php');
    }
}
