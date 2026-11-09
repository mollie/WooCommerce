<?php

declare (strict_types=1);
namespace Mollie;

// phpcs:disable Inpsyde.CodeQuality.LineLength
use Mollie\Psr\Container\ContainerInterface;
return static function (): array {
    return ['payment_gateways' => static function (array $gateways, ContainerInterface $container): array {
        $paymentMethods = $container->get('gateway.getPaymentMethodsAfterFeatureFlag');
        $paymentMethodsEnabledAtMollie = $container->get('gateway.paymentMethodsEnabledAtMollie');
        foreach ($paymentMethods as $paymentMethod) {
            if (!\in_array($paymentMethod['id'], $paymentMethodsEnabledAtMollie)) {
                continue;
            }
            $gatewayId = 'mollie_wc_gateway_' . $paymentMethod['id'];
            $gateways[] = $gatewayId;
        }
        return $gateways;
    }];
};
