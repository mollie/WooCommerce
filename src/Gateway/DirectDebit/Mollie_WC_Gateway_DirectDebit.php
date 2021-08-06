<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\DirectDebit;

use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

class Mollie_WC_Gateway_DirectDebit extends AbstractGateway
{
    /**
     *
     */
    public function __construct(
        IconFactory $iconFactory,
        PaymentService $paymentService,
        SurchargeService $surchargeService,
        MollieOrderService $mollieOrderService,
        Logger $logger,
        NoticeInterface $notice,
        HttpResponse $httpResponse,
        string $pluginUrl,
        string $pluginPath
    ) {

        $this->supports = [
            'products',
            'refunds',
        ];

        parent::__construct(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice,
            $httpResponse,
            $pluginUrl,
            $pluginPath
        );
    }

    /**
     * @return string
     */
    public function getMollieMethodId()
    {
        return PaymentMethod::DIRECTDEBIT;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['display_logo']);
        unset($this->form_fields['description']);
    }

    /**
     * @return string
     */
    public function getDefaultTitle()
    {
        return __('SEPA Direct Debit', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getSettingsDescription()
    {
        return __('SEPA Direct Debit is used for recurring payments with WooCommerce Subscriptions, and will not be shown in the WooCommerce checkout for regular payments! You also need to enable iDEAL and/or other "first" payment methods if you want to use SEPA Direct Debit.', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    protected function paymentConfirmationAfterCoupleOfDays()
    {
        return true;
    }

    /**
     * @param WC_Order                  $order
     * @param Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     *
     * @return string|null
     */
    protected function getInstructions(WC_Order $order, Payment $payment, $admin_instructions, $plain_text)
    {
        if ($payment->isPaid() && $payment->details) {
            return sprintf(
            /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%1$s</strong> (IBAN (last 4 digits): %2$s, BIC: %3$s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                substr($payment->details->consumerAccount, -4),
                $payment->details->consumerBic
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}
