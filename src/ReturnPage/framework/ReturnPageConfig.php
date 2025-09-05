<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Configuration container for payment method settings
 */
class ReturnPageConfig
{
    /**
     * @var string
     */
    private string $paymentMethodId;

    /**
     * @var StatusCheckerInterface
     */
    private StatusCheckerInterface $statusChecker;

    /**
     * @var int
     */
    private int $retryCount;

    /**
     * @var int
     */
    private int $interval;

    /**
     * @var array
     */
    private array $messages;

    /**
     * @var StatusUpdaterInterface|null
     */
    private ?StatusUpdaterInterface $statusUpdater;

    /**
     * @var MessageRendererInterface|null
     */
    private ?MessageRendererInterface $messageRenderer;

    /**
     * @var array
     */
    private array $statusActions;

    /**
     * @var IncidentLoggerInterface|null
     */
    private ?IncidentLoggerInterface $incidentLogger;

    /**
     * @var AdaptiveConfigInterface|null
     */
    private ?AdaptiveConfigInterface $adaptiveConfig;

    public function __construct(
        string $paymentMethodId,
        StatusCheckerInterface $statusChecker,
        int $retryCount = 10,
        int $interval = 2500,
        array $messages = [],
        ?StatusUpdaterInterface $statusUpdater = null,
        ?MessageRendererInterface $messageRenderer = null,
        array $statusActions = [],
        ?IncidentLoggerInterface $incidentLogger = null,
        ?AdaptiveConfigInterface $adaptiveConfig = null
    ) {
        $this->paymentMethodId = $paymentMethodId;
        $this->statusChecker = $statusChecker;
        $this->retryCount = $retryCount;
        $this->interval = $interval;
        $this->messages = $messages;
        $this->statusUpdater = $statusUpdater;
        $this->messageRenderer = $messageRenderer;
        $this->statusActions = $statusActions;
        $this->incidentLogger = $incidentLogger;
        $this->adaptiveConfig = $adaptiveConfig;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function getStatusChecker(): StatusCheckerInterface
    {
        return $this->statusChecker;
    }

    public function getRetryCount(?\WC_Order $order = null): int
    {
        return $this->adaptiveConfig !== null ? $this->adaptiveConfig->getRetryCount($order) : $this->retryCount;
    }

    public function getInterval(?\WC_Order $order = null): int
    {
        return $this->adaptiveConfig !== null ? $this->adaptiveConfig->getInterval($order) : $this->interval;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getStatusUpdater(): ?StatusUpdaterInterface
    {
        return $this->statusUpdater;
    }

    public function getMessageRenderer(): ?MessageRendererInterface
    {
        return $this->messageRenderer;
    }

    public function getStatusActions(): array
    {
        return $this->statusActions;
    }

    public function getIncidentLogger(): ?IncidentLoggerInterface
    {
        return $this->incidentLogger;
    }

    public function getAdaptiveConfig(): ?AdaptiveConfigInterface
    {
        return $this->adaptiveConfig;
    }

    public function shouldMonitor(\WC_Order $order): bool
    {
        return !($this->adaptiveConfig !== null) || $this->adaptiveConfig->shouldMonitor($order);
    }
}
