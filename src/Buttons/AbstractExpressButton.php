<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons;

abstract class AbstractExpressButton implements ExpressButtonInterface
{
    /**
     * @var mixed
     */
    protected $ajaxRequests;

    public function bootstrap(): void
    {
        if (!$this->canShow()) {
            return;
        }

        $this->registerAjaxHandlers();
        $this->enqueueScripts();
    }

    protected function enqueueScripts(): void
    {
    }

    protected function registerAjaxHandlers(): void
    {
        foreach ($this->getAjaxHandlers() as $action => $callback) {
            add_action("wp_ajax_{$action}", $callback);
            add_action("wp_ajax_nopriv_{$action}", $callback);
        }
    }
}
