<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Settings;

use Mockery;
use Mollie\WooCommerce\Settings\MollieSettingsPage;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;

/**
 * What changes when the merchant saves another API key: the plugin asks Mollie, with the new key,
 * who the merchant is and which methods they have.
 *
 * The e2e suite covers the screen in tests/qa/tests/02-merchant-setup (C3510, C3511); the message
 * it shows for each answer of Mollie is ConnectionStatusRenderingTest's.
 * tests/Integration/e2e-equivalence.md has the list.
 *
 * @group integration
 * @group ApiKeyChange
 */
class ApiKeyChangeTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const NEW_LIVE_KEY = 'live_ApiKeyChangeTestKeyXXXXXXXXXXXXXXXX';
    private const NEW_TEST_KEY = 'test_ApiKeyChangeTestKeyXXXXXXXXXXXXXXXX';

    public function tearDown(): void
    {
        unset($_POST[self::PLUGIN_ID . '_live_api_key'], $_POST[self::PLUGIN_ID . '_test_api_key']);
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: saving another key asks Mollie who the merchant is, and keeps the answer
     *   Given a connected shop
     *   When the key of the mode in use is replaced by another valid one
     *   Then Mollie is asked for the profile and for the methods
     *   And the profile id Mollie answers is stored
     *
     * @test
     * @covers \Mollie\WooCommerce\Settings\Settings::updateMerchantIdAfterApiKeyChanges
     */
    public function it_asks_mollie_for_the_profile_and_the_methods_when_the_key_changes(): void
    {
        $this->connectedShop();
        $this->setOptionForTest(self::PLUGIN_ID . '_profile_merchant_id', 'pfl_of_the_old_key');
        $requestsBefore = count($this->fakeMollie()->requests());

        $this->setOptionForTest(self::PLUGIN_ID . '_live_api_key', self::NEW_LIVE_KEY);

        $asked = array_slice($this->fakeMollie()->requests(), $requestsBefore);
        $paths = array_column($asked, 'path');
        $this->assertContains('profiles/me', $paths);
        $this->assertContains('methods/all', $paths);
        $this->assertSame(['live'], array_values(array_unique(array_column($asked, 'authorizedAs'))));
        $this->assertSame('pfl_fake', get_option(self::PLUGIN_ID . '_profile_merchant_id'));
    }

    /**
     * Scenario: a key Mollie refuses leaves no merchant profile behind
     *   Given a connected shop with a stored profile id
     *   When the key is replaced and Mollie refuses the new one
     *   Then the stored profile id is emptied
     *
     * @test
     * @covers \Mollie\WooCommerce\Settings\Settings::updateMerchantIdOnApiKeyChanges
     */
    public function it_forgets_the_profile_when_mollie_refuses_the_new_key(): void
    {
        $this->connectedShop();
        $this->setOptionForTest(self::PLUGIN_ID . '_profile_merchant_id', 'pfl_of_the_old_key');
        $this->fakeMollie()->failNext('GET', 'profiles/me', 401, 'Missing authentication, or failed to authenticate.');

        $this->setOptionForTest(self::PLUGIN_ID . '_live_api_key', self::NEW_LIVE_KEY);

        $this->assertSame('', get_option(self::PLUGIN_ID . '_profile_merchant_id'));
    }

    /**
     * Scenario: the methods are refreshed for the mode in use, whichever key changed
     *   Given a shop in live mode
     *   When the test key is replaced
     *   Then the methods are asked for again, with the key of the mode in use
     *
     * @test
     * @covers \Mollie\WooCommerce\Settings\SettingsModule::run
     */
    public function it_refreshes_the_methods_of_the_mode_in_use_whichever_key_changed(): void
    {
        $this->connectedShop();
        $requestsBefore = count($this->fakeMollie()->requests());

        $this->setOptionForTest(self::PLUGIN_ID . '_test_api_key', self::NEW_TEST_KEY);

        $methods = array_values(array_filter(
            array_slice($this->fakeMollie()->requests(), $requestsBefore),
            static function (array $request): bool {
                return $request['path'] === 'methods/all';
            }
        ));
        $this->assertNotSame([], $methods);
        $this->assertSame(['live'], array_values(array_unique(array_column($methods, 'authorizedAs'))));
    }

    /**
     * Scenario: saving the settings form without touching the masked key keeps the stored key
     *   Given a stored key, shown on the settings screen as stars
     *   When the form comes back with the stars
     *   Then the value that will be saved is the stored key, not the stars
     *
     * @test
     * @dataProvider keyFields
     * @covers \Mollie\WooCommerce\Settings\MollieSettingsPage::processApiKeys
     */
    public function it_keeps_the_stored_key_when_the_form_comes_back_with_the_mask(string $mode, string $pattern): void
    {
        $container = $this->connectedShop();
        $keyName = self::PLUGIN_ID . "_{$mode}_api_key";
        $stored = (string) get_option($keyName);
        $_POST[$keyName] = '**********';

        $this->settingsPage($container)->processApiKeys($keyName, [
            'keyName' => $keyName,
            'pattern' => $pattern,
            'valueInDb' => $stored,
            'postedValue' => '**********',
        ]);

        $this->assertNotSame('', $stored);
        $this->assertSame($stored, $_POST[$keyName]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function keyFields(): array
    {
        return [
            'live key' => ['live', '/^live_\w{30,}$/'],
            'test key' => ['test', '/^test_\w{30,}$/'],
        ];
    }

    /**
     * One boot, heard alone: the listeners of earlier boots would ask their own Mollie client.
     */
    private function connectedShop(): \Psr\Container\ContainerInterface
    {
        $this->removePluginListenersOfEarlierBoots();
        $this->container = $this->bootExpress();

        return $this->container;
    }

    private function settingsPage(\Psr\Container\ContainerInterface $container): MollieSettingsPage
    {
        if (!class_exists(\WC_Admin_Settings::class)) {
            require_once WC()->plugin_path() . '/includes/admin/class-wc-admin-settings.php';
        }
        if (!class_exists(\WC_Settings_Page::class)) {
            require_once WC()->plugin_path() . '/includes/admin/settings/class-wc-settings-page.php';
        }

        return new MollieSettingsPage(
            $container->get('settings.settings_helper'),
            $container->get('shared.plugin_path'),
            $container->get('shared.plugin_url'),
            (bool) $container->get('settings.IsTestModeEnabled'),
            $container->get('settings.data_helper'),
            $container
        );
    }
}
