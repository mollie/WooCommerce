<?php

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
     * @group REQ-121
     * @group REQ-344
     * @group REQ-345
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
     * Scenario: an untracked payment is superseded unless Mollie created it for the order
     *   Given whether the payment is tracked, whether Mollie created it for the order, and its status
     *   When superseded() is asked
     *   Then an untracked payment created for another order is always superseded, and one created
     *   for this order only with failed, canceled or cancelled
     *
     * @dataProvider supersededCases
     * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards::superseded
     * @group REQ-343
     * @group REQ-356
     */
    public function testSupersededForAForeignOrTerminalUntrackedPayment(
        bool $tracksThisPayment,
        bool $createdForOrder,
        string $paymentStatus,
        bool $expected
    ): void {

        self::assertSame($expected, WebhookGuards::superseded($tracksThisPayment, $createdForOrder, $paymentStatus));
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: string, 3: bool}>
     */
    public function supersededCases(): array
    {
        return [
            'REQ-343 failed for an untracked payment' => [false, true, 'failed', true],
            'REQ-356 canceled for an untracked payment' => [false, true, 'canceled', true],
            'REQ-343 the cancelled spelling for an untracked payment' => [false, true, 'cancelled', true],
            'REQ-357 expired for an untracked payment is left to its handler' => [false, true, 'expired', false],
            'REQ-341 paid for an untracked payment created for the order' => [false, true, 'paid', false],
            'REQ-341 an empty status for an untracked payment' => [false, true, '', false],
            'REQ-341 failed for the tracked payment' => [true, true, 'failed', false],
            'REQ-341 canceled for the tracked payment' => [true, true, 'canceled', false],
            'foreign paid payment' => [false, false, 'paid', true],
            'foreign authorized payment' => [false, false, 'authorized', true],
            'foreign expired payment' => [false, false, 'expired', true],
            'foreign failed payment' => [false, false, 'failed', true],
            'the tracked payment needs no metadata' => [true, false, 'paid', false],
        ];
    }

    /**
     * Scenario: the first matching guard gives the verdict
     *   Given gateway, tracking, created-for-order, status and needs-payment
     *   When decide() runs
     *   Then not ours, superseded, settled and process win in that order
     *
     * @dataProvider decideCases
     * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards::decide
     */
    public function testDecideReturnsTheVerdictOfTheFirstGuardThatMatches(
        bool $isMollieGateway,
        bool $tracksThisPayment,
        bool $createdForOrder,
        string $paymentStatus,
        bool $needsPayment,
        string $expected,
        bool $inLinkedMollieOrder = false
    ): void {

        $verdict = WebhookGuards::decide(
            $isMollieGateway,
            $tracksThisPayment,
            $createdForOrder,
            $paymentStatus,
            $needsPayment,
            $inLinkedMollieOrder
        );

        self::assertSame($expected, $verdict);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool, 3: string, 4: bool, 5: string, 6?: bool}>
     */
    public function decideCases(): array
    {
        return [
            'REQ-346 not a Mollie gateway' => [false, true, true, 'paid', true, WebhookGuards::NOT_OURS],
            'REQ-346 not ours outranks superseded' => [false, false, true, 'failed', true, WebhookGuards::NOT_OURS],
            'REQ-346 not ours outranks settled' => [false, true, true, 'paid', false, WebhookGuards::NOT_OURS],
            'REQ-346 not ours before the payment is known' => [false, false, false, '', false, WebhookGuards::NOT_OURS],
            'REQ-343 failed for an untracked payment' => [true, false, true, 'failed', true, WebhookGuards::SUPERSEDED],
            'REQ-356 canceled for an untracked payment' => [true, false, true, 'canceled', true, WebhookGuards::SUPERSEDED],
            'REQ-345 superseded outranks settled' => [true, false, true, 'failed', false, WebhookGuards::SUPERSEDED],
            'REQ-387 duplicate paid delivery on a processed order' => [true, true, true, 'paid', false, WebhookGuards::SETTLED],
            'REQ-129 any status for an order that needs no payment' => [true, true, true, 'pending', false, WebhookGuards::SETTLED],
            'REQ-357 expired for an untracked payment of a settled order' => [true, false, true, 'expired', false, WebhookGuards::SETTLED],
            'paid for an untracked payment of an order another payment settled' => [true, false, true, 'paid', false, WebhookGuards::PAID_ELSEWHERE],
            'authorized for an untracked payment of an order another payment settled' => [true, false, true, 'authorized', false, WebhookGuards::PAID_ELSEWHERE],
            'open for an untracked payment of a settled order has taken nothing yet' => [true, false, true, 'open', false, WebhookGuards::SETTLED],
            'paid for another payment of the Mollie order the settled order is linked to' => [true, false, true, 'paid', false, WebhookGuards::SETTLED, true],
            'REQ-341 paid for the tracked payment' => [true, true, true, 'paid', true, WebhookGuards::PROCESS],
            'REQ-341 failed for the tracked payment' => [true, true, true, 'failed', true, WebhookGuards::PROCESS],
            'REQ-357 expired for an untracked payment is left to its handler' => [true, false, true, 'expired', true, WebhookGuards::PROCESS],
            'REQ-341 paid for an untracked payment created for the order' => [true, false, true, 'paid', true, WebhookGuards::PROCESS],
            'foreign paid payment' => [true, false, false, 'paid', true, WebhookGuards::SUPERSEDED],
            'foreign authorized payment' => [true, false, false, 'authorized', true, WebhookGuards::SUPERSEDED],
            'foreign payment outranks settled' => [true, false, false, 'paid', false, WebhookGuards::SUPERSEDED],
            'REQ-342 a status without a handler is still dispatched' => [true, true, true, 'open', true, WebhookGuards::PROCESS],
        ];
    }

    /**
     * Scenario: the verdicts are the logged words
     *   Given the verdict constants
     *   Then they are not_ours, superseded, settled, paid_elsewhere and process
     *
     * @covers \Mollie\WooCommerce\Payment\Rules\WebhookGuards
     */
    public function testVerdictsAreTheWordsTheStepRecords(): void
    {
        self::assertSame(
            ['not_ours', 'superseded', 'settled', 'paid_elsewhere', 'process'],
            [WebhookGuards::NOT_OURS, WebhookGuards::SUPERSEDED, WebhookGuards::SETTLED, WebhookGuards::PAID_ELSEWHERE, WebhookGuards::PROCESS]
        );
    }
}
