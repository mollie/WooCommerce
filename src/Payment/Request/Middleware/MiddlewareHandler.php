<?php

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use WC_Order;

class MiddlewareHandler
{
    private array $middlewares;

    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }

    public function handle(array $requestData, WC_Order $order, $context = null): array
    {
        $middlewareChain = $this->createMiddlewareChain($this->middlewares);

        return $middlewareChain($requestData, $order, $context);
    }

    private function createMiddlewareChain(array $middlewares): callable
    {
        return array_reduce(
            array_reverse($middlewares),
            function ($next, $middleware) {
                return function ($requestData, $order, $context) use ($middleware, $next) {
                    return $middleware($requestData, $order, $context, $next);
                };
            },
            function ($requestData) {
                return $requestData;
            }
        );
    }
}
