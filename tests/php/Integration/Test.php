<?php

namespace Mollie\WooCommerceTests\Integration;

use Inpsyde\Modularity\Properties\PluginProperties;
use Mollie\WooCommerce\Activation\ActivationModule;
use Mollie\WooCommerce\Shared\SharedModule;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class Test extends TestCase
{

    public function testReturnsPluginVersionService()
    {
        when('get_plugin_data')->justReturn( [
                                                 'Name'                 => 'WooCommerce/mollie-payments-for-woocommerce.php',
                                                 'Title'                => 'Mollie Payments for WooCommerce',
                                                 'Description'          => 'Accept payments in WooCommerce with the official Mollie plugin',
                                                 'TextDomain'           => 'mollie-payments-for-woocommerce',
                                                 'Version'              => '7.3.4',
                                                 'RequiresWP'           => '5.3',
                                                 'RequiresPhp'          => '7.2',
                                                 'WC requires at least' => '5.0',
                                             ]);
        when('plugin_basename')
            ->justReturn('WooCommerce/mollie-payments-for-woocommerce.php');
        when('plugin_dir_path')
            ->justReturn(M4W_PLUGIN_DIR);

        when('is_multisite')
            ->justReturn(false);

        when('esc_attr')->returnArg(1);

        when('esc_url_raw')->returnArg(1);
        when('trailingslashit')->returnArg(1);
        when('do_action')->returnArg(1);
        expect('plugins_url')
            ->with('', M4W_PLUGIN_DIR)
            ->andReturn(M4W_PLUGIN_URL);
        $modules = [
            new ActivationModule(),
            new SharedModule(),
        ];
        $properties = PluginProperties::new(M4W_PLUGIN_DIR . '/mollie-payments-for-woocommerce.php');

        $version = $this->createPackage($properties, $modules)->container()->get('shared.plugin_version');

        $this->assertEquals('7.3.4', $version);
    }
}
