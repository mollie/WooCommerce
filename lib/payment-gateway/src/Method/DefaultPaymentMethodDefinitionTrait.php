<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway\Method;

use Mollie\Inpsyde\PaymentGateway\GatewayIconsRendererInterface;
use Mollie\Inpsyde\PaymentGateway\IconProviderInterface;
use Mollie\Inpsyde\PaymentGateway\DefaultIconsRenderer;
use Mollie\Inpsyde\PaymentGateway\NoopPaymentProcessor;
use Mollie\Inpsyde\PaymentGateway\NoopPaymentRequestValidator;
use Mollie\Inpsyde\PaymentGateway\NoopRefundProcessor;
use Mollie\Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Inpsyde\PaymentGateway\PaymentProcessorInterface;
use Mollie\Inpsyde\PaymentGateway\PaymentRequestValidatorInterface;
use Mollie\Inpsyde\PaymentGateway\RefundProcessorInterface;
use Mollie\Inpsyde\PaymentGateway\ServiceKeyGenerator;
use Mollie\Inpsyde\PaymentGateway\StaticIconProvider;
use Mollie\Psr\Container\ContainerExceptionInterface;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Container\NotFoundExceptionInterface;
/**
 * Trait providing default implementations for PaymentMethodDefinition interface methods.
 */
trait DefaultPaymentMethodDefinitionTrait
{
    protected function ensureServiceKeyGenerator(): ServiceKeyGenerator
    {
        static $keyGen;
        if (!$keyGen) {
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
        $instance = wp_filter_object_list(\WC_Payment_Gateways::instance()->payment_gateways(), ['id' => $this->id()]);
        $mine = reset($instance);
        if (!$mine instanceof PaymentGateway) {
            throw new \RuntimeException("Payment Gateway {$this->id()} not registered before accessing");
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
        return static fn() => \true;
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
            $iconProvider = $container->get($this->ensureServiceKeyGenerator()->createKey('method_icon_provider'));
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $exception) {
            $iconProvider = $container->get($this->ensureServiceKeyGenerator()->createFallbackKey('method_icon_provider'));
        }
        assert($iconProvider instanceof IconProviderInterface);
        return new DefaultIconsRenderer($iconProvider);
    }
    public function paymentFieldsRenderer(ContainerInterface $container): PaymentFieldsRendererInterface
    {
        /**
         * Trigger fallback within PaymentGateway by pretending the service does not exist
         */
        throw new class("Method 'paymentFieldsRenderer' not implemented.") extends \Exception implements NotFoundExceptionInterface
        {
        };
    }
    public function hasFields(ContainerInterface $container): bool
    {
        return \false;
    }
    public function formFields(ContainerInterface $container): array
    {
        //TODO i18n
        return ['enabled' => ['title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable payment method', 'default' => 'no']];
    }
    public function optionKey(ContainerInterface $container): string
    {
        /**
         * Trigger fallback within PaymentGateway by pretending the service does not exist
         */
        throw new class("Method 'optionKey' not implemented.") extends \Exception implements NotFoundExceptionInterface
        {
        };
    }
    public function registerBlocks(ContainerInterface $container): bool
    {
        return \true;
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
