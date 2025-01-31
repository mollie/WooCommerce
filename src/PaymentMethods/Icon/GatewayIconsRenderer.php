<?php

namespace Mollie\WooCommerce\PaymentMethods\Icon;

use Inpsyde\PaymentGateway\GatewayIconsRendererInterface;
use Inpsyde\PaymentGateway\IconProviderInterface;
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
            //we just have one
            $icon = $this->iconProvider->provideIcons()[0];
            return apply_filters(
                $this->paymentMethod->id() . '_icon_url',
                $icon->src()
            );
        }
        return '';
    }
}
