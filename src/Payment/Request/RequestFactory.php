<?php

namespace Mollie\WooCommerce\Payment\Request;

use Psr\Container\ContainerInterface;
use Mollie\WooCommerce\Payment\Request\RequestStrategyInterface;
use WC_Order;

class RequestFactory {
    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Create a request based on the type.
     *
     * @param string $type 'order' or 'payment'.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $customerId Customer ID for the request.
     * @return array The generated request data.
     */
    public function createRequest(string $type, WC_Order $order, string $customerId): array {
        // Use the container to fetch the appropriate strategy.
        $strategy = $this->container->get("request.strategy.{$type}");

        if (!$strategy instanceof RequestStrategyInterface) {
            throw new \InvalidArgumentException("Invalid strategy for type: {$type}");
        }

        return $strategy->createRequest($order, $customerId);
    }
}
