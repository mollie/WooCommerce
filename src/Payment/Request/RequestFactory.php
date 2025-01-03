<?php

namespace Mollie\WooCommerce\Payment\Request;

use Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface;
use Psr\Container\ContainerInterface;
use WC_Order;

class RequestFactory
{
    private $container;

    public function __construct(ContainerInterface $container)
    {

        $this->container = $container;
    }

    /**
     * Create a request based on the type.
     *
     * @param string $type 'order' or 'payment'.
     * @param WC_Order $order The WooCommerce order object.
     * @param string|null $customerId Customer ID for the request.
     * @return array The generated request data.
     */
    public function createRequest(string $type, WC_Order $order, $customerId): array
    {
        // Use the container to fetch the appropriate strategy.
        $serviceName = "request.strategy.{$type}";
        $strategy = $this->container->get($serviceName);

        if (!$strategy instanceof RequestStrategyInterface) {
            throw new \InvalidArgumentException("Invalid strategy for type: {$type}");
        }

        return $strategy->createRequest($order, $customerId);
    }
}
