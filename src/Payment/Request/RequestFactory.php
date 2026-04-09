<?php

namespace Mollie\WooCommerce\Payment\Request;

use Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface;
use Mollie\Psr\Container\ContainerInterface;
use WC_Order;
/**
 * Class RequestFactory
 *
 * This class is responsible for creating payment requests based on the provided type.
 *
 * @package Mollie\WooCommerce\Payment\Request
 */
class RequestFactory
{
    /**
     * @var ContainerInterface The container interface for dependency injection.
     */
    private ContainerInterface $container;
    /**
     * RequestFactory constructor.
     *
     * @param ContainerInterface $container The container interface for dependency injection.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    /**
     * Create a request based on the type.
     *
     * @param string $type The type of request ('order' or 'payment').
     * @param WC_Order $order The WooCommerce order object.
     * @param string|null $customerId The customer ID for the request.
     * @return array The generated request data.
     * @throws \InvalidArgumentException If the strategy for the given type is invalid.
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
