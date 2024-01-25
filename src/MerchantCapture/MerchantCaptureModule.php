<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture;

use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\CapturePayment;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\VoidPayment;
use Mollie\WooCommerce\MerchantCapture\Capture\Type\ManualCapture;
use Mollie\WooCommerce\MerchantCapture\Capture\Type\StateChangeCapture;
use Mollie\WooCommerce\MerchantCapture\UI\OrderActionBlock;
use Mollie\WooCommerce\MerchantCapture\UI\StatusRenderer;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

class MerchantCaptureModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    public const ORDER_PAYMENT_STATUS_META_KEY = '_mollie_payment_status';

    public function services(): array
    {
        return [
                'merchant.manual_capture.enabled' => static function () {
                    $captureType = get_option('mollie-payments-for-woocommerce_place_payment_onhold');
                    return $captureType === 'later_capture';
                },
                'merchant.manual_capture.supported_methods' => static function () {
                    return ['mollie_wc_gateway_creditcard'];
                },
                'merchant.manual_capture.void_statuses' => static function () {
                    return apply_filters('mollie_wc_gateway_void_order_state', [SharedDataDictionary::STATUS_ON_HOLD]);
                },
                'merchant.manual_capture.capture_statuses' => static function () {
                    return apply_filters('mollie_wc_gateway_capture_order_state', [SharedDataDictionary::STATUS_ON_HOLD]);
                },
                'merchant.manual_capture.is_authorized' => static function ($container) {
                    return static function (WC_Order $order) use ($container) {
                        $orderIsAuthorized = $order->get_meta(
                            self::ORDER_PAYMENT_STATUS_META_KEY
                        ) === ManualCaptureStatus::STATUS_AUTHORIZED;
                        $isManualCaptureMethod = in_array(
                            $order->get_payment_method(),
                            $container->get('merchant.manual_capture.supported_methods')
                        );

                        return $isManualCaptureMethod && $orderIsAuthorized;
                    };
                },
                'merchant.manual_capture.is_waiting' => static function ($container) {
                    return static function (WC_Order $order) use ($container) {
                        $orderIsWaiting = $order->get_meta(
                            self::ORDER_PAYMENT_STATUS_META_KEY
                        ) === ManualCaptureStatus::STATUS_WAITING;
                        $isManualCaptureMethod = in_array(
                            $order->get_payment_method(),
                            $container->get('merchant.manual_capture.supported_methods')
                        );

                        return $isManualCaptureMethod && $orderIsWaiting;
                    };
                },
                'merchant.manual_capture.can_capture_the_order' => static function ($container) {
                    return static function (WC_Order $order) use ($container) {
                        $orderIsAuthorized = $order->get_meta(
                            self::ORDER_PAYMENT_STATUS_META_KEY
                        ) === ManualCaptureStatus::STATUS_AUTHORIZED;
                        $isManualCaptureMethod = in_array(
                            $order->get_payment_method(),
                            $container->get('merchant.manual_capture.supported_methods')
                        );
                        $isCorrectState = in_array(
                            $order->get_status(),
                            $container->get('merchant.manual_capture.capture_statuses')
                        );
                        return $isManualCaptureMethod && $orderIsAuthorized && $isCorrectState;
                    };
                },
                'merchant.manual_capture.on_status_change_enabled' => static function () {
                    return get_option('mollie-payments-for-woocommerce_capture_or_void', false);
                },
                'merchant.manual_capture.cart_can_be_captured' => static function (): bool {
                    if (!class_exists(\WC_Product_Subscription::class)) {
                        return true;
                    }
                    $cart = WC()->cart;
                    if (!is_a($cart, \WC_Cart::class)) {
                        return false;
                    }
                    $cartItems = $cart->get_cart_contents();

                    foreach ($cartItems as $cartItemData) {
                        $cartItem = $cartItemData['data'];

                        if (is_a($cartItem, \WC_Product_Subscription::class)) {
                            return false;
                        }
                    }
                    return true;
                },
                CapturePayment::class => static function ($container) {
                    return static function (int $orderId) use ($container) {
                        /** @var Api $api */
                        $api = $container->get('SDK.api_helper');

                        /** @var Settings $settings */
                        $settings = $container->get('settings.settings_helper');

                        /** @var Logger $logger */
                        $logger = $container->get(Logger::class);

                        $pluginId = $container->get('shared.plugin_id');

                        return (new CapturePayment($orderId, $api, $settings, $logger, $pluginId))();
                    };
                },
                VoidPayment::class => static function ($container) {
                    return static function (int $orderId) use ($container) {
                        /** @var Api $api */
                        $api = $container->get('SDK.api_helper');

                        /** @var Settings $settings */
                        $settings = $container->get('settings.settings_helper');

                        /** @var Logger $logger */
                        $logger = $container->get(Logger::class);

                        $pluginId = $container->get('shared.plugin_id');

                        return (new VoidPayment($orderId, $api, $settings, $logger, $pluginId))();
                    };
                },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        add_action('init', static function () use ($container) {
            $pluginId = $container->get('shared.plugin_id');
            $captureSettings = new MollieCaptureSettings();
            if (!apply_filters('mollie_wc_gateway_enable_merchant_capture_module', false)) {
                return;
            }

            add_action(
                $pluginId . '_after_webhook_action',
                static function (Payment $payment, WC_Order $order) use ($container) {

                    if ($payment->isAuthorized()) {
                        if (!$payment->getAmountCaptured() == 0.0) {
                            return;
                        }
                        $order->set_status(SharedDataDictionary::STATUS_ON_HOLD);
                        $order->update_meta_data(
                            self::ORDER_PAYMENT_STATUS_META_KEY,
                            ManualCaptureStatus::STATUS_AUTHORIZED
                        );
                        $order->set_transaction_id($payment->id);
                        $order->save();
                    } elseif (
                        $payment->isPaid() && (
                            ($container->get('merchant.manual_capture.is_waiting'))($order) ||
                            ($container->get('merchant.manual_capture.is_authorized'))($order)
                        )
                    ) {
                        $order->update_meta_data(
                            self::ORDER_PAYMENT_STATUS_META_KEY,
                            ManualCaptureStatus::STATUS_CAPTURED
                        );
                        $order->save();
                    } elseif (
                        $payment->isCanceled()  && (
                            ($container->get('merchant.manual_capture.is_waiting'))($order) ||
                            ($container->get('merchant.manual_capture.is_authorized'))($order)
                        )
                    ) {
                        $order->update_meta_data(
                            self::ORDER_PAYMENT_STATUS_META_KEY,
                            ManualCaptureStatus::STATUS_VOIDED
                        );
                        $order->save();
                    }
                },
                10,
                2
            );

            add_action('woocommerce_order_refunded', static function (int $orderId) use ($container) {
                $order = wc_get_order($orderId);
                if (!is_a($order, WC_Order::class)) {
                    return;
                }
                $merchantCanCapture = ($container->get('merchant.manual_capture.is_authorized'))($order);
                if ($merchantCanCapture) {
                    ($container->get(VoidPayment::class))($order->get_id());
                }
            });
            add_action('woocommerce_order_actions_start', static function (int $orderId) use ($container) {
                $order = wc_get_order($orderId);
                if (!is_a($order, WC_Order::class)) {
                    return;
                }
                $paymentStatus = $order->get_meta(MerchantCaptureModule::ORDER_PAYMENT_STATUS_META_KEY, true);
                $actionBlockParagraphs = [];

                ob_start();
                (new StatusRenderer())($paymentStatus);

                $actionBlockParagraphs[] = ob_get_clean();
                if (($container->get('merchant.manual_capture.can_capture_the_order'))($order)) {
                    $actionBlockParagraphs[] = __(
                        'To capture the authorized payment, select capture action from the list below.',
                        'mollie-payments-for-woocommerce'
                    );
                } elseif (($container->get('merchant.manual_capture.is_authorized'))($order)) {
                    $actionBlockParagraphs[] = __(
                        'Before capturing the authorized payment, ensure to set the order status to On Hold.',
                        'mollie-payments-for-woocommerce'
                    );
                }
                (new OrderActionBlock())($actionBlockParagraphs);
            });
            add_filter(
                'mollie_wc_gateway_disable_ship_and_capture',
                static function ($disableShipAndCapture, WC_Order $order) use ($container) {
                    if ($disableShipAndCapture) {
                        return true;
                    }
                    return $container->get('merchant.manual_capture.is_waiting')($order) || $container->get('merchant.manual_capture.is_authorized')($order);
                },
                10,
                2
            );
            add_filter(
                'inpsyde.mollie-advanced-settings',
                [$captureSettings, 'settings'],
                10,
                2
            );
            new OrderListPaymentColumn();
            new ManualCapture($container);
            new StateChangeCapture($container);
        });

        return true;
    }
}
