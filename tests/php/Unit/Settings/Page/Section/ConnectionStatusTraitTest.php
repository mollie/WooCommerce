<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Settings\Page\Section;

use Mollie\WooCommerce\Settings\Page\Section\ConnectionStatusTrait;
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

            public function callConnectionStatus(Settings $settings, array $connectionStatus): ?string
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
    private function apiFailure(int $errorCode, string $errorMessage): array
    {
        return [
            'connected' => false,
            'error_kind' => Settings::ERROR_KIND_API,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];
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

    // Criterion 5: code 0 on the API stage means the request never reached Mollie
    public function testNetworkErrorMessageEmbedsUnderlyingErrorVerbatim(): void
    {
        $underlyingError = 'cURL error 28: Operation timed out after 10000 milliseconds';

        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(0, $underlyingError)
        );

        self::assertStringContainsString(
            $underlyingError,
            $result,
            'The server-connectivity message must embed the non-empty underlying error detail'
        );
        // Not just the raw error echoed back — it must be framed as a server-side problem.
        self::assertRegExp('/your server/i', $result);
        self::assertStringNotContainsStringIgnoringCase('api key', $result);
    }

    // A missing or malformed API key is never a connectivity problem, even though it has no HTTP status
    public function testApiKeyProblemIsNotPresentedAsAConnectivityProblem(): void
    {
        foreach (
            [
                'No API key provided. Please set your Mollie API keys below.',
                "Invalid API key(s). The API key(s) must start with 'live_' or 'test_'.",
            ] as $keyMessage
        ) {
            $result = (string) $this->makeSut()->callConnectionStatus(
                $this->makeSettings(),
                [
                    'connected' => false,
                    'error_kind' => Settings::ERROR_KIND_API_KEY,
                    'error_code' => 0,
                    'error_message' => $keyMessage,
                ]
            );

            self::assertStringNotContainsStringIgnoringCase('ssl', $result);
            self::assertStringNotContainsStringIgnoringCase('outbound connectivity', $result);
            self::assertStringContainsString($keyMessage, $result);
        }
    }

    // A plugin-authored key message carries an intentional link, which must survive rendering
    public function testApiKeyMessageMarkupIsNotEscapedAway(): void
    {
        $messageWithLink = 'Invalid API key(s). Get them on the '
            . '<a href="https://my.mollie.com/dashboard/developers/api-access-tokens" target="_blank">Developers page</a>.';

        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            [
                'connected' => false,
                'error_kind' => Settings::ERROR_KIND_API_KEY,
                'error_code' => 0,
                'error_message' => $messageWithLink,
            ]
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
            [
                'connected' => false,
                'error_kind' => Settings::ERROR_KIND_INCOMPATIBLE,
                'error_code' => 0,
                'error_message' => $compatibilityError,
            ]
        );

        self::assertStringContainsString($compatibilityError, $result);
        self::assertStringNotContainsString('Incompatible environment', $result);
        self::assertStringNotContainsStringIgnoringCase('ssl', $result);
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

    // Criterion 6b: with nothing to show, the legacy message keeps the field non-empty
    public function testUnrecognizedErrorCodeWithEmptyMessageFallsBackToLegacyString(): void
    {
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            $this->apiFailure(418, '')
        );

        self::assertStringContainsString(self::LEGACY_MESSAGE, $result);
    }

    // A section built before the detail was threaded through still renders the legacy message
    public function testBareDisconnectedStatusFallsBackToLegacyString(): void
    {
        $result = (string) $this->makeSut()->callConnectionStatus(
            $this->makeSettings(),
            ['connected' => false]
        );

        self::assertStringContainsString(self::LEGACY_MESSAGE, $result);
    }

    // Criterion 7: the success messages are unchanged and still follow the test-mode setting
    public function testSuccessfulConnectionMessageReflectsTestModeSetting(): void
    {
        $sut = $this->makeSut();

        $testModeResult = (string) $sut->callConnectionStatus(
            $this->makeSettings(true),
            ['connected' => true]
        );
        $liveModeResult = (string) $sut->callConnectionStatus(
            $this->makeSettings(false),
            ['connected' => true]
        );

        self::assertStringContainsString('Test API', $testModeResult);
        self::assertStringContainsString('Live API', $liveModeResult);
    }
}
