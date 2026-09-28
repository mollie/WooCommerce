<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Settings\Page\Section;

use Mollie\WooCommerce\Settings\Page\Section\ConnectionStatusTrait;
use Mollie\WooCommerce\Settings\ConnectionResult;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Settings\Page\Section\ConnectionStatusTrait::connectionStatus
 * @covers \Mollie\WooCommerce\Settings\Page\Section\ConnectionStatusTrait::connectionErrorMessage
 */
class ConnectionStatusTraitTest extends TestCase
{
    private const LEGACY_MESSAGE = 'Failed to connect to Mollie API - check your API keys';
    private const STATUS_PAGE_URL = 'https://status.mollie.com/';

    protected function setUp(): void
    {
        parent::setUp();
        when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES);
        });
        when('esc_html__')->returnArg(1);
    }

    /**
     * Object under test: the trait exposed through a public wrapper.
     */
    private function makeSut(): object
    {
        return new class {
            use ConnectionStatusTrait;

            public function callConnectionStatus(Settings $settings, ConnectionResult $connectionStatus): ?string
            {
                return $this->connectionStatus($settings, $connectionStatus);
            }
        };
    }

    private function makeSettings(bool $testMode = false): Settings
    {
        $settings = \Mockery::mock(Settings::class);
        $settings->shouldReceive('isTestModeEnabled')->andReturn($testMode);

        return $settings;
    }

    /**
     * A failure that reached Mollie and came back with an HTTP status.
     */
    private function apiFailure(int $errorCode, string $errorMessage): ConnectionResult
    {
        return ConnectionResult::failed(ConnectionResult::KIND_API, $errorCode, $errorMessage);
    }

    // Criterion 2: a 401 authentication failure keeps pointing the merchant at the API keys
    public function testAuthenticationErrorMessageRefersToApiKeys(): void
    {
        $result = $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(401, 'Error executing API call (401: Unauthorized Request): Missing authentication')
        );

        self::assertStringContainsStringIgnoringCase('api key', (string) $result);
    }

    // Criterion 3: server-side statuses point at the Mollie status page and never blame the API keys
    public function testOutageErrorMessageLinksToStatusPageAndOmitsApiKeys(): void
    {
        foreach ([500, 502, 503, 504] as $outageCode) {
            $result = (string) $this->makeSut()->callConnectionStatus(
                $this->makeSettings(),
                $this->apiFailure($outageCode, 'Error executing API call (' . $outageCode . ')')
            );

            self::assertStringContainsString(
                self::STATUS_PAGE_URL,
                $result,
                "Outage message for code {$outageCode} must link to the Mollie status page"
            );
            self::assertStringNotContainsStringIgnoringCase(
                'api key',
                $result,
                "Outage message for code {$outageCode} must not blame the API keys"
            );
        }
    }

    // A 400 is a rejected request, not an outage: sending the merchant to the status page would mislead them
    public function testBadRequestIsNotPresentedAsAnOutage(): void
    {
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(400, 'Error executing API call (400: Bad Request): The amount is invalid')
        );

        self::assertStringNotContainsString(self::STATUS_PAGE_URL, $result);
        self::assertStringContainsString('The amount is invalid', $result);
    }

    // Criterion 4: a 429 is presented as rate limiting, not as a key problem
    public function testRateLimitErrorMessageMentionsTooManyRequests(): void
    {
        // The raw error deliberately omits the phrase, so echoing it back cannot pass this test.
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(429, 'Error executing API call (429)')
        );

        self::assertRegExp('/too many requests/i', $result);
        self::assertStringNotContainsStringIgnoringCase('api key', $result);
    }

    /**
     * Criterion 7: code 0 on the API stage only means no HTTP status is known. The transport
     * failed, or the response had no body, could not be decoded or carried an error object
     * (WordPressHttpAdapter), so the detail is shown in the generic wrapper and nothing blames
     * the server's connectivity or the API keys (review of PIWOO-938).
     *
     * @dataProvider failuresWithoutStatus
     */
    public function testFailureWithoutStatusShowsItsDetailGenerically(string $underlyingError): void
    {
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(0, $underlyingError)
        );

        self::assertSame('Communicating with Mollie failed: ' . $underlyingError . ' &#x2716;', $result);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function failuresWithoutStatus(): array
    {
        return [
            'transport failure' => ['cURL error 28: Operation timed out after 10000 milliseconds'],
            'empty response body' => ['No response body found.'],
            'error object in the body' => ['The profile is blocked.'],
        ];
    }

    // A missing or malformed API key is a key problem, even though it has no HTTP status like a failed request
    public function testApiKeyProblemIsNotPresentedAsAFailedRequest(): void
    {
        foreach (
            [
                'No API key provided. Please set your Mollie API keys below.',
                "Invalid API key(s). The API key(s) must start with 'live_' or 'test_'.",
            ] as $keyMessage
        ) {
            $result = (string) $this->makeSut()->callConnectionStatus(
                $this->makeSettings(),
                ConnectionResult::failed(ConnectionResult::KIND_API_KEY, 0, $keyMessage)
            );

            self::assertSame($keyMessage . ' &#x2716;', $result);
        }
    }

    // A plugin-authored key message carries an intentional link, which must survive rendering
    public function testApiKeyMessageMarkupIsNotEscapedAway(): void
    {
        $messageWithLink = 'Invalid API key(s). Get them on the '
            . '<a href="https://my.mollie.com/dashboard/developers/api-access-tokens" target="_blank">Developers page</a>.';

        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            ConnectionResult::failed(ConnectionResult::KIND_API_KEY, 0, $messageWithLink)
        );

        self::assertStringContainsString('<a href="https://my.mollie.com/', $result);
        self::assertStringNotContainsString('&lt;a href', $result);
    }

    // Mollie's own error text is escaped exactly once, so no entities leak into the page
    public function testApiErrorTextIsEscapedExactlyOnce(): void
    {
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(0, "Connection refused for 'live_' key & retry")
        );

        self::assertStringContainsString('&#039;live_&#039;', $result);
        self::assertStringContainsString('&amp; retry', $result);
        // Double escaping would turn the ampersand of each entity into &amp;
        self::assertStringNotContainsString('&amp;#039;', $result);
        self::assertStringNotContainsString('&amp;amp;', $result);
    }

    // An incompatible environment reports the actual compatibility problems
    public function testIncompatibleEnvironmentReportsCompatibilityErrors(): void
    {
        $compatibilityError = 'Mollie Payments for WooCommerce require PHP 7.4 or higher, you have PHP 7.2.';

        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            ConnectionResult::failed(ConnectionResult::KIND_INCOMPATIBLE, 0, $compatibilityError)
        );

        self::assertStringContainsString($compatibilityError, $result);
        self::assertStringNotContainsString('Incompatible environment', $result);
    }

    // Criterion 6a: an unrecognized code falls back to showing the real error message
    public function testUnrecognizedErrorCodeShowsRawErrorMessage(): void
    {
        $underlyingError = 'Error executing API call (418: I am a teapot)';

        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(418, $underlyingError)
        );

        self::assertStringContainsString($underlyingError, $result);
    }

    /**
     * Criterion 6b: a request that failed without any detail keeps the field non-empty, but with a
     * generic message: nothing at this point says the credentials are the cause (review of PIWOO-938).
     *
     * @dataProvider failuresWithoutDetail
     */
    public function testFailureWithoutDetailShowsAGenericMessage(int $errorCode): void
    {
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure($errorCode, '')
        );

        self::assertSame('Failed to connect to Mollie API &#x2716;', $result);
        self::assertStringNotContainsStringIgnoringCase('api key', $result);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public function failuresWithoutDetail(): array
    {
        return [
            'an unrecognized status' => [418],
            'no status at all' => [0],
        ];
    }

    // A section built before the detail was threaded through still renders the legacy message
    public function testBareDisconnectedStatusFallsBackToLegacyString(): void
    {
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            ConnectionResult::fromStatus(false)
        );

        self::assertStringContainsString(self::LEGACY_MESSAGE, $result);
    }

    // Criterion 7: the success messages are unchanged and still follow the test-mode setting
    public function testSuccessfulConnectionMessageReflectsTestModeSetting(): void
    {
        $sut = $this->makeSut();

        $testModeResult = (string) $sut->callConnectionStatus(
            $this->makeSettings(true),
            ConnectionResult::connected()
        );
        $liveModeResult = (string) $sut->callConnectionStatus(
            $this->makeSettings(false),
            ConnectionResult::connected()
        );

        self::assertStringContainsString('Test API', $testModeResult);
        self::assertStringContainsString('Live API', $liveModeResult);
    }
}
