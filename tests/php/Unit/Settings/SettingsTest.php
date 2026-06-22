<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Settings;

use Mollie\WooCommerce\Settings\Settings;
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
}
