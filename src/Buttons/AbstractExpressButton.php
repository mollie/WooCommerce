<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons;

abstract class AbstractExpressButton implements \Mollie\WooCommerce\Buttons\ExpressButtonInterface
{
    protected $ajaxRequests;
    public function bootstrap()
    {
        if (!$this->canShow()) {
            return;
        }
        $this->registerAjaxHandlers();
        $this->enqueueScripts();
    }
    protected function registerAjaxHandlers()
    {
        foreach ($this->getAjaxHandlers() as $action => $callback) {
            add_action("wp_ajax_{$action}", $callback);
            add_action("wp_ajax_nopriv_{$action}", $callback);
        }
    }
}
