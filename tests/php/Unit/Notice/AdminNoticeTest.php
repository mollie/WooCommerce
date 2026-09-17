<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Notice;

use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Notice\AdminNotice::renderNotice
 */
class AdminNoticeTest extends TestCase
{
    private AdminNotice $sut;

    protected function setUp(): void
    {
        parent::setUp();
        when('esc_attr')->returnArg();
        when('wp_kses_post')->returnArg();
        $this->sut = new AdminNotice();
    }

    /**
     * @scenario Error-level notices carry the data-mollie-error attribute
     * Given a notice level containing the notice-error token
     * When the notice markup is rendered
     * Then the notice div keeps the notice-error class and has the data-mollie-error attribute
     *
     * @dataProvider errorLevelProvider
     */
    public function testRenderNoticeAddsDataMollieErrorForErrorLevels(string $level): void
    {
        $html = $this->sut->renderNotice($level, 'msg');

        self::assertRegExp('/<div\b[^>]*class="[^"]*\bnotice-error\b[^"]*"/', $html);
        self::assertRegExp('/<div\b[^>]*\sdata-mollie-error\b/', $html);
        self::assertStringContainsString('msg', $html);
    }

    /**
     * @scenario Warning-level notices do not carry the data-mollie-error attribute
     * Given a notice level without the notice-error token
     * When the notice markup is rendered
     * Then the notice div has no data-mollie-error attribute
     *
     * @dataProvider warningLevelProvider
     */
    public function testRenderNoticeOmitsDataMollieErrorForWarningLevels(string $level): void
    {
        $html = $this->sut->renderNotice($level, 'msg');

        self::assertRegExp('/<div\b[^>]*class="[^"]*\bnotice-warning\b[^"]*"/', $html);
        self::assertStringNotContainsString('data-mollie-error', $html);
        self::assertStringContainsString('msg', $html);
    }

    public function errorLevelProvider(): array
    {
        return [
            'notice-error' => ['notice-error'],
            'notice-error is-dismissible' => ['notice-error is-dismissible'],
        ];
    }

    public function warningLevelProvider(): array
    {
        return [
            'notice-warning' => ['notice-warning'],
            'notice-warning is-dismissible' => ['notice-warning is-dismissible'],
            'class merely prefixed with notice-error' => ['notice-warning notice-error-like'],
        ];
    }
}
