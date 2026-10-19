<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Settings;

use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\MollieSettingsPage;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use ReflectionProperty;
use WC_Admin_Settings;
use WP_Error;

/**
 * The Mollie Connection Status field, from Mollie's HTTP answer to the HTML the merchant sees
 *
 * Only the HTTP answer is faked, through WordPress's own pre_http_request short-circuit of
 * wp_remote_request(). The unit tests build the exception by hand, so
 * they cannot see what the adapter adds to its message; this test can.
 *
 * @group integration
 * @group ConnectionStatus
 */
class ConnectionStatusRenderingTest extends IntegrationMockedTestCase
{
    private const PLUGIN_ID = 'mollie-payments-for-woocommerce';
    private const VALID_TEST_KEY = 'test_ConnectionStatusRenderingKeyXXXXXXXX';

    /**
     * @var array<string, mixed>
     */
    private array $optionBackups = [];

    /**
     * @var array{0: int, 1: array<string, mixed>|null}|WP_Error|null The next answer of api.mollie.com (a null body is empty).
     */
    private $mollieAnswer = null;

    /**
     * @var list<string> URLs of the requests that reached the fake.
     */
    private array $mollieRequests = [];

    /**
     * Every hook as setUp() found it. Each boot of the plugin and each settings page adds hooks
     * (the page adds another mollie_custom_input renderer), so they are given back after the test.
     *
     * @var array<string, \WP_Hook>
     */
    private array $hookBackup = [];

    public function setUp(): void
    {
        parent::setUp();
        foreach ($GLOBALS['wp_filter'] as $name => $hook) {
            $this->hookBackup[$name] = clone $hook;
        }
        $this->loadWooCommerceAdminSettings();
        $this->forgetApiClient();
        $this->setOption(self::PLUGIN_ID . '_test_mode_enabled', 'yes');
        $this->setOption(self::PLUGIN_ID . '_test_api_key', self::VALID_TEST_KEY);

        add_filter('pre_http_request', function ($preempt, array $args, string $url) {
            if (strpos($url, 'api.mollie.com') === false) {
                return $preempt;
            }
            $this->mollieRequests[] = $url;
            if ($this->mollieAnswer instanceof WP_Error) {
                return $this->mollieAnswer;
            }
            [$status, $body] = $this->mollieAnswer ?? [501, ['status' => 501, 'title' => 'Not Implemented', 'detail' => 'No answer set.']];

            return [
                'headers' => ['content-type' => 'application/hal+json'],
                'body' => $body === null ? '' : (string) wp_json_encode($body),
                'response' => ['code' => $status, 'message' => (string) ($body['title'] ?? '')],
                'cookies' => [],
                'filename' => null,
            ];
        }, 10, 3);
    }

    public function tearDown(): void
    {
        $GLOBALS['wp_filter'] = $this->hookBackup;
        $this->hookBackup = [];
        foreach ($this->optionBackups as $name => $previous) {
            $previous === null ? delete_option($name) : update_option($name, $previous);
        }
        $this->optionBackups = [];
        $this->forgetApiClient();
        parent::tearDown();
    }

    /**
     * Scenario: Mollie's answer decides the message the merchant sees
     *   Given a valid-looking test API key and test mode on
     *   And Mollie answers the connection check with the given HTTP response
     *   When the Mollie settings page builds and renders its Connection Status field
     *   Then the field shows the message for that failure category
     *   And none of the words that belong to another category
     *
     * @test
     * @dataProvider mollieAnswers
     *
     * @param array{0: int, 1: array<string, mixed>}|WP_Error $answer
     * @param list<string> $expected
     * @param list<string> $notExpected
     */
    public function it_renders_the_message_for_each_answer_of_mollie($answer, array $expected, array $notExpected): void
    {
        $this->mollieAnswer = $answer;

        $field = $this->renderedConnectionStatus();

        $this->assertNotSame([], $this->mollieRequests, 'The check must have reached the HTTP layer.');
        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $field);
        }
        foreach ($notExpected as $text) {
            $this->assertStringNotContainsStringIgnoringCase($text, $field);
        }
    }

    /**
     * @return array<string, array{0: array{0: int, 1: array<string, mixed>}|WP_Error, 1: list<string>, 2: list<string>}>
     */
    public function mollieAnswers(): array
    {
        return [
            'connected' => [
                [200, ['count' => 0, '_embedded' => ['methods' => []], '_links' => []]],
                ['Successfully connected with <strong>Test API</strong> &#x2713;'],
                ['Failed to connect'],
            ],
            '401: the key is refused' => [
                [401, $this->problem(401, 'Unauthorized Request', 'Missing authentication, or failed to authenticate.')],
                ['Failed to connect to Mollie API - check your API keys &#x2716;'],
                ['outbound connectivity', 'status.mollie.com'],
            ],
            '429: rate limited' => [
                [429, $this->problem(429, 'Too Many Requests', 'Slow down.')],
                ['Too many requests, please wait and try again &#x2716;'],
                ['API key', 'status.mollie.com'],
            ],
            '503: Mollie is down' => [
                [503, $this->problem(503, 'Service Unavailable', 'Down for maintenance.')],
                ['<a href="https://status.mollie.com/" target="_blank">Mollie status page</a>'],
                ['API key', 'Down for maintenance'],
            ],
            '500: the first status that is an outage' => [
                [500, $this->problem(500, 'Internal Server Error', 'Something broke.')],
                ['<a href="https://status.mollie.com/" target="_blank">Mollie status page</a>'],
                ['API key', 'Something broke'],
            ],
            'no answer: the transport failed, its detail shown generically' => [
                new WP_Error('http_request_failed', 'cURL error 28: Operation timed out after 10000 milliseconds with 0 bytes received'),
                ['Communicating with Mollie failed: cURL error 28: Operation timed out after 10000 milliseconds with 0 bytes received &#x2716;'],
                ['API key', 'status.mollie.com', 'outbound connectivity', 'SSL'],
            ],
            'empty body: no status survives, so not a connectivity problem' => [
                [503, null],
                ['Communicating with Mollie failed: No response body found. &#x2716;'],
                ['API key', 'outbound connectivity', 'SSL'],
            ],
            'error object in the body: not a connectivity problem' => [
                [200, ['error' => ['message' => 'The profile is blocked.']]],
                ['Communicating with Mollie failed: The profile is blocked. &#x2716;'],
                ['API key', 'outbound connectivity', 'SSL', 'status.mollie.com'],
            ],
            'no answer and no detail: a generic message, not a key problem' => [
                new WP_Error('http_request_failed', ''),
                ['Failed to connect to Mollie API &#x2716;'],
                ['API key', 'outbound connectivity', 'status.mollie.com'],
            ],
            '400: Mollie refuses the request, its text escaped once and undecorated' => [
                [400, $this->problem(400, 'Bad Request', "The 'amount' & 'currency' don't match.")],
                ['Communicating with Mollie failed: ', 'The &#039;amount&#039; &amp; &#039;currency&#039; don&#039;t match.'],
                ['&amp;#039;', '&amp;amp;', 'Documentation:', 'Request body:', 'status.mollie.com', 'API key'],
            ],
        ];
    }

    /**
     * Scenario: a missing or malformed key is a key problem, and Mollie is never asked
     *   Given no API key, or one that fails the plugin's format check
     *   When the Mollie settings page renders its Connection Status field
     *   Then the field shows the plugin's own key message, its dashboard link intact
     *   And no request reaches Mollie
     *   And nothing suggests a connectivity problem
     *
     * @test
     * @dataProvider badKeys
     *
     * @param list<string> $expected
     */
    public function it_renders_a_key_problem_without_asking_mollie(string $key, array $expected): void
    {
        $this->setOption(self::PLUGIN_ID . '_test_api_key', $key);

        $field = $this->renderedConnectionStatus();

        $this->assertSame([], $this->mollieRequests);
        foreach ($expected as $text) {
            $this->assertStringContainsString($text, $field);
        }
        $this->assertStringNotContainsString('&lt;a href', $field);
        $this->assertStringNotContainsStringIgnoringCase('outbound connectivity', $field);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public function badKeys(): array
    {
        return [
            'no key' => ['', ['No API key provided. Please set your Mollie API keys below. &#x2716;']],
            'malformed key' => ['test_short', [
                'Invalid API key(s).',
                '<a href="https://my.mollie.com/dashboard/developers/api-access-tokens',
            ]],
        ];
    }

    /**
     * Scenario: an error response keeps its full text for the logs and a plain one for the page
     *   Given Mollie answers a request with a 400 error document
     *   When the plugin's HTTP adapter turns it into an exception
     *   Then getMessage() is today's text: the call, the documentation link and the response body
     *   And getPlainMessage() is only the call and Mollie's detail, which is all the page may show
     *
     * @test
     */
    public function it_keeps_the_full_text_for_logs_and_a_plain_one_for_the_page(): void
    {
        $this->mollieAnswer = [400, $this->problem(400, 'Bad Request', "The 'amount' & 'currency' don't match.")];
        $call = 'Error executing API call (400: Bad Request): The &#039;amount&#039; &amp; &#039;currency&#039; don&#039;t match.';

        try {
            $this->bootstrapModule()->get('SDK.api_helper')->getApiClient(self::VALID_TEST_KEY, true)->methods->all();
            $this->fail('A 400 must raise an ApiException.');
        } catch (\Mollie\Api\Exceptions\ApiException $exception) {
            $this->assertSame(400, $exception->getCode());
            $this->assertSame(
                $call . '. Documentation: https://docs.mollie.com/overview/handling-errors. Request body: '
                    . '{&quot;status&quot;:400,&quot;title&quot;:&quot;Bad Request&quot;,&quot;detail&quot;:&quot;The &#039;amount&#039; &amp; &#039;currency&#039; don&#039;t match.&quot;,'
                    . '&quot;_links&quot;:{&quot;documentation&quot;:{&quot;href&quot;:&quot;https:\/\/docs.mollie.com\/overview\/handling-errors&quot;,&quot;type&quot;:&quot;text\/html&quot;}}}',
                (string) preg_replace('/^\[[^\]]+\] /', '', $exception->getMessage()),
                'The logged text must not change.'
            );
            $this->assertSame($call, $exception->getPlainMessage());
        }
    }

    /**
     * The Connection Status row as WooCommerce renders it on the Mollie settings page.
     */
    private function renderedConnectionStatus(): string
    {
        $container = $this->bootstrapModule();
        $page = new MollieSettingsPage(
            $container->get('settings.settings_helper'),
            $container->get('shared.plugin_path'),
            $container->get('shared.plugin_url'),
            (bool) $container->get('settings.IsTestModeEnabled'),
            $container->get('settings.data_helper'),
            $container
        );
        $fieldId = self::PLUGIN_ID . '_connection_status';
        $fields = array_values(array_filter(
            (array) $page->get_settings(),
            static fn ($field): bool => is_array($field) && ($field['id'] ?? null) === $fieldId
        ));
        $this->assertCount(1, $fields, 'The settings page must hold one Connection Status field.');

        ob_start();
        WC_Admin_Settings::output_fields($fields);
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('Mollie Connection Status', $html, 'The field must be rendered.');

        return $html;
    }

    /**
     * A Mollie error document, as the API sends it.
     *
     * @return array<string, mixed>
     */
    private function problem(int $status, string $title, string $detail): array
    {
        return [
            'status' => $status,
            'title' => $title,
            'detail' => $detail,
            '_links' => ['documentation' => ['href' => 'https://docs.mollie.com/overview/handling-errors', 'type' => 'text/html']],
        ];
    }

    /**
     * @param mixed $value
     */
    private function setOption(string $name, $value): void
    {
        if (!array_key_exists($name, $this->optionBackups)) {
            $existing = get_option($name, null);
            $this->optionBackups[$name] = $existing === false ? null : $existing;
        }
        update_option($name, $value);
    }

    /**
     * Api keeps one client per request in a static property; another test's client (or a Mockery
     * double) would otherwise answer instead of the real SDK.
     */
    private function forgetApiClient(): void
    {
        $property = new ReflectionProperty(Api::class, 'api_client');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    private function loadWooCommerceAdminSettings(): void
    {
        if (!class_exists(WC_Admin_Settings::class)) {
            require_once WC()->plugin_path() . '/includes/admin/class-wc-admin-settings.php';
        }
        if (!class_exists(\WC_Settings_Page::class)) {
            require_once WC()->plugin_path() . '/includes/admin/settings/class-wc-settings-page.php';
        }
    }
}
