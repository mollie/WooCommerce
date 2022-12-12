<?php

declare(strict_types=1);

class WP_REST_Response
{
    /**
     * @var int
     */
    protected $status;

    public function __construct($data = null, int $status = 200, $headers = array())
    {
        $this->status = $status;
    }

    public function get_status(): int
    {
        return $this->status;
    }
}
