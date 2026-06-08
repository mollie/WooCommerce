<?php

namespace Mollie\Api\Resources;

class CapabilityCollection extends \Mollie\Api\Resources\BaseCollection
{
    /**
     * @return string
     */
    public function getCollectionResourceName()
    {
        return "capabilities";
    }
}
