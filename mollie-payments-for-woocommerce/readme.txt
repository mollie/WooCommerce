=== Mollie Payments for WooCommerce ===
Contributors: l.vangunst
Tags: mollie, payments, woocommerce, e-commerce, webshop, psp, ideal, sofort, credit card, creditcard, visa, mastercard, mistercash, bancontact, bitcoin, paysafecard, banktransfer, overboeking, betalingen
Requires at least: 3.8
Tested up to: 4.2.4
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept payments in WooCommerce with Mollie iDEAL, Credit Card, Bancontact/Mister Cash, Bank Transfer, PayPal, Bitcoin, paysafecard and SOFORT Banking

== Description ==

This plugin will add support for the following Mollie payments methods to your WooCommerce webshop:

* iDEAL
* Banktransfer
* Credit card
* Bancontact / Mister Cash
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

== Frequently Asked Questions ==

= I can't install the plugin, the plugin is displayed incorrectly =

Please temporary enable the [WordPress debug option](https://codex.wordpress.org/Debugging_in_WordPress). Set the contants `WP_DEBUG` and `WP_DEBUG_LOG` to `true` and try
it again. When the plugin triggers an error, WordPress will log the error to the log file `/wp-content/debug.log`. Please check this file for errors. When done, don't forget to turn off
the WordPress debug mode by setting the two contants `WP_DEBUG` and `WP_DEBUG_LOG` back to `false`.

= The order status is not getting updated after successfully completing the payment =

* Please check the Mollie log file located in `wp-content/uploads/wc-logs/` for debug info. Please search for the correct order number and check if Mollie has called the shop Webhook to report the payment status.
* Please check your Mollie dashboard to check if there are failed webhook reports. Mollie tried to report the payment status to your website but something went wrong.
* Contact info@mollie.com with your Mollie partner ID and the order number. We can investigate the specific payment and check whether Mollie successfully reported the payment state to your webshop.

= I have a different question about this plugin =

Please contact info@mollie.com with your Mollie partner ID, please describe your problem as detailed as possible. Include screenshots where appropriate.
Where possible, also include the Mollie log file. You can find the Mollie log files in `wp-content/uploads/wc-logs/mollie-payments-for-woocommerce-*.log`.

== Screenshots ==

1. The global Mollie settings are used by all the payment gateways. Please insert your Mollie API key to start.
2. Change the title and description for every payment gateway. Some gateways have special options.
3. The available payment gateways in the checkout.
4. The order received page will display the payment status and customer details if available.
5. The order received page for the gateway banktransfer will display payment instructions.

== Installation ==

= Minimum Requirements =

* PHP version 5.2 or greater
* PHP extensions enabled: cURL, JSON
* WordPress 3.8 or greater
* WooCommerce 2.1.0 or greater

1. Install the plugin via Plugins -> New plugin
2. Activate the 'Mollie Payments for WooCommerce' plugin
3. Set you Mollie API key at WooCommerce -> Settings -> Checkout (or use the *Mollie Settings* link in the Plugins overview)
4. Your done, the active payment methods should be visible in the checkout of your webshop.

Please contact info@mollie.com if you need help installing the Mollie WooCommerce plugin. Please provide you Mollie partner ID and website URL.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 2.0.0 =
Complete rewrite of our WooCommerce plugin to better follow WordPress and WooCommerce standards and add better support for other plugins.

== Upgrade Notice ==

= 2.0.0 =
The 2.x version of the plugin uses a different plugin name. You can still run version 1.x of our plugin if you want to temporary keep support for payments created using version 1.x
