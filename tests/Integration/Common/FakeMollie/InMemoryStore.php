<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\FakeMollie;

final class InMemoryStore implements FakeMollieStore
{
    /**
     * @var array<string, mixed>
     */
    private array $state = [];

    public function load(): array
    {
        return $this->state;
    }

    public function save(array $state): void
    {
        $this->state = $state;
    }
}
