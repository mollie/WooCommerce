<?php

declare (strict_types=1);
namespace Mollie;

use Mollie\Inpsyde\PaymentGateway\PaymentProcessorInterface;
use Mollie\Inpsyde\PaymentGateway\StaticIconProvider;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressCheckoutGate;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\Psr\Container\ContainerInterface;
// The Expresscheckout payment method is not a Mollie method: these replace what its definition
// would take from Mollie's methods list and from gateway settings it does not have.
return static function (): array {
    $gatewayId = 'mollie_wc_gateway_' . Constants::EXPRESSCHECKOUT;
    $service = static fn(string $key): string => "payment_gateway.{$gatewayId}.{$key}";
    $title = static fn(): string => \__('Express checkout', 'mollie-payments-for-woocommerce');
    return [
        'payment_gateways' => static function (array $gateways) use ($gatewayId): array {
            if (!ExpressCheckoutGate::belongsToRequest()) {
                return $gateways;
            }
            $gateways[] = $gatewayId;
            return $gateways;
        },
        $service('availability_callback') => static function ($previous, ContainerInterface $container): callable {
            $gate = $container->get(ExpressCheckoutGate::class);
            \assert($gate instanceof ExpressCheckoutGate);
            return static fn(): bool => $gate->isAvailable();
        },
        $service('payment_processor') => static function ($previous, ContainerInterface $container): PaymentProcessorInterface {
            $gate = $container->get(ExpressCheckoutGate::class);
            \assert($gate instanceof ExpressCheckoutGate);
            return $gate;
        },
        $service('title') => $title,
        $service('method_title') => $title,
        $service('supports') => static fn(): array => ['products'],
        $service('method_icon_provider') => static fn(): StaticIconProvider => new StaticIconProvider(),
        // Its own script registers it, in the payment step.
        $service('register_blocks') => static fn(): bool => \false,
    ];
};
