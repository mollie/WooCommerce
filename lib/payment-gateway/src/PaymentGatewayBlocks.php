<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter
// phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
// phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType


declare(strict_types=1);

namespace Inpsyde\PaymentGateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Psr\Container\ContainerInterface;
use WC_Payment_Gateways;

class PaymentGatewayBlocks extends AbstractPaymentMethodType
{
    private ContainerInterface $container;

    /**
     * phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     * @var string
     */
    protected $name;

    private ?PaymentGateway $gateway = null;

    public function __construct(ContainerInterface $container, string $gatewayId)
    {
        $this->container = $container;
        $this->name = $gatewayId;
    }

    public function initialize()
    {
        // TODO: Implement initialize() method.
    }

    public function is_active()
    {
        return true;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $scriptPath = '/js/frontend/blocks.js';
        $scriptAssetPath = $this->container
                ->get('payment_gateways.assets_path') . '/js/frontend/blocks.asset.php';
        $scriptAsset = file_exists($scriptAssetPath)
            ? require($scriptAssetPath)
            : [
                'dependencies' => [],
                'version' => '0.1.0',
            ];
        $scriptUrl = $this->container->get('payment_gateways.assets_url') . $scriptPath;
        $scriptId = 'inpsyde-blocks';
        /**
         * @psalm-suppress MixedArgument
         */
        wp_register_script(
            $scriptId,
            $scriptUrl,
            $scriptAsset['dependencies'],
            $scriptAsset['version'],
            true
        );
        /**
         * @psalm-suppress MixedArgument
         */
        wp_localize_script($scriptId, 'inpsydeGateways', $this->container->get('payment_gateways'));

        return [$scriptId];
    }

    public function get_payment_method_data()
    {
        $gateway = $this->gateway();

        return [
            'title' => $gateway->get_title(),
            'description' => $gateway->get_description(),
            'supports' => array_filter($gateway->supports, [$gateway, 'supports']),
            'placeOrderButtonLabel' => $gateway->order_button_text,
        ];
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
