<?php

namespace Mollie\Api\Resources;

class SalesInvoiceCollection extends \Mollie\Api\Resources\CursorCollection
{
    /**
     * @return string
     */
    public function getCollectionResourceName()
    {
        return "sales_invoices";
    }
    /**
     * @return BaseResource
     */
    protected function createResourceObject()
    {
        return new \Mollie\Api\Resources\SalesInvoice($this->client);
    }
}
