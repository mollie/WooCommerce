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

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\Voucher;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\PaymentMethods\Voucher;
use Psr\Container\ContainerInterface;

class VoucherModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;

    /**
     * @var string
     */
    protected $voucherDefaultCategory;

    public function services(): array
    {
        return [
                'voucher.defaultCategory' => static function (ContainerInterface $container): string {
                    $paymentMethods = $container->get('gateway.paymentMethods');
                    $voucher = isset($paymentMethods['voucher']) ? $paymentMethods['voucher'] : false;
                    if ($voucher) {
                        return $voucher->voucherDefaultCategory();
                    }
                    return Voucher::NO_CATEGORY;
                },
        ];
    }

    /**
     * @param ContainerInterface $container
     *
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        $gatewayInstances = $container->get('gateway.instances');
        $voucherGateway = $gatewayInstances['mollie_wc_gateway_voucher'] ?? false;
        $voucher = $voucherGateway && $voucherGateway->enabled === 'yes';

        if ($voucher) {
            $this->voucherDefaultCategory = $container->get('voucher.defaultCategory');
            $this->voucherEnabledHooks();
        }

        return true;
    }

    public function voucherEnabledHooks()
    {
        add_filter(
            'woocommerce_product_data_tabs',
            static function ($tabs) {
                    $tabs['MollieSettingsPage'] = [
                            'label' => __('Mollie Settings', 'mollie-payments-for-woocommerce'),
                            'target' => 'mollie_options',
                            'class' => ['show_if_simple', 'show_if_variable'],
                    ];

                    return $tabs;
            }
        );
        add_action('woocommerce_product_data_panels', [$this, 'mollieOptionsProductTabContent']);
        add_action('woocommerce_process_product_meta_simple', [$this, 'saveProductVoucherOptionFields']);
        add_action('woocommerce_process_product_meta_variable', [$this, 'saveProductVoucherOptionFields']);
        add_action('woocommerce_product_after_variable_attributes', [$this, 'voucherFieldInVariations'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'saveVoucherFieldVariations'], 10, 2);
        add_filter('woocommerce_available_variation', [$this, 'addVoucherVariationData']);
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
                <span class="title"><?php esc_html_e('Mollie Voucher Category', 'mollie-payments-for-woocommerce'); ?></span>
                <span class="input-text-wrap">
                <select name="_mollie_voucher_category" class="select">
                   <option value=""><?php esc_html_e('--Please choose an option--', 'mollie-payments-for-woocommerce'); ?></option>
                   <option value="no_category"> <?php esc_html_e('No Category', 'mollie-payments-for-woocommerce'); ?></option>
                   <option value="meal"><?php esc_html_e('Meal', 'mollie-payments-for-woocommerce'); ?></option>
                   <option value="eco"><?php esc_html_e('Eco', 'mollie-payments-for-woocommerce'); ?></option>
                   <option value="gift"><?php esc_html_e('Gift', 'mollie-payments-for-woocommerce'); ?></option>
                </select>
         </span>
            </label>
        </div>
        <?php
    }

    /**
     * Save value entered on product edit bulk action.
     */
    public function voucherBulkEditSave($product)
    {
        $post_id = $product->get_id();
        $optionName = Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION;
        check_ajax_referer('inlineeditnonce', '_inline_edit');
        if (isset($_REQUEST[$optionName])) {
            $option = filter_var(wp_unslash($_REQUEST[$optionName]), FILTER_SANITIZE_SPECIAL_CHARS);
            update_post_meta($post_id, $optionName, wc_clean($option));
        }
    }

    /**
     * Show voucher selector on create product category page.
     */
    public function voucherTaxonomyFieldOnCreatePage()
    {
        ?>
        <div class="form-field">
            <label for="_mollie_voucher_category"><?php esc_html_e('Mollie Voucher Category', 'mollie-payments-for-woocommerce'); ?></label>
            <select name="_mollie_voucher_category" id="_mollie_voucher_category" class="select">
                <option value=""><?php esc_html_e('--Please choose an option--', 'mollie-payments-for-woocommerce'); ?></option>
                <option value="no_category"> <?php esc_html_e('No Category', 'mollie-payments-for-woocommerce'); ?></option>
                <option value="meal"><?php esc_html_e('Meal', 'mollie-payments-for-woocommerce'); ?></option>
                <option value="eco"><?php esc_html_e('Eco', 'mollie-payments-for-woocommerce'); ?></option>
                <option value="gift"><?php esc_html_e('Gift', 'mollie-payments-for-woocommerce'); ?></option>
            </select>
            <p class="description"><?php esc_html_e('Select a voucher category to apply to all products with this category', 'mollie-payments-for-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Show voucher selector on edit product category page.
     */
    public function voucherTaxonomyFieldOnEditPage($term)
    {
        $term_id = $term->term_id;
        $savedCategory = get_term_meta($term_id, '_mollie_voucher_category', true);

        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="_mollie_voucher_category"><?php esc_html_e('Mollie Voucher Category', 'mollie-payments-for-woocommerce'); ?></label></th>
            <td>
                <select name="_mollie_voucher_category" id="_mollie_voucher_category" class="select">
                    <option value="">
                        <?php esc_html_e(
                            '--Please choose an option--',
                            'mollie-payments-for-woocommerce'
                        ); ?></option>
                    <option value="no_category" <?php selected($savedCategory, 'no_category'); ?>>
                        <?php esc_html_e('No Category', 'mollie-payments-for-woocommerce'); ?>
                    </option>
                    <option value="meal" <?php selected($savedCategory, 'meal'); ?>>
                        <?php esc_html_e('Meal', 'mollie-payments-for-woocommerce'); ?>
                    </option>
                    <option value="eco" <?php selected($savedCategory, 'eco'); ?>>
                        <?php esc_html_e('Eco', 'mollie-payments-for-woocommerce'); ?>
                    </option>
                    <option value="gift" <?php selected($savedCategory, 'gift'); ?>>
                        <?php esc_html_e('Gift', 'mollie-payments-for-woocommerce'); ?>
                    </option>
                </select>
                <p class="description">
                    <?php esc_html_e(
                        'Select a voucher category to apply to all products with this category',
                        'mollie-payments-for-woocommerce'
                    ); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save voucher category on product category meta term.
     */
    public function voucherTaxonomyCustomMetaSave($term_id)
    {

        $metaOption = filter_input(INPUT_POST, '_mollie_voucher_category', FILTER_SANITIZE_SPECIAL_CHARS);

        update_term_meta($term_id, '_mollie_voucher_category', $metaOption);
    }

    /**
     * Contents of the Mollie options product tab.
     */
    public function mollieOptionsProductTabContent()
    {
        ?>
        <div id='mollie_options' class='panel woocommerce_options_panel'>
            <div class='options_group'>
                <?php
                $defaultCategory = $this->voucherDefaultCategory;
                woocommerce_wp_select(
                    [
                                'id' => Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
                                'title' => __(
                                    'Select the default products category',
                                    'mollie-payments-for-woocommerce'
                                ),
                                'label' => __(
                                    'Products voucher category',
                                    'mollie-payments-for-woocommerce'
                                ),

                                'type' => 'select',
                                'options' => [
                                        $defaultCategory => __(
                                            'Same as default category',
                                            'mollie-payments-for-woocommerce'
                                        ),
                                        Voucher::NO_CATEGORY => __('No Category', 'mollie-payments-for-woocommerce'),
                                        Voucher::MEAL => __('Meal', 'mollie-payments-for-woocommerce'),
                                        Voucher::ECO => __('Eco', 'mollie-payments-for-woocommerce'),
                                        Voucher::GIFT => __('Gift', 'mollie-payments-for-woocommerce'),

                                ],
                                'default' => $defaultCategory,
                            /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                                'description' => __(
                                    'In order to process it, all products in the order must have a category. To disable the product from voucher selection select "No category" option.',
                                    'mollie-payments-for-woocommerce'
                                ),
                                'desc_tip' => true,
                        ]
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save the product voucher local category option.
     *
     * @param $post_id
     */
    public function saveProductVoucherOptionFields($post_id)
    {
        $option = filter_input(INPUT_POST, Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION, FILTER_SANITIZE_SPECIAL_CHARS);
        $voucherCategory = $option ?? '';

        update_post_meta(
            $post_id,
            Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
            $voucherCategory
        );
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
        $defaultCategory = $this->voucherDefaultCategory;
        woocommerce_wp_select(
            [
                'id' => 'voucher[' . $variation->ID . ']',
                'label' => __('Mollie Voucher category', 'mollie-payments-for-woocommerce'),
                'value' => get_post_meta($variation->ID, 'voucher', true),
                'options' => [
                    $defaultCategory => __('Same as default category', 'mollie-payments-for-woocommerce'),
                    Voucher::NO_CATEGORY => __('No Category', 'mollie-payments-for-woocommerce'),
                    Voucher::MEAL => __('Meal', 'mollie-payments-for-woocommerce'),
                    Voucher::ECO => __('Eco', 'mollie-payments-for-woocommerce'),
                    Voucher::GIFT => __('Gift', 'mollie-payments-for-woocommerce'),
                ],
            ]
        );
    }

    /**
     * Save the voucher option in the variation product
     * @param $variation_id
     * @param $i
     */
    public function saveVoucherFieldVariations($variation_id, $i)
    {
        $optionName = 'voucher';
        //phpcs:ignore WordPress.Security.NonceVerification.Missing
        $voucherCategory = isset($_POST[$optionName]) && isset($_POST[$optionName][$variation_id])
        //phpcs:ignore WordPress.Security.NonceVerification.Missing
                ? sanitize_text_field(wp_unslash($_POST[$optionName][$variation_id]))
                : false;

        if ($voucherCategory) {
            update_post_meta($variation_id, $optionName, esc_attr($voucherCategory));
        }
    }

    public function addVoucherVariationData($variations)
    {
        $optionName = 'voucher';
        $variations[$optionName] = get_post_meta($variations[ 'variation_id' ], $optionName, true);
        return $variations;
    }
}
