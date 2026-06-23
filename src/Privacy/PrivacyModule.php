<?php

# kb-active

declare(strict_types=1);

namespace Mollie\WooCommerce\Privacy;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class PrivacyModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container): bool
    {
        add_action('admin_init', [$this, 'registerPrivacyPolicyContent']);

        return true;
    }

    public function registerPrivacyPolicyContent(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        $content = '<div class="wp-suggested-text"><p>' . sprintf(
            __('By using this extension, you may be processing personal data with Mollie. Please see <a href="%s">here</a> for more information.', 'mollie-payments-for-woocommerce'),
            'https://www.mollie.com/legal/privacy'
        ) . '</p></div>';

        wp_add_privacy_policy_content('Mollie Payments for WooCommerce', $content);
    }
}
