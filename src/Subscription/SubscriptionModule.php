<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Subscription;

use DateTime;
use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\LoggerInterface as Logger;
use Mollie\Psr\Log\LogLevel;
class SubscriptionModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * @var mixed
     */
    protected $logger;
    /**
     * @var mixed
     */
    protected $dataHelper;
    /**
     * @var mixed
     */
    protected $settingsHelper;
    public function run(ContainerInterface $container): bool
    {
        $this->logger = $container->get(Logger::class);
        assert($this->logger instanceof Logger);
        $this->dataHelper = $container->get('settings.data_helper');
        assert($this->dataHelper instanceof Data);
        $this->settingsHelper = $container->get('settings.settings_helper');
        assert($this->settingsHelper instanceof Settings);
        $this->schedulePendingPaymentOrdersExpirationCheck();
        return \true;
    }
    /**
     * WCSubscription related.
     */
    public function schedulePendingPaymentOrdersExpirationCheck()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            $settings_helper = $this->settingsHelper;
            $time = $settings_helper->getPaymentConfirmationCheckTime();
            $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
            if (!$nextScheduledTime) {
                wp_schedule_event($time, 'daily', 'pending_payment_confirmation_check');
            }
            add_action('pending_payment_confirmation_check', [$this, 'checkPendingPaymentOrdersExpiration']);
        }
    }
    /**
     *
     */
    public function checkPendingPaymentOrdersExpiration()
    {
        global $wpdb;
        $currentDate = new DateTime();
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->mollie_pending_payment} WHERE expired_time < %s", $currentDate->getTimestamp()));
        foreach ($items as $item) {
            $order = wc_get_order($item->post_id);
            // Check that order actually exists
            if ($order === \false) {
                return \false;
            }
            if ($order->get_status() === SharedDataDictionary::STATUS_COMPLETED) {
                $new_order_status = SharedDataDictionary::STATUS_FAILED;
                $paymentMethodId = $order->get_meta('_payment_method_title', \true);
                $molliePaymentId = $order->get_meta('_mollie_payment_id', \true);
                $order->add_order_note(sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __('%1$s payment failed (%2$s).', 'mollie-payments-for-woocommerce'),
                    $paymentMethodId,
                    $molliePaymentId
                ));
                $order->update_status($new_order_status, '');
                if ($order->get_meta('_order_stock_reduced', $single = \true)) {
                    // Restore order stock
                    $this->dataHelper->restoreOrderStock($order);
                    $this->logger->debug(__METHOD__ . " Stock for order {$order->get_id()} restored.");
                }
                $wpdb->delete($wpdb->mollie_pending_payment, ['post_id' => $order->get_id()]);
            }
        }
    }
}
