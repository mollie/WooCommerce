<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent;

use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\OrphanedExpressPayments;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressAssets;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressReturnHandler;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes;
use Mollie\WooCommerce\ExpressComponent\Entry\ExpressUrls;
use Mollie\WooCommerce\ExpressComponent\Rules\SurfaceOwnership;
use Mollie\WooCommerce\ExpressComponent\Flow\ExpireAbandonedExpressOrders;
use Mollie\WooCommerce\ExpressComponent\Rules\TaxCompatibility;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\Psr\Container\ContainerInterface;
class ExpressComponentModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        static $services;
        if ($services === null) {
            $services = require_once __DIR__ . '/inc/services.php';
        }
        return $services();
    }
    public function run(ContainerInterface $container): bool
    {
        // Decided once per request and surface, from the cart as the page is rendered.
        $owned = [];
        add_filter('mollie_wc_express_owns_surface', static function ($owns, $surface) use ($container, &$owned): bool {
            $surface = (string) $surface;
            $owned[$surface] ??= self::owns($container, $surface);
            return $owned[$surface];
        }, 10, 2);
        add_action('rest_api_init', static function () use ($container): void {
            $routes = $container->get(ExpressRoutes::class);
            assert($routes instanceof ExpressRoutes);
            $routes->register();
        });
        add_action('wp_enqueue_scripts', static function () use ($container, &$owned): void {
            $assets = $container->get(ExpressAssets::class);
            assert($assets instanceof ExpressAssets);
            $assets->enqueue(static function (string $surface) use ($container, &$owned): bool {
                $owned[$surface] ??= self::owns($container, $surface);
                return $owned[$surface];
            });
        });
        add_action('woocommerce_api_' . ExpressUrls::RETURN_API, static function () use ($container): void {
            $handler = $container->get(ExpressReturnHandler::class);
            assert($handler instanceof ExpressReturnHandler);
            $handler->handle();
        });
        add_action('admin_notices', static function () use ($container): void {
            $orphaned = $container->get(OrphanedExpressPayments::class);
            assert($orphaned instanceof OrphanedExpressPayments);
            $orphaned->renderNotice();
            self::renderTaxNotice($container);
        });
        add_action('shutdown', static function () use ($container): void {
            $log = $container->get(EventLog::class);
            assert($log instanceof EventLog);
            // A fatal error skips the flows' finally.
            $error = error_get_last();
            $fatal = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR;
            $log->flush(is_array($error) && ($error['type'] & $fatal) !== 0);
        });
        // PaymentModule schedules this action.
        add_action('mollie_woocommerce_cancel_unpaid_orders', static function () use ($container): void {
            $cleanup = $container->get(ExpireAbandonedExpressOrders::class);
            assert($cleanup instanceof ExpireAbandonedExpressOrders);
            $cleanup->run();
        }, 12, 0);
        return \true;
    }
    /**
     * One notice while a wallet's express switch is on: an error when the component is hidden because
     * the price would depend on the wallet's address, a warning when only the rates differ by region.
     */
    private static function renderTaxNotice(ContainerInterface $container): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        // The verdict reads the tax-rate table: not on every admin page.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen instanceof \WP_Screen || !function_exists('wc_get_screen_ids') || !in_array($screen->id, wc_get_screen_ids(), \true)) {
            return;
        }
        $facts = $container->get(ExpressFactsBuilder::class);
        assert($facts instanceof ExpressFactsBuilder);
        if (!$facts->anyWalletTurnedOn()) {
            return;
        }
        $carts = $container->get(CartFactsBuilder::class);
        assert($carts instanceof CartFactsBuilder);
        $verdict = $carts->taxVerdict();
        if ($verdict === TaxCompatibility::COMPATIBLE) {
            return;
        }
        $incompatible = $verdict === TaxCompatibility::INCOMPATIBLE;
        $level = $incompatible ? 'notice-error' : 'notice-warning';
        $notice = (new AdminNotice())->renderNotice($level, self::taxNoticeMessage($incompatible));
        echo $notice;
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderNotice()
    }
    private static function taxNoticeMessage(bool $incompatible): string
    {
        if ($incompatible) {
            $title = esc_html__('Mollie express checkout is hidden: the price depends on the customer\'s address', 'mollie-payments-for-woocommerce');
            $body = esc_html__('Your taxes follow the customer\'s address and that address can be in more than one country (the countries you sell to, or ship to when taxes follow the shipping address). The wallet gives its address after the price is set, and Mollie cannot change the amount of a session. To offer express checkout, set "Calculate tax based on" to your shop base address, or limit selling and shipping to one country.', 'mollie-payments-for-woocommerce');
        } else {
            $title = esc_html__('Mollie express checkout: your tax rates differ by state or postcode', 'mollie-payments-for-woocommerce');
            $body = esc_html__('Express checkout is still offered. The tax charged follows the address the wallet gives, so it can differ from the price shown. Check "Calculate tax based on" if this is a problem.', 'mollie-payments-for-woocommerce');
        }
        return sprintf('<p><strong>%s</strong></p><p>%s</p><p><a href="%s">%s</a></p>', $title, $body, esc_url(admin_url('admin.php?page=wc-settings&tab=tax')), esc_html__('Open the tax settings', 'mollie-payments-for-woocommerce'));
    }
    /**
     * A cart WooCommerce has not loaded is not owned; of the two carts, only the block cart is a surface.
     */
    private static function owns(ContainerInterface $container, string $surface): bool
    {
        if ($surface === 'cart') {
            $assets = $container->get(ExpressAssets::class);
            assert($assets instanceof ExpressAssets);
            if (!$assets->isBlockCart()) {
                return \false;
            }
        }
        $facts = $container->get(ExpressFactsBuilder::class);
        assert($facts instanceof ExpressFactsBuilder);
        $carts = $container->get(CartFactsBuilder::class);
        assert($carts instanceof CartFactsBuilder);
        $cart = $carts->fromCart();
        if ($cart === null) {
            return \false;
        }
        return SurfaceOwnership::owns($facts->settings(), $facts->shopFacts(), $cart, $surface);
    }
}
