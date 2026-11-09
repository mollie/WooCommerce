<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Activation;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Activation\Migrations\MigratorInterface;
use Mollie\WooCommerce\Activation\Migrations\PaymentMethodSettingsMigrator;
use Mollie\WooCommerce\Activation\Migrations\VoucherTermMetaTranslationMigrator;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\LoggerInterface;
use Throwable;
class ActivationModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;
    private $baseFile;
    private $pluginVersion;
    /** @var MigratorInterface[] */
    private array $migrators = [];
    /** @var LoggerInterface */
    private $logger;
    public function services(): array
    {
        return ['activation.migrators' => static function (): array {
            return [new PaymentMethodSettingsMigrator(), new VoucherTermMetaTranslationMigrator()];
        }];
    }
    /**
     * @param ContainerInterface $container
     *
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        $this->pluginVersion = $container->get('shared.plugin_version');
        $this->migrators = $container->get('activation.migrators');
        $this->logger = $container->get(LoggerInterface::class);
        $this->baseFile = \M4W_FILE;
        add_action('init', [$this, 'pluginInit']);
        add_action('admin_init', [$this, 'mollieWcNoticeApiKeyMissing']);
        $this->declareCompatibleWithHPOS();
        $this->appleValidationFileRewriteRules();
        return \true;
    }
    /**
     *
     */
    public function initDb(): void
    {
        global $wpdb;
        $wpdb->mollie_pending_payment = $wpdb->prefix . SharedDataDictionary::PENDING_PAYMENT_DB_TABLE_NAME;
        if (get_option(SharedDataDictionary::DB_VERSION_PARAM_NAME, '') === SharedDataDictionary::DB_VERSION) {
            return;
        }
        $table = $wpdb->prefix . SharedDataDictionary::PENDING_PAYMENT_DB_TABLE_NAME;
        require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            $sql = "CREATE TABLE {$table} (\n                id int(11) NOT NULL AUTO_INCREMENT,\n                post_id bigint NOT NULL,\n                expired_time int NOT NULL,\n                PRIMARY KEY id (id)\n            );";
            // dbDelta() runs DESCRIBE on a non-existent table as part of its schema diff;
            // suppress that expected false-positive instead of manipulating $EZSQL_ERROR directly.
            $previousSuppressErrors = $wpdb->suppress_errors(\true);
            dbDelta($sql);
            $wpdb->suppress_errors($previousSuppressErrors);
        }
        update_option(SharedDataDictionary::DB_VERSION_PARAM_NAME, SharedDataDictionary::DB_VERSION);
    }
    /**
     *
     */
    public function appleValidationFileRewriteRules(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        $requestUri = (string) filter_var(wp_unslash($_SERVER['REQUEST_URI']), \FILTER_SANITIZE_URL);
        if (strpos($requestUri, '.well-known/apple-developer-merchantid-domain-association') !== \false) {
            $validationString = '7b2276657273696f6e223a312c227073704964223a2244394337463730314338433646324336463344363536433039393434453332323030423137364631353245353844393134304331433533414138323436453630222c22637265617465644f6e223a313738383835323234363639397d';
            nocache_headers();
            header('Content-Type: text/plain', \true, 200);
            echo $validationString;
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }
    }
    /**
     *
     */
    public function mollieWcNoticeApiKeyMissing()
    {
        //if test/live keys are in db return
        $liveKeySet = get_option('mollie-payments-for-woocommerce_live_api_key');
        $testKeySet = get_option('mollie-payments-for-woocommerce_test_api_key');
        $apiKeysSet = $liveKeySet || $testKeySet;
        if ($apiKeysSet) {
            return;
        }
        $notice = new AdminNotice();
        $message = sprintf(
            /* translators: Placeholder 1: Opening strong tag. Placeholder 2: Closing strong tag. Placeholder 3: Opening link tag to settings. Placeholder 4: Closing link tag.*/
            esc_html__('%1$sMollie Payments for WooCommerce: API keys missing%2$s Please%3$s set your API keys here%4$s.', 'mollie-payments-for-woocommerce'),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=mollie_settings')) . '">',
            '</a>'
        );
        $notice->addNotice('notice-warning is-dismissible', $message);
    }
    protected function markUpdatedOrNew()
    {
        $dbVersionOption = get_option(SharedDataDictionary::DB_VERSION_PARAM_NAME, '');
        $dbPluginOption = get_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '');
        if ($dbPluginOption === $this->pluginVersion) {
            return;
        }
        if (!$dbVersionOption && !$dbPluginOption) {
            update_option(SharedDataDictionary::NEW_INSTALL_PARAM_NAME, 'yes', \true);
            update_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, $this->pluginVersion, \true);
            return;
        }
        update_option(SharedDataDictionary::NEW_INSTALL_PARAM_NAME, 'no', \true);
        update_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, $this->pluginVersion, \true);
    }
    /**
     *
     */
    public function pluginInit()
    {
        $migrationsOk = $this->runMigrations();
        if ($migrationsOk) {
            $this->markUpdatedOrNew();
        }
        $this->initDb();
    }
    protected function runMigrations(): bool
    {
        // Fresh install: no stored version means nothing to migrate from. Let
        // markUpdatedOrNew() seed the cursor at the current plugin version.
        $storedVersion = (string) get_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '');
        if ($storedVersion === '') {
            return \true;
        }
        // Keep only migrators whose target falls in the open-closed window
        // (V_old, V_new]: strictly newer than what's on disk, no newer than
        // the version we're upgrading to.
        $applicable = array_filter($this->migrators, function (MigratorInterface $m) use ($storedVersion): bool {
            return version_compare($m->targetVersion(), $storedVersion, '>') && version_compare($m->targetVersion(), $this->pluginVersion, '<=');
        });
        // Run the ladder bottom-up so each migrator sees the state produced
        // by every earlier one. Same-target ordering is undefined by contract.
        usort($applicable, static function (MigratorInterface $a, MigratorInterface $b): int {
            return version_compare($a->targetVersion(), $b->targetVersion());
        });
        foreach ($applicable as $migrator) {
            try {
                $migrator->migrate();
                // Advance the cursor immediately after each success so a
                // later failure resumes from the last completed step instead
                // of replaying the whole ladder.
                update_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, $migrator->targetVersion(), \true);
            } catch (Throwable $e) {
                // Halt the ladder on the first failure: subsequent migrators
                // may assume the failed one ran. The caller skips the final
                // version bump so this run is retried on the next request.
                $this->logger->error(sprintf('Migration to %s failed: %s', $migrator->targetVersion(), $e->getMessage()), ['exception' => $e]);
                return \false;
            }
        }
        return \true;
    }
    /**
     * @return void
     */
    protected function declareCompatibleWithHPOS(): void
    {
        $baseFile = $this->baseFile;
        add_action('before_woocommerce_init', static function () use ($baseFile) {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                FeaturesUtil::declare_compatibility('custom_order_tables', $baseFile, \true);
            }
        });
    }
}
