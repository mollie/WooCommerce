=== Mollie Payments for WooCommerce ===
Contributors: daanvm, danielhuesken, davdebcom, dinamiko, syde, l.vangunst, ndijkstra, robin-mollie, wido, carmen222, inpsyde-maticluznar
Tags: mollie, woocommerce, payments, ecommerce, credit card
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 8.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept all major payment methods in WooCommerce today. Credit cards, iDEAL and more! Fast, safe and intuitive.

== Description ==

https://www.youtube.com/watch?v=33sQNKelKW4

> **Special limited time offer:** Pay ZERO processing fees for your first month.* [Sign-up to Mollie today](https://my.mollie.com/campaigns/signup/molliewoocommerce2024?utm_campaign=GLO_Q4_2024_Woo-Campaign&utm_medium=company_profile&utm_source=partner&utm_content=pagebannerwordpress&utm_partner=woocommerce&campaign_name=GLO_Q4_2024_Woo-Campaign)

\***To qualify for this offer, you must sign up through the specific link above. Offer subject to terms and conditions**

Quickly integrate all major payment methods in WooCommerce, wherever you need them. Mollie Payments for WooCommerce adds the critical success factor: an easy-to-install, easy-to-use, customizable payments gateway that is as flexible as WooCommerce itself.

> **Effortless payments for your customers, designed for growth**

No need to spend weeks on paperwork or security compliance procedures. Enjoy enhanced conversions as we support shopper's favorite payment methods and ensure their utmost safety. We made payments intuitive and safe for merchants and their customers.

= Payment methods =

Credit & Debit Cards:

* VISA (International)
* MasterCard (International)
* American Express (International)
* Cartes Bancaires (France)
* CartaSi (Italy)
* V Pay (International)
* Maestro (International)

European and local payment methods:

* Bancomat Pay (Italy)
* Bancontact (Belgium)
* Belfius (Belgium)
* Blik (Poland)
* EPS (Austria)
* Gift cards (Netherlands)
* iDEAL (Netherlands)
* KBC/CBC payment button (Belgium)
* Klarna One (UK)
* Klarna Pay now (Netherlands, Belgium, Germany, Austria, Finland)
* Payconiq (Belgium, Luxembourg)
* Przelewy24 (Poland)
* Satispay (EU)
* SEPA – Credit Transfer (EU)
* SEPA – Direct Debit (EU)
* SOFORT Banking (EU)
* TWINT (Switzerland)
* Vouchers (Netherlands, Belgium)

International payment methods:

* Apple Pay (International)
* PayPal (International)
* Paysafecard (International)

Pay after delivery payment methods:

* Billie – Pay by Invoice for Businesses
* iDEAL in3 – Pay in 3 installments, 0% interest
* Klarna Pay later (Netherlands, Belgium, Germany, Austria, Finland)
* Klarna Slice it (Germany, Austria, Finland)
* Riverty (Netherlands, Belgium, Germany, Austria)

= Get started with Mollie =

1. [Create a Mollie account](https://my.mollie.com/campaigns/signup/molliewoocommerce2024?utm_campaign=GLO_Q4_2024_Woo-Campaign&utm_medium=company_profile&utm_source=partner&utm_content=pagebannerwordpress&utm_partner=woocommerce&campaign_name=GLO_Q4_2024_Woo-Campaign)
2. Install **Mollie Payments for WooCommerce** on your WordPress website
3. Activate Mollie in your WooCommerce webshop and enter your Mollie API key
4. In your Mollie Dashboard, go to Settings > Website profiles and select the payment methods you want to offer
5. Go to your WordPress Admin Panel. Open WooCommerce > Settings > Payments to check if your preferred methods are enabled

Once your Mollie account has been approved, you can start accepting payments. 

> **Our pricing is always per transaction. No startup fees, no monthly fees, and no gateway fees. No hidden fees, period.**

= Features =

* Support for all available Mollie payment methods
* Compatible with WooCommerce Subscriptions for recurring payments (Apple Pay, credit card, iDEAL, and more via SEPA Direct Debit)
* Transparent pricing. No startup fees, no monthly fees, and no gateway fees. No hidden fees, period.
* Edit the order, title and description of every payment method in WooCommerce checkout
* Support for full and partial payment refunds
* Configurable pay outs: daily, weekly, monthly - whatever you prefer
* [Powerful dashboard](https://www.mollie.com/en/features/dashboard) on mollie.com to easily keep track of your payments
* Fast in-house support. You will always be helped by someone who knows our products intimately
* Multiple translations: English, Dutch, German, French, Italian, Spanish
* Event log for debugging purposes
* WordPress Multisite support
* Works well with multilingual plugins like WPML/Polylang

= Join the Mollie Community =

Become part of Mollie's growing community and gain access to our comprehensive support network, including a [Discord Developer Community](https://discord.gg/y2rbjqszbs) to stay connected and informed.

> **Your success is our mission. With Mollie, simplify your payments and focus on growing your business.**

[Sign up today](https://my.mollie.com/campaigns/signup/molliewoocommerce2024?utm_campaign=GLO_Q4_2024_Woo-Campaign&utm_medium=company_profile&utm_source=partner&utm_content=pagebannerwordpress&utm_partner=woocommerce&campaign_name=GLO_Q4_2024_Woo-Campaign) and start enhancing your WooCommerce store with Mollie's advanced payment solutions.

Feel free to contact info@mollie.com if you have any questions or comments about this plugin.

= More about Mollie =

Since 2004, Mollie has been on a mission to help businesses drive growth through simplified payments and financial services.

Initially observing banks offering businesses outdated technology and complex processes, Mollie decided to innovate. Striving to improve conditions by acting fairly and being a true partner to customers on their journey to success has always been a priority.

Over the years, Mollie has expanded significantly, yet the core mission remains unchanged: to address and solve customer problems to facilitate their growth.

Mollie champions the belief that simplicity leads to the best solutions and designs products to serve everyone: from solopreneurs and startups to global enterprises. This approach ensures every customer has access to the necessary tools for success.

Today, Mollie powers growth for over 130,000 businesses with effortless online payments, money management tools, and flexible funding, continuously enhancing payment and financial services for a broad spectrum of clients including global brands, SMEs, marketplaces, SaaS platforms, and more.

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

* PHP version 7.4 or greater
* PHP extensions enabled: cURL, JSON
* WordPress 5.0 or greater
* WooCommerce 3.9 or greater
* Mollie account

= Automatic installation =

1. Install the plugin via Plugins -> New plugin. Search for 'Mollie Payments for WooCommerce'.
2. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
3. Set your Mollie API key at WooCommerce -> Settings -> Mollie Settings (or use the *Mollie Settings* link in the Plugins overview)
4. You're done, the active payment methods should be visible in the checkout of your webshop.

= Manual installation =

1. Unpack the download package
2. Upload the directory 'mollie-payments-for-woocommerce' to the `/wp-content/plugins/` directory
3. Activate the 'Mollie Payments for WooCommerce' plugin through the 'Plugins' menu in WordPress
4. Set your Mollie API key at WooCommerce -> Settings -> Mollie Settings (or use the *Mollie Settings* link in the Plugins overview)
5. You're done, the active payment methods should be visible in the checkout of your webshop.

Please contact info@mollie.com if you need help installing the Mollie WooCommerce plugin. Please provide your Mollie partner ID and website URL.

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.


== Changelog ==

= 8.0.0 - 27-03-2025 =

* Feature Flag - Klarna, Riverty and Billie can be used with Payments API
* Feature - Added support for Mollie's new Payments API features
* Fixed - Notice for missing value of cardToken
* Fixed - ltrim error on Apple Pay notice with php 8.2 (author @kylwes)
* Fixed - Logged URL should be same as used in future logic (author @tombroucke)

= 7.10.0 - 18-02-2025 =

* Added - PaybyBank payment method
* Added - MB Way payment method
* Added - Multibanco payment method
* Added - Swish payment method
* Feature - Load block Checkout payment methods despite no active country selection
* Deprecated - Do not show deprecated Klarna methods if disabled in Mollie profile
* Fixed - Currency Symbol Positioning in bock checkout
* Fixed - Wrong order status in some cases
* Fixed - Fatal error on Refunds in some situations (author @Fidelity88)

= 7.9.1 - 22-01-2025 =

* Feature - Style Apple Pay express button via Checkout block
* Fixed - Notice `_load_textdomain_just_in_time` due to early translation loading
* Fixed - Company Name input field not saved in Order when Billie was enabled
* Fixed - Mollie Payment methods may not load on Checkout block
* Fixed - Mollie Payment methods may disappear from Checkout block when changing billing country
* Fixed - Mollie Components are not enabled by default on new installations

= 7.9.0 - 18-11-2024 =

* Feature - Revamp Mollie settings dashboard
* Feature - Implement dedicated Block Express Cart/Checkout location for Apple Pay button
* Feature - Trustly for first Payments
* Fixed - Display notice in iDEAL settings about iDEAL 2.0 and removal of bank issuer dropdown
* Fixed - Translation Update Loop in Mollie Payments for WooCommerce
* Fixed - Bank Transfer payment details displayed in one line on order-received page

= 7.8.2 - 08-09-2024 =

* Fixed - Subscription renewal status on-hold instead of active

= 7.8.1 - 07-09-2024 =

* Feature Flag - Initiative - Swish payment method.
* Fixed - Unable to make PayPal payments when surcharge is enabled on product and cart pages.
* Fixed - Cancel order on expiry date should no longer trigger on WP init.
* Fixed - Display of Payment Status column in orders overview when capturing payments immediately.
* Fixed - Incorrect data type handling in MaybeDisableGateway.php.
* Fixed - Prevented dependency conflicts, such as for psr/log.
* Fixed - Italian translation for integration microcopy.
* Fixed - Improved accessibility of gateway icons (a11y improvement).
* Fixed - Undefined property warning in Apple Pay payments related to stdClass::$cardHolder. (author @mklepaczewski )
* Fixed - German translation issue in order confirmation email.
* Fixed - Populate birthdate on pay page for in3 and Riverty.
* Fixed - Missing translation update for surcharge string.

= 7.8.0 - 27-08-2024 =

* Added - Satispay payment method 
* Security - Remove Mollie SDK examples folder and some CS fixes

= 7.7.0 - 12-08-2024 =

* Added - Payconiq payment method 
* Added - Riverty payment method 
* Fix - Declaring compatibility in WP Editor 
* Security - Enhanced object reference security

= 7.6.0 - 10-07-2024 =

* Added - Trustly payment method
* Deprecated - Giropay payment method ([Giropay Depreciation FAQ](https://help.mollie.com/hc/en-gb/articles/19745480480786-Giropay-Depreciation-FAQ))
* Fixed - Mollie hooks into unrelated orders
* Fixed - Notices and type errors after 7.5.5 update
* Fixed - Rounding issues with products including tax

= 7.5.5 - 18-06-2024 =

* Feature Flag - Enable Bancomat Pay & Alma feature flag by default (official launch 2024-07-01)
* Task - update wordpress.org plugin page
* Fix - Change from iDeal 1.0 to iDeal 2.0
* Fix - update apple-developer-merchantid-domain-association certificate 
* Fix - Description not shown on block checkout
* Fix - All Gift Card issuers displayed despite only some being active
* Fix - Several Undefined array key warnings malform JSON requests on Block Checkout
* Fix - Surcharge string to ‘excl. VAT’

= 7.5.4 - 03-06-2024 =

* Feature Flag - Initiative - Alma for WooCommerce Integration - under flag add_filter('inpsyde.feature-flags.mollie-woocommerce.alma_enabled', false);
* Feature - Add WooCommerce as required plugin in header
* Fix - Display error for Apple Pay Validation Error in Woocommerce
* Fix - TypeError when WooCommerce Analytics is disabled
* Fix - In3 - payment successful with date in the future
* Fix - Ensure Smooth Order Processing Despite Rounding Differences
* FIx - Rebrand from Inpsyde to Syde

= 7.5.3 - 22-05-2024 =

* Fix - Updated in3 checkout process: Phone and birthdate are now optional, but if provided, validated values will be sent to expedite checkout.

= 7.5.2 - 22-04-2024 =

* Feature - Support for new payment method Bancomat Pay (beta)
* Tweak - Reorder gateway settings
* Fix - Gift Card issuer dropdown replaced by icon HTML when only one giftcard enabled
* Fix - TypeError merchant capture feature
* Fix - Type error on Pay for Order page when in3 is active on PHP 8+
* Fix - Typo in variable/method names
* Fix - Refresh methods not enabling methods enabled in Mollie
* Fix - Variable names in strings deprecated in PHP 8.2 (author @vHeemstra)
* Fix - WC 7.4.1 appends billingEmail to Orders API call due to mismatched filter in Banktransfer.php
* Fix - Apple Pay button payment is not possible as a guest user when debugging is active

= 7.5.1 - 12-02-2024 =

* Fix - Merchant capture error. Feature flag disabled by default

= 7.5.0 - 05-02-2024 =

* Feature - Add TWINT payment method
* Feature - Add BLIK payment method
* Feature - Enable merchant capture feature flag by default
* Feature - Enable Klarna one feature flag by default
* Fix - Birth date not showing for in3 on pay for order page
* Fix - Subscription signup payment not possible when using authorizations 
* Fix - Transaction ID field not filled for authorized/captured WooCommerce orders
* Fix - PHP Fatal error: Undefined method isCompleted
* Fix - Align merchant capture wording with Mollie

= 7.4.1 - 06-11-2023 =

* Fix - Send the bank transfer information in the order confirmation email
* Fix - Plugin keeps retrying fraudulent orders
* Fix - Order is not canceled after exact expiry date set in gateway settings
* Fix - No error messages displayed on pay for order page
* Fix - Improve “Initial payment status”  setting description for expired orders
* Fix - Update GitHub wiki after Mollie docs release
* Fix - Update plugin strings regarding documentation and support links
* Fix - Save & display bank transfer payment details in WooCommerce order
* Fix - Complete WooCommerce order when order is shipped at Mollie
* Fix - Check for WC\_Subscriptions class instead of plugin file

= 7.4.0 - 20-09-2023 =

* Feature - Pass Paypal "Additional" address information as Address_2
* Feature - The payment method API image will now display when the "Use API dynamic title and gateway logo" option is enabled.
* Feature - Introduced a new filter to programmatically control the visibility of the API title for each payment method: apply_filters('mollie_wc_gateway_use_api_title', $value, $paymentMethodId)
* Feature - Added a filter to programmatically control the visibility of the API icon for every payment method: apply_filters('mollie_wc_gateway_use_api_icon', $value, $paymentMethodId)
* Fix - Mollie is showing for WooCommerce version under 3.9.0
* Fix - Compatibility with latest WC Blocks \(>9.3.0\) to detect "incompatible gateways"
* Fix - Apple Pay button payments remain in open status at Mollie
* Fix - New block theme 22 and 23 have issues with the look and feel on Mollie components
* Fix - Site is broken on bulk edit when Mollie is activated
* Fix - Fatal error after on 7.3.8 and 7.3.9 with roots/sage
* Fix - WooCommerce - Bank Transfer -  Expiration time feature bug
* Fix - Apple Pay gateway not displayed on order pay page

= 7.3.12 - 21-08-2023 =

* Fix - Security fix

= 7.3.11 - 10-08-2023 =

* Feature flag - adding support to new upcoming payment method
* Fix -  script loading when disabled in Mollie dashboard

= 7.3.10 - 24-07-2023 =

* Fix - Updating payment method after fail in a subscription will not update the mandate
* Fix - Surcharge fee not updating on pay for order page and block checkout
* Fix - Use gateway title from API when the one saved is the previous version default one
* Fix - Missing information for In3 and Billie transactions in blocks and classic checkout
* Fix - Mollie components not initialising on block checkout after changing payment method
* Fix - Paysafecard not shown in block checkout
* Fix - Transaction with components leading to insert card details again
* Fix - Billie gateway hidden when third-party plugins are active
* Fix - Surcharge fee taxes not updated in tax total
* Fix - Biling/shipping country not included in orders from block checkout

= 7.3.9 - 31-05-2023 =

* Fix - Psr/container compatibility issue

= 7.3.8 - 31-05-2023 =

* Fix - Inform customer and merchant about Mollie outage
* Fix - Bank Transfer gateway hidden when "Activate expiry time setting" is enabled
* Fix - Surcharge description string not updated when the language changes after saving
* Fix - Show more information on recurring failed payments
* Fix - Send birthdate and phone number with In3 payments shortcode checkout
* Fix - Update credit card title. Allow users to take title from API

= 7.3.7 - 12-04-2023 =

* HotFix - Warning after update 7.3.6 instanceof PaymentMethodI failed

= 7.3.6 - 12-04-2023 =

* Feature - Implemented new payment method
* Feature - Render hook filter for Apple Pay and PayPal buttons
* Fix - PayPal payment overwrites billing information with PayPal account details
* Fix - Error when creating product category
* Fix - Some type check errors
* Fix - WC 7.2.2 update causes Fatal error: Cannot redeclare as_unschedule_action()
* Fix - Gift card warning when on Checkout page
* Fix - Block scripts loaded on any page when block features are enabled
* Fix - ApplePay Button validation issues
* Fix - PayPal button showing on out of stock product

= 7.3.5 - 24-01-2023 =

* Fix - PayPal payment overwrites billing information with PayPal account details
* Fix - Compatibility with WordPress 6.1.0
* Fix - Compatibility with WC High-Performance Order Storage
* Fix - Compatibility issues with PHP 8.1 deprecated FILTER_SANITIZE_STRING
* Fix - Issue when WooCommerce Blocks plugin was not present to load Block features
* Fix - Surcharge description in new paragraph
* Fix - Custom order meta data filter not working as expected
* Fix - Custom fields in payment translations
* Fix - Voucher showing on order-pay page when no category is set up
* Fix - Product stock restored twice on cancelled orders when Germanized plugin is active
* Fix - Surcharge settings in SEPA should not appear
* Fix - Call to undefined method WC_Gateway_Paypal::handlePaidOrderWebhook()
* Fix - Message "Test mode is active" is showing when test mode is disabled before refreshing the page
* Fix - PayPal button displayed on cart page when product amount is lower then the minimum amount required to display the button
* Fix - Crash when new method Billie is enabled at Mollie

= 7.3.4 - 09-11-2022 =

* Fix - Site crash with WooCommerce 3.0 active
* Fix - Fatal error when payment surcharge limit exceeded
* Fix - Critical error when API connection not available
* Fix - Redundant log entry
* Fix - Conflict with "Extra Checkout Options" plugin
* Fix - PHP Warning for undefined array key
* Fix - Consider order status before setting it to "Canceled" status upon Mollie expiry webhook
* Fix - Broken translation strings
* Fix - Undefined index in voucher category
* Fix - Description printed in wrong field in settings

= 7.3.3 - 21-09-2022 =

* Fix - Subscription renewal charged twice
* Fix - Credit card components not loading on update

= 7.3.2 - 14-09-2022 =

* Fix - Warning stops transaction when debugging on production

= 7.3.1 - 13-09-2022 =

* Fix - When refunding from Mollie profile order notes and status not updated
* Fix - Error on checkout block, surcharge added for all payment methods
* Fix - PayPal button display issues
* Fix - Logs created when logging is disabled
* Fix - Bank Transfer disappears on order pay page
* Fix - Surcharge value not including VAT
* Fix - UTM parameters missing in mollie.com links
* Fix - Voucher category does not reflect on variations
* Fix - Issuers dropdown not loading
* Fix - Querying gateway settings on every page load
* Fix - Inconsistency in expiry date terms
* Fix - Filter should allow SDD enabled without WooCommerce Subscriptions active
* Fix - Change link to API key profile in mollie.com
* Fix - Translations errors
* Fix - Conflict with SSH SFTP Updater Support
* Fix - Error when customer attempts payment with non-Mollie method after expiration

= 7.3.0 - 02-08-2022 =

* Feature - Activate Mollie Components by default for new installations
* Fix - Order note not translated
* Fix - Gateway surcharge not applying tax
* Fix - pending SEPA subscription renewal orders remain in "Pending payment" instead of being set to "On-Hold"
* Fix - PHP warnings when using not Mollie gateway
* Fix - Order API not processing transactions due to taxes mismatch
* Fix - Inconsistent order numbers sometimes printing  "Bestelling {bestelnummer}"
* Fix - Link to new my.mollie.com url
* Fix - Update In3 description

= 7.2.0 - 21-06-2022 =

* Feature - New payment method: In3
* Feature - Add order line information to debug logs
* Feature - Valuta symbol before amount
* Feature - Add new translations
* Fix - Check Payment API setting before showing Voucher, Klarna, In3 (Order API mandatory)
* Fix - Remove title if empty setting on block checkout
* Fix - Typo in Mollie settings
* Fix - SEPA notice shows incorrectly when no settings saved
* Fix - Order API not selected when no settings saved

= 7.1.0 - 26-04-2022 =

* Feature - Implement uninstall method
* Feature - Add setting to remove Mollie's options and scheduled actions from db
* Feature - Improve Payment API description (@vHeemstra)
* Feature - Improve API request
* Feature - Add gateway title for en_GB translation
* Fix - Showing gateway default description when empty description was saved in settings
* Fix - Surcharge added over limit wrongly when WooCommerce Blocks are active
* Fix - Fatal error when visiting invalid return URL
* Fix - Error on refunding subscriptions created with Payments API
* Fix - Fallback to shop country when customer country is not known
* Fix - Invalid argument supplied to foreach error
* Fix - Display SEPA bank transfer details in merchant email notifications
* Fix - Error on update page with translations
* Fix - Empty space under credit card in checkout when components are not enabled
* Fix - Error on notes and logs with canceled, expired and failed orders
* Fix - Incorrect surcharge fee applied when WooCommerce blocks are active
* Fix - Fatal error when saving empty surcharge fields

= 7.0.4 - 23-03-2022 =

* Fix - Conflict with Paytium plugin
* Fix - Fallback from orders API to payments API not working
* Fix - Container access for third-party developers

= 7.0.3 - 15-03-2022 =

* Fix - Update Mollie SDK and add http client
* Fix - Loop API calls causing overload
* Fix - API key error during status change
* Fix - Transaction failing due to tax line mismatch
* Fix - Conflict with invoices plugin
* Fix - List in settings the gateways enabled at Mollie's profile
* Fix - Voucher loads incorrectly on blocks when updating country
* Fix - Update iDeal logo
* Fix - Missing ISK currency with 0 decimal places

= 7.0.2 - 15-02-2022 =

* Fix - Rollback code to version 6.7.0

= 7.0.1 - 14-02-2022 =

* Fix - Fatal error when WC Blocks and third-party payment gateway active after 7.0.0 update
* Fix - Error undefined property actionscheduler_actions
* Fix - Missing payment method title when paying via checkout block
* Fix - Refund functionality missing in v.7.0.0

= 7.0.0 - 09-02-2022 =

* Feature - WooCommerce Blocks integration
* Feature - Merchant change subscription payment method
* Feature - Recharge Subscriptions integration
* Feature - Improve handling components errors
* Fix - Add missing translations
* Fix - Fallback to shop country when billing country is empty
* Fix - Surcharge fatal error when settings not yet saved
* Fix - Correct notice when not capturing due is a payment
* Fix - Punycode only on domain url
* Fix - Update Apple Pay certificate key

= 6.7.0 - 11-11-2021 =

* Feature - New payment method - Klarna Pay Now
* Feature - Apple Pay Subscriptions integration
* Fix - Update Mollie Component Labels
* Fix - Incorrect logo for SOFORT payment method
* Fix - Tax calculation inaccurate for bundled products with mixed tax products
* Fix - Catch error in Object class
* Fix - Change NL translation for Klarna Slice It gateway
* Fix - Show missing selector icons for credit card

= 6.6.0 - 14-09-2021 =

* Feature - Surcharge fee UI/UX improvements
* Fix - Select the correct payment when two subscription have the same payment method
* Fix - Remove obsolete MisterCash payment method
* Fix - Apple Pay button not updated on variable products
* Fix - PayPal button unresponsive on cart page
* Fix - Added missing translations
* Fix - Scheduled actions triggered with disabled feature
* Fix - Removed obsolete “restore subscriptions” tool
* Fix - Typo on PayPal settings

= 6.5.2 - 13-07-2021 =

* Fix - Unneeded metadata causing error

= 6.5.1 - 12-07-2021 =

* Fix - Subscription renewal failing
* Fix - Action scheduler amount of entries

= 6.5.0 - 05-07-2021 =

* Feature - Add expiry date for orders
* Feature - Hide API keys in settings
* Feature - Improve Klarna notice about enabling default fields
* Feature - New Wiki entry: Gateways hide when surcharge fee is applied
* Feature - PayPal button improvements
* Feature - Primary key on pending_payment table
* Feature - PHP and WordPress upgrade
* Feature - Default translations for Klarna payment methods. (by @Timollie)
* Fix - Select the first payment when two subscriptions have the same payment method
* Fix - Credit card icon missing mollie-gateway-icon class
* Fix - Payments transaction ID link leads to orders dashboard in Mollie
* Fix - Manual cancelation of order returns to pending payment
* Fix - Broken compatibility with WooFunnels plugin
* Fix - Enqueue of style script on non-checkout pages
* Fix - IngHomePay class showing in the composer class-map

= 6.4.0 - 19-05-2021 =

* Feature - PayPal Button for digital goods
* Fix - Repair subscription method triggering on parent order
* Fix - Surcharge breaking PDF invoices
* Fix - Mollie Components fail when coupon code is applied on checkout
* Fix - Test mode notice links to old settings page
* Fix - nl_NL(formal) wrong translation string

= 6.3.0 - 29-04-2021 =

* Feature - Allow choosing between Payment/Order API
* Feature - Payment surcharge feature
* Feature - Custom icons for every gateway
* Feature - Notice about increasing minimum PHP and WP version
* Fix - Fix missing metadata in subscriptions
* Fix - Polylang interaction breaks redirect URL
* Fix - Partial refund with quantity 0 errors

= 6.2.2 - 15-04-2021 =

* Fix - Missing metadata on subscriptions results in failing recurring payments

= 6.2.1 - 01-04-2021 =

* Fix - Transaction ID missing

= 6.2.0 - 22-03-2021 =

* Feature - No longer support for WooCommerce version below 3.0
* Feature - New library to check the environment constraints
* Feature - New translations
* Feature - Add support for WooCommerce Gift Cards
* Feature - Apple Pay added new selector settings
* Feature - Add new language NL formal
* Feature - Add rewrite rule to serve Apple Pay validation file
* Fix - Remove Ing HomePay gateway
* Fix - Use pre-scoped Mollie SDK to fix conflict with Guzzle
* Fix - Setting links pointing to new address
* Fix - PHP notice on missing Apple Pay token
* Fix - Do not translate description of payment (by @timollie)
* Fix - After partial refund state changes, should remain the same instead
* Fix - PHP8 error notice on activation
* Fix - Gateway icons not aligned with Flatsome theme
* Fix - Issuers dropdown not showing by default
* Fix - Using the wrong mandate when multiple payment methods exist for the customer

= 6.1.0 - 26-01-2021 =

* Feature - New documentation on settings
* Feature - Bulk-edit functionality for Voucher categories
* Fix - Order updated issue with Polylang
* Fix - Hide Issuers dropdown list on setting option
* Fix - Send domain only even when installation is in subfolder for Apple Pay validation

= 6.0 - 16-12-2020 =

* Feature - New setting to display payment methods based on country
* Feature - Notice customers that support for WooCommerce under v3 is dropped
* Feature - Create mandate for recurring subscriptions
* Feature - New settings UI
* Fix - Guzzle library conflicts with other plugins
* Fix - API keys error with Mollie Components
* Fix - Voucher works with variation products
* Fix - Notice on missing voucher option (by @Timollie)
* Fix - Performance issues related to icons

= 5.11.0 - 11-11-2020 =

* Fix - Google analytics duplicated tracking events
* Fix - Prevent third party plugins from changing billingCountry field
* Fix - Mollie Components string "secure payments..." not translated
* Fix - Credit card icons not displaying correctly

= 5.10.0 - 03-11-2020 =

* Feature - New Voucher gateway
* Feature - Custom expiry date for Bank transfer payments
* Feature - Notice informing that test mode is enabled
* Fix - Error when refunding unshipped Klarna order
* Fix - Selecting item variations when ApplePay is enabled
* Fix - Remove autoload function from global namespace
* Fix - Transactions are included in shipping process
* Fix - Undefined index for ApplePay token
* Fix - Remove file_get_content()

= 5.9.0 - 16-09-2020 =

* Feature - Cancel order on payment expiration

= 5.8.3 - 09-09-2020 =

* Fix - Apple Pay button is disabled if Apple Pay gateways is disabled
* Fix - Breaks Urls of translations plugins
* Fix - Translations update endless loop

= 5.8.2 - 19-08-2020 =

* Fix - Use own plugin translation files
* Fix - Show information in order notes for gift card payments
* Fix - Components does not work with Deutsch Sie language
* Fix - Respect maximal field length for address fields
* Fix - Log info when credit card fails
* Fix - Errors: [] operator not supported for strings
* Fix - Load icons when interacting with add blockers
* Fix - Error with wc_string_to_bool() function

= 5.8.1 - 08-07-2020 =

* Feature - Add Apple Pay direct button feature in product and cart pages

= 5.7.2 - 01-07-2020 =

* Fix - Missing MasterCard icon selector

= 5.7.1 - 01-07-2020 =

* Feature - Show selected credit card icons on checkout
* Feature - Log information about API call data
* Fix - Translate the string "Secure payments provided by" 
* Fix - Refund amount >1000€ (by @NielsdeBlaauw)

= 5.6.1 - 27-05-2020 =

* Feature - Translations of Plugin FR/DE/NL/EN/ES
* Fix - Update order status on payment refund
* Fix - 404 response during redirection on checkout when Polylang plugin is active 
* Fix - Crash on calling a WC 3.0 method, fallback method for BC
* Fix - Remove custom due date for bank transfer payment
* Fix - Performance issues on transient functions
* Fix - Action `*_customer_return_payment_success` backwards compatibility broken
* Fix - Apple Pay is available after a failed payment on not compatible devices
* Fix - Deprecated: idn_to_ascii() (by @sandeshjangam)


= 5.5.1 - 12-03-2020 =

* Fix - Fatal error caused by debug() function
* Fix - Critical uncaught error when idn_to_ascii() function is disabled

= 5.5.0 - 11-03-2020 =

* Add - Use key instead of id to retrieve order onMollieReturn event webhooks
* Tweak - Page load performance improvements
* Tweak - Improve payment methods icons delivery mechanism by rely on cloud and fallback to static images
* Fix - Null pointer exception in case getActiveMolliePayment returns null
* Fix - WooCommerce order status can be modified via Mollie webhook without taking into account possible changes in WooCommerce
* Fix - 404 response during redirection on checkout when Polylang plugin is active
* Fix - Handle domain with non-ASCII characters

= 5.4.2 - 09-12-2019 =

* Fix - Mollie crash when WooCommerce plugin is not active
* Fix - Checkout form does not submit the order at first click on Place Order button when payment method is not one which support Mollie Components
* Fix - Minor styles issues for Mollie Components

= 5.4.1 - 05-12-2019 =

* Fix - Mollie Components request multiple times the merchant profile ID via API

= 5.4.0 - 04-12-2019 =

* Fix - Apple Pay Gateway is removed from available gateways during WooCommerce Api calls
* Fix - Giftcard Gateway does not show the right payment icon in checkout page
* Add - Support for Mollie Components

= 5.3.2 - 04-11-2019 =

* Fix - WooCommerce Session is not available before a specific action has been preformed causing null pointer exceptions in backend

= 5.3.1 - 04-11-2019 =

* Fix - Apple Pay payment method appear temporary in checkout page even if the device does not support Apple Pay
* Fix - Refunding per line items is not possible when the refund amount field is disabled in WooCommerce order edit page
* Fix - Compatibility with PHP 7.4

= 5.3.0 - 21-08-2019 =

* Add - Introduce MyBank payment method
* Fix - Active Payment Object may be NULL causing WSOD after order is placed in Mollie
* Fix - ApplePay logo does not have the right resolution

= 5.2.1 - 24-07-2019 =

* Fix - Payment wall won't load because third party code may register gateways in form of a class instance instead of a string

= 5.2.0 - 23-07-2019 =

* Fix - Missing browser language detect in payment settings
* Add - Apple Pay payment method

= 5.1.8 - 24-05-2019 =

* Fix - Re-add "_orderlines_process_items_after_processing_item" hook
* Fix - Fix issue where renewal order status was not respecting settings
* Fix - Fix PHP Notice: Undefined property: Mollie_WC_Payment_Payment::$id, closes #289
* Fix - Switch version check from woocommerce_db_version to woocommerce_version as the latter is re-added to database a lot faster when it's missing then the former. Might solve issues where Mollie plugin is disabled when WooCommerce updates.

= 5.1.7 - 28-04-2019 =

* Fix - Remove Bitcoin as payment gateway, no longer supported by Mollie, contact info@mollie.com for details
* Fix - Add extra check for URL's with parameters and correct them is structure is incorrect
* Fix - getMethodIssuers: improve caching of issuers (iDEAL, KBC/CBC)
* Fix - During payment always check if a product exists, if it doesn't create a Mollie Payment instead of Mollie Order

= 5.1.6 - 10-04-2019 =

* New - Add support for Przelewy24 (Poland)

= 5.1.5 - 22-03-2019 =

* Fix - Refunds: Fix condition for extended (order line) refunds
* Fix - WPML compatibility: Use get_home_url() to solve issues where people have different URLs for admin/site

= 5.1.4 - 21-03-2019 =

* Fix - Fix caching issues for methods check
* Fix - Only run isValidForUse (and resulting API calls) in the WooCommerce settings

= 5.1.3 - 21-03-2019 =

* Fix - Revert: Check that cached methods are stored as array, otherwise retrieve from API, fixes 'Cannot use object' error

= 5.1.2 - 20-03-2019 =

* Fix - Convert de_DE_formal to de_DE
* Fix - Check that cached methods are stored as array, otherwise retrieve from API, fixes 'Cannot use object' error

= 5.1.1 - 19-03-2019 =

* New - Added two new actions when processing items for Orders API, mollie-payments-for-woocommerce_orderlines_process_items_before_getting_product_id and mollie-payments-for-woocommerce_orderlines_process_items_after_processing_item
* Fix - Fixed bug where expired orders weren't updated in WooCommerce because of check for payment ID instead of order ID
* Fix - Use get_home_url() to solve issues where people have different URLs for admin/site (also influences Polylang)
* Fix - Extended refund processing: make sure people can't do a partial order line amount refund during an order line refund
* Fix - Permanent fix for PHP 7.3 with sporadic caching issues of methods

= 5.1.0 - 19-02-2019 =

* New - Enable 'refunds' for Klarna and SEPA Direct Debit payments
* New - Support refunds per order line for payments via the Orders API (used to be only amount refunds)
* New - Updated "Tested up to" to WordPress 5.1
* New - Automatically updating Mollie Orders from WooCommerce to "Ship and Capture" and "Cancel" now supports all payments via Orders API, not just Klarna payments
* New - Add support for refunding full Mollie Orders when refunding the full WooCommerce order (Orders API)
* New - Update order lines processing to use Order instead of Cart data (for Orders API and Klarna)
* New - Orders API/Klarna: also send WooCommerce order item id to Mollie as metadata, to allow for individual order line refunding
* New - Pro-actively check for required PHP JSON extension
* New - Added filter so merchants can manipulate the payment object metadata, default filter id mollie-payments-for-woocommerce_payment_object_metadata, also see https://www.mollie/WooCommerce/wiki/Helpful-snippets#add-custom-information-to-mollie-payment-object-metadata
* New - Add billing country to payment methods cache in checkout, for when customers change their country in checkout
* New - Allow developers to hook into the subscription renewal payment before it's processed with mollie-payments-for-woocommerce_before_renewal_payment_created
* New - Set Payment screen language setting to wp_locale by default

* Fix - Temporary fix for PHP 7.3 with sporadic caching issues of methods, better fix is now being tested
* Fix - Check if WooCommerce Subscriptions Failed Recurring Payment Retry System is in-use, if it is, don't update subscription status
* Fix - Polylang: another fix for edge-case with URL parameter, please test and provide feedback is you use Polylang!
* Fix - Too many customers redirected to "Pay now" after payment, add isAuthorized to status check in getReturnRedirectUrlForOrder()
* Fix - Add extra warning to order note for orders that are completed at Mollie (not WooCommerce)
* Fix - Improve onWebhookFailed for WooCommerce Subscriptions so failed payments at Mollie are failed orders at WooCommerce

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
* Add message for Belfius, Bancontact and paysafecard when the payment is paid successfully.

= 2.0.0 - 17/08/2015 =
* Complete rewrite of our WooCommerce plugin to better follow WordPress and WooCommerce standards and add better support for other plugins.

== Upgrade Notice ==

= 2.5.2 =
Our plugin is now compatible with WooCommerce Subscriptions for recurring payments.

= 2.0.0 =
* The 2.x version of the plugin uses a different plugin name. You can still run version 1.x of our plugin if you want to temporary
keep support for payments created using version 1.x. Hide the old payment gateways by disabling the old 'Mollie Payment Module' payment gateway in WooCommerce -> Settings -> Payments.
