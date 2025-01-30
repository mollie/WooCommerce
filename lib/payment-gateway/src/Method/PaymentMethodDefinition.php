<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway\Method;

use Inpsyde\PaymentGateway\GatewayIconsRendererInterface;
use Inpsyde\PaymentGateway\IconProviderInterface;
use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Inpsyde\PaymentGateway\PaymentMethodServiceProviderTrait;
use Inpsyde\PaymentGateway\PaymentProcessorInterface;
use Inpsyde\PaymentGateway\PaymentRequestValidatorInterface;
use Inpsyde\PaymentGateway\RefundProcessorInterface;
use Psr\Container\ContainerInterface;
use WC_Payment_Gateway;

/**
 * This interface describes a payment method within the service locator paradigm
 * of this payment gateway library/module.
 * It is not an object that serves an immediate purpose at runtime.
 * It is a convenience tool to make the process of adding payment method easy and understandable:
 * Instead of having to follow the README and add handwritten service definitions,
 * this interface can be implemented with full IDE support.
 *
 * Hence, the methods of this interface have the same signature as modularity service definitions,
 * and they will be used as such
 *
 * @see PaymentMethodServiceProviderTrait
 */
interface PaymentMethodDefinition
{
    public function id(): string;

    public function paymentProcessor(ContainerInterface $container): PaymentProcessorInterface;

    public function paymentRequestValidator(ContainerInterface $container): PaymentRequestValidatorInterface;

    public function title(ContainerInterface $container): string;

    public function methodTitle(ContainerInterface $container): string;

    public function description(ContainerInterface $container): string;

    public function methodDescription(ContainerInterface $container): string;

    /**
     * @param ContainerInterface $container
     *
     * @return callable(WC_Payment_Gateway): bool
     */
    public function availabilityCallback(ContainerInterface $container): callable;

    public function supports(ContainerInterface $container): array;

    public function refundProcessor(ContainerInterface $container): RefundProcessorInterface;

    public function paymentMethodIconProvider(ContainerInterface $container): IconProviderInterface;

    public function gatewayIconsRenderer(ContainerInterface $container): GatewayIconsRendererInterface;

    public function paymentFieldsRenderer(ContainerInterface $container): PaymentFieldsRendererInterface;

    public function hasFields(ContainerInterface $container): bool;

    public function formFields(ContainerInterface $container): array;

    public function optionKey(ContainerInterface $container): string;

    public function registerBlocks(ContainerInterface $container): bool;

    public function orderButtonText(ContainerInterface $container): string;

    public function customSettings(): CustomSettingsFieldsDefinition;
}
