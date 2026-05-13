<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Migration;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Properties\PluginProperties;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Container\ContainerInterface;

class MigrationModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container): bool
    {
        $callback = static function () use ($container): void {
            /** @var PluginProperties $properties */
            $properties = $container->get(Package::PROPERTIES);
            $pluginVersion = $properties->version();

            if (!version_compare($pluginVersion, '0.0.1', '>=')) {
                return;
            }

            $dbPluginVersion = (string) get_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '');

            if (empty($dbPluginVersion)) {
                return;
            }

            if (version_compare($pluginVersion, $dbPluginVersion, '>')) {
                /** @var MigratorInterface $migrator */
                $migrator = $container->get('migration.payment_method_settings_migrator');
                $migrator->migrate();
            }
        };

        add_action('init', $callback, 9);

        return true;
    }

    public function services(): array
    {
        return [
            'migration.payment_method_settings_migrator' => static function (): MigratorInterface {
                return new PaymentMethodSettingsMigrator();
            },
        ];
    }
}
