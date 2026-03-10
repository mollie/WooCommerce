<?php

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;
/**
 * Class MiddlewareHandler
 *
 * This class is responsible for managing and executing a sequence of middleware components.
 * Each middleware component can modify the request data before it is processed further.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class MiddlewareHandler
{
    /**
     * @var array The list of middleware.
     */
    private array $middlewares;
    /**
     * MiddlewareHandler constructor.
     *
     * @param array $middlewares The list of middleware.
     */
    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }
    /**
     * Handle the request data through the middleware chain.
     *
     * @param array $requestData The request data.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context The context of the request.
     * @return array The modified request data.
     */
    public function handle(array $requestData, WC_Order $order, $context): array
    {
        $middlewareChain = $this->createMiddlewareChain($this->middlewares);
        return $middlewareChain($requestData, $order, $context);
    }
    /**
     * Create a chain of middleware.
     *
     * @param array $middlewares The list of middleware.
     * @return callable The middleware chain.
     */
    private function createMiddlewareChain(array $middlewares): callable
    {
        return array_reduce(array_reverse($middlewares), static function ($next, $middleware) {
            return static function ($requestData, $order, $context) use ($middleware, $next) {
                return $middleware($requestData, $order, $context, $next);
            };
        }, static function ($requestData) {
            return $requestData;
        });
    }
}
