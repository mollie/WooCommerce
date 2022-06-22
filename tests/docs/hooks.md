# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `mollie-payments-for-woocommerce_customer_return_payment_{$hookReturnPaymentStatus}`

*Action hook after customer returned from payment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order` | `\WC_Order` | The WooCommerce order.

**Changelog**

Version | Description
------- | -----------
`5.0.0` |

Source: [./src/Gateway/MolliePaymentGateway.php](MolliePaymentGateway.php), [line 639](MolliePaymentGateway.php#L639-L650)

### `mollie-payments-for-woocommerce_before_renewal_payment_created`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$renewal_order` | `\WC_Order` | The WooCommerce order to renew.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Subscription/MollieSubscriptionGateway.php](MollieSubscriptionGateway.php), [line 225](MollieSubscriptionGateway.php#L225-L225)

### `mollie-payments-for-woocommerce_create_payment`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | Data for the payment.
`$renewal_order` | `\WC_Order` | The WooCommerce order to renew.

Source: [./src/Subscription/MollieSubscriptionGateway.php](MollieSubscriptionGateway.php), [line 278](MollieSubscriptionGateway.php#L278-L278), [./src/Payment/PaymentService.php](PaymentService.php), [line 373](PaymentService.php#L373-L385)

### `mollie-payments-for-woocommerce_after_renewal_payment_created`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$payment` | `bool\|MollieOrder\|MolliePayment` | Received payment object.
`$renewal_order` | `\WC_Order` | The order being processed.

Source: [./src/Subscription/MollieSubscriptionGateway.php](MollieSubscriptionGateway.php), [line 376](MollieSubscriptionGateway.php#L376-L376)

### `mollie-payments-for-woocommerce_payment_created`

*Action hook after creating the payment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$paymentObject` | `bool\|\Mollie\WooCommerce\Payment\MollieOrder\|\Mollie\WooCommerce\Payment\MolliePayment` | Received payment object.
`$order` | `\WC_Order` | The order being processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 124](PaymentService.php#L124-L132), [./src/Subscription/MollieSubscriptionGateway.php](MollieSubscriptionGateway.php), [line 367](MollieSubscriptionGateway.php#L367-L367)

### `mollie-payments-for-woocommerce_orderlines_process_items_before_getting_product_id`

*Action hook before processing items.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$cart_item` | `\WC_Order_Item` | The WooCommerce item.

**Changelog**

Version | Description
------- | -----------
`5.1.1` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 287](PaymentService.php#L287-L298)

### `mollie-payments-for-woocommerce_orderlines_process_items_after_processing_item`

*Action hook after processing the items.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$cart_item` | `\WC_Order_Item` | The WooCommerce item.

**Changelog**

Version | Description
------- | -----------
`5.1.1` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 308](PaymentService.php#L308-L319)

### `mollie-payments-for-woocommerce_orderlines_process_items_after_processing_item`

*Action hook after processing the items.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$cart_item` | `\WC_Order_Item` | The WooCommerce item.

**Changelog**

Version | Description
------- | -----------
`5.1.1` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 322](PaymentService.php#L322-L333)

### `mollie-payments-for-woocommerce_after_mandate_created`

*Action hook after the mandate is created.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$paymentObject` | `bool\|MollieOrder\|MolliePayment` | Mollie payment object.
`$order` | `\WC_Order` | WoCommerce order.
`$customerId` | `string` | WoCommerce customer id.
`$mandateId` | `string` | WoCommerce mandate id.

**Changelog**

Version | Description
------- | -----------
`6.0.0` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 795](PaymentService.php#L795-L805)

### `mollie-payments-for-woocommerce_create_refund`

*Action hook after processing the chargeback.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$paymentObject` | `bool\|MollieOrder\|MolliePayment` |  Mollie payment object.
`$order` | `\WC_Order` | The WooCommerce order.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 483](MolliePayment.php#L483-L491)

### `mollie-payments-for-woocommerce_refund_payment_created`

*After Payment Refund has been created*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$refund` | `\Mollie\Api\Resources\Refund` | The refund created
`$order` | `\WC_Order` | The WooCommerce order.

**Changelog**

Version | Description
------- | -----------
`5.3.1` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 505](MolliePayment.php#L505-L513)

### `mollie-payments-for-woocommerce_refund_created`

*After Payment Refund has been created*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$refund` | `\Mollie\Api\Resources\Refund` | The refund created
`$order` | `\WC_Order` | The WooCommerce order.

**Changelog**

Version | Description
------- | -----------
`5.3.1` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 514](MolliePayment.php#L514-L527), [./src/Payment/MollieOrder.php](MollieOrder.php), [line 838](MollieOrder.php#L838-L851), [line 925](MollieOrder.php#L925-L938)

### `mollie-payments-for-woocommerce_refund_order_created`

*Action hook after the refund is created.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$refund` | `\Mollie\Api\Resources\Refund` | Mollie refund object.
`$order` | `\WC_Order` | WoCommerce order.

**Changelog**

Version | Description
------- | -----------
`5.3.1` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 824](MollieOrder.php#L824-L836)


### `mollie-payments-for-woocommerce_refund_amount_created`

*After Refund Amount Created*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$refund` | `\Mollie\Api\Resources\Refund` |Mollie refund object.
`$order` | `\WC_Order` |WoCommerce order.
`$amount` | `string` | The amount to refund.

**Changelog**

Version | Description
------- | -----------
`5.3.1` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 914](MollieOrder.php#L914-L923)


### `mollie-payments-for-woocommerce_line_items_cancelled`

*Canceled Order Lines*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | Data sent to Mollie cancel endpoint
`$order` | `\WC_Order` | WoCommerce order.

**Changelog**

Version | Description
------- | -----------
`5.3.1` |

Source: [./src/Payment/OrderItemsRefunder.php](OrderItemsRefunder.php), [line 206](OrderItemsRefunder.php#L206-L214)

### `mollie-payments-for-woocommerce_refund_items_created`

*Refund Orders Lines*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$refund` | `\Mollie\Api\Resources\Refund` | Refund instance
`$order` | `\WC_Order` | WoCommerce order.
`$data` | `array` | Data sent to Mollie refund endpoint

**Changelog**

Version | Description
------- | -----------
`5.3.1` |

Source: [./src/Payment/OrderItemsRefunder.php](OrderItemsRefunder.php), [line 224](OrderItemsRefunder.php#L224-L233)

### `mollie-payments-for-woocommerce_orderlines_process_items_before_getting_product_id`

*Action hook before processing items.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$cart_item` | `\WC_Order_Item` | The WooCommerce item.

**Changelog**

Version | Description
------- | -----------
`5.1.1` |

Source: [./src/Payment/OrderLines.php](OrderLines.php), [line 97](OrderLines.php#L97-L104)

### `mollie-payments-for-woocommerce_orderlines_process_items_after_processing_item`

*Action hook after processing the items.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$cart_item` | `\WC_Order_Item` | The WooCommerce item.

**Changelog**

Version | Description
------- | -----------
`5.1.1` |

Source: [./src/Payment/OrderLines.php](OrderLines.php), [line 149](OrderLines.php#L149-L156)

### `mollie-payments-for-woocommerce_refunds_processed`

*Action hook after processing the refund.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$payment` | `\Mollie\Api\Resources\Payment\|\Mollie\Api\Resources\Order` | The Mollie payment.
`$order` | `\WC_Order` | The WooCommerce order.

**Changelog**

Version | Description
------- | -----------
`5.0.6` |

Source: [./src/Payment/MollieOrderService.php](MollieOrderService.php), [line 349](MollieOrderService.php#L349-L361)

### `mollie-payments-for-woocommerce_chargebacks_processed`

*Action hook after processing the chargeback.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$payment` | `\Mollie\Api\Resources\Payment\|\Mollie\Api\Resources\Order` | The Mollie payment.
`$order` | `\WC_Order` | The WooCommerce order.

**Changelog**

Version | Description
------- | -----------
`5.0.6` |

Source: [./src/Payment/MollieOrderService.php](MollieOrderService.php), [line 596](MollieOrderService.php#L596-L608)


## Filters
### `mollie_wc_plugin_modules`

*Declare your own module to access the plugin's container data.*

The module must implement the ServiceModule or ExecutableModule interface from the composer package ["inpsyde/modularity"](https://github.com/inpsyde/modularity)

An example could be:
```
$module = new class implements ExecutableModule{
    public function run(ContainerInterface $ccontainer): bool
    {
        $gateways = $container->get('gateway.instances');
        return true;
    }

    public function id(): string
    {
        return __CLASS__;
    }
};

add_filter('mollie_wc_plugin_modules', function($modules) use ($module) {
    array_push($modules, $module);

    return $modules;
});
```

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$modules` | `array` | The array of declared modules.

**Changelog**

Version | Description
------- | -----------
`7.0.4` |

Source: [./mollie-payments-for-woocommerce.php](mollie-payments-for-woocommerce.php), [line 160](mollie-payments-for-woocommerce.php#L160-L170)

### `{$gateway->id}_icon_url`

*Overwrite the returned icon markup.*

A markup of the form '<img src="{your-icon-url-here}" class="mollie-gateway-icon" />' is expected

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$defaultIcon` | `string` | the icon img markup.

**Changelog**

Version | Description
------- | -----------
`6.3.0` |

Source: [./src/Gateway/MolliePaymentGateway.php](MolliePaymentGateway.php), [line 207](MolliePaymentGateway.php#L207-L216)

### `woocommerce_{$gateway->id}_supported_currencies`

*Overwrite the array of supported currencies for every gateway.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$default` | `array` | array of the default supported currencies.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Gateway/MolliePaymentGateway.php](MolliePaymentGateway.php), [line 386](MolliePaymentGateway.php#L386-L396)

### `mollie-payments-for-woocommerce_is_available_billing_country_for_payment_gateways`

*Overwrite the billing country assigned to the customer.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$billingCountry` | `string` | billing country of the customer.

**Changelog**

Version | Description
------- | -----------
`5.0.5` |

Source: [./src/Gateway/MolliePaymentGateway.php](MolliePaymentGateway.php), [line 1111](MolliePaymentGateway.php#L1111-L1122)

### `mollie_api_key_filter`

*Overwrite the Mollie API key.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$apiKey` | `string` | saved in db api key.

**Changelog**

Version | Description
------- | -----------
`2.6.0` |

Source: [./src/SDK/Api.php](Api.php), [line 43](Api.php#L43-L50)

### `mollie-payments-for-woocommerce_api_endpoint`

*Overwrite the Mollie endpoint.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`\Mollie\Api\MollieApiClient::API_ENDPOINT` | `string` | "https://api.mollie.com" endpoint.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/SDK/Api.php](Api.php), [line 79](Api.php#L79-L86)

### `mollie_wc_subscription_plugin_active`

*Declare that there is a subscription plugin active.*

The plugin checks for the WooCommerce Subscriptions plugin
but this can be modified by the filter

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$subscriptionPlugin` | `bool` | is there a subscription plugin active.

**Changelog**

Version | Description
------- | -----------
`6.7.0` |

Source: [./src/Shared/Data.php](Data.php), [line 72](Data.php#L72-L82)

### `mollie-payments-for-woocommerce_is_subscription_payment`

*Declare that the order contains a subscription.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$isSubscription` | `bool` | truthy result means the order contains a subscription.
`$orderId` | `string` | ID of the WooCommerce order.

**Changelog**

Version | Description
------- | -----------
`7.0.0` |

Source: [./src/Shared/Data.php](Data.php), [line 737](Data.php#L737-L745)

### `mollie-payments-for-woocommerce_initial_order_status`

*Overwrite plugin-wide the initial order status.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$initial_order_status` | `string` | initial order status.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Subscription/MollieSubscriptionGateway.php](MollieSubscriptionGateway.php), [line 231](MollieSubscriptionGateway.php#L231-L238)

### `mollie-payments-for-woocommerce_initial_order_status_{$gateway->id}`

*Overwrite gateway-wide the initial order status.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$initial_order_status` | `string` | initial order status.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Subscription/MollieSubscriptionGateway.php](MollieSubscriptionGateway.php), [line 240](MollieSubscriptionGateway.php#L240-L247)

### `woocommerce_{$gateway->id}_args`

*Allow filtering the renewal payment data.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | data to send to Mollie's API.
`$renewal_order` | `string` | the order to be renewed.

**Changelog**

Version | Description
------- | -----------
`2.5.2` |

Source: [./src/Subscription/MollieSubscriptionGateway.php](MollieSubscriptionGateway.php), [line 266](MollieSubscriptionGateway.php#L266-L274)

### `components_settings`

*Filter Component Settings*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$componentsSettings` | `array` | Array of components settings.

Source: [./src/Settings/Page/Components.php](Page/Components.php), [line 37](Page/Components.php#L37-L42), [./src/Settings/Page/MollieSettingsPage.php](Page/MollieSettingsPage.php), [line 88](Page/MollieSettingsPage.php#L88-L96)

### `woocommerce_get_settings_{$gateway->id}`

*Filter Mollie gateway settings*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$mollieSettings` | `array` | The gateway settings.
`$currentSection` | `string` | The current section in settings.

Source: [./src/Settings/Page/MollieSettingsPage.php](Page/MollieSettingsPage.php), [line 98](Page/MollieSettingsPage.php#L98-L102)

### `woocommerce_get_sections_mollie_settings`

**Arguments**

*Filter Mollie settings sections*

Argument | Type | Description
-------- | ---- | -----------
`$sections` | `string` | The gateway settings sections.

Source: [./src/Settings/Page/MollieSettingsPage.php](Page/MollieSettingsPage.php), [line 706](Page/MollieSettingsPage.php#L706-L709)

### `mollie.allowed_language_code_setting`

*Filter Allowed Language Codes*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`self::ALLOWED_LANGUAGE_CODES` | `array`  | The allowed language codes

Source: [./src/Settings/Settings.php](Settings.php), [line 593](Settings.php#L593-L601)

### `woocommerce_{$gateway->id}_args`

*Allow filtering the payment data.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | data to send to Mollie's API.
`$order` | `string` | the order being processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 360](PaymentService.php#L360-L372)

### `woocommerce_{$gateway->id}_args`

*Allow filtering the payment data.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | data to send to Mollie's API.
`$order` | `string` | the order being processed.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 490](PaymentService.php#L490-L502), [line 704](PaymentService.php#L704-L712)

### `mollie-payments-for-woocommerce_initial_order_status`

*Overwrite plugin-wide the initial order status.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$initialOrderStatus` | `string` | initial order status.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 895](PaymentService.php#L895-L904)

### `mollie-payments-for-woocommerce_initial_order_status_{$paymentMethodId}`

*Overwrite gateway-wide the initial order status.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$initialOrderStatus` | `string` | initial order status.

**Changelog**

Version | Description
------- | -----------
`2.0.0` |

Source: [./src/Payment/PaymentService.php](PaymentService.php), [line 906](PaymentService.php#L906-L916)

### `mollie-payments-for-woocommerce_order_status_cancelled`

*Overwrite plugin-wide the status when webhook canceled is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 299](MolliePayment.php#L299-L306)

### `mollie-payments-for-woocommerce_order_status_cancelled_{$gateway->id}`

*Overwrite gateway-wide the status when webhook canceled is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 308](MolliePayment.php#L308-L315)

### `mollie-payments-for-woocommerce_order_status_failed`

*Overwrite plugin-wide the status when webhook failed is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`5.1.0` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 350](MolliePayment.php#L350-L357)

### `mollie-payments-for-woocommerce_order_status_failed_{$gateway->id}`

*Overwrite gateway-wide the status when webhook failed is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`5.1.0` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 359](MolliePayment.php#L359-L366)

### `mollie-payments-for-woocommerce_order_status_expired`

*Overwrite plugin-wide the status when webhook expired is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 416](MolliePayment.php#L416-L423)

### `mollie-payments-for-woocommerce_order_status_expired_{$gateway->id}`

*Overwrite gateway-wide the status when webhook expired is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MolliePayment.php](MolliePayment.php), [line 425](MolliePayment.php#L425-L432)

### `mollie-payments-for-woocommerce_payment_object_metadata`

*Overwrite payment metadata to send to Mollie API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`['order_id' => $order->get_id(), 'order_number' => $order->get_order_number()]` |  |

**Changelog**

Version | Description
------- | -----------
`5.1.0` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 126](MollieOrder.php#L126-L143)

### `mollie-payments-for-woocommerce_order_status_cancelled`

*Overwrite plugin-wide the status when webhook canceled is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 453](MollieOrder.php#L453-L460)

### `mollie-payments-for-woocommerce_order_status_cancelled_{$gateway->id}`

*Overwrite gateway-wide the status when webhook canceled is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 463](MollieOrder.php#L463-L470)

### `mollie-payments-for-woocommerce_order_status_failed`

*Overwrite plugin-wide the status when webhook failed is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`5.1.0` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 506](MollieOrder.php#L506-L513)

### `mollie-payments-for-woocommerce_order_status_failed_{$gateway->id}`

*Overwrite gateway-wide the status when webhook failed is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`5.1.0` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 515](MollieOrder.php#L515-L522)

### `mollie-payments-for-woocommerce_order_status_expired`

*Overwrite plugin-wide the status when webhook expired is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 571](MollieOrder.php#L571-L578)

### `mollie-payments-for-woocommerce_order_status_expired_{$gateway->id}`

*Overwrite gateway-wide the status when webhook expired is triggered.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on canceled.

**Changelog**

Version | Description
------- | -----------
`2.3.0` |

Source: [./src/Payment/MollieOrder.php](MollieOrder.php), [line 580](MollieOrder.php#L580-L587)

### `mollie-payments-for-woocommerce_is_automatic_payment_disabled`

*Overwrite setting for automatic payment disabled.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$default` | `bool` | Default value is false.

**Changelog**

Version | Description
------- | -----------
`7.0.0` |

Source: [./src/Payment/MollieObject.php](MollieObject.php), [line 578](MollieObject.php#L578-L585)

### `mollie-payments-for-woocommerce_return_url`

*Overwrite the return url after Mollie payment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$returnUrl` | `string` | the processed return url.
`$order` | `\WC_Order` | the processed order.

**Changelog**

Version | Description
------- | -----------
`2.1.0` |

Source: [./src/Payment/MollieObject.php](MollieObject.php), [line 781](MollieObject.php#L781-L789)

### `mollie-payments-for-woocommerce_webhook_url`

*Overwrite the webhook url sent to Mollie.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$webhookUrl` | `string` | the processed webhook url.
`$order` | `\WC_Order` | the processed order.

**Changelog**

Version | Description
------- | -----------
`2.1.0` |

Source: [./src/Payment/MollieObject.php](MollieObject.php), [line 815](MollieObject.php#L815-L823)

### `mollie-payments-for-woocommerce_order_status_on_hold`

*Overwrite plugin-wide the status when chargeback is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on hold.

**Changelog**

Version | Description
------- | -----------
`5.0.6` |

Source: [./src/Payment/MollieOrderService.php](MollieOrderService.php), [line 492](MollieOrderService.php#L492-L499)

### `mollie-payments-for-woocommerce_order_status_on_hold_{$gateway->id}`

*Overwrite gateway-wide the status when chargeback is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status on hold.

**Changelog**

Version | Description
------- | -----------
`5.0.6` |

Source: [./src/Payment/MollieOrderService.php](MollieOrderService.php), [line 501](MollieOrderService.php#L501-L508)

### `mollie-payments-for-woocommerce{$refundType}`

*Overwrite plugin-wide the status when refund is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status refunded.

**Changelog**

Version | Description
------- | -----------
`5.0.6` |

Source: [./src/Payment/MollieOrderService.php](MollieOrderService.php), [line 680](MollieOrderService.php#L680-L690)

### `mollie-payments-for-woocommerce{$refundType}{$gateway->id}`

*Overwrite gateway-wide the status when refund is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$newOrderStatus` | `string` | order status refunded.

**Changelog**

Version | Description
------- | -----------
`5.0.6` |

Source: [./src/Payment/MollieOrderService.php](MollieOrderService.php), [line 692](MollieOrderService.php#L692-L702)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>

