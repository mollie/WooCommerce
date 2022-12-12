<?php

declare(strict_types=1);

class WP_Http
{
    /**
     * @return array{headers: array{array-key, string}, body: string, cookies: array}
     */
    public function request(): array
    {
        return [
            'headers' => [],
            'body' => '',
            'cookies' => []
        ];
    }
}
