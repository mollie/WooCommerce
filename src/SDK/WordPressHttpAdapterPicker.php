<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use Mollie\Api\HttpAdapter\MollieHttpAdapterPickerInterface;

class WordPressHttpAdapterPicker implements MollieHttpAdapterPickerInterface
{
    /**
     * @return \GuzzleHttp\ClientInterface|\Mollie\Api\HttpAdapter\MollieHttpAdapterInterface
     */
    public function pickHttpAdapter($httpClient)
    {
        if ($httpClient === null) {
            return new WordPressHttpAdapter();
        }
        return $httpClient;
    }
}
