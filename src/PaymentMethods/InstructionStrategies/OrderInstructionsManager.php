<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

use WC_Order;
class OrderInstructionsManager
{
    protected $strategy;
    protected static $alreadyDisplayedAdminInstructions = \false;
    protected static $alreadyDisplayedCustomerInstructions = \false;
    public function setStrategy($deprecatedGatewayHelper)
    {
        if (!$deprecatedGatewayHelper->paymentMethod()->getProperty('instructions')) {
            $this->strategy = new \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DefaultInstructionStrategy();
        } else {
            $className = 'Mollie\WooCommerce\PaymentMethods\InstructionStrategies\\' . ucfirst($deprecatedGatewayHelper->paymentMethod()->getProperty('id')) . 'InstructionStrategy';
            $this->strategy = class_exists($className) ? new $className() : new \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DefaultInstructionStrategy();
        }
    }
    public function executeStrategy($paymentGateway, $payment, $order = null, $admin_instructions = \false)
    {
        return $this->strategy->execute($paymentGateway, $payment, $order, $admin_instructions);
    }
    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool     $admin_instructions (default: false)
     * @param bool     $plain_text         (default: false)
     *
     * @return void
     */
    public function displayInstructions($paymentGateway, $deprecatedGatewayHelper, WC_Order $order, $admin_instructions = \false, $plain_text = \false)
    {
        if ($admin_instructions && !self::$alreadyDisplayedAdminInstructions || !$admin_instructions && !self::$alreadyDisplayedCustomerInstructions) {
            $order_payment_method = $order->get_payment_method();
            // Invalid gateway
            if ($paymentGateway->id !== $order_payment_method) {
                return;
            }
            $payment = $deprecatedGatewayHelper->paymentObject()->getActiveMolliePayment($order->get_id());
            $methodId = str_replace('mollie_wc_gateway_', '', $paymentGateway->id);
            // Mollie payment not found or invalid gateway
            if (!$payment || $payment->method !== $methodId) {
                return;
            }
            $this->setStrategy($deprecatedGatewayHelper);
            $instructions = $this->executeStrategy($paymentGateway, $payment, $order, $admin_instructions);
            if (!empty($instructions)) {
                $instructions = wptexturize($instructions);
                //save instructions in order meta
                $order->update_meta_data('_mollie_payment_instructions', $instructions);
                $order->save();
                if ($plain_text) {
                    echo esc_html($instructions) . \PHP_EOL;
                } else {
                    echo '<section class="woocommerce-order-details woocommerce-info mollie-instructions" >';
                    echo wp_kses(wpautop($instructions), ['p' => [], 'strong' => [], 'br' => []]) . \PHP_EOL;
                    echo '</section>';
                }
            }
        }
        if ($admin_instructions && !self::$alreadyDisplayedAdminInstructions) {
            self::$alreadyDisplayedAdminInstructions = \true;
        }
        if (!$admin_instructions && !self::$alreadyDisplayedCustomerInstructions) {
            self::$alreadyDisplayedCustomerInstructions = \true;
        }
    }
}
