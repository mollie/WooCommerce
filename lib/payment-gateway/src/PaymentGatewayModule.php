<?php

//phpcs:disable Inpsyde.CodeQuality.NestingLevel.High
//phpcs:disable Inpsyde.CodeQuality.LineLength.TooLong
//phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Inpsyde\Modularity\Package;
use Mollie\Inpsyde\Modularity\Properties\PluginProperties;
use Mollie\Inpsyde\PaymentGateway\Fields\ContentField;
use Mollie\Inpsyde\PaymentGateway\Method\PaymentMethodDefinition;
use Mollie\Psr\Container\ContainerExceptionInterface;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Container\NotFoundExceptionInterface;
class PaymentGatewayModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    use PaymentMethodServiceProviderTrait;
    /**
     * @var PaymentMethodDefinition[]
     */
    private array $paymentMethods;
    public function __construct(PaymentMethodDefinition ...$paymentMethods)
    {
        $this->paymentMethods = $paymentMethods;
    }
    public function services(): array
    {
        return array_merge([
            /**
             * WooCommerce (>= 9.6) derives the payment gateway plugin slug via reflection
             * (see \Automattic\WooCommerce\Internal\Admin\Settings\PaymentProviders\PaymentGateway::get_plugin_slug)
             * but first checks if the plugin_slug property is set. By setting it explicitly here, we
             * prevent potential namespace conflicts when multiple plugins use this payment gateway library.
             */
            'payment_gateways.plugin_slug' => static function (ContainerInterface $container): string {
                /** @var PluginProperties $properties */
                $pluginProperties = $container->get(Package::PROPERTIES);
                return $pluginProperties->baseName();
            },
            'payment_gateways.assets_url' => function (): string {
                return $this->getPluginFileUrlFromAbsolutePath(dirname(__DIR__) . '/assets');
            },
            'payment_gateways.assets_path' => static function (): string {
                return dirname(__DIR__) . '/assets';
            },
            'payment_gateways.noop_payment_request_validator' => static function (): PaymentRequestValidatorInterface {
                return new NoopPaymentRequestValidator();
            },
            'payment_gateways.noop_payment_processor' => static function (): PaymentProcessorInterface {
                return new NoopPaymentProcessor();
            },
            'payment_gateways.noop_refund_processor' => static function (): RefundProcessorInterface {
                return new NoopRefundProcessor();
            },
            'payment_gateways.settings_field_renderer.content' => static function (): SettingsFieldRendererInterface {
                return new ContentField();
            },
            'payment_gateways' => function (): array {
                $gateways = [];
                foreach ($this->paymentMethods as $paymentMethod) {
                    $gateways[] = $paymentMethod->id();
                }
                return $gateways;
            },
            'payment_gateways.methods_supporting_blocks' => static function (ContainerInterface $container): array {
                $supported = [];
                $allMethods = $container->get('payment_gateways');
                foreach ($allMethods as $method) {
                    $registerBlocksKey = 'payment_gateway.' . $method . '.register_blocks';
                    $shouldRegister = \true;
                    if ($container->has($registerBlocksKey)) {
                        $shouldRegister = (bool) $container->get($registerBlocksKey);
                    }
                    if ($shouldRegister) {
                        $supported[] = $method;
                    }
                }
                return $supported;
            },
            'payment_gateways.required_services' => static function (): array {
                return ['payment_gateway.%s.payment_request_validator', 'payment_gateway.%s.payment_processor'];
            },
            'payment_gateways.validator' => static function (ContainerInterface $container): PaymentGatewayValidator {
                $requiredServices = $container->get('payment_gateways.required_services');
                assert(is_array($requiredServices));
                return new PaymentGatewayValidator($container, $requiredServices);
            },
            'payment_gateways.i18n' => static fn(ContainerInterface $container): I18n => new I18n($container),
            'payment_gateways.i18n.messages' => static fn(): array => ['refund_order_not_found' => static fn(array $params): string => sprintf(
                /* translators: %1$s is replaced with the actual order ID. */
                __('Failed to process the refund: the order with ID %1$s not found', 'syde-payment-gateway'),
                (string) $params['orderId']
            ), 'refund_failed' => __('Failed to refund the order payment', 'syde-payment-gateway'), 'payment_method_not_available' => __('Payment method not available. Please select another payment method.', 'syde-payment-gateway')],
        ], $this->providePaymentMethodServices(...$this->paymentMethods));
    }
    public function run(ContainerInterface $container): bool
    {
        add_filter('woocommerce_payment_gateways', static function (array $gateways) use ($container) {
            $gatewayIds = $container->get('payment_gateways');
            $gatewayValidator = $container->get('payment_gateways.validator');
            assert($gatewayValidator instanceof PaymentGatewayValidator);
            foreach ($gatewayIds as $gatewayId) {
                assert(is_string($gatewayId));
                if ($gatewayValidator->validate($gatewayId)) {
                    $gateways[] = new PaymentGateway($gatewayId, $container);
                }
            }
            return $gateways;
        });
        /**
         * Registers WooCommerce Blocks integration.
         *
         */
        add_action('woocommerce_init', function () use ($container): void {
            if (!class_exists(AbstractPaymentMethodType::class)) {
                return;
            }
            add_action('woocommerce_blocks_payment_method_type_registration', function (PaymentMethodRegistry $registry) use ($container) {
                $gatewayIds = $container->get('payment_gateways');
                $gatewayValidator = $container->get('payment_gateways.validator');
                assert($gatewayValidator instanceof PaymentGatewayValidator);
                foreach ($gatewayIds as $gatewayId) {
                    assert(is_string($gatewayId));
                    if (!$gatewayValidator->validate($gatewayId)) {
                        continue;
                    }
                    $this->registerBlocksSupportUnlessDisabled($gatewayId, $container, $registry);
                }
            });
        });
        return \true;
    }
    /**
     * Get the full URL to a file within a plugin, given its absolute file path.
     *
     * @param string $absoluteFilePath The absolute path to the file.
     *
     * @return string The full URL to the file.
     * @throws \RuntimeException if not found in active plugins.
     */
    private function getPluginFileUrlFromAbsolutePath(string $absoluteFilePath): string
    {
        /**
         * We will be doing string comparisons, so we have to ensure we work with
         * sanitized data
         */
        $absoluteFilePath = wp_normalize_path($absoluteFilePath);
        $activePlugins = wp_get_active_and_valid_plugins();
        $networkPlugins = function_exists('wp_get_active_network_plugins') ? wp_get_active_network_plugins() : [];
        $plugins = array_merge($activePlugins, $networkPlugins);
        // Iterate through active plugins to find the matching one
        foreach ($plugins as $plugin) {
            $pluginPath = \WP_PLUGIN_DIR . '/' . dirname(plugin_basename($plugin));
            /**
             * Again, ensure the path is sanitized before doing the comparison
             */
            $pluginPath = wp_normalize_path($pluginPath);
            if (0 === strpos($absoluteFilePath, $pluginPath)) {
                $relativePath = (string) substr($absoluteFilePath, strlen($pluginPath) + 1);
                $baseUrl = plugins_url('', $plugin);
                return $baseUrl . '/' . $relativePath;
            }
        }
        throw new \RuntimeException('Could not derive plugin path');
        // File not found in active plugins
    }
    /**
     * Register blocks integration after checking a method-specific key to control whether this method
     * opts out of the functionality. This might be needed for integrations that do not yet support the
     * needed JS components but also cannot work with a generic approach.
     *
     * @param string $gatewayId
     * @param ContainerInterface $container
     * @param PaymentMethodRegistry $registry
     *
     * @return void
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    private function registerBlocksSupportUnlessDisabled(string $gatewayId, ContainerInterface $container, PaymentMethodRegistry $registry): void
    {
        /**
         * @var array $supportedMethods
         */
        $supportedMethods = $container->get('payment_gateways.methods_supporting_blocks');
        if (!in_array($gatewayId, $supportedMethods, \true)) {
            return;
        }
        $registry->register(new PaymentGatewayBlocks($container, $gatewayId));
    }
}
