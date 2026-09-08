<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter
// phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
// phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Mollie\Psr\Container\ContainerExceptionInterface;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Container\NotFoundExceptionInterface;
use WC_Payment_Gateways;
class PaymentGatewayBlocks extends AbstractPaymentMethodType
{
    private ContainerInterface $container;
    /**
     * phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     *
     * @var string
     */
    protected $name;
    private ?PaymentGateway $gateway = null;
    private ServiceKeyGenerator $serviceKeyGenerator;
    public function __construct(ContainerInterface $container, string $gatewayId)
    {
        $this->container = $container;
        $this->name = $gatewayId;
        $this->serviceKeyGenerator = new ServiceKeyGenerator($gatewayId);
    }
    public function initialize()
    {
        // TODO: Implement initialize() method.
    }
    public function is_active()
    {
        $gateway = $this->gateway();
        return filter_var($gateway->enabled, \FILTER_VALIDATE_BOOLEAN);
    }
    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function get_payment_method_script_handles()
    {
        $scriptPath = '/js/frontend/blocks.js';
        $scriptAssetPath = $this->container->get('payment_gateways.assets_path') . '/js/frontend/blocks.asset.php';
        $scriptAsset = file_exists($scriptAssetPath) ? require $scriptAssetPath : ['dependencies' => [], 'version' => '0.1.0'];
        // Simple filter so clients can add more dependencies
        $scriptAsset['dependencies'] = apply_filters('inpsyde_payment_gateway_blocks_dependencies', $scriptAsset['dependencies']);
        $scriptUrl = $this->container->get('payment_gateways.assets_url') . $scriptPath;
        $scriptId = 'inpsyde-blocks';
        /**
         * @psalm-suppress MixedArgument
         */
        wp_register_script($scriptId, $scriptUrl, $scriptAsset['dependencies'], $scriptAsset['version'], \true);
        /**
         * @psalm-suppress MixedArgument
         */
        wp_localize_script($scriptId, 'inpsydeGateways', $this->container->get('payment_gateways.methods_supporting_blocks'));
        return [$scriptId];
    }
    public function get_payment_method_data()
    {
        $gateway = $this->gateway();
        $iconProvider = $this->container->get($this->serviceKeyGenerator->createKey('method_icon_provider'));
        assert($iconProvider instanceof IconProviderInterface);
        $data = ['title' => $gateway->get_title(), 'description' => $gateway->get_description(), 'supports' => array_filter($gateway->supports, [$gateway, 'supports']), 'placeOrderButtonLabel' => $gateway->order_button_text, 'icons' => array_map(static fn(Icon $i) => ['id' => $i->id(), 'alt' => $i->alt(), 'src' => $i->src()], $iconProvider->provideIcons())];
        return apply_filters('inpsyde_payment_gateway_blocks_data', $data, $this->name, $gateway);
    }
    protected function gateway(): PaymentGateway
    {
        if ($this->gateway !== null) {
            return $this->gateway;
        }
        $wcPaymentGateways = WC_Payment_Gateways::instance();
        $gateways = $wcPaymentGateways->payment_gateways();
        foreach ($gateways as $gatewayId => $gateway) {
            if ($gatewayId === $this->name) {
                assert($gateway instanceof PaymentGateway);
                $this->gateway = $gateway;
                return $this->gateway;
            }
        }
        throw new \RuntimeException('Gateway not found');
    }
}
