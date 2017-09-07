=== Mollie Payments for WooCommerce ===
Contributors: daanvm, davdebcom, l.vangunst, ndijkstra, robin-mollie
Tags: mollie, payments, woocommerce, payment gateway, e-commerce, credit card, ideal, sofort, bancontact, bitcoin, direct debit, subscriptions
Requires at least: 3.8
Tested up to: 4.9
Requires PHP: 5.3
Stable tag: 2.7.0
Requires PHP: 5.3
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

Alternative payment methods:

* iDEAL (Netherlands)
* Bancontact (Belgium)
* PayPal (International)
* SOFORTbanking (EU)
* Belfius (Belgium)
* KBC/CBC payment button (Belgium)
* SEPA - Credit Transfer (EU)
* SEPA - Direct Debit (EU)
* Bitcoin (International)
* Paysafecard (International)
* Gift cards (Netherlands)

Please go to the [signup page](https://www.mollie.com/signup) to create a new Mollie account and start receiving payments in a couple of minutes. Contact info@mollie.com if you have any questions or comments about this plugin.

> Our pricing is always per transaction. No startup fees, no monthly fees, and no gateway fees. No hidden fees, period.

= FEATURES =

* Support for all available Mollie payment methods
* Compatible with WooCommerce Subscriptions for recurring payments (credit card, iDEAL, SEPA Direct Debit)
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

* Please go to WooCommerce -> Settings -> Checkout in your WordPress admin and scroll down to the Mollie settings section.
* Check which payment gateways are disabled.
* Go to the specific payment gateway settings page to find out why the payment gateway is disabled.

= The order status is not getting updated after successfully completing the payment =

* Please check the Mollie log file located in `/wp-content/uploads/wc-logs/` or `/wp-content/plugin/woocommerce/logs` for debug info. Please search for the correct order number and check if Mollie has called the shop Webhook to report the payment status.
* Do you have maintenance mode enabled? Please make sure to whitelist the 'wc-api' endpoint otherwise Mollie can't report the payment status to your website.
* Please check your Mollie dashboard to check if there are failed webhook reports. Mollie tried to report the payment status to your website but something went wrong.
* Contact info@mollie.com with your Mollie partner ID and the order number. We can investigate the specific payment and check whether Mollie successfully reported the payment state to your webshop.

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

== Installation ==

= Minimum Requirements =

* PHP version 5.3 or greater
* PHP extensions enabled: cURL, JSON
* WordPress 3.8 or greater
* WooCommerce 2.1.0 or greater

= Automatic installation =

1. Install the plugin via Plugins -> New plugin. Search for 'Mollie Payments for WooCommerce'.
2. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
3. Set your Mollie API key at WooCommerce -> Settings -> Checkout (or use the *Mollie Settings* link in the Plugins overview)
4. You're done, the active payment methods should be visible in the checkout of your webshop.

= Manual installation =

1. Unpack the download package
2. Upload the directory 'mollie-payments-for-woocommerce' to the `/wp-content/plugins/` directory
3. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
4. Set your Mollie API key at WooCommerce -> Settings -> Checkout (or use the *Mollie Settings* link in the Plugins overview)
5. You're done, the active payment methods should be visible in the checkout of your webshop.

Please contact info@mollie.com if you need help installing the Mollie WooCommerce plugin. Please provide your Mollie partner ID and website URL.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

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
WooCommerce -> Settings -> Checkout -> Mollie - Bank Transfer.

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
* Add message for Belfius, Bitcoin, Bancontact/Mister Cash and paysafecard when the payment is paid successfully.

= 2.0.0 - 17/08/2015 =
* Complete rewrite of our WooCommerce plugin to better follow WordPress and WooCommerce standards and add better support for other plugins.

== Upgrade Notice ==

= 2.5.2 =
Our plugin is now compatible with WooCommerce Subscriptions for recurring payments.

= 2.0.0 =
* The 2.x version of the plugin uses a different plugin name. You can still run version 1.x of our plugin if you want to temporary
keep support for payments created using version 1.x. Hide the old payment gateways by disabling the old 'Mollie Payment Module' payment gateway in WooCommerce -> Settings -> Checkout.
