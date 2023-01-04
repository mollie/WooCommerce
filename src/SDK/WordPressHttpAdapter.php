<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\CurlConnectTimeoutException;
use Mollie\Api\HttpAdapter\MollieHttpAdapterInterface;
use WP_Error;

class WordPressHttpAdapter implements MollieHttpAdapterInterface
{
    /**
     * HTTP status code for an empty ok response.
     */
    const HTTP_NO_CONTENT = 204;

    /**
     * The maximum number of retries
     */
    public const MAX_RETRIES = 5;

    /**
     * The amount of milliseconds the delay is being increased with on each retry.
     */
    public const DELAY_INCREASE_MS = 1000;

    /**
     * @param string $httpMethod
     * @param string $url
     * @param array $headers
     * @param $httpBody
     *
     * @throws ApiException
     */
    public function send($httpMethod, $url, $headers, $httpBody)
    {
        $headers['Content-Type'] = 'application/json';

        $args = [
            'method' => $httpMethod,
            'body' => $httpBody,
            'headers' => $headers,
            'user-agent' => $headers['User-Agent']
        ];
        $message = '';
        $code = 0;
        for ($i = 0; $i <= self::MAX_RETRIES; $i++) {
            usleep($i * self::DELAY_INCREASE_MS);
            $response = wp_remote_request($url, $args);

            if (!is_wp_error($response)) {
                return $this->parseResponse($response);
            }
            $message =  $response->get_error_message() ?? 'Unknown error';
            $code = is_int($response->get_error_code()) ? $response->get_error_code() : 0;
        }

        throw new ApiException("Unable to connect to Mollie. Maximum number of retries (". self::MAX_RETRIES .") reached. " . $message, $code);
    }

    public function versionString()
    {
        global $wp_version;
        return 'WordPress/'. $wp_version;
    }

    /**
     * @param $response
     * @throws ApiException
     */
    protected function parseResponse($response)
    {
        $statusCode = wp_remote_retrieve_response_code($response);
        $httpBody = wp_remote_retrieve_body($response);
        if (empty($httpBody)) {
            if ($statusCode === self::HTTP_NO_CONTENT) {
                return null;
            }

            throw new ApiException("No response body found.");
        }

        $body = @json_decode($httpBody);

        // GUARDS
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException("Unable to decode Mollie response: '{$response}'.");
        }

        if (isset($body->error)) {
            throw new ApiException($body->error->message);
        }

        if ($statusCode >= 400) {
            $message = "Error executing API call ({$body->status}: {$body->title}): {$body->detail}";

            $field = null;

            if (! empty($body->field)) {
                $field = $body->field;
            }

            if (isset($body->_links, $body->_links->documentation)) {
                $message .= ". Documentation: {$body->_links->documentation->href}";
            }

            if ($httpBody) {
                $message .= ". Request body: {$httpBody}";
            }

            throw new ApiException($message, $statusCode, $field);
        }

        return $body;
    }

}
