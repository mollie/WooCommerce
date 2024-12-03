<?php

declare(strict_types=1);

use Inpsyde\Modularity\Module\Module;
use Inpsyde\PaymentGateway\PaymentGatewayModule;
use Mollie\WooCommerce\Activation\ActivationModule;
use Mollie\WooCommerce\Assets\AssetsModule;
use Mollie\WooCommerce\Gateway\GatewayModule;
use Mollie\WooCommerce\Gateway\Voucher\VoucherModule;
use Mollie\WooCommerce\Log\LogModule;
use Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule;
use Mollie\WooCommerce\Notice\NoticeModule;
use Mollie\WooCommerce\Payment\PaymentModule;
use Mollie\WooCommerce\SDK\SDKModule;
use Mollie\WooCommerce\Settings\SettingsModule;
use Mollie\WooCommerce\Shared\SharedModule;
use Mollie\WooCommerce\Uninstall\UninstallModule;

return /**
 * @return iterable<Module>
 */
    static function (): iterable {
        return [
            new ActivationModule(),
            new NoticeModule(),
            new SharedModule(),
            new PaymentGatewayModule(),
            new SDKModule(),
            new SettingsModule(),
            new LogModule('mollie-payments-for-woocommerce-'),
            new AssetsModule(),
            new GatewayModule(),
            new VoucherModule(),
            new PaymentModule(),
            new MerchantCaptureModule(),
            new UninstallModule(),
        ];
    };
