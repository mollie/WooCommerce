=== Mollie Payments for WooCommerce ===
Contributors: daanvm, davdebcom, l.vangunst, ndijkstra, robin-mollie
Tags: mollie, payments, woocommerce, payment gateway, e-commerce, credit card, ideal, sofort, bancontact, bitcoin, direct debit, subscriptions
Requires at least: 3.8
Tested up to: 5.0
Stable tag: 5.0.7
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept all major payment methods in WooCommerce today. Credit cards, iDEAL, bitcoin and more! Fast, safe and intuitive.

== Description ==

Quickly integrate all major payment methods in WooCommerce, wherever you need them. Simply drop them ready-made into your WooCommerce webshop with this powerful plugin by Mollie. Mollie is dedicated to making payments better for WooCommerce.

> Next level payments, for WooCommerce

No need to spend weeks on paperwork or security compliance procedures. No more lost conversions because you don’t support a shopper’s favorite payment method or because they don’t feel safe. We made payments intuitive and safe for merchants and their customers.

= PAYMENT METHODS =

Credit cards:

* VISA (International)
* MasterCard (International)
* American Express (International)
* Cartes Bancaires (France)
* CartaSi (Italy)

Debit cards:

* V Pay (International)
* Maestro (International)

European and local payment methods:

* iDEAL (Netherlands)
* Bancontact (Belgium)
* ING Home'Pay (Belgium)
* Giropay (Germany)
* EPS (Austria)
* SOFORTbanking (EU)
* Belfius (Belgium)
* KBC/CBC payment button (Belgium)
* SEPA - Credit Transfer (EU)
* SEPA - Direct Debit (EU)
* Gift cards (Netherlands)

International payment methods:

* PayPal (International)
* Bitcoin (International)
* Paysafecard (International)

Pay after delivery payment methods:

* Klarna Pay later (Netherlands, Germany, Austria, Finland)
* Klarna Slice it (Germany, Austria, Finland)

Please go to the [signup page](https://www.mollie.com/signup) to create a new Mollie account and start receiving payments in a couple of minutes. Contact info@mollie.com if you have any questions or comments about this plugin.

> Our pricing is always per transaction. No startup fees, no monthly fees, and no gateway fees. No hidden fees, period.

= FEATURES =

* Support for all available Mollie payment methods
* Compatible with WooCommerce Subscriptions for recurring payments (credit card, iDEAL, SEPA Direct Debit and more)
* Transparent pricing. No startup fees, no monthly fees, and no gateway fees. No hidden fees, period.
* Edit the order, title and description of every payment method in WooCommerce checkout
* Support for full and partial payment refunds (WooCommerce 2.2+)
* Configurable pay outs: daily, weekly, monthly - whatever you prefer
* [Powerful dashboard](https://www.mollie.com/en/features/dashboard) on mollie.com to easily keep track of your payments.
* Fast in-house support. You will always be helped by someone who knows our products intimately.
* Multiple translations: English, Dutch, German and French
* Event log for debugging purposes
* WordPress Multisite support
* WPML support

== Frequently Asked Questions ==

= I can't install the plugin, the plugin is displayed incorrectly =

Please temporarily enable the [WordPress Debug Mode](https://codex.wordpress.org/Debugging_in_WordPress). Edit your `wp-config.php` and set the constants `WP_DEBUG` and `WP_DEBUG_LOG` to `true` and try
it again. When the plugin triggers an error, WordPress will log the error to the log file `/wp-content/debug.log`. Please check this file for errors. When done, don't forget to turn off
the WordPress debug mode by setting the two constants `WP_DEBUG` and `WP_DEBUG_LOG` back to `false`.

= I get a white screen when opening ... =

Most of the time a white screen means a PHP error. Because PHP won't show error messages on default for security reasons, the page is white. Please turn on the WordPress Debug Mode to turn on PHP error messages (see previous answer).

= The Mollie payment gateways aren't displayed in my checkout =

* Please go to WooCommerce -> Settings -> Payments in your WordPress admin and scroll down to the Mollie settings section.
* Check which payment gateways are disabled.
* Go to the specific payment gateway settings page to find out why the payment gateway is disabled.

= The order status is not getting updated after successfully completing the payment =

* Please check the Mollie log file located in `/wp-content/uploads/wc-logs/` or `/wp-content/plugin/woocommerce/logs` for debug info. Please search for the correct order number and check if Mollie has called the shop Webhook to report the payment status.
* Do you have maintenance mode enabled? Please make sure to whitelist the 'wc-api' endpoint otherwise Mollie can't report the payment status to your website.
* Please check your Mollie dashboard to check if there are failed webhook reports. Mollie tried to report the payment status to your website but something went wrong.
* Contact info@mollie.com with your Mollie partner ID and the order number. We can investigate the specific payment and check whether Mollie successfully reported the payment state to your webshop.

= Payment gateways and mails aren't always translated =

This plugin uses [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/mollie-payments-for-woocommerce) for translations. WordPress will automatically add those translations to your website if they hit 100% completion at least once. If you are not seeing the Mollie plugin as translated on your website, the plugin is probably not translated (completely) into your language (you can view the status on the above URL).

You can either download and use the incomplete translations or help us get the translation to 100% by translating it.

To download translations manually:
1. Go to [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/mollie-payments-for-woocommerce/)
2. Click on the percentage in the "Stable" column for your language.
3. Scroll down to "Export". 
4. Choose "All current" and "MO - Machine Object" 
5. Upload this file to plugins/languages/mollie-payments-for-woocommerce/.
6. Repeat this for all your translations.

If you want to help translate the plugin, read the instructions in the [Translate strings instructions](https://make.wordpress.org/polyglots/handbook/tools/glotpress-translate-wordpress-org/#translating-strings).

= Can I add payment fees to payment methods? =

Yes, you can with a separate plugin. At the moment we have tested and can recommend [Payment Gateway Based Fees and Discounts for WooCommerce](https://wordpress.org/plugins/checkout-fees-for-woocommerce/). Other plugins might also work. For more specific information, also see [helpful snippets](https://github.com/mollie/WooCommerce/wiki/Helpful-snippets#add-payment-fee-to-payment-methods).

= Can I set up payment methods to show based on customers country? =

Yes, you can with a separate plugin. At the moment we have tested and can recommend [WooCommerce - Country Based Payments](https://wordpress.org/plugins/woocommerce-country-based-payments/). Other plugins might also work.

= Why do orders with payment method BankTransfer and Direct Debit get the status 'on-hold'? =

These payment methods take longer than a few hours to complete. The order status is set to 'on-hold' to prevent the WooCommerce setting 'Hold stock (minutes)' (https://docs.woothemes.com/document/configuring-woocommerce-settings/#inventory-options) will 
cancel the order. The order stock is also reduced to reserve stock for these orders. The stock is restored if the payment fails or is cancelled. You can change the initial order status for these payment methods on their setting page.

= I have a different question about this plugin =

Please contact info@mollie.com with your Mollie partner ID, please describe your problem as detailed as possible. Include screenshots where appropriate.
Where possible, also include the Mollie log file. You can find the Mollie log files in `/wp-content/uploads/wc-logs/` or `/wp-content/plugin/woocommerce/logs`.

== Screenshots ==

1. The global Mollie settings are used by all the payment gateways. Please insert your Mollie API key to start.
2. Change the title and description for every payment gateway. Some gateways have special options.
3. The available payment gateways in the checkout.
4. The order received page will display the payment status and customer details if available.
5. The order received page for the gateway bank transfer will display payment instructions.
6. Some payment methods support refunds. The 'Refund' button will be available when the payment method supports refunds.
7. Within Mollie Dashboard, intuitive design meets clever engineering, allowing you to get more work done, in less time.
8. Also in Mollie Dashboard, get your administration done quick. You’ll have a detailed overview of your current balance.
9. Statistics with a double graph gives gives you extensive insights and data on how your business is performing.
10. Mollie Checkout turns a standard payment form into a professional experience that drives conversions.

== Installation ==

= Minimum Requirements =

* PHP version 5.6 or greater
* PHP extensions enabled: cURL, JSON
* WordPress 3.8 or greater
* WooCommerce 2.2.0 or greater

= Automatic installation =

1. Install the plugin via Plugins -> New plugin. Search for 'Mollie Payments for WooCommerce'.
2. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
3. Set your Mollie API key at WooCommerce -> Settings -> Payments (or use the *Mollie Settings* link in the Plugins overview)
4. You're done, the active payment methods should be visible in the checkout of your webshop.

= Manual installation =

1. Unpack the download package
2. Upload the directory 'mollie-payments-for-woocommerce' to the `/wp-content/plugins/` directory
3. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
4. Set your Mollie API key at WooCommerce -> Settings -> Payments (or use the *Mollie Settings* link in the Plugins overview)
5. You're done, the active payment methods should be visible in the checkout of your webshop.

Please contact info@mollie.com if you need help installing the Mollie WooCommerce plugin. Please provide your Mollie partner ID and website URL.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 5.0.7 - 04-12-2018 =

* Fix - Bancontact payments don't return a name as part of IBAN details (in Mollie API), so in that case use the WooCommerce name
* Fix - WooCommerce 2.6 and older: use get_country instead of get_billing_country
* Fix - Remove calls to delete payment mode, renewal payments can't use a different mode anyway, mandates aren't shared between modes
* Fix - Subscription renewal payments: if subscription does not contain the payment mode, try getting it from the parent order
* Fix - For shipping details use !empty() instead of isset
* Fix - Further improve restore_mollie_customer_id so it catches more edge-cases (and rename to restore_mollie_customer_id_and_mandate)
* Fix - Remove delete meta calls for meta that wasn't used anywhere (_mollie_card_4_digits)

= 5.0.6 - 23-11-2018 =

* Fix - Set subscription to On-Hold if renewal order gets a charge-back, add action hooks after refunds and chargebacks
* Fix - Update translation function call

= 5.0.5 - 22-11-2018 =

* Fix - WooCommerce Subscriptions: improve support for options "Accept Manual Renewals" and "Turn off Automatic Payments"
* Fix - Update Refunds and Chargebacks processing to support Orders and Payments API
* Fix - Remove option to set a description for payments, the new Orders API does not support this
* Fix - Update is_available to use billing country, and add filter Mollie_WC_Plugin::PLUGIN_ID . '_is_available_billing_country_for_payment_gateways'
* Fix - Add new hook after renewal payment is created: mollie-payments-for-woocommerce_after_renewal_payment_created
* Fix - Improve warnings in WooCommerce > Settings > Payments so they are less confusing to users (and really dismissable)
* Fix - Simplify log messages in shipAndCaptureOrderAtMollie, cancelOrderAtMollie

= 5.0.4 - 08-11-2018 =

* Fix - Get test mode higher in scheduled_subscription_payment() process
* Fix - Add automated option to restore a customer ID from Mollie API
* Fix - Store sequenceType in the correct position for payments


= 5.0.3 - 01-11-2018 =

* Fix - Improvements to refunds: better log messages, show errors to shop-manager
* Fix - Remove option to set a description for payments, the new Orders API does not support this
* Fix - Update WooCommerce status constants in our plugin (cancelled and refunded)
* Fix - Make sure customer ID is stored by getting the payment object with all payments embedded
* Fix - Get and use correct _mollie_payment_id in setActiveMolliePaymentForOrders and setActiveMolliePaymentForSubscriptions
* Fix - Move adding of sequenceType into payment object
* Fix - Update Webship Giftcard logo to webshopgiftcard.svg

* Fix - Klarna/Orders API: Also send Address Line 2 to Mollie Orders API
* Fix - Klarna/Orders API: In billing and shipping address check that fields aren't just a space
* Fix - Klarna/Orders API: Decode HTML entities in product names before sending them to Mollie
* Fix - Klarna/Orders API: Don't fall back to Payments API if payment method is Klarna
* Fix - Klarna/Orders API: Only add shipping address if all required fields are present
* Fix - Klarna/Orders API: Always store Mollie order ID as _mollie_order_id
* Fix - Klarna/Orders API: Add fallback to getActiveMolliePayment and try to get payment ID from Mollie order if possible

= 5.0.2 - 11-10-2018 =

IMPORTANT
This version requires PHP 5.6 or higher. If you are using an older PHP version, please read this article: [PHP & Mollie API v2](https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2).

* New - Now supports [Klarna Pay later](https://www.mollie.com/en/payments/klarna-pay-later) and [Klara Slice it](https://www.mollie.com/en/payments/klarna-slice-it), [read more](https://www.mollie.com/en/news/post/mollie-partners-with-klarna-for-maximum-payment-flexibility)
* New - Implemented support for the new Orders API
* New - Tested with and updated for WooCommerce 3.5 beta compatibility
* New - EPS, GiroPay: implemented support for SEPA first payments (recurring payments)

* Fix - Fixed for "Uncaught Error: Call to a member function isOpen() on null"
* Fix - Fixed issue with Guzzle and PhpScoper

* Fix - WooCommerce emails: make sure "Payment completed by..." message is only shown once per email
* Fix - WooCommerce Subscriptions: add support for "Accept Manual Renewals". This enables Bank Transfer and PayPal in checkout for subscription products.
* Fix - Mollie payment gateways weren't always shows when cart was empty.
* Fix - Fix for "Link expired" message after refresh methods in WooCommerce settings
* Fix - Stricter check for valid API key when individual gateways are loaded
* Fix - Added new action hook in Mollie_WC_Gateway_Abstract::getReturnRedirectUrlForOrder()
* Fix - Improve log messages for orderNeedsPayment check, old messages where confusing to merchants
* Fix - Update VVV giftcard logo filename

= 4.0.2 - 07-08-2018 =

* Fix - Reverted to older version of Mollie API PHP client, as it caused more issues than it fixed. This means conflicts with other plugins that use Guzzle are still possible. Use Mollie Payments For WooCommerce 3.0.6 if you also use plugins Klarna, Simple Locator, Cardinity, LeadPages, ConstantContact until we can provide a solution. If you experience issues, please contact us. [Please review this article.](https://github.com/mollie/WooCommerce/wiki/Composer-Guzzle-conflicts)

= 4.0.1 - 06-08-2018 =

IMPORTANT
Version 4.0 requires PHP 5.6 or higher. If you are using an older PHP version, please read this article: [PHP & Mollie API v2](https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2).

* New - [Multicurrency support for WooCommerce added](https://www.mollie.com/en/features/multicurrency/)
* New - [New payment methods EPS and GiroPay added](https://www.mollie.com/en/news/post/introducing-two-new-payment-methods-eps-and-giropay)
* New - Updated payment method logo's (better quality SVG's)
* New - Updated Mollie API PHP to 2.0.10

* New - Add support for failed regular payments (already had support for failed renewal payments)
* New - In WooCommerce order edit view, add direct link to payment in Mollie Dashboard
* New - Add notice to use bank transfer via Mollie, not default BACS gateway
* New - Add support for new refunds and chargebacks processing (that are initiated in Mollie Dashboard)

* Fix - Guzzle conflicts with other plugins solved (Klarna, Simple Locator, Cardinity, LeadPages, ConstantContact)
* Fix - "cURL error 60" fixed by including valid cacert.pem file
* Fix - Make sure getting the shop currency is also possible on WooCommerce 2.6 or older
* Fix - Fix "Fatal error: Uncaught exception 'Exception' with message 'Serialization of 'Closure' is not allowed' in " by adding try/catch blocks for serialize() for the rare cases where __sleep() isn't found in PHP
* Fix - Check that a locale (language code) is supported by Mollie before trying to create a payment
* Fix - "Couldn't create * payment", when other plugins (like WPML) use another locale format then the Mollie API (ISO 15897)
* Fix - "Couldn't create * payment", temporarily disable sending the address details to Mollie for fraud detection, payments not allowed if one of the fields is missing
* Fix - "Call to undefined function get_current_screen()" that can happen on some screens

= 3.0.6 - 21/06/2018 =

IMPORTANT
Starting with version 4.0, this plugin will require PHP 5.6. If you are using an older version, please read this article: [PHP & Mollie API v2](https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2). We expect to launch version 4.0 in June 2018.

* Fix - Remove a remove_action() call that blocked the plugin from running on PHP versions below PHP 5.6
* Fix - Added more log messages to onWebhookPaid

= 3.0.5 - 18/06/2018 =

IMPORTANT
Starting with version 4.0, this plugin will require PHP 5.6. If you are using an older version, please read this article: [PHP & Mollie API v2](https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2). We expect to launch version 4.0 in June 2018.

* Add warning that version 4.0 will require PHP 5.6
* Update 'Required PHP' tag to PHP 5.6
* Removes fatal error for thank you page without valid order (Issue #212 by NielsdeBlaauw)

= 3.0.4 - 24/05/2018 =

* Fix - Limit order status update for cancelled and expired payments if another non-Mollie payment gateway also started payment processing (and is active) for that order, prevents expired and cancelled Mollie payments from cancelling the order
* Fix - Webhook URL's with double slashes, caused by some multilanguage plugins (Polylang etc)
* Fix - Add extra condition to make sure customers with paid payments are redirected to correct URL after payment
* Fix - Incorrect return page after payment for some orders, fix was to get payment without cache at least once on return URL (in case Webhook Url is still processing)

= 3.0.3 - 14/05/2018 =

* Note - If you use Polylang or another multilanguage plugin, read this [FAQ item](https://github.com/mollie/WooCommerce/wiki/Common-issues#issues-with-polylang-or-other-multilanguage-plugins)!
* Fix - Polylang: Received all versions of Polylang from Frederic, made sure our integration works with all combinations

* Fix - Order confirmation/Thank you page ([issue #206](https://github.com/mollie/WooCommerce/issues/206)):
    * Show pending payment message for open and pending payments, not just open
    * Show payment instructions and pending payment message in WooCommerce notice style, so shop-customers notice it better
    * Make sure pending payment message is also shown for creditcard, PayPal and Sofort payments
* Fix - Redirect to checkout payment URL (retry payment) more often, also for failed payments

= 3.0.2 - 07/05/2018 =

* New - Add extra log message "Start process_payment for order ..."
* Fix - Fix "Uncaught Error: Call to undefined function wcs_order_contains_renewal()" when users don't have WooCommerce Subscriptions installed
* Fix - Improve condition(s) for disableMollieOnPaymentMethodChange, make sure not to disable payment methods on checkout (because of is_account_page() false positives, bug in WooCommerce)
* Fix - Improve is_available() check for minimum/maximum amounts, better check renewal payment amounts

= 3.0.1 - 17/04/2018 =

* [Fix/Revert, see issue 173](https://github.com/mollie/WooCommerce/issues/173) - Improve support for Polylang option "Hide URL language information for default language" in webhook and return URLs

= 3.0.0 - 17/04/2018 =

* New - WooCommerce Subscriptions: add support for 'subscription_payment_method_change', shop-customers can change payment method if renewal payment fails (SEPA incasso, credit card)
* New - WooCommerce Subscriptions: disable Mollie payment methods on shop-customer's my account page for "Payment method change", keep it enabled for "Pay now" link in emails
* New - WooCommerce Subscriptions: improve handling and update messages and notices for Subscription switch to better explain what's happening
* New - WooCommerce Subscriptions: set renewal orders and subscriptions to 'On-Hold' if renewal payment fails

* Fix - Fallback for getUserMollieCustomerId, get Mollie Customer ID from recent subscription if it's empty in WordPress user meta
* Fix - Improve support for Polylang option "Hide URL language information for default language" in webhook and return URLs
* Fix - Only check if customer ID is valid on current API key if there is a customer ID (not empty)(and improve log messages)
* Fix - Make sure payment instructions (Bank Transfer) are styled the same as WooCommerce content (Order received, payment pending)
* Fix - Don't update/process/expire Mollie payments on WooCommerce orders that have been paid with other payment gateways
* Fix - Updated text strings for Bancontact/Mister Cash to just Bancontact
* Fix - Use the exact same translation as WooCommerce for order statuses
* Fix - Resolve error (fatal error get_payment_method()) that occurred when users made certain custom changes to the WooCommerce template files
* Fix - Add order note and log message when customer returns to the site but payment is open/pending
* Fix - Improved order note for charged back renewal payments

= 2.9.0 - 13/02/2018 =

* New - Added support for new payment method: [ING Home'Pay](https://www.mollie.com/en/payments/ing-homepay)
* New - Updated Mollie API PHP to 1.9.6 (ING Home'Pay support)

* Fix - Check that Mollie customerID is known on current API key, solves issues for admins that switched from test to live
* Fix - Charged back payments now update the order status to On Hold and add an order note in WooCommerce, stock is not updated
* Fix - For 'Payment screen language' set default to 'Detect using browser language' as it is usually more accurate
* Fix - For subscriptions also compare recurring total amount to payment method maximums, not only the order/cart total
* Fix - Improve WPML compatibility by removing duplicate trailing slash in WooCommerce API request URL

= 2.8.2 - 15/01/2018 =

* Fix - Fixed a PHP error by setting an argument default for onOrderReceivedTitle(), because post ID not set in all WordPress versions

= 2.8.1 - 15/01/2018 =

* New - iDEAL, KBC, Gift cards: Option to hide issuers/bank list in WooCommerce
* New - Allow subscription switching (downgrade) when amount to pay is €0 and there is a valid mandate for the user

* Fix - A new customerID was created for every new order where a payment method that supported recurring payments was selected
* Fix - When plugin 2.8.0 was used with WooCommerce 2.6 or older, a fatal error would be shown on the return page (because of use of new WooCommerce 3.0 method)
* Fix - Some cancelled payments for cancelled orders where redirected to "Retry payment" instead of "Order received" page, see Github issue #166

= 2.8.0 - 09/01/2018 =

* New - Updated required WooCommerce version to 2.2.0
* New - Tested with WooCommerce 3.3 beta, no issues found
* New - Better message on "Order Received" page for open/pending payments
* New - Allow users to set the order status for orders where a payment was cancelled
* New - Added support for Polylang Pro (polylang-pro) to getSiteUrlWithLanguage()
* New - Updated credit card icon in WooCommerce checkout to show icons for MasterCard, Visa, AMEX, CartaSi, Cartes Bancaires
* New - Better way to check if WooCommerce is activated and has correct version (so plugin doesn't de-activate on WooCommerce updates)
* New - Redact customer IBAN in notification-mails
* New - Update how "Select your bank" is shown in the dropdown for iDEAL and KBC/CBC (and show a default)

* Fix - Fix error by making sure order is not removed/exists (in checkPendingPaymentOrdersExpiration)
* Fix - Make sure payments cancelled at Mollie are also cancelled in WooCommerce, so customers can select a new payment method
* Fix - KBC/CBC: Make sure KBC/CBC is listed as "Automatic Recurring Payment" gateway in WooCommerce
* Fix - Fix (no title) showing in settings for SEPA Direct Debit for some new installs
* Fix - Fix wrong date formatting shown for bank transfer instructions, thank you profoX!
* Fix - Typo in SEPA Direct Debit description, thank you Yame-!
* Fix - It's possible to set the initial status of bank transfer to pending instead of on-hold, but in that case the payment instructions would not be shown on the Order Received page (missing in condition)
* Fix - Make sure webhook processing for Paid doesn't run on status PaidOut
* Fix - Improve orderNeedsPayment so there are less false-positives if users use 3PD plugins to change the order status too early
* Fix - Add WC_Subscriptions_Manager::activate_subscriptions_for_order to make sure subscriptions are always activated when payment is paid, independent of order status

= 2.7.0 - 07/09/2017 =

* New - Support for gift cards! See: https://www.mollie.com/en/blog/post/mollie-launches-gift-cards/
* New - Also show issuers (banks) for KBC payment method

* Fix - Add better support for WooCommerce Deposits (by Webtomizer)
* Fix - Subscriptions would be set to 'On Hold' during SEPA Direct Debit payments, those subscriptions are now set to 'Active'
* Fix - Multiple issues that occurred when users had multiple (unpaid) payments per order
* Fix - Remove SEPA Direct Debit (only used for Mollie recurring) as visible gateway in checkout and settings
* Fix - Tested with WordPress 4.9 Alpha and WooCommerce 3.1
* Fix - Remove existing language files from plugin so they can be managed via https://translate.wordpress.org/projects/wp-plugins/mollie-payments-for-woocommerce
* Fix - Use better customer name when name is sent to Mollie (use full name and last name if available)
* Fix - Don't update orders to cancelled status for expired payments if there are still pending payments for same order
* Fix - Show correct return page to customer when they have placed multiple payments for single order
* Fix - For subscription renewal orders, update payment method (from iDEAL, Belfius etc) to SEPA Direct Debit when needed
* Fix - Add message that SEPA Direct Debit is required when using WooCommerce Subscriptions with iDEAL

* Dev - Stop checking change of payment methods with isValidPaymentMethod
* Dev - Add support for new WooCommerce version check
* Dev - In setActiveMolliePayment use update_post_meta so payment is always updated to latest
* Dev - In unsetActiveMolliePayment, a payment calling that function should only be able to unset itself
* Dev - Improve log messages (WooCommerce > System status > Logs > mollie-payments-for-woocommerce)
* Dev - Security improvement: sanitize getting ID's via POST and use $_POST instead of $_REQUEST
* Dev - Only show "Check Subscription Status" tool if WooCommerce Subscriptions is installed
* Dev - Fix PHP warnings about unserialize() by using serialize() before storing object as transient
* Dev - Move load_plugin_textdomain to own function and load on plugins_loaded action

= 2.6.0 - 07/06/2017 =
* Add support for WooCommerce 3.0 (backwards compatible with older versions of WooCommerce)
* The expiry date that's shown for payments via Bank transfer is now in the correct (translated) format
* Fix redundant "DESCRIBE *__mollie_pending_payment" error (on new installs)
* WooCommerce Subscriptions:
    * Important: added Subscription Status tool to fix broken subscriptions, see [instructions](https://github.com/mollie/WooCommerce/wiki/Mollie-Subscriptions-Status)
    * SEPA recurring payments, take initial order status from settings, default On-Hold (instead of Completed)
    * Fix issue where valid subscriptions are set to 'on-hold' and 'manual renewal' only 15 days after renewal payment is created (now only do that after 21 days)
    * Improve "Subscription switching" support to also allow amount changes
    * Fix typo in recurring payment order notes

= 2.5.5 - 31/03/2017 =
* Allow the option name to have maximum 191 characters for newer WooPress installations.

= 2.5.4 - 07/03/2017 =
* Added an option to disable storing the customer details at Mollie

= 2.5.3 - 01/03/2017 =
* Bugfix for crashing WooPress when using PHP version 5.3 or lower

= 2.5.2 - 28/02/2017 =
* The plugin is now compatible with WooCommerce Subscriptions for recurring payments
* Removed 'test mode enabled' description, which causes problems when using WPML
* Empty the cart when the order is finished, rather than when the payment is created

= 2.4.1 - 10/10/2016 =
* Fix 2.4.0 release (https://wordpress.org/support/topic/error-500-after-updating)

= 2.4.0 - 10/10/2016 =
* Add KBC/CBC Payment Button method.
* Add option to the iDEAL gateway to set the text for the empty option in the issuers drop down. Go to the iDEAL gateway settings to change this value.
* Update Mollie API client to v1.7.1.

= 2.3.1 - 14/09/2016 =
* Turn on 'mail payment instructions' for Bank Transfer by default
* Fix bug to support Polylang plugin

= 2.3.0 - 27/07/2016 =
* Update payment method icons.
* Send the refund description to Mollie. The refund description will be visible for your customer on their bank statement.
* Add new filters `mollie-payments-for-woocommerce_order_status_cancelled` and `mollie-payments-for-woocommerce_order_status_expired` to be able 
to overwrite the order status for cancelled and expired Mollie payments. You can find all available filters on https://github.com/mollie/WooCommerce/tree/master/development.
* Update Mollie API client to v1.6.5.

= 2.2.1 - 18/04/2016 =
* Add option for the Bank Transfer gateway to skip redirecting your users to the Mollie payment screen. Instead directly redirect to the WooCommerce order 
received page where payment instruction will be displayed. You can turn on this option on the Mollie Bank Transfer setting page: 
WooCommerce -> Settings -> Payments -> Mollie - Bank Transfer.

= 2.2.0 - 29/03/2016 =
* Add integration with Mollie Customers API.
* Use shorter transient prefix.
* Update Mollie API client to v1.4.1.

= 2.1.1 - 27/01/2016 =
* Add better support for translation plugins Polylang and mLanguage.
* Fixed small issue for PHP 5.2 users.

= 2.1.0 - 01/12/2015 =
* For payment methods where the payment status will be delivered after a couple of days you can set the initial order status. Choose between `on-hold` or `pending`.
* Get the correct current locale (with support for [WPML](https://wpml.org)).
* Cache payment methods and issuers by locale.
* Cancel order when payment is expired.
* Reduce order when initial order status is `on-hold`. Restore order stock when payment fails.
* Hide payment gateway when cart exceeds method min / max amount. Method min / max amount is returned by Mollie API.
* Add filter to change the return URL.

= 2.0.1 - 02/10/2015 =
* Add support for SEPA Direct Debit.
* Add message for Belfius, Bitcoin, Bancontact and paysafecard when the payment is paid successfully.

= 2.0.0 - 17/08/2015 =
* Complete rewrite of our WooCommerce plugin to better follow WordPress and WooCommerce standards and add better support for other plugins.

== Upgrade Notice ==

= 2.5.2 =
Our plugin is now compatible with WooCommerce Subscriptions for recurring payments.

= 2.0.0 =
* The 2.x version of the plugin uses a different plugin name. You can still run version 1.x of our plugin if you want to temporary
keep support for payments created using version 1.x. Hide the old payment gateways by disabling the old 'Mollie Payment Module' payment gateway in WooCommerce -> Settings -> Payments.
