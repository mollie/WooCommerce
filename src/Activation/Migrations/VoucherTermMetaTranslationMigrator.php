<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Activation\Migrations;

use Mollie\WooCommerce\PaymentMethods\Voucher;

/**
 * One-time backfill of the voucher-category "translation twin" term meta for
 * categories that were already translated before the twin existed.
 *
 * Seeds `mollie_voucher_translation_category` from the legacy
 * `_mollie_voucher_category` on every product_cat term and copies it onto that
 * term's existing WPML translations, so pre-upgrade multilingual sites work
 * without a manual re-save.
 *
 * For the full mechanism, see the class docblock of
 * tests/Integration/spec/Gateway/Voucher/VoucherTermMetaTranslationTest.php.
 */
class VoucherTermMetaTranslationMigrator implements MigratorInterface
{
    public function targetVersion(): string
    {
        return '8.1.10';
    }

    public function migrate(): void
    {
        global $wpdb;

        $termIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT term_id FROM {$wpdb->termmeta}
                 WHERE meta_key = %s AND meta_value <> ''",
                Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION
            )
        );

        foreach ($termIds as $termId) {
            $termId = (int) $termId;
            $value = get_term_meta($termId, Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION, true);
            if (!$value) {
                continue;
            }

            // Source term: seed the twin so future WPML syncs have it to copy.
            update_term_meta($termId, Voucher::MOLLIE_VOUCHER_CATEGORY_TRANSLATION_OPTION, $value);

            // Existing translations: write the twin directly so already-translated
            // categories resolve a voucher category at once (WPML would only copy
            // it on the next save of the source term).
            foreach ($this->translatedTermIds($termId) as $translatedTermId) {
                update_term_meta(
                    $translatedTermId,
                    Voucher::MOLLIE_VOUCHER_CATEGORY_TRANSLATION_OPTION,
                    $value
                );
            }
        }
    }

    /**
     * Product-category term IDs that are translations of $termId in other
     * languages. Empty when WPML is not active.
     *
     * @return int[]
     */
    private function translatedTermIds(int $termId): array
    {
        $languages = apply_filters('wpml_active_languages', null);
        if (!is_array($languages) || $languages === []) {
            return [];
        }

        $ids = [];
        foreach ($languages as $language) {
            $code = is_array($language) ? ($language['code'] ?? null) : null;
            if (!$code) {
                continue;
            }
            // return_original_if_missing=false => null when no translation exists.
            $translatedId = apply_filters('wpml_object_id', $termId, 'product_cat', false, $code);
            $translatedId = (int) $translatedId;
            if ($translatedId && $translatedId !== $termId) {
                $ids[$translatedId] = $translatedId;
            }
        }

        return array_values($ids);
    }
}
