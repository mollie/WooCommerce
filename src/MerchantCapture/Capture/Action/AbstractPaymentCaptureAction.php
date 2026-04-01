<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\Capture\Action;

use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Psr\Log\LoggerInterface;

class AbstractPaymentCaptureAction
{
    /** @var Api */
    protected $apiHelper;
    /** @var Settings */
    protected $settingsHelper;
    /** @var string */
    protected $apiKey;
    /** @var \WC_Order|false */
    protected $order;
    /** @var LoggerInterface */
    protected $logger;
    /** @var string */
    protected $pluginId;

    public function __construct(
        int $orderId,
        Api $apiHelper,
        Settings $settingsHelper,
        LoggerInterface $logger,
        string $pluginId
    ) {

        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->order = wc_get_order($orderId);
        $this->logger = $logger;
        $this->pluginId = $pluginId;
        $this->setApiKey();
    }

    protected function setApiKey(): void
    {
        $this->apiKey = $this->settingsHelper->getApiKey();
    }
}
