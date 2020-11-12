<?php

use Mollie\Api\CompatibilityChecker;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;

class Mollie_WC_Plugin
{
    const PLUGIN_ID      = 'mollie-payments-for-woocommerce';
    const PLUGIN_TITLE   = 'Mollie Payments for WooCommerce';
    const PLUGIN_VERSION = '5.11.0';

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
        'Mollie_WC_Gateway_IngHomePay',
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
     *
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

		// Add global Mollie settings to 'WooCommerce -> Checkout -> Checkout Options'
		add_filter( 'woocommerce_payment_gateways_settings', array ( $settings_helper, 'addGlobalSettingsFields' ) );
        remove_filter('wp_kses_allowed_html', array ( $settings_helper, 'svgAllowedTags' ) , 10);

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

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . $plugin_basename, array ( __CLASS__, 'addPluginActionLinks' ) );

		// Listen to return URL call
		add_action( 'woocommerce_api_mollie_return', array ( __CLASS__, 'onMollieReturn' ) );

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
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueMealvoucherAssets']);
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
                $settings[] = new Mollie_WC_Settings_Page_Components();

                return $settings;
            }
        );
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

        add_filter( Mollie_WC_Plugin::PLUGIN_ID . '_retrieve_payment_gateways', function(){
            return self::$GATEWAYS;
        });
        add_action('wp_loaded', [__CLASS__, 'maybeTestModeNotice']);
        self::mollieApplePayDirectHandling();

		self::initDb();
		self::schedulePendingPaymentOrdersExpirationCheck();
        self::registerFrontendScripts();

		// Mark plugin initiated
		self::$initiated = true;
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
            $notice->addAdminNotice('notice-error', $message);
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
    /**
     * Contents of the Mollie options product tab.
     */
    public static function mollieOptionsProductTabContent()
    {
        ?>
        <div id='mollie_options' class='panel woocommerce_options_panel'><?php

        ?>
        <div class='options_group'><?php

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
                                Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY => 'No category',
                                Mollie_WC_Gateway_Mealvoucher::MEAL => 'Meal',
                                Mollie_WC_Gateway_Mealvoucher::ECO => 'Eco',
                                Mollie_WC_Gateway_Mealvoucher::GIFT => 'Gift'

                        ),
                        'default' => Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY,
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
     * Enqueues the ApplePay button scripts if enabled and in correct page
     */
    public static function enqueueApplePayDirectScripts()
    {
        if (mollieWooCommerceIsApplePayDirectEnabled() && is_product()) {
            $dataToScripts = new Mollie_WC_ApplePayButton_DataToAppleButtonScripts();
            wp_enqueue_style('mollie-applepaydirect');
            wp_enqueue_script('mollie_applepaydirect');
            wp_localize_script(
                'mollie_applepaydirect',
                'mollieApplePayDirectData',
                $dataToScripts->applePayScriptData()
            );
        }
        if (mollieWooCommerceIsApplePayDirectEnabled() && is_cart()) {
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
     * Serves the validation string required by Apple when the known url is called
     */
    public static function mollieApplePayDirectHandling()
    {
        if (mollieWooCommerceIsApplePayDirectEnabled()) {
            $requestUri = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL); //phpcs:ignore
            $validationPath = '/.well-known/apple-developer-merchantid-domain-association';
            if(strpos($requestUri, $validationPath) === 0){
                echo('7B227073704964223A2244394337463730314338433646324336463344363536433039393434453332323030423137364631353245353844393134304331433533414138323436453630222C2276657273696F6E223A312C22637265617465644F6E223A313535373438323935353137362C227369676E6174757265223A22333038303036303932613836343838366637306430313037303261303830333038303032303130313331306633303064303630393630383634383031363530333034303230313035303033303830303630393261383634383836663730643031303730313030303061303830333038323033653633303832303338626130303330323031303230323038363836306636393964396363613730663330306130363038326138363438636533643034303330323330376133313265333032633036303335353034303330633235343137303730366336353230343137303730366336393633363137343639366636653230343936653734363536373732363137343639366636653230343334313230326432303437333333313236333032343036303335353034306230633164343137303730366336353230343336353732373436393636363936333631373436393666366532303431373537343638366637323639373437393331313333303131303630333535303430613063306134313730373036633635323034393665363332653331306233303039303630333535303430363133303235353533333031653137306433313336333033363330333333313338333133363334333035613137306433323331333033363330333233313338333133363334333035613330363233313238333032363036303335353034303330633166363536333633326437333664373032643632373236663662363537323264373336393637366535663535343333343264353334313465343434323466353833313134333031323036303335353034306230633062363934663533323035333739373337343635366437333331313333303131303630333535303430613063306134313730373036633635323034393665363332653331306233303039303630333535303430363133303235353533333035393330313330363037326138363438636533643032303130363038326138363438636533643033303130373033343230303034383233306664616263333963663735653230326335306439396234353132653633376532613930316464366362336530623163643462353236373938663863663465626465383161323561386332316534633333646463653865326139366332663661666131393330333435633465383761343432366365393531623132393561333832303231313330383230323064333034353036303832623036303130353035303730313031303433393330333733303335303630383262303630313035303530373330303138363239363837343734373033613266326636663633373337303265363137303730366336353265363336663664326636663633373337303330333432643631373037303663363536313639363336313333333033323330316430363033353531643065303431363034313430323234333030623961656565643436333139376134613635613239396534323731383231633435333030633036303335353164313330313031666630343032333030303330316630363033353531643233303431383330313638303134323366323439633434663933653465663237653663346636323836633366613262626664326534623330383230313164303630333535316432303034383230313134333038323031313033303832303130633036303932613836343838366637363336343035303133303831666533303831633330363038326230363031303530353037303230323330383162363063383162333532363536633639363136653633363532303666366532303734363836393733323036333635373237343639363636393633363137343635323036323739323036313665373932303730363137323734373932303631373337333735366436353733323036313633363336353730373436313665363336353230366636363230373436383635323037343638363536653230363137303730366336393633363136323663363532303733373436313665363436313732363432303734363537323664373332303631366536343230363336663665363436393734363936663665373332303666363632303735373336353263323036333635373237343639363636393633363137343635323037303666366336393633373932303631366536343230363336353732373436393636363936333631373436393666366532303730373236313633373436393633363532303733373436313734363536643635366537343733326533303336303630383262303630313035303530373032303131363261363837343734373033613266326637373737373732653631373037303663363532653633366636643266363336353732373436393636363936333631373436353631373537343638366637323639373437393266333033343036303335353164316630343264333032623330323961303237613032353836323336383734373437303361326632663633373236633265363137303730366336353265363336663664326636313730373036633635363136393633363133333265363337323663333030653036303335353164306630313031666630343034303330323037383033303066303630393261383634383836663736333634303631643034303230353030333030613036303832613836343863653364303430333032303334393030333034363032323130306461316336336165386265356636346638653131653836353639333762396236396334373262653933656163333233336131363739333665346138643565383330323231303062643561666266383639663363306361323734623266646465346637313731353963623362643731393962326361306666343039646536353961383262323464333038323032656533303832303237356130303330323031303230323038343936643266626633613938646139373330306130363038326138363438636533643034303330323330363733313162333031393036303335353034303330633132343137303730366336353230353236663666373432303433343132303264323034373333333132363330323430363033353530343062306331643431373037303663363532303433363537323734363936363639363336313734363936663665323034313735373436383666373236393734373933313133333031313036303335353034306130633061343137303730366336353230343936653633326533313062333030393036303335353034303631333032353535333330316531373064333133343330333533303336333233333334333633333330356131373064333233393330333533303336333233333334333633333330356133303761333132653330326330363033353530343033306332353431373037303663363532303431373037303663363936333631373436393666366532303439366537343635363737323631373436393666366532303433343132303264323034373333333132363330323430363033353530343062306331643431373037303663363532303433363537323734363936363639363336313734363936663665323034313735373436383666373236393734373933313133333031313036303335353034306130633061343137303730366336353230343936653633326533313062333030393036303335353034303631333032353535333330353933303133303630373261383634386365336430323031303630383261383634386365336430333031303730333432303030346630313731313834313964373634383564353161356532353831303737366538383061326566646537626165346465303864666334623933653133333536643536363562333561653232643039373736306432323465376262613038666437363137636538386362373662623636373062656338653832393834666635343435613338316637333038316634333034363036303832623036303130353035303730313031303433613330333833303336303630383262303630313035303530373330303138363261363837343734373033613266326636663633373337303265363137303730366336353265363336663664326636663633373337303330333432643631373037303663363537323666366637343633363136373333333031643036303335353164306530343136303431343233663234396334346639336534656632376536633466363238366333666132626266643265346233303066303630333535316431333031303166663034303533303033303130316666333031663036303335353164323330343138333031363830313462626230646561313538333338383961613438613939646562656264656261666461636232346162333033373036303335353164316630343330333032653330326361303261613032383836323636383734373437303361326632663633373236633265363137303730366336353265363336663664326636313730373036633635373236663666373436333631363733333265363337323663333030653036303335353164306630313031666630343034303330323031303633303130303630613261383634383836663736333634303630323065303430323035303033303061303630383261383634386365336430343033303230333637303033303634303233303361636637323833353131363939623138366662333563333536636136326266663431376564643930663735346461323865626566313963383135653432623738396638393866373962353939663938643534313064386639646539633266653032333033323264643534343231623061333035373736633564663333383362393036376664313737633263323136643936346663363732363938323132366635346638376137643162393963623962303938393231363130363939306630393932316430303030333138323031386233303832303138373032303130313330383138363330376133313265333032633036303335353034303330633235343137303730366336353230343137303730366336393633363137343639366636653230343936653734363536373732363137343639366636653230343334313230326432303437333333313236333032343036303335353034306230633164343137303730366336353230343336353732373436393636363936333631373436393666366532303431373537343638366637323639373437393331313333303131303630333535303430613063306134313730373036633635323034393665363332653331306233303039303630333535303430363133303235353533303230383638363066363939643963636137306633303064303630393630383634383031363530333034303230313035303061303831393533303138303630393261383634383836663730643031303930333331306230363039326138363438383666373064303130373031333031633036303932613836343838366637306430313039303533313066313730643331333933303335333133303331333033303339333133353561333032613036303932613836343838366637306430313039333433313164333031623330306430363039363038363438303136353033303430323031303530306131306130363038326138363438636533643034303330323330326630363039326138363438383666373064303130393034333132323034323035613437363366643264396534366338346162356331346462383563633833663831303934316536323838306363663138636536376131613630656633356661333030613036303832613836343863653364303430333032303434363330343430323230363436636338323861383361333062353136313731323266633462333532386432373762373937646264333861633064396263643439393864633832303634383032323030366663656534646432316661313165653665353834346561393565643465643034323939636666363333656437623233343461383835613433636431613662303030303030303030303030227D');
                exit();
            }

            $notices = new Mollie_WC_Notice_AdminNotice();
            $responseTemplates = new Mollie_WC_ApplePayButton_ResponsesToApple();
            $ajaxRequests = new Mollie_WC_ApplePayButton_AjaxRequests( $responseTemplates);
            $applePayHandler = new Mollie_WC_Helper_ApplePayDirectHandler($notices, $ajaxRequests);
            $applePayHandler->bootstrap();
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
            'mollie_wc_gateway_mealvoucher',
            Mollie_WC_Plugin::getPluginUrl('/public/js/mealvoucher.min.js'),
            ['underscore', 'jquery'],
            filemtime(Mollie_WC_Plugin::getPluginPath('/public/js/mealvoucher.min.js')),
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

        wp_enqueue_script('mollie_wc_gateway_applepay');
        wp_enqueue_script('mollie_wc_gateway_mealvoucher');
        wp_enqueue_style('unabledButton');

    }

    public static function enqueueMealvoucherAssets()
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        $enableButtonHelper = new Mollie_WC_Helper_MaybeDisableGateway();
        wp_localize_script(
                'mollie_wc_gateway_mealvoucher',
                'mealvoucherSettings',
                [
                        'message'=> __('Some products in the cart cannot be purchased with the selected gateway. Please, select another gateway'),
                        'productsWithCategory' => $enableButtonHelper->numberProductsWithCategory()

                ]
        );
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
     * Payment return url callback
     */
    public static function onMollieReturn ()
    {
        $dataHelper = mollieWooCommerceGetDataHelper();

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

