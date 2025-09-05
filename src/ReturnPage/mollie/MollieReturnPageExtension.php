<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\mollie;

use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\ReturnPage\framework\ReturnPageConfig;
use Mollie\WooCommerce\ReturnPage\framework\ReturnPageManager;
use Psr\Log\LoggerInterface;

/**
 * Mollie Return Page Extension - The Main Integration Class
 */
class MollieReturnPageExtension
{
    private ReturnPageManager $manager;
    private MollieOrderService $orderService;
    private LoggerInterface $logger;

    public function __construct(
        ReturnPageManager $manager,
        MollieOrderService $orderService,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->manager = $manager;
    }

    public function init(): void
    {
// Create Mollie-specific implementations
        $incidentLogger = new MollieSmartIncidentLogger($this->logger);
        $adaptiveConfig = new MollieAdaptiveConfig($incidentLogger, $this->logger);

        $statusChecker = new MollieStatusChecker($this->orderService);
        $statusUpdater = new MollieStatusUpdater($this->orderService, $this->logger);
        $messageRenderer = new MollieMessageRenderer();

// Create configuration for each Mollie payment method
        $mollieGateways = [
            'mollie_wc_gateway_ideal',
// ... add other Mollie gateways
        ];

        foreach ($mollieGateways as $gatewayId) {
            $config = new ReturnPageConfig(
                $gatewayId,
                $statusChecker,
                12, // Default, will be adjusted by adaptive config
                2500, // Default, will be adjusted by adaptive config
                [
                    'loading' => __(
                        'Verifying your payment with Mollie...',
                        'mollie-payments-for-woocommerce'
                    ),
                    'success' => __(
                        '✅ Payment confirmed! Your order is being processed.',
                        'mollie-payments-for-woocommerce'
                    ),
                    'failed' => __(
                        '❌ Payment was not successful. Please try again or contact support.',
                        'mollie-payments-for-woocommerce'
                    ),
                    'timeout' => __(
                        '⏳ Payment verification is taking longer than expected. We\'re checking with Mollie to confirm your payment.',
                        'mollie-payments-for-woocommerce'
                    ),
                    'error' => __(
                        'Unable to verify payment status. If you completed the payment, your order will be processed automatically.',
                        'mollie-payments-for-woocommerce'
                    )
                ],
                $statusUpdater,
                $messageRenderer,
                [], // Could add custom actions here
                $incidentLogger,
                $adaptiveConfig
            );

            $this->manager->registerPaymentMethod($config);
        }
        $this->manager->init();

// Initialize admin interface
        $admin = new MollieReturnPageAdmin($incidentLogger);
        $admin->init();
    }
}
