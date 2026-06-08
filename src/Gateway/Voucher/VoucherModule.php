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
declare (strict_types=1);
namespace Mollie\WooCommerce\Gateway\Voucher;

use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Mollie\Psr\Container\ContainerInterface;
class VoucherModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        return [];
    }
    /**
     * @param ContainerInterface $container
     *
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        $gatewayInstances = $container->get('__deprecated.gateway_helpers');
        $voucherGateway = $gatewayInstances['mollie_wc_gateway_voucher'] ?? \false;
        $voucher = $voucherGateway && $voucherGateway->enabled === 'yes';
        if ($voucher) {
            $this->voucherEnabledHooks();
        }
        return \true;
    }
    public function voucherEnabledHooks()
    {
        add_filter('woocommerce_product_data_tabs', static function ($tabs) {
            $tabs['MollieSettingsPage'] = ['label' => __('Mollie Settings', 'mollie-payments-for-woocommerce'), 'target' => 'mollie_options', 'class' => ['show_if_simple', 'show_if_variable']];
            return $tabs;
        });
        add_action('woocommerce_product_data_panels', [$this, 'mollieOptionsProductTabContent']);
        add_action('woocommerce_process_product_meta_simple', [$this, 'saveProductVoucherOptionFields']);
        add_action('woocommerce_product_after_variable_attributes', [$this, 'voucherFieldInVariations'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'saveVoucherFieldVariations'], 10, 2);
        add_action('woocommerce_product_bulk_edit_start', [$this, 'voucherBulkEditInput']);
        add_action('woocommerce_product_bulk_edit_save', [$this, 'voucherBulkEditSave']);
        add_action('product_cat_add_form_fields', [$this, 'voucherTaxonomyFieldOnCreatePage'], 10, 1);
        add_action('product_cat_edit_form_fields', [$this, 'voucherTaxonomyFieldOnEditPage'], 10, 1);
        add_action('edited_product_cat', [$this, 'voucherTaxonomyCustomMetaSave'], 10, 1);
        add_action('create_product_cat', [$this, 'voucherTaxonomyCustomMetaSave'], 10, 1);
    }
    /**
     * Show voucher selector on product edit bulk action
     */
    public function voucherBulkEditInput()
    {
        ?>
        <div class="inline-edit-group">
            <label class="alignleft">
                <span class="title"><?php 
        esc_html_e('Mollie Voucher Category', 'mollie-payments-for-woocommerce');
        ?></span>
                <span class="input-text-wrap">
                <select name="_mollie_voucher_category" class="select">
                   <option value=""><?php 
        esc_html_e('-- No category Selected --', 'mollie-payments-for-woocommerce');
        ?></option>
                   <option value="<?php 
        echo esc_attr(Voucher::MEAL);
        ?>"><?php 
        esc_html_e('Meal', 'mollie-payments-for-woocommerce');
        ?></option>
                   <option value="<?php 
        echo esc_attr(Voucher::ECO);
        ?>"><?php 
        esc_html_e('Eco', 'mollie-payments-for-woocommerce');
        ?></option>
                   <option value="<?php 
        echo esc_attr(Voucher::GIFT);
        ?>"><?php 
        esc_html_e('Gift', 'mollie-payments-for-woocommerce');
        ?></option>
                   <option value="<?php 
        echo esc_attr(Voucher::SPORT_CULTURE);
        ?>"><?php 
        esc_html_e('Sport & Culture', 'mollie-payments-for-woocommerce');
        ?></option>
                </select>
         </span>
            </label>
        </div>
        <?php 
    }
    /**
     * Save value entered on product edit bulk action.
     *
     * @param \WC_Product $product
     */
    public function voucherBulkEditSave($product)
    {
        $post_id = $product->get_id();
        $optionName = Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION;
        if (wp_doing_ajax()) {
            check_ajax_referer('inlineeditnonce', '_inline_edit');
        }
        if (isset($_REQUEST[$optionName])) {
            $option = filter_var(wp_unslash($_REQUEST[$optionName]), \FILTER_SANITIZE_SPECIAL_CHARS);
            $option = (array) wc_clean(wp_unslash($option));
            $product = wc_get_product($post_id);
            if ($product) {
                $product->update_meta_data($optionName, $option);
                $product->save();
            }
        }
    }
    /**
     * Show voucher selector on create product category page.
     */
    public function voucherTaxonomyFieldOnCreatePage()
    {
        ?>
        <div class="form-field">
            <label for="_mollie_voucher_category"><?php 
        esc_html_e('Mollie Voucher Category', 'mollie-payments-for-woocommerce');
        ?></label>
            <select name="_mollie_voucher_category" id="_mollie_voucher_category" class="select">
                <option value=""><?php 
        esc_html_e('-- No category Selected --', 'mollie-payments-for-woocommerce');
        ?></option>
                <option value="<?php 
        echo esc_attr(Voucher::MEAL);
        ?>"><?php 
        esc_html_e('Meal', 'mollie-payments-for-woocommerce');
        ?></option>
                <option value="<?php 
        echo esc_attr(Voucher::ECO);
        ?>"><?php 
        esc_html_e('Eco', 'mollie-payments-for-woocommerce');
        ?></option>
                <option value="<?php 
        echo esc_attr(Voucher::GIFT);
        ?>"><?php 
        esc_html_e('Gift', 'mollie-payments-for-woocommerce');
        ?></option>
                <option value="<?php 
        echo esc_attr(Voucher::SPORT_CULTURE);
        ?>"><?php 
        esc_html_e('Sport & Culture', 'mollie-payments-for-woocommerce');
        ?></option>
            </select>
            <p class="description"><?php 
        esc_html_e('Select a voucher category to apply to all products with this category', 'mollie-payments-for-woocommerce');
        ?></p>
        </div>
        <?php 
    }
    /**
     * Show voucher selector on edit product category page.
     */
    public function voucherTaxonomyFieldOnEditPage($term)
    {
        $term_id = $term->term_id;
        $savedCategory = get_term_meta($term_id, '_mollie_voucher_category', \true);
        if (!is_array($savedCategory)) {
            $savedCategory = [$savedCategory];
        }
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="_mollie_voucher_category"><?php 
        esc_html_e('Mollie Voucher Category', 'mollie-payments-for-woocommerce');
        ?></label></th>
            <td>
                <select name="_mollie_voucher_category" id="_mollie_voucher_category" class="select">
                    <option value="">
                        <?php 
        esc_html_e('-- No category Selected --', 'mollie-payments-for-woocommerce');
        ?></option>
                    <option value="<?php 
        echo esc_attr(Voucher::MEAL);
        ?>" <?php 
        selected(in_array(Voucher::MEAL, $savedCategory, \true));
        ?>>
                        <?php 
        esc_html_e('Meal', 'mollie-payments-for-woocommerce');
        ?>
                    </option>
                    <option value="<?php 
        echo esc_attr(Voucher::ECO);
        ?>" <?php 
        selected(in_array(Voucher::ECO, $savedCategory, \true));
        ?>>
                        <?php 
        esc_html_e('Eco', 'mollie-payments-for-woocommerce');
        ?>
                    </option>
                    <option value="<?php 
        echo esc_attr(Voucher::GIFT);
        ?>" <?php 
        selected(in_array(Voucher::GIFT, $savedCategory, \true));
        ?>>
                        <?php 
        esc_html_e('Gift', 'mollie-payments-for-woocommerce');
        ?>
                    </option>
                    <option value="<?php 
        echo esc_attr(Voucher::SPORT_CULTURE);
        ?>" <?php 
        selected(in_array(Voucher::SPORT_CULTURE, $savedCategory, \true));
        ?>>
                        <?php 
        esc_html_e('Sport & Culture', 'mollie-payments-for-woocommerce');
        ?>
                    </option>
                </select>
                <p class="description">
                    <?php 
        esc_html_e('Select a voucher category to apply to all products with this category', 'mollie-payments-for-woocommerce');
        ?>
                </p>
            </td>
        </tr>
        <?php 
    }
    /**
     * Save voucher category on product category meta term.
     *
     * @param int $term_id
     */
    public function voucherTaxonomyCustomMetaSave($term_id)
    {
        $metaOption = filter_input(\INPUT_POST, '_mollie_voucher_category', \FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$metaOption) {
            $metaOption = '';
        }
        $metaOption = wc_clean(wp_unslash($metaOption));
        if (in_array($metaOption, [Voucher::MEAL, Voucher::ECO, Voucher::GIFT, Voucher::SPORT_CULTURE], \true)) {
            update_term_meta($term_id, '_mollie_voucher_category', $metaOption);
        } else {
            delete_term_meta($term_id, '_mollie_voucher_category');
        }
    }
    /**
     * Contents of the Mollie options product tab.
     */
    public function mollieOptionsProductTabContent()
    {
        //get values manually for old settings conversion
        $product = wc_get_product();
        if (!$product || $product->get_type() !== 'simple') {
            return;
        }
        $values = $product->get_meta(Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION);
        if ($values && !is_array($values)) {
            if ($values === Voucher::NO_CATEGORY) {
                $values = [];
            }
            $values = [$values];
        }
        if (!$values) {
            $values = [];
        }
        ?>
        <div id='mollie_options' class='panel woocommerce_options_panel'>
            <div class='options_group'>
                <?php 
        woocommerce_wp_select([
            'id' => Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
            'name' => Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION . '[]',
            'title' => __('Select the default products category', 'mollie-payments-for-woocommerce'),
            'label' => __('Products voucher category', 'mollie-payments-for-woocommerce'),
            'class' => 'wc-enhanced-select short',
            'options' => [Voucher::MEAL => __('Meal', 'mollie-payments-for-woocommerce'), Voucher::ECO => __('Eco', 'mollie-payments-for-woocommerce'), Voucher::GIFT => __('Gift', 'mollie-payments-for-woocommerce'), Voucher::SPORT_CULTURE => __('Sport & Culture', 'mollie-payments-for-woocommerce')],
            'default' => [],
            'value' => $values,
            'custom_attributes' => ['multiple' => \true],
            /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
            'description' => __("In order to process it, all products in the order must have a category. To disable the product from voucher selection select no option. If orders API is active only the first option will be used", 'mollie-payments-for-woocommerce'),
            'desc_tip' => \true,
        ]);
        ?>
            </div>
        </div>
        <?php 
    }
    /**
     * Save the product voucher local category option.
     *
     * @param int $post_id
     */
    public function saveProductVoucherOptionFields($post_id)
    {
        //phpcs:ignore WordPress.Security.NonceVerification.Missing
        $option = filter_input(\INPUT_POST, Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION, \FILTER_SANITIZE_STRING, \FILTER_FORCE_ARRAY);
        //filter out not allowed
        if (!is_array($option)) {
            $option = [$option];
        }
        foreach ($option as $key => $value) {
            if (!in_array($value, [Voucher::MEAL, Voucher::ECO, Voucher::GIFT, Voucher::SPORT_CULTURE], \true)) {
                unset($option[$key]);
            }
        }
        $product = wc_get_product($post_id);
        if ($product) {
            if (!$option) {
                $product->delete_meta_data(Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION);
            } else {
                $product->update_meta_data(Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION, $option);
            }
            $product->save();
        }
    }
    /**
     * Add dedicated voucher category field for variations.
     * Default is the same as the general voucher category
     * @param $loop
     * @param $variation_data
     * @param $variation
     */
    public function voucherFieldInVariations($loop, $variation_data, $variation)
    {
        //get values manually for old settings conversion
        $product = wc_get_product($variation->ID);
        if (!$product) {
            return;
        }
        $values = $product->get_meta('voucher');
        if ($values && !is_array($values)) {
            if ($values === Voucher::NO_CATEGORY) {
                $values = [];
            }
            $values = [$values];
        }
        if (!$values) {
            $values = [];
        }
        woocommerce_wp_select(['id' => 'voucher[' . $variation->ID . ']', 'name' => 'voucher[' . $variation->ID . '][]', 'label' => __('Mollie Voucher category', 'mollie-payments-for-woocommerce'), 'class' => 'wc-enhanced-select short', 'options' => [Voucher::MEAL => __('Meal', 'mollie-payments-for-woocommerce'), Voucher::ECO => __('Eco', 'mollie-payments-for-woocommerce'), Voucher::GIFT => __('Gift', 'mollie-payments-for-woocommerce'), Voucher::SPORT_CULTURE => __('Sport & Culture', 'mollie-payments-for-woocommerce')], 'default' => [], 'value' => $values, 'custom_attributes' => ['multiple' => \true]]);
    }
    /**
     * Save the voucher option in the variation product
     * @param int $variation_id
     * @param int $i
     */
    public function saveVoucherFieldVariations($variation_id, $i)
    {
        //phpcs:ignore WordPress.Security.NonceVerification.Missing
        $voucher = filter_input(\INPUT_POST, 'voucher', \FILTER_SANITIZE_STRING, \FILTER_FORCE_ARRAY);
        $voucherCategories = $voucher[$variation_id] ?? [];
        //filter out not allowed
        if (!is_array($voucherCategories)) {
            $voucherCategories = [$voucherCategories];
        }
        foreach ($voucherCategories as $key => $value) {
            if (!in_array($value, [Voucher::MEAL, Voucher::ECO, Voucher::GIFT, Voucher::SPORT_CULTURE], \true)) {
                unset($voucherCategories[$key]);
            }
        }
        $product = wc_get_product($variation_id);
        if ($product) {
            if (!$voucherCategories) {
                $product->delete_meta_data('voucher');
            } else {
                $product->update_meta_data('voucher', $voucherCategories);
            }
            $product->save();
        }
    }
}
