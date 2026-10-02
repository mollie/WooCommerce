<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Rules;

use Mollie\WooCommerce\Payment\Rules\WebhookGuards;
use Mollie\WooCommerceTests\TestCase;

/**
 * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards
 */
class WebhookGuardsTest extends TestCase
{
    /**
     * Scenario: the first matching check answers
     *   Given the five order facts
     *   When needsPayment() is asked
     *   Then the first matching check decides
     *
     * @dataProvider needsPaymentCases
     * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards::needsPayment
     */
    public function testNeedsPaymentAnswersTheFirstOfFiveChecksThatMatches(
        bool $paidByOtherGateway,
        bool $paidAndProcessed,
        bool $authorized,
        bool $wcNeedsPayment,
        bool $onHoldAsInitialStatus,
        bool $expected
    ): void {

        $answer = WebhookGuards::needsPayment(
            $paidByOtherGateway,
            $paidAndProcessed,
            $authorized,
            $wcNeedsPayment,
            $onHoldAsInitialStatus
        );

        self::assertSame($expected, $answer);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool, 3: bool, 4: bool, 5: bool}>
     */
    public function needsPaymentCases(): array
    {
        return [
            'REQ-121 paid by another gateway' => [true, true, false, false, false, false],
            'REQ-345 paid by another gateway outranks not processed by Mollie' => [true, false, false, false, false, false],
            'REQ-345 paid by another gateway outranks every yes' => [true, true, true, true, true, false],
            'REQ-345 not processed by Mollie' => [false, false, false, false, false, true],
            'REQ-345 not processed by Mollie answers before the other checks' => [false, false, false, false, true, true],
            'REQ-345 processed and authorized' => [false, true, true, false, false, true],
            'REQ-345 processed but WooCommerce needs payment' => [false, true, false, true, false, true],
            'REQ-345 processed and on hold as the method starts orders' => [false, true, false, false, true, true],
            'REQ-344 processed and nothing else' => [false, true, false, false, false, false],
        ];
    }

    /**
     * Scenario: only a terminal status for an untracked payment is superseded
     *   Given whether the payment is tracked, and its status
     *   When superseded() is asked
     *   Then only failed, canceled or cancelled and untracked is true
     *
     * @dataProvider supersededCases
     * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards::superseded
     */
    public function testSupersededOnlyForATerminalStatusOfAnUntrackedPayment(
        bool $tracksThisPayment,
        string $paymentStatus,
        bool $expected
    ): void {

        self::assertSame($expected, WebhookGuards::superseded($tracksThisPayment, $paymentStatus));
    }

    /**
     * @return array<string, array{0: bool, 1: string, 2: bool}>
     */
    public function supersededCases(): array
    {
        return [
            'REQ-343 failed for an untracked payment' => [false, 'failed', true],
            'REQ-356 canceled for an untracked payment' => [false, 'canceled', true],
            'REQ-343 the cancelled spelling for an untracked payment' => [false, 'cancelled', true],
            'REQ-357 expired for an untracked payment is left to its handler' => [false, 'expired', false],
            'REQ-341 paid for an untracked payment' => [false, 'paid', false],
            'REQ-341 an empty status for an untracked payment' => [false, '', false],
            'REQ-341 failed for the tracked payment' => [true, 'failed', false],
            'REQ-341 canceled for the tracked payment' => [true, 'canceled', false],
        ];
    }

    /**
     * Scenario: the first matching guard gives the verdict
     *   Given gateway, tracking, status and needs-payment
     *   When decide() runs
     *   Then not ours, superseded, settled and process win in that order
     *
     * @dataProvider decideCases
     * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards::decide
     */
    public function testDecideReturnsTheVerdictOfTheFirstGuardThatMatches(
        bool $isMollieGateway,
        bool $tracksThisPayment,
        string $paymentStatus,
        bool $needsPayment,
        string $expected
    ): void {

        $verdict = WebhookGuards::decide($isMollieGateway, $tracksThisPayment, $paymentStatus, $needsPayment);

        self::assertSame($expected, $verdict);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: string, 3: bool, 4: string}>
     */
    public function decideCases(): array
    {
        return [
            'REQ-346 not a Mollie gateway' => [false, true, 'paid', true, WebhookGuards::NOT_OURS],
            'REQ-346 not ours outranks superseded' => [false, false, 'failed', true, WebhookGuards::NOT_OURS],
            'REQ-346 not ours outranks settled' => [false, true, 'paid', false, WebhookGuards::NOT_OURS],
            'REQ-346 not ours before the payment is known' => [false, false, '', false, WebhookGuards::NOT_OURS],
            'REQ-343 failed for an untracked payment' => [true, false, 'failed', true, WebhookGuards::SUPERSEDED],
            'REQ-356 canceled for an untracked payment' => [true, false, 'canceled', true, WebhookGuards::SUPERSEDED],
            'REQ-345 superseded outranks settled' => [true, false, 'failed', false, WebhookGuards::SUPERSEDED],
            'REQ-387 duplicate paid delivery on a processed order' => [true, true, 'paid', false, WebhookGuards::SETTLED],
            'REQ-129 any status for an order that needs no payment' => [true, true, 'pending', false, WebhookGuards::SETTLED],
            'REQ-357 expired for an untracked payment of a settled order' => [true, false, 'expired', false, WebhookGuards::SETTLED],
            'REQ-341 paid for the tracked payment' => [true, true, 'paid', true, WebhookGuards::PROCESS],
            'REQ-341 failed for the tracked payment' => [true, true, 'failed', true, WebhookGuards::PROCESS],
            'REQ-357 expired for an untracked payment is left to its handler' => [true, false, 'expired', true, WebhookGuards::PROCESS],
            'REQ-341 paid for an untracked payment' => [true, false, 'paid', true, WebhookGuards::PROCESS],
            'REQ-342 a status without a handler is still dispatched' => [true, true, 'open', true, WebhookGuards::PROCESS],
        ];
    }

    /**
     * Scenario: the verdicts are the logged words
     *   Given the verdict constants
     *   Then they are not_ours, superseded, settled and process
     *
     * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards
     */
    public function testVerdictsAreTheFourWordsTheStepRecords(): void
    {
        self::assertSame(
            ['not_ours', 'superseded', 'settled', 'process'],
            [WebhookGuards::NOT_OURS, WebhookGuards::SUPERSEDED, WebhookGuards::SETTLED, WebhookGuards::PROCESS]
        );
    }
}
