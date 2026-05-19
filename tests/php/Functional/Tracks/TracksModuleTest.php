<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Tracks;

use Mollie\WooCommerce\Tracks\TracksModule;
use Mollie\WooCommerce\Tracks\TracksEventRecorder;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerceTests\TestCase;
use Mockery;
use Psr\Container\ContainerInterface;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class TracksModuleTest extends TestCase
{
    /** @var \stdClass */
    private $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = (object) ['callbacks' => []];
        unset($_SERVER['REQUEST_METHOD']);
        $_GET = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $_GET = [];
        parent::tearDown();
    }

    /**
     * WHEN plugin is activated
     * THEN onPluginActivation sets the option flag
     * @test
     */
    public function onPluginActivationSetsOption()
    {
        expect('update_option')
            ->once()
            ->with('mollie_tracks_plugin_activated', '1', false);

        TracksModule::onPluginActivation();

        $this->addToAssertionCount(1);
    }

    /**
     * WHEN activation option is set and merchant is NOT connected
     * THEN deferred activation callback fires the event and clears the option
     * @test
     */
    public function deferredActivationFiresWhenOptionSet()
    {
        $this->interceptAddAction();

        when('get_option')->alias(function ($name) {
            if ($name === 'mollie_tracks_plugin_activated') {
                return '1';
            }
            return false;
        });
        expect('delete_option')
            ->once()
            ->with('mollie_tracks_plugin_activated');

        $settingsHelper = Mockery::mock(Settings::class);
        $settingsHelper->shouldReceive('getConnectionStatus')->andReturn(false);
        $settingsHelper->shouldIgnoreMissing();

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldReceive('recordEvent')
            ->once()
            ->with('mollie_plugin_activated');

        $container = $this->createMockContainer($recorder, $settingsHelper);
        $module = new TracksModule();
        $module->run($container);

        $this->assertNotEmpty($this->getCallbacks('admin_init'));
        $this->runCallbacks('admin_init');
    }

    /**
     * WHEN activation option is set but merchant IS already connected
     * THEN the activation event does NOT fire
     * @test
     */
    public function activationSkippedWhenConnected()
    {
        $this->interceptAddAction();

        when('get_option')->alias(function ($name) {
            if ($name === 'mollie_tracks_plugin_activated') {
                return '1';
            }
            return false;
        });
        expect('delete_option')
            ->once()
            ->with('mollie_tracks_plugin_activated');

        $settingsHelper = Mockery::mock(Settings::class);
        $settingsHelper->shouldReceive('getConnectionStatus')->andReturn(true);
        $settingsHelper->shouldIgnoreMissing();

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldNotReceive('recordEvent')
            ->with('mollie_plugin_activated');
        $recorder->shouldReceive('recordEvent')->withAnyArgs()->zeroOrMoreTimes();

        $container = $this->createMockContainer($recorder, $settingsHelper);
        $module = new TracksModule();
        $module->run($container);

        $this->runCallbacks('admin_init');
        $this->addToAssertionCount(1);
    }

    /**
     * WHEN activation option is NOT set
     * THEN no activation event fires
     * @test
     */
    public function activationSkippedWithoutFlag()
    {
        $this->interceptAddAction();

        when('get_option')->justReturn(false);

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldNotReceive('recordEvent')
            ->with('mollie_plugin_activated');
        $recorder->shouldReceive('recordEvent')->withAnyArgs()->zeroOrMoreTimes();

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $this->runCallbacks('admin_init');
        $this->addToAssertionCount(1);
    }

    /**
     * WHEN api keys page is viewed for the first time
     * THEN api_keys_viewed fires and sets the one-time gate
     * @test
     */
    public function apiKeysViewedFiresOnFirstVisit()
    {
        $this->interceptAddAction();

        $_GET['section'] = 'mollie_api_keys';

        when('get_option')->justReturn(false);
        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();
        expect('update_option')
            ->once()
            ->with('mollie_tracks_api_keys_viewed', '1', false);

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldReceive('recordEvent')
            ->once()
            ->with('mollie_api_keys_viewed');

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $cbs = $this->getCallbacks('woocommerce_settings_mollie_settings');
        $this->assertNotEmpty($cbs);
        $cbs[0]();
    }

    /**
     * WHEN api keys page has already been viewed once
     * THEN api_keys_viewed does NOT fire again
     * @test
     */
    public function apiKeysViewedDoesNotFireOnSecondVisit()
    {
        $this->interceptAddAction();

        $_GET['section'] = 'mollie_api_keys';

        when('get_option')->alias(function ($name, $default = false) {
            if ($name === 'mollie_tracks_api_keys_viewed') {
                return '1';
            }
            return $default;
        });

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldNotReceive('recordEvent')
            ->with('mollie_api_keys_viewed');
        $recorder->shouldReceive('recordEvent')->withAnyArgs()->zeroOrMoreTimes();

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $cbs = $this->getCallbacks('woocommerce_settings_mollie_settings');
        $this->assertNotEmpty($cbs);
        $cbs[0]();
        $this->addToAssertionCount(1);
    }

    /**
     * WHEN settings are saved on the Mollie page with a successful connection
     * THEN api_key_saved and connection_success fire with correct params
     * @test
     */
    public function saveFiresKeyAndConnectionSuccess()
    {
        $this->interceptAddAction();

        $_GET['page'] = 'wc-settings';
        $_GET['tab'] = 'mollie_settings';

        when('get_option')->alias(function ($name, $default = false) {
            if ($name === 'mollie-payments-for-woocommerce_test_api_key') {
                return 'test_key_123';
            }
            if ($name === 'mollie-payments-for-woocommerce_live_api_key') {
                return 'live_key_456';
            }
            return $default;
        });
        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();

        $settingsHelper = Mockery::mock(Settings::class);
        $settingsHelper->shouldReceive('isTestModeEnabled')->andReturn(true);
        $settingsHelper->shouldReceive('getConnectionStatus')->andReturn(true);
        $settingsHelper->shouldReceive('getConnectionStatusWithError')->andReturn([
            'connected' => true,
        ]);

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldReceive('recordEvent')
            ->once()
            ->with('mollie_api_key_saved', Mockery::on(function ($props) {
                return $props['payment_mode'] === 'test'
                    && $props['has_test_key'] === true
                    && $props['has_live_key'] === true;
            }));
        $recorder->shouldReceive('recordEvent')
            ->once()
            ->with('mollie_connection_success', Mockery::on(function ($props) {
                return $props['payment_mode'] === 'test';
            }));

        $container = $this->createMockContainer($recorder, $settingsHelper);
        $module = new TracksModule();
        $module->run($container);

        $cbs = $this->getCallbacks('woocommerce_settings_saved');
        $this->assertNotEmpty($cbs);
        $cbs[0]();
    }

    /**
     * WHEN settings are saved with a failed connection
     * THEN connection_failed fires with error details
     * @test
     */
    public function connectionFailedFiresWithErrorDetails()
    {
        $this->interceptAddAction();

        $_GET['page'] = 'wc-settings';
        $_GET['tab'] = 'mollie_settings';

        when('get_option')->alias(function ($name, $default = false) {
            if ($name === 'mollie-payments-for-woocommerce_test_api_key') {
                return 'test_invalid';
            }
            return $default;
        });
        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();

        $settingsHelper = Mockery::mock(Settings::class);
        $settingsHelper->shouldReceive('isTestModeEnabled')->andReturn(true);
        $settingsHelper->shouldReceive('getConnectionStatus')->andReturn(false);
        $settingsHelper->shouldReceive('getConnectionStatusWithError')->andReturn([
            'connected' => false,
            'error_code' => 401,
            'error_message' => '[2026-05-15T12:00:00+0000] Invalid API key',
        ]);

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldReceive('recordEvent')
            ->once()
            ->with('mollie_api_key_saved', Mockery::any());
        $recorder->shouldReceive('recordEvent')
            ->once()
            ->with('mollie_connection_failed', Mockery::on(function ($props) {
                return $props['payment_mode'] === 'test'
                    && $props['error_code'] === 401
                    && $props['error_message'] === 'Invalid API key';
            }));

        $container = $this->createMockContainer($recorder, $settingsHelper);
        $module = new TracksModule();
        $module->run($container);

        $cbs = $this->getCallbacks('woocommerce_settings_saved');
        $this->assertNotEmpty($cbs);
        $cbs[0]();
    }

    /**
     * WHEN settings are saved on a NON-Mollie page
     * THEN api_key_saved does NOT fire
     * @test
     */
    public function apiKeySavedDoesNotFireOnNonMolliePage()
    {
        $this->interceptAddAction();

        $_GET['page'] = 'wc-settings';
        $_GET['tab'] = 'general';

        when('get_option')->justReturn(false);
        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldNotReceive('recordEvent')
            ->with('mollie_api_key_saved', Mockery::any());
        $recorder->shouldReceive('recordEvent')->withAnyArgs()->zeroOrMoreTimes();

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $cbs = $this->getCallbacks('woocommerce_settings_saved');
        $this->assertNotEmpty($cbs);
        $cbs[0]();
        $this->addToAssertionCount(1);
    }

    /**
     * WHEN a paid test payment webhook fires
     * AND first_test_payment has NOT been tracked yet
     * THEN the event fires and option is set
     * @test
     */
    public function firstTestPaymentFiresOnWebhook()
    {
        $this->interceptAddAction();

        when('get_option')->alias(function ($name, $default = false) {
            if ($name === 'mollie_tracks_first_test_payment_tracked') {
                return false;
            }
            return $default;
        });
        expect('update_option')
            ->once()
            ->with('mollie_tracks_first_test_payment_tracked', '1', false);

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldReceive('recordEvent')
            ->once()
            ->with('mollie_first_test_payment_complete', Mockery::on(function ($props) {
                return $props['payment_method'] === 'ideal';
            }));

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $hookName = 'mollie-payments-for-woocommerce_after_webhook_action';
        $cbs = $this->getCallbacks($hookName);
        $this->assertNotEmpty($cbs);

        $payment = Mockery::mock();
        $payment->shouldReceive('isPaid')->andReturn(true);
        $payment->mode = 'test';
        $payment->method = 'ideal';
        $order = Mockery::mock('WC_Order');

        $cbs[0]($payment, $order);
    }

    /**
     * WHEN a paid live payment webhook fires
     * THEN the test payment event does NOT fire
     * @test
     */
    public function testPaymentSkippedForLiveMode()
    {
        $this->interceptAddAction();

        when('get_option')->justReturn(false);

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldNotReceive('recordEvent')
            ->with('mollie_first_test_payment_complete', Mockery::any());
        $recorder->shouldReceive('recordEvent')->withAnyArgs()->zeroOrMoreTimes();

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $hookName = 'mollie-payments-for-woocommerce_after_webhook_action';
        $cbs = $this->getCallbacks($hookName);
        $this->assertNotEmpty($cbs);

        $payment = Mockery::mock();
        $payment->shouldReceive('isPaid')->andReturn(true);
        $payment->mode = 'live';
        $payment->method = 'ideal';
        $order = Mockery::mock('WC_Order');

        $cbs[0]($payment, $order);
        $this->addToAssertionCount(1);
    }

    /**
     * WHEN the webhook fires but payment is not paid
     * THEN the test payment event does NOT fire
     * @test
     */
    public function testPaymentSkippedWhenUnpaid()
    {
        $this->interceptAddAction();

        when('get_option')->justReturn(false);

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldNotReceive('recordEvent')
            ->with('mollie_first_test_payment_complete', Mockery::any());
        $recorder->shouldReceive('recordEvent')->withAnyArgs()->zeroOrMoreTimes();

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $hookName = 'mollie-payments-for-woocommerce_after_webhook_action';
        $cbs = $this->getCallbacks($hookName);
        $this->assertNotEmpty($cbs);

        $payment = Mockery::mock();
        $payment->shouldReceive('isPaid')->andReturn(false);
        $payment->mode = 'test';
        $payment->method = 'ideal';
        $order = Mockery::mock('WC_Order');

        $cbs[0]($payment, $order);
        $this->addToAssertionCount(1);
    }

    /**
     * WHEN first test payment has already been tracked
     * THEN it does NOT fire again
     * @test
     */
    public function firstTestPaymentDoesNotFireTwice()
    {
        $this->interceptAddAction();

        when('get_option')->alias(function ($name, $default = false) {
            if ($name === 'mollie_tracks_first_test_payment_tracked') {
                return '1';
            }
            return $default;
        });

        $recorder = Mockery::mock(TracksEventRecorder::class);
        $recorder->shouldNotReceive('recordEvent')
            ->with('mollie_first_test_payment_complete', Mockery::any());
        $recorder->shouldReceive('recordEvent')->withAnyArgs()->zeroOrMoreTimes();

        $container = $this->createMockContainer($recorder);
        $module = new TracksModule();
        $module->run($container);

        $hookName = 'mollie-payments-for-woocommerce_after_webhook_action';
        $cbs = $this->getCallbacks($hookName);
        $this->assertNotEmpty($cbs);

        $payment = Mockery::mock();
        $payment->shouldReceive('isPaid')->andReturn(true);
        $payment->mode = 'test';
        $payment->method = 'ideal';
        $order = Mockery::mock('WC_Order');

        $cbs[0]($payment, $order);
        $this->addToAssertionCount(1);
    }

    /**
     * WHEN plugin is deactivated and merchant has no API keys
     * THEN all tracking options are cleared
     * @test
     */
    public function deactivationResetsWhenNotConnected()
    {
        when('get_option')->justReturn(false);

        expect('delete_option')
            ->once()
            ->with('mollie_tracks_first_test_payment_tracked');
        expect('delete_option')
            ->once()
            ->with('mollie_tracks_api_keys_viewed');
        expect('delete_option')
            ->once()
            ->with('mollie_tracks_plugin_activated');

        TracksModule::onPluginDeactivation();

        $this->addToAssertionCount(1);
    }

    /**
     * WHEN plugin is deactivated and merchant has API keys configured
     * THEN tracking options are NOT cleared
     * @test
     */
    public function deactivationKeepsWhenConnected()
    {
        when('get_option')->alias(function ($name, $default = false) {
            if ($name === 'mollie-payments-for-woocommerce_test_api_key') {
                return 'test_xxx';
            }
            return $default;
        });

        expect('delete_option')->never();

        TracksModule::onPluginDeactivation();

        $this->addToAssertionCount(1);
    }

    private function interceptAddAction(): void
    {
        $hooks = $this->hooks;
        expect('add_action')
            ->andReturnUsing(function () use ($hooks) {
                $args = func_get_args();
                $hook = $args[0];
                $callback = $args[1];
                if (!isset($hooks->callbacks[$hook])) {
                    $hooks->callbacks[$hook] = [];
                }
                $hooks->callbacks[$hook][] = $callback;
                return true;
            });
    }

    private function getCallbacks(string $hook): array
    {
        return $this->hooks->callbacks[$hook] ?? [];
    }

    private function runCallbacks(string $hook): void
    {
        foreach ($this->getCallbacks($hook) as $cb) {
            $cb();
        }
    }

    private function createMockContainer(
        ?TracksEventRecorder $recorder = null,
        ?Settings $settingsHelper = null
    ): ContainerInterface {

        $recorder = $recorder ?? Mockery::mock(TracksEventRecorder::class)->shouldIgnoreMissing();
        $settingsHelper = $settingsHelper ?? Mockery::mock(Settings::class)->shouldIgnoreMissing();

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(TracksEventRecorder::class)->andReturn($recorder);
        $container->shouldReceive('get')->with('settings.settings_helper')->andReturn($settingsHelper);
        $container->shouldReceive('get')->with('shared.plugin_id')->andReturn('mollie-payments-for-woocommerce');

        return $container;
    }
}
