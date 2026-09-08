<?php

namespace Mollie\WooCommerce\PaymentMethods\Icon;

use Mollie\Inpsyde\PaymentGateway\GatewayIconsRendererInterface;
use Mollie\Inpsyde\PaymentGateway\IconProviderInterface;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
class GatewayIconsRenderer implements GatewayIconsRendererInterface
{
    private PaymentMethodI $paymentMethod;
    private IconProviderInterface $iconProvider;
    public function __construct(PaymentMethodI $paymentMethod, IconProviderInterface $paymentMethodIconProvider)
    {
        $this->paymentMethod = $paymentMethod;
        $this->iconProvider = $paymentMethodIconProvider;
    }
    /**
     * @inheritDoc
     */
    public function renderIcons(): string
    {
        if ($this->paymentMethod->shouldDisplayIcon()) {
            $icons = $this->iconProvider->provideIcons();
            $html = '';
            foreach ($icons as $icon) {
                $url = apply_filters($this->paymentMethod->id() . '_icon_url', $icon->src());
                $html .= "<img src='{$url}' alt='{$icon->alt()}' class='mollie-gateway-icon' />";
            }
            return $html;
        }
        return '';
    }
}
