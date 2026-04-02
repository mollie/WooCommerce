<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\ApplePayButton;

use Mollie\WooCommerce\Buttons\AbstractExpressButton;

class ApplePayExpressButton extends AbstractExpressButton
{
    /**
     * @var AppleAjaxRequests
     */
    protected $ajaxRequests;

    public function __construct(AppleAjaxRequests $ajaxRequests)
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
        return !empty($_SERVER['HTTPS']) &&
            // phpstan:ignore [dead-code] isEnabledInSettings() is called but not declared in this class or its ancestors; likely missing trait or interface method
            // @phpstan-ignore-next-line
            $this->isEnabledInSettings();
    }

    public function getAjaxHandlers(): array
    {
        return $this->ajaxRequests->getHandlers();
    }

    public function getScriptData(): array
    {
        return [
            // phpstan:ignore [dead-code] getCountryCode() is called but not declared in this class or its ancestors; likely missing trait or interface method
            // @phpstan-ignore-next-line
            'shop' => ['countryCode' => $this->getCountryCode()],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mollie_applepay'),
        ];
    }
}
