=== Mollie Payments for WooCommerce ===
Contributors: l.vangunst, daanvm
Tags: mollie, payments, woocommerce, e-commerce, webshop, psp, ideal, sofort, credit card, creditcard, visa, mastercard, mistercash, bancontact, bitcoin, paysafecard, direct debit, incasso, sepa, banktransfer, overboeking, betalingen
Requires at least: 3.8
Tested up to: 4.3.1
Stable tag: 2.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept payments in WooCommerce with Mollie iDEAL, Credit Card, Bancontact/Mister Cash, Bank Transfer, PayPal, Bitcoin, paysafecard and SOFORT Banking

== Description ==

This plugin will add support for the following Mollie payments methods to your WooCommerce webshop:

* iDEAL
* Banktransfer
* Credit card
* Bancontact / Mister Cash
* SEPA Direct Debit
* PayPal
* SOFORT banking
* Belfius Direct Net
* Bitcoin
* paysafecard

Please go to the [signup page](https://www.mollie.com/nl/signup) to create a new Mollie account and start receiving payments in a couple of minutes. Contact info@mollie.com if you have any questions or comments about this plugin.

= Features = 

* Support for all available Mollie payment methods
* Edit order, title, description for every payment method
* Refunds (WooCommerce 2.2+)
* Multiple translations: English, Dutch, German and French
* Event log for debugging purposes
* WordPress Multisite support
* WPML support

== Frequently Asked Questions ==

= I can't install the plugin, the plugin is displayed incorrectly =

Please temporary enable the [WordPress Debug Mode](https://codex.wordpress.org/Debugging_in_WordPress). Edit your `wp-config.php` and set the contants `WP_DEBUG` and `WP_DEBUG_LOG` to `true` and try
it again. When the plugin triggers an error, WordPress will log the error to the log file `/wp-content/debug.log`. Please check this file for errors. When done, don't forget to turn off
the WordPress debug mode by setting the two contants `WP_DEBUG` and `WP_DEBUG_LOG` back to `false`.

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
cancel the order. The order stock is also reduced to reserve stock for these orders. The stock is restored if the payment fails or is cancelled. You can change the initial order status for these payment methods on there setting page.

= I have a different question about this plugin =

Please contact info@mollie.com with your Mollie partner ID, please describe your problem as detailed as possible. Include screenshots where appropriate.
Where possible, also include the Mollie log file. You can find the Mollie log files in `/wp-content/uploads/wc-logs/` or `/wp-content/plugin/woocommerce/logs`.

== Screenshots ==

1. The global Mollie settings are used by all the payment gateways. Please insert your Mollie API key to start.
2. Change the title and description for every payment gateway. Some gateways have special options.
3. The available payment gateways in the checkout.
4. The order received page will display the payment status and customer details if available.
5. The order received page for the gateway banktransfer will display payment instructions.
6. Some payment methods support refunds. The 'Refund' button will be available when the payment method supports refunds.

== Installation ==

= Minimum Requirements =

* PHP version 5.2 or greater
* PHP extensions enabled: cURL, JSON
* WordPress 3.8 or greater
* WooCommerce 2.1.0 or greater

= Automatic installation =

1. Install the plugin via Plugins -> New plugin. Search for 'Mollie Payments for WooCommerce'.
2. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
3. Set you Mollie API key at WooCommerce -> Settings -> Checkout (or use the *Mollie Settings* link in the Plugins overview)
4. Your done, the active payment methods should be visible in the checkout of your webshop.

= Manual installation =

1. Unpack the download package
2. Upload the directory 'mollie-payments-for-woocommerce' to the `/wp-content/plugins/` directory
3. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
4. Set you Mollie API key at WooCommerce -> Settings -> Checkout (or use the *Mollie Settings* link in the Plugins overview)
5. Your done, the active payment methods should be visible in the checkout of your webshop.

Please contact info@mollie.com if you need help installing the Mollie WooCommerce plugin. Please provide your Mollie partner ID and website URL.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 2.0.0 - 17/08/2015 =
* Complete rewrite of our WooCommerce plugin to better follow WordPress and WooCommerce standards and add better support for other plugins.

= 2.0.1 - 02/10/2015 =
* Add support for SEPA Direct Debit.
* Add message for Belfius, Bitcoin, Bancontact/Mister Cash and paysafecard when the payment is paid successfully.

= 2.1.0 - 01/12/2015 =
* For payment methods where the payment status will be delivered after a couple of days you can set the initial order status. Choose between `on-hold` or `pending`.
* Get the correct current locale (with support for [WPML](https://wpml.org)).
* Cache payment methods and issuers by locale.
* Cancel order when payment is expired.
* Reduce order when initial order status is `on-hold`. Restore order stock when payment fails.
* Hide payment gateway when cart exceeds method min / max amount. Method min / max amount is returned by Mollie API.
* Add filter to change the return URL.

== Upgrade Notice ==

= 2.0.0 =
* The 2.x version of the plugin uses a different plugin name. You can still run version 1.x of our plugin if you want to temporary
keep support for payments created using version 1.x. Hide the old payment gateways by disabling the old 'Mollie Payment Module' payment gateway in WooCommerce -> Settings -> Checkout.
