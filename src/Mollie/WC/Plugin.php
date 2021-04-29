<?php

use Mollie\Api\CompatibilityChecker;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;

class Mollie_WC_Plugin
{
    const PLUGIN_ID      = 'mollie-payments-for-woocommerce';
    const PLUGIN_TITLE   = 'Mollie Payments for WooCommerce';
    const PLUGIN_VERSION = '6.3.0';

    const DB_VERSION     = '1.0';
    const DB_VERSION_PARAM_NAME = 'mollie-db-version';
    const PENDING_PAYMENT_DB_TABLE_NAME = 'mollie_pending_payment';

    const POST_DATA_KEY = 'post_data';
    const APPLE_PAY_METHOD_ALLOWED_KEY = 'mollie_apple_pay_method_allowed';

    /**
     * @var bool
     */
    private static $initiated = false;

    /**
     * @var array
     */
    public static $GATEWAYS = array(
        'Mollie_WC_Gateway_BankTransfer',
        'Mollie_WC_Gateway_Belfius',
        'Mollie_WC_Gateway_Creditcard',
        'Mollie_WC_Gateway_DirectDebit',
        'Mollie_WC_Gateway_EPS',
        'Mollie_WC_Gateway_Giropay',
        'Mollie_WC_Gateway_Ideal',
        'Mollie_WC_Gateway_Kbc',
        'Mollie_WC_Gateway_KlarnaPayLater',
        'Mollie_WC_Gateway_KlarnaSliceIt',
        'Mollie_WC_Gateway_Bancontact',
	    // LEGACY - DO NOT REMOVE!
        // MisterCash was renamed to Bancontact, but this class should stay available for old orders and subscriptions!
        'Mollie_WC_Gateway_MisterCash',
        'Mollie_WC_Gateway_PayPal',
        'Mollie_WC_Gateway_Paysafecard',
        'Mollie_WC_Gateway_Przelewy24',
        'Mollie_WC_Gateway_Sofort',
        'Mollie_WC_Gateway_Giftcard',
        'Mollie_WC_Gateway_Applepay',
        'Mollie_WC_Gateway_MyBank',
        'Mollie_WC_Gateway_Mealvoucher',

    );

    private function __construct () {}

    /**
     * WCSubscription related.
     */
    public static function schedulePendingPaymentOrdersExpirationCheck()
    {
        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            $settings_helper = self::getSettingsHelper();
            $time = $settings_helper->getPaymentConfirmationCheckTime();
            $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
            if (!$nextScheduledTime) {
                wp_schedule_event($time, 'daily', 'pending_payment_confirmation_check');
            }

            add_action('pending_payment_confirmation_check', array(__CLASS__, 'checkPendingPaymentOrdersExpiration'));
        }

    }

    /**
     *
     */
    public static function initDb()
    {
        global $wpdb;
        $wpdb->mollie_pending_payment = $wpdb->prefix . self::PENDING_PAYMENT_DB_TABLE_NAME;
        if(get_option(self::DB_VERSION_PARAM_NAME, '') != self::DB_VERSION){

            global $wpdb;
            $pendingPaymentConfirmTable = $wpdb->prefix . self::PENDING_PAYMENT_DB_TABLE_NAME;
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            if($wpdb->get_var("show tables like '$pendingPaymentConfirmTable'") != $pendingPaymentConfirmTable) {
                $sql = "
					CREATE TABLE " . $pendingPaymentConfirmTable . " (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    post_id bigint NOT NULL,
                    expired_time int NOT NULL,
                    UNIQUE KEY id (id)
                );";
                dbDelta($sql);

	            /**
	             * Remove redundant 'DESCRIBE *__mollie_pending_payment' error so it doesn't show up in error logs
	             */
	            global $EZSQL_ERROR;
				array_pop($EZSQL_ERROR);
            }
            update_option(self::DB_VERSION_PARAM_NAME, self::DB_VERSION);
        }

    }

    /**
     *
     */
    public static function checkPendingPaymentOrdersExpiration()
    {
        global $wpdb;
        $currentDate = new DateTime();
        $items = $wpdb->get_results("SELECT * FROM {$wpdb->mollie_pending_payment} WHERE expired_time < {$currentDate->getTimestamp()};");
        foreach ($items as $item){
	        $order = wc_get_order( $item->post_id );

	        // Check that order actually exists
	        if ( $order == false ) {
		        return false;
	        }

            if ($order->get_status() == Mollie_WC_Gateway_Abstract::STATUS_COMPLETED){

                $new_order_status = Mollie_WC_Gateway_Abstract::STATUS_FAILED;
                $paymentMethodId = $order->get_meta( '_payment_method_title', true );
                $molliePaymentId = $order->get_meta( '_mollie_payment_id', true );
                $order->add_order_note(sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __('%s payment failed (%s).', 'mollie-payments-for-woocommerce'),
                    $paymentMethodId,$molliePaymentId
                ));

                $order->update_status($new_order_status, '');
                if ( $order->get_meta( '_order_stock_reduced', $single = true ) ) {
                    // Restore order stock
                    Mollie_WC_Plugin::getDataHelper()->restoreOrderStock( $order );

                    Mollie_WC_Plugin::debug( __METHOD__ . " Stock for order {$order->get_id()} restored." );
                }

                $wpdb->delete(
                        $wpdb->mollie_pending_payment,
                        array(
                                'post_id' => $order->get_id(),
                        )
                );
            }
        }

    }

    /**
     * Initialize plugin
     */
	public static function init() {
		if ( self::$initiated ) {
			/*
			 * Already initialized
			 */
			return;
		}

		$plugin_basename = self::getPluginFile();
		$settings_helper = self::getSettingsHelper();
		$data_helper     = self::getDataHelper();

		// When page 'WooCommerce -> Checkout -> Checkout Options' is saved
		add_action( 'woocommerce_settings_save_checkout', array ( $data_helper, 'deleteTransients' ) );

		// Add Mollie gateways
		add_filter( 'woocommerce_payment_gateways', array ( __CLASS__, 'addGateways' ) );

        add_filter('woocommerce_payment_gateways', [__CLASS__, 'maybeDisableApplePayGateway'], 20);
        add_filter('woocommerce_payment_gateways', function($gateways){
            $maybeEnablegatewayHelper = new Mollie_WC_Helper_MaybeDisableGateway();
            return $maybeEnablegatewayHelper->maybeDisableMealVoucherGateway($gateways);
        });
        add_filter('woocommerce_payment_gateways', [__CLASS__, 'maybeDisableBankTransferGateway'], 20);
        add_action(
            'woocommerce_after_order_object_save',
            function () {
                $mollieWooCommerceSession = mollieWooCommerceSession();
                if ($mollieWooCommerceSession instanceof WC_Session) {
                    $mollieWooCommerceSession->__unset(self::APPLE_PAY_METHOD_ALLOWED_KEY);
                }
            }
        );
        add_action(
            'woocommerce_admin_settings_sanitize_option',
            [$settings_helper, 'updateMerchantIdOnApiKeyChanges'],
            10,
            2
        );
        add_action(
            'update_option_mollie-payments-for-woocommerce_live_api_key',
            [$settings_helper, 'updateMerchantIdAfterApiKeyChanges'],
            10,
            3
        );
        add_action(
            'update_option_mollie-payments-for-woocommerce_test_api_key',
            [$settings_helper, 'updateMerchantIdAfterApiKeyChanges'],
            10,
            3
        );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . $plugin_basename, array ( __CLASS__, 'addPluginActionLinks' ) );

		// Listen to return URL call
		add_action( 'woocommerce_api_mollie_return', array ( __CLASS__, 'onMollieReturn' ) );
        add_action( 'template_redirect', array ( __CLASS__, 'mollieReturnRedirect' ) );

		// Show Mollie instructions on order details page
		add_action( 'woocommerce_order_details_after_order_table', array ( __CLASS__, 'onOrderDetails' ), 10, 1 );

		// Disable SEPA as payment option in WooCommerce checkout
		add_filter( 'woocommerce_available_payment_gateways', array ( __CLASS__, 'disableSEPAInCheckout' ), 10, 1 );

		// Disable old MisterCash as payment option in WooCommerce checkout
		add_filter( 'woocommerce_available_payment_gateways', array ( __CLASS__, 'disableMisterCashInCheckout' ), 10, 1 );

		// Disable Mollie methods on some pages
		add_filter( 'woocommerce_available_payment_gateways', array ( __CLASS__, 'disableMollieOnPaymentMethodChange' ), 10, 1 );

		// Set order to paid and processed when eventually completed without Mollie
		add_action( 'woocommerce_payment_complete', array ( __CLASS__, 'setOrderPaidByOtherGateway' ), 10, 1 );

		// Cancel order at Mollie (for Orders API/Klarna)
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'cancelOrderAtMollie' ) );

		// Capture order at Mollie (for Orders API/Klarna)
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'shipAndCaptureOrderAtMollie' ) );

        add_filter(
            'woocommerce_cancel_unpaid_order',
            array( __CLASS__, 'maybeLetWCCancelOrder' ),
            90,
            2
        );

        // Enqueue Scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueFrontendScripts']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueComponentsAssets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueApplePayDirectScripts']);

        add_action(
            Mollie_WC_Payment_OrderItemsRefunder::ACTION_AFTER_REFUND_ORDER_ITEMS,
            [__CLASS__, 'addOrderNoteForRefundCreated'],
            10,
            3
        );
        add_action(
            Mollie_WC_Payment_OrderItemsRefunder::ACTION_AFTER_CANCELED_ORDER_ITEMS,
            [__CLASS__, 'addOrderNoteForCancelledLineItems'],
            10,
            2
        );

        add_filter(
            'woocommerce_get_settings_pages',
            function ($settings) {
                $settings[] = new Mollie_WC_Settings_Page_Mollie(self::getSettingsHelper());

                return $settings;
            }
        );
        self::voucherEnabledHooks();
        add_filter( Mollie_WC_Plugin::PLUGIN_ID . '_retrieve_payment_gateways', function(){
            return self::$GATEWAYS;
        });
        add_action('wp_loaded', [__CLASS__, 'maybeTestModeNotice']);
        self::mollieApplePayDirectHandling();
        self::gatewaySurchargeHandling();

		self::initDb();
		self::schedulePendingPaymentOrdersExpirationCheck();
        self::registerFrontendScripts();
        wp_enqueue_style('mollie-gateway-icons');
        self::maybeFixSubscriptions();

		// Mark plugin initiated
		self::$initiated = true;
    }

    public static function gatewaySurchargeHandling(){
        $gatewaSurchargeHandler = new Mollie_WC_Helper_GatewaySurchargeHandler();
    }
    /**
     * See MOL-322
     */
    public static function maybeFixSubscriptions(){
        $fixer = new Mollie_WC_Helper_MaybeFixSubscription();
        $fixer->maybeFix();
    }


    public static function maybeTestModeNotice()
    {
        if (mollieWooCommerceIsTestModeEnabled()) {
            $notice = new Mollie_WC_Notice_AdminNotice();
            $message = sprintf(
                esc_html__(
                    '%1$sMollie Payments for WooCommerce%2$s The test mode is active, %3$s disable it%4$s before deploying into production.',
                    'mollie-payments-for-woocommerce'
                ),
                '<strong>',
                '</strong>',
                '<a href="' . esc_url(
                    admin_url('admin.php?page=wc-settings&tab=checkout')
                ) . '">',
                '</a>'
            );
            $notice->addNotice('notice-error', $message);
        }
    }

    public static function maybeLetWCCancelOrder($willCancel, $order) {
        if (!empty($willCancel)) {
            if ($order->get_payment_method()
                !== 'mollie_wc_gateway_banktransfer'
            ) {
                return $willCancel;
            }
            //is banktransfer due date setting activated
            $dueDateActive = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_banktransfer_settings', 'activate_expiry_days_setting');
            if ($dueDateActive) {
                return false;
            }
        }
        return $willCancel;
    }

    public static function voucherEnabledHooks(){
        if(mollieWooCommerceIsVoucherEnabled()){
            add_filter(
                    'woocommerce_product_data_tabs',
                    function ($tabs) {
                        $tabs['Mollie'] = array(
                                'label'		=> __( 'Mollie Settings', 'mollie-payments-for-woocommerce' ),
                                'target'	=> 'mollie_options',
                                'class'		=> array( 'show_if_simple', 'show_if_variable'  ),
                        );

                        return $tabs;
                    }
            );
            add_filter( 'woocommerce_product_data_panels', [__CLASS__, 'mollieOptionsProductTabContent'] );
            add_action( 'woocommerce_process_product_meta_simple', [__CLASS__, 'saveProductVoucherOptionFields']  );
            add_action( 'woocommerce_process_product_meta_variable', [__CLASS__, 'saveProductVoucherOptionFields']  );
            add_action( 'woocommerce_product_after_variable_attributes', [__CLASS__,'voucherFieldInVariations'], 10, 3 );
            add_action( 'woocommerce_save_product_variation', [__CLASS__,'saveVoucherFieldVariations'], 10, 2 );
            add_filter( 'woocommerce_available_variation', [__CLASS__,'addVoucherVariationData'] );

            add_action( 'woocommerce_product_bulk_edit_start', [__CLASS__,'voucherBulkEditInput'] );
            add_action( 'woocommerce_product_bulk_edit_save', [__CLASS__,'voucherBulkEditSave'] );

            add_action('product_cat_add_form_fields',[__CLASS__, 'voucherTaxonomyFieldOnCreatePage'], 10, 1);
            add_action('product_cat_edit_form_fields',[__CLASS__, 'voucherTaxonomyFieldOnEditPage'], 10, 1);
            add_action('edited_product_cat',[__CLASS__, 'voucherTaxonomyCustomMetaSave'], 10, 1);
            add_action('create_product_cat',[__CLASS__, 'voucherTaxonomyCustomMetaSave'], 10, 1);
        }
    }

    /**
     * Show voucher selector on product edit bulk action
     */
    public static function voucherBulkEditInput() {
        ?>
        <div class="inline-edit-group">
            <label class="alignleft">
                <span class="title"><?php _e( 'Mollie Voucher Category', 'mollie-payments-for-woocommerce' ); ?></span>
                <span class="input-text-wrap">
                <select name="_mollie_voucher_category" class="select">
                   <option value=""><?php _e( '--Please choose an option--', 'mollie-payments-for-woocommerce' ); ?></option>
                   <option value="no_category"> <?php _e( 'No Category', 'mollie-payments-for-woocommerce' ); ?></option>
                   <option value="meal"><?php _e( 'Meal', 'mollie-payments-for-woocommerce' ); ?></option>
                   <option value="eco"><?php _e( 'Eco', 'mollie-payments-for-woocommerce' ); ?></option>
                   <option value="gift"><?php _e( 'Gift', 'mollie-payments-for-woocommerce' ); ?></option>
                </select>
         </span>
            </label>
        </div>
        <?php
    }

    /**
     * Save value entered on product edit bulk action.
     */
    public static function voucherBulkEditSave( $product ) {
        $post_id = $product->get_id();
        $optionName = Mollie_WC_Gateway_Mealvoucher::MOLLIE_VOUCHER_CATEGORY_OPTION;
        if ( isset( $_REQUEST[$optionName] ) ) {
            $option = $_REQUEST[$optionName];
            update_post_meta( $post_id, $optionName, wc_clean( $option ) );
        }
    }

    /**
     * Show voucher selector on create product category page.
     */
    public static function voucherTaxonomyFieldOnCreatePage() {
        ?>
        <div class="form-field">
            <label for="_mollie_voucher_category"><?php _e('Mollie Voucher Category', 'mollie-payments-for-woocommerce'); ?></label>
            <select name="_mollie_voucher_category" id="_mollie_voucher_category" class="select">
                <option value=""><?php _e( '--Please choose an option--', 'mollie-payments-for-woocommerce' ); ?></option>
                <option value="no_category"> <?php _e( 'No Category', 'mollie-payments-for-woocommerce' ); ?></option>
                <option value="meal"><?php _e( 'Meal', 'mollie-payments-for-woocommerce' ); ?></option>
                <option value="eco"><?php _e( 'Eco', 'mollie-payments-for-woocommerce' ); ?></option>
                <option value="gift"><?php _e( 'Gift', 'mollie-payments-for-woocommerce' ); ?></option>
            </select>
            <p class="description"><?php _e('Select a voucher category to apply to all products with this category', 'mollie-payments-for-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Show voucher selector on edit product category page.
     */
    public static function voucherTaxonomyFieldOnEditPage($term) {
        $term_id = $term->term_id;
        $savedCategory = get_term_meta($term_id, '_mollie_voucher_category', true);

        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="_mollie_voucher_category"><?php _e('Mollie Voucher Category', 'mollie-payments-for-woocommerce'); ?></label></th>
            <td>
                <select name="_mollie_voucher_category" id="_mollie_voucher_category" class="select">
                    <option value=""><?php _e( '--Please choose an option--', 'mollie-payments-for-woocommerce' ); ?></option>
                    <option value="no_category" <?php selected( $savedCategory, 'no_category' ); ?>> <?php _e( 'No Category', 'mollie-payments-for-woocommerce' ); ?></option>
                    <option value="meal" <?php selected( $savedCategory, 'meal' ); ?>><?php _e( 'Meal', 'mollie-payments-for-woocommerce' ); ?></option>
                    <option value="eco" <?php selected( $savedCategory, 'eco' ); ?>><?php _e( 'Eco', 'mollie-payments-for-woocommerce' ); ?></option>
                    <option value="gift" <?php selected( $savedCategory, 'gift' ); ?>><?php _e( 'Gift', 'mollie-payments-for-woocommerce' ); ?></option>
                </select>
                <p class="description"><?php _e('Select a voucher category to apply to all products with this category', 'mollie-payments-for-woocommerce'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save voucher category on product category meta term.
     */
    public static function voucherTaxonomyCustomMetaSave($term_id) {

        $metaOption = filter_input(INPUT_POST, '_mollie_voucher_category');

        update_term_meta($term_id, '_mollie_voucher_category', $metaOption);
    }

    /**
     * Contents of the Mollie options product tab.
     */
    public static function mollieOptionsProductTabContent()
    {
        ?>
        <div id='mollie_options' class='panel woocommerce_options_panel'><?php

        ?>
        <div class='options_group'><?php
            $voucherSettings = get_option(
                    'mollie_wc_gateway_mealvoucher_settings'
            );
            $defaultCategory = $voucherSettings? $voucherSettings['mealvoucher_category_default']:Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY;
        woocommerce_wp_select(
                array(
                        'id' => Mollie_WC_Gateway_Mealvoucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
                        'title' => __(
                                'Select the default products category',
                                'mollie-payments-for-woocommerce'
                        ),
                        'label' => __(
                                'Products voucher category',
                                'mollie-payments-for-woocommerce'
                        ),

                        'type' => 'select',
                        'options' => array(
                                $defaultCategory => __( 'Same as default category', 'mollie-payments-for-woocommerce' ),
                                Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY => 'No category',
                                Mollie_WC_Gateway_Mealvoucher::MEAL => 'Meal',
                                Mollie_WC_Gateway_Mealvoucher::ECO => 'Eco',
                                Mollie_WC_Gateway_Mealvoucher::GIFT => 'Gift'

                        ),
                        'default' => $defaultCategory,
                    /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                        'description' => sprintf(
                                __(
                                        'In order to process it, all products in the order must have a category. To disable the product from voucher selection select "No category" option.',
                                        'mollie-payments-for-woocommerce'
                                )
                        ),
                        'desc_tip' => true,
                )
        );

        ?></div>

        </div><?php
    }

    /**
     * Save the product voucher local category option.
     *
     * @param $post_id
     */
    public static function saveProductVoucherOptionFields($post_id)
    {
        $option = filter_input(
            INPUT_POST,
            Mollie_WC_Gateway_Mealvoucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
            FILTER_SANITIZE_STRING
        );
        $voucherCategory = isset($option) ? $option : '';

        update_post_meta(
            $post_id,
            Mollie_WC_Gateway_Mealvoucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
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
    public static function voucherFieldInVariations($loop, $variation_data, $variation ) {
        $voucherSettings = get_option(
                'mollie_wc_gateway_mealvoucher_settings'
        );
        $defaultCategory = $voucherSettings? $voucherSettings['mealvoucher_category_default']:Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY;
        woocommerce_wp_select(
                array(
                        'id'          => 'voucher[' . $variation->ID . ']',
                        'label'       => __( 'Mollie Voucher category', 'mollie-payments-for-woocommerce' ),
                        'value'       => get_post_meta( $variation->ID, 'voucher', true ),
                        'options' => array(
                                $defaultCategory => __( 'Same as default category', 'mollie-payments-for-woocommerce' ),
                                Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY => __( 'No Category', 'mollie-payments-for-woocommerce' ),
                                Mollie_WC_Gateway_Mealvoucher::MEAL => __( 'Meal', 'mollie-payments-for-woocommerce' ),
                                Mollie_WC_Gateway_Mealvoucher::ECO => __( 'Eco', 'mollie-payments-for-woocommerce' ),
                                Mollie_WC_Gateway_Mealvoucher::GIFT => __( 'Gift', 'mollie-payments-for-woocommerce' )
                        ),
                )
        );
    }

    /**
     * Save the voucher option in the variation product
     * @param $variation_id
     * @param $i
     */
    public static function saveVoucherFieldVariations($variation_id, $i ) {
        $optionName = 'voucher';
        $args = [$optionName => ['filter' => FILTER_SANITIZE_STRING, 'flags'=>FILTER_REQUIRE_ARRAY]];
        $option = filter_input_array(INPUT_POST, $args);
        $voucherCategory = $option[$optionName][$variation_id] ? $option[$optionName][$variation_id] : null;

        if ( isset( $voucherCategory ) ) update_post_meta( $variation_id, $optionName, esc_attr( $voucherCategory ) );
    }

    public static function addVoucherVariationData( $variations ) {
        $optionName = 'voucher';
        $variations[$optionName] = get_post_meta( $variations[ 'variation_id' ], $optionName, true );
        return $variations;
    }

    /**
     * Enqueues the ApplePay button scripts if enabled and in correct page
     */
    public static function enqueueApplePayDirectScripts()
    {
        if (mollieWooCommerceIsApplePayDirectEnabled('product') && is_product()) {
            $dataToScripts = new Mollie_WC_ApplePayButton_DataToAppleButtonScripts();
            wp_enqueue_style('mollie-applepaydirect');
            wp_enqueue_script('mollie_applepaydirect');
            wp_localize_script(
                'mollie_applepaydirect',
                'mollieApplePayDirectData',
                $dataToScripts->applePayScriptData()
            );
        }
        if (mollieWooCommerceIsApplePayDirectEnabled('cart') && is_cart()) {
            $dataToScripts = new Mollie_WC_ApplePayButton_DataToAppleButtonScripts();
            wp_enqueue_style('mollie-applepaydirect');
            wp_enqueue_script('mollie_applepaydirectCart');
            wp_localize_script(
                'mollie_applepaydirectCart',
                'mollieApplePayDirectDataCart',
                $dataToScripts->applePayScriptData()
            );
        }
    }

    /**
     * Bootstrap the ApplePay button logic if feature enabled
     */
    public static function mollieApplePayDirectHandling()
    {
        $buttonEnabledCart = mollieWooCommerceIsApplePayDirectEnabled('cart');
        $buttonEnabledProduct = mollieWooCommerceIsApplePayDirectEnabled('product');

        if ($buttonEnabledCart || $buttonEnabledProduct) {
            $notices = new Mollie_WC_Notice_AdminNotice();
            $responseTemplates = new Mollie_WC_ApplePayButton_ResponsesToApple();
            $ajaxRequests = new Mollie_WC_ApplePayButton_AjaxRequests( $responseTemplates);
            $applePayHandler = new Mollie_WC_Helper_ApplePayDirectHandler($notices, $ajaxRequests);
            $applePayHandler->bootstrap($buttonEnabledProduct, $buttonEnabledCart);
        }
    }



    /**
     * @param Refund $refund
     * @param WC_Order $order
     * @param array $data
     */
    public static function addOrderNoteForRefundCreated(
        Refund $refund,
        WC_Order $order,
        array $data
    ) {

        $orderNote = sprintf(
            __(
                '%1$s items refunded in WooCommerce and at Mollie.',
                'mollie-payments-for-woocommerce'
            ),
            self::extractRemoteItemsIds($data)
        );

        $order->add_order_note($orderNote);
        Mollie_WC_Plugin::debug($orderNote);
    }

    /**
     * @param array $data
     * @param WC_Order $order
     */
    public static function addOrderNoteForCancelledLineItems(array $data, WC_Order $order)
    {
        $orderNote = sprintf(
            __(
                '%1$s items cancelled in WooCommerce and at Mollie.',
                'mollie-payments-for-woocommerce'
            ),
            self::extractRemoteItemsIds($data)
        );

        $order->add_order_note($orderNote);
        Mollie_WC_Plugin::debug($orderNote);
    }

    /**
     * Register Scripts
     *
     * @return void
     */
    public static function registerFrontendScripts()
    {
        wp_register_script(
            'babel-polyfill',
            Mollie_WC_Plugin::getPluginUrl('/public/js/babel-polyfill.min.js'),
            [],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/js/babel-polyfill.min.js')),
            true
        );

        wp_register_script(
            'mollie_wc_gateway_applepay',
            Mollie_WC_Plugin::getPluginUrl('/public/js/applepay.min.js'),
            [],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/js/applepay.min.js')),
            true
        );
        wp_register_style(
                'mollie-gateway-icons',
                Mollie_WC_Plugin::getPluginUrl('/public/css/mollie-gateway-icons.min.css'),
                [],
                filemtime(Mollie_WC_Plugin::getPluginPath('/public/css/mollie-gateway-icons.min.css')),
                'screen'
        );
        wp_register_style(
            'mollie-components',
            Mollie_WC_Plugin::getPluginUrl('/public/css/mollie-components.min.css'),
            [],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/css/mollie-components.min.css')),
            'screen'
        );
        wp_register_style(
            'mollie-applepaydirect',
            Mollie_WC_Plugin::getPluginUrl('/public/css/mollie-applepaydirect.min.css'),
            [],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/css/mollie-applepaydirect.min.css')),
            'screen'
        );
        wp_register_script(
            'mollie_applepaydirect',
            Mollie_WC_Plugin::getPluginUrl('/public/js/applepayDirect.min.js'),
            ['underscore', 'jquery'],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/js/applepayDirect.min.js')),
            true
        );
        wp_register_script(
            'mollie_applepaydirectCart',
            Mollie_WC_Plugin::getPluginUrl('/public/js/applepayDirectCart.min.js'),
            ['underscore', 'jquery'],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/js/applepayDirectCart.min.js')),
            true
        );
        wp_register_script('mollie', 'https://js.mollie.com/v1/mollie.js', [], null, true);
        wp_register_script(
            'mollie-components',
            Mollie_WC_Plugin::getPluginUrl('/public/js/mollie-components.min.js'),
            ['underscore', 'jquery', 'mollie', 'babel-polyfill'],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/js/mollie-components.min.js')),
            true
        );

        wp_register_style(
            'unabledButton',
            Mollie_WC_Plugin::getPluginUrl('/public/css/unabledButton.min.css'),
            [],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/css/unabledButton.min.css')),
            'screen'
        );
        wp_register_script(
                'gatewaySurcharge',
                Mollie_WC_Plugin::getPluginUrl('/public/js/gatewaySurcharge.min.js'),
                ['underscore', 'jquery'],
                filemtime(Mollie_WC_Plugin::getPluginPath('/public/js/gatewaySurcharge.min.js')),
                true
        );
    }

    /**
     * Enqueue Frontend only scripts
     *
     * @return void
     */
    public static function enqueueFrontendScripts()
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        $applePayGatewayEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_applepay_settings', 'enabled');

        if (!$applePayGatewayEnabled) {
            return;
        }
        wp_enqueue_style('unabledButton');
        wp_enqueue_script('mollie_wc_gateway_applepay');
    }

    /**
     * Enqueue Mollie Component Assets
     */
    public static function enqueueComponentsAssets()
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }

        try {
            $merchantProfileId = mollieWooCommerceMerchantProfileId();
        } catch (ApiException $exception) {
            return;
        }

        $mollieComponentsStylesGateways = mollieWooCommerceComponentsStylesForAvailableGateways();
        $gatewayNames = array_keys($mollieComponentsStylesGateways);

        if (!$merchantProfileId || !$mollieComponentsStylesGateways) {
            return;
        }

        $locale = get_locale();
        $locale =  str_replace('_formal', '', $locale);
        $allowedLocaleValues = Mollie_WC_Components_AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP;
        if(!in_array($locale, $allowedLocaleValues)){
            $locale = Mollie_WC_Components_AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE;
        }

        wp_enqueue_style('mollie-components');
        wp_enqueue_script('mollie-components');

        wp_localize_script(
            'mollie-components',
            'mollieComponentsSettings',
            [
                'merchantProfileId' => $merchantProfileId,
                'options' => [
                    'locale' => $locale,
                    'testmode' => mollieWooCommerceIsTestModeEnabled(),
                ],
                'enabledGateways' => $gatewayNames,
                'componentsSettings' => $mollieComponentsStylesGateways,
                'componentsAttributes' => [
                    [
                        'name' => 'cardHolder',
                        'label' => esc_html__('Card Holder', 'mollie-payments-for-woocommerce')
                    ],
                    [
                        'name' => 'cardNumber',
                        'label' => esc_html__('Card Number', 'mollie-payments-for-woocommerce')
                    ],
                    [
                        'name' => 'expiryDate',
                        'label' => esc_html__('Expiry Date', 'mollie-payments-for-woocommerce')
                    ],
                    [
                        'name' => 'verificationCode',
                        'label' => esc_html__(
                            'Verification Code',
                            'mollie-payments-for-woocommerce'
                        )
                    ],
                ],
                'messages' => [
                    'defaultErrorMessage' => esc_html__(
                        'An unknown error occurred, please check the card fields.',
                        'mollie-payments-for-woocommerce'
                    ),
                ],
                'isCheckout' => is_checkout(),
                'isCheckoutPayPage' => is_checkout_pay_page()
            ]
        );
    }

    /**
     * Returns the order from the Request first by Id, if not by Key
     *
     * @return bool|WC_Order
     */
    public static function orderByRequest()
    {
        $orderId = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT) ?: null;
        $key = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING) ?: null;
        $order = wc_get_order($orderId);

        if (!$order) {
            $order = wc_get_order(wc_get_order_id_by_order_key($key));
        }

        if (!$order) {
            throw new RuntimeException(
                "Could not find order by order Id {$orderId}",
                404
            );
        }

        if (!$order->key_is_valid($key)) {
            throw new RuntimeException(
                "Invalid key given. Key {$key} does not match the order id: {$orderId}",
                401
            );
        }

        return $order;
    }

    /**
     * Old Payment return url callback
     *
     */
    public static function onMollieReturn ()
    {
        try {
            $order = self::orderByRequest();
        } catch (RuntimeException $exc) {
            self::setHttpResponseCode($exc->getCode());
            mollieWooCommerceDebug(__METHOD__ . ":  {$exc->getMessage()}");
            return;
        }

        $gateway = wc_get_payment_gateway_by_order($order);
        $orderId = $order->get_id();

        if (!$gateway) {
            $gatewayName = $order->get_payment_method();

            self::setHttpResponseCode(404);
            mollieWooCommerceDebug(
                __METHOD__ . ":  Could not find gateway {$gatewayName} for order {$orderId}."
            );
            return;
        }

        if (!($gateway instanceof Mollie_WC_Gateway_Abstract)) {
            self::setHttpResponseCode(400);
            mollieWooCommerceDebug(__METHOD__ . ": Invalid gateway {get_class($gateway)} for this plugin. Order {$orderId}.");
            return;
        }

        $redirect_url = $gateway->getReturnRedirectUrlForOrder($order);

        // Add utm_nooverride query string
        $redirect_url = add_query_arg(['utm_nooverride' => 1], $redirect_url);

        mollieWooCommerceDebug(__METHOD__ . ": Redirect url on return order {$gateway->id}, order {$orderId}: {$redirect_url}");

        wp_safe_redirect($redirect_url);
        die;
    }

    /**
     * New Payment return url callback
     *
     */
    public static function mollieReturnRedirect()
    {
        if (isset($_GET['filter_flag'])) {
            $filterFlag = filter_input(INPUT_GET, 'filter_flag', FILTER_SANITIZE_STRING);
            if ($filterFlag === 'onMollieReturn') {
                self::onMollieReturn();
            }
        }
    }

    /**
     * @param WC_Order $order
     */
    public static function onOrderDetails (WC_Order $order)
    {
        if (is_order_received_page())
        {
            /**
             * Do not show instruction again below details on order received page
             * Instructions already displayed on top of order received page by $gateway->thankyou_page()
             *
             * @see Mollie_WC_Gateway_Abstract::thankyou_page
             */
            return;
        }

        $gateway = wc_get_payment_gateway_by_order($order);

        if (!$gateway || !($gateway instanceof Mollie_WC_Gateway_Abstract))
        {
            return;
        }

        /** @var Mollie_WC_Gateway_Abstract $gateway */

        $gateway->displayInstructions($order);
    }

    /**
     * Set HTTP status code
     *
     * @param int $status_code
     */
    public static function setHttpResponseCode ($status_code)
    {
        if (PHP_SAPI !== 'cli' && !headers_sent())
        {
            if (function_exists("http_response_code"))
            {
                http_response_code($status_code);
            }
            else
            {
                header(" ", TRUE, $status_code);
            }
        }
    }

    /**
     * Add Mollie gateways
     *
     * @param array $gateways
     * @return array
     */
	public static function addGateways( array $gateways ) {

		$gateways = array_merge( $gateways, self::$GATEWAYS );

		// Return if function get_current_screen() is not defined
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $gateways;
		}

		// Try getting get_current_screen()
		$current_screen = get_current_screen();

		// Return if get_current_screen() isn't set
		if ( ! $current_screen ) {
			return $gateways;
		}

		// Remove old MisterCash (only) from WooCommerce Payment settings
		if ( is_admin() && ! empty( $current_screen->base ) && $current_screen->base == 'woocommerce_page_wc-settings' ) {
			if ( ( $key = array_search( 'Mollie_WC_Gateway_MisterCash', $gateways ) ) !== false ) {
				unset( $gateways[ $key ] );
			}
		}

		return $gateways;
	}

    /**
     * Disable Bank Transfer Gateway
     *
     * @param array $gateways
     * @return array
     */
    public static function maybeDisableBankTransferGateway(array $gateways)
    {
        $isWcApiRequest = (bool)filter_input(INPUT_GET, 'wc-api', FILTER_SANITIZE_STRING);
        $bankTransferSettings = get_option('mollie_wc_gateway_banktransfer_settings', false);
        $isSettingActivated = false;
        if($bankTransferSettings && isset($bankTransferSettings['activate_expiry_days_setting'])){
            $expiryDays = $bankTransferSettings['activate_expiry_days_setting'];
            $isSettingActivated = mollieWooCommerceStringToBoolOption($expiryDays);
        }

        /*
         * There is only one case where we want to filter the gateway and it's when the
         * pay-page render the available payments methods AND the setting is enabled
         *
         * For any other case we want to be sure bank transfer gateway is included.
         */
        if ($isWcApiRequest ||
            !$isSettingActivated ||
            is_checkout() && ! is_wc_endpoint_url( 'order-pay' )||
            !wp_doing_ajax() && ! is_wc_endpoint_url( 'order-pay' )||
            is_admin()
        ) {
            return $gateways;
        }
        $bankTransferGatewayClassName = Mollie_WC_Gateway_BankTransfer::class;
        $bankTransferGatewayIndex = array_search($bankTransferGatewayClassName, $gateways, true);
        if ($bankTransferGatewayIndex !== false) {
            unset($gateways[$bankTransferGatewayIndex]);
        }
        return  $gateways;
    }

    /**
     * Disable Apple Pay Gateway
     *
     * @param array $gateways
     * @return array
     */
    public static function maybeDisableApplePayGateway(array $gateways)
    {
        $isWcApiRequest = (bool)filter_input(INPUT_GET, 'wc-api', FILTER_SANITIZE_STRING);
        $wooCommerceSession = mollieWooCommerceSession();

        /*
         * There is only one case where we want to filter the gateway and it's when the checkout
         * page render the available payments methods.
         *
         * For any other case we want to be sure apple pay gateway is included.
         */
        if ($isWcApiRequest ||
            !$wooCommerceSession instanceof WC_Session ||
            !doing_action('woocommerce_payment_gateways') ||
            !wp_doing_ajax() && ! is_wc_endpoint_url( 'order-pay' )||
            is_admin()
        ) {
            return $gateways;
        }

        if ($wooCommerceSession->get(self::APPLE_PAY_METHOD_ALLOWED_KEY, false)) {
            return $gateways;
        }

        $applePayGatewayClassName = Mollie_WC_Gateway_Applepay::class;
        $applePayGatewayIndex = array_search($applePayGatewayClassName, $gateways, true);
        $postData = (string)filter_input(
            INPUT_POST,
            self::POST_DATA_KEY,
            FILTER_SANITIZE_STRING
        ) ?: '';
        parse_str($postData, $postData);

        $applePayAllowed = isset($postData[self::APPLE_PAY_METHOD_ALLOWED_KEY]) && $postData[self::APPLE_PAY_METHOD_ALLOWED_KEY];

        if ($applePayGatewayIndex !== false && !$applePayAllowed) {
            unset($gateways[$applePayGatewayIndex]);
        }

        if ($applePayGatewayIndex !== false && $applePayAllowed) {
            $wooCommerceSession->set(self::APPLE_PAY_METHOD_ALLOWED_KEY, true);
        }

        return $gateways;
    }

	/**
	 * Add a WooCommerce notification message
	 *
	 * @param string $message Notification message
	 * @param string $type    One of notice, error or success (default notice)
	 *
	 * @return $this
	 */
	public static function addNotice( $message, $type = 'notice' ) {
		$type = in_array( $type, array ( 'notice', 'error', 'success' ) ) ? $type : 'notice';

		// Check for existence of new notification api (WooCommerce >= 2.1)
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $type );
		} else {
			$woocommerce = WooCommerce::instance();

			switch ( $type ) {
				case 'error' :
					$woocommerce->add_error( $message );
					break;
				default :
					$woocommerce->add_message( $message );
					break;
			}
		}
	}

    /**
     * Log messages to WooCommerce log
     *
     * @param mixed $message
     * @param bool  $set_debug_header Set X-Mollie-Debug header (default false)
     */
    public static function debug ($message, $set_debug_header = false)
    {
        // Convert message to string
        if (!is_string($message))
        {
            $message = wc_print_r($message, true);
        }

        // Set debug header
        if ($set_debug_header && PHP_SAPI !== 'cli' && !headers_sent())
        {
            header("X-Mollie-Debug: $message");
        }

	    // Log message
	    if ( self::getSettingsHelper()->isDebugEnabled() ) {
            $logger = wc_get_logger();

            $context = array ( 'source' => self::PLUGIN_ID . '-' . date( 'Y-m-d' ) );

            $logger->debug( $message, $context );
	    }
    }

    /**
     * Get location of main plugin file
     *
     * @return string
     */
    public static function getPluginFile ()
    {
        return plugin_basename(self::PLUGIN_ID . '/' . self::PLUGIN_ID . '.php');
    }

    /**
     * Get plugin URL
     *
     * @param string $path
     * @return string
     */
    public static function getPluginUrl ($path = '')
    {
        return untrailingslashit(M4W_PLUGIN_URL) . '/' . ltrim($path, '/');
    }

    public static function getPluginPath($path = '')
    {
        return untrailingslashit(M4W_PLUGIN_DIR) . '/' . ltrim($path, '/');
    }

    /**
     * Add plugin action links
     * @param array $links
     * @return array
     */
    public static function addPluginActionLinks (array $links)
    {
        $action_links = array(
            // Add link to global Mollie settings
            '<a href="' . self::getSettingsHelper()->getGlobalSettingsUrl() . '">' . __('Mollie settings', 'mollie-payments-for-woocommerce') . '</a>',
        );


        // Add link to WooCommerce logs
        $action_links[] = '<a href="' . self::getSettingsHelper()->getLogsUrl()
                . '">' . __('Logs', 'mollie-payments-for-woocommerce') . '</a>';


        return array_merge($action_links, $links);
    }

    /**
     * @return Mollie_WC_Helper_Settings
     */
    public static function getSettingsHelper ()
    {
        static $settings_helper;

        if (!$settings_helper)
        {
            $settings_helper = new Mollie_WC_Helper_Settings();
        }

        return $settings_helper;
    }

    /**
     * @return Mollie_WC_Helper_Api
     */
    public static function getApiHelper ()
    {
        static $api_helper;

        if (!$api_helper)
        {
            $api_helper = new Mollie_WC_Helper_Api(self::getSettingsHelper());
        }

        return $api_helper;
    }

    /**
     * @return Mollie_WC_Helper_Data
     */
    public static function getDataHelper ()
    {
        static $data_helper;

        if (!$data_helper)
        {
            $data_helper = new Mollie_WC_Helper_Data(self::getApiHelper());
        }

        return $data_helper;
    }

    /**
     * @return Mollie_WC_Helper_Status
     */
    public static function getStatusHelper ()
    {
        static $status_helper;

        if (!$status_helper)
        {
            $status_helper = new Mollie_WC_Helper_Status(new CompatibilityChecker());
        }

        return $status_helper;
    }

	/**
	 * @return Mollie_WC_Helper_PaymentFactory
	 */
	public static function getPaymentFactoryHelper() {
		static $payment_helper;

		if ( ! $payment_helper ) {
			$payment_helper = new Mollie_WC_Helper_PaymentFactory();
		}

		return $payment_helper;

	}

	/**
	 * @return Mollie_WC_Payment_Object
	 */
	public static function getPaymentObject() {
		static $payment_parent;

		if ( ! $payment_parent ) {
			$payment_parent = new Mollie_WC_Payment_Object( null );
		}

		return $payment_parent;

	}

	/**
	 * @return Mollie_WC_Helper_OrderLines
	 */
	public static function getOrderLinesHelper ( $shop_country, WC_Order $order )
	{
		static $order_lines_helper;

		if (!$order_lines_helper)
		{

			$order_lines_helper = new Mollie_WC_Helper_OrderLines( $shop_country, $order );
		}

		return $order_lines_helper;
	}

	/**
	 * Ship all order lines and capture an order at Mollie.
	 *
	 */
	public static function shipAndCaptureOrderAtMollie( $order_id ) {

		$order = wc_get_order( $order_id );

		// Does WooCommerce order contain a Mollie payment?
		if ( strstr( $order->get_payment_method(), 'mollie_wc_gateway_') == FALSE ) {
			return;
		}

		// To disable automatic shipping and capturing of the Mollie order when a WooCommerce order status is updated to completed,
		// store an option 'mollie-payments-for-woocommerce_disableShipOrderAtMollie' with value 1
		if ( get_option(Mollie_WC_Plugin::PLUGIN_ID . '_' . 'disableShipOrderAtMollie', '0' ) == '1' ) {
			return;
		}

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Try to process completed order for a potential capture at Mollie.' );

		// Does WooCommerce order contain a Mollie Order?
        $mollie_order_id = ( $mollie_order_id = $order->get_meta( '_mollie_order_id', true ) ) ? $mollie_order_id : false;
        // Is it a payment? you cannot ship a payment
		if ( $mollie_order_id == false || substr($mollie_order_id,0,3) == 'tr_') {
			$order->add_order_note( 'Order contains Mollie payment method, but not a Mollie Order ID. Processing capture canceled.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order contains Mollie payment method, but not a Mollie Order ID. Processing capture cancelled.' );

			return;
		}

		// Is test mode enabled?
        $test_mode = mollieWooCommerceIsTestModeEnabled();

		try {
			// Get the order from the Mollie API
			$mollie_order = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id );

			// Check that order is Paid or Authorized and can be captured
			if ( $mollie_order->isCanceled() ) {
				$order->add_order_note( 'Order already canceled at Mollie, can not be shipped/captured.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order already canceled at Mollie, can not be shipped/captured.' );

				return;

			}

			if ( $mollie_order->isCompleted() ) {
				$order->add_order_note( 'Order already completed at Mollie, can not be shipped/captured.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order already completed at Mollie, can not be shipped/captured.' );

				return;

			}

			if ( $mollie_order->isPaid() || $mollie_order->isAuthorized() ) {
				Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id )->shipAll();
				$order->add_order_note( 'Order successfully updated to shipped at Mollie, capture of funds underway.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order successfully updated to shipped at Mollie, capture of funds underway.' );

				return;

			}

			$order->add_order_note( 'Order not paid or authorized at Mollie yet, can not be shipped.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order not paid or authorized at Mollie yet, can not be shipped.' );

		}
		catch ( Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Processing shipment & capture failed, error: ' . $e->getMessage() );
		}

		return;
	}


	/**
	 * Cancel an order at Mollie.
	 *
	 */
	public static function cancelOrderAtMollie( $order_id ) {

		$order = wc_get_order( $order_id );

		// Does WooCommerce order contain a Mollie payment?
		if ( strstr( $order->get_payment_method(), 'mollie_wc_gateway_') == FALSE ) {
			return;
		}

		// To disable automatic canceling of the Mollie order when a WooCommerce order status is updated to canceled,
		// store an option 'mollie-payments-for-woocommerce_disableCancelOrderAtMollie' with value 1
		if ( get_option(Mollie_WC_Plugin::PLUGIN_ID . '_' . 'disableCancelOrderAtMollie', '0' ) == '1' ) {
			return;
		}

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Try to process cancelled order at Mollie.' );

        $mollie_order_id = ( $mollie_order_id = $order->get_meta( '_mollie_order_id', true ) ) ? $mollie_order_id : false;

        if ( $mollie_order_id == false ) {
			$order->add_order_note( 'Order contains Mollie payment method, but not a valid Mollie Order ID. Canceling order failed.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order contains Mollie payment method, but not a valid Mollie Order ID. Canceling order failed.' );

			return;
		}

		// Is test mode enabled?
        $test_mode = mollieWooCommerceIsTestModeEnabled();

		try {
			// Get the order from the Mollie API
			$mollie_order = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id );

			// Check that order is not already canceled at Mollie
			if ( $mollie_order->isCanceled() ) {
				$order->add_order_note( 'Order already canceled at Mollie, can not be canceled again.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order already canceled at Mollie, can not be canceled again.' );

				return;

			}

			// Check that order has the correct status to be canceled
			if ( $mollie_order->isCreated() || $mollie_order->isAuthorized() || $mollie_order->isShipping() ) {
				Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id )->cancel();
				$order->add_order_note( 'Order also cancelled at Mollie.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order cancelled in WooCommerce, also cancelled at Mollie.' );

				return;

			}

			$order->add_order_note( 'Order could not be canceled at Mollie, because order status is ' . $mollie_order->status . '.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order could not be canceled at Mollie, because order status is ' . $mollie_order->status . '.' );

		}
		catch ( Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Updating order to canceled at Mollie failed, error: ' . $e->getMessage() );
		}

		return;
	}

	/**
	 * Don't show SEPA Direct Debit in WooCommerce Checkout
	 */
	public static function disableSEPAInCheckout( $available_gateways ) {

		if ( is_checkout() ) {
			unset( $available_gateways['mollie_wc_gateway_directdebit'] );
		}

		return $available_gateways;
	}

	/**
	 * Don't show old MisterCash in WooCommerce Checkout
	 */
	public static function disableMisterCashInCheckout( $available_gateways ) {

		if ( is_checkout() ) {
			unset( $available_gateways['mollie_wc_gateway_mistercash'] );
		}

		return $available_gateways;
	}

	/**
	 * Don't show Mollie Payment Methods in WooCommerce Account > Subscriptions
	 */
	public static function disableMollieOnPaymentMethodChange( $available_gateways ) {

		// Can't use $wp->request or is_wc_endpoint_url() to check if this code only runs on /subscriptions and /view-subscriptions,
		// because slugs/endpoints can be translated (with WPML) and other plugins.
		// So disabling on is_account_page (if not checkout, bug in WC) and $_GET['change_payment_method'] for now.

		// Only disable payment methods if WooCommerce Subscriptions is installed
		if ( class_exists( 'WC_Subscription' ) ) {
			// Do not disable if account page is also checkout (workaround for bug in WC), do disable on change payment method page (param)
			if ( ( ! is_checkout() && is_account_page() ) || ! empty( $_GET['change_payment_method'] ) ) {
				foreach ( $available_gateways as $key => $value ) {
					if ( strpos( $key, 'mollie_' ) !== false ) {
						unset( $available_gateways[ $key ] );
					}
				}
			}
		}

		return $available_gateways;
	}

	/**
	 * If an order is paid with another payment method (gateway) after a first payment was
	 * placed with Mollie, set a flag, so status updates (like expired) aren't processed by
	 * Mollie Payments for WooCommerce.
	 */
	public static function setOrderPaidByOtherGateway( $order_id ) {

		$order = wc_get_order( $order_id );

        $mollie_payment_id    = $order->get_meta( '_mollie_payment_id', $single = true );
        $order_payment_method = $order->get_payment_method();

        if ( $mollie_payment_id !== '' && ( strpos( $order_payment_method, 'mollie' ) === false ) ) {

            $order->update_meta_data( '_mollie_paid_by_other_gateway', '1' );
            $order->save();
        }

		return true;

	}

    private static function extractRemoteItemsIds(array $data)
    {
        if (empty($data['lines'])) {
            return [];
        }

        return implode(',', wp_list_pluck($data['lines'], 'id'));
    }
}

