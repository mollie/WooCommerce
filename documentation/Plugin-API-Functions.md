### Programmatically capture, refund, void, cancel, and ship Mollie orders.

With the Mollie API, you can programmatically capture, refund, void, cancel, and ship orders. 
These actions are logged by the plugin. 
Here are some examples of how to use these functions:

#### Capture an order
```php
use function Mollie\WooCommerce\Inc\Api\mollie_capture_order;

add_action('init', function () {
    $order_id = 123;
    $order = wc_get_order($order_id);
    mollie_capture_order($order);
});
```

#### Refund an order
```php
use function Mollie\WooCommerce\Inc\Api\mollie_refund_order;

add_action('init', function () {
    $order_id = 123;
    $order = wc_get_order($order_id);
    mollie_refund_order($order);
});
```

#### Void an order
```php
use function Mollie\WooCommerce\Inc\Api\mollie_void_order;

add_action('init', function () {
    $order_id = 123;
    $order = wc_get_order($order_id);
    mollie_void_order($order);
});
```

#### Cancel an order
```php
use function Mollie\WooCommerce\Inc\Api\mollie_cancel_order;

add_action('init', function () {
    $order_id = 123;
    $order = wc_get_order($order_id);
    mollie_cancel_order($order);
});
```

#### Ship an order
```php
use function Mollie\WooCommerce\Inc\Api\mollie_ship_order;

add_action('init', function () {
    $order_id = 123;
    $order = wc_get_order($order_id);
    mollie_ship_order($order);
});
```




