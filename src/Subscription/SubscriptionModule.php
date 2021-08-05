<?php

/**
 * This file is part of the  Mollie\WooCommerce.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * PHP version 7
 *
 * @category Activation
 * @package  Mollie\WooCommerce
 * @author   AuthorName <hello@inpsyde.com>
 * @license  GPLv2+
 * @link     https://www.inpsyde.com
 */

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings;

use DateTime;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\Settings\Page\MollieSettingsPage;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Utils\MaybeFixSubscription;
use Psr\Container\ContainerInterface;

class SubscriptionModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container): bool
    {
        $this->maybeFixSubscriptions();
        $this->schedulePendingPaymentOrdersExpirationCheck();
        return true;
    }

    /**
     * See MOL-322, MOL-405
     */
    public function maybeFixSubscriptions()
    {
        $fixer = new MaybeFixSubscription();
        $fixer->maybeFix();
    }

    /**
     * WCSubscription related.
     */
    public function schedulePendingPaymentOrdersExpirationCheck()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            $settings_helper = Plugin::getSettingsHelper();
            $time = $settings_helper->getPaymentConfirmationCheckTime();
            $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
            if (!$nextScheduledTime) {
                wp_schedule_event($time, 'daily', 'pending_payment_confirmation_check');
            }

            add_action('pending_payment_confirmation_check', [__CLASS__, 'checkPendingPaymentOrdersExpiration']);
        }
    }

    /**
     *
     */
    public static function checkPendingPaymentOrdersExpiration()
    {
        global $wpdb;
        $currentDate = new DateTime();
        $items = $wpdb->get_results("SELECT * FROM {$wpdb->mollie_pending_payment} WHERE expired_time < {$currentDate->getTimestamp()};");
        foreach ($items as $item) {
            $order = wc_get_order($item->post_id);

            // Check that order actually exists
            if ($order == false) {
                return false;
            }

            if ($order->get_status() == AbstractGateway::STATUS_COMPLETED) {
                $new_order_status = AbstractGateway::STATUS_FAILED;
                $paymentMethodId = $order->get_meta('_payment_method_title', true);
                $molliePaymentId = $order->get_meta('_mollie_payment_id', true);
                $order->add_order_note(sprintf(
                                       /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __('%1$s payment failed (%2$s).', 'mollie-payments-for-woocommerce'),
                    $paymentMethodId,
                    $molliePaymentId
                ));

                $order->update_status($new_order_status, '');
                if ($order->get_meta('_order_stock_reduced', $single = true)) {
                    // Restore order stock
                    Plugin::getDataHelper()->restoreOrderStock($order);

                    Plugin::debug(__METHOD__ . " Stock for order {$order->get_id()} restored.");
                }

                $wpdb->delete(
                    $wpdb->mollie_pending_payment,
                    [
                        'post_id' => $order->get_id(),
                    ]
                );
            }
        }
    }
}
