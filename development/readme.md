## Development machine (vagrant)

I use [Varying Vagrant Vagrants](https://github.com/Varying-Vagrant-Vagrants/VVV) for WordPress development. This is a vagrant machine which installs a couple of WordPress websites for you to use to develop or test your plugins. [Installation instructions](https://github.com/Varying-Vagrant-Vagrants/VVV#the-first-vagrant-up).

### Add new WordPress website to vagrant machine

You can manually add new WordPress websites using [auto site setup](https://github.com/varying-vagrant-vagrants/vvv/wiki/Auto-site-Setup) but you can also use a command line tool like [vv](https://github.com/bradp/vv) to add and configure new WordPress websites. The tool will automatically update and provision your vagrant machine.

This can be used to test the Mollie WooCommerce plugin in a specific WordPress + WooCommerce version.

## GIT checkout plugin code

I have checked out the Mollie plugin in my vagrant `www` directory so it is accessible by the vagrant machine and makes it easier to later update the translation files.

```
cd <path-to-vagrant-local>/wwww
git clone git@github.com:mollie/WooCommerce.git
cd WooCommerce/

# Load submodule
git submodule init
git submodule update
```

## Keep the plugin sourcecode in sync with the plugin in your WordPress website

I use [fswatch](https://github.com/emcrisostomo/fswatch) to watch the plugin directory for changes and copy the changes to my WordPress plugin directory to keep them in sync. I use [this shell script](https://github.com/mollie/WooCommerce/tree/master/development/fswatch-sync.sh) to automatically watch for changes and update the plugin in my WordPress website.

```
cd <path-to-vagrant-local>/wwww
fswatch-sync.sh ./WooCommerce/mollie-payments-for-woocommerce/ ./wordpress-default/wp-content/plugins/mollie-payments-for-woocommerce/
```

It will notify you that the target directory will be overwritten. Changes made in the target directory will be overwritten when you make a change to the source directory.

## Updating translations

The development Wordpress website in the vagrant machine contains [translation tools](https://codex.wordpress.org/I18n_for_WordPress_Developers#Using_the_i18n_tools) to parse your sourcecode and update the translation `.pot` file.

On your vagrant machine:

```
cd /srv/www
makepot wp-plugin ./WooCommerce/mollie-payments-for-woocommerce/ ./WooCommerce/mollie-payments-for-woocommerce/i18n/languages/mollie-payments-for-woocommerce.pot
```

After the `.pot` file is updated you can update the translation files using [Poedit](https://poedit.net/). You can use the "Update from POT file..." to get the update translation keys.

## Available gateway ID's

A gateway ID is used by WooCommerce to identify the payment gateway.

* mollie_wc_gateway_banktransfer
* mollie_wc_gateway_belfius
* mollie_wc_gateway_bitcoin
* mollie_wc_gateway_creditcard
* mollie_wc_gateway_directdebit
* mollie_wc_gateway_ideal
* mollie_wc_gateway_kbc
* mollie_wc_gateway_mistercash
* mollie_wc_gateway_paypal
* mollie_wc_gateway_paysafecard
* mollie_wc_gateway_sofort

## Filters

### `mollie-payments-for-woocommerce_initial_order_status`
Determine the default order status (default: `pending`). This status is assigned to the order when the payment is created. Use this filter 
if you want to overwrite this status for all payment gateways this plugin provides.

```
add_filter('mollie-payments-for-woocommerce_initial_order_status', function($initial_order_status) {
    /* https://docs.woocommerce.com/document/managing-orders/ */
    return 'pending';
});
```

### `mollie-payments-for-woocommerce_initial_order_status_<gateway_id>`
Determine the default order status (default: `pending`). This status is assigned to the order when the payment is created. Use this filter 
if you want to overwrite this status for a specific payment gateway this plugin provides.

```
$gateway_id = 'mollie_wc_gateway_creditcard';

add_filter('mollie-payments-for-woocommerce_initial_order_status_' . $gateway_id, function($initial_order_status) {
    /* https://docs.woocommerce.com/document/managing-orders/ */
    return 'pending';
});
```

### `mollie-payments-for-woocommerce_order_status_cancelled`
Determine the new order status for when the Mollie payment is cancelled (default: `pending`). Use this filter if you want to overwrite this 
status for all payment gateways this plugin provides.

```
add_filter('mollie-payments-for-woocommerce_order_status_cancelled', function($order_status) {
    /* https://docs.woocommerce.com/document/managing-orders/ */
    return 'pending';
});
```

### `mollie-payments-for-woocommerce_order_status_cancelled_<gateway_id>`
Determine the new order status for when the Mollie payment is cancelled (default: `pending`). Use this filter if you want 
to overwrite this status for a specific payment gateway this plugin provides.

```
$gateway_id = 'mollie_wc_gateway_creditcard';

add_filter('mollie-payments-for-woocommerce_order_status_cancelled_' . $gateway_id, function($order_status) {
    /* https://docs.woocommerce.com/document/managing-orders/ */
    return 'pending';
});
```

### `mollie-payments-for-woocommerce_order_status_expired`
Determine the new order status for when the Mollie payment has expired (default: `pending`). Use this filter if you want to overwrite this 
status for all payment gateways this plugin provides.

```
add_filter('mollie-payments-for-woocommerce_order_status_cancelled', function($order_status) {
    /* https://docs.woocommerce.com/document/managing-orders/ */
    return 'cancelled';
});
```

### `mollie-payments-for-woocommerce_order_status_expired_<gateway_id>`
Determine the new order status for when the Mollie payment has expired (default: `pending`). Use this filter if you want 
to overwrite this status for a specific payment gateway this plugin provides.

```
$gateway_id = 'mollie_wc_gateway_creditcard';

add_filter('mollie-payments-for-woocommerce_order_status_expired_' . $gateway_id, function($order_status) {
    /* https://docs.woocommerce.com/document/managing-orders/ */
    return 'cancelled';
});
```

### `<gateway_id>_icon_url`
Implement this filter if you want to overwrite the default gateway icon URL.

```
$gateway_id = 'mollie_wc_gateway_creditcard';

add_filter($gateway_id . '_icon_url', function($icon_url) {
    // Overwrite gateway icon URL
    $icon_url = 'http://my-website.com/path/to/icons/creditcard.png';

    return $icon_url;
});
```

### `mollie-payments-for-woocommerce_webhook_url`
This filter can be added if you want to overwrite the payment webhook. This can be useful if your development environment is on a local machine and your machine is not publicly accessible by the Mollie platform, in which case Mollie can not deliver the webhook request to your website. You can use a tool like [ngrok](https://ngrok.com/) to create a public endpoint that proxies requests to your local machine.

```
add_filter('mollie-payments-for-woocommerce_webhook_url', function($webhook_url, WC_Order $order) {
    // Overwrite plugin webhook URL (I use ngrok.io)
    $new_webhook_url = str_replace($_SERVER['HTTP_HOST'], '63950d2f.ngrok.io', $webhook_url);

    return $new_webhook_url;
});
```

### `mollie-payments-for-woocommerce_return_url`
This filter can be added if you want to overwrite the payment return URL. The user is redirected to this return URL after he or she completes the payment.

```
add_filter('mollie-payments-for-woocommerce_return_url', function($return_url, WC_Order $order) {
    return $return_url;
});
```

### `mollie-payments-for-woocommerce_api_endpoint`
You can use this filter to overwrite the Mollie API endpoint. This is only useful for Mollie employees who have a local development version of Mollie on their own machine.

```
// Overwrite Mollie API endpoint for local Mollie installation (Mollie employees only)
add_filter('mollie-payments-for-woocommerce_api_endpoint', function($api_endpoint) {
    return 'http://api.mollie.dev';
});
```

### `woocommerce_<gateway_id>_args`
Use this filter if you need to overwrite or add specific Mollie payment parameters for creating a new payment. The `banktransfer` gateway implements this filter to add `dueDate` and `billingEmail` arguments.

```
add_filter('woocommerce_' . $this->id . '_args', function(array $arguments, WC_Order $order) {
	/* Here you can overwrite or add new arguments to the $arguments array */

	return $arguments;
});
```

## Actions

### `mollie-payments-for-woocommerce_create_payment`
Add this action if you want to receive the arguments that are used for creating a new payment. This can be useful if you want to log this during development.

```
add_action('mollie-payments-for-woocommerce_create_payment', function(array $payment_arguments, WC_Order $order) {
	log("Order {$order->id} create payment parameters: " . print_r($payment_arguments, TRUE));
}, $priority = 10, $accepted_args = 2);
```

### `mollie-payments-for-woocommerce_payment_created`
Add this action if you want to receive the arguments that are used for creating a new payment. This can be useful if you want to log this during development.

```
add_action('mollie-payments-for-woocommerce_payment_created', function(Mollie_API_Object_Payment $payment, WC_Order $order) {
    log("Order {$order->id} payment created: " . print_r($payment, TRUE));
}, $priority = 10, $accepted_args = 2);
```

### `mollie-payments-for-woocommerce_create_refund`
Add this action if you want to receive the payment that is being refunded. This can be useful if you want to log this during development.

```
add_action('mollie-payments-for-woocommerce_create_refund', function(Mollie_API_Object_Payment $payment, WC_Order $order) {
	log("Refund order {$order->id}, payment: {$payment->id}");
}, $priority = 10, $accepted_args = 2);
```

### `mollie-payments-for-woocommerce_refund_created`
Add this action if you want to receive the payment that is being refunded. This can be useful if you want to log this during development.

```
add_action('mollie-payments-for-woocommerce_create_refund', function(Mollie_API_Object_Payment_Refund $refund, WC_Order $order) {
	log("Order {$order->id} refunded, refund: {$refund->id}");
}, $priority = 10, $accepted_args = 2);
```

## Development WordPress plugin

You can add [this WordPress plugin](https://github.com/lvgunst/woocommerce-mollie-payments-development) which implements some of these filters and actions.
