<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter
// phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
// phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
// phpcs:disable Inpsyde.CodeQuality.NestingLevel.High
// phpcs:disable NeutronStandard.Functions.DisallowCallUserFunc.CallUserFunc
declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Exception;
use Mollie\Psr\Container\ContainerExceptionInterface;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Container\NotFoundExceptionInterface;
use RangeException;
use RuntimeException;
use UnexpectedValueException;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;
/**
 * Abstract Payment gateway.
 *
 * We need to disable rules below for the PaymentGateway class because it
 * extends WC_Payment_Gateway. We haven't this class in the development
 * environment, but using stubs instead. These stubs have no methods
 * parameters defined, so we cannot let psalm know what parameters are expected.
 * Additionally, WC_Payment_Gateway class and its parent WC_Settings_API are not
 * well-typed, so it causes a lot of false error reports too.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 * @psalm-suppress InvalidPropertyAssignmentValue
 * @psalm-suppress MixedOperand
 * @psalm-suppress MissingParamType
 * @psalm-suppress MixedArrayOffset
 * @psalm-suppress MixedArgument
 */
class PaymentGateway extends WC_Payment_Gateway
{
    protected const TRANSACTION_URL_TEMPLATE_FIELD_NAME = '_transaction_url_template';
    protected ContainerInterface $serviceLocator;
    protected I18n $i18n;
    private ServiceKeyGenerator $serviceKeyGenerator;
    public function __construct(string $id, ContainerInterface $serviceLocator)
    {
        $this->id = $id;
        $this->serviceLocator = $serviceLocator;
        $this->serviceKeyGenerator = new ServiceKeyGenerator($id);
        $this->supports = $this->locate('supports');
        $this->i18n = $serviceLocator->get('payment_gateways.i18n');
        $this->init_settings();
        unset($this->order_button_text);
        unset($this->method_title);
        unset($this->method_description);
        unset($this->icon);
        unset($this->form_fields);
        unset($this->enabled);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_settings_checkout', [$this, 'display_errors']);
        add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'filterVirtualFields'], -1000);
    }
    public function init_settings()
    {
        parent::init_settings();
        do_action($this->id . '_after_init_settings', $this);
    }
    public function get_title(): string
    {
        if (!$this->title) {
            $this->title = $this->locate('title');
        }
        return parent::get_title();
    }
    public function get_description(): string
    {
        if (!$this->description) {
            $this->description = $this->locate('description');
        }
        return parent::get_description();
    }
    /**
     * Detect payment gateway availability.
     *
     * @return bool Whether it is available, true for yes and false for no.
     */
    public function is_available(): bool
    {
        $isAvailable = parent::is_available();
        if (!$isAvailable) {
            return \false;
        }
        $canBeUsed = $this->locate('availability_callback');
        assert(is_callable($canBeUsed));
        return $canBeUsed($this);
    }
    /**
     * @inheritDoc
     */
    public function process_payment($orderId): array
    {
        /**
         * Produce the WC_Order instance first
         * This should never fail unless there's a bug in WC
         * But we need to be verbose here
         */
        try {
            $order = $this->getOrder((string) $orderId);
            $paymentRequestValidator = $this->locate('payment_request_validator');
            assert($paymentRequestValidator instanceof PaymentRequestValidatorInterface);
            $paymentRequestValidator->assertIsValid($order, $this);
        } catch (RuntimeException $exception) {
            wc_add_notice($exception->getMessage(), 'error');
            WC()->session->set('refresh_totals', \true);
            return ['result' => 'failure', 'redirect' => ''];
        }
        $processor = $this->locate('payment_processor');
        assert($processor instanceof PaymentProcessorInterface);
        return $processor->processPayment($order, $this);
    }
    public function get_icon()
    {
        $output = '';
        try {
            $iconService = $this->locate('gateway_icons_renderer');
            assert($iconService instanceof GatewayIconsRendererInterface);
            $output = $iconService->renderIcons();
        } catch (ContainerExceptionInterface $exception) {
            // Silence
        }
        return apply_filters('woocommerce_gateway_icon', $output, $this->id);
    }
    /**
     * Get order by ID or throw exception.
     *
     * @param string $orderId Order ID to get order by.
     *
     * @return WC_Order Found order.
     *
     * @throws RuntimeException If order not found.
     */
    protected function getOrder(string $orderId): WC_Order
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            throw new RuntimeException(sprintf('Failed to process order %1$d, it cannot be found.', $orderId));
        }
        return $order;
    }
    /**
     * Get transaction URL template for order.
     *
     * @param $order
     *
     * @return string
     */
    public function get_transaction_url($order): string
    {
        $this->view_transaction_url = (string) $order->get_meta(self::TRANSACTION_URL_TEMPLATE_FIELD_NAME, \true);
        return parent::get_transaction_url($order);
    }
    /**
     * @inheritDoc
     * @throws Exception
     */
    public function process_refund($orderId, $amount = \null, $reason = '')
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return new WP_Error('refund_order_not_found', $this->i18n->translate('refund_order_not_found', $this->id, ['orderId' => $orderId]));
        }
        $amount = floatval($amount);
        $refundProcessor = $this->locate('refund_processor');
        assert($refundProcessor instanceof RefundProcessorInterface);
        $refundProcessor->refundOrderPayment($order, $amount, $reason);
        return \true;
    }
    /**
     * @inheritDoc
     */
    public function payment_fields(): void
    {
        try {
            $renderer = $this->locate('payment_fields_renderer');
            assert($renderer instanceof PaymentFieldsRendererInterface);
        } catch (ContainerExceptionInterface $exception) {
            parent::payment_fields();
            return;
        }
        try {
            //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $renderer->renderFields();
        } catch (\Throwable $exception) {
            do_action($this->id . '_payment_fields_failure', ['exception' => $exception]);
            echo esc_html($this->i18n->translate('payment_method_not_available', $this->id));
        }
    }
    /**
     * Container-aware re-implementation of the parent method.
     * It first tries to find a dedicated service and falls back to the original implementation
     * if none is found.
     *
     * @param $formFields
     * @param $echo
     *
     * @return string|void
     */
    public function generate_settings_html($formFields = [], $echo = \true)
    {
        if (empty($formFields)) {
            $formFields = $this->get_form_fields();
        }
        $html = '';
        foreach ($formFields as $key => $value) {
            $type = $this->get_field_type($value);
            try {
                /**
                 * Check if we have a dedicated renderer in our service container
                 */
                $fieldRenderer = $this->locate('settings_field_renderer.' . $type);
                assert($fieldRenderer instanceof SettingsFieldRendererInterface);
                $html .= $fieldRenderer->render($key, $value, $this);
            } catch (ContainerExceptionInterface $exception) {
                /**
                 * Fallback to WC core implementation
                 */
                if (method_exists($this, 'generate_' . $type . '_html')) {
                    $html .= $this->{'generate_' . $type . '_html'}($key, $value);
                    continue;
                }
                if (has_filter('woocommerce_generate_' . $type . '_html')) {
                    $html .= apply_filters('woocommerce_generate_' . $type . '_html', '', $key, $value, $this);
                    continue;
                }
                $html .= $this->generate_text_html($key, $value);
            }
        }
        if ($echo) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $html;
        }
        return $html;
    }
    /**
     * @inheritDoc
     *
     * Adds support for groups.
     */
    public function get_custom_attribute_html($data)
    {
        if (!isset($data['custom_attributes'])) {
            $data['custom_attributes'] = [];
        }
        if (isset($data['group'])) {
            $data['custom_attributes']['group'] = $data['group'];
        }
        if (isset($data['group_role'])) {
            $data['custom_attributes']['group_role'] = $data['group_role'];
        }
        $html = parent::get_custom_attribute_html($data);
        return $html;
    }
    /**
     * @inheritDoc
     */
    public function process_admin_options(): bool
    {
        $result = parent::process_admin_options();
        return $result;
    }
    /**
     * @inheritDoc
     *
     * Makes sanitization container-aware.
     * If a  'inpsyde_payment_gateway.settings_field_sanitizer.' . $type service is found
     * then it is used instead of WC core sanitization.
     *
     * Additional exception handling is applied, so a RangeException thrown by any sanitization
     * will be rendered as an error
     */
    public function get_field_value($key, $field, $postData = [])
    {
        $type = $this->get_field_type($field);
        $fieldKey = $this->get_field_key($key);
        $postData = empty($postData) ? $_POST : $postData;
        $value = $postData[$fieldKey] ?? null;
        try {
            if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
                // keeping WC behavior
                // phpcs:ignore Inpsyde.CodeQuality.DisableCallUserFunc.call_user_func_call_user_func
                return call_user_func($field['sanitize_callback'], $value);
            }
            /**
             * Check if we have a dedicated field sanitizer in our service container
             */
            $sanitizer = $this->locateWithFallback('settings_field_sanitizer.' . $key . '_field', $this->locateWithFallback('settings_field_sanitizer.' . $type, null));
            if ($sanitizer instanceof SettingsFieldSanitizerInterface) {
                return $sanitizer->sanitize($key, $value, $this);
            }
            /**
             * Fallback to WC core implementation
             */
            // Look for a validate_FIELDID_field method for special handling.
            if (is_callable([$this, 'validate_' . $key . '_field'])) {
                return $this->{'validate_' . $key . '_field'}($key, $value);
            }
            // Look for a validate_FIELDTYPE_field method.
            if (is_callable([$this, 'validate_' . $type . '_field'])) {
                return $this->{'validate_' . $type . '_field'}($key, $value);
            }
            // Fallback to text.
            return $this->validate_text_field($key, $value);
        } catch (RangeException $exception) {
            $this->add_error(sprintf('Field "%1$s" is invalid: %2$s', $key, $exception->getMessage()));
            return null;
        }
    }
    /**
     * Retrieves configuration for a field.
     *
     * @param string $key The key of the field.
     *
     * @return array The field configuration.
     *
     * @throws RangeException If field not configured.
     * @throws RuntimeException If problem retrieving.
     */
    protected function getFieldConfig(string $key): array
    {
        $fields = $this->get_form_fields();
        if (!isset($fields[$key])) {
            throw new RangeException(sprintf('Field "%1$s" is not configured', $key));
        }
        $field = $fields[$key];
        if (!is_array($field)) {
            throw new UnexpectedValueException(sprintf('Invalid configuration for field "%1$s"', $key));
        }
        return $field;
    }
    /**
     * Retrieves the incoming value of a field with the specified name.
     *
     * @param string $key The field key.
     *
     * @return scalar The value of the field.
     *
     * @throws RangeException If field not configured.
     * @throws RuntimeException If problem retrieving.
     */
    protected function getIncomingFieldValue(string $key)
    {
        $field = $this->getFieldConfig($key);
        /**
         * See https://github.com/woocommerce/woocommerce/issues/32512
         */
        $type = $this->get_field_type($field);
        // Virtual fields only available in storage.
        $value = $type === 'virtual' ? $this->get_option($key) : $this->get_field_value($key, $field);
        return $value;
    }
    /**
     * Returns the value of a field with the specified key.
     *
     * Allows defaults to be overridden.
     *
     * @param string $key The field key.
     *
     * @return scalar The value of the field.
     *
     * @throws RangeException If field not configured.
     * @throws RuntimeException If problem retrieving.
     */
    protected function getFieldValue(string $key)
    {
        $value = $this->getIncomingFieldValue($key);
        if ($value === '') {
            $value = $this->get_option($key);
        }
        return $value;
    }
    /**
     * @inheritDoc
     */
    public function get_option_key()
    {
        try {
            $optionKey = $this->locate('option_key');
            assert(is_string($optionKey));
        } catch (ContainerExceptionInterface $exception) {
            $optionKey = null;
        }
        return $optionKey ?? parent::get_option_key();
    }
    /**
     * Prevents some fields from being saved to the database.
     *
     * It filters fields with the 'virtual' field type
     * and fields with the 'save` attribute set to false.
     * Such fields can be used for safely transferring API credentials or
     * other use-cases that require processing user input without storing it as-is
     *
     * @param array $settings
     *
     * @return array
     */
    public function filterVirtualFields(array $settings): array
    {
        $validFields = array_filter($this->get_form_fields(), static function (array $fieldConfig) {
            if (isset($fieldConfig['save'])) {
                return $fieldConfig['save'] !== \false;
            }
            return $fieldConfig['type'] !== 'virtual';
        });
        $validKeys = array_keys($validFields);
        foreach ($settings as $key => $value) {
            if (!in_array($key, $validKeys, \true)) {
                unset($settings[$key]);
            }
        }
        return $settings;
    }
    /**
     * @param string $key
     *
     * @return mixed
     * @throws NotFoundExceptionInterface  No entry was found for this key.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     */
    private function locate(string $key)
    {
        try {
            return $this->serviceLocator->get($this->serviceKeyGenerator->createKey($key));
        } catch (ContainerExceptionInterface $exception) {
            $globalKey = $this->serviceKeyGenerator->createFallbackKey($key);
            if ($this->serviceLocator->has($globalKey)) {
                return $this->serviceLocator->get($globalKey);
            }
            throw $exception;
        }
    }
    private function locateWithFallback(string $key, $fallback)
    {
        try {
            return $this->locate($key);
        } catch (ContainerExceptionInterface $exception) {
            return $fallback;
        }
    }
    public function has_fields()
    {
        try {
            return (bool) $this->locate('has_fields');
        } catch (ContainerExceptionInterface $exception) {
            return parent::has_fields();
        }
    }
    public function __get($name)
    {
        if ($name === 'enabled') {
            return $this->locate('is_enabled') ? 'yes' : 'no';
        }
        if ($name === 'order_button_text') {
            return $this->locate($name);
        }
        if ($name === 'method_title') {
            return $this->locate($name);
        }
        if ($name === 'method_description') {
            return $this->locate($name);
        }
        if ($name === 'plugin_slug') {
            return $this->locate($name);
        }
        if ($name === 'icon') {
            return $this->locateWithFallback($name, null);
        }
        if ($name === 'form_fields') {
            return $this->locate('form_fields');
        }
        return $this->{$name};
    }
}
