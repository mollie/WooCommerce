<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Closure;
use Mollie\Inpsyde\PaymentGateway\Method\PaymentMethodDefinition;
/**
 * Trait providing functionality to register services for payment methods.
 *
 * This trait is intended to be used in service provider classes that need to
 * dynamically generate and register a set of services based on the provided
 * payment method definitions. Each payment method can define various callback
 * functions and settings customizations, which are then registered as separate
 * services within the container.
 */
trait PaymentMethodServiceProviderTrait
{
    /**
     * Generates an array of service definitions for the given payment methods.
     *
     * For each payment method, this function creates a set of services based on
     * predefined interface methods. Additionally, it registers any custom settings
     * renderers and sanitizers provided by the payment method as separate services.
     *
     * @param PaymentMethodDefinition ...$paymentMethods
     *      The payment method definitions to generate services for.
     * @return array
     *      An associative array where each key is a unique service identifier
     *      and each value is a closure representing the service implementation.
     */
    public function providePaymentMethodServices(PaymentMethodDefinition ...$paymentMethods): array
    {
        //
        $services = [];
        foreach ($paymentMethods as $paymentMethod) {
            $id = $paymentMethod->id();
            $interfaceMethods = ['title' => 'title', 'isEnabled' => 'is_enabled', 'description' => 'description', 'availabilityCallback' => 'availability_callback', 'methodTitle' => 'method_title', 'methodDescription' => 'method_description', 'paymentProcessor' => 'payment_processor', 'paymentRequestValidator' => 'payment_request_validator', 'supports' => 'supports', 'hasFields' => 'has_fields', 'refundProcessor' => 'refund_processor', 'gatewayIconsRenderer' => 'gateway_icons_renderer', 'paymentMethodIconProvider' => 'method_icon_provider', 'paymentFieldsRenderer' => 'payment_fields_renderer', 'formFields' => 'form_fields', 'optionKey' => 'option_key', 'orderButtonText' => 'order_button_text', 'registerBlocks' => 'register_blocks', 'icon' => 'icon'];
            foreach ($interfaceMethods as $method => $serviceKey) {
                $services["payment_gateway.{$id}.{$serviceKey}"] = Closure::fromCallable([$paymentMethod, $method]);
            }
            $settingsCustomization = $paymentMethod->customSettings();
            foreach ($settingsCustomization->renderers() as $fieldType => $renderer) {
                $services["payment_gateway.{$id}.settings_field_renderer.{$fieldType}"] = Closure::fromCallable($renderer);
            }
            foreach ($settingsCustomization->sanitizers() as $fieldType => $sanitizer) {
                $services["payment_gateway.{$id}.settings_field_sanitizer.{$fieldType}"] = Closure::fromCallable($sanitizer);
            }
        }
        return $services;
    }
}
