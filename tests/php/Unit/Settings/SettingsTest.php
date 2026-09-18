<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Settings;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Status;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Settings\Settings::processUploadedFile
 */
class SettingsTest extends TestCase
{
    private const GATEWAY_ID = 'mollie_wc_gateway_ideal';

    protected function setUp(): void
    {
        parent::setUp();
        when('admin_url')->justReturn('https://example.com/wp-admin/');
        when('wp_handle_upload')->justReturn([]);
    }

    protected function tearDown(): void
    {
        unset($_FILES);
        parent::tearDown();
    }

    private function makeSut(): Settings
    {
        return new Settings('mollie_wc', null, '8.1.4', 'https://example.com', null, false);
    }

    private function callProcessUploadedFile(Settings $sut, string $name, string $tempName, string $gatewayId): void
    {
        $method = new \ReflectionMethod(Settings::class, 'processUploadedFile');
        $method->setAccessible(true);
        $method->invoke($sut, $name, $tempName, $gatewayId);
    }

    private function callValidateUploadedFile(Settings $sut, string $fileName, string $fileTempName, int $fileSize): bool
    {
        $method = new \ReflectionMethod(Settings::class, 'validateUploadedFile');
        $method->setAccessible(true);
        return $method->invoke($sut, $fileName, $fileTempName, $fileSize);
    }

    private function createTempFile(string $content, string $extension): string
    {
        $path = sys_get_temp_dir() . '/mollie_test_' . uniqid() . '.' . $extension;
        file_put_contents($path, $content);
        return $path;
    }

    private function setUpFilesGlobal(string $gatewayId, string $name, string $tmpPath, string $type = 'image/svg+xml'): void
    {
        $_FILES['woocommerce_' . $gatewayId . '_upload_logo'] = [
            'name'     => $name,
            'type'     => $type,
            'tmp_name' => $tmpPath,
            'error'    => 0,
            'size'     => (int) filesize($tmpPath),
        ];
    }

    // T1: SVG containing <script> tag has it stripped after upload
    public function testSvgScriptTagIsRemovedAfterUpload(): void
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>';
        $storedFile   = $this->createTempFile($maliciousSvg, 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $storedFile);

        when('wp_handle_upload')->justReturn(['url' => 'https://example.com/logo.svg', 'file' => $storedFile]);
        when('get_option')->justReturn([]);
        expect('update_option')->once()->andReturn(true);

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $storedFile, self::GATEWAY_ID);

        self::assertStringNotContainsString('<script', file_get_contents($storedFile));
        @unlink($storedFile);
    }

    // T2: SVG containing on* event-handler attribute has it stripped after upload
    public function testSvgEventHandlerAttributeIsStrippedAfterUpload(): void
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path d="M0 0"/></svg>';
        $storedFile   = $this->createTempFile($maliciousSvg, 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $storedFile);

        when('wp_handle_upload')->justReturn(['url' => 'https://example.com/logo.svg', 'file' => $storedFile]);
        when('get_option')->justReturn([]);
        expect('update_option')->once()->andReturn(true);

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $storedFile, self::GATEWAY_ID);

        self::assertStringNotContainsString('onload=', file_get_contents($storedFile));
        @unlink($storedFile);
    }

    // T3: SVG containing javascript: URI in href attribute has it stripped after upload
    public function testSvgJavascriptUriIsRemovedAfterUpload(): void
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><text>x</text></a></svg>';
        $storedFile   = $this->createTempFile($maliciousSvg, 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $storedFile);

        when('wp_handle_upload')->justReturn(['url' => 'https://example.com/logo.svg', 'file' => $storedFile]);
        when('get_option')->justReturn([]);
        expect('update_option')->once()->andReturn(true);

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $storedFile, self::GATEWAY_ID);

        self::assertStringNotContainsString('javascript:', file_get_contents($storedFile));
        @unlink($storedFile);
    }

    // T4: Safe SVG is stored intact and update_option receives correct iconFileUrl / iconFilePath
    public function testSafeSvgIsStoredIntactWithCorrectSettings(): void
    {
        $safeSvg    = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0 L10 10"/></svg>';
        $storedFile = $this->createTempFile($safeSvg, 'svg');
        $fileUrl    = 'https://example.com/logo.svg';
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $storedFile);

        when('wp_handle_upload')->justReturn(['url' => $fileUrl, 'file' => $storedFile]);
        when('get_option')->justReturn([]);

        $captured = null;
        expect('update_option')
            ->once()
            ->andReturnUsing(static function (string $name, array $settings) use (&$captured): bool {
                $captured = $settings;
                return true;
            });

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $storedFile, self::GATEWAY_ID);

        self::assertStringContainsString('<path', file_get_contents($storedFile));
        self::assertSame($fileUrl, $captured['iconFileUrl'] ?? null);
        self::assertSame($storedFile, $captured['iconFilePath'] ?? null);
        @unlink($storedFile);
    }

    // T5: JPEG file bypasses sanitizer entirely; file content is unchanged and settings are persisted
    public function testNonSvgFileBypassesSanitizerAndIsPersisted(): void
    {
        $jpegContent = "\xFF\xD8\xFF\xE0fake-jpeg-binary";
        $storedFile  = $this->createTempFile($jpegContent, 'jpg');
        $fileUrl     = 'https://example.com/logo.jpg';
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.jpg', $storedFile, 'image/jpeg');

        when('wp_handle_upload')->justReturn(['url' => $fileUrl, 'file' => $storedFile]);
        when('get_option')->justReturn([]);
        expect('update_option')->once()->andReturn(true);
        expect('wp_delete_file')->never();

        $this->callProcessUploadedFile($this->makeSut(), 'logo.jpg', $storedFile, self::GATEWAY_ID);

        self::assertSame($jpegContent, file_get_contents($storedFile));
        @unlink($storedFile);
    }

    // wp_handle_upload returns an error array; settings are not written and no file deletion is attempted
    public function testHandleUploadErrorSkipsSettingsAndFileCleanup(): void
    {
        when('add_action')->justReturn(null);
        when('esc_html__')->returnArg(1);

        $tempFile = $this->createTempFile('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $tempFile);

        when('wp_handle_upload')->justReturn(['error' => 'Upload failed due to file type restriction.']);
        expect('update_option')->never();
        expect('wp_delete_file')->never();

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $tempFile, self::GATEWAY_ID);

        @unlink($tempFile);
    }

    // SVG with unparseable content causes sanitizer to return empty; file is deleted and settings not written
    public function testEmptySanitizerOutputDeletesFileAndSkipsSettings(): void
    {
        when('add_action')->justReturn(null);
        when('esc_html__')->returnArg(1);

        $invalidContent = 'not-valid-xml-or-svg-content';
        $storedFile     = $this->createTempFile($invalidContent, 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $storedFile);

        when('wp_handle_upload')->justReturn(['url' => 'https://example.com/logo.svg', 'file' => $storedFile]);
        when('get_option')->justReturn([]);
        expect('update_option')->never();
        expect('wp_delete_file')->once()->with($storedFile);

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $storedFile, self::GATEWAY_ID);

        self::assertSame($invalidContent, file_get_contents($storedFile));
        @unlink($storedFile);
    }

    // SVG .svg extension passes validateUploadedFile regardless of what finfo detects
    public function testSvgFilePassesValidationRegardlessOfFinfoMime(): void
    {
        when('add_action')->justReturn(null);
        when('esc_html__')->returnArg(1);

        $tmpFile  = $this->createTempFile('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>', 'svg');
        $fileSize = (int) filesize($tmpFile);

        $result = $this->callValidateUploadedFile($this->makeSut(), 'logo.svg', $tmpFile, $fileSize);

        self::assertTrue($result);
        @unlink($tmpFile);
    }

    // Non-image MIME type with non-svg extension is rejected by validateUploadedFile
    public function testNonImageNonSvgFileIsRejectedByValidation(): void
    {
        when('add_action')->justReturn(null);
        when('esc_html__')->returnArg(1);

        $tmpFile  = $this->createTempFile('<?php echo "hello"; ?>', 'php');
        $fileSize = (int) filesize($tmpFile);

        $result = $this->callValidateUploadedFile($this->makeSut(), 'shell.php', $tmpFile, $fileSize);

        self::assertFalse($result);
        @unlink($tmpFile);
    }

    // Error message produced on rejection mentions svg as an allowed format
    public function testRejectionNoticeMessageMentionsSvg(): void
    {
        $capturedCallback = null;
        when('add_action')->alias(static function (string $hook, callable $cb) use (&$capturedCallback): void {
            if ($hook === 'admin_notices') {
                $capturedCallback = $cb;
            }
        });
        when('esc_html__')->returnArg(1);
        when('esc_attr')->returnArg();
        when('wp_kses_post')->returnArg();

        $tmpFile  = $this->createTempFile('<?php echo "hello"; ?>', 'php');
        $fileSize = (int) filesize($tmpFile);

        $this->callValidateUploadedFile($this->makeSut(), 'shell.php', $tmpFile, $fileSize);

        self::assertNotNull($capturedCallback, 'AdminNotice did not register an admin_notices callback');
        ob_start();
        ($capturedCallback)();
        $output = ob_get_clean();
        self::assertStringContainsString('svg', strtolower($output));
        @unlink($tmpFile);
    }

    // Oversized SVG (>500kb) is rejected by the file-size check, not the MIME check
    public function testOversizedSvgIsRejectedByFileSizeNotMimeCheck(): void
    {
        when('add_action')->justReturn(null);
        when('esc_html__')->returnArg(1);

        $tmpFile = $this->createTempFile('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>', 'svg');

        $result = $this->callValidateUploadedFile($this->makeSut(), 'logo.svg', $tmpFile, 600000);

        self::assertFalse($result);
        @unlink($tmpFile);
    }

    // wp_handle_upload error array triggers an admin_notices hook registration
    public function testHandleUploadErrorRegistersAdminNotice(): void
    {
        $capturedCallback = null;
        when('add_action')->alias(static function (string $hook, callable $cb) use (&$capturedCallback): void {
            if ($hook === 'admin_notices') {
                $capturedCallback = $cb;
            }
        });
        when('esc_html__')->returnArg(1);

        $tmpFile = $this->createTempFile('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $tmpFile);
        when('wp_handle_upload')->justReturn(['error' => 'Upload failed due to file type restriction.']);

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $tmpFile, self::GATEWAY_ID);

        self::assertNotNull($capturedCallback, 'Expected an admin_notices callback to be registered on wp_handle_upload error');
        @unlink($tmpFile);
    }

    // SVG sanitizer returning falsy output triggers an admin_notices hook registration
    public function testSanitizerRejectionRegistersAdminNotice(): void
    {
        $capturedCallback = null;
        when('add_action')->alias(static function (string $hook, callable $cb) use (&$capturedCallback): void {
            if ($hook === 'admin_notices') {
                $capturedCallback = $cb;
            }
        });
        when('esc_html__')->returnArg(1);

        $tmpFile = $this->createTempFile('not-valid-xml-or-svg-content', 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $tmpFile);
        when('wp_handle_upload')->justReturn(['url' => 'https://example.com/logo.svg', 'file' => $tmpFile]);
        when('get_option')->justReturn([]);
        expect('wp_delete_file')->once()->with($tmpFile);

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $tmpFile, self::GATEWAY_ID);

        self::assertNotNull($capturedCallback, 'Expected an admin_notices callback to be registered when sanitizer rejects the file');
        @unlink($tmpFile);
    }

    // Notice message shown on rejection contains no words revealing the cause
    public function testRejectionNoticeMessageDoesNotRevealRejectionReason(): void
    {
        $capturedCallback = null;
        when('add_action')->alias(static function (string $hook, callable $cb) use (&$capturedCallback): void {
            if ($hook === 'admin_notices') {
                $capturedCallback = $cb;
            }
        });
        when('esc_html__')->returnArg(1);
        when('esc_attr')->returnArg();
        when('wp_kses_post')->returnArg();

        $tmpFile = $this->createTempFile('not-valid-xml-or-svg-content', 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $tmpFile);
        when('wp_handle_upload')->justReturn(['url' => 'https://example.com/logo.svg', 'file' => $tmpFile]);
        when('get_option')->justReturn([]);
        expect('wp_delete_file')->once();

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $tmpFile, self::GATEWAY_ID);

        self::assertNotNull($capturedCallback, 'Expected an admin_notices callback to be registered');
        ob_start();
        ($capturedCallback)();
        $output = strtolower((string) ob_get_clean());
        foreach (['sanitize', 'malicious', 'blocked', 'script', 'invalid content'] as $bannedWord) {
            self::assertStringNotContainsString($bannedWord, $output, "Notice must not reveal rejection reason via '{$bannedWord}'");
        }
        @unlink($tmpFile);
    }

    // getConnectionStatus() must report whatever the getConnectionStatusWithError() accessor decided
    public function testGetConnectionStatusReturnsConnectedValueFromWithErrorAccessor(): void
    {
        when('get_option')->justReturn('test_dummyapikeydummyapikeydummy');
        when('is_admin')->justReturn(true);

        $statusHelper = \Mockery::mock(Status::class);
        $statusHelper->shouldReceive('isCompatible')->andReturn(true);
        $apiHelper = \Mockery::mock(Api::class);
        // The legacy in-method connection attempt fails; only the accessor's verdict may count.
        $apiHelper->shouldReceive('getApiClient')->andThrow(new ApiException('unreachable', 0));

        $sut = \Mockery::mock(
            Settings::class . '[getConnectionStatusWithError]',
            ['mollie_wc', $statusHelper, '8.1.4', 'https://example.com', $apiHelper, false]
        );
        $sut->shouldAllowMockingProtectedMethods();
        $sut->shouldReceive('getConnectionStatusWithError')->andReturn(['connected' => true]);

        self::assertTrue($sut->getConnectionStatus());
    }

    // getConnectionStatus() must not run a second connection attempt of its own
    public function testGetConnectionStatusPerformsExactlyOneApiConnectionAttempt(): void
    {
        when('get_option')->justReturn('test_dummyapikeydummyapikeydummy');
        when('is_admin')->justReturn(true);

        $apiClient = new \stdClass();
        $statusHelper = \Mockery::mock(Status::class);
        $statusHelper->shouldReceive('isCompatible')->andReturn(true);
        $statusHelper->shouldReceive('getMollieApiStatus')->once()->with($apiClient);
        $apiHelper = \Mockery::mock(Api::class);
        $apiHelper->shouldReceive('getApiClient')->once()->andReturn($apiClient);

        $sut = new Settings('mollie_wc', $statusHelper, '8.1.4', 'https://example.com', $apiHelper, false);

        self::assertTrue($sut->getConnectionStatus());
    }

    /**
     * An API client whose first call fails the way the Mollie SDK fails.
     */
    private function apiClientFailingWith(\Throwable $failure): object
    {
        $methods = new class ($failure) {
            /** @var \Throwable */
            private $failure;

            public function __construct(\Throwable $failure)
            {
                $this->failure = $failure;
            }

            public function all()
            {
                throw $this->failure;
            }
        };

        return new class ($methods) {
            /** @var object */
            public $methods;

            public function __construct(object $methods)
            {
                $this->methods = $methods;
            }
        };
    }

    /**
     * Real Status, so the re-throw in getMollieApiStatus() is exercised rather than mocked away.
     */
    private function realStatusHelper(): Status
    {
        $statusHelper = \Mockery::mock(
            Status::class . '[isCompatible]',
            [\Mockery::mock(\Mollie\Api\CompatibilityChecker::class), 'Mollie Payments for WooCommerce']
        );
        $statusHelper->shouldReceive('isCompatible')->andReturn(true);

        return $statusHelper;
    }

    private function settingsWithApiClient(object $apiClient): Settings
    {
        when('get_option')->justReturn('test_dummyapikeydummyapikeydummy');
        when('is_admin')->justReturn(true);

        $apiHelper = \Mockery::mock(Api::class);
        $apiHelper->shouldReceive('getApiClient')->andReturn($apiClient);

        return new Settings('mollie_wc', $this->realStatusHelper(), '8.1.4', 'https://example.com', $apiHelper, false);
    }

    /**
     * The HTTP status of a failed call must survive the re-throw in Status::getMollieApiStatus(),
     * otherwise every failure looks identical to the settings page.
     *
     * @dataProvider provideApiHttpStatuses
     */
    public function testApiHttpStatusSurvivesIntoTheConnectionStatusArray(int $httpStatus): void
    {
        $sut = $this->settingsWithApiClient(
            $this->apiClientFailingWith(
                new ApiException("Error executing API call ({$httpStatus}: Failed)", $httpStatus)
            )
        );

        $result = $sut->getConnectionStatusWithError();

        self::assertFalse($result['connected']);
        self::assertSame($httpStatus, $result['error_code']);
        self::assertSame(Settings::ERROR_KIND_API, $result['error_kind']);
    }

    public function provideApiHttpStatuses(): array
    {
        return [
            'unauthorized' => [401],
            'bad request' => [400],
            'rate limited' => [429],
            'server error' => [500],
            'gateway timeout' => [504],
        ];
    }

    // A transport failure never reached Mollie, so it has no HTTP status
    public function testTransportFailureIsReportedWithoutAnHttpStatus(): void
    {
        $sut = $this->settingsWithApiClient(
            $this->apiClientFailingWith(new ApiException('cURL error 28: Operation timed out', 0))
        );

        $result = $sut->getConnectionStatusWithError();

        self::assertSame(0, $result['error_code']);
        self::assertSame(Settings::ERROR_KIND_API, $result['error_kind']);
        self::assertStringContainsString('cURL error 28', $result['error_message']);
    }

    // A missing or malformed key fails before any request, and must be reported as a key problem
    public function testApiKeyFailureIsReportedAsAKeyProblemNotAsAnApiFailure(): void
    {
        when('get_option')->justReturn('');
        when('is_admin')->justReturn(false);

        $apiHelper = \Mockery::mock(Api::class);
        $apiHelper->shouldReceive('getApiClient')
            ->andThrow(new ApiException('No API key provided. Please set your Mollie API keys below.'));

        $sut = new Settings('mollie_wc', $this->realStatusHelper(), '8.1.4', 'https://example.com', $apiHelper, false);

        $result = $sut->getConnectionStatusWithError();

        self::assertFalse($result['connected']);
        self::assertSame(Settings::ERROR_KIND_API_KEY, $result['error_kind']);
        self::assertStringContainsString('No API key provided', $result['error_message']);
    }

    // An incompatible environment reports the real compatibility errors, not a placeholder
    public function testIncompatibleEnvironmentReportsTheCompatibilityErrors(): void
    {
        $statusHelper = \Mockery::mock(Status::class);
        $statusHelper->shouldReceive('isCompatible')->andReturn(false);
        $statusHelper->shouldReceive('getErrors')->andReturn(['Mollie requires PHP 7.4 or higher.']);

        $sut = new Settings('mollie_wc', $statusHelper, '8.1.4', 'https://example.com', \Mockery::mock(Api::class), false);

        $result = $sut->getConnectionStatusWithError();

        self::assertSame(Settings::ERROR_KIND_INCOMPATIBLE, $result['error_kind']);
        self::assertStringContainsString('PHP 7.4 or higher', $result['error_message']);
        self::assertNotSame('Incompatible environment', $result['error_message']);
    }

    // The message handed to the settings page carries no ISO-8601 prefix from ApiException
    public function testReportedMessageIsFreeOfExceptionDecoration(): void
    {
        $sut = $this->settingsWithApiClient(
            $this->apiClientFailingWith(new ApiException('Error executing API call (503: Service Unavailable)', 503))
        );

        $result = $sut->getConnectionStatusWithError();

        self::assertRegExp('/^Error executing API call/', $result['error_message']);
        self::assertStringNotContainsString('[', $result['error_message']);
    }

    // End to end: a wrong API key must not be presented as a server connectivity problem
    public function testWrongApiKeyRendersAsAKeyProblemOnTheSettingsPage(): void
    {
        when('esc_html')->returnArg();
        when('esc_html__')->returnArg(1);

        $sut = $this->settingsWithApiClient(
            $this->apiClientFailingWith(
                new ApiException(
                    'Error executing API call (401: Unauthorized Request): Missing authentication, or failed to authenticate',
                    401
                )
            )
        );

        $renderer = new class {
            use \Mollie\WooCommerce\Settings\Page\Section\ConnectionStatusTrait;

            public function render(Settings $settings, array $connectionStatus): ?string
            {
                return $this->connectionStatus($settings, $connectionStatus);
            }
        };

        $settingsForRender = \Mockery::mock(Settings::class);
        $settingsForRender->shouldReceive('isTestModeEnabled')->andReturn(true);

        $message = (string) $renderer->render($settingsForRender, $sut->getConnectionStatusWithError());

        self::assertStringContainsStringIgnoringCase('api key', $message);
        self::assertStringNotContainsStringIgnoringCase('ssl', $message);
        self::assertStringNotContainsStringIgnoringCase('outbound connectivity', $message);
    }

    // Gateway logo setting is not written when sanitizer rejects the file (notice-enabled path)
    public function testSanitizerRejectionLeavesSettingsUnchangedAfterNotice(): void
    {
        when('add_action')->justReturn(null);
        when('esc_html__')->returnArg(1);

        $tmpFile = $this->createTempFile('not-valid-xml-or-svg-content', 'svg');
        $this->setUpFilesGlobal(self::GATEWAY_ID, 'logo.svg', $tmpFile);
        when('wp_handle_upload')->justReturn(['url' => 'https://example.com/logo.svg', 'file' => $tmpFile]);
        when('get_option')->justReturn([]);
        expect('update_option')->never();
        expect('wp_delete_file')->once()->with($tmpFile);

        $this->callProcessUploadedFile($this->makeSut(), 'logo.svg', $tmpFile, self::GATEWAY_ID);

        @unlink($tmpFile);
    }
}
