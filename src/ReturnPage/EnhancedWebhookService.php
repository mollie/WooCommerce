<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage;

/**
 * Enhanced webhook service that integrates with race condition detection
 * This would extend or modify the existing MollieOrderService
 */
class EnhancedWebhookService
{
    private WebhookRaceConditionDetector $detector;

    public function __construct(WebhookRaceConditionDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Hook into the existing webhook processing to track timing
     */
    public function enhanceWebhookProcessing(): void
    {
// This would integrate with the existing webhook handlers
        add_action('mollie_payment_webhook_received', [$this, 'trackWebhookTiming'], 5, 2);
    }

    /**
     * Track webhook timing when webhooks are received
     */
    public function trackWebhookTiming(int $order_id, string $payment_id): void
    {
        $this->detector->trackWebhookTiming($order_id, $payment_id);
    }
}
