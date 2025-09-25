<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway\Method;

use Inpsyde\PaymentGateway\GatewayIconsRendererInterface;
use Inpsyde\PaymentGateway\IconProviderInterface;
use Inpsyde\PaymentGateway\DefaultIconsRenderer;
use Inpsyde\PaymentGateway\NoopPaymentProcessor;
use Inpsyde\PaymentGateway\NoopPaymentRequestValidator;
use Inpsyde\PaymentGateway\NoopRefundProcessor;
use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Inpsyde\PaymentGateway\PaymentGateway;
use Inpsyde\PaymentGateway\PaymentProcessorInterface;
use Inpsyde\PaymentGateway\PaymentRequestValidatorInterface;
use Inpsyde\PaymentGateway\RefundProcessorInterface;
use Inpsyde\PaymentGateway\ServiceKeyGenerator;
use Inpsyde\PaymentGateway\StaticIconProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Trait providing default implementations for PaymentMethodDefinition interface methods.
 */
trait DefaultPaymentMethodDefinitionTrait
{
    protected function ensureServiceKeyGenerator(): ServiceKeyGenerator
    {
        static $keyGen;
        if (! $keyGen) {
            $keyGen = new ServiceKeyGenerator($this->id());
        }

        return $keyGen;
    }

    /**
     * Retrieves the PaymentGateway instance associated with this definition.
     * It is identified by $this->id() and requires the Gateway
     * to be registered to WooCommerce already.
     *
     * @return PaymentGateway
     */
    protected function fetchInstance(): PaymentGateway
    {
        $instance = wp_filter_object_list(
            \WC_Payment_Gateways::instance()->payment_gateways(),
            [
                'id' => $this->id(),
            ]
        );
        $mine = reset($instance);
        if (! $mine instanceof PaymentGateway) {
            throw new \RuntimeException(
                "Payment Gateway {$this->id()} not registered before accessing"
            );
        }

        return $mine;
    }

    public function isEnabled(ContainerInterface $container): bool
    {
        $instance = $this->fetchInstance();

        return $instance->get_option('enabled') === 'yes';
    }

    public function paymentProcessor(ContainerInterface $container): PaymentProcessorInterface
    {
        return new NoopPaymentProcessor();
    }

    public function paymentRequestValidator(ContainerInterface $container): PaymentRequestValidatorInterface
    {
        return new NoopPaymentRequestValidator();
    }

    public function title(ContainerInterface $container): string
    {
        return $this->id();
    }

    public function methodTitle(ContainerInterface $container): string
    {
        return $this->id();
    }

    public function description(ContainerInterface $container): string
    {
        return $this->id();
    }

    public function methodDescription(ContainerInterface $container): string
    {
        return $this->id();
    }

    public function availabilityCallback(ContainerInterface $container): callable
    {
        return static fn() => true;
    }

    public function supports(ContainerInterface $container): array
    {
        return ['products'];
    }

    public function refundProcessor(ContainerInterface $container): RefundProcessorInterface
    {
        return new NoopRefundProcessor();
    }

    public function paymentMethodIconProvider(ContainerInterface $container): IconProviderInterface
    {
        return new StaticIconProvider();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function gatewayIconsRenderer(ContainerInterface $container): GatewayIconsRendererInterface
    {
        try {
            $iconProvider = $container->get(
                $this->ensureServiceKeyGenerator()->createKey('method_icon_provider')
            );
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $exception) {
            $iconProvider = $container->get(
                $this->ensureServiceKeyGenerator()->createFallbackKey('method_icon_provider')
            );
        }
        assert($iconProvider instanceof IconProviderInterface);

        return new DefaultIconsRenderer($iconProvider);
    }

    public function paymentFieldsRenderer(ContainerInterface $container): PaymentFieldsRendererInterface
    {
        /**
         * Trigger fallback within PaymentGateway by pretending the service does not exist
         */
        throw new class ("Method 'paymentFieldsRenderer' not implemented.") extends \Exception implements
            NotFoundExceptionInterface
        {
        };
    }

    public function hasFields(ContainerInterface $container): bool
    {
        return false;
    }

    public function formFields(ContainerInterface $container): array
    {
        //TODO i18n
        return [
            'enabled' => [
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable payment method',
                'default' => 'no',
            ],
        ];
    }

    public function optionKey(ContainerInterface $container): string
    {
        /**
         * Trigger fallback within PaymentGateway by pretending the service does not exist
         */
        throw new class ("Method 'optionKey' not implemented.") extends \Exception implements
            NotFoundExceptionInterface
        {
        };
    }

    public function registerBlocks(ContainerInterface $container): bool
    {
        return true;
    }

    public function orderButtonText(ContainerInterface $container): string
    {
        return '';
    }

    public function customSettings(): CustomSettingsFieldsDefinition
    {
        return new CustomSettingsFields([], []);
    }
}
