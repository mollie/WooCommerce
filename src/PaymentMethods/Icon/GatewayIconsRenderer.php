<?php

namespace Mollie\WooCommerce\PaymentMethods\Icon;

use Inpsyde\PaymentGateway\GatewayIconsRendererInterface;
use Psr\Container\ContainerInterface;

class GatewayIconsRenderer implements GatewayIconsRendererInterface
{
    private string $gatewayId;
    private ContainerInterface $container;
    public function __construct(string $gatewayId, ContainerInterface $container)
    {
        $this->gatewayId = $gatewayId;
        $this->container = $container;
    }
    /**
     * @inheritDoc
     */
    public function renderIcons(): string
    {
        $paymentMethods = $this->container->get('gateway.paymentMethods');
        $methodId = substr($this->gatewayId, strrpos($this->gatewayId, '_') + 1);
        $paymentMethod = $paymentMethods[$methodId];
        if ($paymentMethod->shouldDisplayIcon()) {
            $defaultIcon = $paymentMethod->getIconUrl();
            return apply_filters(
                $this->gatewayId . '_icon_url',
                $defaultIcon
            );
        }
        return '';
    }
}
