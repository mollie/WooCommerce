<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Gateway\Voucher;

use Mollie\WooCommerce\Activation\Migrations\VoucherTermMetaTranslationMigrator;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;

/**
 * Integration coverage for the WPML voucher-category "translation twin".
 *
 * THE BUG
 * The Mollie Voucher method can take its category from a product's category
 * term, stored as the term meta `_mollie_voucher_category`. Under WPML/WCML that
 * value never reached translated category terms, so Voucher was missing at the
 * secondary-language checkout for category-configured products.
 *
 * ROOT CAUSE (why wpml-config.xml alone cannot fix the term path)
 * WPML's term-meta sync (WPML_Sync_Term_Meta_Action) only iterates keys returned
 * by WPML_Custom_Field_Setting_Factory::get_term_meta_keys(), which runs
 * filter_custom_field_key() and EXCLUDES every key starting with "_" unless
 * $show_system_fields is true. That flag defaults to false and is only ever set
 * from $_GET['show_system_fields'] while rendering the settings table, never
 * during sync. So `_mollie_voucher_category` (underscore-prefixed) is never
 * copied to translated terms even when registered action="copy" — and the
 * "show/hide system fields" settings toggle is display-only. Post and variation
 * meta are NOT affected: their copiers (WPML_Sync_Custom_Fields, WCML
 * VariationMeta) read the settings list directly and do copy "_"-prefixed keys.
 *
 * THE FIX
 * Mirror the value into a non-underscore twin `mollie_voucher_translation_category`
 * that WPML does copy; register the twin under <custom-term-fields action="copy">
 * in wpml-config.xml; read it as a fallback in Voucher::getCategoriesForProduct().
 * VoucherModule::voucherTaxonomyCustomMetaSave() writes the twin on every category
 * save (WPML copies it on the same save/translation event), and
 * VoucherTermMetaTranslationMigrator backfills the twin onto categories that were
 * already translated before the twin existed. The legacy `_mollie_voucher_category`
 * is still written and read first, so non-WPML and source-language behaviour is
 * unchanged.
 *
 * RUNNING
 * These need a real WordPress + WooCommerce (+ WPML/WCML) install, so they are
 * kept out of CI two ways: the root phpunit config excludes @group skip, and the
 * dedicated integration suite is not run in CI at all. Run locally with WPML and
 * WooCommerce Multilingual active and at least two languages:
 *
 *   ./vendor/bin/phpunit -c tests/Integration/phpunit.xml.dist \
 *       --filter VoucherTermMetaTranslationTest
 *
 * WPML-dependent cases self-skip when WPML is inactive or misconfigured.
 *
 * @group skip
 * @group wpml
 */
class VoucherTermMetaTranslationTest extends IntegrationMockedTestCase
{
    private const TWIN = Voucher::MOLLIE_VOUCHER_CATEGORY_TRANSLATION_OPTION;
    private const LEGACY = Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION;

    /** @var int[] */
    private array $createdTermIds = [];

    public function tearDown(): void
    {
        foreach ($this->createdTermIds as $id) {
            wp_delete_term($id, 'product_cat');
        }
        $this->createdTermIds = [];
        parent::tearDown();
    }

    /**
     * Given a translated category term that carries only the twin
     *   (WPML never copies the underscore-prefixed legacy key to translations)
     * When getCategoriesForProduct() runs for a product in that category
     * Then it resolves the voucher category from the twin.
     */
    public function test_read_falls_back_to_twin_when_legacy_absent(): void
    {
        $termId = $this->createCategory('Twin Only');
        update_term_meta($termId, self::TWIN, Voucher::ECO);

        $product = $this->createSimpleProductInCategory($termId);

        self::assertSame([Voucher::ECO], array_values(Voucher::getCategoriesForProduct($product)));
    }

    /**
     * Given a source-language term that has both the legacy key and the twin
     * When getCategoriesForProduct() runs for a product in that category
     * Then the legacy key wins, so existing and non-WPML installs are unaffected.
     */
    public function test_legacy_takes_precedence_over_twin(): void
    {
        $termId = $this->createCategory('Both Keys');
        update_term_meta($termId, self::LEGACY, Voucher::MEAL);
        update_term_meta($termId, self::TWIN, Voucher::ECO);

        $product = $this->createSimpleProductInCategory($termId);

        self::assertSame([Voucher::MEAL], array_values(Voucher::getCategoriesForProduct($product)));
    }

    /**
     * Given a category term that has only the legacy key (a pre-twin install)
     * When the migrator runs
     * Then the twin is seeded from the legacy value and the legacy key is left
     *   untouched (additive, no regression).
     */
    public function test_migrator_seeds_twin_and_leaves_legacy_untouched(): void
    {
        $termId = $this->createCategory('Legacy Only');
        update_term_meta($termId, self::LEGACY, Voucher::GIFT);

        (new VoucherTermMetaTranslationMigrator())->migrate();
        clean_term_cache($termId, 'product_cat');

        self::assertSame(Voucher::GIFT, get_term_meta($termId, self::TWIN, true), 'twin seeded from legacy');
        self::assertSame(Voucher::GIFT, get_term_meta($termId, self::LEGACY, true), 'legacy key untouched');
    }

    /**
     * Given a source term with the twin set and a linked translation, with the
     *   twin registered as a copy term field
     * When WPML's term-meta sync runs with its default (non-forced) settings
     * Then the twin is copied to the translated term and the underscore-prefixed
     *   legacy key does not leak onto the translation.
     */
    public function test_wpml_sync_copies_twin_to_translated_term(): void
    {
        $this->skipWithoutWpml();
        $this->requireTwinRegisteredAsCopy();
        [$sourceTermId, $sourceTtId, $translatedTermId] = $this->createTranslatedCategoryPair();

        update_term_meta($sourceTermId, self::TWIN, Voucher::SPORT_CULTURE);

        global $sitepress;
        (new \WPML_Sync_Term_Meta_Action($sitepress, $sourceTtId, false))->run();
        clean_term_cache($translatedTermId, 'product_cat');

        self::assertSame(
            Voucher::SPORT_CULTURE,
            get_term_meta($translatedTermId, self::TWIN, true),
            'twin copied to the translated term'
        );
        self::assertSame(
            '',
            (string) get_term_meta($translatedTermId, self::LEGACY, true),
            'legacy underscore key must not appear on the translation'
        );
    }

    /**
     * Given a source term with the legacy key set and a pre-existing translation
     *   that has neither key (a pre-upgrade multilingual site)
     * When the migrator runs
     * Then the twin is backfilled directly onto the translation, so no manual
     *   re-save is needed.
     */
    public function test_migrator_backfills_existing_translation(): void
    {
        $this->skipWithoutWpml();
        [$sourceTermId, , $translatedTermId] = $this->createTranslatedCategoryPair();

        // Pre-upgrade state: legacy key on the source only.
        update_term_meta($sourceTermId, self::LEGACY, Voucher::MEAL);

        (new VoucherTermMetaTranslationMigrator())->migrate();
        clean_term_cache($translatedTermId, 'product_cat');

        self::assertSame(
            Voucher::MEAL,
            get_term_meta($translatedTermId, self::TWIN, true),
            'twin backfilled onto the existing translation'
        );
    }

    // --- helpers -----------------------------------------------------------

    private function createCategory(string $name): int
    {
        $term = wp_insert_term($name . ' ' . uniqid('', true), 'product_cat');
        self::assertIsArray($term, 'failed to create product category');
        $this->createdTermIds[] = (int) $term['term_id'];
        return (int) $term['term_id'];
    }

    private function createSimpleProductInCategory(int $termId): \WC_Product
    {
        // Not saved on purpose: getCategoriesForProduct() reads get_category_ids()
        // from the in-memory product, so there is no persisted product to delete
        // (deleting products triggers a WCML attribute-lookup fatal in teardown).
        $product = new \WC_Product_Simple();
        $product->set_name('Voucher Cat Product ' . uniqid('', true));
        $product->set_category_ids([$termId]);
        return $product;
    }

    private function skipWithoutWpml(): void
    {
        if (!defined('ICL_SITEPRESS_VERSION') || !class_exists('WPML_Sync_Term_Meta_Action')) {
            self::markTestSkipped('WPML is not active.');
        }
        $languages = apply_filters('wpml_active_languages', null);
        if (!is_array($languages) || count($languages) < 2) {
            self::markTestSkipped('WPML needs at least two active languages.');
        }
    }

    private function requireTwinRegisteredAsCopy(): void
    {
        global $iclTranslationManagement;
        if (!$iclTranslationManagement) {
            self::markTestSkipped('WPML TranslationManagement unavailable.');
        }
        $status = (int) $iclTranslationManagement->settings_factory()
            ->term_meta_setting(self::TWIN)
            ->status();
        if ($status !== 1) { // 1 = WPML_COPY_CUSTOM_FIELD
            self::markTestSkipped(
                'wpml-config.xml not imported yet (twin not registered as Copy) — open any wp-admin page once.'
            );
        }
    }

    /**
     * Creates a source product_cat term and a linked translation in a second
     * language.
     *
     * @return array{0:int,1:int,2:int} [sourceTermId, sourceTermTaxonomyId, translatedTermId]
     */
    private function createTranslatedCategoryPair(): array
    {
        $defaultLang = apply_filters('wpml_default_language', null);
        $secondary = null;
        foreach ((array) apply_filters('wpml_active_languages', null) as $lang) {
            $code = $lang['code'] ?? null;
            if ($code && $code !== $defaultLang) {
                $secondary = $code;
                break;
            }
        }
        if (!$defaultLang || !$secondary) {
            self::markTestSkipped('Could not determine two WPML languages.');
        }

        $source = wp_insert_term('Voucher Src ' . uniqid('', true), 'product_cat');
        self::assertIsArray($source);
        $this->createdTermIds[] = (int) $source['term_id'];
        $sourceTtId = (int) $source['term_taxonomy_id'];
        do_action('wpml_set_element_language_details', [
            'element_id' => $sourceTtId,
            'element_type' => 'tax_product_cat',
            'trid' => false,
            'language_code' => $defaultLang,
            'source_language_code' => null,
        ]);

        $trid = apply_filters('wpml_element_trid', null, $sourceTtId, 'tax_product_cat');

        $translated = wp_insert_term('Voucher Trn ' . uniqid('', true), 'product_cat');
        self::assertIsArray($translated);
        $this->createdTermIds[] = (int) $translated['term_id'];
        $translatedTtId = (int) $translated['term_taxonomy_id'];
        do_action('wpml_set_element_language_details', [
            'element_id' => $translatedTtId,
            'element_type' => 'tax_product_cat',
            'trid' => $trid,
            'language_code' => $secondary,
            'source_language_code' => $defaultLang,
        ]);

        return [(int) $source['term_id'], $sourceTtId, (int) $translated['term_id']];
    }
}