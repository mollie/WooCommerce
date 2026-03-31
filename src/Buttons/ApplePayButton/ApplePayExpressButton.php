<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons\ApplePayButton;

use Mollie\WooCommerce\Buttons\AbstractExpressButton;
class ApplePayExpressButton extends AbstractExpressButton
{
    /**
     * @var AppleAjaxRequests
     */
    protected $ajaxRequests;
    public function __construct(\Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests $ajaxRequests)
    {
        $this->ajaxRequests = $ajaxRequests;
    }
    public function getId(): string
    {
        return 'applepay';
    }
    public function getButtonComponent(): string
    {
        return 'ApplePayButtonComponent';
    }
    public function canShow(): bool
    {
        return !empty($_SERVER['HTTPS']) && $this->isEnabledInSettings();
    }
    public function getAjaxHandlers(): array
    {
        return $this->ajaxRequests->getHandlers();
    }
    public function getScriptData(): array
    {
        return ['shop' => ['countryCode' => $this->getCountryCode()], 'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mollie_applepay')];
    }
}
