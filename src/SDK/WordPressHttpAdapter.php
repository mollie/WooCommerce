<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\CurlConnectTimeoutException;
use Mollie\Api\HttpAdapter\MollieHttpAdapterInterface;

class WordPressHttpAdapter implements MollieHttpAdapterInterface
{
    /**
     * HTTP status code for an empty ok response.
     */
    const HTTP_NO_CONTENT = 204;
    /**
     * The maximum number of retries
     */
    const MAX_RETRIES = 5;
    /**
     * The amount of milliseconds the delay is being increased with on each retry.
     */
    const DELAY_INCREASE_MS = 1000;

    /**
     * @param string $httpMethod
     * @param string $url
     * @param array $headers
     * @param $httpBody
     *
     * @throws \Mollie\Api\Exceptions\CurlConnectTimeoutException
     */
    public function send($httpMethod, $url, $headers, $httpBody)
    {
        for ($i = 0; $i <= self::MAX_RETRIES; $i++) {
            usleep($i * self::DELAY_INCREASE_MS);

            try {
                return $this->attemptRequest($httpMethod, $httpBody, $headers, $url);
            } catch (ApiException $e) {
                // Nothing
            }
        }

        throw new CurlConnectTimeoutException(
            "Unable to connect to Mollie. Maximum number of retries (" . self::MAX_RETRIES . ") reached."
        );
    }

    public function versionString()
    {
        global $wp_version;
        return 'WordPress/'. $wp_version;
    }

    /**
     * @param $response
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

    /**
     * @param string $httpMethod
     * @param $httpBody
     * @param array $headers
     * @param string $url
     * @return array|\WP_Error
     */
    protected function attemptRequest(string $httpMethod, $httpBody, array $headers, string $url)
    {
        $args = [
            'method' => $httpMethod,
            'body' => $httpBody,
            'headers' => $headers
        ];
        $response = wp_remote_request($url, $args);
        if(is_wp_error($response)){
            throw new ApiException($response->get_error_message());
        }

        return $this->parseResponse($response);
    }
}
